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

use local_assistente\domain\report\report_schedule;

/**
 * Repository of the report of a course and its versions: the aggregate `report` with its `report_version` rows (plan 003 "Agregados" and
 * "Unidades transacionais"; spec-019 data-model and contract; DEC-23, DEC-29, DEC-44; T113).
 *
 * Rules kept here:
 * - One report for each course (the foreign-unique key of `course_id`); it is created without a version and without being a decision about when
 *   to generate one.
 * - A version is born in one transaction, under the Lock API of the report (unit_of_work::report_resource): the number is `max(version) + 1` read
 *   inside the lock (the first is 1), the version row is written first, and only then `current_version` is switched to it, together with the
 *   four schedule fields of the report. A failure rolls everything back, so the report keeps its last valid version and its schedule. The unique
 *   index (report, version) is the last guard if the lock was somehow skipped (CL-07 and CL-39 validate this on the target database).
 * - The current version is the one `report.current_version` points to, not the highest number. No method changes or deletes a version.
 * - The schedule fields are nullable (DEC-29) and come from {@see report_schedule}, which this class does not duplicate: it stores what that
 *   class calculated. The state read back is the record; the schedule is calculated again from the dates of the activities, the instant of the
 *   current version (the last success) and `final_completed_at`.
 * - `snapshot_json` is the manifest of DEC-44 D1, built by T114. Here it is only required to be a non-empty JSON object (no values are interpreted).
 *
 * It holds no reading of Moodle, no scheduled task and no decision about whether an update is due; that is the dispatcher (T116).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_repository extends repository {
    /** @var table_repository the report_version table */
    private table_repository $versions;

    /**
     * Constructor.
     *
     * @param unit_of_work $uow
     */
    public function __construct(
        private readonly unit_of_work $uow = new unit_of_work()
    ) {
        $this->versions = new table_repository('local_assistente_report_version');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_report';
    }

    /**
     * Create the report of a course, with no version yet.
     *
     * @param int $courseid
     * @param report_schedule $schedule schedule to store (may hold nulls)
     * @param int|null $now
     * @return \stdClass the stored report
     * @throws invalid_domain_value_exception when the course does not exist (nothing is written)
     * @throws duplicate_record_exception when the course already has a report
     */
    public function create(int $courseid, report_schedule $schedule, ?int $now = null): \stdClass {
        global $DB;
        if ($courseid <= 0 || !$DB->record_exists('course', ['id' => $courseid])) {
            throw new invalid_domain_value_exception("The course {$courseid} does not exist.");
        }
        $record = (object) ['course_id' => $courseid, 'created_at' => $now ?? time(), 'current_version' => null];
        $id = $this->insert($this->with_schedule($record, $schedule));
        return $this->find($id);
    }

    /**
     * Save a new version of the report and its new schedule, all or nothing.
     *
     * @param int $reportid
     * @param string $snapshot_json manifest of the version (a non-empty JSON object)
     * @param report_schedule $schedule schedule after this version (for instance report_schedule::after_success())
     * @param int|null $now
     * @return \stdClass the stored `report_version`
     * @throws invalid_domain_value_exception when the manifest is not a non-empty JSON object (nothing is written)
     * @throws \dml_missing_record_exception when the report does not exist
     * @throws lock_unavailable_exception when the lock of the report cannot be acquired
     */
    public function add_version(int $reportid, string $snapshot_json, report_schedule $schedule, ?int $now = null): \stdClass {
        $decoded = json_decode($snapshot_json, true);
        if (!is_array($decoded) || $decoded === []) {
            throw new invalid_domain_value_exception('The snapshot of a report version must be a non-empty JSON object.');
        }
        $now ??= time();

        $id = $this->uow->run(function () use ($reportid, $snapshot_json, $schedule, $now): int {
            $report = $this->find($reportid)
                ?? throw new \dml_missing_record_exception($this->table(), 'id = ?', [$reportid]);
            $versionid = $this->versions->insert((object) [
                'report_id' => $reportid,
                'version' => 1 + $this->highest_number($reportid),
                'snapshot_json' => $snapshot_json,
                'created_at' => $now,
            ]);
            $report->current_version = $versionid;
            $this->update($this->with_schedule($report, $schedule));
            return $versionid;
        }, [unit_of_work::report_resource($reportid)]);

        return $this->versions->find($id);
    }

    /**
     * Replace the schedule of a report without creating a version (a changed date, a reopened cycle), under the lock of the report.
     *
     * @param int $reportid
     * @param report_schedule $schedule
     * @throws \dml_missing_record_exception when the report does not exist
     * @throws lock_unavailable_exception when the lock of the report cannot be acquired
     */
    public function save_schedule(int $reportid, report_schedule $schedule): void {
        $this->uow->run(function () use ($reportid, $schedule): void {
            $report = $this->find($reportid)
                ?? throw new \dml_missing_record_exception($this->table(), 'id = ?', [$reportid]);
            $this->update($this->with_schedule($report, $schedule));
        }, [unit_of_work::report_resource($reportid)]);
    }

    /**
     * The report of a course.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public function find_by_course(int $courseid): ?\stdClass {
        return $this->find_by(['course_id' => $courseid]);
    }

    /**
     * The reports whose next update is due, the oldest due first. A report with no schedule (null) is never due.
     *
     * @param int $now
     * @return \stdClass[] indexed by id
     */
    public function find_due(int $now): array {
        global $DB;
        return $DB->get_records_select($this->table(), 'next_update_at IS NOT NULL AND next_update_at <= ?', [$now], 'next_update_at ASC, id ASC');
    }

    /**
     * The current version of a report: the one `current_version` points to.
     *
     * @param int $reportid
     * @return \stdClass|null null when the report does not exist or has no version
     */
    public function find_current_version(int $reportid): ?\stdClass {
        $report = $this->find($reportid);
        return $report && $report->current_version !== null ? $this->versions->find((int) $report->current_version) : null;
    }

    /**
     * A version of a report by its number.
     *
     * @param int $reportid
     * @param int $version
     * @return \stdClass|null
     */
    public function find_version(int $reportid, int $version): ?\stdClass {
        return $this->versions->find_by(['report_id' => $reportid, 'version' => $version]);
    }

    /**
     * All the versions of a report, newest first.
     *
     * @param int $reportid
     * @return \stdClass[] indexed by id; [] when there are none
     */
    public function find_history(int $reportid): array {
        return $this->versions->find_all_by(['report_id' => $reportid], 'version DESC');
    }

    /**
     * Highest version number of a report, 0 when it has none.
     *
     * @param int $reportid
     * @return int
     */
    private function highest_number(int $reportid): int {
        global $DB;
        return (int) $DB->get_field_select('local_assistente_report_version', 'MAX(version)', 'report_id = ?', [$reportid]);
    }

    /**
     * The record with the four schedule fields of the schedule.
     *
     * @param \stdClass $record
     * @param report_schedule $schedule
     * @return \stdClass
     */
    private function with_schedule(\stdClass $record, report_schedule $schedule): \stdClass {
        $record->next_update_at = $schedule->next_update_at;
        $record->final_activity_date = $schedule->final_activity_date;
        $record->final_update_at = $schedule->final_update_at;
        $record->final_completed_at = $schedule->final_completed_at;
        return $record;
    }
}
