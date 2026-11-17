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

use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\event_handler;
use local_assistente\application\queue\handler_result;
use local_assistente\domain\event\event_type;
use local_assistente\application\synthesis\synthesis_trigger;
use local_assistente\domain\quiz\metrics_calculator;
use local_assistente\domain\quiz\multiple_choice_selection;
use local_assistente\infrastructure\moodle\questionnaire_reader;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use local_assistente\infrastructure\persistence\quiz_results_repository;

/**
 * Handler of the events that recalculate the results of a questionnaire: attempt submitted, manual grading, regrading and the closing
 * of the questionnaire (T072; spec-011; DEC-24, DEC-36). No LLM is called.
 *
 * Each execution reads the finished attempts of the students (previews, attempts in progress, abandoned ones and users that are
 * not students are left out), computes the metrics by question and the aggregate by the grading method of the questionnaire, and
 * writes one immutable `quiz_results` analysis, all or nothing (T071). The events differ only in why they run: a closing event is the
 * final recalculation, and every event reads all the finished attempts.
 *
 * Questions:
 * - An answered question is linked to the analysed row of the same entry of the question bank, in the latest initial analysis of the
 *   questionnaire (T067): `question_id`/`question_version` are the version answered and `questionnaire_question_id` the analysed row
 *   (a new version of an analysed question is linked to it, and no initial analysis is made again; DEC-31, DEC-36 D5).
 * - A question with no analysed row (added or replaced after the publication, or the questionnaire has no initial analysis yet) gets
 *   no `question_result`; its answers still count in the aggregate, because the aggregate comes from the grade of the attempts, and it
 *   is listed in the context of the processing (`unanalysed_questions`).
 * - A question with no graded answer gets no row: `no_data`, never zero. An aggregate with no student result writes none.
 *
 * Attempt grade: the percentage of the maximum grade of the attempt, `sum_grades` over the sum of the maximum marks of its questions;
 * an attempt with no grade yet (manual grading pending) has no result and does not count.
 *
 * Partial result (Q-011-07, decided 26/10/2026): for a multiple choice question with several correct alternatives, correct alternatives
 * selected over correct alternatives, 0 if any incorrect one is selected; an answer with no recorded response is left out of the mean and
 * is not zero; independent of the grade. The rule is multiple_choice_selection; the reader gives the alternatives of the attempt.
 * For a matching, drag-and-drop words into sentences or Cloze question (DEC-36 D1) it is the parts right over the parts of the question, from
 * `get_num_parts_right()` through the reader, and an incorrect or empty part only lowers the numerator; an answer without a grade
 * (`gaveup`, never answered) has no parts and is left out, not zero.
 *
 * Regrade window (T075): an attempt whose stored grade is not the total of its questions is being regraded and Moodle has not persisted
 * its grade yet; the handler then fails the attempt of the queue without writing anything, and the retry (or any later event) reads the
 * settled state. There is no event after the grades are persisted and a fixed wait would prove nothing, so the check is on the data.
 *
 * Idempotent: if the processing already has its results, nothing is read or written. A failure writes nothing and the retry redoes it
 * for the same processing.
 *
 * When the analysis is saved, or already was, it asks for the synthesis of the section of the questionnaire (T098, DEC-41 D3); a failure to queue it fails the
 * attempt, and the retry asks again.
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class results_handler implements event_handler {
    /** @var float largest difference between the stored grade and the total of the questions taken as equal (sumgrades has 5 decimals) */
    private const GRADE_TOLERANCE = 0.0001;

    /** @var int version of the structure of the context of the processing */
    public const CONTEXT_FORMAT = 1;

    /** @var quiz_results_repository */
    private quiz_results_repository $repository;

    /** @var quiz_initial_repository */
    private quiz_initial_repository $initial;

    /** @var questionnaire_reader */
    private questionnaire_reader $questionnaires;

    /** @var student_reader */
    private student_reader $students;

    /** @var event_repository */
    private event_repository $events;

    /** @var metrics_calculator */
    private metrics_calculator $calculator;

    /** @var synthesis_trigger */
    private synthesis_trigger $synthesis;

    /**
     * Constructor; every collaborator has a default.
     *
     * @param quiz_results_repository|null $repository
     * @param quiz_initial_repository|null $initial
     * @param questionnaire_reader|null $questionnaires
     * @param student_reader|null $students
     * @param event_repository|null $events
     * @param metrics_calculator|null $calculator
     * @param synthesis_trigger|null $synthesis
     */
    public function __construct(
        ?quiz_results_repository $repository = null,
        ?quiz_initial_repository $initial = null,
        ?questionnaire_reader $questionnaires = null,
        ?student_reader $students = null,
        ?event_repository $events = null,
        ?metrics_calculator $calculator = null,
        ?synthesis_trigger $synthesis = null
    ) {
        $this->repository = $repository ?? new quiz_results_repository();
        $this->initial = $initial ?? new quiz_initial_repository();
        $this->questionnaires = $questionnaires ?? new questionnaire_reader();
        $this->students = $students ?? new student_reader();
        $this->events = $events ?? new event_repository();
        $this->calculator = $calculator ?? new metrics_calculator();
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
            return handler_result::success('the results of this processing already exist');
        }
        $questionnaire = $this->questionnaires->get_questionnaire($cmid);
        if ($questionnaire === null || $questionnaire->course_id !== $courseid) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the questionnaire does not exist in the course');
        }

        try {
            $analysed = $this->analysed_rows($courseid, $cmid);
            $answers = [];
            $unanalysed = [];
            $finals = [];
            $considered = 0;
            $stale = [];
            foreach ($this->student_attempts($cmid, $courseid) as $userid => $attempts) {
                $percentages = [];
                foreach ($attempts as $attempt) {
                    $detail = $this->questionnaires->get_attempt_detail($attempt->attempt_id);
                    $items = $detail?->questions ?? [];
                    if (!$this->grade_is_current($attempt->sum_grades, $detail?->total_mark)) {
                        $stale[] = $attempt->attempt_id;
                    }
                    foreach ($items as $item) {
                        $this->collect($answers, $unanalysed, $analysed, $item);
                    }
                    $percentage = $this->attempt_percentage($attempt, $items);
                    if ($percentage !== null) {
                        $percentages[] = $percentage;
                        $considered++;
                    }
                }
                $final = $this->calculator->final_result($percentages, $questionnaire->grade_method);
                if ($final !== null) {
                    $finals[] = $final;
                }
            }

            if ($stale) {
                // A regrade saved the questions and Moodle has not yet persisted the grade of the attempt: nothing is written, the
                // queue retries (three attempts) and any later event recalculates from the settled state.
                return handler_result::failure(attempt_failure::TECHNICAL, sprintf(
                    'grade not persisted yet for attempt(s) %s: the questions were regraded and quiz_attempts.sumgrades is stale; retry later',
                    implode(', ', $stale)
                ));
            }

            $results = [];
            foreach ($answers as $group) {
                $metrics = $this->calculator->question_metrics($group['answers']);
                if ($metrics !== null) {
                    $results[] = [
                        'questionnaire_question_id' => $group['row'],
                        'question_id' => $group['question_id'],
                        'question_version' => $group['question_version'],
                        'metrics' => $metrics,
                    ];
                }
            }

            $this->save_context($processingid, $cmid, $event->event_type, $considered, count($finals), $unanalysed);
            $this->repository->save($courseid, $cmid, $processingid, $this->calculator->aggregate($finals), $results);
            $this->synthesis->request_for_module($courseid, $cmid);
        } catch (\Throwable $e) {
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }

        return handler_result::success(sprintf(
            'results saved: %d attempts, %d students, %d questions, %d unanalysed questions, %.2f s',
            $considered,
            count($finals),
            count($results),
            count($unanalysed),
            microtime(true) - $started
        ));
    }

    /**
     * The analysed rows of the questionnaire by entry of the question bank, from its latest initial analysis.
     *
     * @param int $courseid
     * @param int $cmid
     * @return int[] questionnaire_question id by bank entry id; [] when there is no initial analysis
     */
    private function analysed_rows(int $courseid, int $cmid): array {
        $history = $this->initial->find_for_quiz($courseid, $cmid);
        if (!$history) {
            return [];
        }
        $rows = [];
        foreach ($this->initial->find_questions((int) reset($history)->id) as $row) {
            $question = $this->questionnaires->get_question((int) $row->question_id, false);
            if ($question !== null) {
                $rows[$question->bank_entry_id] = (int) $row->id;
            }
        }
        return $rows;
    }

    /**
     * The finished attempts of the students, by user, in the order of the attempts. Previews, attempts in progress or abandoned and
     * users that are not students are left out (DEC-36 D3).
     *
     * @param int $cmid
     * @param int $courseid
     * @return array<int, \stdClass[]>
     */
    private function student_attempts(int $cmid, int $courseid): array {
        $students = array_flip($this->students->get_students($courseid));
        $byuser = [];
        foreach ($this->questionnaires->get_attempts($cmid) as $attempt) {
            if ($attempt->preview || !$attempt->is_finished || !isset($students[$attempt->user_id])) {
                continue;
            }
            $byuser[$attempt->user_id][] = $attempt;
        }
        return $byuser;
    }

    /**
     * Add an answer to the group of its question, or list the question as unanalysed.
     *
     * @param array $answers groups by answered question and version
     * @param array $unanalysed
     * @param int[] $analysed analysed row by bank entry
     * @param \stdClass $item answer of an attempt
     */
    private function collect(array &$answers, array &$unanalysed, array $analysed, \stdClass $item): void {
        if ($item->question_version === null) {
            return;
        }
        $key = "{$item->question_id}:{$item->question_version}";
        $row = $analysed[$item->bank_entry_id] ?? null;
        if ($row === null) {
            $unanalysed[$key] = ['question_id' => $item->question_id, 'question_version' => $item->question_version];
            return;
        }
        $answers[$key] ??= [
            'row' => $row,
            'question_id' => $item->question_id,
            'question_version' => $item->question_version,
            'answers' => [],
        ];
        $answers[$key]['answers'][] = ['fraction' => $item->fraction] + $this->items($item);    }

    /**
     * Correct items of an answer, for the partial result (Q-011-07): a multiple choice answer with several correct alternatives, when
     * the attempt holds a response (rule: multiple_choice_selection), or a graded matching, drag-and-drop words into sentences or Cloze answer
     * (parts right over parts, the count of Moodle; an incorrect part does not zero the others). Independent of the grade.
     *
     * @param \stdClass $item answer of an attempt
     * @return array correct_items_answered and total_correct_items, or [] when there is no data
     */
    private function items(\stdClass $item): array {
        $parts = $item->parts ?? null;
        if ($parts !== null) {
            return ['correct_items_answered' => $parts['right'], 'total_correct_items' => $parts['total']];
        }
        $choices = $item->choices ?? null;
        if ($choices === null) {
            return [];
        }
        $answered = multiple_choice_selection::items_answered(
            $choices['selected_correct'],
            $choices['selected_incorrect'],
            $choices['recorded']
        );
        return $answered === null ? [] : [
            'correct_items_answered' => $answered,
            'total_correct_items' => $choices['correct_total'],
        ];
    }
    /**
     * Whether the grade stored for an attempt is the total of its questions, that is, whether Moodle has finished persisting it.
     *
     * After a regrade of the overview report the questions are saved and `attempt_regraded` is emitted before the grades of the attempts
     * are recomputed (T075). A stored grade that differs from the total of the questions means that step has not happened yet. Both
     * null means that a question waits for manual grading: the attempt has no grade and does not count.
     *
     * @param float|null $stored quiz_attempts.sumgrades
     * @param float|null $total total mark of the questions of the attempt
     * @return bool
     */
    private function grade_is_current(?float $stored, ?float $total): bool {
        if ($stored === null || $total === null) {
            return $stored === $total;
        }
        return abs($stored - $total) <= self::GRADE_TOLERANCE;
    }
    /**
     * Percentage of the maximum grade of an attempt; null while it has no grade.
     *
     * @param \stdClass $attempt
     * @param \stdClass[] $items answers of the attempt
     * @return float|null
     */
    private function attempt_percentage(\stdClass $attempt, array $items): ?float {
        $max = array_sum(array_map(static fn($i) => $i->max_mark, $items));
        if ($attempt->sum_grades === null || $max <= 0) {
            return null;
        }
        return min(100.0, max(0.0, $attempt->sum_grades / $max * 100));
    }

    /**
     * Store the context of the processing once: why it ran, what it considered and the questions with no analysed row.
     *
     * @param int $processingid
     * @param int $cmid
     * @param string $eventtype
     * @param int $attempts attempts considered
     * @param int $students students with a result
     * @param array $unanalysed
     */
    private function save_context(int $processingid, int $cmid, string $eventtype, int $attempts, int $students, array $unanalysed): void {
        if ($this->events->find_context($processingid) !== null) {
            return;
        }
        $this->events->save_context($processingid, json_encode([
            'format' => self::CONTEXT_FORMAT,
            'kind' => 'quiz_results',
            'cmid' => $cmid,
            'final' => $eventtype === event_type::QUIZ_CLOSED->value,
            'attempts_considered' => $attempts,
            'students_with_result' => $students,
            'unanalysed_questions' => array_values($unanalysed),
        ], JSON_THROW_ON_ERROR), time());
    }
}
