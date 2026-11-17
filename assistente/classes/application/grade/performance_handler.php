<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_assistente\application\grade;

use local_assistente\application\criteria\criteria_request_service;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\synthesis\synthesis_trigger;
use local_assistente\domain\event\erased_subject;
use local_assistente\application\queue\event_handler;
use local_assistente\application\queue\handler_result;
use local_assistente\domain\grade\grade_normalizer;
use local_assistente\infrastructure\moodle\gradebook_reader;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\performance_repository;

/**
 * Handler of the event `grade_changed`: the performance of a student in a course (T078; spec-012; DEC-24, DEC-37). No LLM is called.
 *
 * Each event recalculates the whole student in the course: one immutable `grade_performance` analysis (T077) with one row for each
 * graded activity, in percent of the range of its item (T076), and the final total of the course, which the gradebook computes with the
 * weights and the aggregation the teacher configured, in the context of the processing as the average of the student (DEC-37 D3). The
 * plugin has no weight and calculates no average.
 *
 * Activities are the main grade item (item number 0) of each activity module, with a numeric grade or a scale; categories, the course
 * total, manual items and items with no grade type or text only are not activities. An activity with no grade, or whose item has a null
 * range, has no row (`no_data`); a grade of zero is a row. A technical failure fails the attempt and writes nothing (`error`, never
 * zero). A grade removed leaves the activity without a row in the next analysis, and the earlier analysis stays as it was.
 *
 * When the analysis is saved, or already was, the handler asks for the criteria of the student to be applied (T093, DEC-55); a failure to
 * queue that request fails the attempt, and the retry asks again.
 *
 * Only a student of the course is analysed (active enrolment and a role the gradebook evaluates, DEC-47); for another user the event
 * is done with nothing written. Idempotent: if the processing already has its analysis, nothing is read or written.
 *
 * Gap kept explicit: a module with more than one grade item has one row only, for the main item, because the schema keeps one row for
 * each activity of a student.
 *
 * When the analysis is saved, or already was, it asks for the synthesis of the section of the module that the grade item of the event grades (an item that is not of a module asks for none) (T098, DEC-41 D3); a failure to queue it fails the
 * attempt, and the retry asks again.
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class performance_handler implements event_handler {
    /** @var int version of the structure of the context of the processing */
    public const CONTEXT_FORMAT = 1;

    /** @var string[] grade types that count as an activity grade */
    private const GRADED_TYPES = ['value', 'scale'];

    /** @var performance_repository */
    private performance_repository $repository;

    /** @var gradebook_reader */
    private gradebook_reader $gradebook;

    /** @var student_reader */
    private student_reader $students;

    /** @var event_repository */
    private event_repository $events;

    /** @var grade_normalizer */
    private grade_normalizer $normalizer;

    /** @var criteria_request_service */
    private criteria_request_service $criteria;

    /** @var synthesis_trigger */
    private synthesis_trigger $synthesis;

    /**
     * Constructor; every collaborator has a default.
     *
     * @param performance_repository|null $repository
     * @param gradebook_reader|null $gradebook
     * @param student_reader|null $students
     * @param event_repository|null $events
     * @param grade_normalizer|null $normalizer
     * @param criteria_request_service|null $criteria
     * @param synthesis_trigger|null $synthesis
     */
    public function __construct(
        ?performance_repository $repository = null,
        ?gradebook_reader $gradebook = null,
        ?student_reader $students = null,
        ?event_repository $events = null,
        ?grade_normalizer $normalizer = null,
        ?criteria_request_service $criteria = null,
        ?synthesis_trigger $synthesis = null
    ) {
        $this->repository = $repository ?? new performance_repository();
        $this->gradebook = $gradebook ?? new gradebook_reader();
        $this->students = $students ?? new student_reader();
        $this->events = $events ?? new event_repository();
        $this->normalizer = $normalizer ?? new grade_normalizer();
        $this->criteria = $criteria ?? new criteria_request_service();
        $this->synthesis = $synthesis ?? new synthesis_trigger();
    }

    #[\Override]
    public function handle(\stdClass $event, \stdClass $processing): handler_result {
        $started = microtime(true);
        $payload = json_decode((string) $event->payload, true);
        $userid = is_array($payload) ? ($payload['userid'] ?? null) : null;
        if ($userid === erased_subject::USERID) {
            // The student's data were erased (DEC-57): the event is done, and nothing individual is made again.
            return handler_result::success(erased_subject::RESPONSE);
        }
        if (!is_int($userid) || $userid <= 0) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the event has no valid userid');
        }
        $courseid = (int) $event->course_id;
        $processingid = (int) $processing->id;

        if ($this->repository->find_by_processing($processingid) !== null) {
            try {
                $this->criteria->request($courseid, $userid);
                $this->synthesis->request_for_grade_item($courseid, (int) ($payload['itemid'] ?? 0));
            } catch (\Throwable $e) {
                return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
            }
            return handler_result::success('the performance of this processing already exists');
        }

        try {
            if (!$this->students->is_student($courseid, $userid)) {
                return handler_result::success('the user is not a student of the course: nothing to do');
            }

            $rows = [];
            foreach ($this->gradebook->get_items($courseid) as $item) {
                if ($item->item_number !== 0 || !in_array($item->grade_type, self::GRADED_TYPES, true)) {
                    continue;
                }
                $grade = $this->gradebook->get_final_grade($courseid, $item->cmid, $userid);
                if ($grade === null || $grade->final_grade === null) {
                    continue;
                }
                $percentage = $this->normalizer->percentage($grade->final_grade, $grade->grade_min, $grade->grade_max);
                if ($percentage !== null) {
                    $rows[] = ['activity_id' => $item->cmid, 'grade' => $percentage];
                }
            }

            $total = $this->gradebook->get_course_total($courseid, $userid);
            $average = $total->final_grade === null
                ? null
                : $this->normalizer->percentage($total->final_grade, $total->grade_min, $total->grade_max);

            if ($this->events->find_context($processingid) === null) {
                $this->events->save_context($processingid, json_encode([
                    'format' => self::CONTEXT_FORMAT,
                    'kind' => 'grade_performance',
                    'student_id' => $userid,
                    'course_total_percent' => $average,
                    'activities_with_grade' => count($rows),
                ], JSON_THROW_ON_ERROR), time());
            }
            $this->repository->save($courseid, $processingid, $userid, $rows);
            $this->criteria->request($courseid, $userid);
            $this->synthesis->request_for_grade_item($courseid, (int) $payload['itemid']);
        } catch (\Throwable $e) {
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }

        return handler_result::success(sprintf(
            'performance saved: %d activities with grade, course total %s, %.2f s',
            count($rows),
            $average === null ? 'absent' : sprintf('%.3f', $average),
            microtime(true) - $started
        ));
    }
}
