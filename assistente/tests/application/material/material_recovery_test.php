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

namespace local_assistente\application\material;

use local_assistente\application\queue\attempt_failure;
use local_assistente\domain\material\material_availability;
use local_assistente\domain\material_type;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\moodle\material_reader;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

require_once(__DIR__ . '/../../fixtures/lock_observer.php');

/**
 * Recovery of the material service after a failure in each step of the persistence (CL-07: I-06 = scenario C, I-13 = scenario A2,
 * I-15 = scenario B; I-09 in all of them).
 *
 * The service is run for real, against the database of the test: a first execution fails at a known step and leaves a known state, and a
 * new execution of the same service, with the same content, must find that state and finish the work. The failure of the persistence is
 * a trigger of the test database (created right before the execution that must fail and removed right after, always in `finally`).
 *
 * - I-06 (C): the switch of `current_version` fails after the version is `completed`. The new execution only moves the pointer: no new
 *   call to the LLM, no new version.
 * - I-13 (A2): a `pending` version is left behind, by an exception of the extractor or by a process that died after the first step. The
 *   new execution takes up the same version and calls the LLM once.
 * - I-15 (B): the summary cannot be persisted. The service leaves the version `failed`, with no summary, and the new execution takes up
 *   the same version and calls the LLM AGAIN (two calls in all). This is the limitation accepted in CL-52: the test records the behaviour,
 *   it is not a guarantee of idempotence and not a requirement.
 *
 * The state is read from the database, never inferred from the result of the service. The pointer must be null or point to a `completed`
 * version of the same material (I-09) before the failure, after it and after the recovery. The monotonicity of DEC-62 is not tested (T144).
 * A prerequisite that is missing (observation of the locks, creation of the trigger, strict SQL mode) is a failure, never a skip.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(material_service::class)]
final class material_recovery_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var string start of the name of every trigger of this class, and only of them: triggers of other tests are not touched */
    private const TRIGGER_PREFIX = 'cl07_mr_';

    /** @var int seconds to wait for the metadata lock of a CREATE or DROP TRIGGER before failing */
    private const DDL_WAIT_SECONDS = 10;

    /** @var material_repository */
    private material_repository $repository;

    /** @var fake_adapter */
    private fake_adapter $llm;

    /** @var material_service */
    private material_service $service;

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
        $this->assert_observable_locks();
        $this->repository = new material_repository();
        $this->llm = new fake_adapter();
        $this->service = new material_service($this->repository, null, $this->llm);
    }

    protected function tearDown(): void {
        global $DB;
        try {
            $this->drop_leftover_triggers();
        } finally {
            try {
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
     * Create a trigger. Any error of the creation fails the test.
     *
     * @param string $name name of the trigger, starting with TRIGGER_PREFIX
     * @param string $event INSERT or UPDATE
     * @param string $table logical name of the table
     * @param string $body the one statement the trigger runs, without `;`
     */
    private function create_trigger(string $name, string $event, string $table, string $body): void {
        global $DB;
        $this->assertMatchesRegularExpression('/^' . self::TRIGGER_PREFIX . '[a-z0-9_]+$/', $name);
        $this->assertStringNotContainsString(';', $body, 'The trigger must be one statement');
        $this->assertFalse($DB->is_transaction_started(), 'DDL commits implicitly: no transaction may be open');
        try {
            $DB->execute("CREATE TRIGGER {$name} BEFORE {$event} ON " . $DB->get_prefix() . $table . " FOR EACH ROW {$body}");
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
     * I-09: the material exists, and its pointer is null or points to a completed version of the same material. Read from the database.
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
     * Nothing is left open: no transaction (Moodle and, when the server tells, the server), the lock free in the server, and no trigger.
     *
     * @param int $materialid
     * @param string $when
     */
    private function assert_nothing_left_open(int $materialid, string $when): void {
        global $DB;
        $this->assertFalse($DB->is_transaction_started(), "{$when}: a transaction was left open");
        try {
            $this->assertSame(0, (int) $DB->get_field_sql('SELECT @@in_transaction'), "{$when}: the server reports a transaction in progress");
        } catch (\dml_exception $e) {
            $this->note("{$when}: @@in_transaction is not available in this server; only is_transaction_started() was checked");
        }
        $resource = unit_of_work::material_resource($materialid);
        $this->assertNull($this->lock_owner($resource), "{$when}: the lock of {$resource} is still held in the server");
        $this->assertSame([], $this->leftover_triggers(), "{$when}: a trigger of the test was left in the database");
    }

    /**
     * A course with a page.
     *
     * @param string $content
     * @return \stdClass course, cmid and instance
     */
    private function page(string $content): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => $content]);
        return (object) ['course' => $course, 'cmid' => (int) $page->cmid, 'instance' => (int) $page->id];
    }

    /**
     * Change the text of the page, which makes the next execution see a new content.
     *
     * @param \stdClass $page
     * @param string $content
     */
    private function change_content(\stdClass $page, string $content): void {
        global $DB;
        $DB->set_field('page', 'content', $content, ['id' => $page->instance]);
    }

    /**
     * Run the service for the page.
     *
     * @param \stdClass $page
     * @param material_service|null $service
     * @return \local_assistente\application\queue\handler_result
     */
    private function run_service(\stdClass $page, ?material_service $service = null) {
        return ($service ?? $this->service)->process((int) $page->course->id, $page->cmid);
    }

    /**
     * The material of the page.
     *
     * @param \stdClass $page
     * @return \stdClass
     */
    private function material_of(\stdClass $page): \stdClass {
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertNotNull($material, 'The material must be registered');
        return $material;
    }

    /**
     * The versions of a material, oldest first.
     *
     * @param int $materialid
     * @return \stdClass[]
     */
    private function versions_of(int $materialid): array {
        return array_values($this->repository->find_versions($materialid));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function with_or_without_a_current_version(): array {
        return ['a previous current version' => [true], 'no current version before' => [false]];
    }

    /**
     * I-06 (scenario C; CR-19): the switch of the pointer fails after the version is completed. A new execution of the service with the same
     * content only moves the pointer: the LLM is not called again and no version number is created.
     *
     * @param bool $previous whether the material already had a completed and current version
     */
    #[DataProvider('with_or_without_a_current_version')]
    public function test_a_switch_that_failed_after_completed_is_finished_without_calling_the_llm(bool $previous): void {
        $page = $this->page('<p>Texto 1</p>');
        $first = null;
        if ($previous) {
            $this->assertTrue($this->run_service($page)->success);
            $first = (int) $this->material_of($page)->current_version;
            $this->change_content($page, '<p>Texto 2</p>');
        }
        $callsbefore = $this->llm->calls();

        $marker = 'cl07 i06 falha injetada na troca';
        $this->create_trigger(
            'cl07_mr_i06_switch',
            'UPDATE',
            'local_assistente_material',
            "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$marker}'"
        );
        try {
            $failed = $this->run_service($page);
        } finally {
            $this->drop_failure_trigger('cl07_mr_i06_switch');
        }

        $material = $this->material_of($page);
        $materialid = (int) $material->id;
        $this->assertFalse($failed->success, 'The execution must fail at the switch');
        $this->assertSame(attempt_failure::TECHNICAL, $failed->failure);
        $this->assertStringStartsWith('could not persist the summary', (string) $failed->message);
        $versions = $this->versions_of($materialid);
        $target = end($versions);
        $this->assertCount($previous ? 2 : 1, $versions);
        $this->assertSame('completed', $target->summary_status, 'The version is completed and not current (DEC-61)');
        $this->assertNotNull($target->summary);
        $this->assertSame($first, $material->current_version === null ? null : (int) $material->current_version, 'The pointer must not move');
        $this->assertSame($callsbefore + 1, $this->llm->calls(), 'The LLM answered once for this version');
        $this->assert_pointer_invariant($materialid, 'I-06 after the failure');
        $this->assert_nothing_left_open($materialid, 'I-06 after the failure');
        $callsafterfailure = $this->llm->calls();

        $recovered = $this->run_service($page);

        $this->assertTrue($recovered->success, 'The new execution must succeed: ' . (string) $recovered->message);
        $this->assertStringContainsString('is current', (string) $recovered->response(), 'Only the switch was left to do');
        $this->assertSame($callsafterfailure, $this->llm->calls(), 'The LLM must not be called again');
        $after = $this->versions_of($materialid);
        $this->assertCount(count($versions), $after, 'No new version number');
        $this->assertEquals($versions, $after, 'No version row may change');
        $this->assertSame((int) $target->id, (int) $this->material_of($page)->current_version, 'The pointer must be on the version');
        $this->assert_pointer_invariant($materialid, 'I-06 after the recovery');
        $this->assert_nothing_left_open($materialid, 'I-06 after the recovery');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ways_to_leave_a_pending_version(): array {
        return ['exception of the extractor' => ['extractor'], 'process that died after the first step' => ['died']];
    }

    /**
     * I-13 (scenario A2; CR-19): a pending version is left behind. A new execution takes up the same version, creates no other, calls the
     * LLM once and leaves it completed and current.
     *
     * The state "pending, no summary" is the one the first step of DEC-61 leaves. It is reached either by an exception of the extractor
     * (the extractor is called outside the `try` of the service, so the exception leaves the service) or by what a process that died right
     * after `add_version` would leave: the same `add_version` of the repository, with the hash of the content, and nothing else. A real
     * kill of a process running the service is not exercised (the fixtures of the workers do not run the service).
     *
     * @param string $how
     */
    #[DataProvider('ways_to_leave_a_pending_version')]
    public function test_a_pending_version_left_behind_is_taken_up_and_completed(string $how): void {
        $page = $this->page('<p>Texto 1</p>');
        $this->assertTrue($this->run_service($page)->success);
        $materialid = (int) $this->material_of($page)->id;
        $first = (int) $this->material_of($page)->current_version;
        $this->change_content($page, '<p>Texto 2</p>');
        $callsbefore = $this->llm->calls();

        if ($how === 'extractor') {
            $inner = new page_extractor();
            $broken = new class ($inner) implements content_extractor {
                /**
                 * @param page_extractor $inner
                 */
                public function __construct(private readonly page_extractor $inner) {
                }

                #[\Override]
                public function type(): material_type {
                    return $this->inner->type();
                }

                #[\Override]
                public function extract(int $cmid): ?extracted_content {
                    throw new \RuntimeException('the extractor failed');
                }
            };
            $service = new material_service($this->repository, null, $this->llm, null, [$broken]);
            $thrown = null;
            try {
                $this->run_service($page, $service);
            } catch (\Throwable $e) {
                $thrown = $e;
            }
            $this->assertInstanceOf(\RuntimeException::class, $thrown, 'The exception of the extractor leaves the service');
            $this->assertSame('the extractor failed', $thrown->getMessage());
        } else {
            $hash = (new material_reader())->get_material((int) $page->course->id, $page->cmid)->content_hash;
            $this->repository->add_version($materialid, $hash, material_availability::PENDING);
        }

        $versions = $this->versions_of($materialid);
        $target = end($versions);
        $this->assertCount(2, $versions);
        $this->assertSame('pending', $target->summary_status, 'The version is left pending');
        $this->assertNull($target->summary);
        $this->assertSame($first, (int) $this->material_of($page)->current_version, 'The pointer must stay on the previous version');
        $this->assertSame($callsbefore, $this->llm->calls(), 'The LLM was not reached');
        $this->assert_pointer_invariant($materialid, 'I-13 after the failure');
        $this->assert_nothing_left_open($materialid, 'I-13 after the failure');

        $recovered = $this->run_service($page);

        $this->assertTrue($recovered->success, 'The new execution must succeed: ' . (string) $recovered->message);
        $after = $this->versions_of($materialid);
        $this->assertCount(2, $after, 'The count of versions must not change');
        $done = end($after);
        $this->assertSame((int) $target->id, (int) $done->id, 'The same version must be taken up');
        $this->assertSame((int) $target->version, (int) $done->version);
        $this->assertSame('completed', $done->summary_status);
        $this->assertNotEmpty($done->summary);
        $this->assertSame((int) $done->id, (int) $this->material_of($page)->current_version, 'The version must be current');
        $this->assertSame($callsbefore + 1, $this->llm->calls(), 'The LLM must be called exactly once');
        $this->assert_pointer_invariant($materialid, 'I-13 after the recovery');
        $this->assert_nothing_left_open($materialid, 'I-13 after the recovery');
    }

    /**
     * I-15 (scenario B; CR-19; limitation accepted in CL-52): the LLM answers and the summary cannot be persisted. The service leaves the
     * version failed, with no summary, and the previous version current. A new execution takes up the same version and asks the LLM AGAIN:
     * two calls in all for this version, and the second summary is a new one. The test records this behaviour; it is not a guarantee of
     * idempotence and it is not required of the plugin.
     *
     * The failure must reach only the step that makes the version completed, because the service still has to mark it failed. A trigger that
     * is conditional per row cannot be created through `$DB->execute()` (compound body), so the trigger is one statement with a conditional
     * expression, with no `;`, that sets a NOT NULL column to NULL when the new status is `completed`: it needs a strict SQL mode, which
     * the test checks. That failure has no marker of its own; it is recognised by the state it leaves.
     */
    public function test_a_summary_that_could_not_be_persisted_is_asked_again(): void {
        global $DB;
        $mode = (string) $DB->get_field_sql('SELECT @@SESSION.sql_mode');
        $this->assertMatchesRegularExpression(
            '/STRICT_(TRANS|ALL)_TABLES/',
            $mode,
            'PRÉ-REQUISITO AUSENTE: the failure of I-15 needs a strict SQL mode (now: ' . $mode . ')'
        );
        $page = $this->page('<p>Texto 1</p>');
        $this->assertTrue($this->run_service($page)->success);
        $materialid = (int) $this->material_of($page)->id;
        $first = (int) $this->material_of($page)->current_version;
        $this->change_content($page, '<p>Texto 2</p>');
        $base = $this->llm->calls();

        $this->create_trigger(
            'cl07_mr_i15_complete',
            'UPDATE',
            'local_assistente_material_version',
            "SET NEW.material_id = IF(NEW.summary_status = 'completed', NULL, NEW.material_id)"
        );
        try {
            $failed = $this->run_service($page);
        } finally {
            $this->drop_failure_trigger('cl07_mr_i15_complete');
        }

        $this->assertFalse($failed->success, 'The execution must fail when the summary is persisted');
        $this->assertSame(attempt_failure::TECHNICAL, $failed->failure);
        $this->assertStringStartsWith('could not persist the summary', (string) $failed->message);
        $versions = $this->versions_of($materialid);
        $target = end($versions);
        $this->assertCount(2, $versions);
        $this->assertSame('failed', $target->summary_status, 'The service must leave the version failed');
        $this->assertNull($target->summary, 'No summary may be left');
        $this->assertSame($first, (int) $this->material_of($page)->current_version, 'The previous version must stay current');
        $this->assertSame($base + 1, $this->llm->calls(), 'The LLM answered once, and its answer was lost');
        $this->assert_pointer_invariant($materialid, 'I-15 after the failure');
        $this->assert_nothing_left_open($materialid, 'I-15 after the failure');

        $recovered = $this->run_service($page);

        $this->assertTrue($recovered->success, 'The new execution must succeed: ' . (string) $recovered->message);
        $after = $this->versions_of($materialid);
        $this->assertCount(2, $after, 'The count of versions must not change');
        $done = end($after);
        $this->assertSame((int) $target->id, (int) $done->id, 'The same version must be taken up');
        $this->assertSame('completed', $done->summary_status);
        $this->assertNotEmpty($done->summary);
        $this->assertSame((int) $done->id, (int) $this->material_of($page)->current_version, 'The version must be current');
        $this->assertSame($base + 2, $this->llm->calls(), 'The LLM is called again: two calls in all for this version (CL-52)');
        $this->assert_pointer_invariant($materialid, 'I-15 after the recovery');
        $this->assert_nothing_left_open($materialid, 'I-15 after the recovery');
    }
}
