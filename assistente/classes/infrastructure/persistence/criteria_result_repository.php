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
use local_assistente\domain\criteria\criteria_evaluation;
use local_assistente\domain\criteria_status;

/**
 * Repository of the criteria applied to the students: the aggregate `analysis` (type `criteria_check`) with its `criteria_result` rows,
 * one for each student (plan 003 "Agregados"; spec-015 data-model; T092).
 *
 * Rules kept here:
 * - Each execution writes one immutable analysis, with all its rows, in one transaction, under the lock of the event of the processing.
 *   The current analysis of a student is the latest one that has a row of the student; a failed execution writes nothing, so that is the
 *   last successful one. No method changes or deletes an analysis or a row.
 * - One analysis for each processing: a second save for the same processing is rejected (duplicate_record_exception) and leaves the first
 *   untouched. One row for each student in an analysis (unique index); a repeated student is rejected before anything is written.
 * - The three statuses are 1 (meets), 0 (below) and 2 (no data), taken from criteria_status, so no other value can be written
 *   (DEC-40). A status of no data agrees with the absence of the value that was compared: the activity and the frequency are 2 exactly
 *   when they have no value, and a value that is present is never stored as 2 nor an absent one as 0 or 1 (DEC-24).
 * - A course without criteria has no row at all (spec-015): the caller does not save for it. An empty list writes only the analysis.
 * - Only the statuses are stored. The limits that were used (DEC-45 D2), the values and the forums considered are in the criteria_evaluation
 *   and go to the context of the processing, which the handler writes (T093); this table has no column for them.
 * - The student must exist; the course module of the analysis is null (the analysis is of the course).
 *
 * It holds no reading of Moodle and no evaluation: it does not call the evaluator (T093).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criteria_result_repository extends repository {
    /** @var table_repository the criteria_result table */
    private table_repository $results;

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
        $this->results = new table_repository('local_assistente_criteria_result');
        $this->processings = new table_repository('local_assistente_processing');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_analysis';
    }

    /**
     * Save the criteria of some students in one execution, all or nothing.
     *
     * @param int $courseid
     * @param int $processingid
     * @param array $students list of ['student_id' => int, 'evaluation' => criteria_evaluation]; may be empty
     * @param string|null $summary
     * @param int|null $now
     * @return int the id of the analysis
     * @throws invalid_domain_value_exception when a value or a reference is invalid (nothing is written)
     * @throws duplicate_record_exception when the processing already has an analysis, or a student is repeated
     * @throws \dml_missing_record_exception when the processing does not exist
     */
    public function save(int $courseid, int $processingid, array $students, ?string $summary = null, ?int $now = null): int {
        global $DB;
        $seen = [];
        foreach ($students as $row) {
            $student = (int) ($row['student_id'] ?? 0);
            $evaluation = $row['evaluation'] ?? null;
            if (!$evaluation instanceof criteria_evaluation) {
                throw new invalid_domain_value_exception('A criteria row needs its evaluation.');
            }
            $this->assert_consistent($evaluation);
            if ($student <= 0 || !$DB->record_exists('user', ['id' => $student, 'deleted' => 0])) {
                throw new invalid_domain_value_exception("The student {$student} does not exist.");
            }
            if (isset($seen[$student])) {
                throw new duplicate_record_exception("Student {$student} appears twice in the criteria.");
            }
            $seen[$student] = true;
        }

        $processing = $this->processings->find($processingid)
            ?? throw new \dml_missing_record_exception('local_assistente_processing', 'id = ?', [$processingid]);
        $now ??= time();

        return $this->uow->run(function () use ($courseid, $processingid, $students, $summary, $now): int {
            if ($this->find_by_processing($processingid) !== null) {
                throw new duplicate_record_exception("Processing {$processingid} already has a criteria analysis.");
            }
            $analysisid = $this->insert((object) [
                'course_id' => $courseid,
                'processing_id' => $processingid,
                'analysis_type' => analysis_type::CRITERIA_CHECK->value,
                'cmid' => null,
                'summary' => $summary,
                'created_at' => $now,
            ]);
            foreach ($students as $row) {
                /** @var criteria_evaluation $evaluation */
                $evaluation = $row['evaluation'];
                $this->results->insert((object) [
                    'analysis_id' => $analysisid,
                    'student_id' => (int) $row['student_id'],
                    'activity_status' => $evaluation->activity->value,
                    'forum_status' => $evaluation->forum->value,
                    'frequency_status' => $evaluation->frequency->value,
                ]);
            }
            return $analysisid;
        }, [unit_of_work::event_resource((int) $processing->event_id)]);
    }

    /**
     * The criteria analysis of a processing.
     *
     * @param int $processingid
     * @return \stdClass|null
     */
    public function find_by_processing(int $processingid): ?\stdClass {
        return $this->find_by(['processing_id' => $processingid, 'analysis_type' => analysis_type::CRITERIA_CHECK->value]);
    }

    /**
     * The analyses of a course that have a row of a student, newest first.
     *
     * @param int $courseid
     * @param int $studentid
     * @return \stdClass[]
     */
    public function find_history_for_student(int $courseid, int $studentid): array {
        $history = [];
        $analyses = $this->find_all_by(
            ['course_id' => $courseid, 'analysis_type' => analysis_type::CRITERIA_CHECK->value],
            'created_at DESC, id DESC'
        );
        foreach ($analyses as $analysis) {
            if ($this->results->find_by(['analysis_id' => (int) $analysis->id, 'student_id' => $studentid]) !== null) {
                $history[] = $analysis;
            }
        }
        return $history;
    }

    /**
     * The current analysis of a student in a course: the latest one that has the student, which is the last successful one.
     *
     * @param int $courseid
     * @param int $studentid
     * @return \stdClass|null
     */
    public function find_current_for_student(int $courseid, int $studentid): ?\stdClass {
        $history = $this->find_history_for_student($courseid, $studentid);
        return $history ? reset($history) : null;
    }

    /**
     * The `criteria_result` rows of an analysis, by student.
     *
     * @param int $analysisid
     * @param int|null $studentid restrict to one student
     * @return \stdClass[] indexed by id; [] when there are none
     */
    public function find_results(int $analysisid, ?int $studentid = null): array {
        $conditions = ['analysis_id' => $analysisid];
        if ($studentid !== null) {
            $conditions['student_id'] = $studentid;
        }
        return $this->results->find_all_by($conditions, 'student_id ASC');
    }

    /**
     * The row of a student in an analysis.
     *
     * @param int $analysisid
     * @param int $studentid
     * @return \stdClass|null
     */
    public function find_result(int $analysisid, int $studentid): ?\stdClass {
        return $this->results->find_by(['analysis_id' => $analysisid, 'student_id' => $studentid]);
    }

    /**
     * Check that an evaluation agrees with itself: no data (2) only for a dimension without a value, so that an absence is never stored as
     * a result and a value never as an absence (DEC-24).
     *
     * @param criteria_evaluation $evaluation
     * @throws invalid_domain_value_exception
     */
    private function assert_consistent(criteria_evaluation $evaluation): void {
        foreach ([
            'activity' => [$evaluation->activity, $evaluation->activityvalue],
            'frequency' => [$evaluation->frequency, $evaluation->frequencyvalue],
        ] as $name => [$status, $value]) {
            if (($status === criteria_status::NO_DATA) !== ($value === null)) {
                throw new invalid_domain_value_exception("The {$name} status of a criteria row does not agree with its value.");
            }
        }
    }
}
