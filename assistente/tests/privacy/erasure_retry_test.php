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

namespace local_assistente\privacy;

use core\task\manager;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use local_assistente\application\privacy\erasure_pending_exception;
use local_assistente\application\privacy\erasure_state_resolver;
use local_assistente\domain\event\event_type;
use local_assistente\domain\privacy\erasure_key;
use local_assistente\domain\privacy\erasure_state;
use local_assistente\infrastructure\moodle\erasure_audit_reader;
use local_assistente\infrastructure\persistence\course_erasure_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use local_assistente\task\retry_student_erasure;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The retry of the erasures, end to end (T142; DEC-58; quickstart 017 Q8): the Privacy API provider, the retry task run by the real
 * executor of the cron, the audit events in the log and the state. The locks are held through the shared lock factory of the process, as
 * in provider_test; the tasks are run by core\cron::run_inner_adhoc_task, the code the cron uses.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
#[CoversClass(retry_student_erasure::class)]
#[CoversClass(erasure_state_resolver::class)]
final class erasure_retry_test extends \advanced_testcase {
    /** @var \stdClass[] the courses A and B */
    private array $courses = [];

    /** @var \stdClass */
    private \stdClass $maria;

    /** @var \stdClass */
    private \stdClass $joao;

    /** @var event_repository */
    private event_repository $events;

    /** @var int */
    private int $tick = 0;

    /** @var string file that receives the PHP error log of the test */
    private string $phplog = '';

