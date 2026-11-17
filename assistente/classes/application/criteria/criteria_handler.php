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

namespace local_assistente\application\criteria;

use local_assistente\application\course_config_service;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\event_handler;
use local_assistente\application\queue\handler_result;
use local_assistente\domain\criteria\criteria_evaluator;
use local_assistente\domain\event\erased_subject;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\moodle\forum_reader;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\persistence\criteria_result_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\frequency_repository;
use local_assistente\infrastructure\persistence\performance_repository;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * Handler of the events `criteria_check_requested` (one student) and `course_criteria_check_requested` (every student of the course): the
 * criteria of the course applied to the students (T093, T141; spec-015, DEC-40, DEC-45 D2, DEC-55). No LLM is called.
 *
 * The performance handler (T078) and the frequency handler (T087) queue the event for a student after saving their analysis, which is the
 * only way the criteria are chained: the posts of a forum reach it through the participation event, and the passing of a reference date
 * through `reference_date_reached`. The identity of the event is coalesced while it is pending, so a burst of analyses of a student is one
 * check, made with the data that are current when it runs.
 *
 * For the student of the event the handler reads the criteria in force now (so that a correction by the administrator reaches only the
 * new checks, DEC-45 D2, quickstart 020 Q8), the course total of the current performance analysis, the posts in each forum of the course
 * through forum_reader, and the rows of the current frequency analysis; it gives them to criteria_evaluator (T091) and saves the statuses
 * through criteria_result_repository (T092). A dimension whose analysis does not exist yet is `no_data`: nothing is waited for, nothing is
 * read as zero. The context of the processing keeps the limits that were used, the values compared, the forums considered and the analyses
 * the values came from.
 *
 * The request for the course (DEC-55 D2, queued once when a reference date passes) is handled in one processing for all the students of the
 * course (`student_reader::get_students`, DEC-47). Everything is read and evaluated before anything is written; then the context and the
 * analysis with one row for each student are written in one transaction, under the lock of the event, so a failure of a reader, of the
 * evaluation or of the persistence leaves no row, no analysis and no context. Because of that a context without an analysis is not a state
 * this handler produces, and if it finds one it fails the attempt instead of attaching an old context to a new attempt. A course with no
 * student writes nothing at all, as one without criteria or not enabled. The context of the course keeps the limits once and, for each
 * student, the values compared, the forums considered and the analyses the values came from.
 *
 * A course that is not enabled or has no criteria writes nothing: no analysis and no `criteria_result` (spec-015). A user who is not a
 * student of the course is done with nothing written. Any failure of a reader or of the persistence fails the attempt and writes nothing
 * (`error`, never a status). Idempotent: a processing that already has its check does nothing.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criteria_handler implements event_handler {
    /** @var int version of the structure of the context of the processing */
    public const CONTEXT_FORMAT = 1;

    /** @var course_config_service */
    private course_config_service $config;

    /** @var criteria_result_repository */
    private criteria_result_repository $repository;

    /** @var performance_repository */
    private performance_repository $performance;

    /** @var frequency_repository */
    private frequency_repository $frequency;

    /** @var course_module_reader */
    private course_module_reader $modules;

    /** @var course_section_reader */
    private course_section_reader $sections;

    /** @var forum_reader */
    private forum_reader $forums;

    /** @var student_reader */
    private student_reader $students;

    /** @var event_repository */
    private event_repository $events;

    /** @var criteria_evaluator */
    private criteria_evaluator $evaluator;

    /** @var \Closure moment of the evaluation, unix time */
    private \Closure $clock;

    /** @var unit_of_work */
    private unit_of_work $unitofwork;

    /**
     * Constructor; every collaborator has a default.
     *
     * @param course_config_service|null $config
     * @param criteria_result_repository|null $repository
     * @param performance_repository|null $performance
     * @param frequency_repository|null $frequency
     * @param course_module_reader|null $modules
     * @param course_section_reader|null $sections
     * @param forum_reader|null $forums
     * @param student_reader|null $students
     * @param event_repository|null $events
     * @param criteria_evaluator|null $evaluator
     * @param callable|null $clock returns the unix time; the real time by default
     * @param unit_of_work|null $unitofwork
     */
    public function __construct(
        ?course_config_service $config = null,
        ?criteria_result_repository $repository = null,
        ?performance_repository $performance = null,
        ?frequency_repository $frequency = null,
        ?course_module_reader $modules = null,
        ?course_section_reader $sections = null,
        ?forum_reader $forums = null,
        ?student_reader $students = null,
        ?event_repository $events = null,
        ?criteria_evaluator $evaluator = null,
        ?callable $clock = null,
        ?unit_of_work $unitofwork = null
    ) {
        $this->config = $config ?? new course_config_service();
        $this->repository = $repository ?? new criteria_result_repository();
        $this->performance = $performance ?? new performance_repository();
        $this->frequency = $frequency ?? new frequency_repository();
        $this->modules = $modules ?? new course_module_reader();
        $this->sections = $sections ?? new course_section_reader();
        $this->forums = $forums ?? new forum_reader();
        $this->students = $students ?? new student_reader();
        $this->events = $events ?? new event_repository();
        $this->evaluator = $evaluator ?? new criteria_evaluator();
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : time(...);
        $this->unitofwork = $unitofwork ?? new unit_of_work();
    }

    #[\Override]
    public function handle(\stdClass $event, \stdClass $processing): handler_result {
        if ($event->event_type === 'course_criteria_check_requested') {
            return $this->handle_course($event, $processing);
        }
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
            return handler_result::success('the criteria check of this processing already exists');
        }

        try {
            if (!$this->config->is_enabled($courseid)) {
                return handler_result::success('the assistant is not enabled in the course: nothing to do');
            }
            $criteria = $this->config->current_criteria($courseid);
            if ($criteria === null) {
                return handler_result::success('the course has no criteria: nothing written');
            }
            if (!$this->students->is_student($courseid, $userid)) {
                return handler_result::success('the user is not a student of the course: nothing to do');
            }

            $now = ($this->clock)();
            [$evaluation, $performanceid, $frequencyid] = $this->evaluate_student(
                $courseid,
                $userid,
                $criteria,
                $now,
                $this->sections->course_end_date($courseid)
            );

            if ($this->events->find_context($processingid) === null) {
                $this->events->save_context($processingid, json_encode([
                    'format' => self::CONTEXT_FORMAT,
                    'kind' => 'criteria_check',
                    'course_id' => $courseid,
                    'student_id' => $userid,
                    'evaluated_at' => $now,
                    'limits' => $evaluation->limits,
                    'activity_value' => $evaluation->activityvalue,
                    'frequency_value' => $evaluation->frequencyvalue,
                    'forums_due' => $evaluation->forumsdue,
                    'forums_missing' => $evaluation->forumsmissing,
                    'performance_analysis_id' => $performanceid,
                    'frequency_analysis_id' => $frequencyid,
                ], JSON_THROW_ON_ERROR), $now);
            }
            $this->repository->save($courseid, $processingid, [['student_id' => $userid, 'evaluation' => $evaluation]]);
        } catch (\Throwable $e) {
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }

        return handler_result::success(sprintf(
            'criteria saved: activity %d, forum %d, frequency %d',
            $evaluation->activity->value,
            $evaluation->forum->value,
            $evaluation->frequency->value
        ));
    }

    /**
     * The criteria of every student of the course in one processing, all or nothing (DEC-55 D2 to D4).
     *
     * @param \stdClass $event
     * @param \stdClass $processing
     * @return handler_result
     */
    private function handle_course(\stdClass $event, \stdClass $processing): handler_result {
        $payload = json_decode((string) $event->payload, true);
        $courseid = (int) $event->course_id;
        $processingid = (int) $processing->id;
        if (!is_array($payload) || ($payload['course_id'] ?? null) !== $courseid) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the event has no valid course');
        }

        if ($this->repository->find_by_processing($processingid) !== null) {
            return handler_result::success('the criteria check of this processing already exists');
        }

        try {
            if (!$this->config->is_enabled($courseid)) {
                return handler_result::success('the assistant is not enabled in the course: nothing to do');
            }
            $criteria = $this->config->current_criteria($courseid);
            if ($criteria === null) {
                return handler_result::success('the course has no criteria: nothing written');
            }
            $studentids = $this->students->get_students($courseid);
            if ($studentids === []) {
                return handler_result::success('the course has no eligible student: nothing written');
            }
            if ($this->events->find_context($processingid) !== null) {
                return handler_result::failure(
                    attempt_failure::TECHNICAL,
                    'the processing has a context and no criteria check: an old context is never attached to a new attempt'
                );
            }

            $now = ($this->clock)();
            $courseend = $this->sections->course_end_date($courseid);
            $results = [];
            $students = [];
            foreach ($studentids as $studentid) {
                [$evaluation, $performanceid, $frequencyid] = $this->evaluate_student($courseid, $studentid, $criteria, $now, $courseend);
                $results[] = ['student_id' => $studentid, 'evaluation' => $evaluation];
                $students[] = [
                    'student_id' => $studentid,
                    'activity_value' => $evaluation->activityvalue,
                    'frequency_value' => $evaluation->frequencyvalue,
                    'forums_due' => $evaluation->forumsdue,
                    'forums_missing' => $evaluation->forumsmissing,
                    'performance_analysis_id' => $performanceid,
                    'frequency_analysis_id' => $frequencyid,
                ];
            }
            $context = json_encode([
                'format' => self::CONTEXT_FORMAT,
                'kind' => 'criteria_check',
                'scope' => 'course',
                'course_id' => $courseid,
                'evaluated_at' => $now,
                'limits' => $results[0]['evaluation']->limits,
                'students' => $students,
            ], JSON_THROW_ON_ERROR);

            // The context and the analysis with all its rows go together or not at all; the lock of the event is the one the queue holds.
            $this->unitofwork->run(function () use ($processingid, $context, $now, $courseid, $results): void {
                $this->events->save_context($processingid, $context, $now);
                $this->repository->save($courseid, $processingid, $results);
            }, [unit_of_work::event_resource((int) $event->id)]);
        } catch (\Throwable $e) {
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }

        return handler_result::success(sprintf('criteria saved for %d students', count($results)));
    }

    /**
     * Read the sources of a student and apply the criteria.
     *
     * @param int $courseid
     * @param int $userid
     * @param \stdClass $criteria the criteria in force
     * @param int $now
     * @param int|null $courseend end date of the course
     * @return array [criteria_evaluation, id of the performance analysis or null, id of the frequency analysis or null]
     */
    private function evaluate_student(int $courseid, int $userid, \stdClass $criteria, int $now, ?int $courseend): array {
        [$total, $performanceid] = $this->course_total($courseid, $userid);
        [$rows, $frequencyid] = $this->frequency_rows($courseid, $userid);
        $evaluation = $this->evaluator->evaluate([
            'activity_criterion' => (float) $criteria->activity_criterion,
            'forum_criterion' => (int) $criteria->forum_criterion,
            'frequency_criterion' => (float) $criteria->frequency_criterion,
        ], $total, $this->forum_facts($courseid, $userid, $courseend), $rows, $now);
        return [$evaluation, $performanceid, $frequencyid];
    }

    /**
     * The course total of the student in the current performance analysis, kept in the context of its processing (DEC-37 D3).
     *
     * @param int $courseid
     * @param int $userid
     * @return array [total in percent or null, id of the analysis or null]
     */
    private function course_total(int $courseid, int $userid): array {
        $analysis = $this->performance->find_current_for_student($courseid, $userid);
        if ($analysis === null) {
            return [null, null];
        }
        $context = $this->events->find_context((int) $analysis->processing_id);
        $data = $context === null ? null : json_decode((string) $context->context_json, true);
        $total = is_array($data) ? ($data['course_total_percent'] ?? null) : null;
        return [$total === null ? null : (float) $total, (int) $analysis->id];
    }

    /**
     * The rows by section of the student in the current frequency analysis.
     *
     * @param int $courseid
     * @param int $userid
     * @return array [rows for the evaluator, id of the analysis or null]
     */
    private function frequency_rows(int $courseid, int $userid): array {
        $analysis = $this->frequency->find_current_for_student($courseid, $userid);
        if ($analysis === null) {
            return [[], null];
        }
        $rows = [];
        foreach ($this->frequency->find_frequencies((int) $analysis->id, $userid) as $row) {
            $rows[] = [
                'total_activities' => (int) $row->total_activities,
                'participating_activities' => (int) $row->participating_activities,
            ];
        }
        return [$rows, (int) $analysis->id];
    }

    /**
     * The facts of the forums of the course for the student (DEC-39 D1 and D2): every forum module is eligible, it counts if the student can
     * see it, and its reference date is the delivery or closing time, or else the end of the course.
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $courseend end date of the course
     * @return array[]
     */
    private function forum_facts(int $courseid, int $userid, ?int $courseend): array {
        $facts = [];
        foreach ($this->modules->get_modules($courseid, $userid) as $module) {
            if ($module->modname !== 'forum' || $module->is_resource) {
                continue;
            }
            $due = (int) $module->due_date;
            $facts[] = [
                'cmid' => (int) $module->cmid,
                'eligible' => true,
                'available' => (bool) $module->available_to_user,
                'reference_date' => $due > 0 ? $due : $courseend,
                'posted' => $this->forums->has_posted((int) $module->cmid, $userid),
            ];
        }
        return $facts;
    }
}
