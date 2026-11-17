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

namespace local_assistente\application\queue;

use local_assistente\application\course_config_service;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the queue cycle (quickstart 007 Q3 to Q12; DEC-08, DEC-09, DEC-16, DEC-22, DEC-32 D2 to D6; P-04).
 *
 * Handlers are fakes: no LLM is called. The clock is controlled by the test, so deadlines are exact. Concurrency tests use
 * one process: they show that an event whose lock is held by another run is not restarted and not duplicated. They need a lock
 * backend that is not re-entrant in one process and are skipped otherwise; two real processes and the behaviour of the lock
 * of a process that died are validated by CL-02 (T051).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queue_processor::class)]
#[CoversClass(cycle_report::class)]
#[CoversClass(cycle_outcome::class)]
final class queue_processor_test extends \advanced_testcase {
    /** @var int[] ids of the events the handler was called for, in order */
    private array $calls = [];

    /** @var array states seen by the handler: event status, processing status and whether a transaction was open */
    private array $seen = [];

    /** @var int controlled clock */
    private int $now = 1000;

    /**
     * Real transactions and locks are used.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->calls = [];
        $this->seen = [];
        $this->now = 1000;
    }

    /**
     * Called by the fake handlers.
     *
     * @param \stdClass $event
     * @param \stdClass $processing
     * @param \Closure|null $behaviour what to answer; success by default
     * @return handler_result
     * @internal
     */
    public function handled(\stdClass $event, \stdClass $processing, ?\Closure $behaviour): handler_result {
        global $DB;
        $this->calls[] = (int) $event->id;
        $this->seen[] = [$event->status, $processing->status, $DB->is_transaction_started()];
        return $behaviour ? $behaviour($event, $processing) : handler_result::success('ok');
    }

    /**
     * A fake handler.
     *
     * @param \Closure|null $behaviour
     * @return event_handler
     */
    private function handler(?\Closure $behaviour = null): event_handler {
        return new class($this, $behaviour) implements event_handler {
            /**
             * Constructor.
             *
             * @param queue_processor_test $test
             * @param \Closure|null $behaviour
             */
            public function __construct(
                /** @var queue_processor_test the test that records the calls */
                private readonly queue_processor_test $test,
                /** @var \Closure|null answer */
                private readonly ?\Closure $behaviour
            ) {
            }

            #[\Override]
            public function handle(\stdClass $event, \stdClass $processing): handler_result {
                return $this->test->handled($event, $processing, $this->behaviour);
            }
        };
    }

    /**
     * A registry with the same fake handler for the given types (the publication type by default).
     *
     * @param \Closure|null $behaviour
     * @param event_type[] $types
     * @return handler_registry
     */
    private function registry(?\Closure $behaviour = null, array $types = [event_type::QUIZ_PUBLISHED]): handler_registry {
        $registry = new handler_registry();
        foreach ($types as $type) {
            $registry->register($type, $this->handler($behaviour));
        }
        return $registry;
    }

    /**
     * A processor with the controlled clock.
     *
     * @param handler_registry $registry
     * @param course_config_service|null $config
     * @return queue_processor
     */
    private function processor(handler_registry $registry, ?course_config_service $config = null): queue_processor {
        return new queue_processor(null, $registry, $config, new unit_of_work(1), fn() => $this->now);
    }

    /**
     * A course with the assistant enabled.
     *
     * @return \stdClass
     */
    private function enabled_course(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($course->id, true);
        return $course;
    }

    /**
     * A pending publication event.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $createdat
     * @return \stdClass
     */
    private function event(int $courseid, int $cmid, int $createdat = 100): \stdClass {
        return (new event_repository())->create_event($courseid, event_type::QUIZ_PUBLISHED, ['cmid' => $cmid], $createdat);
    }

    /**
     * Current state of an event.
     *
     * @param \stdClass $event
     * @return \stdClass
     */
    private function reload(\stdClass $event): \stdClass {
        return (new event_repository())->find((int) $event->id);
    }

