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

namespace local_assistente\application\frequency;

use local_assistente\application\criteria\criteria_request_service;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\synthesis\synthesis_trigger;
use local_assistente\application\queue\event_handler;
use local_assistente\application\queue\handler_result;
use local_assistente\domain\event\erased_subject;
use local_assistente\domain\frequency\frequency_calculator;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\moodle\gradebook_reader;
use local_assistente\infrastructure\moodle\participation_reader;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\frequency_repository;

/**
 * Handler of the events of participation and of the reference dates: the frequency of the students by section (T087; spec-014, DEC-25,
 * DEC-39, DEC-24). No LLM is called.
 *
 * An event of participation (`participation_changed`) recalculates the student of the event in the course; an event of reference date
 * (`reference_date_reached`, queued by the scheduled task T088) recalculates every student of the course (DEC-39 D2). For each student the
 * handler reads the facts of each module through the existing readers (modules and availability for that student, grade items, completion
 * and participation signals), groups them by section as Moodle holds them now, hands each section to frequency_calculator (T085) and saves
 * the results, one analysis in all, through frequency_repository (T086). The percentage is the one of the calculator, unchanged.
 *
 * Categories of an activity: `graded` when the gradebook has a numeric or scale item for the module, `completion` when completion is
 * enabled for it, `submission` for an assignment and `forum` for a forum. Resources are told by the module reader (DEC-39 D1). Only
 * sections with at least one module get a row; a section whose modules are not in the denominator gets a row with total zero (`no_data`).
 *
 * The moment of the calculation is the clock of the handler, and "the date has passed" is the rule of the calculator: strictly after.
 * The context of the processing records, for each student, whether the student accessed the course and the last access, as evidence only
 * (DEC-39 D3), and the activities left out for having no date at all (DEC-39 D2); none of it changes the percentage.
 *
 * When the analysis is saved, or already was, the handler asks for the criteria to be applied (T093, T141, DEC-55): once for the whole
 * course after a reference date, and for each student of the analysis after an event of participation. A failure to queue a request fails
 * the attempt, and the retry asks again.
 *
 * Any failure of a reader fails the attempt and writes nothing (`error`): a failure is never a frequency of zero. A user who is not a
 * student of the course is done with nothing written. Idempotent: a processing that already has its analysis does nothing.
 *
 * When the analysis is saved, or already was, it asks for the synthesis of each section that has a row in the analysis (T098, DEC-41 D3); a failure to queue it fails the
 * attempt, and the retry asks again.
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class frequency_handler implements event_handler {
    /** @var int version of the structure of the context of the processing */
    public const CONTEXT_FORMAT = 1;

    /** @var frequency_repository */
    private frequency_repository $repository;

    /** @var course_module_reader */
    private course_module_reader $modules;

    /** @var course_section_reader */
    private course_section_reader $sections;

    /** @var gradebook_reader */
    private gradebook_reader $gradebook;

    /** @var participation_reader */
    private participation_reader $participation;

    /** @var student_reader */
    private student_reader $students;

    /** @var event_repository */
    private event_repository $events;

    /** @var frequency_calculator */
    private frequency_calculator $calculator;

    /** @var \Closure moment of the calculation, unix time */
    private \Closure $clock;

    /** @var criteria_request_service */
    private criteria_request_service $criteria;

    /** @var synthesis_trigger */
    private synthesis_trigger $synthesis;

    /**
     * Constructor; every collaborator has a default.
     *
     * @param frequency_repository|null $repository
     * @param course_module_reader|null $modules
     * @param course_section_reader|null $sections
     * @param gradebook_reader|null $gradebook
     * @param participation_reader|null $participation
     * @param student_reader|null $students
     * @param event_repository|null $events
     * @param frequency_calculator|null $calculator
     * @param callable|null $clock returns the unix time; the real time by default
     * @param criteria_request_service|null $criteria
     * @param synthesis_trigger|null $synthesis
     */
    public function __construct(
        ?frequency_repository $repository = null,
        ?course_module_reader $modules = null,
        ?course_section_reader $sections = null,
        ?gradebook_reader $gradebook = null,
        ?participation_reader $participation = null,
        ?student_reader $students = null,
        ?event_repository $events = null,
        ?frequency_calculator $calculator = null,
        ?callable $clock = null,
        ?criteria_request_service $criteria = null,
        ?synthesis_trigger $synthesis = null
    ) {
        $this->repository = $repository ?? new frequency_repository();
        $this->modules = $modules ?? new course_module_reader();
        $this->sections = $sections ?? new course_section_reader();
        $this->gradebook = $gradebook ?? new gradebook_reader();
        $this->participation = $participation ?? new participation_reader();
        $this->students = $students ?? new student_reader();
        $this->events = $events ?? new event_repository();
        $this->calculator = $calculator ?? new frequency_calculator();
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : time(...);
        $this->criteria = $criteria ?? new criteria_request_service();
        $this->synthesis = $synthesis ?? new synthesis_trigger();
    }

    #[\Override]
    public function handle(\stdClass $event, \stdClass $processing): handler_result {
        $started = microtime(true);
        $payload = json_decode((string) $event->payload, true);
        if (!is_array($payload) || !is_int($payload['cmid'] ?? null)) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the event has no valid module');
        }
        $courseid = (int) $event->course_id;
        $processingid = (int) $processing->id;

        $existing = $this->repository->find_by_processing($processingid);
        if ($existing !== null) {
            try {
                $this->request_criteria($courseid, $event->event_type === 'reference_date_reached', array_column(
                    $this->repository->find_frequencies((int) $existing->id),
                    'student_id'
                ));
                $this->synthesis->request_sections($courseid, array_column(
                    $this->repository->find_frequencies((int) $existing->id),
                    'section_id'
                ));
            } catch (\Throwable $e) {
                return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
            }
            return handler_result::success('the frequency of this processing already exists');
        }

        try {
            $everyone = $event->event_type === 'reference_date_reached';
            if ($everyone) {
                $studentids = $this->students->get_students($courseid);
            } else {
                $userid = $payload['userid'] ?? null;
                if ($userid === erased_subject::USERID) {
                    // The student's data were erased (DEC-57): the event is done, and nothing individual is made again.
                    return handler_result::success(erased_subject::RESPONSE);
                }
                if (!is_int($userid) || $userid <= 0) {
                    return handler_result::failure(attempt_failure::TECHNICAL, 'the event has no valid userid');
                }
                if (!$this->students->is_student($courseid, $userid)) {
                    return handler_result::success('the user is not a student of the course: nothing to do');
                }
                $studentids = [$userid];
            }

            $now = ($this->clock)();
            $courseend = $this->sections->course_end_date($courseid);
            $items = $this->graded_modules($courseid);
            $rows = [];
            $evidence = [];
            $withoutdate = [];
            foreach ($studentids as $studentid) {
                foreach ($this->student_sections($courseid, $studentid, $items) as $sectionid => $facts) {
                    $result = $this->calculator->calculate($facts, $courseend, $now);
                    $rows[] = ['student_id' => $studentid, 'section_id' => $sectionid, 'result' => $result];
                    $withoutdate = array_merge($withoutdate, $result->withoutdate);
                }
                $last = $this->participation->get_last_course_access($courseid, $studentid);
                $evidence[] = ['student_id' => $studentid, 'accessed' => $last !== null, 'last_access' => $last];
            }

            if ($this->events->find_context($processingid) === null) {
                $this->events->save_context($processingid, json_encode([
                    'format' => self::CONTEXT_FORMAT,
                    'kind' => 'frequency',
                    'course_id' => $courseid,
                    'calculated_at' => $now,
                    'course_access' => $evidence,
                    'activities_without_date' => array_values(array_unique($withoutdate)),
                ], JSON_THROW_ON_ERROR), $now);
            }
            $this->repository->save($courseid, $processingid, $rows);
            $this->request_criteria($courseid, $everyone, array_column($rows, 'student_id'));
            $this->synthesis->request_sections($courseid, array_column($rows, 'section_id'));
        } catch (\Throwable $e) {
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }

        return handler_result::success(sprintf(
            'frequency saved: %d students, %d rows by section, %.2f s',
            count($studentids),
            count($rows),
            microtime(true) - $started
        ));
    }

    /**
     * Ask for the criteria to be applied: one request for the course when the analysis is of the whole course, else one for each student.
     *
     * @param int $courseid
     * @param bool $wholecourse whether the analysis is of every student of the course (a reference date)
     * @param int[] $studentids
     */
    private function request_criteria(int $courseid, bool $wholecourse, array $studentids): void {
        if ($wholecourse) {
            $this->criteria->request_course($courseid);
            return;
        }
        foreach (array_unique(array_map('intval', $studentids)) as $studentid) {
            $this->criteria->request($courseid, $studentid);
        }
    }

    /**
     * Modules of the course that have a numeric or scale item in the gradebook.
     *
     * @param int $courseid
     * @return array<int, true> by cmid
     */
    private function graded_modules(int $courseid): array {
        $graded = [];
        foreach ($this->gradebook->get_items($courseid) as $item) {
            if (in_array($item->grade_type, ['value', 'scale'], true)) {
                $graded[$item->cmid] = true;
            }
        }
        return $graded;
    }

    /**
     * The facts of the modules for a student, grouped by the section of each module.
     *
     * @param int $courseid
     * @param int $studentid
     * @param array<int, true> $graded modules with a numeric or scale item
     * @return array<int, array[]> facts for the calculator by section id
     */
    private function student_sections(int $courseid, int $studentid, array $graded): array {
        $bysection = [];
        foreach ($this->modules->get_modules($courseid, $studentid) as $module) {
            $signals = $this->participation->get_participation($courseid, $studentid, $module->cmid);
            if ($signals === null) {
                continue;
            }
            $categories = [];
            isset($graded[$module->cmid]) && $categories[] = 'graded';
            $signals->completion_enabled && $categories[] = 'completion';
            $module->modname === 'assign' && $categories[] = 'submission';
            $module->modname === 'forum' && $categories[] = 'forum';
            $bysection[$module->section_id][] = [
                'activity_id' => $module->cmid,
                'is_resource' => $module->is_resource,
                'categories' => $categories,
                'available' => (bool) $module->available_to_user,
                'due_date' => $module->due_date,
                'participation' => [
                    'submitted' => $signals->has_submission,
                    'attempt_finished' => $signals->has_finished_attempt,
                    'graded' => $signals->has_grade,
                    'posted' => $signals->has_post,
                    'completion' => match ($signals->completion_state) {
                        'complete' => 'complete',
                        'complete_pass' => 'pass',
                        'complete_fail' => 'fail',
                        default => null,
                    },
                ],
            ];
        }
        return $bysection;
    }
}
