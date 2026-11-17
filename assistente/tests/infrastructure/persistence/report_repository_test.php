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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

require_once(__DIR__ . '/../../fixtures/lock_observer.php');

/**
 * Tests of the repository of the report and its versions (T113; quickstart 019 at persistence level; DEC-23, DEC-29, DEC-44).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_repository::class)]
final class report_repository_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var string Site time zone of the schedules. */
    private const SITE_TZ = 'America/Sao_Paulo';

    /** @var string A valid manifest. */
    private const MANIFEST = '{"type":"periodic","generated_at":1,"blocks":[]}';

    /** @var report_repository */
    private report_repository $repository;

    /** @var \stdClass */
    private \stdClass $course;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new report_repository();
        $this->course = $this->getDataGenerator()->create_course();
    }

    protected function tearDown(): void {
        $this->end_actors();
        parent::tearDown();
    }

    /**
     * Instant of a local date and time in the site time zone.
     *
     * @param string $local
     * @return int
     */
    private function at(string $local): int {
        return (new \DateTimeImmutable($local, new \DateTimeZone(self::SITE_TZ)))->getTimestamp();
    }

    /**
     * Schedule with a final date on 25/10 at 18:00 and the last success at the given instant.
     *
     * @param int $last_success_at
     * @param int|null $final_completed_at
     * @param int|null $now
     * @return report_schedule
     */
    private function schedule(int $last_success_at, ?int $final_completed_at = null, ?int $now = null): report_schedule {
        return report_schedule::calculate(
            [$this->at('2026-10-25 18:00')],
            $last_success_at,
            self::SITE_TZ,
            $final_completed_at,
            $now ?? $last_success_at
        );
    }

    /**
     * A report with no schedule is created with null dates and no version (DEC-29, DEC-07).
     */
    public function test_report_is_created_without_schedule_and_without_version(): void {
        $report = $this->repository->create((int) $this->course->id, report_schedule::calculate([], null, self::SITE_TZ, null, 0), 100);

        $this->assertSame((int) $this->course->id, (int) $report->course_id);
        $this->assertSame(100, (int) $report->created_at);
        foreach (['next_update_at', 'final_activity_date', 'final_update_at', 'final_completed_at', 'current_version'] as $field) {
            $this->assertNull($report->$field, $field);
        }
        $this->assertNull($this->repository->find_current_version((int) $report->id));
        $this->assertSame([], $this->repository->find_history((int) $report->id));
        $this->assertSame($report->id, $this->repository->find_by_course((int) $this->course->id)->id);
        $this->assertSame([], $this->repository->find_due(PHP_INT_MAX));
    }

    /**
     * The schedule fields of the domain are stored as they are.
     */
    public function test_schedule_is_stored_as_calculated(): void {
        $last = $this->at('2026-10-24 12:00');
        $schedule = $this->schedule($last);

        $report = $this->repository->create((int) $this->course->id, $schedule);

        $this->assertSame($schedule->next_update_at, (int) $report->next_update_at);
        $this->assertSame($this->at('2026-10-25 18:00'), (int) $report->final_activity_date);
        $this->assertSame($this->at('2026-10-26 00:00'), (int) $report->final_update_at);
        $this->assertNull($report->final_completed_at);
    }

    /**
     * One report for each course, and only for a course that exists; nothing is written when it is refused.
     */
    public function test_one_report_per_course_and_the_course_must_exist(): void {
        global $DB;
        $schedule = $this->schedule($this->at('2026-10-24 12:00'));
        $this->repository->create((int) $this->course->id, $schedule);

        try {
            $this->repository->create((int) $this->course->id, $schedule);
            $this->fail('A second report for the course must be refused.');
        } catch (duplicate_record_exception) {
            $this->assertSame(1, $DB->count_records('local_assistente_report', ['course_id' => $this->course->id]));
        }
        $this->expectException(invalid_domain_value_exception::class);
        $this->repository->create(999999, $schedule);
    }

    /**
     * The first version is 1 and becomes current together with the new schedule; the next is 2 and the first is kept.
     */
    public function test_versions_are_numbered_and_current_version_is_switched(): void {
        $report = $this->repository->create((int) $this->course->id, $this->schedule($this->at('2026-10-20 12:00')));
        $first_at = $this->at('2026-10-20 12:00');

        $first = $this->repository->add_version((int) $report->id, self::MANIFEST, $this->schedule($first_at), $first_at);
        $second_at = $first_at + 72 * 3600;
        $second = $this->repository->add_version((int) $report->id, '{"type":"periodic","blocks":[1]}', $this->schedule($second_at), $second_at);

        $this->assertSame([1, 2], [(int) $first->version, (int) $second->version]);
        $this->assertSame($second->id, $this->repository->find_current_version((int) $report->id)->id);
        $this->assertSame((int) $second->id, (int) $this->repository->find($report->id)->current_version);
        $this->assertSame(self::MANIFEST, $this->repository->find_version((int) $report->id, 1)->snapshot_json);
        $this->assertSame([2, 1], array_map(fn($v) => (int) $v->version, array_values($this->repository->find_history((int) $report->id))));
        $this->assertSame($second_at, (int) $second->created_at);
    }

    /**
     * Saving a version stores the next 72 hours from that version, and the periodic report becomes due at that instant.
     */
    public function test_new_version_stores_the_next_update_and_the_report_becomes_due(): void {
        $report = $this->repository->create((int) $this->course->id, $this->schedule($this->at('2026-10-20 12:00')));
        $at = $this->at('2026-10-21 08:00');
        $schedule = $this->schedule($at)->after_success($at, report_schedule::TYPE_PERIODIC);

        $this->repository->add_version((int) $report->id, self::MANIFEST, $schedule, $at);

        $stored = $this->repository->find($report->id);
        $this->assertSame($at + 72 * 3600, (int) $stored->next_update_at);
        $this->assertArrayNotHasKey((int) $report->id, $this->repository->find_due($at + 72 * 3600 - 1));
        $this->assertArrayHasKey((int) $report->id, $this->repository->find_due($at + 72 * 3600));
    }

    /**
     * The final version closes the cycle: final_completed_at is stored and the report is no longer due (Q3).
     */
    public function test_final_version_completes_the_report(): void {
        $report = $this->repository->create((int) $this->course->id, $this->schedule($this->at('2026-10-24 12:00')));
        $final_at = $this->at('2026-10-26 00:05');
        $closed = $this->schedule($this->at('2026-10-24 12:00'))->after_success($final_at, report_schedule::TYPE_FINAL);

        $this->repository->add_version((int) $report->id, '{"type":"final","blocks":[]}', $closed, $final_at);

        $stored = $this->repository->find($report->id);
        $this->assertSame($final_at, (int) $stored->final_completed_at);
        $this->assertNull($stored->next_update_at);
        $this->assertSame([], $this->repository->find_due(PHP_INT_MAX));
    }

    /**
     * Reopening (Q14): the schedule is saved with final_completed_at null and the versions, the final one included, stay.
     */
    public function test_reopening_clears_final_completed_at_and_keeps_the_versions(): void {
        $report = $this->repository->create((int) $this->course->id, $this->schedule($this->at('2026-10-24 12:00')));
        $final_at = $this->at('2026-10-26 00:05');
        $closed = $this->schedule($this->at('2026-10-24 12:00'))->after_success($final_at, report_schedule::TYPE_FINAL);
        $final = $this->repository->add_version((int) $report->id, '{"type":"final","blocks":[]}', $closed, $final_at);

        $reopened = report_schedule::calculate(
            [$this->at('2026-11-05 18:00')],
            $final_at,
            self::SITE_TZ,
            $closed->final_completed_at,
            $this->at('2026-10-27 10:00')
        );
        $this->repository->save_schedule((int) $report->id, $reopened);

        $stored = $this->repository->find($report->id);
        $this->assertTrue($reopened->reopens($closed));
        $this->assertNull($stored->final_completed_at);
        $this->assertSame($this->at('2026-11-06 00:00'), (int) $stored->final_update_at);
        $this->assertSame($final_at + 72 * 3600, (int) $stored->next_update_at);
        $this->assertSame((int) $final->id, (int) $stored->current_version);
        $this->assertCount(1, $this->repository->find_history((int) $report->id));
    }

    /**
     * Q6: a schedule with no final date is stored with nulls, replacing a previous one; the versions stay.
     */
    public function test_schedule_can_go_back_to_nulls(): void {
        $report = $this->repository->create((int) $this->course->id, $this->schedule($this->at('2026-10-24 12:00')));
        $this->repository->add_version((int) $report->id, self::MANIFEST, $this->schedule($this->at('2026-10-24 12:00')));

        $this->repository->save_schedule((int) $report->id, report_schedule::calculate([null], $this->at('2026-10-24 12:00'), self::SITE_TZ, null, 0));

        $stored = $this->repository->find($report->id);
        $this->assertNull($stored->next_update_at);
        $this->assertNull($stored->final_activity_date);
        $this->assertNull($stored->final_update_at);
        $this->assertCount(1, $this->repository->find_history((int) $report->id));
    }

    /**
     * The reports that are due come oldest first, and only those with a next update on or before the instant.
     */
    public function test_find_due_returns_the_due_reports_oldest_first(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $late = $generator->create_report(['next_update_at' => 2000]);
        $early = $generator->create_report(['next_update_at' => 1000]);
        $generator->create_report(['next_update_at' => 3000]);
        $generator->create_report();

        $this->assertSame([(int) $early->id, (int) $late->id], array_map('intval', array_keys($this->repository->find_due(2000))));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid_manifests(): array {
        return ['empty' => [''], 'not json' => ['{oops'], 'empty object' => ['{}'], 'scalar' => ['"x"'], 'null' => ['null']];
    }

    /**
     * A manifest that is not a non-empty JSON object is refused and nothing is written.
     *
     * @param string $manifest
     */
    #[DataProvider('invalid_manifests')]
    public function test_invalid_manifest_writes_nothing(string $manifest): void {
        global $DB;
        $report = $this->repository->create((int) $this->course->id, $this->schedule($this->at('2026-10-24 12:00')));

        try {
            $this->repository->add_version((int) $report->id, $manifest, $this->schedule($this->at('2026-10-25 12:00')));
            $this->fail('An invalid manifest must be refused.');
        } catch (invalid_domain_value_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_report_version'));
            $this->assertNull($this->repository->find($report->id)->current_version);
        }
    }

    /**
     * A report that does not exist gets no version and no schedule.
     */
    public function test_missing_report_is_refused_and_nothing_is_written(): void {
        global $DB;
        try {
            $this->repository->add_version(999999, self::MANIFEST, $this->schedule($this->at('2026-10-24 12:00')));
            $this->fail('A version of a missing report must be refused.');
        } catch (\dml_missing_record_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_report_version'));
        }
        $this->assertNull($this->repository->find_by_course(999999));
        $this->assertNull($this->repository->find_current_version(999999));
    }

    /**
     * A failure after the version row was written rolls back the version, the switch of current_version and the schedule: a database
     * trigger refuses the update of a report that already has a current version.
     */
    public function test_failure_midway_keeps_the_last_valid_version_and_schedule(): void {
        global $DB;
        $report = $this->repository->create((int) $this->course->id, $this->schedule($this->at('2026-10-20 12:00')));
        $first_at = $this->at('2026-10-20 12:00');
        $first = $this->repository->add_version((int) $report->id, self::MANIFEST, $this->schedule($first_at), $first_at);
        $before = $this->repository->find($report->id);
        $table = $DB->get_prefix() . 'local_assistente_report';
        try {
            $DB->execute("CREATE TRIGGER t113_refuse BEFORE UPDATE ON {$table} FOR EACH ROW
                SET NEW.course_id = IF(OLD.current_version IS NOT NULL AND NEW.current_version <> OLD.current_version, NULL, NEW.course_id)");
        } catch (\dml_exception $e) {
            $this->fail('PRÉ-REQUISITO AUSENTE: the trigger of the test could not be created (' . get_class($e) . '): ' . $e->getMessage());
        }

        try {
            $second_at = $first_at + 72 * 3600;
            $this->repository->add_version((int) $report->id, self::MANIFEST, $this->schedule($second_at), $second_at);
            $saved = true;
        } catch (\dml_exception) {
            $saved = false;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t113_refuse');
        }

        $this->assertFalse($saved, 'The trigger must have refused the switch of the current version');
        $this->assertEquals($before, $this->repository->find($report->id));
        $this->assertSame((int) $first->id, (int) $this->repository->find_current_version((int) $report->id)->id);
        $this->assertCount(1, $this->repository->find_history((int) $report->id));
        // The number that was not used is taken by the next attempt.
        $third = $this->repository->add_version((int) $report->id, self::MANIFEST, $this->schedule($first_at + 80 * 3600));
        $this->assertSame(2, (int) $third->version);
    }

    /**
     * While the lock of the report is held by another process the unit does not start, and nothing is written.
     *
     * The lock is held by an independent process (a real connection to the database), so the refusal comes from GET_LOCK on the server and
     * not from the internal map of the lock factory of this process (correction 1 of the CL-07 matrix).
     */
    public function test_version_is_not_written_without_the_lock_of_the_report(): void {
        $this->assert_observable_locks();
        $report = $this->repository->create((int) $this->course->id, $this->schedule($this->at('2026-10-20 12:00')));
        $resource = unit_of_work::report_resource((int) $report->id);
        $holder = $this->hold_resource($resource, 'report-holder');
        $this->assertSame($holder['connection'], $this->lock_owner($resource), 'The other process must own the lock of the report');
        $repository = new report_repository(new unit_of_work(1));

        try {
            $repository->add_version((int) $report->id, self::MANIFEST, $this->schedule($this->at('2026-10-21 12:00')));
            $this->fail('The version must not be written while another process holds the lock of the report');
        } catch (lock_unavailable_exception $e) {
            $this->assertSame($resource, $e->resource(), 'The exception must name the lock of the report');
        }
        $this->assertSame([], $repository->find_history((int) $report->id));
        $this->assertNull($repository->find($report->id)->current_version);
        $this->assertSame($holder['connection'], $this->lock_owner($resource), 'The other process must still own the lock');

        $this->release_actor($holder);
        $this->await_lock_free($resource);
        $answer = $this->finish_actor($holder, 60);
        $this->assertNull($answer['error'], 'Error of the holder: ' . var_export($answer['error'], true));
    }

    /**
     * The generator creates reports and numbered versions that follow the schema.
     */
    public function test_generator_rows_follow_the_schema(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $version = $generator->create_report_version();
        $second = $generator->create_report_version(['report_id' => $version->report_id]);

        $this->assertSame([1, 2], [(int) $version->version, (int) $second->version]);
        $this->assertSame((int) $second->id, (int) $this->repository->find((int) $version->report_id)->current_version);
    }
}
