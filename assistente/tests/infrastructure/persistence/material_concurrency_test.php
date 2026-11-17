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

require_once(__DIR__ . '/../../fixtures/lock_observer.php');

/**
 * The versions of one material written by several processes at the same time (CL-07: M-01, M-02, M-03).
 *
 * Four PHP processes, each with its own connection, begin together (ready/go) and write to the same material. What is checked is what is in
 * the database afterwards, and that the processes really overlapped in time: a result obtained by workers that ran one after the other
 * would prove nothing about the lock.
 *
 * M-01 and M-02 exercise the protocol of the material (lock and transaction, DEC-61). Since T144 the pointer does not go back (DEC-62):
 * in M-02 a worker whose switch is refused because a greater version is already current is a valid outcome, and the pointer must end on
 * the greatest version.
 * M-03 is a control: the same writing with no lock, where only the unique index protects, to show that the processes do race.
 *
 * Scope: MariaDB, GET_LOCK, processes of the same machine, the database of PHPUnit. A prerequisite that is missing is a failure.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(material_repository::class)]
final class material_concurrency_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var int processes that write at the same time */
    private const WORKERS = 4;

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
     * Register a material.
     *
     * @param int $cmid
     * @return int material id
     */
    private function register_material(int $cmid): int {
        return (new material_repository())->register(2, 10, $cmid, material_type::PDF, 'Concurrency', material_availability::AVAILABLE, 1000);
    }

    /**
     * Run workers that begin together and give back their answers.
     *
     * @param string $op operation of the worker
     * @param int $materialid
     * @param int $rounds rounds of each worker
     * @return array[] answers by label
     */
    private function run_group(string $op, int $materialid, int $rounds): array {
        $specs = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $specs[] = [
                'mode' => 'material_ops', 'op' => $op, 'material_id' => $materialid, 'rounds' => $rounds, 'prefix' => "w{$i}",
                'lock_timeout' => 60,
            ];
        }
        $group = $this->spawn_group($specs, "material-{$op}");
        $this->go_group($group);
        $answers = $this->finish_group($group, 180);
        foreach ($answers as $label => $answer) {
            $this->assertNull($answer['error'], "Error of the worker '{$label}': " . var_export($answer['error'], true));
        }
        return $answers;
    }

    /**
     * The workers must have worked at the same time: the latest start comes before the earliest end.
     *
     * @param array[] $answers
     */
    private function assert_overlap(array $answers): void {
        $starts = array_column($answers, 'started_at');
        $ends = array_column($answers, 'ended_at');
        $this->assertCount(self::WORKERS, $starts);
        $this->assertLessThan(
            min($ends),
            max($starts),
            'The workers did not overlap in time, so this run proves nothing about concurrency. Intervals: ' . json_encode(
                array_map(fn($a) => [round($a['started_at'], 3), round($a['ended_at'], 3)], $answers)
            )
        );
    }

    /**
     * Rounds of all the workers, with the label of the worker.
     *
     * @param array[] $answers
     * @return array[] label and round each
     */
    private function rounds(array $answers): array {
        $all = [];
        foreach ($answers as $label => $answer) {
            foreach ($answer['results'] as $round) {
                $all[] = ['label' => $label] + $round;
            }
        }
        return $all;
    }

    /**
     * Every round must have succeeded; the message names the worker, the round and the failure.
     *
     * @param array[] $answers
     */
    private function assert_all_ok(array $answers): void {
        $failures = array_filter($this->rounds($answers), fn($r) => empty($r['ok']));
        $this->assertSame(
            [],
            array_values(array_map(fn($r) => "{$r['label']} round {$r['round']}: {$r['class']}: {$r['message']}", $failures)),
            'Rounds that failed'
        );
    }

    /**
     * The invariant of the pointer: it is null, or it points to a completed version of the same material.
     *
     * @param int $materialid
     */
    private function assert_pointer_is_valid(int $materialid): void {
        global $DB;
        $pointer = $DB->get_field('local_assistente_material', 'current_version', ['id' => $materialid]);
        if ($pointer === null || $pointer === false) {
            return;
        }
        $version = $DB->get_record('local_assistente_material_version', ['id' => $pointer]);
        $this->assertNotFalse($version, "current_version {$pointer} points to no version");
        $this->assertSame($materialid, (int) $version->material_id, 'current_version points to a version of another material');
        $this->assertSame('completed', $version->summary_status, 'current_version points to a version that is not completed');
    }

    /**
     * M-01 (CR-14): four processes adding versions to the same material at the same time get distinct and consecutive numbers.
     */
    public function test_four_processes_adding_versions_get_consecutive_numbers(): void {
        global $DB;
        $id = $this->register_material(8101);

        $answers = $this->run_group('add_version', $id, 8);

        $this->assert_overlap($answers);
        $this->assert_all_ok($answers);
        $versions = array_values($DB->get_records('local_assistente_material_version', ['material_id' => $id], 'version ASC'));
        $this->assertSame(range(1, self::WORKERS * 8), array_map(fn($v) => (int) $v->version, $versions), 'The numbers of the versions');
        $this->assertCount(self::WORKERS * 8, array_unique(array_map(fn($v) => $v->content_hash, $versions)), 'Each round wrote its own version');
        $this->assertSame(['pending'], array_values(array_unique(array_map(fn($v) => $v->summary_status, $versions))), 'A new version is pending');
        $this->assertNull($DB->get_field('local_assistente_material', 'current_version', ['id' => $id]), 'Adding versions does not move the pointer');
    }

    /**
     * M-02 (CR-14): four processes running the whole cycle (create, complete the summary, switch) on the same material leave the numbers
     * consecutive, every version completed with its own summary, and a valid pointer.
     */
    public function test_four_processes_running_the_full_cycle_leave_a_valid_pointer(): void {
        global $DB;
        $id = $this->register_material(8201);

        $answers = $this->run_group('cycle', $id, 8);

        $this->assert_overlap($answers);
        $this->assert_all_ok($answers);
        $versions = array_values($DB->get_records('local_assistente_material_version', ['material_id' => $id], 'version ASC'));
        $this->assertSame(range(1, self::WORKERS * 8), array_map(fn($v) => (int) $v->version, $versions), 'The numbers of the versions');
        foreach ($versions as $version) {
            $this->assertSame('completed', $version->summary_status, "Version {$version->version}");
            $this->assertSame('resumo ' . $version->content_hash, $version->summary, "Summary of version {$version->version}");
        }
        $this->assertNotNull($DB->get_field('local_assistente_material', 'current_version', ['id' => $id]), 'The material must have a current version');
        $this->assert_pointer_is_valid($id);
        $pointer = $DB->get_record('local_assistente_material_version', ['id' => $DB->get_field('local_assistente_material', 'current_version', ['id' => $id])]);
        $this->assertSame(count($versions), (int) $pointer->version, 'The pointer never goes back: it ends on the greatest version (DEC-62)');
        $refused = count(array_filter($this->rounds($answers), fn($r) => !empty($r['switch_refused'])));
        $this->note("M-02: the pointer ended on version {$pointer->version} of " . count($versions) . "; switches refused by the rule: {$refused}");
    }

    /**
     * M-03 (CR-05, control): the same writing with no lock and no transaction, made deterministic by a barrier. In the first round every
     * worker reads the maximum version and calculates its number, and then waits, before inserting, for the test to release the barrier;
     * the test releases it only when every worker has said that it arrived. So all of them hold the same number before any insert, and the
     * unique index of (material, version) must accept one of them and refuse the others. It does not depend on the scheduling of the
     * processes. The later rounds run without the barrier, as before. It shows that the concurrency of M-01 is real; it is not a guarantee
     * of the lock.
     */
    public function test_without_the_lock_the_unique_index_refuses_duplicate_versions(): void {
        global $DB;
        $id = $this->register_material(8301);
        $release = $this->signal_file('barrier_release');
        $arrive = [];
        $specs = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $arrive["nolock-{$i}"] = $this->signal_file("barrier_arrive_{$i}");
            $specs[] = [
                'mode' => 'material_ops', 'op' => 'add_version_nolock', 'material_id' => $id, 'rounds' => 16, 'prefix' => "w{$i}",
                'lock_timeout' => 60, 'barrier_arrive_file' => $arrive["nolock-{$i}"], 'barrier_release_file' => $release,
                'barrier_timeout' => 90,
            ];
        }
        $group = $this->spawn_group($specs, 'nolock');
        $this->go_group($group);
        $reads = [];
        try {
            $this->await_group_barrier($group, $arrive, 'M-03: every worker has read the maximum', 60);
            foreach ($arrive as $label => $file) {
                $reads[$label] = json_decode((string) file_get_contents($file), true)['next'] ?? null;
            }
        } finally {
            // Released also when something failed, so that no worker stays waiting.
            file_put_contents($release, 'go');
        }
        $answers = $this->finish_group($group, 180);
        foreach ($answers as $label => $answer) {
            $this->assertNull($answer['error'], "Error of the worker '{$label}': " . var_export($answer['error'], true));
        }

        $this->assertSame(
            array_fill_keys(array_keys($arrive), 1),
            $reads,
            'Before the barrier was released every worker must have calculated version 1 (the same state was read by all)'
        );
        $this->assert_overlap($answers);
        $rounds = $this->rounds($answers);
        $first = array_values(array_filter($rounds, fn($r) => $r['round'] === 1));
        $accepted = array_filter($rounds, fn($r) => !empty($r['ok']));
        $refused = array_filter($rounds, fn($r) => empty($r['ok']));
        $firstaccepted = array_filter($first, fn($r) => !empty($r['ok']));
        $firstrefused = array_filter($first, fn($r) => empty($r['ok']));

        // The deterministic part: four inserts of version 1, one accepted and the others refused as duplicates.
        $this->assertCount(self::WORKERS, $first, 'One first round per worker');
        $this->assertCount(1, $firstaccepted, 'Of four inserts of the same version, the index must accept exactly one: ' . json_encode($first));
        $this->assertCount(self::WORKERS - 1, $firstrefused, 'The others must be refused: ' . json_encode($first));
        foreach ($firstrefused as $round) {
            $this->assertSame(duplicate_record_exception::class, $round['class'], "The refusal of {$round['label']} must be the duplicate of the index: {$round['message']}");
        }

        // What follows, without the barrier, may vary in how many are refused, never in what is refused or in what ends up in the table.
        $this->assertCount(self::WORKERS * 16, $rounds);
        $this->assertSame(
            [],
            array_values(array_unique(array_filter(
                array_map(fn($r) => $r['class'], $refused),
                fn($class) => $class !== duplicate_record_exception::class
            ))),
            'What the index refused must be only duplicates; any other failure is a different problem'
        );
        $numbers = array_map(fn($v) => (int) $v->version, array_values($DB->get_records('local_assistente_material_version', ['material_id' => $id], 'version ASC')));
        $this->assertCount(count($accepted), $numbers, 'The versions in the table are those that were accepted');
        $this->assertSame(array_values(array_unique($numbers)), $numbers, 'No repeated number may be in the table');
        $this->assertSame(range(1, count($numbers)), $numbers, 'The accepted numbers are consecutive (a number is the maximum read plus one)');
        $this->note('M-03: ' . count($accepted) . ' accepted and ' . count($refused) . ' refused by the unique index, of ' . count($rounds) . '; first round: 1 accepted, ' . count($firstrefused) . ' refused');
    }
}
