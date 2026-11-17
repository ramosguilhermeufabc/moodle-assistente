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
use PHPUnit\Framework\Attributes\DataProvider;

require_once(__DIR__ . '/../../fixtures/lock_observer.php');

/**
 * The locks and the transaction of the unit of work, seen from other processes (CL-07: I-12, M-06, M-07, M-08, M-09, M-10, M-11).
 *
 * The lock of the database is re-entrant inside one process, so the tests of unit_of_work_test show the logic of the plugin and not the
 * exclusion of the server. Here the other side is a separate PHP process (tests/fixtures/queue_worker.php) with its own connection, and what
 * is observed is what the server says: who holds a lock (IS_USED_LOCK), who waits for it, and whether a connection still exists.
 *
 * Scope: MariaDB, the lock factory of its GET_LOCK, processes of the same machine, the database and data root of PHPUnit. Other servers, other
 * lock factories and other databases are not covered. Nothing here is a test of the site.
 *
 * A prerequisite that is missing (the signals of GET_LOCK cannot be observed) is a failure, not a skip. The only test that may be skipped is
 * M-10, and only when the server refuses KILL CONNECTION to this user: then CR-20 stays not proven.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(unit_of_work::class)]
final class unit_of_work_concurrency_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /**
     * The workers write in the database and the test reads what they wrote: nothing may be wrapped in a transaction, and the reset of
     * PHPUnit does not see what other processes write, so the tables are emptied here, before and after each test.
     */
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
        unit_of_work::reset_lock_factory();
        parent::tearDown();
    }

    /**
     * Empty the tables the tests write in.
     */
    private function clean_tables(): void {
        global $DB;
        $DB->delete_records('local_assistente_material_version');
        $DB->delete_records('local_assistente_material');
    }

    /**
     * Register a material.
     *
     * @param material_repository $repository
     * @param int $cmid
     * @return int material id
     */
    private function register_material(material_repository $repository, int $cmid): int {
        return $repository->register(2, 10, $cmid, material_type::PDF, 'Concurrency', material_availability::AVAILABLE, 1000);
    }

    /**
     * I-12 (CR-09): the lock factory that the plugin really uses in this process is the one of GET_LOCK of the database, and a lock taken
     * through it shows in the server under the key that is expected, held by the connection of this test.
     */
    public function test_the_effective_lock_factory_is_the_database_one(): void {
        $this->assert_the_factory_in_use_is_the_database_one(unit_of_work::lock_factory());
    }

    /**
     * I-12 with the official wrapper of timing: when perfdebug is high (or MDL_PERF is on) Moodle wraps the real factory in
     * timing_wrapper_lock_factory. The wrapper is accepted only then, and only as the official class; what is checked is the real factory
     * it wraps (get_real_factory()), and the lock still shows in the server under the expected key.
     */
    public function test_the_effective_lock_factory_is_recognised_behind_the_timing_wrapper(): void {
        global $CFG;
        $CFG->perfdebug = 8; // Restored by resetAfterTest().
        unit_of_work::reset_lock_factory();
        $factory = unit_of_work::lock_factory();
        $this->assertInstanceOf(
            \core\lock\timing_wrapper_lock_factory::class,
            $factory,
            'With perfdebug 8 Moodle must wrap the lock factory; it returned ' . get_class($factory)
        );
        $this->assert_the_factory_in_use_is_the_database_one($factory);
    }

    /**
     * What I-12 asserts about the factory the plugin uses: it is the one of GET_LOCK of the database (behind the official wrapper of timing
     * only when Moodle puts it there), and a lock taken through it shows in the server under the key that is expected.
     *
     * @param \core\lock\lock_factory $factory the factory in use
     */
    private function assert_the_factory_in_use_is_the_database_one(\core\lock\lock_factory $factory): void {
        global $CFG, $DB;
        $this->assertSame(
            \core\lock\mysql_lock_factory::class,
            ltrim(\core\lock\lock_config::get_lock_factory_class(), '\\'),
            'The class that Moodle chooses for the lock factory'
        );
        $real = $factory;
        if ($factory instanceof \core\lock\timing_wrapper_lock_factory) {
            // The wrapper is expected only when Moodle is told to measure time; any other wrapper, or this one without the reason, is not.
            $this->assertTrue(
                (defined('MDL_PERF') && MDL_PERF) || (!empty($CFG->perfdebug) && $CFG->perfdebug > 7),
                'The timing wrapper is in use, but neither MDL_PERF nor perfdebug > 7 asks for it'
            );
            $real = $factory->get_real_factory();
        }
        $this->assertInstanceOf(
            \core\lock\mysql_lock_factory::class,
            $real,
            'The real lock factory in use is ' . get_class($real) . ' (the one in the plugin is ' . get_class($factory) . ')'
        );
        $this->assertTrue($factory->supports_timeout(), 'The factory must support a timeout');
        $this->assertTrue($factory->supports_auto_release(), 'The factory must release the locks of a process that ends');

        $resource = unit_of_work::material_resource(912345);
        $other = unit_of_work::material_resource(912346);
        $this->assertNull($this->lock_owner($resource), 'The lock must be free before the test');

        $lock = $factory->get_lock($resource, 1);
        $this->assertNotFalse($lock, 'The lock could not be taken');
        try {
            $mine = (int) $DB->get_field_sql('SELECT CONNECTION_ID()');
            $this->assertSame($mine, $this->lock_owner($resource), 'The server must show this connection as the owner of the key of ' . $resource);
            $this->assertNull($this->lock_owner($other), 'The key of another resource must stay free');
        } finally {
            $lock->release();
        }
        $this->assertNull($this->lock_owner($resource), 'The lock must be free after the release');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function outcomes(): array {
        return ['commit' => ['commit'], 'rollback' => ['rollback']];
    }

    /**
     * M-06 (CR-11): while a unit is open in another process the lock is held by that process's connection and this process cannot take it;
     * after the commit, and after the rollback, the lock is free even though the connection that held it is still alive.
     *
     * @param string $outcome
     */
    #[DataProvider('outcomes')]
    public function test_the_lock_is_exclusive_while_held_and_free_after_commit_or_rollback(string $outcome): void {
        global $DB;
        $cmid = $outcome === 'commit' ? 7101 : 7102;
        $resource = unit_of_work::material_resource($cmid);
        $inside = $this->signal_file('inside');
        $insiderelease = $this->signal_file('inside_release');
        $ready = $this->signal_file('ready');
        $release = $this->signal_file('release');

        $worker = $this->spawn_actor([
            'mode' => 'run_unit', 'resource' => $resource, 'outcome' => $outcome, 'cmid' => $cmid, 'seconds' => 120,
            'inside_file' => $inside, 'inside_release_file' => $insiderelease, 'ready_file' => $ready, 'release_file' => $release,
        ], "unit-{$outcome}");
        $this->await(fn() => file_exists($inside), "the worker did not get inside the unit ({$outcome})");

        $this->assertSame($worker['connection'], $this->lock_owner($resource), "While the unit is open the lock must belong to the worker ({$outcome})");
        $this->assertSame(0, $DB->count_records('local_assistente_material', ['cmid' => $cmid]), 'The write of the open unit must not be visible');
        $intruder = unit_of_work::lock_factory()->get_lock($resource, 1);
        if ($intruder) {
            $intruder->release();
            $this->fail("This process took a lock that the worker holds inside its unit ({$outcome})");
        }

        file_put_contents($insiderelease, 'go');
        $this->await(fn() => file_exists($ready), "the worker did not finish its unit ({$outcome})");
        $this->assertTrue(proc_get_status($worker['process'])['running'], 'The worker must still be alive: the proof is the lock free with its connection alive');
        $this->assertNull($this->lock_owner($resource), "After the {$outcome} the lock must be free while the worker is alive");
        $lock = unit_of_work::lock_factory()->get_lock($resource, 1);
        $this->assertNotFalse($lock, "After the {$outcome} another process must take the lock");
        $lock->release();
        $this->assertSame($outcome === 'commit' ? 1 : 0, $DB->count_records('local_assistente_material', ['cmid' => $cmid]), "The write after the {$outcome}");

        file_put_contents($release, 'go');
        $answer = $this->finish_actor($worker, 60);
        $this->assertNull($answer['error'], 'Error of the worker: ' . var_export($answer['error'], true));
        $this->assertSame($outcome === 'commit' ? 'committed' : 'rolled_back', $answer['results'][0]['unit'] ?? null);
    }

    /**
     * M-07 (CR-12): with the lock held by another process, the operation fails after the timeout with the exception of the plugin, writes
     * nothing, and works once the lock is released.
     */
    public function test_the_lock_timeout_expires_while_another_process_holds_the_lock(): void {
        global $DB;
        $repository = new material_repository(new unit_of_work(1));
        $id = $this->register_material($repository, 7201);
        $resource = unit_of_work::material_resource($id);
        $holder = $this->hold_resource($resource, 'holder');
        $this->assertSame($holder['connection'], $this->lock_owner($resource), 'The holder must own the lock');

        $started = microtime(true);
        try {
            $repository->add_version($id, 'hash-m07', material_availability::AVAILABLE);
            $this->fail('The operation must not go through while another process holds the lock');
        } catch (lock_unavailable_exception $e) {
            $elapsed = microtime(true) - $started;
            $this->assertSame($resource, $e->resource(), 'The exception must name the resource');
            $this->assertGreaterThanOrEqual(0.9, $elapsed, 'The operation gave up before the timeout of 1s');
            $this->assertLessThan(15.0, $elapsed, 'The operation waited far beyond the timeout');
            $this->note(sprintf('M-07: the operation gave up after %.2fs (timeout 1s)', $elapsed));
        }
        $this->assertSame(0, $DB->count_records('local_assistente_material_version', ['material_id' => $id]), 'Nothing may be written');
        $this->assertFalse($DB->is_transaction_started(), 'No transaction may be left open');
        $this->assertSame($holder['connection'], $this->lock_owner($resource), 'The holder must still own the lock');

        $this->release_actor($holder);
        $this->await_lock_free($resource);
        $versionid = $repository->add_version($id, 'hash-m07-after', material_availability::AVAILABLE);
        $this->assertSame(1, (int) $DB->get_field('local_assistente_material_version', 'version', ['id' => $versionid]));
        $answer = $this->finish_actor($holder, 60);
        $this->assertNull($answer['error'], 'Error of the holder: ' . var_export($answer['error'], true));
    }

    /**
     * M-08 (CR-13): the lock of a process that is killed (SIGKILL) is released by the server.
     */
    public function test_the_lock_is_released_when_the_holder_is_killed(): void {
        $resource = unit_of_work::material_resource(7301);
        $holder = $this->hold_resource($resource, 'holder');
        $this->assertSame($holder['connection'], $this->lock_owner($resource), 'The holder must own the lock before the kill');

        $how = $this->kill_actor($holder);
        $this->assertTrue($how['signaled'], 'The holder must have ended by a signal: ' . json_encode($how));
        $this->assertSame(9, $how['termsig'], 'The signal must be SIGKILL: ' . json_encode($how));

        $this->await_connection_gone($holder['connection']);
        $this->await_lock_free($resource);
        $lock = unit_of_work::lock_factory()->get_lock($resource, 1);
        $this->assertNotFalse($lock, 'Another process must take the lock of the killed holder');
        $lock->release();
    }

    /**
     * M-09 (CR-18): a write that is not confirmed is rolled back when the process is killed: no partial row, the lock is free, the
     * number the dead process used is available, and the next write goes through without waiting for a row lock that would be left behind.
     */
    public function test_an_unconfirmed_write_is_rolled_back_when_the_process_is_killed(): void {
        global $DB;
        $repository = new material_repository(new unit_of_work(5));
        $id = $this->register_material($repository, 7401);
        $resource = unit_of_work::material_resource($id);
        $ready = $this->signal_file('ready');
        $release = $this->signal_file('release');
        $worker = $this->spawn_actor([
            'mode' => 'write_and_hold', 'material_id' => $id, 'hash' => 'hash-m09', 'seconds' => 120, 'lock_timeout' => 5,
            'ready_file' => $ready, 'release_file' => $release,
        ], 'writer');
        $this->await(fn() => file_exists($ready), 'the worker did not write inside its unit');
        $written = json_decode((string) file_get_contents($ready), true);
        $this->assertSame(1, $written['version'], 'The worker wrote the first version: ' . json_encode($written));
        $this->assertSame($worker['connection'], $this->lock_owner($resource), 'The worker must own the lock while its unit is open');
        $this->assertSame(0, $DB->count_records('local_assistente_material_version', ['material_id' => $id]), 'The unconfirmed write must not be visible');

        $this->kill_actor($worker);
        $this->await_connection_gone($worker['connection']);
        $this->await_lock_free($resource);

        $this->assertSame(0, $DB->count_records('local_assistente_material_version', ['material_id' => $id]), 'No row of the killed process may remain');
        $this->assertFalse($DB->record_exists('local_assistente_material_version', ['id' => $written['version_id']]), 'The row of the killed process must not exist');
        $original = (int) $DB->get_field_sql('SELECT @@SESSION.innodb_lock_wait_timeout');
        $DB->execute('SET SESSION innodb_lock_wait_timeout = 5');
        try {
            $versionid = $repository->add_version($id, 'hash-m09-after', material_availability::AVAILABLE);
        } finally {
            $DB->execute('SET SESSION innodb_lock_wait_timeout = ' . $original);
        }
        $this->assertSame(1, (int) $DB->get_field('local_assistente_material_version', 'version', ['id' => $versionid]), 'The number of the dead process must be free');
        $this->assertFalse($DB->is_transaction_started());
    }

    /**
     * M-10 (CR-20), only when the server lets this user end the connection of another process: the server ends the connection of a worker
     * that has a unit open; the worker fails with an exception of the database layer, nothing is left behind, the lock is free and the same
     * work goes through afterwards. When the server refuses KILL, the test is skipped and CR-20 stays not proven.
     */
    public function test_a_connection_killed_by_the_server_fails_cleanly_and_the_work_can_be_repeated(): void {
        global $DB;
        $repository = new material_repository(new unit_of_work(5));
        $id = $this->register_material($repository, 7501);
        $resource = unit_of_work::material_resource($id);
        $ready = $this->signal_file('ready');
        $release = $this->signal_file('release');
        $worker = $this->spawn_actor([
            'mode' => 'write_and_hold', 'material_id' => $id, 'hash' => 'hash-m10', 'seconds' => 120, 'lock_timeout' => 5,
            'after' => 'touch_db', 'ready_file' => $ready, 'release_file' => $release,
        ], 'writer');
        $this->await(fn() => file_exists($ready), 'the worker did not write inside its unit');
        $this->assertSame($worker['connection'], $this->lock_owner($resource), 'The worker must own the lock while its unit is open');

        try {
            $this->kill_connection($worker['connection']);
        } catch (\dml_exception $e) {
            if ($this->kill_was_denied($e)) {
                $this->markTestSkipped('M-10 NÃO EXECUTADO: o servidor recusou KILL CONNECTION a este usuário (' . $e->getMessage() . '). CR-20 permanece não comprovado.');
            }
            throw $e;
        }
        $this->await_connection_gone($worker['connection']);
        $this->await_lock_free($resource);

        file_put_contents($release, 'go');
        $answer = $this->finish_actor($worker, 60);
        $this->assertNotNull($answer['error'], 'The worker must fail: its connection was ended by the server');
        $class = strstr((string) $answer['error'], ':', true);
        $this->note('M-10: the worker failed with ' . substr((string) $answer['error'], 0, 160));
        $this->assertTrue(is_a($class, \dml_exception::class, true), 'The failure must come from the database layer, and it was: ' . $answer['error']);

        $this->assertSame(0, $DB->count_records('local_assistente_material_version', ['material_id' => $id]), 'No row of the worker may remain');
        $versionid = $repository->add_version($id, 'hash-m10-after', material_availability::AVAILABLE);
        $this->assertSame(1, (int) $DB->get_field('local_assistente_material_version', 'version', ['id' => $versionid]), 'The work must go through again with the same number');
    }

    /**
     * M-11 (CR-16): while a lock is held, a worker on the same resource waits (the server shows it waiting) and workers on other resources
     * (another material, a report) finish; the one that waited finishes only after the release.
     */
    public function test_independent_resources_progress_while_the_same_resource_waits(): void {
        global $DB;
        $repository = new material_repository(new unit_of_work(5));
        $a = $this->register_material($repository, 7601);
        $b = $this->register_material($repository, 7602);
        $resourcea = unit_of_work::material_resource($a);
        $holder = $this->hold_resource($resourcea, 'holder-a');

        $doneb = $this->signal_file('done_b');
        $donea = $this->signal_file('done_a');
        $readyreport = $this->signal_file('ready_report');
        $releasereport = $this->signal_file('release_report');
        $workerb = $this->spawn_actor([
            'mode' => 'material_ops', 'op' => 'add_version', 'material_id' => $b, 'rounds' => 1, 'prefix' => 'b', 'done_file' => $doneb,
        ], 'other-material');
        $workerreport = $this->spawn_actor([
            'mode' => 'run_unit', 'resource' => unit_of_work::report_resource(7603), 'outcome' => 'commit', 'seconds' => 120,
            'ready_file' => $readyreport, 'release_file' => $releasereport,
        ], 'other-report');
        $workera = $this->spawn_actor([
            'mode' => 'material_ops', 'op' => 'add_version', 'material_id' => $a, 'rounds' => 1, 'prefix' => 'a', 'done_file' => $donea,
            'lock_timeout' => 60,
        ], 'same-resource');

        $this->await(fn() => file_exists($doneb) && file_exists($readyreport), 'the workers on other resources did not progress');
        $this->await(fn() => $this->waiting_row($workera['connection'], $resourcea) !== null, 'the worker on the same resource is not waiting for the lock');
        $this->assertFileDoesNotExist($donea, 'The worker on the same resource must not finish while the lock is held');
        $this->assertSame($holder['connection'], $this->lock_owner($resourcea), 'The holder must still own the lock');
        $this->assertSame(1, $DB->count_records('local_assistente_material_version', ['material_id' => $b]), 'The other material must have its version');
        $this->assertSame(0, $DB->count_records('local_assistente_material_version', ['material_id' => $a]), 'The held material must have none yet');

        $releasedat = microtime(true);
        $this->release_actor($holder);
        $this->await(fn() => file_exists($donea), 'the worker on the same resource did not finish after the release');
        $answera = json_decode((string) file_get_contents($donea), true);
        $this->assertTrue($answera['results'][0]['ok'] ?? false, 'The waiting worker must succeed after the release: ' . json_encode($answera));
        $this->assertGreaterThan($releasedat, $answera['ended_at'], 'The waiting worker must finish only after the release');
        $this->assertSame(1, $DB->count_records('local_assistente_material_version', ['material_id' => $a]));

        file_put_contents($releasereport, 'go');
        foreach ([$holder, $workerb, $workerreport, $workera] as $actor) {
            $answer = $this->finish_actor($actor, 60);
            $this->assertNull($answer['error'], "Error of '{$actor['label']}': " . var_export($answer['error'], true));
        }
    }
}
