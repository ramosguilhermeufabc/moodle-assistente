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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests of the transactional unit of work (quickstart 003 Q5, Q7, Q8).
 *
 * These tests exercise the semantics inside one PHP process. Behaviour of the lock backend under
 * real concurrency is the technical validation CL-07 (task T020).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(unit_of_work::class)]
final class unit_of_work_test extends \advanced_testcase {
    /**
     * The tests use real transactions, so the test data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
    }

    /**
     * Insert a config row and the criteria row that depends on it.
     *
     * @param int $courseid
     * @param bool $failbetween throw between the two writes
     */
    private function write_config_and_criteria(int $courseid, bool $failbetween): void {
        global $DB;
        $configid = $DB->insert_record('local_assistente_config', (object) [
            'course_id' => $courseid, 'enabled' => 0, 'created_at' => time(),
        ]);
        if ($failbetween) {
            throw new \RuntimeException('failure between the two writes');
        }
        $DB->insert_record('local_assistente_criteria', (object) [
            'config_id' => $configid, 'activity_criterion' => 60, 'forum_criterion' => 1, 'frequency_criterion' => 40,
        ]);
    }

    /**
     * Q5: a failure in the middle of a multi-table operation leaves no partial state.
     */
    public function test_failure_in_the_middle_leaves_no_partial_state(): void {
        global $DB;
        $uow = new unit_of_work();

        try {
            $uow->run(fn() => $this->write_config_and_criteria(10, true));
            $this->fail('The failure must be rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('failure between the two writes', $e->getMessage());
        }

        $this->assertSame(0, $DB->count_records('local_assistente_config'));
        $this->assertSame(0, $DB->count_records('local_assistente_criteria'));
        $this->assertFalse($DB->is_transaction_started());
    }

    /**
     * A successful unit commits both writes and returns the value of the work.
     */
    public function test_successful_unit_commits_and_returns_the_result(): void {
        global $DB;
        $uow = new unit_of_work();

        $result = $uow->run(function () {
            $this->write_config_and_criteria(11, false);
            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(1, $DB->count_records('local_assistente_config'));
        $this->assertSame(1, $DB->count_records('local_assistente_criteria'));
        $this->assertFalse($DB->is_transaction_started());
    }

    /**
     * Q7: the guard used before calling the LLM port rejects an open transaction and accepts none.
     */
    public function test_llm_guard_rejects_an_open_transaction(): void {
        // Outside a transaction, the call to the port is allowed.
        unit_of_work::assert_no_active_transaction();
        $this->assertTrue(true);

        // Inside the unit (transaction open), it is not.
        $uow = new unit_of_work();
        $this->expectException(\coding_exception::class);
        $uow->run(fn() => unit_of_work::assert_no_active_transaction());
    }

    /**
     * Locks are acquired before the transaction: asking for locks inside one is a programming error.
     */
    public function test_locks_inside_an_open_transaction_are_rejected(): void {
        $uow = new unit_of_work();
        $this->expectException(\coding_exception::class);
        $uow->run(fn() => $uow->run(fn() => null, [unit_of_work::event_resource(1)]));
    }

    /**
     * The short transactions of the work done under the lock of an event (repositories of a handler) share that lock: run() on a
     * resource that run_locked() holds in this process does not acquire it again, and the lock is released once, by its owner.
     */
    public function test_run_shares_the_lock_held_by_run_locked(): void {
        $uow = new unit_of_work(1);
        $resource = unit_of_work::event_resource(77);

        $result = $uow->run_locked($resource, fn() => $uow->run(fn() => 'inner', [$resource]));

        $this->assertSame('inner', $result);
        $free = unit_of_work::lock_factory()->get_lock($resource, 1);
        $this->assertNotFalse($free, 'Released after the owner finished');
        $free->release();
    }

    /**
     * A second run_locked() on a resource the process already holds is another run, not the same owner: it is refused, so two runs of
     * the queue cannot process one event.
     */
    public function test_nested_run_locked_on_the_same_resource_is_refused(): void {
        $uow = new unit_of_work(1);
        $resource = unit_of_work::event_resource(78);
        $nested = null;

        $uow->run_locked($resource, function () use ($uow, $resource, &$nested) {
            try {
                $uow->run_locked($resource, fn() => $nested = 'ran');
            } catch (lock_unavailable_exception) {
                $nested = 'refused';
            }
        });

        $this->assertSame('refused', $nested);
    }

    /**
     * After a unit that held a resource, the next unit acquires it again (the held mark is cleared, also after an exception).
     */
    public function test_the_held_mark_is_cleared_after_an_exception(): void {
        $uow = new unit_of_work(1);
        $resource = unit_of_work::event_resource(79);

        try {
            $uow->run_locked($resource, function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            $this->assertSame('ok', $uow->run(fn() => 'ok', [$resource]));
        }
    }
    /**
     * Q8 (semantics): while a resource is locked, another unit for the same resource does not start,
     * and the lock is released after both a commit and a rollback.
     *
     * Scope: the logic of the plugin inside ONE process. The lock is held by the same connection that asks for it again, so the refusal
     * comes from the internal map of the lock factory and not from GET_LOCK on the server. This test is not evidence of exclusion in the
     * database: that is proved by M-06 and M-07 (unit_of_work_concurrency_test), with the lock held by another process (CL-07, correction 2).
     */
    #[TestDox('Lock logic in the process: refused while held, released after commit and rollback (database exclusion: M-06)')]
    public function test_lock_is_exclusive_and_released_after_commit_and_rollback(): void {
        $factory = unit_of_work::lock_factory();
        $resource = unit_of_work::material_resource(5);

        $held = $factory->get_lock($resource, 1);
        $this->assertNotFalse($held);
        $started = false;
        try {
            (new unit_of_work(1))->run(function () use (&$started) {
                $started = true;
            }, [$resource]);
            // The backend allowed a second acquisition in the same process; exclusion is checked in CL-07.
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated in CL-07 (T020).');
        } catch (lock_unavailable_exception $e) {
            $this->assertFalse($started, 'The work must not start without the lock');
        } finally {
            $held->release();
        }

        // Released after a commit.
        $uow = new unit_of_work(1);
        $uow->run(fn() => null, [$resource]);
        $again = $factory->get_lock($resource, 1);
        $this->assertNotFalse($again, 'The lock must be released after the commit');
        $again->release();

        // Released after a rollback.
        try {
            $uow->run(function () {
                throw new \RuntimeException('forced rollback');
            }, [$resource]);
        } catch (\RuntimeException $e) {
            $this->assertSame('forced rollback', $e->getMessage());
        }
        $again = $factory->get_lock($resource, 1);
        $this->assertNotFalse($again, 'The lock must be released after the rollback');
        $again->release();
    }

    /**
     * run_locked(): the work runs under the lock with no transaction open, and the lock is released afterwards, also on failure.
     */
    public function test_run_locked_holds_the_lock_without_a_transaction(): void {
        global $DB;
        $uow = new unit_of_work(1);
        $resource = unit_of_work::event_resource(42);
        $factory = unit_of_work::lock_factory();

        $inside = $uow->run_locked($resource, function () use ($DB) {
            return $DB->is_transaction_started();
        });
        $this->assertFalse($inside, 'No transaction is open while the work runs (external work)');
        $free = $factory->get_lock($resource, 1);
        $this->assertNotFalse($free, 'The lock is released after the work');
        $free->release();

        try {
            $uow->run_locked($resource, function () {
                throw new \RuntimeException('failure in the work');
            });
            $this->fail('The failure must be rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('failure in the work', $e->getMessage());
        }
        $again = $factory->get_lock($resource, 1);
        $this->assertNotFalse($again, 'The lock is released after a failure');
        $again->release();
    }

    /**
     * run_locked(): a held lock excludes the work; asking for a lock inside a transaction is a programming error.
     *
     * Scope: the logic of the plugin inside ONE process (the lock is held by the same connection, so the refusal comes from the internal map
     * of the lock factory). It is not evidence of exclusion in the database: that is proved by M-07 (unit_of_work_concurrency_test), with the
     * lock held by another process (CL-07, correction 2).
     */
    #[TestDox('run_locked logic in the process: refused while held, refused inside a transaction (database exclusion: M-07)')]
    public function test_run_locked_is_excluded_by_a_held_lock_and_refuses_open_transactions(): void {
        $resource = unit_of_work::event_resource(43);
        $factory = unit_of_work::lock_factory();
        $held = $factory->get_lock($resource, 1);
        $this->assertNotFalse($held);
        $ran = false;
        try {
            (new unit_of_work(1))->run_locked($resource, function () use (&$ran) {
                $ran = true;
            });
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated in CL-07 (T020).');
        } catch (lock_unavailable_exception $e) {
            $this->assertFalse($ran, 'The work must not start without the lock');
        } finally {
            $held->release();
        }

        $this->expectException(\coding_exception::class);
        (new unit_of_work())->run(fn() => (new unit_of_work())->run_locked($resource, fn() => null));
    }

    /**
     * Lock resource names are stable and distinct per aggregate.
     */
    public function test_resource_names(): void {
        $this->assertSame('event_7', unit_of_work::event_resource(7));
        $this->assertSame('material_7', unit_of_work::material_resource(7));
        $this->assertSame('synthesis_3_9', unit_of_work::section_resource(3, 9));
        $this->assertSame('report_7', unit_of_work::report_resource(7));
    }

    /**
     * The lock of the events of a student (T104, DEC-57) and the global order of the resources: run() sorts the names as strings and the
     * paths that hold one lock while they ask for another go upward in this order, so the order of the names must not change.
     */
    public function test_the_privacy_lock_and_the_global_order_of_the_resources(): void {
        $this->assertSame('privacy_user_58', unit_of_work::privacy_user_resource(58));
        $identity = \local_assistente\domain\event\occurrence_identity::lock_resource(
            3,
            \local_assistente\domain\event\event_type::GRADE_CHANGED,
            'x'
        );
        $this->assertSame('course_erasure_12', unit_of_work::course_erasure_resource(12));
        $names = [
            'course_erasure' => unit_of_work::course_erasure_resource(12),
            'event' => unit_of_work::event_resource(1234),
            'identity' => $identity,
            'material' => unit_of_work::material_resource(7),
            'privacy_user' => unit_of_work::privacy_user_resource(58),
            'report' => unit_of_work::report_resource(7),
            'synthesis' => unit_of_work::section_resource(3, 9),
        ];
        $sorted = $names;
        sort($sorted, SORT_STRING);

        $this->assertSame(array_values($names), $sorted, 'course_erasure < event < identity < material < privacy_user < report < synthesis');
        // The lock of the erasure of a course comes before every other, whatever the numbers of the ids.
        foreach ([1, 99, 100000, 99999999] as $id) {
            foreach ([$names['event'], $identity, $names['material'], $names['privacy_user'], $names['report'], $names['synthesis']] as $other) {
                $this->assertLessThan(0, strcmp(unit_of_work::course_erasure_resource($id), $other));
            }
        }
        // The numbers of the ids do not change it: every event comes before every identity, whatever its number.
        foreach ([1, 99, 100000, 99999999] as $id) {
            $this->assertLessThan(0, strcmp(unit_of_work::event_resource($id), $identity));
        }
    }
}
