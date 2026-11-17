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

namespace local_assistente\application;

use core\task\manager;
use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\application\delivery\lock_failure_budget;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use local_assistente\task\redeliver_event;
use PHPUnit\Framework\Attributes\CoversClass;

require_once(__DIR__ . '/../fixtures/lock_holder.php');

/**
 * Tests of the redelivery of an event whose lock is busy (exception to DEC-32 D2; DEC-16, DEC-22, DEC-57).
 *
 * A busy lock is held by another PHP process (the lock of the database is re-entrant inside one), and the queue under test waits one
 * second for it, not the three of the observers or the thirty of the queue, so no test waits for long.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(event_service::class)]
#[CoversClass(event_redelivery_scheduler::class)]
#[CoversClass(lock_failure_budget::class)]
final class event_service_deferral_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_holder;

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
     * The holders end and nothing stays in the memory of the observers.
     */
    protected function tearDown(): void {
        $this->release_locks();
        \local_assistente\application\delivery\observer_event_service::reset();
        parent::tearDown();
    }

    /**
     * A queue like the one of the observers, with a short wait.
     *
     * @param lock_failure_budget|null $budget
     * @param event_redelivery_scheduler|null $scheduler
     * @return event_service
     */
    private function queue(?lock_failure_budget $budget = null, ?event_redelivery_scheduler $scheduler = null): event_service {
        return new event_service(null, null, new unit_of_work(1), $scheduler ?? new event_redelivery_scheduler($budget));
    }

    /**
     * The payload of a grade of a student.
     *
     * @param int $userid
     * @param int $item
     * @return array
     */
    private function grade(int $userid, int $item = 7): array {
        return ['userid' => $userid, 'itemid' => $item, 'occurred_at' => 1790000000];
    }

    /**
     * The retry tasks that exist.
     *
     * @return \core\task\adhoc_task[]
     */
    private function tasks(): array {
        return array_values(manager::get_adhoc_tasks(redeliver_event::class));
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
     * A delivery that finds its locks free is the delivery of always: an event, and no task.
     */
    public function test_a_free_lock_creates_the_event_and_no_task(): void {
        $result = $this->queue()->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(501));

        $this->assertSame(enqueue_status::CREATED, $result->status);
        $this->assertSame(1, $this->events());
        $this->assertSame([], $this->tasks());
    }

    /**
     * A busy lock of the student: no event now, and a task that holds the delivery, confirmed. The task has no user, 8 attempts and
     * holds identifiers only.
     */
    public function test_a_busy_lock_defers_the_delivery_to_a_confirmed_task(): void {
        $this->hold_lock(unit_of_work::privacy_user_resource(502), (int) $this->course->id);

        $result = $this->queue()->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(502));

        $this->assertSame(enqueue_status::DEFERRED, $result->status);
        $this->assertFalse($result->has_event());
        $this->assertSame(0, $this->events());
        $tasks = $this->tasks();
        $this->assertCount(1, $tasks);
        $this->assertEmpty($tasks[0]->get_userid(), 'A task of a user is refused by Moodle when the account is deleted or suspended');
        $this->assertSame(event_redelivery_scheduler::ATTEMPTS, $tasks[0]->get_attempts_available());
        $data = event_redelivery_scheduler::decode($tasks[0]->get_custom_data());
        $this->assertSame(
            ['course_id' => (int) $this->course->id, 'event_type' => 'grade_changed', 'payload' => $this->grade(502)],
            $data,
            'The original occurrence, with its occurred_at'
        );
        foreach ($data['payload'] as $value) {
            $this->assertIsInt($value, 'Identifiers and times only: no name, no text');
        }
    }

    /**
     * After one wait, the following deliveries of the process for the same student go straight to the retry; another student is not
     * affected by that lock.
     */
    public function test_the_next_deliveries_do_not_wait_and_other_students_are_not_affected(): void {
        $this->hold_lock(unit_of_work::privacy_user_resource(503), (int) $this->course->id);
        $queue = $this->queue();

        $first = $queue->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(503, 1));
        $start = microtime(true);
        $second = $queue->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(503, 2));
        $third = $queue->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(503, 3));
        $waited = microtime(true) - $start;
        $other = $queue->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(504, 1));

        $this->assertSame([enqueue_status::DEFERRED, enqueue_status::DEFERRED, enqueue_status::DEFERRED], [$first->status, $second->status, $third->status]);
        $this->assertLessThan(0.5, $waited, 'No new wait for a lock that has just failed');
        $this->assertSame(enqueue_status::CREATED, $other->status, 'The lock of one student does not touch another');
        $this->assertCount(3, $this->tasks());
        $this->assertSame(1, $this->events());
    }

    /**
     * The memory of a failed lock lasts 30 seconds and no more: a delivery after that tries the lock again.
     */
    public function test_the_memory_of_a_failed_lock_expires(): void {
        $now = 1000;
        $budget = new lock_failure_budget(function () use (&$now) {
            return $now;
        });
        $this->hold_lock(unit_of_work::privacy_user_resource(505), (int) $this->course->id);
        $queue = $this->queue($budget);
        $this->assertSame(enqueue_status::DEFERRED, $queue->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(505, 1))->status);

        $this->release_locks();
        $now += lock_failure_budget::VALIDITY - 1;
        $this->assertSame(
            enqueue_status::DEFERRED,
            $queue->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(505, 2))->status,
            'Within the 30 seconds the lock is not tried again, even if it is free now'
        );
        $now += 2;
        $this->assertSame(
            enqueue_status::CREATED,
            $queue->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(505, 3))->status,
            'After the 30 seconds it is tried again'
        );
    }

    /**
     * The same occurrence delivered twice while the lock is busy is one task.
     */
    public function test_the_same_occurrence_twice_is_one_task(): void {
        $this->hold_lock(unit_of_work::privacy_user_resource(506), (int) $this->course->id);
        $queue = $this->queue();

        $one = $queue->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(506));
        $two = $queue->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(506));

        $this->assertSame([enqueue_status::DEFERRED, enqueue_status::DEFERRED], [$one->status, $two->status]);
        $this->assertCount(1, $this->tasks());
    }

    /**
     * Only a busy lock defers: a payload that does not fit, or a course without the assistant, behave as always and schedule nothing.
     */
    public function test_only_a_busy_lock_defers(): void {
        $disabled = $this->getDataGenerator()->create_course();
        (new config_repository())->create($disabled->id, false);

        $off = $this->queue()->enqueue($disabled->id, event_type::GRADE_CHANGED, $this->grade(507));
        $this->assertSame(enqueue_status::SKIPPED_NOT_ENABLED, $off->status);
        try {
            $this->queue()->enqueue($this->course->id, event_type::GRADE_CHANGED, ['userid' => 507]);
            $this->fail('A payload that does not fit the type must be refused');
        } catch (\InvalidArgumentException) {
            $this->assertSame([], $this->tasks());
        }
    }

    /**
     * A queue without a redelivery scheduler (the queue, the handlers, the synchronization) lets a busy lock throw, as always.
     */
    public function test_without_a_scheduler_a_busy_lock_still_throws(): void {
        $this->hold_lock(unit_of_work::privacy_user_resource(508), (int) $this->course->id);
        $plain = new event_service(null, null, new unit_of_work(1));

        $thrown = null;
        try {
            $plain->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(508));
        } catch (\local_assistente\infrastructure\persistence\lock_unavailable_exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'The lock is busy: without a scheduler the exception goes up');
        $this->assertSame(unit_of_work::privacy_user_resource(508), $thrown->resource());
        $this->assertSame([], $this->tasks());
    }

    /**
     * If the task cannot be confirmed, the delivery throws: it is never answered as deferred, and nothing is preserved.
     */
    public function test_a_delivery_that_cannot_be_scheduled_is_never_deferred(): void {
        $this->hold_lock(unit_of_work::privacy_user_resource(509), (int) $this->course->id);
        $failing = new class extends event_redelivery_scheduler {
            #[\Override]
            public function defer(int $courseid, event_type $type, array $payload): int {
                throw new \RuntimeException('The redelivery of an event could not be scheduled; the event is not preserved.');
            }
        };

        // Not asserted inside the try: the failure of an assertion is itself a RuntimeException of PHPUnit and would be caught here.
        $result = null;
        $thrown = null;
        try {
            $result = $this->queue(null, $failing)->enqueue($this->course->id, event_type::GRADE_CHANGED, $this->grade(509));
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNull($result, 'A delivery that is not preserved must not be answered');
        $this->assertNotNull($thrown);
        $this->assertSame('The redelivery of an event could not be scheduled; the event is not preserved.', $thrown->getMessage());
        $this->assertSame(0, $this->events());
        $this->assertSame([], $this->tasks());
    }

    /**
     * The real scheduler, when Moodle cannot take the task, in the three ways it can fail: it throws, it never answers with an
     * identifier, and the line it leaves in the log has fixed text, the module and the attempt, and no student.
     */
    public function test_the_scheduler_that_fails_says_so_in_the_log_without_the_student(): void {
        $database = new class extends event_redelivery_scheduler {
            #[\Override]
            protected function queue_task(redeliver_event $task): int|false {
                throw new \dml_write_exception('the database is down');
            }
        };
        $neither = new class extends event_redelivery_scheduler {
            #[\Override]
            protected function queue_task(redeliver_event $task): int|false {
                return false;
            }

            #[\Override]
            protected function waiting_record(redeliver_event $task): \stdClass|false {
                return false;
            }
        };
        $payload = ['cmid' => 12, 'attemptid' => 34, 'userid' => 98765];

        foreach ([$database, $neither] as $scheduler) {
            $logfile = tempnam(sys_get_temp_dir(), 'redelivery_log_');
            $previous = ini_set('error_log', $logfile);
            try {
                $scheduler->defer((int) $this->course->id, event_type::QUIZ_ATTEMPT_SUBMITTED, $payload);
                $this->fail('The task could not have been confirmed');
            } catch (\RuntimeException $e) {
                $this->assertSame('The redelivery of an event could not be scheduled; the event is not preserved.', $e->getMessage());
            } finally {
                ini_set('error_log', (string) $previous);
            }
            $log = (string) file_get_contents($logfile);
            unlink($logfile);

            $this->assertStringContainsString('NOT preserved', $log);
            $this->assertStringContainsString('cmid 12', $log);
            $this->assertStringContainsString('attemptid 34', $log);
            $this->assertStringContainsString('may not be recovered automatically', $log, 'The reinforced warning for a questionnaire attempt');
            $this->assertStringNotContainsString('98765', $log, 'No student in the log');
            $this->assertStringNotContainsString('database is down', $log, 'No detail of the failure either');
        }
        $this->assertSame([], $this->tasks());
    }

    /**
     * An equal task that is already waiting is a confirmation, not a failure: the identifier of the one that waits.
     */
    public function test_an_equal_task_that_is_waiting_confirms_the_delivery(): void {
        $scheduler = new event_redelivery_scheduler();
        $first = $scheduler->defer($this->course->id, event_type::GRADE_CHANGED, $this->grade(510));
        $second = $scheduler->defer($this->course->id, event_type::GRADE_CHANGED, $this->grade(510));

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, $second);
        $this->assertCount(1, $this->tasks());
    }

    /**
     * The tasks of a student are found and removed, by course or in every course, and nobody else's.
     */
    public function test_the_tasks_of_a_student_are_found_and_removed(): void {
        $other = $this->getDataGenerator()->create_course();
        $scheduler = new event_redelivery_scheduler();
        $scheduler->defer($this->course->id, event_type::GRADE_CHANGED, $this->grade(601));
        $scheduler->defer($other->id, event_type::GRADE_CHANGED, $this->grade(601));
        $scheduler->defer($this->course->id, event_type::GRADE_CHANGED, $this->grade(602));
        // An event of no student has no one to belong to.
        $scheduler->defer($this->course->id, event_type::FORUM_POST_CREATED, ['cmid' => 4, 'discussionid' => 5, 'postid' => 6]);

        $this->assertCount(2, $scheduler->tasks_of(601));
        $this->assertCount(1, $scheduler->tasks_of(601, [(int) $this->course->id]));
        $this->assertSame([], $scheduler->tasks_of(603));

        $this->assertSame(1, $scheduler->remove_tasks_of(601, [(int) $this->course->id]));
        $this->assertCount(1, $scheduler->tasks_of(601), 'The other course stays');
        $this->assertSame(1, $scheduler->remove_tasks_of(601));
        $this->assertCount(2, $this->tasks(), 'The student 602 and the forum event stay');
    }
}
