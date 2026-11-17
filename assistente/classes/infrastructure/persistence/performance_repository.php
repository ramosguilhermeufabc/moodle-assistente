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
 * Repository of the performance of a student in a course: the aggregate `analysis` (type `grade_performance`) with its `performance`
 * rows, one for each activity with a grade (plan 003 "Agregados"; spec-012, data-model; T077).
 *
 * Rules kept here:
 * - Each execution writes one immutable analysis, with its rows, in one transaction, under the lock of the event of the processing.
 *   The current analysis of a student is the latest one; a failed execution writes nothing, so the latest is the last successful
 *   (spec-012, "Histórico"). No method changes or deletes an analysis.
 * - One analysis for each processing: a second save for the same processing is rejected (duplicate_record_exception) and leaves the first
 *   untouched.
 * - `no_data` is the absence of the row, never a zero (DEC-24, P-06): an activity with no grade is simply not passed, and an analysis
 *   with no row is valid (a student with nothing graded). A real zero is a row. `error` is a processing in `failure`; it leaves no
 *   analysis here.
 * - One row for each activity in an analysis. The activity must be a course module of the course and the student an existing user. The
 *   grade is the percentage of the range of the item (grade_normalizer), a number that fits `number(10,3)`; it is not limited to 0..100.
 * - The analysis table has no student column: the student of an analysis is the `userid` of the grade event of its processing
 *   (find_history_for_student), which also covers an analysis with no rows. The average of the student, the final total of the course,
 *   is not stored here: it goes in the context snapshot of the analysis (DEC-37 D3).
 *
 * It holds no reading of the gradebook and no calculation: it does not call the normalizer or read grades (T078).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class performance_repository extends repository {
    /** @var float largest absolute value that number(10,3) holds */
    private const MAX_GRADE = 9999999.999;

    /** @var table_repository the performance table */
    private table_repository $performance;

    /** @var table_repository the processing table, to find the event to lock */
    private table_repository $processings;

    /** @var table_repository the event table, to find the student of a processing */
    private table_repository $events;

    /**
     * Constructor.
     *
     * @param unit_of_work $uow
     */
    public function __construct(
        private readonly unit_of_work $uow = new unit_of_work()
    ) {
        $this->performance = new table_repository('local_assistente_performance');
        $this->processings = new table_repository('local_assistente_processing');
        $this->events = new table_repository('local_assistente_event');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_analysis';
    }

    /**
     * Save the performance of a student in one execution, all or nothing.
     *
     * @param int $courseid
     * @param int $processingid
     * @param int $studentid
     * @param array $grades list of ['activity_id' => int, 'grade' => float]: only the activities with a grade; may be empty
     * @param string|null $summary
     * @param int|null $now
     * @return int the id of the analysis
     * @throws invalid_domain_value_exception when a value or a reference is invalid (nothing is written)
     * @throws duplicate_record_exception when the processing already has an analysis, or an activity is repeated
     * @throws \dml_missing_record_exception when the processing does not exist
     */
    public function save(
        int $courseid,
        int $processingid,
        int $studentid,
        array $grades,
        ?string $summary = null,
        ?int $now = null
    ): int {
        global $DB;
        if ($studentid <= 0 || !$DB->record_exists('user', ['id' => $studentid, 'deleted' => 0])) {
            throw new invalid_domain_value_exception("The student {$studentid} does not exist.");
        }
        $seen = [];
        foreach ($grades as $row) {
            $activity = (int) ($row['activity_id'] ?? 0);
            $grade = $row['grade'] ?? null;
            if ($activity <= 0 || !is_numeric($grade) || !is_finite((float) $grade) || abs((float) $grade) > self::MAX_GRADE) {
                throw new invalid_domain_value_exception('A performance row needs an activity and a grade that fits number(10,3).');
            }
            if (isset($seen[$activity])) {
                throw new duplicate_record_exception("Activity {$activity} appears twice in the performance.");
            }
            $seen[$activity] = true;
            if (!$DB->record_exists('course_modules', ['id' => $activity, 'course' => $courseid])) {
                throw new invalid_domain_value_exception("The activity {$activity} is not a module of course {$courseid}.");
            }
        }

        $processing = $this->processings->find($processingid)
            ?? throw new \dml_missing_record_exception('local_assistente_processing', 'id = ?', [$processingid]);
        $now ??= time();

        return $this->uow->run(function () use ($courseid, $processingid, $studentid, $grades, $summary, $now): int {
            if ($this->find_by_processing($processingid) !== null) {
                throw new duplicate_record_exception("Processing {$processingid} already has a performance analysis.");
            }
            $analysisid = $this->insert((object) [
                'course_id' => $courseid,
                'processing_id' => $processingid,
                'analysis_type' => analysis_type::GRADE_PERFORMANCE->value,
                'cmid' => null,
                'summary' => $summary,
                'created_at' => $now,
            ]);
            foreach ($grades as $row) {
                $this->performance->insert((object) [
                    'analysis_id' => $analysisid,
                    'student_id' => $studentid,
                    'activity_id' => (int) $row['activity_id'],
                    'grade' => (float) $row['grade'],
                ]);
            }
            return $analysisid;
        }, [unit_of_work::event_resource((int) $processing->event_id)]);
    }

    /**
     * The performance analysis of a processing.
     *
     * @param int $processingid
     * @return \stdClass|null
     */
    public function find_by_processing(int $processingid): ?\stdClass {
        return $this->find_by(['processing_id' => $processingid, 'analysis_type' => analysis_type::GRADE_PERFORMANCE->value]);
    }

    /**
     * The analyses of a student in a course, newest first. The student of an analysis is the user of the grade event that caused it.
     *
     * @param int $courseid
     * @param int $studentid
     * @return \stdClass[]
     */
    public function find_history_for_student(int $courseid, int $studentid): array {
        $history = [];
        $analyses = $this->find_all_by([
            'course_id' => $courseid,
            'analysis_type' => analysis_type::GRADE_PERFORMANCE->value,
        ], 'created_at DESC, id DESC');
        foreach ($analyses as $analysis) {
            $processing = $this->processings->find((int) $analysis->processing_id);
            $event = $processing === null ? null : $this->events->find((int) $processing->event_id);
            $payload = $event === null ? null : json_decode((string) $event->payload, true);
            if (is_array($payload) && ($payload['userid'] ?? null) === $studentid) {
                $history[] = $analysis;
            }
        }
        return $history;
    }

    /**
     * The current analysis of a student in a course: the latest one, which is the last successful.
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
     * The rows of an analysis, by activity.
     *
     * @param int $analysisid
     * @return \stdClass[] indexed by id; [] when there are none (nothing graded, or no such analysis)
     */
    public function find_rows(int $analysisid): array {
        return $this->performance->find_all_by(['analysis_id' => $analysisid], 'activity_id ASC');
    }
}