    /** @var string|false|null the error_log setting before the test */
    private string|false|null $previouslog = null;

    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $factory = unit_of_work::lock_factory();
        $first = $factory->get_lock('probe_reentrancy', 1);
        $second = $first ? $factory->get_lock('probe_reentrancy', 1) : false;
        $first && $first->release();
        $second && $second->release();
        if ($second) {
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated with separate processes (CL-02).');
        }
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
        $this->maria = $this->getDataGenerator()->create_user(['firstname' => 'Maria', 'lastname' => 'Souza']);
        $this->joao = $this->getDataGenerator()->create_user(['firstname' => 'João', 'lastname' => 'Lima']);
        $this->courses = [
            'A' => $this->getDataGenerator()->create_course(),
            'B' => $this->getDataGenerator()->create_course(),
        ];
        $this->events = new event_repository();
        provider::use_tracer(static function (string $line): void {
        });
        $this->phplog = tempnam(sys_get_temp_dir(), 't142');
        $this->previouslog = ini_set('error_log', $this->phplog);
    }

    public function tearDown(): void {
        provider::use_tracer(null);
        ini_set('error_log', (string) $this->previouslog);
        @unlink($this->phplog);
        parent::tearDown();
    }

    /**
     * A pending event of a student in a course: the residue by identifier, and the event whose lock is held.
     *
     * @param string $course A or B
     * @param \stdClass $user
     * @return int the id of the event
     */
    private function pending(string $course, \stdClass $user): int {
        return (int) $this->events->create_event(
            (int) $this->courses[$course]->id,
            event_type::GRADE_CHANGED,
            ['userid' => (int) $user->id, 'itemid' => 1, 'occurred_at' => ++$this->tick],
            time()
        )->id;
    }

    /**
     * Hold the lock of an event, as a handler that is running would.
     *
     * @param int $eventid
     * @return \core\lock\lock
     */
    private function hold(int $eventid): \core\lock\lock {
        $lock = unit_of_work::lock_factory()->get_lock(unit_of_work::event_resource($eventid), 1);
        $this->assertNotFalse($lock);
        return $lock;
    }

    /**
     * An approved list of contexts, as the privacy manager gives it.
     *
     * @param \stdClass $user
     * @param string[] $courses
     * @return approved_contextlist
     */
    private function approved(\stdClass $user, array $courses): approved_contextlist {
        return new approved_contextlist($user, 'local_assistente', array_map(fn($c) => \context_course::instance($this->courses[$c]->id)->id, $courses));
    }

    /**
     * Ask the erasure through the provider and return the exception, which must be the one told to the DPO.
     *
     * @param \stdClass $user
     * @param string[] $courses
     * @return erasure_pending_exception|null null when nothing was pending
     */
    private function ask(\stdClass $user, array $courses): ?erasure_pending_exception {
        try {
            provider::delete_data_for_user($this->approved($user, $courses));
        } catch (erasure_pending_exception $e) {
            return $e;
        }
        return null;
    }

    /**
     * Run the next retry task the way the cron does, even if its delay has not passed.
     *
     * @return array{0: int, 1: string}|null the attempts left that the task saw and its output; null when nothing is offered
     */
    private function run_next(): ?array {
        manager::clear_static_caches();
        $task = manager::get_next_adhoc_task(time() + 86400 * 400, true, '\\' . retry_student_erasure::class);
        return $task === null ? null : $this->execute_task($task);
    }

    /**
     * Run a task by its id, as `adhoc_task.php --id` does.
     *
     * @param int $id
     * @return array{0: int, 1: string}
     */
    private function run_id(int $id): array {
        manager::clear_static_caches();
        return $this->execute_task(manager::get_adhoc_task($id));
    }

    /**
     * @param \core\task\adhoc_task $task
     * @return array{0: int, 1: string}
     */
    private function execute_task(\core\task\adhoc_task $task): array {
        $seen = $task->get_attempts_available();
        ob_start();
        \core\cron::run_inner_adhoc_task($task);
        return [$seen, (string) ob_get_clean()];
    }

    /**
     * The retry tasks in the queue, by id.
     *
     * @return \stdClass[]
     */
    private function rows(): array {
        global $DB;
        return $DB->get_records('task_adhoc', ['classname' => '\\' . retry_student_erasure::class], 'id');
    }

    /**
     * How many audit events of a type a student has.
     *
     * @param \stdClass $user
     * @param string $type
     * @return int
     */
    private function audit_count(\stdClass $user, string $type): int {
        return count(array_filter((new erasure_audit_reader())->events_of((int) $user->id), fn($e) => $e->type === $type));
    }

    /**
     * The state of an erasure.
     *
     * @param \stdClass $user
     * @param string[] $courses
     * @return \local_assistente\domain\privacy\erasure_result
     */
    private function state(\stdClass $user, array $courses) {
        return (new erasure_state_resolver())->result(new erasure_key((int) $user->id, array_map(fn($c) => (int) $this->courses[$c]->id, $courses)));
    }

    /**
     * A digest of the tables of the plugin, to tell that nothing changed.
     *
     * @return string
     */
    private function digest(): string {
        global $DB;
        $all = [];
        foreach (course_erasure_repository::TABLES as $table) {
            $all[$table] = array_values($DB->get_records($table, null, 'id'));
        }
        return md5(json_encode($all));
    }

    /**
     * The payload of an event.
     *
     * @param int $eventid
     * @return array
     */
    private function payload(int $eventid): array {
        global $DB;
        return json_decode($DB->get_field('local_assistente_event', 'payload', ['id' => $eventid]), true);
    }

    /**
     * Defer an erasure by holding the lock, and release it when asked.
     *
     * @return array{0: int, 1: int, 2: \core\lock\lock} the event, the task and the held lock
     */
    private function deferred_case(): array {
        $event = $this->pending('A', $this->maria);
        $held = $this->hold($event);
        $exception = $this->ask($this->maria, ['A']);
        $this->assertNotNull($exception);
        $this->assertSame([1, 0], [$exception->deferred(), $exception->incomplete()]);
        $this->assertCount(1, $this->rows());
        return [$event, (int) array_key_first($this->rows()), $held];
    }

    /**
     * The last attempt: the task sees 8, 7 ... 1 attempts left, writes exactly one `stopped` event on the last, only then, and after it
     * Moodle no longer offers the task. Running the stopped task again by its id does not write another event. The output of the task has
     * no name and no lock; the data of the student is untouched; the state is STOPPED.
     */
    public function test_the_last_attempt_writes_exactly_one_stopped_event(): void {
        global $DB;
        [$event, $task, $held] = $this->deferred_case();
        $before = $this->digest();
        $this->assertSame(1, $this->audit_count($this->maria, 'deferred'));

        $seen = [];
        for ($run = 1; $run <= 9; $run++) {
            $ran = $this->run_next();
            if ($ran === null) {
                break;
            }
            $seen[] = $ran[0];
            $this->assertStringContainsString('Adhoc task failed', $ran[1]);
            foreach (['Maria', 'Souza', 'event_' . $event, 'privacy_user', 'Could not acquire'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $ran[1]);
            }
            $this->assertSame($run === 8 ? 1 : 0, $this->audit_count($this->maria, 'stopped'), "stopped events after run {$run}");
        }

        $this->assertSame([8, 7, 6, 5, 4, 3, 2, 1], $seen, 'Eight attempts, the last one sees only one left');
        $this->assertSame(0, (int) $DB->get_field('task_adhoc', 'attemptsavailable', ['id' => $task]));
        $this->assertNull($this->run_next(), 'Moodle no longer offers it');
        $this->assertSame($before, $this->digest());
        $this->assertSame(erasure_state::STOPPED, $this->state($this->maria, ['A'])->state);

        $this->run_id($task);
        $this->assertSame(1, $this->audit_count($this->maria, 'stopped'), 'A manual run of the stopped task writes no other event');
        $log = (string) file_get_contents($this->phplog);
        $this->assertSame(1, substr_count($log, 'the attempts to erase a student ended'), 'And no second line in the PHP log');
        foreach (['Maria', 'Souza', 'event_', 'privacy_user'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $log);
        }
        $this->assertSame(1, $this->audit_count($this->maria, 'deferred'));
        $held->release();
    }

    /**
     * Recovery after earlier failures: three attempts fail, the lock is released and the fourth completes by itself, with no new request.
     * The task disappears, one `completed` event of the retry exists, there is no `stopped`, the data is erased and the state says so.
     */
    public function test_recovery_after_a_previous_failure(): void {
        [$event, $task, $held] = $this->deferred_case();
        foreach ([8, 7, 6] as $left) {
            $this->assertSame($left, $this->run_next()[0]);
        }
        $this->assertSame(erasure_state::PENDING, $this->state($this->maria, ['A'])->state);
        $held->release();

        $ran = $this->run_next();

        $this->assertSame(5, $ran[0]);
        $this->assertStringContainsString('Adhoc task complete', $ran[1]);
        $this->assertCount(0, $this->rows());
        $this->assertSame([1, 1, 0], [$this->audit_count($this->maria, 'deferred'), $this->audit_count($this->maria, 'completed'), $this->audit_count($this->maria, 'stopped')]);
        $this->assertSame(0, $this->payload($event)['userid']);
        $state = $this->state($this->maria, ['A']);
        $this->assertSame(erasure_state::NO_RESIDUE_FOUND, $state->state);
        $this->assertTrue($state->closedbyretry);
        $this->assertSame('retry_task', $state->closedby);
        $this->assertSame('data+events', $state->evidence);
    }

    /**
     * A new request closes the case before the task runs: the case is closed by the request, not by the retry; the task that was waiting
     * is kept and, when it runs, finds nothing to do and adds no second `completed` event.
     */
    public function test_a_new_request_closes_the_case(): void {
        [$event, $task, $held] = $this->deferred_case();
        $held->release();

        $this->assertNull($this->ask($this->maria, ['A']));

        $state = $this->state($this->maria, ['A']);
        $this->assertSame([erasure_state::NO_RESIDUE_FOUND, false, 'new_request'], [$state->state, $state->closedbyretry, $state->closedby]);
        $this->assertSame(1, $this->audit_count($this->maria, 'completed'));
        $this->assertCount(1, $this->rows(), 'The task that waits is active and is kept');

        $ran = $this->run_next();
        $this->assertStringContainsString('Adhoc task complete', $ran[1]);
        $this->assertCount(0, $this->rows());
        $this->assertSame(1, $this->audit_count($this->maria, 'completed'), 'No second completed event for the same task');
        $this->assertFalse($this->state($this->maria, ['A'])->closedbyretry);
    }

    /**
     * Cases of overlapping courses, [A,B], [A] and [B], never mix. The student has an event in each course; the lock of the one in A is
     * held, so the requests that include A are deferred.
     */
    public function test_cases_with_overlapping_courses_do_not_mix(): void {
        $inA = $this->pending('A', $this->maria);
        $inB = $this->pending('B', $this->maria);
        $held = $this->hold($inA);
        $this->assertNotNull($this->ask($this->maria, ['A']));
        $this->assertNotNull($this->ask($this->maria, ['A', 'B']));
        $tasks = array_map('intval', array_keys($this->rows()));
        $this->assertCount(2, $tasks, 'Two cases, two tasks');
        [$onlya, $both] = $tasks;
        $this->assertSame(0 !== $this->payload($inB)['userid'], true, 'A failed attempt changed nothing, not even course B');
        foreach ([['A'], ['B'], ['A', 'B']] as $key) {
            $this->assertSame(erasure_state::PENDING, $this->state($this->maria, $key)->state, implode(',', $key));
        }
        $held->release();

        $this->run_id($both);

        $this->assertSame(0, $this->payload($inA)['userid']);
        $this->assertSame(0, $this->payload($inB)['userid']);
        $ab = $this->state($this->maria, ['A', 'B']);
        $this->assertSame([erasure_state::NO_RESIDUE_FOUND, true], [$ab->state, $ab->closedbyretry]);
        $a = $this->state($this->maria, ['A']);
        $this->assertSame([erasure_state::NO_RESIDUE_FOUND, false], [$a->state, $a->closedbyretry], 'The case [A] is not closed by the completion of [A,B]');
        $b = $this->state($this->maria, ['B']);
        $this->assertSame([erasure_state::NO_RESIDUE_FOUND, false], [$b->state, $b->closedbyretry], '[B] never had a case of its own');
        $this->assertArrayHasKey($onlya, $this->rows(), 'The task of [A] is active and is kept');

        $this->run_id($onlya);

        $this->assertTrue($this->state($this->maria, ['A'])->closedbyretry, 'Now [A] was completed by its own task');
        $this->assertFalse($this->state($this->maria, ['B'])->closedbyretry);
        $this->assertCount(0, $this->rows());
    }

    /**
     * The name is unavailable: the steps by identifier are done, but the erasure is never a success. A mark with no attempts and one
     * `stopped` event are left, the state is TEXTS_UNVERIFIED, repeating the request adds nothing, and only a request made with the name
     * available again closes the case.
     */
    public function test_an_unavailable_name_is_never_a_success(): void {
        global $DB;
        $event = $this->pending('A', $this->maria);
        $DB->set_field('user', 'firstname', ' ', ['id' => $this->maria->id]);

        $exception = $this->ask($this->maria, ['A']);

        $this->assertSame([0, 1], [$exception->deferred(), $exception->incomplete()]);
        $this->assertSame(0, $this->payload($event)['userid'], 'The steps by identifier were done');
        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertSame(0, (int) reset($rows)->attemptsavailable);
        $this->assertSame([0, 1, 0], [$this->audit_count($this->maria, 'deferred'), $this->audit_count($this->maria, 'stopped'), $this->audit_count($this->maria, 'completed')]);
        $this->assertSame(erasure_state::TEXTS_UNVERIFIED, $this->state($this->maria, ['A'])->state);

        $this->assertNotNull($this->ask($this->maria, ['A']));
        $this->assertCount(1, $this->rows(), 'The mark is not duplicated');
        $this->assertSame(1, $this->audit_count($this->maria, 'stopped'), 'Nor the event');
        $this->assertSame(erasure_state::TEXTS_UNVERIFIED, $this->state($this->maria, ['A'])->state);

        $DB->set_field('user', 'firstname', 'Maria', ['id' => $this->maria->id]);
        $this->assertNull($this->ask($this->maria, ['A']));
        $state = $this->state($this->maria, ['A']);
        $this->assertSame([erasure_state::NO_RESIDUE_FOUND, false, 'new_request'], [$state->state, $state->closedbyretry, $state->closedby]);
    }

    /**
     * The retry task finds the name unavailable (the account was anonymized meanwhile): it gives up its attempts at once, writes one
     * `stopped` event and the state is TEXTS_UNVERIFIED; the output has no name.
     */
    public function test_the_retry_gives_up_when_the_name_is_gone(): void {
        global $DB;
        [$event, $task, $held] = $this->deferred_case();
        $held->release();
        $DB->set_field('user', 'lastname', '', ['id' => $this->maria->id]);

        $ran = $this->run_next();

        $this->assertStringContainsString('Adhoc task failed', $ran[1]);
        $this->assertStringNotContainsString('Maria', $ran[1]);
        $this->assertSame(0, (int) $DB->get_field('task_adhoc', 'attemptsavailable', ['id' => $task]));
        $this->assertNull($this->run_next(), 'It is not offered again');
        $this->assertSame(1, $this->audit_count($this->maria, 'stopped'));
        $this->assertSame(0, $this->payload($event)['userid']);
        $this->assertSame(erasure_state::TEXTS_UNVERIFIED, $this->state($this->maria, ['A'])->state);
    }

    /**
     * A database failure is not retried (DEC-58): the provider throws it as it came, with no task and no event; inside the retry task it
     * stops the task at once, with a `stopped` event of code `unexpected_error`, and the message that leaves has fixed words.
     */
    public function test_a_database_failure_is_not_retried(): void {
        global $DB;
        $event = $this->pending('A', $this->maria);
        $table = $DB->get_prefix() . 'local_assistente_event';
        try {
            $DB->execute("CREATE TRIGGER t142_refuse BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused'");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }
        try {
            try {
                provider::delete_data_for_user($this->approved($this->maria, ['A']));
                $this->fail('The failure was swallowed');
            } catch (\dml_exception) {
                $this->assertCount(0, $this->rows(), 'A database failure schedules nothing');
                $this->assertSame(0, $this->audit_count($this->maria, 'deferred') + $this->audit_count($this->maria, 'stopped'));
            }

            // Inside the task: schedule by hand, as if the first attempt had met a lock.
            $task = (new \local_assistente\application\privacy\erasure_retry_scheduler())->schedule(
                new erasure_key((int) $this->maria->id, [(int) $this->courses['A']->id]),
                'lock_unavailable'
            );
            $ran = $this->run_next();

            $this->assertStringContainsString('Adhoc task failed', $ran[1]);
            $this->assertStringNotContainsString('refused', $ran[1]);
            $this->assertStringNotContainsString('UPDATE', $ran[1]);
            $this->assertSame(0, (int) $DB->get_field('task_adhoc', 'attemptsavailable', ['id' => $task]));
            $this->assertSame(1, $this->audit_count($this->maria, 'stopped'));
            $this->assertSame('unexpected_error', $this->last_detail($this->maria, 'stopped'));
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t142_refuse');
        }
        $this->assertSame(erasure_state::STOPPED, $this->state($this->maria, ['A'])->state);
        $this->assertNotSame(0, $this->payload($event)['userid']);
    }

    /**
     * The detail of the last event of a type.
     *
     * @param \stdClass $user
     * @param string $type
     * @return string
     */
    private function last_detail(\stdClass $user, string $type): string {
        $found = array_values(array_filter((new erasure_audit_reader())->events_of((int) $user->id), fn($e) => $e->type === $type));
        return $found ? end($found)->detail : '';
    }

    /**
     * The attempt that Moodle spends outside the code of the plugin (a fatal error, an orphan task) leaves the row with no attempts and no
     * `stopped` event: the state is STOPPED from the row alone; when the row is also gone (the retention of Moodle) it is INCOMPLETE, never
     * a success.
     */
    public function test_a_fatal_failure_outside_the_plugin_leaves_stopped_without_an_event(): void {
        global $DB;
        [$event, $task, $held] = $this->deferred_case();

        $DB->set_field('task_adhoc', 'attemptsavailable', 0, ['id' => $task]);

        $this->assertSame(0, $this->audit_count($this->maria, 'stopped'));
        $this->assertSame(erasure_state::STOPPED, $this->state($this->maria, ['A'])->state);

        manager::delete_adhoc_task($task);
        $DB->delete_records_select('logstore_standard_log', "eventname LIKE '%student_erasure%'");
        $this->assertSame(erasure_state::INCOMPLETE, $this->state($this->maria, ['A'])->state, 'No row, no event: only the residue is left to say');
        $held->release();
    }

    /**
     * Each student on his own, in the three ways of the Privacy API: with the lock of one student held, the other is erased at once, only
     * the held one gets a task, the exception counts one, and the held one keeps all his data.
     *
     * @param callable $ask what to call, receives the two students
     */
    private function assert_isolation(callable $ask): void {
        $mine = $this->pending('A', $this->maria);
        $his = $this->pending('A', $this->joao);
        $held = $this->hold($mine);

        $exception = $ask($this->maria, $this->joao);

        $held->release();
        $this->assertInstanceOf(erasure_pending_exception::class, $exception);
        $this->assertSame([1, 0], [$exception->deferred(), $exception->incomplete()]);
        $this->assertSame(0, $this->payload($his)['userid'], 'The other student was erased');
        $this->assertSame((int) $this->maria->id, $this->payload($mine)['userid'], 'The held one was not touched');
        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $key = erasure_key::from_custom_data(json_decode(reset($rows)->customdata));
        $this->assertSame([(int) $this->maria->id, [(int) $this->courses['A']->id]], [$key->userid(), $key->courses()]);
        $this->assertSame(erasure_state::PENDING, $this->state($this->maria, ['A'])->state);
        $this->assertSame(erasure_state::NO_RESIDUE_FOUND, $this->state($this->joao, ['A'])->state);
    }

    public function test_isolation_when_the_erasure_is_by_context(): void {
        $this->assert_isolation(function () {
            try {
                provider::delete_data_for_all_users_in_context(\context_course::instance($this->courses['A']->id));
            } catch (erasure_pending_exception $e) {
                return $e;
            }
            return null;
        });
    }

    public function test_isolation_when_the_erasure_is_by_list_of_users(): void {
        $this->assert_isolation(function (\stdClass $maria, \stdClass $joao) {
            try {
                provider::delete_data_for_users(new approved_userlist(\context_course::instance($this->courses['A']->id), 'local_assistente', [(int) $maria->id, (int) $joao->id]));
            } catch (erasure_pending_exception $e) {
                return $e;
            }
            return null;
        });
    }

    public function test_isolation_when_the_erasure_is_by_user(): void {
        $this->assert_isolation(function (\stdClass $maria, \stdClass $joao) {
            $first = $this->ask($joao, ['A']);
            $this->assertNull($first, 'The one with no lock is not deferred');
            return $this->ask($maria, ['A']);
        });
    }

    /**
     * A failure that is not transient does not stop the others: with the update of the event of the first student refused by the database,
     * the second is still erased, nothing is scheduled for the first, and the failure is thrown after, as it came.
     */
    public function test_a_database_failure_of_one_student_does_not_stop_the_others(): void {
        global $DB;
        $mine = $this->pending('A', $this->maria);
        $his = $this->pending('A', $this->joao);
        $table = $DB->get_prefix() . 'local_assistente_event';
        try {
            $DB->execute("CREATE TRIGGER t142_one BEFORE UPDATE ON {$table} FOR EACH ROW SET NEW.payload = IF(OLD.id = {$mine}, (SELECT 1 UNION SELECT 2), NEW.payload)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }
        try {
            try {
                provider::delete_data_for_all_users_in_context(\context_course::instance($this->courses['A']->id));
                $this->fail('The failure was swallowed');
            } catch (\dml_exception) {
                $this->addToAssertionCount(1);
            }
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t142_one');
        }

        $this->assertSame(0, $this->payload($his)['userid'], 'The second student was erased');
        $this->assertSame((int) $this->maria->id, $this->payload($mine)['userid'], 'The first was rolled back');
        $this->assertCount(0, $this->rows(), 'Nothing is scheduled for a database failure');
    }

    /**
     * Without the log (purged, or no store enabled) the state falls back to the data: NO_RESIDUE_FOUND says only that nothing was found, the
     * flag `closed_by_retry` and the evidence of the events are gone, and an unavailable name is still TEXTS_UNVERIFIED.
     */
    public function test_the_state_without_the_log(): void {
        global $DB;
        [$event, $task, $held] = $this->deferred_case();
        $held->release();
        $this->run_next();
        $this->assertTrue($this->state($this->maria, ['A'])->closedbyretry);

        $DB->delete_records_select('logstore_standard_log', "eventname LIKE '%student_erasure%'");
        $purged = $this->state($this->maria, ['A']);
        $this->assertSame([erasure_state::NO_RESIDUE_FOUND, false, null, 'data'], [$purged->state, $purged->closedbyretry, $purged->closedby, $purged->evidence]);

        set_config('enabled_stores', '', 'tool_log');
        get_log_manager(true);
        $this->assertSame(erasure_state::NO_RESIDUE_FOUND, $this->state($this->maria, ['A'])->state);

        $DB->set_field('user', 'firstname', '', ['id' => $this->maria->id]);
        $this->assertSame(erasure_state::TEXTS_UNVERIFIED, $this->state($this->maria, ['A'])->state);
    }

    /**
     * A text that still cites the name, with no id left, is residue: INCOMPLETE; after the redaction it is not.
     */
    public function test_a_text_that_cites_the_name_is_residue(): void {
        global $DB;
        $analysis = $DB->insert_record('local_assistente_analysis', (object) [
            'course_id' => $this->courses['A']->id, 'processing_id' => 1, 'analysis_type' => 'forum_analysis', 'cmid' => null,
            'summary' => 'A aluna Maria Souza perguntou.', 'created_at' => time(),
        ]);

        $this->assertSame(erasure_state::INCOMPLETE, $this->state($this->maria, ['A'])->state);
        $this->assertSame(erasure_state::NO_RESIDUE_FOUND, $this->state($this->joao, ['A'])->state, 'Another name');

        $DB->set_field('local_assistente_analysis', 'summary', 'A aluna [removido] perguntou.', ['id' => $analysis]);
        $this->assertSame(erasure_state::NO_RESIDUE_FOUND, $this->state($this->maria, ['A'])->state);
    }
}
