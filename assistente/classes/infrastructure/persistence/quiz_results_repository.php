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

namespace local_assistente\infrastructure\persistence;

use local_assistente\domain\analysis_type;
use local_assistente\domain\quiz\aggregate_metrics;
use local_assistente\domain\quiz\question_metrics;

/**
 * Repository of the results of a questionnaire: the aggregate `analysis` (type `quiz_results`) with its `question_result` rows and
 * its 1:1 `questionnaire_metric` (plan 003 "Agregados"; spec-011, data-model; T071).
 *
 * Rules kept here:
 * - Each execution writes one immutable analysis, with its question results and its aggregate, in one transaction, under the lock of
 *   the event of the processing. The current analysis of a questionnaire is the latest one; a failed execution writes nothing, so the
 *   latest is always the last successful (spec-011, "Histórico"). No method changes or deletes an analysis.
 * - One analysis for each processing: a second save for the same processing is rejected (duplicate_record_exception) and leaves the
 *   first untouched, so a retry or a repeated delivery cannot duplicate it.
 * - `no_data` is the absence of the row, never a zero (DEC-24, P-06): a question with no graded answer is simply not passed, and an
 *   aggregate of `null` writes no `questionnaire_metric`. A real zero is stored as a row. `error` is a processing in `failure`; it
 *   leaves no analysis here.
 * - A question result points to the analysed row of the same entry of the question bank (`questionnaire_question_id`); the repository
 *   checks that this row belongs to the initial analysis of the same course and questionnaire. `question_id` and `question_version`
 *   are the version answered, which may differ from the analysed one (DEC-36 D5).
 * - One row for each question and version in an analysis. Percentages are from 0 to 100 and counts are not negative.
 * - Reads are scoped by course and questionnaire (`cmid`).
 *
 * It holds no calculation or selection of attempts: it does not call the metrics calculator or read the questionnaire (T072).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_results_repository extends repository {
    /** @var table_repository the question_result table */
    private table_repository $results;

    /** @var table_repository the questionnaire_metric table */
    private table_repository $metrics;

    /** @var table_repository the questionnaire_question table */
    private table_repository $analysed;

    /** @var table_repository the questionnaire_analysis table */
    private table_repository $coverages;

    /** @var table_repository the processing table, to find the event to lock */
    private table_repository $processings;

    /**
     * Constructor.
     *
     * @param unit_of_work $uow
     */
    public function __construct(
        private readonly unit_of_work $uow = new unit_of_work()
    ) {
        $this->results = new table_repository('local_assistente_question_result');
        $this->metrics = new table_repository('local_assistente_questionnaire_metric');
        $this->analysed = new table_repository('local_assistente_questionnaire_question');
        $this->coverages = new table_repository('local_assistente_questionnaire_analysis');
        $this->processings = new table_repository('local_assistente_processing');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_analysis';
    }

    /**
     * Save the results of one execution, all or nothing.
     *
     * @param int $courseid
     * @param int $cmid the questionnaire
     * @param int $processingid
     * @param aggregate_metrics|null $aggregate null writes no aggregate (`no_data`)
     * @param array $questions list of ['questionnaire_question_id' => int, 'question_id' => int, 'question_version' => int,
     *                         'metrics' => question_metrics]; may be empty
     * @param string|null $summary
     * @param int|null $now
     * @return int the id of the analysis
     * @throws invalid_domain_value_exception when a value or a reference is invalid (nothing is written)
     * @throws duplicate_record_exception when the processing already has results, or a question version is repeated
     * @throws \dml_missing_record_exception when the processing does not exist
     */
    public function save(
        int $courseid,
        int $cmid,
        int $processingid,
        ?aggregate_metrics $aggregate,
        array $questions,
        ?string $summary = null,
        ?int $now = null
    ): int {
        if ($aggregate !== null) {
            foreach (['mean', 'populationstandarddeviation', 'minimum', 'maximum'] as $field) {
                $this->assert_percent($aggregate->$field, $field);
            }
        }
        $seen = [];
        foreach ($questions as $question) {
            $id = (int) ($question['question_id'] ?? 0);
            $version = (int) ($question['question_version'] ?? 0);
            if ($id <= 0 || $version <= 0 || (int) ($question['questionnaire_question_id'] ?? 0) <= 0) {
                throw new invalid_domain_value_exception('A result needs a positive question_id, question_version and questionnaire_question_id.');
            }
            $metrics = $question['metrics'] ?? null;
            if (!$metrics instanceof question_metrics) {
                throw new invalid_domain_value_exception('A question result needs its metrics; a question without data is not passed.');
            }
            $this->assert_percent($metrics->averagecorrectness, 'average_correctness');
            if ($metrics->partialresult !== null) {
                $this->assert_percent($metrics->partialresult, 'partial_result');
            }
            if ($metrics->correctcount < 0 || $metrics->incorrectcount < 0) {
                throw new invalid_domain_value_exception('The counts of a question result must not be negative.');
            }
            if (isset($seen["{$id}:{$version}"])) {
                throw new duplicate_record_exception("Question {$id} version {$version} appears twice in the results.");
            }
            $seen["{$id}:{$version}"] = true;
            $this->assert_analysed_row((int) $question['questionnaire_question_id'], $courseid, $cmid);
        }

        $processing = $this->processings->find($processingid)
            ?? throw new \dml_missing_record_exception('local_assistente_processing', 'id = ?', [$processingid]);
        $now ??= time();

        return $this->uow->run(function () use ($courseid, $cmid, $processingid, $aggregate, $questions, $summary, $now): int {
            if ($this->find_by_processing($processingid) !== null) {
                throw new duplicate_record_exception("Processing {$processingid} already has results of a questionnaire.");
            }
            $analysisid = $this->insert((object) [
                'course_id' => $courseid,
                'processing_id' => $processingid,
                'analysis_type' => analysis_type::QUIZ_RESULTS->value,
                'cmid' => $cmid,
                'summary' => $summary,
                'created_at' => $now,
            ]);
            if ($aggregate !== null) {
                $this->metrics->insert((object) [
                    'analysis_id' => $analysisid,
                    'mean' => $aggregate->mean,
                    'population_standard_deviation' => $aggregate->populationstandarddeviation,
                    'minimum' => $aggregate->minimum,
                    'maximum' => $aggregate->maximum,
                ]);
            }
            foreach ($questions as $question) {
                $this->results->insert((object) [
                    'analysis_id' => $analysisid,
                    'questionnaire_question_id' => (int) $question['questionnaire_question_id'],
                    'question_id' => (int) $question['question_id'],
                    'question_version' => (int) $question['question_version'],
                    'average_correctness' => $question['metrics']->averagecorrectness,
                    'correct_count' => $question['metrics']->correctcount,
                    'incorrect_count' => $question['metrics']->incorrectcount,
                    'partial_result' => $question['metrics']->partialresult,
                ]);
            }
            return $analysisid;
        }, [unit_of_work::event_resource((int) $processing->event_id)]);
    }

    /**
     * The results of a processing.
     *
     * @param int $processingid
     * @return \stdClass|null
     */
    public function find_by_processing(int $processingid): ?\stdClass {
        return $this->find_by(['processing_id' => $processingid, 'analysis_type' => analysis_type::QUIZ_RESULTS->value]);
    }

    /**
     * The current results of a questionnaire: the latest execution, which is the last successful one.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass|null
     */
    public function find_current(int $courseid, int $cmid): ?\stdClass {
        $history = $this->find_history($courseid, $cmid);
        return $history ? reset($history) : null;
    }

    /**
     * All executions of a questionnaire, newest first.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass[]
     */
    public function find_history(int $courseid, int $cmid): array {
        return $this->find_all_by([
            'course_id' => $courseid,
            'cmid' => $cmid,
            'analysis_type' => analysis_type::QUIZ_RESULTS->value,
        ], 'created_at DESC, id DESC');
    }

    /**
     * The question results of an analysis, by question and version.
     *
     * @param int $analysisid
     * @return \stdClass[] indexed by id; [] when there are none
     */
    public function find_question_results(int $analysisid): array {
        return $this->results->find_all_by(['analysis_id' => $analysisid], 'question_id ASC, question_version ASC');
    }

    /**
     * The aggregate of an analysis.
     *
     * @param int $analysisid
     * @return \stdClass|null null is `no_data`: there was no student with a result
     */
    public function find_metric(int $analysisid): ?\stdClass {
        return $this->metrics->find_by(['analysis_id' => $analysisid]);
    }

    /**
     * Check that an analysed row belongs to the initial analysis of the same course and questionnaire.
     *
     * @param int $rowid questionnaire_question.id
     * @param int $courseid
     * @param int $cmid
     * @throws invalid_domain_value_exception
     */
    private function assert_analysed_row(int $rowid, int $courseid, int $cmid): void {
        $row = $this->analysed->find($rowid);
        $coverage = $row === null ? null : $this->coverages->find((int) $row->questionnaire_analysis_id);
        $initial = $coverage === null ? null : $this->find((int) $coverage->analysis_id);
        if (
            $initial === null
            || $initial->analysis_type !== analysis_type::QUIZ_INITIAL->value
            || (int) $initial->course_id !== $courseid
            || (int) $initial->cmid !== $cmid
        ) {
            throw new invalid_domain_value_exception(
                "The analysed question {$rowid} does not belong to the initial analysis of this questionnaire."
            );
        }
    }

    /**
     * Check a percentage from 0 to 100.
     *
     * @param float $value
     * @param string $field
     * @throws invalid_domain_value_exception
     */
    private function assert_percent(float $value, string $field): void {
        if (is_nan($value) || $value < 0 || $value > 100) {
            throw new invalid_domain_value_exception("The {$field} must be a percentage from 0 to 100.");
        }
    }
}