    /**
     * Whether the lock backend lets one process take the same lock twice (the concurrency tests then say nothing).
     *
     * @return bool
     */
    private function lock_is_reentrant(): bool {
        $factory = unit_of_work::lock_factory();
        $first = $factory->get_lock('probe_reentrancy', 1);
        $second = $first ? $factory->get_lock('probe_reentrancy', 1) : false;
        $first && $first->release();
        $second && $second->release();
        return (bool) $second;
    }

    /**
     * Q3: all pending events of enabled courses are processed in one cycle; the cycle ends because the queue is empty.
     */
    public function test_all_pending_events_are_processed_in_one_cycle(): void {
        $course = $this->enabled_course();
        $events = [$this->event($course->id, 1), $this->event($course->id, 2), $this->event($course->id, 3)];

        $report = $this->processor($this->registry())->run_cycle();

        $this->assertSame(3, $report->count(cycle_outcome::PROCESSED));
        $this->assertSame(cycle_report::STOPPED_EMPTY, $report->stopped_by());
        foreach ($events as $event) {
            $stored = $this->reload($event);
            $this->assertSame('processed', $stored->status);
            $this->assertSame(1000, (int) $stored->processed_at);
            $this->assertSame('ok', $stored->response);
        }
    }

    /**
     * An empty queue is a cycle with no work, and no handler call.
     */
    public function test_empty_queue(): void {
        $report = $this->processor($this->registry())->run_cycle();

        $this->assertSame([], $report->events());
        $this->assertSame(cycle_report::STOPPED_EMPTY, $report->stopped_by());
        $this->assertSame([], $this->calls);
        $this->assertStringContainsString('stopped_by=empty', $report->summary());
    }

    /**
     * Q12: events are processed in order of creation time, ties broken by id, whatever the order they were inserted.
     */
    public function test_events_are_processed_in_creation_order(): void {
        $course = $this->enabled_course();
        $late = $this->event($course->id, 1, 300);
        $early = $this->event($course->id, 2, 100);
        $tiea = $this->event($course->id, 3, 200);
        $tieb = $this->event($course->id, 4, 200);

        $this->processor($this->registry())->run_cycle();

        $this->assertSame([(int) $early->id, (int) $tiea->id, (int) $tieb->id, (int) $late->id], $this->calls);
    }

    /**
     * Q4 and the limit per cycle: a handler that always fails gets one attempt per cycle; exactly three attempts; back to
     * pending after the first and the second, failure after the third; then the event is not tried again.
     */
    public function test_one_attempt_per_cycle_and_three_attempts_in_total(): void {
        global $DB;
        $course = $this->enabled_course();
        $event = $this->event($course->id, 1);
        $failing = fn() => handler_result::failure(attempt_failure::TECHNICAL, 'always fails');
        $processor = $this->processor($this->registry($failing));

        $first = $processor->run_cycle();
        $this->assertSame([(int) $event->id], $this->calls, 'One attempt in the cycle, not three');
        $this->assertSame(1, $first->count(cycle_outcome::RETRY_LATER));
        $this->assertSame('pending', $this->reload($event)->status);

        $second = $processor->run_cycle();
        $this->assertSame(1, $second->count(cycle_outcome::RETRY_LATER));
        $this->assertSame('pending', $this->reload($event)->status);

        $third = $processor->run_cycle();
        $this->assertSame(1, $third->count(cycle_outcome::FAILED));
        $this->assertSame('failure', $this->reload($event)->status);

        $fourth = $processor->run_cycle();
        $this->assertSame([], $fourth->events(), 'A failed event is not tried again');
        $this->assertCount(3, $this->calls);
        $this->assertSame(3, $DB->count_records('local_assistente_processing_attempt'));
        $this->assertSame(1, $DB->count_records('local_assistente_processing'), 'The retries belong to one processing');
    }

