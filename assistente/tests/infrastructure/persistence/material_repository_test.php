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

/**
 * Tests of the material aggregate repository (T061; DEC-50, DEC-51; quickstart 008 Q2, Q8, Q9, Q10 at persistence level).
 *
 * Written against the Moodle test framework; not executed outside a Moodle 5.2 with PHPUnit (DEC-48).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(material_repository::class)]
final class material_repository_test extends \advanced_testcase {
    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
    }

    /**
     * Register a material.
     *
     * @param material_repository $repository
     * @param int $cmid
     * @return int material id
     */
    private function material(material_repository $repository, int $cmid = 5): int {
        return $repository->register(2, 10, $cmid, material_type::PDF, 'Apostila', material_availability::AVAILABLE, 1000);
    }

    /**
     * A version that is pending, completed with a summary, and current.
     *
     * @param material_repository $repository
     * @param int $materialid
     * @param string $hash
     * @param string $summary
     * @return int version id
     */
    private function current_version(material_repository $repository, int $materialid, string $hash, string $summary): int {
        $id = $repository->add_version($materialid, $hash, material_availability::AVAILABLE);
        $repository->complete_summary($id, $summary);
        $repository->switch_current($materialid, $id);
        return $id;
    }

    /**
     * A registered material has no version and no current version.
     */
    public function test_register_creates_a_material_without_versions(): void {
        $repository = new material_repository();
        $id = $this->material($repository);

        $material = $repository->find($id);
        $this->assertSame('pdf', $material->material_type);
        $this->assertSame('available', $material->availability);
        $this->assertNull($material->current_version);
        $this->assertSame([], $repository->find_versions($id));
        $this->assertNull($repository->find_current_version($material));
        $this->assertNull($repository->find_state(2, 5), 'No current version, nothing recorded to compare');
        $this->assertSame($id, (int) $repository->find_by_cmid(2, 5)->id);
        $this->assertNull($repository->find_by_cmid(2, 99));
    }

    /**
     * The same module cannot be registered twice.
     */
    public function test_a_module_is_registered_once(): void {
        global $DB;
        $repository = new material_repository();
        $this->material($repository);

        try {
            $this->material($repository);
            $this->fail('The duplicate must be rejected');
        } catch (duplicate_record_exception $e) {
            $this->assertSame(1, $DB->count_records('local_assistente_material'));
        }
    }

    /**
     * A new version is numbered, starts pending (a new version does not imply a completed summary) and is not current.
     */
    public function test_new_versions_start_pending_and_do_not_become_current(): void {
        $repository = new material_repository();
        $id = $this->material($repository);

        $first = $repository->add_version($id, 'hash-1', material_availability::PENDING, 1100);
        $second = $repository->add_version($id, 'hash-2', material_availability::AVAILABLE, 1200);

        $versions = array_values($repository->find_versions($id));
        $this->assertSame([$first, $second], array_map(fn($v) => (int) $v->id, $versions));
        $this->assertSame([1, 2], array_map(fn($v) => (int) $v->version, $versions));
        $this->assertSame(['pending', 'pending'], array_map(fn($v) => $v->summary_status, $versions));
        $this->assertSame([null, null], array_map(fn($v) => $v->summary, $versions));
        $this->assertSame(['pending', 'available'], array_map(fn($v) => $v->availability, $versions));
        $this->assertNull($repository->find($id)->current_version);
    }

    /**
     * Completing persists the summary and the status together; the availability is not touched (independent fields).
     */
    public function test_completing_persists_the_summary_and_keeps_the_availability(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $versionid = $repository->add_version($id, 'hash-1', material_availability::UNAVAILABLE);

        $repository->complete_summary($versionid, 'resumo');

        $version = $repository->find_version_by_id($versionid);
        $this->assertSame('completed', $version->summary_status);
        $this->assertSame('resumo', $version->summary);
        $this->assertSame('unavailable', $version->availability, 'Availability is independent of the summary status');
        $this->assertSame('hash-1', $version->content_hash);
    }

    /**
     * An empty summary is not a completed one: the version stays pending.
     */
    public function test_empty_summary_cannot_complete(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $versionid = $repository->add_version($id, 'hash-1', material_availability::AVAILABLE);

        foreach (['', '   '] as $summary) {
            try {
                $repository->complete_summary($versionid, $summary);
                $this->fail('An empty summary must be rejected');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('pending', $repository->find_version_by_id($versionid)->summary_status);
            }
        }
    }

    /**
     * The material only points to a completed version after the switch; the previous one stays in the history.
     */
    public function test_switch_makes_the_version_current_and_keeps_the_history(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $first = $this->current_version($repository, $id, 'hash-1', 'resumo 1');
        $this->assertSame($first, (int) $repository->find($id)->current_version);

        $second = $repository->add_version($id, 'hash-2', material_availability::AVAILABLE);
        $this->assertSame($first, (int) $repository->find($id)->current_version, 'Still version 1 until the switch');
        $repository->complete_summary($second, 'resumo 2');
        $repository->switch_current($id, $second);

        $material = $repository->find($id);
        $this->assertSame($second, (int) $material->current_version);
        $this->assertSame(2, (int) $repository->find_current_version($material)->version);
        $this->assertSame([1, 2], array_map(fn($v) => (int) $v->version, array_values($repository->find_versions($id))));
        $this->assertSame('hash-1', $repository->find_version($id, 1)->content_hash);
        $this->assertSame('resumo 1', $repository->find_version($id, 1)->summary, 'Version 1 is preserved');
        $this->assertSame('completed', $repository->find_version($id, 1)->summary_status);
    }

    /**
     * Only a completed version can become current: a pending or failed one cannot, and the previous stays current.
     */
    public function test_a_version_that_is_not_completed_cannot_become_current(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $previous = $this->current_version($repository, $id, 'hash-1', 'resumo 1');
        $pending = $repository->add_version($id, 'hash-2', material_availability::AVAILABLE);
        $failed = $repository->add_version($id, 'hash-3', material_availability::AVAILABLE);
        $repository->fail_summary($failed);

        foreach ([$pending, $failed] as $versionid) {
            try {
                $repository->switch_current($id, $versionid);
                $this->fail('The switch must be refused');
            } catch (\coding_exception $e) {
                $this->assertSame($previous, (int) $repository->find($id)->current_version);
            }
        }
    }

    /**
     * Failure: a failed summary keeps the last valid summary, which stays current and intact (DEC-50 rule 2).
     */
    public function test_failure_preserves_the_last_valid_summary(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $valid = $this->current_version($repository, $id, 'hash-1', 'resumo valido');

        $new = $repository->add_version($id, 'hash-2', material_availability::AVAILABLE);
        $repository->fail_summary($new);

        $this->assertSame('failed', $repository->find_version_by_id($new)->summary_status);
        $this->assertNull($repository->find_version_by_id($new)->summary);
        $material = $repository->find($id);
        $this->assertSame($valid, (int) $material->current_version);
        $current = $repository->find_current_version($material);
        $this->assertSame('resumo valido', $current->summary);
        $this->assertSame('completed', $current->summary_status);
        $this->assertSame('hash-1', $repository->find_state(2, 5)->contenthash);
    }

    /**
     * A completed version cannot be failed or restarted: a valid summary is never erased.
     */
    public function test_a_completed_summary_is_final(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $versionid = $this->current_version($repository, $id, 'hash-1', 'resumo');

        foreach ([
            fn() => $repository->fail_summary($versionid),
            fn() => $repository->retry_summary($versionid),
            fn() => $repository->complete_summary($versionid, 'outro resumo'),
        ] as $call) {
            try {
                $call();
                $this->fail('A completed summary must not change');
            } catch (\coding_exception $e) {
                $version = $repository->find_version_by_id($versionid);
                $this->assertSame('completed', $version->summary_status);
                $this->assertSame('resumo', $version->summary);
            }
        }
    }

    /**
     * Transitions: failed goes back to pending for a new attempt and can then complete; other transitions are refused.
     */
    public function test_transitions_of_the_summary_status(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $versionid = $repository->add_version($id, 'hash-1', material_availability::AVAILABLE);

        $this->expect_refused(fn() => $repository->retry_summary($versionid), 'pending to pending');
        $repository->fail_summary($versionid);
        $this->expect_refused(fn() => $repository->fail_summary($versionid), 'failed to failed');
        $this->expect_refused(fn() => $repository->complete_summary($versionid, 'x'), 'failed to completed');

        $repository->retry_summary($versionid);
        $this->assertSame('pending', $repository->find_version_by_id($versionid)->summary_status);
        $repository->complete_summary($versionid, 'resumo');
        $this->assertSame('completed', $repository->find_version_by_id($versionid)->summary_status);
    }

    /**
     * Assert that a call is refused as an invalid transition.
     *
     * @param callable $call
     * @param string $message
     */
    private function expect_refused(callable $call, string $message): void {
        try {
            $call();
            $this->fail("Refused transition expected: {$message}");
        } catch (\coding_exception $e) {
            $this->assertTrue(true, $message);
        }
    }

    /**
     * Logical rejection before any write: a summary cannot be completed on a version that already failed (invalid transition), so the
     * status is not changed (no completed without a summary).
     *
     * The repository refuses the transition BEFORE writing anything: this is not a failure of the write in the database and it is not
     * evidence of atomicity under such a failure (CR-07). That is proved by I-03 to I-05 (CL-07, correction 4).
     */
    #[TestDox('Logical rejection before any write: an invalid transition keeps the status (not a database write failure)')]
    public function test_failed_write_leaves_the_status_unchanged(): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->material($repository);
        $versionid = $repository->add_version($id, 'hash-1', material_availability::AVAILABLE);

        // A transition refused in the middle of the unit rolls everything back and releases the transaction.
        $repository->fail_summary($versionid);
        try {
            $repository->complete_summary($versionid, 'resumo');
            $this->fail('failed cannot go to completed');
        } catch (\coding_exception $e) {
            $version = $repository->find_version_by_id($versionid);
            $this->assertSame('failed', $version->summary_status);
            $this->assertNull($version->summary);
            $this->assertFalse($DB->is_transaction_started());
        }
    }

    /**
     * Logical rejection before any write: a switch to a version of another material, or to one that does not exist, keeps the previous
     * version current.
     *
     * The repository refuses the switch BEFORE writing anything (the target is validated first): this is not a failure of the write in the
     * database and it is not evidence of atomicity under such a failure (CR-07). That is proved by I-03 to I-05 (CL-07, correction 4).
     */
    #[TestDox('Logical rejection before any write: a foreign or missing target keeps the previous version (not a database write failure)')]
    public function test_failed_switch_keeps_the_previous_version(): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->material($repository, 5);
        $other = $this->material($repository, 6);
        $mine = $this->current_version($repository, $id, 'hash-1', 'r');
        $theirs = $this->current_version($repository, $other, 'hash-x', 'r');

        foreach ([[$theirs, \InvalidArgumentException::class], [987654, \dml_missing_record_exception::class]] as [$version, $class]) {
            try {
                $repository->switch_current($id, $version);
                $this->fail('The switch must fail');
            } catch (\Throwable $e) {
                $this->assertInstanceOf($class, $e);
            }
            $this->assertSame($mine, (int) $repository->find($id)->current_version);
            $this->assertFalse($DB->is_transaction_started());
        }
        try {
            $repository->switch_current(987654, $mine);
            $this->fail('A material that does not exist');
        } catch (\dml_missing_record_exception $e) {
            $this->assertSame($mine, (int) $repository->find($id)->current_version);
        }
    }

    /**
     * Rename or move: the details are updated and no version is created (Q8, Q9).
     */
    public function test_update_details_creates_no_version(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $version = $this->current_version($repository, $id, 'hash-1', 'r');

        $repository->update_details($id, 20, 'Outro nome');

        $material = $repository->find($id);
        $this->assertSame(20, (int) $material->section_id);
        $this->assertSame('Outro nome', $material->name);
        $this->assertSame($version, (int) $material->current_version);
        $this->assertCount(1, $repository->find_versions($id));

        $this->expectException(\dml_missing_record_exception::class);
        $repository->update_details(987654, 1, 'x');
    }

    /**
     * CL-49: update_details runs under the lock of the material: with the lock held elsewhere it fails and writes nothing, and
     * inside a transaction it is rejected.
     */
    public function test_update_details_needs_the_lock_of_the_material(): void {
        global $DB;
        $repository = new material_repository(new unit_of_work(1));
        $id = $this->material($repository);

        $lock = unit_of_work::lock_factory()->get_lock(unit_of_work::material_resource($id), 1);
        $this->assertNotFalse($lock);
        try {
            $repository->update_details($id, 20, 'Outro nome');
            $this->fail('The lock of the material is held by someone else');
        } catch (lock_unavailable_exception $e) {
            $material = $repository->find($id);
            $this->assertSame(10, (int) $material->section_id);
            $this->assertSame('Apostila', $material->name);
        } finally {
            $lock->release();
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $repository->update_details($id, 20, 'Outro nome');
            $this->fail('A lock must not be acquired inside a transaction');
        } catch (\coding_exception $e) {
            $this->assertSame('Apostila', $repository->find($id)->name);
        }
        try {
            $transaction->rollback(new \Exception('end of the test'));
        } catch (\Exception $e) {
            $this->assertSame('end of the test', $e->getMessage());
        }
    }

    /**
     * CL-49: a current_version switched after the caller last read the material is not restored by update_details, because the row
     * is read again inside the lock.
     */
    public function test_update_details_does_not_overwrite_a_newer_current_version(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $first = $this->current_version($repository, $id, 'hash-1', 'r1');
        $stale = $repository->find($id);
        $this->assertSame($first, (int) $stale->current_version);

        $second = $this->current_version($repository, $id, 'hash-2', 'r2');
        $repository->update_details($id, 20, 'Outro nome');

        $material = $repository->find($id);
        $this->assertSame($second, (int) $material->current_version, 'The newer pointer survives');
        $this->assertSame(20, (int) $material->section_id);
        $this->assertSame('Outro nome', $material->name);
    }

    /**
     * CL-49: the availability recorded meanwhile is kept, and the versions are not touched.
     */
    public function test_update_details_preserves_the_availability(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $version = $this->current_version($repository, $id, 'hash-1', 'r');
        $repository->update_availability($id, material_availability::UNAVAILABLE);

        $repository->update_details($id, 20, 'Outro nome');

        $material = $repository->find($id);
        $this->assertSame('unavailable', $material->availability);
        $this->assertSame($version, (int) $material->current_version);
        $this->assertSame('available', $repository->find_version_by_id($version)->availability);
        $this->assertCount(1, $repository->find_versions($id));
    }

    /**
     * CL-49: the normal update changes the name and the section, and only them.
     */
    public function test_update_details_changes_only_the_section_and_the_name(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $before = $repository->find($id);

        $repository->update_details($id, 33, 'Novo nome');

        $after = $repository->find($id);
        $this->assertSame(33, (int) $after->section_id);
        $this->assertSame('Novo nome', $after->name);
        foreach (['course_id', 'cmid', 'material_type', 'availability', 'current_version', 'created_at'] as $field) {
            $this->assertEquals($before->$field, $after->$field, $field);
        }
    }

    /**
     * The availability of the material changes by itself, with no effect on versions or on any summary status (DEC-51).
     */
    public function test_material_availability_is_independent(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $version = $this->current_version($repository, $id, 'hash-1', 'r');

        $repository->update_availability($id, material_availability::UNAVAILABLE);

        $this->assertSame('unavailable', $repository->find($id)->availability);
        $this->assertSame('completed', $repository->find_version_by_id($version)->summary_status);
        $this->assertSame('available', $repository->find_version_by_id($version)->availability);
        $this->assertCount(1, $repository->find_versions($id));

        $this->expectException(\dml_missing_record_exception::class);
        $repository->update_availability(987654, material_availability::PENDING);
    }

    /**
     * T058: the latest known availability moves among the three values, in both directions, and never touches the versions or the
     * current version.
     */
    public function test_availability_transitions_leave_versions_and_current_version_alone(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $v1 = $this->current_version($repository, $id, 'hash-1', 'r');
        $v2 = $repository->add_version($id, 'hash-2', material_availability::UNAVAILABLE);

        foreach ([material_availability::UNAVAILABLE, material_availability::AVAILABLE, material_availability::UNAVAILABLE,
                  material_availability::PENDING] as $to) {
            $repository->update_availability($id, $to);

            $this->assertSame($to->value, $repository->find($id)->availability);
            $this->assertSame($v1, (int) $repository->find($id)->current_version);
            $this->assertSame('available', $repository->find_version_by_id($v1)->availability);
            $this->assertSame('unavailable', $repository->find_version_by_id($v2)->availability);
            $this->assertCount(2, $repository->find_versions($id));
        }
    }

    /**
     * T058: recording the value already held writes nothing.
     */
    public function test_same_availability_is_not_written_again(): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->material($repository);
        $repository->update_availability($id, material_availability::UNAVAILABLE);

        $writes = $DB->perf_get_writes();
        $repository->update_availability($id, material_availability::UNAVAILABLE);

        $this->assertSame($writes, $DB->perf_get_writes());
        $this->assertSame('unavailable', $repository->find($id)->availability);
    }

    /**
     * T058: the update runs under the lock of the material, like the switch of the current version: with the lock held elsewhere it
     * fails and writes nothing, and inside a transaction it is rejected.
     */
    public function test_availability_is_updated_under_the_lock_of_the_material(): void {
        global $DB;
        $repository = new material_repository(new unit_of_work(1));
        $id = $this->material($repository);

        $lock = unit_of_work::lock_factory()->get_lock(unit_of_work::material_resource($id), 1);
        $this->assertNotFalse($lock);
        try {
            $repository->update_availability($id, material_availability::UNAVAILABLE);
            $this->fail('The lock of the material is held by someone else');
        } catch (lock_unavailable_exception $e) {
            $this->assertSame('available', $repository->find($id)->availability);
        } finally {
            $lock->release();
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $repository->update_availability($id, material_availability::UNAVAILABLE);
            $this->fail('A lock must not be acquired inside a transaction');
        } catch (\coding_exception $e) {
            $this->assertSame('available', $repository->find($id)->availability);
        }
        try {
            $transaction->rollback(new \Exception('end of the test'));
        } catch (\Exception $e) {
            $this->assertSame('end of the test', $e->getMessage());
        }
    }

    /**
     * What was recorded for the current version, for the detection of change, follows the switch.
     */
    public function test_state_is_that_of_the_current_version(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $this->current_version($repository, $id, 'hash-1', 'r');
        $repository->add_version($id, 'hash-2', material_availability::AVAILABLE);

        $state = $repository->find_state(2, 5);
        $this->assertSame('hash-1', $state->contenthash, 'A version that is not current is not the recorded state');
        $this->assertSame(material_type::PDF, $state->type);
        $this->assertSame(10, $state->sectionid);
        $this->assertSame('Apostila', $state->name);

        $repository->update_details($id, 30, 'Novo');
        $this->assertSame(30, $repository->find_state(2, 5)->sectionid);
    }

    /**
     * Invalid input: nothing is stored.
     */
    public function test_invalid_input_stores_nothing(): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->material($repository);

        foreach (['', '   ', str_repeat('a', 65)] as $hash) {
            try {
                $repository->add_version($id, $hash, material_availability::AVAILABLE);
                $this->fail('The hash must be rejected');
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, $DB->count_records('local_assistente_material_version'));
        $this->assertSame(1, $DB->count_records('local_assistente_material'));
    }

    /**
     * Values outside the approved domains are refused by the schema domains, even when written directly (DEC-50, DEC-51).
     */
    public function test_values_outside_the_domains_are_refused(): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->material($repository);
        $table = new table_repository('local_assistente_material_version');
        $row = fn(array $overrides) => (object) ($overrides + [
            'material_id' => $id, 'version' => 1, 'content_hash' => 'h', 'summary' => null,
            'summary_status' => 'pending', 'availability' => 'pending', 'created_at' => 1,
        ]);

        foreach ([['summary_status' => 'done'], ['summary_status' => 'extraction_pending'], ['availability' => 'maybe'],
                  ['availability' => 'completed'], ['summary_status' => '']] as $overrides) {
            try {
                $table->insert($row($overrides));
                $this->fail('A value outside the domain must be refused');
            } catch (invalid_domain_value_exception $e) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, $DB->count_records('local_assistente_material_version'));
    }

    /**
     * A version of a material that does not exist is not created.
     */
    public function test_version_of_a_missing_material_is_not_created(): void {
        global $DB;
        $repository = new material_repository();

        try {
            $repository->add_version(987654, 'hash', material_availability::AVAILABLE);
            $this->fail('The material does not exist');
        } catch (\dml_missing_record_exception $e) {
            $this->assertSame(0, $DB->count_records('local_assistente_material_version'));
            $this->assertFalse($DB->is_transaction_started());
        }
    }

    /**
     * A version that does not exist cannot be completed, failed or restarted.
     */
    public function test_missing_version_is_refused(): void {
        $repository = new material_repository();
        foreach ([
            fn() => $repository->complete_summary(987654, 'x'),
            fn() => $repository->fail_summary(987654),
            fn() => $repository->retry_summary(987654),
        ] as $call) {
            try {
                $call();
                $this->fail('The version does not exist');
            } catch (\dml_missing_record_exception $e) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * Versions can be added only outside a transaction, because the lock comes first (plan 003).
     */
    public function test_add_version_inside_a_transaction_is_rejected(): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->material($repository);

        $transaction = $DB->start_delegated_transaction();
        try {
            $repository->add_version($id, 'hash', material_availability::AVAILABLE);
            $this->fail('A lock must not be acquired inside a transaction');
        } catch (\coding_exception $e) {
            $this->assertTrue(true);
        }
        try {
            $transaction->rollback(new \Exception('end of the test'));
        } catch (\Exception $e) {
            $this->assertSame('end of the test', $e->getMessage());
        }
    }

    /**
     * Everything the switch must leave alone when it is refused: the material row and every version row, read from the database.
     *
     * @param int $materialid
     * @return array[] the material and its versions, as plain arrays
     */
    private function state_of(int $materialid): array {
        global $DB;
        return [
            'material' => (array) $DB->get_record('local_assistente_material', ['id' => $materialid]),
            'versions' => array_map(fn($v) => (array) $v, $DB->get_records('local_assistente_material_version', ['material_id' => $materialid], 'id ASC')),
        ];
    }

    /**
     * A completed version that is not current.
     *
     * @param material_repository $repository
     * @param int $materialid
     * @param string $hash
     * @return int version id
     */
    private function completed_version(material_repository $repository, int $materialid, string $hash): int {
        $id = $repository->add_version($materialid, $hash, material_availability::AVAILABLE);
        $repository->complete_summary($id, 'resumo ' . $hash);
        return $id;
    }

    /**
     * Q16 (DEC-62; T144), in sequence: the first selection with a null pointer and a greater number are accepted; an equal number (the
     * current one included) and a lower number are refused with nothing changed; a version of another material and a version that is not
     * completed are refused as before; the lower versions stay completed and can still be read. The pointer never goes back.
     */
    #[TestDox('Q16: null pointer and greater number accepted; equal and lower refused with nothing changed; history still readable')]
    public function test_q16_the_pointer_only_moves_to_a_greater_number(): void {
        $repository = new material_repository();
        $id = $this->material($repository);
        $other = $this->material($repository, 6);
        $foreign = $this->completed_version($repository, $other, 'hash-foreign');
        $v1 = $this->completed_version($repository, $id, 'hash-1');
        $v2 = $this->completed_version($repository, $id, 'hash-2');
        $v3 = $this->completed_version($repository, $id, 'hash-3');
        $pending = $repository->add_version($id, 'hash-4', material_availability::AVAILABLE);
        $this->assertNull($repository->find($id)->current_version);

        // (1) The first selection with a null pointer: version 2, not necessarily the first.
        $repository->switch_current($id, $v2);
        $this->assertSame($v2, (int) $repository->find($id)->current_version);
        // (2) A greater number.
        $repository->switch_current($id, $v3);
        $this->assertSame($v3, (int) $repository->find($id)->current_version);

        $before = $this->state_of($id);
        $beforeother = $this->state_of($other);
        // (3) The same number (the current version itself), and (4) lower numbers: refused, nothing changed.
        foreach ([[$v3, true], [$v2, false], [$v1, false]] as [$versionid, $iscurrent]) {
            try {
                $repository->switch_current($id, $versionid);
                $this->fail("Version {$versionid} must be refused");
            } catch (version_not_newer_exception $e) {
                $this->assertSame($iscurrent, $e->is_current());
                $this->assertSame($versionid, $e->versionid);
                $this->assertSame($v3, $e->currentid);
                $this->assertSame(3, $e->currentnumber);
            }
            $this->assertSame($before, $this->state_of($id), "Refusing version {$versionid} must change nothing");
        }
        // (5) Refused as before: another material, a version that is not completed.
        try {
            $repository->switch_current($id, $foreign);
            $this->fail('A version of another material must be refused');
        } catch (\InvalidArgumentException $e) {
            $this->assertNotInstanceOf(version_not_newer_exception::class, $e);
        }
        try {
            $repository->switch_current($id, $pending);
            $this->fail('A version that is not completed must be refused');
        } catch (\coding_exception $e) {
            $this->assertNotInstanceOf(version_not_newer_exception::class, $e);
        }
        $this->assertSame($before, $this->state_of($id));
        $this->assertSame($beforeother, $this->state_of($other));

        // The history is intact and readable: the lower versions stay completed with their summaries.
        $this->assertSame([1, 2, 3, 4], array_map(fn($v) => (int) $v->version, array_values($repository->find_versions($id))));
        foreach ([1 => $v1, 2 => $v2, 3 => $v3] as $number => $versionid) {
            $version = $repository->find_version($id, $number);
            $this->assertSame($versionid, (int) $version->id);
            $this->assertSame('completed', $version->summary_status);
            $this->assertSame('resumo hash-' . $number, $version->summary);
        }
        $this->assertSame($v3, (int) $repository->find($id)->current_version, 'The pointer never goes back');
    }

    /**
     * The comparison uses the number of the version, never the id: a version with a greater id and a lower number is refused.
     */
    public function test_the_comparison_is_by_number_and_not_by_id(): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->material($repository);
        $make = fn(int $number) => $DB->insert_record('local_assistente_material_version', (object) [
            'material_id' => $id, 'version' => $number, 'content_hash' => 'h' . $number, 'summary' => 'resumo ' . $number,
            'summary_status' => 'completed', 'availability' => 'available', 'created_at' => 1000,
        ]);
        $high = $make(3);
        $low = $make(2);
        $this->assertGreaterThan($high, $low, 'The lower number has the greater id');

        $repository->switch_current($id, $high);
        $before = $this->state_of($id);
        $this->expectException(version_not_newer_exception::class);
        try {
            $repository->switch_current($id, $low);
        } finally {
            $this->assertSame($before, $this->state_of($id));
        }
    }

    /**
     * A refusal by the rule is not a technical failure: it is its own type, not one that means something else, and it leaves the
     * lock free and no transaction open.
     */
    public function test_the_refusal_is_its_own_type_and_leaves_nothing_open(): void {
        global $DB;
        $repository = new material_repository();
        $id = $this->material($repository);
        $v1 = $this->current_version($repository, $id, 'hash-1', 'resumo 1');

        try {
            $repository->switch_current($id, $v1);
            $this->fail('The current version must be refused');
        } catch (\Throwable $e) {
            $this->assertSame(version_not_newer_exception::class, get_class($e));
            $this->assertNotInstanceOf(\coding_exception::class, $e);
            $this->assertNotInstanceOf(\InvalidArgumentException::class, $e);
            $this->assertNotInstanceOf(\dml_exception::class, $e);
            $this->assertNotInstanceOf(lock_unavailable_exception::class, $e);
        }
        $this->assertFalse($DB->is_transaction_started());
        $repository->add_version($id, 'hash-2', material_availability::AVAILABLE);
        $this->assertSame(2, (int) $DB->get_field_sql('SELECT MAX(version) FROM {local_assistente_material_version} WHERE material_id = ?', [$id]));
    }

    /**
     * CL-51: the completed version that should be current and is not. Only the completed version of the greatest number is a candidate, when
     * there is no current version or its number is greater than the current one; pending and failed versions are ignored whatever their
     * number and do not hide the completed one below them; a material that already has its greatest completed version current is left out;
     * and so is a material of a course without the assistant enabled. The query writes nothing.
     */
    #[TestDox('CL-51: the greatest completed version above the current one (or with no current), ignoring pending and failed')]
    public function test_the_query_selects_the_greatest_completed_version_that_is_not_current(): void {
        $config = new config_repository();
        $config->create(2, true);
        $config->create(3, false);
        $repository = new material_repository();

        // No current version: the greatest completed, with a pending and a failed above it.
        $none = $this->material($repository, 11);
        $this->completed_version($repository, $none, 'n1');
        $expectednone = $this->completed_version($repository, $none, 'n2');
        $repository->add_version($none, 'n3', material_availability::AVAILABLE);
        $this->failed_version($repository, $none, 'n4');

        // A current version below a greater completed one (and another between them, also completed), with a pending above.
        $above = $this->material($repository, 12);
        $this->current_version($repository, $above, 'a1', 'resumo a1');
        $this->completed_version($repository, $above, 'a2');
        $expectedabove = $this->completed_version($repository, $above, 'a3');
        $repository->add_version($above, 'a4', material_availability::AVAILABLE);

        // Already current: the greatest completed is the current one, with a pending above it.
        $done = $this->material($repository, 13);
        $this->completed_version($repository, $done, 'd1');
        $this->current_version($repository, $done, 'd2', 'resumo d2');
        $repository->add_version($done, 'd3', material_availability::AVAILABLE);

        // Only pending or failed versions, and no version at all.
        $pending = $this->material($repository, 14);
        $repository->add_version($pending, 'p1', material_availability::AVAILABLE);
        $failed = $this->material($repository, 15);
        $this->failed_version($repository, $failed, 'f1');
        $this->material($repository, 16);

        // A current completed version with a failed one above it.
        $failedabove = $this->material($repository, 17);
        $this->current_version($repository, $failedabove, 'fa1', 'resumo fa1');
        $this->failed_version($repository, $failedabove, 'fa2');

        // A course with the assistant disabled, and another without configuration.
        $disabled = $repository->register(3, 10, 18, material_type::PDF, 'Apostila', material_availability::AVAILABLE, 1000);
        $this->completed_version($repository, $disabled, 'x1');
        $unconfigured = $repository->register(4, 10, 19, material_type::PDF, 'Apostila', material_availability::AVAILABLE, 1000);
        $this->completed_version($repository, $unconfigured, 'u1');
        $before = $this->all_rows();

        $found = $repository->find_unpromoted_completed_versions();

        $this->assertSame([$none => $expectednone, $above => $expectedabove], $found);
        $this->assertEquals($before, $this->all_rows(), 'The query writes nothing');
        $repository->switch_current($none, $expectednone);
        $repository->switch_current($above, $expectedabove);
        $this->assertSame([], $repository->find_unpromoted_completed_versions(), 'Once promoted, the candidates are gone');
    }

    /**
     * Every row of the material tables.
     *
     * @return array
     */
    private function all_rows(): array {
        global $DB;
        return [
            $DB->get_records('local_assistente_material', [], 'id ASC'),
            $DB->get_records('local_assistente_material_version', [], 'id ASC'),
        ];
    }

    /**
     * A version that failed.
     *
     * @param material_repository $repository
     * @param int $materialid
     * @param string $hash
     * @return int version id
     */
    private function failed_version(material_repository $repository, int $materialid, string $hash): int {
        $id = $repository->add_version($materialid, $hash, material_availability::AVAILABLE);
        $repository->fail_summary($id);
        return $id;
    }

    /**
     * CL-51: the pending versions that may be marked failed. A pending version is a candidate when no completed version of a greater number
     * exists in its material and its course has the assistant enabled; a superseded pending version, a failed or completed version, and a
     * version of a course without the assistant enabled are not. The query writes nothing.
     */
    #[TestDox('CL-51: the pending versions without a later completed one, in enabled courses; the query writes nothing')]
    public function test_the_query_selects_the_pending_versions_that_nothing_supersedes(): void {
        $config = new config_repository();
        $config->create(2, true);
        $config->create(3, false);
        $repository = new material_repository();

        $alone = $this->material($repository, 21);
        $expectedalone = $repository->add_version($alone, 'p1', material_availability::AVAILABLE);

        // Completed below it: not superseded. Completed above it: superseded, and the one above is the only other pending of the material.
        $mixed = $this->material($repository, 22);
        $this->completed_version($repository, $mixed, 'm1');
        $expectedmixed = $repository->add_version($mixed, 'm2', material_availability::AVAILABLE);
        $superseded = $this->material($repository, 23);
        $repository->add_version($superseded, 's1', material_availability::AVAILABLE);
        $this->completed_version($repository, $superseded, 's2');

        // A pending version above a failed one is a candidate; the failed one is not.
        $failedbelow = $this->material($repository, 24);
        $this->failed_version($repository, $failedbelow, 'f1');
        $expectedfailedbelow = $repository->add_version($failedbelow, 'f2', material_availability::AVAILABLE);

        $done = $this->material($repository, 25);
        $this->completed_version($repository, $done, 'd1');
        $this->failed_version($repository, $this->material($repository, 26), 'x1');

        $disabled = $repository->register(3, 10, 27, material_type::PDF, 'Apostila', material_availability::AVAILABLE, 1000);
        $repository->add_version($disabled, 'o1', material_availability::AVAILABLE);
        $before = $this->all_rows();

        $found = $repository->find_pending_without_later_completed();

        $this->assertSame([$expectedalone, $expectedmixed, $expectedfailedbelow], array_map('intval', array_keys($found)));
        $this->assertSame([$alone, $mixed, $failedbelow], array_map(static fn($row) => (int) $row->materialid, array_values($found)));
        $this->assertSame([21, 22, 24], array_map(static fn($row) => (int) $row->cmid, array_values($found)));
        $this->assertSame([2, 2, 2], array_map(static fn($row) => (int) $row->courseid, array_values($found)));
        $this->assertEquals($before, $this->all_rows(), 'The query writes nothing');
    }

    /**
     * CL-51: the caller's question is asked inside the lock and the transaction, after the state was read again: a version it says is not
     * abandoned is left as it is, and one it says is abandoned is marked failed. An exception of the question rolls back and is raised.
     */
    #[TestDox('CL-51: the question of the caller is asked inside the lock; "no" leaves the version, "yes" marks it, an exception rolls back')]
    public function test_the_question_of_the_caller_decides_inside_the_lock(): void {
        $repository = new material_repository(new unit_of_work(1));
        $id = $this->material($repository, 33);
        $version = $repository->add_version($id, 'g1', material_availability::AVAILABLE);
        $asked = [];

        $this->assertFalse($repository->fail_pending_version($version, function () use (&$asked) {
            $asked[] = 'asked';
            return false;
        }));
        $this->assertSame('pending', $repository->find_version_by_id($version)->summary_status);
        $this->assertCount(1, $asked);

        try {
            $repository->fail_pending_version($version, static function (): bool {
                throw new \RuntimeException('question failed');
            });
            $this->fail('The exception of the question must be raised');
        } catch (\RuntimeException $e) {
            $this->assertSame('question failed', $e->getMessage());
        }
        $this->assertSame('pending', $repository->find_version_by_id($version)->summary_status);

        $this->assertTrue($repository->fail_pending_version($version, static fn(): bool => true));
        $this->assertSame('failed', $repository->find_version_by_id($version)->summary_status);
    }

    /**
     * CL-51: a pending version is marked failed, nothing else changes (the summary, the pointer and the other versions stay as they were).
     * A version that is no longer pending, or that a completed version of a greater number supersedes, is left as it is and the answer is
     * false; the transition runs under the lock of the material.
     */
    #[TestDox('CL-51: pending becomes failed; a version no longer pending or superseded is left as it is; the pointer is untouched')]
    public function test_a_pending_version_is_marked_failed_only_while_it_is_pending_and_not_superseded(): void {
        $repository = new material_repository(new unit_of_work(1));
        $id = $this->material($repository, 31);
        $current = $this->current_version($repository, $id, 'c1', 'resumo c1');
        $pending = $repository->add_version($id, 'c2', material_availability::AVAILABLE);

        // Superseded: a completed version of a greater number exists. Nothing is written.
        $later = $repository->add_version($id, 'c3', material_availability::AVAILABLE);
        $repository->complete_summary($later, 'resumo c3');
        $this->assertFalse($repository->fail_pending_version($pending));
        $this->assertSame('pending', $repository->find_version_by_id($pending)->summary_status);

        // Not superseded (another material): the transition is made, under the lock of the material.
        $other = $this->material($repository, 32);
        $alone = $repository->add_version($other, 'a1', material_availability::AVAILABLE);
        $this->assertTrue($repository->fail_pending_version($alone));
        $version = $repository->find_version_by_id($alone);
        $this->assertSame('failed', $version->summary_status);
        $this->assertNull($version->summary);
        $this->assertNull($repository->find($other)->current_version);

        // No longer pending: failed and completed versions are left as they are.
        $this->assertFalse($repository->fail_pending_version($alone));
        $this->assertFalse($repository->fail_pending_version($later));
        $this->assertSame('completed', $repository->find_version_by_id($later)->summary_status);
        $this->assertSame('resumo c3', $repository->find_version_by_id($later)->summary);

        // The pointer and the first version were never touched.
        $this->assertSame($current, (int) $repository->find($id)->current_version);
        $this->assertSame('resumo c1', $repository->find_version_by_id($current)->summary);

        // The lock of the material is taken: held by someone else, the transition is refused and nothing is written.
        $stuck = $repository->add_version($other, 'a2', material_availability::AVAILABLE);
        $lock = unit_of_work::lock_factory()->get_lock(unit_of_work::material_resource($other), 1);
        $this->assertNotFalse($lock);
        try {
            $repository->fail_pending_version($stuck);
            $this->fail('The lock of the material is held by someone else');
        } catch (lock_unavailable_exception) {
            $this->assertSame('pending', $repository->find_version_by_id($stuck)->summary_status);
        } finally {
            $lock->release();
        }
        $this->assertTrue($repository->fail_pending_version($stuck));
    }
}
