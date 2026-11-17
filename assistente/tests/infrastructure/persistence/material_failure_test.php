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
use local_assistente\domain\material\summary_status;
use local_assistente\domain\material_type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

require_once(__DIR__ . '/../../fixtures/lock_observer.php');

/**
 * The three steps of the persistence of a material version, each failing for real in the database (CL-07: I-03, I-04, I-05; I-09 in all).
 *
 * The failure is a trigger of the test database that raises `SIGNAL SQLSTATE '45000'` with its own marker, created immediately before the
 * operation and removed right after it (always in `finally`). The trigger is a single statement with no `;`, so it goes through
 * `$DB->execute()` without bypassing the DML API of Moodle. It cannot be conditioned, so the window of the trigger is the control: the state
 * is prepared before it, and the operation under test is the only write inside it.
 *
 * What proves the failure is the persisted state, not the exception alone: the rows of both tables are compared with a snapshot taken before
 * (every column, ordered by id), the transaction is over, the lock is free in the server (`IS_USED_LOCK`, not only in the map of the factory),
 * no trigger is left, and the same operation works once the trigger is gone. The exception only has to carry the marker and be a write
 * failure, never a duplicate. The number `version` is checked, never the `id`: the AUTO_INCREMENT of the engine may be consumed by a
 * failed insert.
 *
 * I-09 (the pointer is null, or points to a completed version of the same material) is checked before the failure, after it and after
 * the control in each scenario. It is not the monotonicity of DEC-62 (T144). Recovery by the service is not tested here (I-06, I-13, I-15).
 *
 * A missing prerequisite (observation of the locks, creation of the trigger) is a failure, never a skip.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(material_repository::class)]
final class material_failure_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var string start of the name of every trigger of this class, and only of them: triggers of other tests are not touched */
    private const TRIGGER_PREFIX = 'cl07_mf_';

    /** @var int seconds to wait for the metadata lock of a CREATE or DROP TRIGGER before failing */
    private const DDL_WAIT_SECONDS = 10;

    /** @var int|null lock_wait_timeout of the session before the test */
    private ?int $previouswait = null;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback (and DDL commits implicitly: there must be no outer
     * transaction). Before anything else, any trigger of an earlier run that was killed is removed.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        unit_of_work::reset_lock_factory();
        $this->assertFalse($DB->is_transaction_started(), 'There must be no transaction before the DDL of the triggers');
        $this->previouswait = (int) $DB->get_field_sql('SELECT @@session.lock_wait_timeout');
        $DB->execute('SET SESSION lock_wait_timeout = ' . self::DDL_WAIT_SECONDS);
        $this->drop_leftover_triggers();
        $this->clean_tables();
        $this->assert_observable_locks();
    }

    protected function tearDown(): void {
        global $DB;
        try {
            $this->drop_leftover_triggers();
        } finally {
            try {
                $this->clean_tables();
                $this->end_actors();
            } finally {
                if ($this->previouswait !== null) {
                    $DB->execute('SET SESSION lock_wait_timeout = ' . $this->previouswait);
                }
                unit_of_work::reset_lock_factory();
                parent::tearDown();
            }
        }
    }

    /**
     * The workers' writes are not seen by the reset of PHPUnit and the triggers block writes: empty the tables here, before and after.
     */
    private function clean_tables(): void {
        global $DB;
        $DB->delete_records('local_assistente_material_version');
        $DB->delete_records('local_assistente_material');
    }

    /**
     * Names of the triggers of this class that exist in the test database.
     *
     * @return string[]
     */
    private function leftover_triggers(): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT TRIGGER_NAME AS id FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND LEFT(TRIGGER_NAME, ' . strlen(self::TRIGGER_PREFIX) . ') = ?',
            [self::TRIGGER_PREFIX]
        );
        return array_map('strval', array_keys($rows));
    }

    /**
     * Remove every trigger of this class that is left in the database.
     */
    private function drop_leftover_triggers(): void {
        foreach ($this->leftover_triggers() as $name) {
            $this->drop_failure_trigger($name);
        }
    }

    /**
     * Create the trigger that makes the next write of a table fail, with a marker of its own.
     *
     * One statement, no `;`, no parameters: it goes through `$DB->execute()`. Any error of the creation fails the test.
     *
     * @param string $name name of the trigger, starting with TRIGGER_PREFIX
     * @param string $event INSERT or UPDATE
     * @param string $table logical name of the table
     * @param string $marker text of the failure; none of: duplicate, unique constraint, a colon, a question mark, a dollar sign
     */
    private function create_failure_trigger(string $name, string $event, string $table, string $marker): void {
        global $DB;
        $this->assertMatchesRegularExpression('/^' . self::TRIGGER_PREFIX . '[a-z0-9_]+$/', $name);
        $this->assertDoesNotMatchRegularExpression('/duplicate|unique constraint|[:?$;]/i', $marker, 'The marker would be mistaken for something else');
        $this->assertFalse($DB->is_transaction_started(), 'DDL commits implicitly: no transaction may be open');
        $sql = "CREATE TRIGGER {$name} BEFORE {$event} ON " . $DB->get_prefix() . $table
            . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$marker}'";
        try {
            $DB->execute($sql);
        } catch (\Throwable $e) {
            $this->fail('PRÉ-REQUISITO AUSENTE: the trigger of the test could not be created (' . get_class($e) . '): ' . $e->getMessage());
        }
    }

    /**
     * Remove a trigger.
     *
     * @param string $name
     */
    private function drop_failure_trigger(string $name): void {
        global $DB;
        $this->assertMatchesRegularExpression('/^' . self::TRIGGER_PREFIX . '[a-z0-9_]+$/', $name);
        $DB->execute("DROP TRIGGER IF EXISTS {$name}");
    }

    /**
     * Every row of both tables, every column, ordered by id.
     *
     * @return array{material: array, versions: array}
     */
    private function snapshot(): array {
        global $DB;
        return [
            'material' => $DB->get_records('local_assistente_material', [], 'id ASC'),
            'versions' => $DB->get_records('local_assistente_material_version', [], 'id ASC'),
        ];
    }

    /**
     * The state is exactly the one of the snapshot: material and versions, compared separately.
     *
     * @param array $before
     * @param string $what
     */
    private function assert_same_state(array $before, string $what): void {
        $after = $this->snapshot();
        $this->assertCount(count($before['material']), $after['material'], "{$what}: rows of the material");
        $this->assertCount(count($before['versions']), $after['versions'], "{$what}: rows of the versions");
        $this->assertEquals($before['material'], $after['material'], "{$what}: the material row changed");
        $this->assertEquals($before['versions'], $after['versions'], "{$what}: a version row changed");
    }

    /**
     * I-09: the pointer is null, or points to a completed version of the same material. Read from the persisted state.
     *
     * @param int $materialid
     * @param string $when
     */
    private function assert_pointer_invariant(int $materialid, string $when): void {
        global $DB;
        $material = $DB->get_record('local_assistente_material', ['id' => $materialid]);
        $this->assertNotFalse($material, "{$when}: material {$materialid} does not exist");
        $pointer = $material->current_version;
        if ($pointer === null) {
            return;
        }
        $version = $DB->get_record('local_assistente_material_version', ['id' => $pointer]);
        $this->assertNotFalse($version, "{$when}: current_version {$pointer} points to no version");
        $this->assertSame($materialid, (int) $version->material_id, "{$when}: current_version points to a version of another material");
        $this->assertSame('completed', $version->summary_status, "{$when}: current_version points to a version that is not completed");
    }

    /**
     * The failure came from the database write that the trigger broke: a write exception that carries the marker, and not a duplicate.
     *
     * @param \Throwable|null $caught
     * @param string $marker
     */
    private function assert_injected_failure(?\Throwable $caught, string $marker): void {
        $this->assertNotNull($caught, 'The operation must fail while the trigger exists');
        $this->assertNotInstanceOf(duplicate_record_exception::class, $caught, 'The failure must not be taken for a duplicate');
        $this->assertInstanceOf(\dml_write_exception::class, $caught, 'Class of the failure: ' . get_class($caught) . ': ' . $caught->getMessage());
        $this->assertStringContainsString(
            $marker,
            (string) ($caught->error ?? ''),
            'The marker of the injected failure is not in the error: ' . ($caught->error ?? '') . ' / ' . ($caught->debuginfo ?? '')
        );
    }

    /**
     * Nothing is left open: no transaction (Moodle and, when the server tells, the server), the lock free in the server, and no trigger.
     *
     * @param string $resource lock resource of the material
     * @param string $when
     */
    private function assert_nothing_left_open(string $resource, string $when): void {
        global $DB;
        $this->assertFalse($DB->is_transaction_started(), "{$when}: a transaction was left open");
        try {
            $server = $DB->get_field_sql('SELECT @@in_transaction');
            $this->assertSame(0, (int) $server, "{$when}: the server reports a transaction in progress");
        } catch (\dml_exception $e) {
            $this->note("{$when}: @@in_transaction is not available in this server; only is_transaction_started() was checked");
        }
        $this->assertNull($this->lock_owner($resource), "{$when}: the lock of {$resource} is still held in the server");
        $this->assertSame([], $this->leftover_triggers(), "{$when}: a trigger of the test was left in the database");
    }

    /**
     * Register a material.
     *
     * @param material_repository $repository
     * @param int $cmid
     * @return int material id
     */
    private function register_material(material_repository $repository, int $cmid): int {
        return $repository->register(2, 10, $cmid, material_type::PDF, 'Failure', material_availability::AVAILABLE, 1000);
    }

    /**
     * A completed and current version, made with the three steps.
     *
     * @param material_repository $repository
     * @param int $materialid
     * @param string $hash
     * @return int version id
     */
    private function completed_current(material_repository $repository, int $materialid, string $hash): int {
        $id = $repository->add_version($materialid, $hash, material_availability::AVAILABLE);
        $repository->complete_summary($id, 'summary of ' . $hash);
        $repository->switch_current($materialid, $id);
        return $id;
    }

    /**
     * I-03 (CR-07, CR-19): the update of the pointer fails in the database. The previous version stays current, the target stays completed
     * (not current: the state of DEC-61), and the same switch works when the failure is gone.
     */
    public function test_a_failure_in_the_switch_keeps_the_previous_version_current(): void {
        $repository = new material_repository();
        $id = $this->register_material($repository, 7401);
        $resource = unit_of_work::material_resource($id);
        $first = $this->completed_current($repository, $id, 'hash-i03-1');
        $second = $repository->add_version($id, 'hash-i03-2', material_availability::AVAILABLE);
        $repository->complete_summary($second, 'summary of hash-i03-2');
        $this->assertSame($first, (int) $repository->find($id)->current_version);
        $this->assertSame('completed', $repository->find_version_by_id($second)->summary_status, 'The target is completed and not current');
        $this->assert_pointer_invariant($id, 'I-03 before');
        $this->assertNull($this->lock_owner($resource), 'I-03 before: the lock must be free');
        $before = $this->snapshot();

        $marker = 'cl07 i03 falha injetada na troca';
        $caught = null;
        $this->create_failure_trigger('cl07_mf_i03_switch', 'UPDATE', 'local_assistente_material', $marker);
        try {
            $repository->switch_current($id, $second);
        } catch (\Throwable $e) {
            $caught = $e;
        } finally {
            $this->drop_failure_trigger('cl07_mf_i03_switch');
        }

        $this->assert_injected_failure($caught, $marker);
        $this->assert_same_state($before, 'I-03 after the failure');
        $this->assertSame($first, (int) $repository->find($id)->current_version, 'The previous version must stay current');
        $this->assertSame('completed', $repository->find_version_by_id($second)->summary_status, 'The target must stay completed');
        $this->assert_pointer_invariant($id, 'I-03 after the failure');
        $this->assert_nothing_left_open($resource, 'I-03 after the failure');

        $repository->switch_current($id, $second);
        $this->assertSame($second, (int) $repository->find($id)->current_version, 'Without the failure the same switch must work');
        $this->assertSame('completed', $repository->find_version_by_id($second)->summary_status);
        $this->assert_pointer_invariant($id, 'I-03 after the control');
        $this->assert_nothing_left_open($resource, 'I-03 after the control');
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function with_or_without_a_previous_version(): array {
        return ['first version of the material' => [false], 'next version of the material' => [true]];
    }

    /**
     * I-04 (CR-07): the insert of the version fails in the database. No row is written and the number is not consumed: the next attempt gets
     * the number the failed one would have had. The number is checked, not the id (the engine may consume an AUTO_INCREMENT value).
     *
     * @param bool $previous whether the material already has a completed and current version
     */
    #[DataProvider('with_or_without_a_previous_version')]
    public function test_a_failure_in_the_insert_of_the_version_does_not_consume_the_number(bool $previous): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->register_material($repository, $previous ? 7403 : 7402);
        $resource = unit_of_work::material_resource($id);
        $first = $previous ? $this->completed_current($repository, $id, 'hash-i04-1') : null;
        $expected = (int) $DB->get_field_sql('SELECT MAX(version) FROM {local_assistente_material_version} WHERE material_id = ?', [$id]) + 1;
        $this->assertSame($previous ? 2 : 1, $expected);
        $this->assert_pointer_invariant($id, 'I-04 before');
        $this->assertNull($this->lock_owner($resource), 'I-04 before: the lock must be free');
        $before = $this->snapshot();

        $marker = 'cl07 i04 falha injetada na insercao';
        $caught = null;
        $this->create_failure_trigger('cl07_mf_i04_insert', 'INSERT', 'local_assistente_material_version', $marker);
        try {
            $repository->add_version($id, 'hash-i04-failed', material_availability::AVAILABLE);
        } catch (\Throwable $e) {
            $caught = $e;
        } finally {
            $this->drop_failure_trigger('cl07_mf_i04_insert');
        }

        $this->assert_injected_failure($caught, $marker);
        $this->assert_same_state($before, 'I-04 after the failure');
        $this->assertSame(0, $DB->count_records('local_assistente_material_version', ['content_hash' => 'hash-i04-failed']), 'No row of the failed attempt');
        $pointer = $repository->find($id)->current_version;
        $this->assertSame($first, $pointer === null ? null : (int) $pointer, 'The pointer must not move');
        $this->assert_pointer_invariant($id, 'I-04 after the failure');
        $this->assert_nothing_left_open($resource, 'I-04 after the failure');

        $retried = $repository->add_version($id, 'hash-i04-retry', material_availability::AVAILABLE);
        $this->assertSame($expected, (int) $DB->get_field('local_assistente_material_version', 'version', ['id' => $retried]), 'The next attempt must get the number of the failed one');
        $this->assertSame(range(1, $expected), array_map(fn($v) => (int) $v->version, array_values($repository->find_versions($id))), 'Numbers must be contiguous');
        $this->assert_pointer_invariant($id, 'I-04 after the control');
        $this->assert_nothing_left_open($resource, 'I-04 after the control');
    }

    /**
     * I-05 (CR-07): the update that makes the version completed fails in the database, after the transition has been accepted by the
     * repository. The version stays pending with no summary, and the previous version stays current.
     */
    public function test_a_failure_when_the_version_becomes_completed_keeps_it_pending(): void {
        $repository = new material_repository();
        $id = $this->register_material($repository, 7404);
        $resource = unit_of_work::material_resource($id);
        $first = $this->completed_current($repository, $id, 'hash-i05-1');
        $second = $repository->add_version($id, 'hash-i05-2', material_availability::AVAILABLE);
        $pending = $repository->find_version_by_id($second);
        $this->assertSame('pending', $pending->summary_status);
        $this->assertNull($pending->summary);
        $this->assertTrue(
            summary_status::from($pending->summary_status)->can_become(summary_status::COMPLETED),
            'The transition is valid: the only thing that can fail is the write'
        );
        $this->assert_pointer_invariant($id, 'I-05 before');
        $this->assertNull($this->lock_owner($resource), 'I-05 before: the lock must be free');
        $before = $this->snapshot();

        $marker = 'cl07 i05 falha injetada na conclusao';
        $caught = null;
        $this->create_failure_trigger('cl07_mf_i05_complete', 'UPDATE', 'local_assistente_material_version', $marker);
        try {
            $repository->complete_summary($second, 'summary of hash-i05-2');
        } catch (\Throwable $e) {
            $caught = $e;
        } finally {
            $this->drop_failure_trigger('cl07_mf_i05_complete');
        }

        $this->assert_injected_failure($caught, $marker);
        $this->assert_same_state($before, 'I-05 after the failure');
        $version = $repository->find_version_by_id($second);
        $this->assertSame('pending', $version->summary_status, 'The version must stay pending');
        $this->assertNull($version->summary, 'No summary may be left');
        $this->assertSame($first, (int) $repository->find($id)->current_version, 'The previous version must stay current');
        $this->assert_pointer_invariant($id, 'I-05 after the failure');
        $this->assert_nothing_left_open($resource, 'I-05 after the failure');

        $repository->complete_summary($second, 'summary of hash-i05-2');
        $done = $repository->find_version_by_id($second);
        $this->assertSame('completed', $done->summary_status, 'Without the failure the same call must work');
        $this->assertSame('summary of hash-i05-2', $done->summary);
        $this->assertSame($first, (int) $repository->find($id)->current_version, 'Completing does not move the pointer');
        $this->assert_pointer_invariant($id, 'I-05 after the control');
        $this->assert_nothing_left_open($resource, 'I-05 after the control');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function broken_pointers(): array {
        return ['a pending version of the same material' => ['pending'], 'a completed version of another material' => ['other']];
    }

    /**
     * I-09 (control of the check): the check used in I-03 to I-05 really fails when the pointer is broken, so it is not an assertion that
     * can never fail. The broken pointers are written straight in the table, which the repository would not allow.
     *
     * @param string $kind
     */
    #[DataProvider('broken_pointers')]
    public function test_the_check_of_the_pointer_detects_a_broken_pointer(string $kind): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->register_material($repository, 7405);
        $other = $this->register_material($repository, 7406);
        $foreign = $this->completed_current($repository, $other, 'hash-i09-other');
        $pending = $repository->add_version($id, 'hash-i09-pending', material_availability::AVAILABLE);
        $this->assert_pointer_invariant($id, 'I-09 control, valid (null)');

        $DB->set_field('local_assistente_material', 'current_version', $kind === 'pending' ? $pending : $foreign, ['id' => $id]);
        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assert_pointer_invariant($id, 'I-09 control, broken');
    }
}