    /**
     * The class and the reason of a failure are recorded in the attempt and in the event; an exception of the handler is a
     * technical failure; and the cycle goes on to the next event.
     */
    public function test_failures_are_recorded_with_their_class_and_do_not_stop_the_cycle(): void {
        global $DB;
        $course = $this->enabled_course();
        $limited = $this->event($course->id, 1);
        $crashing = $this->event($course->id, 2);
        $fine = $this->event($course->id, 3);
        $behaviour = function (\stdClass $event) use ($limited, $crashing): handler_result {
            if ((int) $event->id === (int) $limited->id) {
                return handler_result::failure(attempt_failure::RATE_LIMIT, 'slow down');
            }
            if ((int) $event->id === (int) $crashing->id) {
                throw new \RuntimeException('unexpected');
            }
            return handler_result::success();
        };

        $report = $this->processor($this->registry($behaviour))->run_cycle();

        $this->assertSame(2, $report->count(cycle_outcome::RETRY_LATER));
        $this->assertSame(1, $report->count(cycle_outcome::PROCESSED));
        $this->assertSame('processed', $this->reload($fine)->status);
        $attempts = $DB->get_records('local_assistente_processing_attempt', null, 'id ASC');
        $responses = array_values(array_map(static fn($a) => $a->response, array_filter($attempts, static fn($a) => $a->status === 'failure')));
        $this->assertSame('rate_limit: slow down', $responses[0]);
        $this->assertSame(attempt_failure::RATE_LIMIT, attempt_failure::from_response($responses[0]));
        $this->assertSame('technical: RuntimeException: unexpected', $responses[1]);
        $this->assertSame('rate_limit: slow down', $this->reload($limited)->response);
    }

    /**
     * Q5: running the cycle again over a processed event calls nothing and duplicates nothing.
     */
    public function test_processed_event_is_not_processed_again(): void {
        global $DB;
        $course = $this->enabled_course();
        $this->event($course->id, 1);
        $processor = $this->processor($this->registry());

        $processor->run_cycle();
        $again = $processor->run_cycle();

        $this->assertSame([], $again->events());
        $this->assertCount(1, $this->calls);
        $this->assertSame(1, $DB->count_records('local_assistente_processing_attempt'));
        $this->assertSame(1, $DB->count_records('local_assistente_processing'));
    }

    /**
     * Q8: a new legitimate occurrence of the same object is a new event with its own processing.
     */
    public function test_a_new_occurrence_has_its_own_processing(): void {
        global $DB;
        $course = $this->enabled_course();
        $repository = new event_repository();
        $post = ['cmid' => 1, 'discussionid' => 2, 'postid' => 3];
        $first = $repository->create_event($course->id, event_type::FORUM_POST_UPDATED, $post + ['occurred_at' => 1000], 100);
        $second = $repository->create_event($course->id, event_type::FORUM_POST_UPDATED, $post + ['occurred_at' => 1010], 200);

        $this->processor($this->registry(null, [event_type::FORUM_POST_UPDATED]))->run_cycle();

        $this->assertSame([(int) $first->id, (int) $second->id], $this->calls);
        $this->assertSame(2, $DB->count_records('local_assistente_processing'));
        $this->assertSame('processed', $this->reload($first)->status);
        $this->assertSame('processed', $this->reload($second)->status);
    }

    /**
     * Events of a course whose assistant is disabled, or that has no configuration, are not processed and stay pending; they
     * are processed normally when the assistant is enabled again (DEC-45 D3).
     */
    public function test_disabled_course_events_are_not_processed(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $disabled = $generator->create_course();
        $unconfigured = $generator->create_course();
        $config = new config_repository();
        $row = $config->create($disabled->id, false);
        $a = $this->event($disabled->id, 1);
        $b = $this->event($unconfigured->id, 2);
        $processor = $this->processor($this->registry());

        $report = $processor->run_cycle();

        $this->assertSame([], $report->events());
        $this->assertSame([], $this->calls);
        $this->assertSame('pending', $this->reload($a)->status);
        $this->assertSame('pending', $this->reload($b)->status);
        $this->assertSame(0, $DB->count_records('local_assistente_processing'), 'Not even an attempt was started');

        $config->set_enabled((int) $row->id, true);
        $processor->run_cycle();
        $this->assertSame([(int) $a->id], $this->calls);
        $this->assertSame('processed', $this->reload($a)->status);
        $this->assertSame('pending', $this->reload($b)->status, 'The course without configuration stays untouched');
    }

