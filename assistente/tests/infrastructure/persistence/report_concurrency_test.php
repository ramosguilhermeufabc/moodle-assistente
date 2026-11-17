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

require_once(__DIR__ . '/../../fixtures/lock_observer.php');

/**
 * The versions of one report written by several processes at the same time (CL-07: M-05; DEC-23, DEC-44).
 *
 * Four PHP processes, each with its own connection, begin together (ready/go) and call add_version of the same report, with a valid
 * schedule. In the report the version and the switch of `current_version` are one unit of work (one lock, one transaction), so the version
 * with the highest number is also the one the pointer ends on. That the processes really overlapped in time is checked.
 *
 * Scope: MariaDB, GET_LOCK, processes of the same machine, the database of PHPUnit. A prerequisite that is missing is a failure.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_repository::class)]
final class report_concurrency_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var int processes that write at the same time */
    private const WORKERS = 4;

    /** @var int rounds of each process */
    private const ROUNDS = 8;

    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        unit_of_work::reset_lock_factory();
        $this->clean_tables();
        $this->assert_observable_locks();
    }

    protected function tearDown(): void {
        $this->end_actors();
        $this->clean_tables();
        parent::tearDown();
    }

    /**
     * The reset of PHPUnit does not see what other processes write: empty the tables here, before and after each test.
     */
    private function clean_tables(): void {
        global $DB;
        $DB->delete_records('local_assistente_report_version');
        $DB->delete_records('local_assistente_report');
    }

    /**
     * M-05 (CR-15): four processes adding versions to the same report at the same time get distinct and consecutive numbers, every version
     * has its own manifest, and `current_version` points to the version with the highest number.
     */
    public function test_four_processes_adding_report_versions_get_consecutive_numbers(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $report = (new report_repository())->create((int) $course->id, report_schedule::calculate([], null, 'America/Sao_Paulo', null, time()));

        $specs = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $specs[] = ['mode' => 'report_ops', 'report_id' => (int) $report->id, 'rounds' => self::ROUNDS, 'prefix' => "w{$i}", 'lock_timeout' => 60];
        }
        $group = $this->spawn_group($specs, 'report');
        $this->go_group($group);
        $answers = $this->finish_group($group, 180);

        foreach ($answers as $label => $answer) {
            $this->assertNull($answer['error'], "Error of the worker '{$label}': " . var_export($answer['error'], true));
        }
        $starts = array_column($answers, 'started_at');
        $ends = array_column($answers, 'ended_at');
        $this->assertLessThan(
            min($ends),
            max($starts),
            'The workers did not overlap in time, so this run proves nothing about concurrency. Intervals: ' . json_encode(
                array_map(fn($a) => [round($a['started_at'], 3), round($a['ended_at'], 3)], $answers)
            )
        );
        $failures = [];
        foreach ($answers as $label => $answer) {
            foreach ($answer['results'] as $round) {
                if (empty($round['ok'])) {
                    $failures[] = "{$label} round {$round['round']}: {$round['class']}: {$round['message']}";
                }
            }
        }
        $this->assertSame([], $failures, 'Rounds that failed');

        $versions = array_values($DB->get_records('local_assistente_report_version', ['report_id' => $report->id], 'version ASC'));
        $total = self::WORKERS * self::ROUNDS;
        $this->assertSame(range(1, $total), array_map(fn($v) => (int) $v->version, $versions), 'The numbers of the versions');
        $this->assertCount($total, array_unique(array_map(fn($v) => $v->snapshot_json, $versions)), 'Each round wrote its own version');
        $pointer = (int) $DB->get_field('local_assistente_report', 'current_version', ['id' => $report->id]);
        $this->assertSame((int) end($versions)->id, $pointer, 'current_version must point to the version with the highest number');
    }
}
