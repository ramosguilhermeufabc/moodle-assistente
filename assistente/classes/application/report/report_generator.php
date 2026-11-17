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
namespace local_assistente\application\report;

use local_assistente\domain\report\report_schedule;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\duplicate_record_exception;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\infrastructure\persistence\report_repository;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * Generation of the due report versions (T116; spec-019 contract `updateDueReports` and "Agenda"; DEC-06, DEC-07, DEC-17, DEC-18, DEC-44 D2/D3, P-05).
 *
 * For each course where the assistant is enabled:
 * 1. the schedule is calculated again from the dates of the eligible activities (report_collector), the instant of the current version (the last
 *    success) and `final_completed_at`, so a moved date, a date that disappeared and a reopening (DEC-44 D3) are applied every run, and stored
 *    when it differs from the stored one, without creating a version;
 * 2. a course with a dated activity and no report gets its report, and its first version in the same run (Q-019-04);
 * 3. when the update is due, a version is generated under the Lock API of the report: the due check is repeated inside the lock, so a second
 *    run that waited for the lock finds nothing due and writes nothing. The manifest is built by report_builder and saved by report_repository
 *    together with the new schedule (a successful final update records `final_completed_at`).
 *
 * A failure of one report (reading, building, saving) is returned, leaves the report as it was (the last valid version, the same next update, the same
 * 72 hours) and does not stop the other reports. A lock that is not available is also left for the next run. Nothing here waits or reads the clock:
 * `now` is given.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_generator {
    /**
     * Constructor.
     *
     * @param report_repository|null $reports
     * @param report_collector|null $collector
     * @param report_builder|null $builder
     * @param config_repository|null $configs
     * @param unit_of_work|null $uow
     * @param string|null $timezone IANA identifier of the site time zone; the Moodle server time zone when null
     */
    public function __construct(
        private ?report_repository $reports = null,
        private ?report_collector $collector = null,
        private ?report_builder $builder = null,
        private ?config_repository $configs = null,
        private ?unit_of_work $uow = null,
        private ?string $timezone = null
    ) {
        $this->reports ??= new report_repository();
        $this->collector ??= new report_collector();
        $this->builder ??= new report_builder();
        $this->configs ??= new config_repository();
        $this->uow ??= new unit_of_work();
    }

    /**
     * Generate the versions that are due at the given instant.
     *
     * @param int $now
     * @return array{generated: int[], failed: array<int, string>, skipped: int[]} course ids with a new version; course id => error message;
     *     course ids left for the next run (lock not available)
     */
    public function generate_due(int $now): array {
        $result = ['generated' => [], 'failed' => [], 'skipped' => []];
        foreach ($this->configs->find_enabled_course_ids() as $courseid) {
            try {
                if ($this->process($courseid, $now)) {
                    $result['generated'][] = $courseid;
                }
            } catch (lock_unavailable_exception) {
                $result['skipped'][] = $courseid;
            } catch (\Throwable $e) {
                $result['failed'][$courseid] = $e->getMessage();
            }
        }
        return $result;
    }

    /**
     * One course: bring the schedule up to date and generate the version when it is due.
     *
     * @param int $courseid
     * @param int $now
     * @return bool whether a version was generated
     */
    private function process(int $courseid, int $now): bool {
        $dates = $this->collector->activity_dates($courseid);
        $report = $this->reports->find_by_course($courseid);
        if ($report === null) {
            if (!$dates) {
                return false;
            }
            try {
                $report = $this->reports->create($courseid, report_schedule::calculate([], null, 'UTC', null, 0), $now);
            } catch (duplicate_record_exception) {
                // Another run created it first (the unique key of the course): use that one.
                $report = $this->reports->find_by_course($courseid);
            }
        }

        $reportid = (int) $report->id;
        return $this->uow->run_locked(unit_of_work::report_resource($reportid), function () use ($reportid, $courseid, $dates, $now): bool {
            $report = $this->reports->find($reportid);
            $current = $this->reports->find_current_version($reportid);
            $zone = $this->timezone ?? \core_date::get_server_timezone();
            $final_completed_at = $report->final_completed_at === null ? null : (int) $report->final_completed_at;

            if ($current === null) {
                // First version: the report was just born, or the first run did not finish.
                $schedule = report_schedule::calculate($dates, $now, $zone, null, $now);
                if ($schedule->final_activity_date === null) {
                    $this->reports->save_schedule($reportid, $schedule);
                    return false;
                }
                $type = $schedule->final_update_at <= $now ? report_schedule::TYPE_FINAL : report_schedule::TYPE_PERIODIC;
            } else {
                $schedule = report_schedule::calculate($dates, (int) $current->created_at, $zone, $final_completed_at, $now);
                $type = $schedule->due_type($now);
                if ($type === null) {
                    if ($this->differs($report, $schedule)) {
                        $this->reports->save_schedule($reportid, $schedule);
                    }
                    return false;
                }
            }

            $manifest = $this->builder->build($type, $now, $this->collector->blocks($courseid));
            $this->reports->add_version($reportid, $manifest, $schedule->after_success($now, $type), $now);
            return true;
        });
    }

    /**
     * Whether the stored schedule differs from the calculated one.
     *
     * @param \stdClass $report
     * @param report_schedule $schedule
     * @return bool
     */
    private function differs(\stdClass $report, report_schedule $schedule): bool {
        $stored = static fn($value): ?int => $value === null ? null : (int) $value;
        return $stored($report->next_update_at) !== $schedule->next_update_at
            || $stored($report->final_activity_date) !== $schedule->final_activity_date
            || $stored($report->final_update_at) !== $schedule->final_update_at
            || $stored($report->final_completed_at) !== $schedule->final_completed_at;
    }
}