    /**
     * A course disabled between the choice of the event and its lock is detected under the lock: nothing is attempted.
     */
    public function test_course_disabled_before_the_lock_is_detected(): void {
        $course = $this->enabled_course();
        $event = $this->event($course->id, 1);
        $disabled = new class extends course_config_service {
            #[\Override]
            public function is_enabled(int $courseid): bool {
                return false;
            }
        };

        $report = $this->processor($this->registry(), $disabled)->run_cycle();

        $this->assertSame([(int) $event->id], $report->events(cycle_outcome::SKIPPED_NOT_RUNNABLE));
        $this->assertSame([], $this->calls);
        $this->assertSame('pending', $this->reload($event)->status);
    }

    /**
     * Events of a type without a handler wait: they are not attempted and not failed.
     */
    public function test_events_without_a_handler_wait(): void {
        global $DB;
        $course = $this->enabled_course();
        $closed = (new event_repository())->create_event($course->id, event_type::QUIZ_CLOSED, ['cmid' => 1, 'timeclose' => 5], 100);
        $handled = $this->event($course->id, 2, 200);

        $report = $this->processor($this->registry())->run_cycle();

        $this->assertSame([(int) $handled->id], $this->calls);
        $this->assertSame([(int) $handled->id], $report->events());
        $this->assertSame('pending', $this->reload($closed)->status);
        $this->assertSame(0, $DB->count_records('local_assistente_processing', ['event_id' => $closed->id]));
    }

    /**
     * Q10: an event that arrives during the cycle is processed in the same cycle.
     */
    public function test_event_arriving_during_the_cycle_is_processed_in_it(): void {
        $course = $this->enabled_course();
        $first = $this->event($course->id, 1, 100);
        $arrived = null;
        $behaviour = function (\stdClass $event) use ($first, $course, &$arrived): handler_result {
            if ((int) $event->id === (int) $first->id) {
                $arrived = $this->event($course->id, 2, 500);
            }
            return handler_result::success();
        };

        $report = $this->processor($this->registry($behaviour))->run_cycle();

        $this->assertNotNull($arrived);
        $this->assertSame([(int) $first->id, (int) $arrived->id], $this->calls);
        $this->assertSame(2, $report->count(cycle_outcome::PROCESSED));
    }

    /**
     * Q11: when the time of the next scheduled run arrives with events left, the event in progress is finished, the rest
     * waits for the next cycle, and the next cycle processes it.
     */
    public function test_cycle_stops_at_the_deadline_and_finishes_the_event_in_progress(): void {
        $course = $this->enabled_course();
        $events = [];
        foreach ([1, 2, 3, 4] as $n) {
            $events[$n] = $this->event($course->id, $n, $n * 10);
        }
        $slow = function (): handler_result {
            $this->now += 100; // Each event takes 100 seconds.
            return handler_result::success();
        };
        $processor = $this->processor($this->registry($slow));

        // Started at 1000, next scheduled run at 1250: events start at 1000, 1100, 1200 and the fourth would start at 1300.
        $report = $processor->run_cycle(1250);

        $this->assertSame(cycle_report::STOPPED_DEADLINE, $report->stopped_by());
        $this->assertSame(3, $report->count(cycle_outcome::PROCESSED), 'The one in progress at 1200 was finished');
        $this->assertSame('processed', $this->reload($events[3])->status);
        $this->assertSame('pending', $this->reload($events[4])->status, 'The rest waits for the next cycle');
        $this->assertSame(1300, $this->now);

        $next = $processor->run_cycle();
        $this->assertSame([(int) $events[4]->id], $next->events(cycle_outcome::PROCESSED));
    }

