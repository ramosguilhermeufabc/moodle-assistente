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

namespace local_assistente\application\quiz;

use local_assistente\application\context\context_snapshot_builder;
use local_assistente\application\llm\instruction_builder;
use local_assistente\application\synthesis\synthesis_trigger;
use local_assistente\application\llm\llm_result_adapter;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\event_handler;
use local_assistente\application\queue\handler_result;
use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use local_assistente\infrastructure\llm\adapter_factory;
use local_assistente\infrastructure\llm\llm_port;
use local_assistente\infrastructure\moodle\html_text_converter;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\moodle\questionnaire_reader;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;

/**
 * Handler of the event `quiz_published`: the initial analysis of a questionnaire (T068; spec-010; DEC-31, DEC-35).
 *
 * Steps, for the questionnaire of the event:
 * 1. the section of the questionnaire is resolved from its `cmid` (T021); an unresolved section is a technical error and the
 *    attempt fails: it is never read as "no materials" (`coverage = 0.0`);
 * 2. the context snapshot is built once for the processing (T064) and reused by the retries of the same processing;
 * 3. the questions are every resolvable question of the questionnaire except type `description`: the version in force of the
 *    fixed positions and the candidates of the filter of the random ones, one row per question and version;
 * 4. the LLM is called with no transaction open: one `question_complexity` per question and one `quiz_coverage`, omitted with
 *    `coverage = 0.0` when the snapshot has no eligible material (DEC-35 D2);
 * 5. only if every result is valid, the repository writes the whole analysis in one transaction (T067). Any failure leaves nothing
 *    written, and the retry redoes the analysis for the same processing.
 *
 * The context holds the statement, the answers and the response the teacher marked, plus the summaries of the eligible materials.
 * It holds no attempt, answer of a student or identifier of a student (DEC-34 D3); the guard of the port enforces it.
 *
 * Idempotent: if the processing already has its analysis, nothing is called or written. The duration of the processing is
 * recorded in the response of the attempt (R-010-01).
 *
 * The texts of the tasks are provisional: Q-009-05 (instructions of the uses) is open.
 *
 * When the analysis is saved, or already was, it asks for the synthesis of the section of the questionnaire (T098, DEC-41 D3); a failure to queue it fails the
 * attempt, and the retry asks again.
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class initial_analysis_handler implements event_handler {
    /** @var string task of the coverage; the language of the course is appended (Q-009-05 is open) */
    public const COVERAGE_TASK = 'Rate, from 0.0 to 1.0 in steps of 0.1, how much of the questionnaire is covered by the given '
        . 'teaching materials. Return only the coverage.';

    /** @var string task of the complexity; the language of the course is appended (Q-009-05 is open) */
    public const COMPLEXITY_TASK = 'Rate, from 0.0 to 1.0 in steps of 0.1, the complexity of the given question for a student of the '
        . 'course. Return only the complexity.';

    /** @var string question type that is not analysed (DEC-35 D1) */
    private const EXCLUDED_TYPE = 'description';

    /** @var quiz_initial_repository */
    private quiz_initial_repository $repository;

    /** @var questionnaire_reader */
    private questionnaire_reader $questionnaires;

    /** @var course_section_reader */
    private course_section_reader $sections;

    /** @var student_reader */
    private student_reader $students;

    /** @var context_snapshot_builder */
    private context_snapshot_builder $snapshots;

    /** @var event_repository */
    private event_repository $events;

    /** @var llm_port */
    private llm_port $port;

    /** @var instruction_builder */
    private instruction_builder $instructions;

    /** @var synthesis_trigger */
    private synthesis_trigger $synthesis;

    /**
     * Constructor; every collaborator has a default.
     *
     * @param llm_port|null $port the port; the simulated one behind the privacy guard by default
     * @param quiz_initial_repository|null $repository
     * @param questionnaire_reader|null $questionnaires
     * @param course_section_reader|null $sections
     * @param student_reader|null $students
     * @param context_snapshot_builder|null $snapshots
     * @param event_repository|null $events
     * @param instruction_builder|null $instructions
     * @param synthesis_trigger|null $synthesis
     */
    public function __construct(
        ?llm_port $port = null,
        ?quiz_initial_repository $repository = null,
        ?questionnaire_reader $questionnaires = null,
        ?course_section_reader $sections = null,
        ?student_reader $students = null,
        ?context_snapshot_builder $snapshots = null,
        ?event_repository $events = null,
        ?instruction_builder $instructions = null,
        ?synthesis_trigger $synthesis = null
    ) {
        $this->port = $port ?? adapter_factory::create();
        $this->repository = $repository ?? new quiz_initial_repository();
        $this->questionnaires = $questionnaires ?? new questionnaire_reader();
        $this->sections = $sections ?? new course_section_reader();
        $this->students = $students ?? new student_reader();
        $this->snapshots = $snapshots ?? new context_snapshot_builder();
        $this->events = $events ?? new event_repository();
        $this->instructions = $instructions ?? new instruction_builder($this->sections);
        $this->synthesis = $synthesis ?? new synthesis_trigger();
    }

    #[\Override]
    public function handle(\stdClass $event, \stdClass $processing): handler_result {
        $started = microtime(true);
        $payload = json_decode((string) $event->payload, true);
        $cmid = is_array($payload) ? ($payload['cmid'] ?? null) : null;
        if (!is_int($cmid) || $cmid <= 0) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the event has no valid cmid');
        }
        $courseid = (int) $event->course_id;
        $processingid = (int) $processing->id;

        if ($this->repository->find_by_processing($processingid) !== null) {
            try {
                $this->synthesis->request_for_module($courseid, $cmid);
            } catch (\Throwable $e) {
                return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
            }
            return handler_result::success('the initial analysis of this processing already exists');
        }

        $questionnaire = $this->questionnaires->get_questionnaire($cmid);
        if ($questionnaire === null || $questionnaire->course_id !== $courseid) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the questionnaire does not exist in the course');
        }

        $section = $this->sections->resolve_module_section($courseid, $cmid);
        if (!$section->is_resolved()) {
            return handler_result::failure(
                attempt_failure::TECHNICAL,
                'the section of the questionnaire is not resolved (' . $section->status->value . ')'
            );
        }

        try {
            $snapshot = $this->snapshot($processingid, $courseid, (int) $section->sectionid);
            $questions = $this->questions($cmid);
            $materials = $this->eligible_summaries($snapshot);
            $instruction = fn(string $task) => $this->instructions->for_course($courseid, $task);

            $calls = 0;
            $rows = [];
            foreach ($questions as $question) {
                $result = $this->port->evaluate(
                    new llm_context(llm_use_case::QUESTION_COMPLEXITY, [
                        'question' => $this->question_data($question),
                        'materials' => $materials,
                    ]),
                    $instruction(self::COMPLEXITY_TASK)
                );
                $calls++;
                if (!$result->is_valid()) {
                    return llm_result_adapter::failure_of($result);
                }
                $rows[] = [
                    'question_id' => $question->question_id,
                    'question_version' => $question->question_version,
                    'complexity' => (float) $result->data['complexity'],
                ];
            }

            $coverage = 0.0;
            if ($materials) {
                $result = $this->port->evaluate(
                    new llm_context(llm_use_case::QUIZ_COVERAGE, [
                        'questionnaire' => ['name' => $questionnaire->name],
                        'questions' => array_map(fn($q) => $this->question_data($q), $questions),
                        'materials' => $materials,
                    ]),
                    $instruction(self::COVERAGE_TASK)
                );
                $calls++;
                if (!$result->is_valid()) {
                    return llm_result_adapter::failure_of($result);
                }
                $coverage = (float) $result->data['coverage'];
            }

            $this->repository->save($courseid, $cmid, $processingid, $coverage, $rows);
            $this->synthesis->request_section($courseid, (int) $section->sectionid);
        } catch (\Throwable $e) {
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }

        return handler_result::success(sprintf(
            'initial analysis saved: coverage %.1f, %d questions, %d LLM calls, %.2f s',
            $coverage,
            count($rows),
            $calls,
            microtime(true) - $started
        ));
    }

    /**
     * The snapshot of the processing: the stored one on a retry, otherwise built and stored now.
     *
     * @param int $processingid
     * @param int $courseid
     * @param int $sectionid
     * @return array
     */
    private function snapshot(int $processingid, int $courseid, int $sectionid): array {
        $stored = $this->events->find_context($processingid);
        if ($stored !== null) {
            return json_decode($stored->context_json, true, 512, JSON_THROW_ON_ERROR);
        }
        return $this->snapshots->build_and_save(
            $processingid,
            $courseid,
            $sectionid,
            $this->students->get_students($courseid),
            $this->events
        );
    }

    /**
     * Questions to analyse: one per question and version, in the order of the questionnaire.
     *
     * @param int $cmid
     * @return \stdClass[] with question_id, question_version, type, text, answers and correct_response
     */
    private function questions(int $cmid): array {
        $found = [];
        foreach ($this->questionnaires->get_slots($cmid) as $slot) {
            if ($slot->random) {
                foreach ($this->questionnaires->get_random_candidates($slot->filter ?? []) as $candidate) {
                    $this->collect($found, $this->questionnaires->get_question($candidate->question_id));
                }
            } else {
                $this->collect($found, $slot->question);
            }
        }
        return array_values($found);
    }

    /**
     * Add a question unless it is missing, of the excluded type, or already collected.
     *
     * @param array $found
     * @param \stdClass|null $question
     */
    private function collect(array &$found, ?\stdClass $question): void {
        if ($question === null || $question->type === self::EXCLUDED_TYPE) {
            return;
        }
        $found["{$question->question_id}:{$question->question_version}"] ??= $question;
    }

    /**
     * Data of a question for the context: statement, answers and the response the teacher marked, nothing of a student.
     *
     * @param \stdClass $question
     * @return array
     */
    private function question_data(\stdClass $question): array {
        return [
            'type' => $question->type,
            'statement' => html_text_converter::to_text($question->text),
            'answers' => array_map(
                static fn($a) => ['text' => html_text_converter::to_text($a->text), 'fraction' => $a->fraction],
                $question->answers
            ),
            'correct_response' => $question->correct_response,
        ];
    }

    /**
     * Summaries of the eligible materials of the snapshot.
     *
     * @param array $snapshot
     * @return array list of ['name' => string, 'summary' => string]
     */
    private function eligible_summaries(array $snapshot): array {
        $summaries = [];
        foreach ($snapshot['materials'] as $material) {
            if ($material['eligible']) {
                $summaries[] = ['name' => $material['name'], 'summary' => $material['summary']];
            }
        }
        return $summaries;
    }
}
