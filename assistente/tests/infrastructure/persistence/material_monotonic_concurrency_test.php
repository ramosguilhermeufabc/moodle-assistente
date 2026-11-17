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

use local_assistente\domain\material\material_availability;
use local_assistente\domain\material_type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;

require_once(__DIR__ . '/../../fixtures/lock_observer.php');

/**
 * Monotonicity of `current_version` under concurrency, in independent processes (T144; DEC-62; quickstart 008 Q17; M-12 and M-13).
 *
 * Each worker is a PHP process with its own connection. The versions already exist and are completed, as after the summary; the workers
 * only try the switch. Two kinds of arrival are used:
 * - an order fixed by the test (a worker waits for a signal file until the other has finished), to cover BOTH orders of arrival;
 * - real contention: the test holds the lock of the material, both workers are seen waiting for it (the list of the server), and the lock
 *   is then released. Which of them gets the lock first is not decided by the test, and nothing assumes that the lower number finishes first.
 *
 * What is required is defined by invariants, not by an exception type: the final pointer, that the refused version stays completed and
 * historical, that nothing but the rule refuses a switch (no technical failure), that the pointer never goes back and that a refusal writes
 * nothing. The runs under contention report in which order the lock was actually obtained.
 *
 * Scope: MariaDB, GET_LOCK, processes of the same machine, the database of PHPUnit. A prerequisite that is missing is a failure.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(material_repository::class)]
final class material_monotonic_concurrency_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var int runs of each contended scenario, each with its own material */
    private const CONTENDED_RUNS = 6;

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
        $DB->delete_records('local_assistente_material_version');
        $DB->delete_records('local_assistente_material');
    }

    /**
     * A material with completed versions 1 to $count, none of them current.
     *
     * @param int $cmid
     * @param int $count
     * @return array [material id, version ids by number]
     */
    private function material_with_versions(int $cmid, int $count): array {
        $repository = new material_repository();
        $id = $repository->register(2, 10, $cmid, material_type::PDF, 'Monotonic', material_availability::AVAILABLE, 1000);
        $versions = [];
        for ($number = 1; $number <= $count; $number++) {
            $versions[$number] = $repository->add_version($id, "hash-{$number}", material_availability::AVAILABLE);
            $repository->complete_summary($versions[$number], "resumo {$number}");
        }
        return [$id, $versions];
    }

    /**
     * What the database holds for the material, to prove that a refusal wrote nothing.
     *
     * @param int $materialid
     * @return array
     */
    private function state_of(int $materialid): array {
        global $DB;
        return [
            (array) $DB->get_record('local_assistente_material', ['id' => $materialid]),
            array_map(fn($v) => (array) $v, $DB->get_records('local_assistente_material_version', ['material_id' => $materialid], 'id ASC')),
        ];
    }

    /**
     * The pointer of the material, as a version number (null when null).
     *
     * @param int $materialid
     * @return int|null
     */
    private function pointer_number(int $materialid): ?int {
        global $DB;
        $pointer = $DB->get_field('local_assistente_material', 'current_version', ['id' => $materialid]);
        return $pointer === null ? null : (int) $DB->get_field('local_assistente_material_version', 'version', ['id' => $pointer], MUST_EXIST);
    }

    /**
     * The spec of a worker that tries the switch for the given versions.
     *
     * @param int $materialid
     * @param int[] $versionids
     * @param array $extra
     * @return array
     */
    private function worker(int $materialid, array $versionids, array $extra = []): array {
        return $extra + ['mode' => 'switch_ops', 'material_id' => $materialid, 'versions' => $versionids, 'lock_timeout' => 60];
    }

    /**
     * The only outcome that is not a rule refusal or an acceptance is a failure of the test: nothing technical may happen.
     *
     * @param array[] $answers
     * @return array[] the outcomes, with the label
     */
    private function outcomes(array $answers): array {
        $all = [];
        foreach ($answers as $label => $answer) {
            $this->assertNull($answer['error'], "Error of the worker '{$label}': " . var_export($answer['error'], true));
            foreach ($answer['results'] as $outcome) {
                $this->assertTrue(
                    !empty($outcome['ok']) || !empty($outcome['refused']),
                    "Technical failure in '{$label}': " . json_encode($outcome)
                );
                $all[] = ['label' => $label] + $outcome;
            }
        }
        return $all;
    }

    /**
     * Run two workers in a fixed order of arrival: the first runs alone and finishes, then the second is released.
     *
     * @param int $materialid
     * @param int[] $first versions tried by the first worker
     * @param int[] $second versions tried by the second worker
     * @param string $name
     * @return array[] outcomes in the order of arrival
     */
    private function run_in_order(int $materialid, array $first, array $second, string $name): array {
        $done = $this->signal_file("{$name}_done");
        $release = $this->signal_file("{$name}_release");
        $group = $this->spawn_group([
            $this->worker($materialid, $first, ['done_file' => $done]),
            $this->worker($materialid, $second, ['wait_file' => $release]),
        ], $name);
        $this->go_group($group);
        $this->await(fn() => file_exists($done), "'{$name}': the first worker did not finish");
        file_put_contents($release, 'go');
        $answers = $this->finish_group($group, 120);
        $labels = array_keys($answers);
        $this->assertLessThan($answers[$labels[1]]['started_at'], $answers[$labels[0]]['ended_at'], 'The first worker must finish before the second begins');
        return $this->outcomes($answers);
    }

    /**
     * Run two workers that arrive together at the lock of the material: the test holds it, sees both waiting, and releases it.
     *
     * @param int $materialid
     * @param int[] $first versions tried by the first worker
     * @param int[] $second versions tried by the second worker
     * @param string $name
     * @return array[] outcomes
     */
    private function run_contended(int $materialid, array $first, array $second, string $name): array {
        $resource = unit_of_work::material_resource($materialid);
        $holder = $this->hold_resource($resource, "{$name}-holder");
        $group = $this->spawn_group([$this->worker($materialid, $first), $this->worker($materialid, $second)], $name);
        $this->go_group($group);
        foreach ($group['actors'] as $label => $actor) {
            $this->await(
                fn() => $this->waiting_row($actor['connection'], $resource) !== null,
                "'{$label}' did not wait for the lock of the material"
            );
        }
        $this->release_actor($holder);
        return $this->outcomes($this->finish_group($group, 120));
    }

    /**
     * M-12 / Q17 (1), order 1: version 3 arrives first, version 2 afterwards. Version 3 is accepted; version 2 is refused by the rule, writes
     * nothing, stays completed and historical.
     */
    #[TestDox('M-12 (Q17-1): v3 arrives before v2: v3 is current; v2 is refused, completed and historical')]
    public function test_m12_version_three_before_version_two(): void {
        [$id, $v] = $this->material_with_versions(8401, 3);

        $outcomes = $this->run_in_order($id, [$v[3]], [$v[2]], 'm12-v3-v2');

        $this->assertTrue($outcomes[0]['ok'], 'Version 3 is accepted');
        $this->assertTrue($outcomes[1]['refused'] ?? false, 'Version 2 is refused by the rule');
        $this->assertFalse($outcomes[1]['is_current']);
        $this->assertSame($v[3], $outcomes[1]['current_id']);
        $this->assertSame(3, $this->pointer_number($id));
        $this->assert_history_intact($id, $v, 3);
    }

    /**
     * M-12 / Q17 (1), order 2: version 2 arrives first, version 3 afterwards. Both are accepted, in this order; the final state is version 3.
     */
    #[TestDox('M-12 (Q17-1): v2 arrives before v3: both are accepted; v3 is current')]
    public function test_m12_version_two_before_version_three(): void {
        [$id, $v] = $this->material_with_versions(8402, 3);

        $outcomes = $this->run_in_order($id, [$v[2]], [$v[3]], 'm12-v2-v3');

        $this->assertTrue($outcomes[0]['ok'] && $outcomes[1]['ok'], 'Both are accepted: ' . json_encode($outcomes));
        $this->assertSame(3, $this->pointer_number($id));
        $this->assert_history_intact($id, $v, 3);
    }

    /**
     * M-12 / Q17 (1), contention: versions 2 and 3 reach the lock together, in whichever order the server grants it. The final state is
     * version 3 in every run; version 3 is always accepted; version 2 is accepted (it got the lock first) or refused by the rule, never
     * anything else; nothing is corrupted.
     */
    #[TestDox('M-12 (Q17-1): v2 and v3 contend for the lock: v3 is always current, whatever the order')]
    public function test_m12_versions_two_and_three_contending(): void {
        $orders = [];
        for ($run = 1; $run <= self::CONTENDED_RUNS; $run++) {
            [$id, $v] = $this->material_with_versions(8410 + $run, 3);

            $outcomes = $this->run_contended($id, [$v[2]], [$v[3]], "m12-run{$run}");

            $byversion = array_column($outcomes, null, 'version_id');
            $this->assertTrue($byversion[$v[3]]['ok'], "Run {$run}: version 3 must be accepted: " . json_encode($outcomes));
            $this->assertSame(3, $this->pointer_number($id), "Run {$run}: the final state is version 3");
            $orders[] = $byversion[$v[2]]['ok'] ? 'v2,v3' : 'v3,v2';
            $this->assert_history_intact($id, $v, 3);
        }
        $this->note('M-12: order in which the lock was obtained in ' . self::CONTENDED_RUNS . ' runs: ' . implode(' | ', $orders));
    }

    /**
     * M-13 / Q17 (2): two processes switch to the same version at the same time. The first to get the lock is accepted; the other finds that
     * the version is already current and is refused; the pointer is on the version, with nothing else changed.
     */
    #[TestDox('M-13 (Q17-2): two processes with the same version: one accepted, the other refused as already current')]
    public function test_m13_same_version_in_two_processes(): void {
        for ($run = 1; $run <= self::CONTENDED_RUNS; $run++) {
            [$id, $v] = $this->material_with_versions(8420 + $run, 2);

            $outcomes = $this->run_contended($id, [$v[2]], [$v[2]], "m13-same-run{$run}");

            $accepted = array_values(array_filter($outcomes, fn($o) => !empty($o['ok'])));
            $refused = array_values(array_filter($outcomes, fn($o) => !empty($o['refused'])));
            $this->assertCount(1, $accepted, "Run {$run}: exactly one is accepted: " . json_encode($outcomes));
            $this->assertCount(1, $refused, "Run {$run}: exactly one is refused: " . json_encode($outcomes));
            $this->assertTrue($refused[0]['is_current'], 'The refusal says that the version is already current');
            $this->assertSame(2, $this->pointer_number($id));
            $this->assert_history_intact($id, $v, 2);
        }
    }

    /**
     * M-13 / Q17 (3): selecting the version that is already current, from another process, is refused and writes nothing.
     */
    #[TestDox('M-13 (Q17-3): selecting the version that is already current is refused and writes nothing')]
    public function test_m13_selecting_the_current_version_writes_nothing(): void {
        [$id, $v] = $this->material_with_versions(8431, 2);
        (new material_repository())->switch_current($id, $v[2]);
        $before = $this->state_of($id);

        $outcomes = $this->run_in_order($id, [$v[2]], [$v[1]], 'm13-current');

        $this->assertTrue($outcomes[0]['refused'] && $outcomes[0]['is_current'], 'The current version is refused as such: ' . json_encode($outcomes[0]));
        $this->assertTrue($outcomes[1]['refused'] && !$outcomes[1]['is_current'], 'A lower number is refused: ' . json_encode($outcomes[1]));
        $this->assertSame($before, $this->state_of($id), 'The refusals wrote nothing');
    }

    /**
     * M-13 / Q17 (4): two processes dispute a null pointer, with versions 1 and 2. Whoever gets the lock first is accepted; the other
     * follows the rule: version 2 is always accepted, version 1 is accepted only if it was first. The final state is version 2.
     */
    #[TestDox('M-13 (Q17-4): two processes dispute a null pointer: the first to get the lock is accepted, the other follows the rule')]
    public function test_m13_null_pointer_disputed(): void {
        $orders = [];
        for ($run = 1; $run <= self::CONTENDED_RUNS; $run++) {
            [$id, $v] = $this->material_with_versions(8440 + $run, 2);
            $this->assertNull($this->pointer_number($id));

            $outcomes = $this->run_contended($id, [$v[1]], [$v[2]], "m13-null-run{$run}");

            $byversion = array_column($outcomes, null, 'version_id');
            $this->assertTrue($byversion[$v[2]]['ok'], "Run {$run}: version 2 is always accepted: " . json_encode($outcomes));
            $orders[] = $byversion[$v[1]]['ok'] ? 'v1,v2' : 'v2,v1';
            $this->assertSame(2, $this->pointer_number($id));
            $this->assert_history_intact($id, $v, 2);
        }
        $this->note('M-13: order in which the lock was obtained in ' . self::CONTENDED_RUNS . ' runs: ' . implode(' | ', $orders));
    }

    /**
     * After the dispute: every version is still there, completed, with its summary and its number; the pointer is on the expected one.
     *
     * @param int $materialid
     * @param int[] $versions ids by number
     * @param int $expected number of the version the pointer must be on
     */
    private function assert_history_intact(int $materialid, array $versions, int $expected): void {
        global $DB;
        $this->assertSame($versions[$expected], (int) $DB->get_field('local_assistente_material', 'current_version', ['id' => $materialid]));
        $rows = array_values($DB->get_records('local_assistente_material_version', ['material_id' => $materialid], 'version ASC'));
        $this->assertSame(range(1, count($versions)), array_map(fn($r) => (int) $r->version, $rows), 'The numbers are not repeated or lost');
        foreach ($rows as $row) {
            $this->assertSame('completed', $row->summary_status, "Version {$row->version}");
            $this->assertSame("resumo {$row->version}", $row->summary, "Summary of version {$row->version}");
            $this->assertSame($versions[(int) $row->version], (int) $row->id);
        }
    }
}