    /**
     * A deadline that is already past processes nothing, and says so.
     */
    public function test_deadline_already_reached_processes_nothing(): void {
        $course = $this->enabled_course();
        $event = $this->event($course->id, 1);

        $report = $this->processor($this->registry())->run_cycle(1000);

        $this->assertSame(cycle_report::STOPPED_DEADLINE, $report->stopped_by());
        $this->assertSame([], $this->calls);
        $this->assertSame('pending', $this->reload($event)->status);
    }

    /**
     * The handler runs with the event already in processing and no transaction open (nothing is held open during external work).
     */
    public function test_handler_runs_without_a_transaction_and_sees_the_event_in_processing(): void {
        $course = $this->enabled_course();
        $this->event($course->id, 1);

        $this->processor($this->registry())->run_cycle();

        $this->assertSame([['processing', 'processing', false]], $this->seen);
    }

    /**
     * The lock of the event is held while the handler runs: another run cannot take it.
     */
    public function test_event_lock_is_held_while_the_handler_runs(): void {
        if ($this->lock_is_reentrant()) {
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated in CL-02 (T051).');
        }
        $course = $this->enabled_course();
        $event = $this->event($course->id, 1);
        $acquired = null;
        $behaviour = function (\stdClass $row) use (&$acquired): handler_result {
            $lock = unit_of_work::lock_factory()
                ->get_lock(unit_of_work::event_resource((int) $row->id), 1);
            $acquired = (bool) $lock;
            $lock && $lock->release();
            return handler_result::success();
        };

        $this->processor($this->registry($behaviour))->run_cycle();

        $this->assertFalse($acquired, 'The lock must be held during the handler');
        $free = unit_of_work::lock_factory()->get_lock(unit_of_work::event_resource((int) $event->id), 1);
        $this->assertNotFalse($free, 'And released afterwards');
        $free->release();
    }

    /**
     * Q9: an event whose lock is held by another run is not restarted and nothing is duplicated; it is processed once the
     * other run lets go.
     */
    public function test_event_held_by_another_run_is_skipped(): void {
        global $DB;
        if ($this->lock_is_reentrant()) {
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated in CL-02 (T051).');
        }
        $course = $this->enabled_course();
        $event = $this->event($course->id, 1);
        $other = $this->event($course->id, 2, 200);
        $held = unit_of_work::lock_factory()->get_lock(unit_of_work::event_resource((int) $event->id), 1);
        $this->assertNotFalse($held);
        $processor = $this->processor($this->registry());

        $report = $processor->run_cycle();
        $held->release();

        $this->assertSame([(int) $event->id], $report->events(cycle_outcome::SKIPPED_LOCKED));
        $this->assertSame([(int) $other->id], $this->calls, 'Only the free event ran');
        $this->assertSame('pending', $this->reload($event)->status);
        $this->assertSame(0, $DB->count_records('local_assistente_processing', ['event_id' => $event->id]));

        $processor->run_cycle();
        $this->assertSame([(int) $other->id, (int) $event->id], $this->calls);
    }

