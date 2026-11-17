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
use local_assistente\domain\frequency\frequency_result;
use local_assistente\domain\result_state;
use local_assistente\domain\truncated_percentage;

/**
 * Repository of the frequency of the students: the aggregate `analysis` (type `frequency`) with its `frequency` rows, one for each
 * student and section, and their `frequency_activity` rows, one for each activity of the denominator (plan 003 "Agregados"; spec-014,
 * data-model; T086).
 *
 * Rules kept here:
 * - Each execution writes one immutable analysis, with all its rows, in one transaction, under the lock of the event of the processing.
 *   The current analysis is the latest one; a failed execution writes nothing, so the latest is the last successful one. No method
 *   changes or deletes an analysis.
 * - One analysis for each processing: a second save for the same processing is rejected (duplicate_record_exception) and leaves the first
 *   untouched.
 * - One `frequency` row for each student and section in an analysis (unique index), and one `frequency_activity` row for each activity of
 *   the denominator of that student (unique by `frequency` row and activity). The row of a student with no activity in the denominator is
 *   written, with total zero, percentage 0 and the state `no_data` derived from the total (DEC-24); the state has no column.
 * - `total_activities` is the number of activity rows and `participating_activities` the number of activities with `participated` = 1, so
 *   participating never exceeds total (spec-014). The result is checked for that before anything is written.
 * - Only derived results are stored, never the interactions themselves (spec-014, "Conformidade"): no submission, post, attempt or access
 *   data, and no content of any student. The access to the course goes in the context snapshot of the processing (DEC-39 D3).
 * - The student must exist, the section must belong to the course, and each activity must be a course module of the course.
 *
 * It holds no reading of Moodle and no calculation: it does not call the calculator (T087).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class frequency_repository extends repository {
    /** @var table_repository the frequency table */
    private table_repository $frequencies;

    /** @var table_repository the frequency_activity table */
    private table_repository $activities;

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
        $this->frequencies = new table_repository('local_assistente_frequency');
        $this->activities = new table_repository('local_assistente_frequency_activity');
        $this->processings = new table_repository('local_assistente_processing');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_analysis';
    }

    /**
     * Save the frequency of some students in one execution, all or nothing.
     *
     * @param int $courseid
     * @param int $processingid
     * @param array $frequencies list of ['student_id' => int, 'section_id' => int, 'result' => frequency_result]; may be empty
     * @param string|null $summary
     * @param int|null $now
     * @return int the id of the analysis
     * @throws invalid_domain_value_exception when a value or a reference is invalid (nothing is written)
     * @throws duplicate_record_exception when the processing already has an analysis, or a student and section are repeated
     * @throws \dml_missing_record_exception when the processing does not exist
     */
    public function save(int $courseid, int $processingid, array $frequencies, ?string $summary = null, ?int $now = null): int {
        global $DB;
        $seen = [];
        foreach ($frequencies as $row) {
            $student = (int) ($row['student_id'] ?? 0);
            $section = (int) ($row['section_id'] ?? 0);
            $result = $row['result'] ?? null;
            if (!$result instanceof frequency_result) {
                throw new invalid_domain_value_exception('A frequency row needs its result.');
            }
            $this->assert_consistent($result);
            if ($student <= 0 || !$DB->record_exists('user', ['id' => $student, 'deleted' => 0])) {
                throw new invalid_domain_value_exception("The student {$student} does not exist.");
            }
            if ($section <= 0 || !$DB->record_exists('course_sections', ['id' => $section, 'course' => $courseid])) {
                throw new invalid_domain_value_exception("The section {$section} is not a section of course {$courseid}.");
            }
            if (isset($seen["{$student}:{$section}"])) {
                throw new duplicate_record_exception("Student {$student} and section {$section} appear twice in the frequency.");
            }
            $seen["{$student}:{$section}"] = true;
            foreach ($result->activities as $activity) {
                if (!$DB->record_exists('course_modules', ['id' => $activity['activity_id'], 'course' => $courseid])) {
                    throw new invalid_domain_value_exception("The activity {$activity['activity_id']} is not a module of course {$courseid}.");
                }
            }
        }

        $processing = $this->processings->find($processingid)
            ?? throw new \dml_missing_record_exception('local_assistente_processing', 'id = ?', [$processingid]);
        $now ??= time();

        return $this->uow->run(function () use ($courseid, $processingid, $frequencies, $summary, $now): int {
            if ($this->find_by_processing($processingid) !== null) {
                throw new duplicate_record_exception("Processing {$processingid} already has a frequency analysis.");
            }
            $analysisid = $this->insert((object) [
                'course_id' => $courseid,
                'processing_id' => $processingid,
                'analysis_type' => analysis_type::FREQUENCY->value,
                'cmid' => null,
                'summary' => $summary,
                'created_at' => $now,
            ]);
            foreach ($frequencies as $row) {
                /** @var frequency_result $result */
                $result = $row['result'];
                $frequencyid = $this->frequencies->insert((object) [
                    'analysis_id' => $analysisid,
                    'student_id' => (int) $row['student_id'],
                    'section_id' => (int) $row['section_id'],
                    'total_activities' => $result->total,
                    'participating_activities' => $result->participating,
                    'percentage' => $result->percentage,
                ]);
                foreach ($result->activities as $activity) {
                    $this->activities->insert((object) [
                        'frequency_id' => $frequencyid,
                        'activity_id' => $activity['activity_id'],
                        'participated' => $activity['participated'],
                        'reference_date' => $activity['reference_date'],
                    ]);
                }
            }
            return $analysisid;
        }, [unit_of_work::event_resource((int) $processing->event_id)]);
    }

    /**
     * The frequency analysis of a processing.
     *
     * @param int $processingid
     * @return \stdClass|null
     */
    public function find_by_processing(int $processingid): ?\stdClass {
        return $this->find_by(['processing_id' => $processingid, 'analysis_type' => analysis_type::FREQUENCY->value]);
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
            ['course_id' => $courseid, 'analysis_type' => analysis_type::FREQUENCY->value],
            'created_at DESC, id DESC'
        );
        foreach ($analyses as $analysis) {
            // A student has one row for each section, so the existence is asked for all of them, not for one record.
            if ($this->frequencies->find_all_by(['analysis_id' => (int) $analysis->id, 'student_id' => $studentid]) !== []) {
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
     * The `frequency` rows of an analysis, by student and section.
     *
     * @param int $analysisid
     * @param int|null $studentid restrict to one student
     * @return \stdClass[] indexed by id; [] when there are none
     */
    public function find_frequencies(int $analysisid, ?int $studentid = null): array {
        $conditions = ['analysis_id' => $analysisid];
        if ($studentid !== null) {
            $conditions['student_id'] = $studentid;
        }
        return $this->frequencies->find_all_by($conditions, 'student_id ASC, section_id ASC');
    }

    /**
     * The activities of a `frequency` row, by activity.
     *
     * @param int $frequencyid
     * @return \stdClass[] indexed by id; [] when there are none
     */
    public function find_activities(int $frequencyid): array {
        return $this->activities->find_all_by(['frequency_id' => $frequencyid], 'activity_id ASC');
    }

    /**
     * Check that a result agrees with itself: the total is the number of activities, the participating the number that took part, the
     * percentage is the one that follows, and the state is no_data only for a total of zero.
     *
     * @param frequency_result $result
     * @throws invalid_domain_value_exception
     */
    private function assert_consistent(frequency_result $result): void {
        $participated = 0;
        foreach ($result->activities as $activity) {
            if (!in_array($activity['participated'] ?? null, [0, 1], true) || (int) ($activity['reference_date'] ?? 0) <= 0
                    || (int) ($activity['activity_id'] ?? 0) <= 0) {
                throw new invalid_domain_value_exception('An activity row needs an activity, participated 0 or 1 and a reference date.');
            }
            $participated += $activity['participated'];
        }
        $total = count($result->activities);
        $state = $total === 0 ? result_state::NO_DATA : result_state::VALID;
        if ($result->total !== $total || $result->participating !== $participated || $result->state !== $state) {
            throw new invalid_domain_value_exception('The totals of a frequency do not agree with its activities.');
        }
        $expected = $total === 0 ? 0.0 : truncated_percentage::of($participated / $total * 100);
        if (abs($result->percentage - $expected) > 0.0005) {
            throw new invalid_domain_value_exception('The percentage of a frequency does not agree with its totals.');
        }
    }
}
