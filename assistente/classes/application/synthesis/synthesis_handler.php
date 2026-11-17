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


namespace local_assistente\application\synthesis;

use local_assistente\application\context\context_snapshot_builder;
use local_assistente\application\llm\instruction_builder;
use local_assistente\application\llm\llm_result_adapter;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\event_handler;
use local_assistente\application\queue\handler_result;
use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use local_assistente\domain\synthesis\comparable_set;
use local_assistente\domain\synthesis\fingerprint;
use local_assistente\infrastructure\llm\adapter_factory;
use local_assistente\infrastructure\llm\llm_port;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\moodle\gradebook_reader;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\forum_analysis_repository;
use local_assistente\infrastructure\persistence\frequency_repository;
use local_assistente\infrastructure\persistence\performance_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use local_assistente\infrastructure\persistence\quiz_results_repository;
use local_assistente\infrastructure\persistence\synthesis_repository;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * Handler of the event `synthesis_requested`: the pedagogical synthesis of a course section (T097; spec-016, DEC-03, DEC-04, DEC-13, DEC-19,
 * DEC-41, DEC-46). The LLM is called through the port, and only here.
 *
 * Flow, as in the contract of the specification:
 * 1. The section of the event (`section_id`) is validated with the course (T021). An invalid one is a technical failure of the attempt, with
 *    the reason recorded in it, and writes nothing (the same treatment the other handlers give to a section that does not resolve).
 * 2. The comparable set of the section is built from the current analyses of the course (performance and frequency of the students,
 *    questionnaires, forums) and from the summaries of the eligible materials of the context snapshot (T064). Only aggregates and texts
 *    already produced; no student, no individual grade, no criteria of spec-015 (T095). Without any pedagogical result the event is done
 *    with no synthesis: the response records that it waits for one, and the next analysis of the section asks again.
 * 3. The fingerprint of the set is compared with the one of the current version. Equal: done, with no call to the LLM and no version
 *    (DEC-04, DEC-19: only the set decides, never the text of the answer).
 * 4. Otherwise the port is called, with no transaction open, with the set and the instruction in the generation language (DEC-46). A
 *    failed or invalid answer fails the attempt and no version is made; the current one stays.
 * 5. Under the lock of the section (the one that serializes the writers of its versions) and in one transaction, the comparison is made
 *    again against the current version and, if it is still new, the context snapshot and the version are written together
 *    (`synthesis_repository`, T096), so that two processings of one section never leave two versions with the same information, and a
 *    failure leaves neither a version nor a context. The snapshot holds the generation language, which is how the language is recorded.
 *
 * The comparison is with the current version only: information that goes back to the value of an older version is a change relative to the
 * current one and makes a new version. The content is kept as text; whoever shows it escapes it.
 *
 * Idempotent: a processing that already has its synthesis does nothing. A change in one section never touches another (DEC-13).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class synthesis_handler implements event_handler {
    /** @var string task of the synthesis (spec-009 Q-009-05); the language directive is added by instruction_builder */
    public const SYNTHESIS_TASK = 'Write a pedagogical synthesis of one course section for the teacher, from the aggregated results, the summaries of the '
        . 'forums and of the materials that are given. Use only the data given, mention no student, and write plain text only.';

    /** @var llm_port */
    private llm_port $port;

    /** @var synthesis_repository */
    private synthesis_repository $repository;

    /** @var course_section_reader */
    private course_section_reader $sections;

    /** @var course_module_reader */
    private course_module_reader $modules;

    /** @var gradebook_reader */
    private gradebook_reader $gradebook;

    /** @var student_reader */
    private student_reader $students;

    /** @var performance_repository */
    private performance_repository $performance;

    /** @var frequency_repository */
    private frequency_repository $frequency;

    /** @var quiz_initial_repository */
    private quiz_initial_repository $initial;

    /** @var quiz_results_repository */
    private quiz_results_repository $results;

    /** @var forum_analysis_repository */
    private forum_analysis_repository $forums;

    /** @var context_snapshot_builder */
    private context_snapshot_builder $snapshots;

    /** @var event_repository */
    private event_repository $events;

    /** @var instruction_builder */
    private instruction_builder $instructions;

    /** @var unit_of_work */
    private unit_of_work $unitofwork;

    /**
     * Constructor; every collaborator has a default.
     *
     * @param llm_port|null $port the port to the LLM; the one of the adapter factory by default
     * @param synthesis_repository|null $repository
     * @param course_section_reader|null $sections
     * @param course_module_reader|null $modules
     * @param gradebook_reader|null $gradebook
     * @param student_reader|null $students
     * @param performance_repository|null $performance
     * @param frequency_repository|null $frequency
     * @param quiz_initial_repository|null $initial
     * @param quiz_results_repository|null $results
     * @param forum_analysis_repository|null $forums
     * @param context_snapshot_builder|null $snapshots
     * @param event_repository|null $events
     * @param instruction_builder|null $instructions
     * @param unit_of_work|null $unitofwork
     */
    public function __construct(
        ?llm_port $port = null,
        ?synthesis_repository $repository = null,
        ?course_section_reader $sections = null,
        ?course_module_reader $modules = null,
        ?gradebook_reader $gradebook = null,
        ?student_reader $students = null,
        ?performance_repository $performance = null,
        ?frequency_repository $frequency = null,
        ?quiz_initial_repository $initial = null,
        ?quiz_results_repository $results = null,
        ?forum_analysis_repository $forums = null,
        ?context_snapshot_builder $snapshots = null,
        ?event_repository $events = null,
        ?instruction_builder $instructions = null,
        ?unit_of_work $unitofwork = null
    ) {
        $this->port = $port ?? adapter_factory::create();
        $this->repository = $repository ?? new synthesis_repository();
        $this->sections = $sections ?? new course_section_reader();
        $this->modules = $modules ?? new course_module_reader();
        $this->gradebook = $gradebook ?? new gradebook_reader();
        $this->students = $students ?? new student_reader();
        $this->performance = $performance ?? new performance_repository();
        $this->frequency = $frequency ?? new frequency_repository();
        $this->initial = $initial ?? new quiz_initial_repository();
        $this->results = $results ?? new quiz_results_repository();
        $this->forums = $forums ?? new forum_analysis_repository();
        $this->snapshots = $snapshots ?? new context_snapshot_builder();
        $this->events = $events ?? new event_repository();
        $this->instructions = $instructions ?? new instruction_builder($this->sections);
        $this->unitofwork = $unitofwork ?? new unit_of_work();
    }

    #[\Override]
    public function handle(\stdClass $event, \stdClass $processing): handler_result {
        $payload = json_decode((string) $event->payload, true);
        $sectionid = is_array($payload) ? ($payload['section_id'] ?? null) : null;
        if (!is_int($sectionid) || $sectionid <= 0) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the event has no valid section');
        }
        $courseid = (int) $event->course_id;
        $processingid = (int) $processing->id;

        if ($this->repository->find_by_processing($processingid) !== null) {
            return handler_result::success('the synthesis of this processing already exists');
        }

        try {
            $section = $this->sections->validate_section($courseid, $sectionid);
            if (!$section->is_resolved()) {
                return handler_result::failure(
                    attempt_failure::TECHNICAL,
                    'the section of the synthesis is not valid in the course (' . $section->status->value . '): no synthesis'
                );
            }

            $studentids = $this->students->get_students($courseid);
            $snapshot = $this->snapshots->build($courseid, $sectionid, $studentids);
            $set = comparable_set::from($this->facts($courseid, $sectionid, $studentids, $snapshot));
            if (!$set->has_pedagogical_result()) {
                return handler_result::success('pending: the section has no pedagogical result yet, so no synthesis was made');
            }

            $print = fingerprint::of($set);
            $current = $this->repository->find_current($courseid, $sectionid);
            if ($current !== null && $current->input_fingerprint === $print) {
                return handler_result::success('unchanged: the information of the section is the same as in version ' . $current->version);
            }

            $answer = $this->port->evaluate(
                new llm_context(llm_use_case::PEDAGOGICAL_SYNTHESIS, ['comparable_set' => $set->to_array()]),
                $this->instructions->for_course($courseid, self::SYNTHESIS_TASK)
            );
            if (!$answer->is_valid()) {
                return llm_result_adapter::failure_of($answer);
            }

            $version = $this->unitofwork->run(function () use ($courseid, $sectionid, $processingid, $answer, $print, $snapshot): ?\stdClass {
                $latest = $this->repository->find_current($courseid, $sectionid);
                if ($latest !== null && $latest->input_fingerprint === $print) {
                    return null;
                }
                $this->events->save_context(
                    $processingid,
                    json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    (int) $snapshot['created_at']
                );
                return $this->repository->save($courseid, $sectionid, $processingid, $answer->data['content'], $print);
            }, [unit_of_work::section_resource($courseid, $sectionid)]);
        } catch (\Throwable $e) {
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }

        return $version === null
            ? handler_result::success('unchanged: another processing saved the same information while this one waited')
            : handler_result::success('synthesis saved: version ' . $version->version);
    }

    /**
     * The facts of the section for the comparable set, from the current analyses (spec-016 contract).
     *
     * @param int $courseid
     * @param int $sectionid
     * @param int[] $studentids the students of the course
     * @param array $snapshot the context snapshot of the materials
     * @return array
     */
    private function facts(int $courseid, int $sectionid, array $studentids, array $snapshot): array {
        $inside = array_filter(
            $this->modules->get_modules($courseid),
            static fn($module) => $module->section_id === $sectionid && !$module->is_resource
        );
        $graded = [];
        foreach ($this->gradebook->get_items($courseid) as $item) {
            if ($item->item_number === 0 && in_array($item->grade_type, ['value', 'scale'], true) && isset($inside[$item->cmid])) {
                $graded[$item->cmid] = true;
            }
        }

        // The current performance and frequency of each student; a student with no analysis has no data.
        $grades = array_fill_keys(array_keys($graded), []);
        $frequency = [];
        foreach ($studentids as $studentid) {
            $analysis = $this->performance->find_current_for_student($courseid, $studentid);
            $rows = $analysis === null ? [] : array_column($this->performance->find_rows((int) $analysis->id), 'grade', 'activity_id');
            foreach (array_keys($graded) as $cmid) {
                $grades[$cmid][] = isset($rows[$cmid]) ? (float) $rows[$cmid] : null;
            }
            $frequency[] = $this->section_frequency($courseid, $studentid, $sectionid);
        }
        $activities = [];
        foreach ($grades as $cmid => $values) {
            $activities[] = ['activity_id' => $cmid, 'grades' => $values];
        }

        $questionnaires = [];
        $forums = [];
        foreach ($inside as $cmid => $module) {
            if ($module->modname === 'quiz') {
                $questionnaire = $this->questionnaire($courseid, $cmid);
                $questionnaire === null || $questionnaires[] = $questionnaire;
            } else if ($module->modname === 'forum') {
                $forum = $this->forum($courseid, $cmid);
                $forum === null || $forums[] = $forum;
            }
        }

        $materials = [];
        foreach ($snapshot['materials'] as $material) {
            if ($material['eligible']) {
                $materials[] = ['material_id' => (int) $material['material_id'], 'summary' => (string) $material['summary']];
            }
        }

        return [
            'activities' => $activities,
            'frequency' => $frequency,
            'questionnaires' => $questionnaires,
            'forums' => $forums,
            'materials' => $materials,
        ];
    }

    /**
     * The current frequency of a student in a section, in percent; null when there is none or it has no activity in the denominator.
     *
     * @param int $courseid
     * @param int $studentid
     * @param int $sectionid
     * @return float|null
     */
    private function section_frequency(int $courseid, int $studentid, int $sectionid): ?float {
        $analysis = $this->frequency->find_current_for_student($courseid, $studentid);
        if ($analysis === null) {
            return null;
        }
        foreach ($this->frequency->find_frequencies((int) $analysis->id, $studentid) as $row) {
            if ((int) $row->section_id === $sectionid) {
                return (int) $row->total_activities > 0 ? (float) $row->percentage : null;
            }
        }
        return null;
    }

    /**
     * Coverage, complexity and metrics of a questionnaire from its current analyses.
     *
     * @param int $courseid
     * @param int $cmid
     * @return array|null null when it has no analysis
     */
    private function questionnaire(int $courseid, int $cmid): ?array {
        $initial = $this->initial->find_for_quiz($courseid, $cmid);
        $initial = $initial ? reset($initial) : null;
        $results = $this->results->find_current($courseid, $cmid);
        if ($initial === null && $results === null) {
            return null;
        }
        $questions = [];
        if ($initial !== null) {
            foreach ($this->initial->find_questions((int) $initial->id) as $row) {
                $questions["{$row->question_id}:{$row->question_version}"] = [
                    'question_id' => (int) $row->question_id,
                    'question_version' => (int) $row->question_version,
                    'complexity' => $row->complexity === null ? null : (float) $row->complexity,
                ];
            }
        }
        $metrics = null;
        if ($results !== null) {
            foreach ($this->results->find_question_results((int) $results->id) as $row) {
                $key = "{$row->question_id}:{$row->question_version}";
                $questions[$key] = ($questions[$key] ?? ['question_id' => (int) $row->question_id, 'question_version' => (int) $row->question_version]) + [];
                $questions[$key] += [
                    'average_correctness' => $row->average_correctness === null ? null : (float) $row->average_correctness,
                    'correct_count' => $row->correct_count === null ? null : (int) $row->correct_count,
                    'incorrect_count' => $row->incorrect_count === null ? null : (int) $row->incorrect_count,
                    'partial_result' => $row->partial_result === null ? null : (float) $row->partial_result,
                ];
            }
            $metric = $this->results->find_metric((int) $results->id);
            $metrics = $metric === null ? null : [
                'mean' => (float) $metric->mean,
                'population_standard_deviation' => (float) $metric->population_standard_deviation,
                'minimum' => (float) $metric->minimum,
                'maximum' => (float) $metric->maximum,
            ];
        }
        $coverage = $initial === null ? null : $this->initial->find_questionnaire_analysis((int) $initial->id);
        return [
            'cmid' => $cmid,
            'coverage' => $coverage === null || $coverage->coverage === null ? null : (float) $coverage->coverage,
            'metrics' => $metrics,
            'questions' => array_values($questions),
        ];
    }

    /**
     * Summary and difficulties of the current snapshot of a forum.
     *
     * @param int $courseid
     * @param int $cmid
     * @return array|null null when it has no analysis
     */
    private function forum(int $courseid, int $cmid): ?array {
        $current = $this->forums->find_current($courseid, $cmid);
        if ($current === null) {
            return null;
        }
        $difficulties = [];
        foreach ($this->forums->find_difficulties((int) $current->id) as $row) {
            $difficulties[] = ['discussion_id' => (int) $row->discussion_id, 'description' => (string) $row->description];
        }
        return ['cmid' => $cmid, 'summary' => $current->summary, 'difficulties' => $difficulties];
    }
}
