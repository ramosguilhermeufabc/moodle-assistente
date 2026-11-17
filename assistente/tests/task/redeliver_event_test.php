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

namespace local_assistente\task;

use core\task\manager;
use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\domain\event\occurrence_identity;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;

require_once(__DIR__ . '/../fixtures/lock_holder.php');
require_once(__DIR__ . '/../fixtures/lock_observer.php');

/**
 * Tests of the task that delivers again an event whose lock was busy (exception to DEC-32 D2; DEC-16, DEC-22, DEC-57, DEC-58).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(redeliver_event::class)]
final class redeliver_event_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_holder;
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var \stdClass course with the assistant enabled */
    private \stdClass $course;

    /**
     * Real transactions, locks and tasks are used.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($this->course->id, true);
    }

    /**
     * The holders end.
     */
    protected function tearDown(): void {
        $this->release_locks();
        $this->end_actors();
        $this->stop_recording_locks();
        parent::tearDown();
    }

    /**
     * Schedule a delivery and give back the task, as Moodle would hand it to the run.
     *
     * @param event_type $type
     * @param array $payload
     * @param int|null $courseid
     * @return redeliver_event
     */
    private function scheduled(event_type $type, array $payload, ?int $courseid = null): redeliver_event {
        $id = (new event_redelivery_scheduler())->defer($courseid ?? (int) $this->course->id, $type, $type->normalize_payload($payload));
        return manager::get_adhoc_tasks(redeliver_event::class)[$id];
    }

    /**
     * The same task, waiting one second for a lock instead of thirty, so a test about a busy lock does not wait.
     *
     * @param redeliver_event $task
     * @param \Closure|null $queue builds the queue the task delivers to; one with a wait of a second when null
     * @return redeliver_event
     */
    private function quick(redeliver_event $task, ?\Closure $queue = null): redeliver_event {
        $quick = new class ($queue) extends redeliver_event {
            /**
             * Constructor.
             *
             * @param \Closure|null $queue
             */
            public function __construct(private readonly ?\Closure $queue) {
            }

            #[\Override]
            protected function queue(): event_service {
                return $this->queue !== null ? ($this->queue)() : new event_service(null, null, new unit_of_work(1));
            }

            #[\Override]
            protected function locks(): unit_of_work {
                return new unit_of_work(1);
            }
        };
        $quick->set_id($task->get_id());
        $quick->set_custom_data($task->get_custom_data());
        $quick->set_attempts_available($task->get_attempts_available());
        return $quick;
    }

    /**
     * Run a task and give back what it printed.
     *
     * @param redeliver_event $task
     * @return string
     */
    private function run_task(redeliver_event $task): string {
        ob_start();
        try {
            $task->execute();
        } finally {
            $output = ob_get_clean();
        }
        return (string) $output;
    }

    /**
     * Number of events of the course.
     *
     * @return int
     */
    private function events(): int {
        global $DB;
        return $DB->count_records('local_assistente_event', ['course_id' => $this->course->id]);
    }

    /**
     * Run a task and give back what it left in the log of PHP.
     *
     * @param redeliver_event $task
     * @param \Throwable|null $thrown set to what the task threw
     * @return string
     */
    private function logged(redeliver_event $task, ?\Throwable &$thrown = null): string {
        $logfile = tempnam(sys_get_temp_dir(), 'redeliver_log_');
        $previous = ini_set('error_log', $logfile);
        try {
            $this->run_task($task);
        } catch (\Throwable $e) {
            $thrown = $e;
        } finally {
            ini_set('error_log', (string) $previous);
        }
        $log = (string) file_get_contents($logfile);
        unlink($logfile);
        return $log;
    }

    /**
     * The name is a text of the plugin, in both languages.
     */
    public function test_the_task_has_a_name(): void {
        $this->assertSame(get_string('task:redeliver_event', 'local_assistente'), (new redeliver_event())->get_name());
        $this->assertNotSame('[[task:redeliver_event]]', (new redeliver_event())->get_name());
    }

    /**
     * It delivers the event with the identity of the original occurrence, and a second run reuses it: nothing is duplicated.
     */
    public function test_it_delivers_with_the_original_identity_and_a_second_run_reuses(): void {
        global $DB;
        // A real, active student: the guard asks for the account.
        $student = $this->getDataGenerator()->create_user();
        $payload = ['userid' => (int) $student->id, 'itemid' => 7, 'occurred_at' => 1790000000];
        $task = $this->scheduled(event_type::GRADE_CHANGED, $payload);

        $this->assertStringContainsString('created', $this->run_task($task));
        $this->assertSame(1, $this->events());
        $event = $DB->get_record('local_assistente_event', ['course_id' => $this->course->id]);
        $stored = json_decode($event->payload, true);
        $this->assertSame(occurrence_identity::of(event_type::GRADE_CHANGED, $payload), $stored[occurrence_identity::PAYLOAD_KEY]);
        $this->assertSame(1790000000, $stored['occurred_at'], 'The time of the occurrence is the original one');
        $this->assertSame('pending', $event->status);

        $this->assertStringContainsString('reused', $this->run_task($task));
        $direct = (new event_service())->enqueue($this->course->id, event_type::GRADE_CHANGED, $payload);
        $this->assertSame(\local_assistente\application\enqueue_status::REUSED, $direct->status, 'The delivery of always recognises it too');
        $this->assertSame(1, $this->events());
    }

    /**
     * A course that no longer has the assistant enabled is done, not a failure.
     */
    public function test_a_course_without_the_assistant_is_done_without_failure(): void {
        $off = $this->getDataGenerator()->create_course();
        (new config_repository())->create($off->id, false);
        $task = $this->scheduled(event_type::GRADE_CHANGED, ['userid' => 702, 'itemid' => 7, 'occurred_at' => 1790000000], (int) $off->id);

        $this->assertStringContainsString('skipped_not_enabled', $this->run_task($task));
        $this->assertSame(0, $this->events());
    }

    /**
     * A task that Moodle had already handed to the run, whose record the erasure of the student removed, creates nothing.
     */
    public function test_the_guard_stops_a_task_whose_record_is_gone(): void {
        $task = $this->scheduled(event_type::GRADE_CHANGED, ['userid' => 703, 'itemid' => 7, 'occurred_at' => 1790000000]);
        manager::delete_adhoc_task((int) $task->get_id());

        $this->assertStringContainsString('skipped_revoked', $this->run_task($task));
        $this->assertSame(0, $this->events());
    }

    /**
     * A busy lock: the task throws, Moodle takes the attempt and tries again. The message has the course and the type, not the student
     * nor the name of the lock (which holds the student).
     */
    public function test_a_busy_lock_throws_a_message_without_the_student(): void {
        $task = $this->scheduled(event_type::GRADE_CHANGED, ['userid' => 704123, 'itemid' => 7, 'occurred_at' => 1790000000]);
        $this->hold_lock(unit_of_work::privacy_user_resource(704123), (int) $this->course->id);

        $thrown = null;
        $log = $this->logged($this->quick($task), $thrown);

        $this->assertInstanceOf(\RuntimeException::class, $thrown);
        $this->assertStringContainsString('waiting for a lock', $thrown->getMessage());
        $this->assertStringContainsString("course {$this->course->id}, type grade_changed", $thrown->getMessage());
        $this->assertStringNotContainsString('704123', $thrown->getMessage());
        $this->assertStringNotContainsString('privacy_user', $thrown->getMessage());
        $this->assertSame('', $log, 'Not the last attempt: nothing in the log of PHP');
        $this->assertSame(0, $this->events());
        $this->assertSame(event_redelivery_scheduler::ATTEMPTS, $task->get_attempts_available(), 'Taking the attempt is for Moodle to do');
    }

    /**
     * On the last attempt the task says the event is not preserved; for an attempt of a questionnaire it also says it may not be
     * recovered automatically, and names the module and the attempt, not the student.
     */
    public function test_the_last_attempt_says_so_and_a_questionnaire_attempt_is_named(): void {
        $payload = ['cmid' => 12, 'attemptid' => 34, 'userid' => 705123];
        $task = $this->scheduled(event_type::QUIZ_ATTEMPT_SUBMITTED, $payload);
        $this->hold_lock(unit_of_work::privacy_user_resource(705123), (int) $this->course->id);
        $last = $this->quick($task);
        $last->set_attempts_available(1);

        $thrown = null;
        $log = $this->logged($last, $thrown);

        $this->assertInstanceOf(\RuntimeException::class, $thrown);
        $this->assertStringContainsString('attempts to redeliver an event ended', $log);
        $this->assertStringContainsString('NOT preserved', $log);
        $this->assertStringContainsString("course {$this->course->id}, type quiz_attempt_submitted, cmid 12, attemptid 34", $log);
        $this->assertStringContainsString('may not be recovered automatically', $log);
        $this->assertStringNotContainsString('705123', $log . $thrown->getMessage());
    }

    /**
     * A failure that another attempt would not change gives up the attempts at once and says so.
     */
    public function test_a_failure_that_is_not_a_lock_gives_up_the_attempts(): void {
        // A payload that no longer fits its type: the queue refuses it, and another attempt would refuse it again.
        $broken = new redeliver_event();
        $broken->set_custom_data(['course_id' => (int) $this->course->id, 'event_type' => 'grade_changed', 'payload' => ['userid' => 706123]]);
        $broken->set_attempts_available(8);

        $thrown = null;
        $log = $this->logged($broken, $thrown);

        $this->assertInstanceOf(\RuntimeException::class, $thrown);
        $this->assertStringContainsString('will not be tried again', $thrown->getMessage());
        $this->assertSame(0, $broken->get_attempts_available());
        $this->assertStringContainsString('NOT preserved', $log);
        $this->assertStringNotContainsString('706123', $log . $thrown->getMessage(), 'No student');
        $this->assertStringNotContainsString('itemid', $log . $thrown->getMessage(), 'No detail of the failure');
        $this->assertSame(0, $this->events());
    }

    /**
     * Data that is not a delivery is not tried again.
     */
    public function test_data_that_is_not_a_delivery_gives_up(): void {
        $task = new redeliver_event();
        $task->set_custom_data(['unexpected' => 1]);
        $task->set_attempts_available(8);

        try {
            $task->execute();
            $this->fail('A task without a delivery cannot run');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no valid data', $e->getMessage());
        }
        $this->assertSame(0, $task->get_attempts_available());
    }

    /**
     * A task that has no identifier cannot confirm that it is still wanted: the guard fails closed and nothing is created. (A task that
     * Moodle runs always has one.)
     */
    public function test_a_task_without_an_identifier_fails_closed(): void {
        $student = $this->getDataGenerator()->create_user();
        $task = new redeliver_event();
        $task->set_custom_data([
            'course_id' => (int) $this->course->id, 'event_type' => 'grade_changed',
            'payload' => ['userid' => (int) $student->id, 'itemid' => 7, 'occurred_at' => 1790000000],
        ]);
        $task->set_attempts_available(8);
        $this->assertNull($task->get_id());

        $this->assertStringContainsString('skipped_revoked', $this->run_task($task));
        $this->assertSame(0, $this->events());
    }

    /**
     * S2: the account of the student was deleted after the delivery was scheduled (Moodle deletes it right after the plugins erased the
     * data): the delivery is revoked and no event is made.
     */
    public function test_a_deleted_account_revokes_the_delivery(): void {
        $student = $this->getDataGenerator()->create_user();
        $task = $this->scheduled(event_type::GRADE_CHANGED, ['userid' => (int) $student->id, 'itemid' => 7, 'occurred_at' => 1790000000]);
        delete_user($student);

        $this->assertStringContainsString('skipped_revoked', $this->run_task($task));
        $this->assertSame(0, $this->events());
    }

    /**
     * An account that never existed is revoked the same way.
     */
    public function test_an_account_that_does_not_exist_revokes_the_delivery(): void {
        $task = $this->scheduled(event_type::GRADE_CHANGED, ['userid' => 99999999, 'itemid' => 7, 'occurred_at' => 1790000000]);

        $this->assertStringContainsString('skipped_revoked', $this->run_task($task));
        $this->assertSame(0, $this->events());
    }

    /**
     * A delivery with no student in it has no account to ask about: the event of a forum message is made.
     */
    public function test_a_delivery_without_a_student_does_not_ask_for_an_account(): void {
        $task = $this->scheduled(event_type::FORUM_POST_CREATED, ['cmid' => 4, 'discussionid' => 5, 'postid' => 6]);

        $this->assertStringContainsString('created', $this->run_task($task));
        $this->assertSame(1, $this->events());
    }

    /**
     * The order of the locks on the real path, nested: the lock of the erasure of the course is taken first, with nothing else held, and
     * the lock of the identity and the lock of the student after it, each with only the ones before it held, going up in the global order.
     * This shows the order, not the dispute (that is in the tests with independent processes).
     */
    public function test_the_delivery_takes_the_lock_of_the_course_first_and_the_others_after_it_in_order(): void {
        $student = $this->getDataGenerator()->create_user();
        $payload = ['userid' => (int) $student->id, 'itemid' => 7, 'occurred_at' => 1790000000];
        $task = $this->scheduled(event_type::GRADE_CHANGED, $payload);
        $factory = $this->record_locks();
        try {
            $output = $this->run_task($task);
        } finally {
            $this->stop_recording_locks();
        }

        $identity = occurrence_identity::lock_resource(
            (int) $this->course->id,
            event_type::GRADE_CHANGED,
            occurrence_identity::of(event_type::GRADE_CHANGED, $payload)
        );
        $expected = [
            unit_of_work::course_erasure_resource((int) $this->course->id), $identity, unit_of_work::privacy_user_resource((int) $student->id),
        ];
        $this->assertStringContainsString('created', $output);
        $this->assertSame($expected, $factory->sequence());
        $sorted = $expected;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $expected, 'Going up in the global order');
        $this->assertSame([], $factory->log[0]['held_before'], 'The lock of the course is taken with nothing else held');
        foreach ($factory->log as $position => $entry) {
            $this->assertSame(array_slice($expected, 0, $position), $entry['held_before'], 'Each lock is taken with only the earlier ones held');
        }
    }
}
