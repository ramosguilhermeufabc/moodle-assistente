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

/**
 * Repository of the initial analysis of a questionnaire: the aggregate `analysis` (type `quiz_initial`) with its 1:1
 * `questionnaire_analysis` (coverage) and its 1:N `questionnaire_question` rows (complexity of each question version)
 * (plan 003 "Agregados"; spec-010, data-model; T067).
 *
 * Rules kept here:
 * - The three tables are written together, in one transaction: either the whole analysis exists or nothing does (spec-010: the
 *   result is saved only if every result is valid). The write runs under the lock of the event of the processing.
 * - One initial analysis for each processing: a second save for the same processing is rejected (duplicate_record_exception) and
 *   leaves the first untouched, so a retry or a repeated delivery cannot duplicate it.
 * - One row for each question and version: the same pair twice in one analysis is rejected before anything is written.
 * - `coverage` and `complexity` are in {0.0, 0.1, ..., 1.0} (contract of spec-010). `coverage = 0.0` is the value stored when
 *   there is no eligible material (DEC-35 D2); this repository does not decide that, the handler does.
 * - Reads are scoped by course and questionnaire (`cmid`), so one course never sees the analysis of another.
 *
 * It holds no analysis logic: it does not call the LLM, read the questionnaire or evaluate the snapshot (T068).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_initial_repository extends repository {
    /** @var table_repository the questionnaire_analysis table */
    private table_repository $coverages;

    /** @var table_repository the questionnaire_question table */
    private table_repository $questions;

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
        $this->coverages = new table_repository('local_assistente_questionnaire_analysis');
        $this->questions = new table_repository('local_assistente_questionnaire_question');
        $this->processings = new table_repository('local_assistente_processing');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_analysis';
    }

    /**
     * Save the initial analysis of a questionnaire, all or nothing.
     *
     * @param int $courseid
     * @param int $cmid the questionnaire
     * @param int $processingid
     * @param float $coverage 0.0 to 1.0 in steps of 0.1
     * @param array $questions list of ['question_id' => int, 'question_version' => int, 'complexity' => float]; may be empty
     * @param string|null $summary
     * @param int|null $now
     * @return int the id of the analysis
     * @throws invalid_domain_value_exception when a value is invalid (nothing is written)
     * @throws duplicate_record_exception when the processing already has an initial analysis, or a question version is repeated
     * @throws \dml_missing_record_exception when the processing does not exist
     */
    public function save(
        int $courseid,
        int $cmid,
        int $processingid,
        float $coverage,
        array $questions,
        ?string $summary = null,
        ?int $now = null
    ): int {
        $this->assert_tenth($coverage, 'coverage');
        $seen = [];
        foreach ($questions as $question) {
            $id = (int) ($question['question_id'] ?? 0);
            $version = (int) ($question['question_version'] ?? 0);
            if ($id <= 0 || $version <= 0) {
                throw new invalid_domain_value_exception('A question needs a positive question_id and question_version.');
            }
            $this->assert_tenth((float) ($question['complexity'] ?? -1), 'complexity');
            if (isset($seen["{$id}:{$version}"])) {
                throw new duplicate_record_exception("Question {$id} version {$version} appears twice in the analysis.");
            }
            $seen["{$id}:{$version}"] = true;
        }

        $processing = $this->processings->find($processingid)
            ?? throw new \dml_missing_record_exception('local_assistente_processing', 'id = ?', [$processingid]);
        $now ??= time();

        return $this->uow->run(function () use ($courseid, $cmid, $processingid, $coverage, $questions, $summary, $now): int {
            if ($this->find_by_processing($processingid) !== null) {
                throw new duplicate_record_exception("Processing {$processingid} already has an initial analysis.");
            }
            $analysisid = $this->insert((object) [
                'course_id' => $courseid,
                'processing_id' => $processingid,
                'analysis_type' => analysis_type::QUIZ_INITIAL->value,
                'cmid' => $cmid,
                'summary' => $summary,
                'created_at' => $now,
            ]);
            $qaid = $this->coverages->insert((object) ['analysis_id' => $analysisid, 'coverage' => round($coverage, 1)]);
            foreach ($questions as $question) {
                $this->questions->insert((object) [
                    'questionnaire_analysis_id' => $qaid,
                    'question_id' => (int) $question['question_id'],
                    'question_version' => (int) $question['question_version'],
                    'complexity' => round((float) $question['complexity'], 1),
                ]);
            }
            return $analysisid;
        }, [unit_of_work::event_resource((int) $processing->event_id)]);
    }

    /**
     * The initial analysis of a processing.
     *
     * @param int $processingid
     * @return \stdClass|null
     */
    public function find_by_processing(int $processingid): ?\stdClass {
        return $this->find_by(['processing_id' => $processingid, 'analysis_type' => analysis_type::QUIZ_INITIAL->value]);
    }

    /**
     * The initial analyses of a questionnaire, newest first.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass[]
     */
    public function find_for_quiz(int $courseid, int $cmid): array {
        return $this->find_all_by([
            'course_id' => $courseid,
            'cmid' => $cmid,
            'analysis_type' => analysis_type::QUIZ_INITIAL->value,
        ], 'created_at DESC, id DESC');
    }

    /**
     * The coverage row of an analysis.
     *
     * @param int $analysisid
     * @return \stdClass|null
     */
    public function find_questionnaire_analysis(int $analysisid): ?\stdClass {
        return $this->coverages->find_by(['analysis_id' => $analysisid]);
    }

    /**
     * The question rows of an analysis, by question and version.
     *
     * @param int $analysisid
     * @return \stdClass[] indexed by id; [] when the analysis has none or does not exist
     */
    public function find_questions(int $analysisid): array {
        $qa = $this->find_questionnaire_analysis($analysisid);
        return $qa === null ? [] : $this->questions->find_all_by(
            ['questionnaire_analysis_id' => (int) $qa->id],
            'question_id ASC, question_version ASC'
        );
    }

    /**
     * Check a value in {0.0, 0.1, ..., 1.0}.
     *
     * @param float $value
     * @param string $field
     * @throws invalid_domain_value_exception
     */
    private function assert_tenth(float $value, string $field): void {
        $tenths = $value * 10;
        if (is_nan($value) || $value < 0 || $value > 1 || abs($tenths - round($tenths)) > 1e-9) {
            throw new invalid_domain_value_exception("The {$field} must be from 0.0 to 1.0 in steps of 0.1.");
        }
    }
}