    /**
     * Q6: two simultaneous runs process no event twice: a second run started while the first is inside the handler of an
     * event skips that event and handles the others once.
     */
    public function test_two_simultaneous_runs_process_no_event_twice(): void {
        if ($this->lock_is_reentrant()) {
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated in CL-02 (T051).');
        }
        $course = $this->enabled_course();
        $a = $this->event($course->id, 1, 100);
        $b = $this->event($course->id, 2, 200);
        $nested = null;
        $registry = null;
        $behaviour = function (\stdClass $event) use ($a, &$nested, &$registry): handler_result {
            if ((int) $event->id === (int) $a->id) {
                $nested = $this->processor($registry)->run_cycle();
            }
            return handler_result::success();
        };
        $registry = $this->registry($behaviour);

        $outer = $this->processor($registry)->run_cycle();

        $this->assertSame([(int) $a->id], $nested->events(cycle_outcome::SKIPPED_LOCKED), 'The second run cannot take the event in progress');
        $this->assertSame([(int) $b->id], $nested->events(cycle_outcome::PROCESSED));
        $this->assertSame([(int) $a->id, (int) $b->id], $this->calls, 'Each event ran once');
        $this->assertSame([(int) $a->id], $outer->events(cycle_outcome::PROCESSED));
        $this->assertSame('processed', $this->reload($a)->status);
        $this->assertSame('processed', $this->reload($b)->status);
    }

    /**
     * Q7: an interrupted run is recovered by the next cycle, on the same processing: no second processing, the interrupted
     * attempt is closed as failed and counted, and a new attempt completes it.
     */
    public function test_interrupted_run_is_recovered_on_the_same_processing(): void {
        global $DB;
        $course = $this->enabled_course();
        $event = $this->event($course->id, 1);
        $repository = new event_repository();
        $interrupted = $repository->begin_attempt((int) $event->id, 900); // The worker died after this.
        $this->assertSame('processing', $this->reload($event)->status);

        $report = $this->processor($this->registry())->run_cycle();

        $this->assertSame([(int) $event->id], $report->events(cycle_outcome::PROCESSED));
        $this->assertSame([['processing', 'processing', false]], $this->seen);
        $this->assertSame(1, $DB->count_records('local_assistente_processing'), 'No second processing');
        $attempts = $repository->find_attempts((int) $interrupted->processing_id);
        $this->assertSame([1, 2], array_map(static fn($a) => (int) $a->attempt_number, $attempts));
        $this->assertSame(['failure', 'processed'], array_map(static fn($a) => $a->status, $attempts));
        $this->assertSame('interrupted', $attempts[0]->response);
        $this->assertSame('processed', $this->reload($event)->status);
    }

    /**
     * Interruptions count toward the limit: when the three attempts were used by interrupted runs, the event goes to failure
     * without calling the handler a fourth time.
     */
    public function test_exhausted_by_interruptions_goes_to_failure_without_calling_the_handler(): void {
        global $DB;
        $course = $this->enabled_course();
        $event = $this->event($course->id, 1);
        $repository = new event_repository();
        foreach ([1, 2, 3] as $n) {
            $repository->begin_attempt((int) $event->id, 900 + $n); // Three runs, each died.
        }

        $report = $this->processor($this->registry())->run_cycle();

        $this->assertSame([(int) $event->id], $report->events(cycle_outcome::EXHAUSTED));
        $this->assertSame([], $this->calls);
        $this->assertSame('failure', $this->reload($event)->status);
        $this->assertSame(3, $DB->count_records('local_assistente_processing_attempt'));
    }

    /**
     * An event left in processing whose lock is held is another run's work in progress: it is not recovered.
     */
    public function test_event_in_processing_with_a_live_lock_is_not_recovered(): void {
        global $DB;
        if ($this->lock_is_reentrant()) {
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated in CL-02 (T051).');
        }
        $course = $this->enabled_course();
        $event = $this->event($course->id, 1);
        (new event_repository())->begin_attempt((int) $event->id, 900);
        $held = unit_of_work::lock_factory()->get_lock(unit_of_work::event_resource((int) $event->id), 1);
        $this->assertNotFalse($held);

        $report = $this->processor($this->registry())->run_cycle();
        $held->release();

        $this->assertSame([(int) $event->id], $report->events(cycle_outcome::SKIPPED_LOCKED));
        $this->assertSame([], $this->calls);
        $this->assertSame('processing', $this->reload($event)->status);
        $this->assertSame(1, $DB->count_records('local_assistente_processing_attempt'), 'The open attempt was not touched');
    }
}
