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


namespace local_assistente\application\privacy;

use local_assistente\application\event_service;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\erased_subject;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\infrastructure\persistence\performance_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the part of the erasure of a student that concerns the events (T104; DEC-57, lacuna 1). Real events, handlers, queue and locks.
 * Where the test says "another process" it holds the lock itself through the shared factory of the unit of work, which refuses a second
 * acquisition of the same resource in a process just as a second process would (the same technique as queue_processor_test).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(student_event_eraser::class)]
#[CoversClass(\local_assistente\infrastructure\persistence\student_data_repository::class)]
final class student_event_eraser_test extends \advanced_testcase {
    /** @var int counter that keeps the occurrences distinct */
    private static int $occurred = 20000;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass the student whose data are erased */
    private \stdClass $student;

    /** @var \stdClass another student of the same course */
    private \stdClass $other;

    /** @var event_repository */
    private event_repository $events;

    /** @var performance_repository */
    private performance_repository $performance;

    /** @var int[] a real activity of each course, by course id */
    private array $activity = [];

    /**
     * A course with the assistant and criteria, two students, and the lock backend checked.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $factory = unit_of_work::lock_factory();
        $first = $factory->get_lock('probe_reentrancy', 1);
        $second = $first ? $factory->get_lock('probe_reentrancy', 1) : false;
        $first && $first->release();
        $second && $second->release();
        if ($second) {
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated with separate processes (CL-02).');
        }
        $this->course = $this->getDataGenerator()->create_course();
        $configs = new config_repository();
        $config = $configs->create($this->course->id, true);
        $configs->insert_criteria((int) $config->id, 60, 1, 40);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->events = new event_repository();
        $this->performance = new performance_repository();
        $this->activity[(int) $this->course->id] = (int) $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id])->cmid;
    }

    /**
     * A real activity of a course.
     *
     * @param \stdClass $course
     * @return int
     */
    private function activity_of(\stdClass $course): int {
        return $this->activity[(int) $course->id] ??= (int) $this->getDataGenerator()->create_module('assign', ['course' => $course->id])->cmid;
    }

    /**
     * An eraser with a short wait for locks.
     *
     * @param \Closure|null $afterreading
     * @return student_event_eraser
     */
    private function eraser(?\Closure $afterreading = null): student_event_eraser {
        return new student_event_eraser(null, new unit_of_work(1), $afterreading);
    }

    /**
     * A grade event of a student in the given state, with the performance analysis of a processed one.
     *
     * @param \stdClass $user
     * @param string $state pending, processing (interrupted), processed or failure
     * @param \stdClass|null $course
     * @return array [event id, analysis id or null]
     */
    private function grade_event(\stdClass $user, string $state, ?\stdClass $course = null): array {
        $course ??= $this->course;
        $event = $this->events->create_event((int) $course->id, event_type::GRADE_CHANGED, [
            'userid' => (int) $user->id, 'itemid' => 9, 'occurred_at' => ++self::$occurred,
        ], time());
        $analysis = null;
        if ($state !== 'pending') {
            $attempt = $this->events->begin_attempt((int) $event->id, time());
            if ($state === 'processed') {
                $processing = $this->events->find_processing((int) $event->id);
                $analysis = $this->performance->save((int) $course->id, (int) $processing->id, (int) $user->id, [['activity_id' => $this->activity_of($course), 'grade' => 70.0]]);
                $this->events->complete_attempt((int) $attempt->id, time(), 'ok');
            } else if ($state === 'failure') {
                $this->events->fail_attempt((int) $attempt->id, time(), 'technical: x');
                for ($i = 0; $i < 2; $i++) {
                    $again = $this->events->begin_attempt((int) $event->id, time());
                    $this->events->fail_attempt((int) $again->id, time(), 'technical: x');
                }
            }
        }
        return [(int) $event->id, $analysis];
    }

    /**
     * The decoded payload of an event.
     *
     * @param int $eventid
     * @return array
     */
    private function payload(int $eventid): array {
        global $DB;
        return json_decode($DB->get_field('local_assistente_event', 'payload', ['id' => $eventid]), true);
    }

    /**
     * Counts of the individual results of a student.
     *
     * @param \stdClass $user
     * @return int
     */
    private function results_of(\stdClass $user): int {
        global $DB;
        return $DB->count_records('local_assistente_performance', ['student_id' => $user->id])
            + $DB->count_records('local_assistente_frequency', ['student_id' => $user->id])
            + $DB->count_records('local_assistente_criteria_result', ['student_id' => $user->id]);
    }

    /**
     * A snapshot of every event of the course, to tell later that nothing changed.
     *
     * @return string
     */
    private function snapshot(): string {
        global $DB;
        return md5(json_encode([
            array_values($DB->get_records('local_assistente_event', null, 'id ASC')),
            array_values($DB->get_records('local_assistente_performance', null, 'id ASC')),
        ]));
    }

    /**
     * Final events are only neutralized: the same ids, statuses, attempts and analyses stay, the payload has no student, and the individual
     * results go.
     */
    public function test_final_events_are_neutralized_and_the_history_stays(): void {
        global $DB;
        [$processed, $analysis] = $this->grade_event($this->student, 'processed');
        [$failed] = $this->grade_event($this->student, 'failure');
        $before = $this->payload($processed);
        $attempts = $DB->count_records('local_assistente_processing_attempt');

        $result = $this->eraser()->erase((int) $this->student->id);

        $after = $this->payload($processed);
        $this->assertSame(0, $after['userid']);
        $this->assertSame(erased_subject::IDENTITY, $after['identity']);
        $this->assertNotSame($before['identity'], $after['identity']);
        $this->assertSame([$before['itemid'], $before['occurred_at']], [$after['itemid'], $after['occurred_at']], 'The rest of the payload stays');
        $this->assertSame(0, $this->payload($failed)['userid']);
        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $processed]));
        $this->assertSame('failure', $DB->get_field('local_assistente_event', 'status', ['id' => $failed]));
        $this->assertTrue($DB->record_exists('local_assistente_analysis', ['id' => $analysis]), 'The analysis stays');
        $this->assertSame($attempts, $DB->count_records('local_assistente_processing_attempt'));
        $this->assertSame(0, $this->results_of($this->student));
        $this->assertSame([2, 0, 1], [$result['events'], $result['not_final'], $result['rows']['local_assistente_performance']]);
    }

    /**
     * Pending events are neutralized and the queue finishes them as events of an erased subject: processed, with the response of the erasure,
     * and nothing individual is made again even though the student is still enrolled.
     */
    public function test_pending_events_are_neutralized_and_the_queue_finishes_them_without_results(): void {
        global $DB;
        [$grade] = $this->grade_event($this->student, 'pending');
        $service = new event_service();
        $criteria = $service->enqueue((int) $this->course->id, event_type::CRITERIA_CHECK_REQUESTED, ['userid' => (int) $this->student->id]);
        $participation = $service->enqueue((int) $this->course->id, event_type::PARTICIPATION_CHANGED, [
            'cmid' => 1, 'userid' => (int) $this->student->id, 'source' => 'grade', 'occurred_at' => ++self::$occurred,
        ]);
        $analyses = $DB->count_records('local_assistente_analysis');

        $result = $this->eraser()->erase((int) $this->student->id);
        (new queue_processor(null, handler_registry::default()))->run_cycle();

        $this->assertSame(3, $result['not_final']);
        foreach ([$grade, (int) $criteria->event->id, (int) $participation->event->id] as $id) {
            $this->assertSame(0, $this->payload($id)['userid']);
            $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $id]), "event {$id}");
            $processing = $this->events->find_processing($id);
            $attempts = $this->events->find_attempts((int) $processing->id);
            $this->assertCount(1, $attempts);
            $this->assertSame(erased_subject::RESPONSE, $attempts[0]->response);
        }
        $this->assertSame(0, $this->results_of($this->student), 'Nothing individual is made again');
        $this->assertSame($analyses, $DB->count_records('local_assistente_analysis'), 'And no analysis');
    }

    /**
     * An event left in `processing` by a run that died is neutralized like a pending one, and the queue recovers it on the same processing.
     */
    public function test_an_interrupted_event_is_neutralized_and_recovered_by_the_queue(): void {
        global $DB;
        [$id] = $this->grade_event($this->student, 'processing');
        $this->assertSame('processing', $DB->get_field('local_assistente_event', 'status', ['id' => $id]));

        $this->eraser()->erase((int) $this->student->id);
        (new queue_processor(null, handler_registry::default()))->run_cycle();

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $id]));
        $processing = $this->events->find_processing($id);
        $attempts = $this->events->find_attempts((int) $processing->id);
        $this->assertSame(['failure', 'processed'], array_map(fn($a) => $a->status, $attempts), 'The interrupted attempt is closed, the next one finishes');
        $this->assertSame(erased_subject::RESPONSE, $attempts[1]->response);
        $this->assertSame(0, $this->results_of($this->student));
    }

    /**
     * A handler that is running holds the lock of its event: the request fails with nothing changed, and works when repeated.
     */
    public function test_a_running_handler_makes_the_request_fail_without_changes_and_it_can_be_repeated(): void {
        [$running] = $this->grade_event($this->student, 'pending');
        [$processed] = $this->grade_event($this->student, 'processed');
        $held = unit_of_work::lock_factory()->get_lock(unit_of_work::event_resource($running), 1);
        $this->assertNotFalse($held);
        $before = $this->snapshot();

        try {
            $this->eraser()->erase((int) $this->student->id);
            $this->fail('The erasure went on over a locked event');
        } catch (lock_unavailable_exception) {
            $this->assertSame($before, $this->snapshot(), 'Nothing changed: no event, no result');
        } finally {
            $held->release();
        }

        $this->eraser()->erase((int) $this->student->id);
        $this->assertSame(0, $this->payload($running)['userid']);
        $this->assertSame(0, $this->payload($processed)['userid']);
        $this->assertSame(0, $this->results_of($this->student));
    }

    /**
     * The handler ends while the request waits for its lock: the results it wrote a moment ago are gone with the rest, in the transaction of
     * the erasure, and its event is neutralized.
     */
    public function test_a_handler_that_ends_while_the_request_waits_has_its_results_removed(): void {
        global $DB;
        [$id] = $this->grade_event($this->student, 'pending');
        $attempt = $this->events->begin_attempt($id, time());
        $held = unit_of_work::lock_factory()->get_lock(unit_of_work::event_resource($id), 1);
        $this->assertNotFalse($held);
        $finish = function (int $round) use ($id, $attempt, $held, &$finished) {
            if ($finished) {
                return;
            }
            $finished = true;
            // The handler ends while the request is about to ask for the lock: it runs under the lock of its event, as the queue runs it, writes
            // its result, completes the attempt and lets go.
            $held->release();
            (new unit_of_work(1))->run_locked(unit_of_work::event_resource($id), function () use ($id, $attempt) {
                $processing = $this->events->find_processing($id);
                $this->performance->save((int) $this->course->id, (int) $processing->id, (int) $this->student->id, [['activity_id' => $this->activity_of($this->course), 'grade' => 55.0]]);
                $this->events->complete_attempt((int) $attempt->id, time(), 'ok');
            });
        };

        $result = $this->eraser($finish)->erase((int) $this->student->id);

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $id]));
        $this->assertSame(0, $this->payload($id)['userid']);
        $this->assertSame(0, $this->results_of($this->student), 'The result written by the handler was removed');
        $this->assertSame(1, $result['rows']['local_assistente_performance']);
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['processing_id' => $this->events->find_processing($id)->id]), 'The analysis stays');
    }

    /**
     * An event born between the first reading and the locks is not in the set of locks: the transaction finds it, the attempt is undone and
     * made again with it, and in the end it is neutralized too.
     */
    public function test_an_event_born_between_the_reading_and_the_locks_is_detected(): void {
        [$first] = $this->grade_event($this->student, 'pending');
        $born = null;
        $afterreading = function (int $round) use (&$born) {
            if ($round === 1) {
                $born = (new event_service())->enqueue((int) $this->course->id, event_type::CRITERIA_CHECK_REQUESTED, ['userid' => (int) $this->student->id]);
            }
        };

        $result = $this->eraser($afterreading)->erase((int) $this->student->id);

        $this->assertSame(2, $result['rounds'], 'The second round had the new event in the set');
        $this->assertSame(0, $this->payload($first)['userid']);
        $this->assertSame(0, $this->payload((int) $born->event->id)['userid']);
        $this->assertSame(erased_subject::IDENTITY, $this->payload((int) $born->event->id)['identity']);
    }

    /**
     * The rounds are bounded: events that never stop appearing make the request fail with nothing changed.
     */
    public function test_the_rounds_are_bounded(): void {
        $this->grade_event($this->student, 'pending');
        $before = $this->snapshot();
        $afterreading = function (int $round) {
            $this->grade_event($this->student, 'pending');
        };

        try {
            $this->eraser($afterreading)->erase((int) $this->student->id);
            $this->fail('The erasure did not give up');
        } catch (student_event_stabilisation_exception $e) {
            $this->assertStringContainsString((string) student_event_eraser::MAX_ATTEMPTS, $e->getMessage());
        }
        $this->assertSame(
            student_event_eraser::MAX_ATTEMPTS + 1,
            count((new \local_assistente\infrastructure\persistence\student_data_repository())->find_events((int) $this->student->id)),
            'Nothing was neutralized: every event of the student still has the student'
        );
        $this->assertNotSame($before, $this->snapshot(), 'Only the events the test itself made differ');
    }

    /**
     * While the erasure runs the lock of the student and the lock of each event that is not final are held, so the creation of an event of the
     * student (event_service needs the first) and the start of one of its events (the queue needs the second) are refused. Inside the
     * transaction a new lock cannot be taken in this process, so the proof is that those locks cannot be taken by anyone else.
     */
    public function test_the_locks_that_forbid_creating_or_starting_events_are_held_during_the_erasure(): void {
        [$pending] = $this->grade_event($this->student, 'pending');
        [$interrupted] = $this->grade_event($this->student, 'processing');
        [$processed] = $this->grade_event($this->student, 'processed');
        $free = [];
        $within = function (int $userid) use (&$free, $pending, $interrupted, $processed) {
            foreach ([
                'user' => unit_of_work::privacy_user_resource($userid),
                'pending' => unit_of_work::event_resource($pending),
                'interrupted' => unit_of_work::event_resource($interrupted),
                'processed' => unit_of_work::event_resource($processed),
            ] as $name => $resource) {
                $lock = unit_of_work::lock_factory()->get_lock($resource, 1);
                $free[$name] = (bool) $lock;
                $lock && $lock->release();
            }
        };

        $this->eraser()->erase((int) $this->student->id, $within);

        $this->assertSame(['user' => false, 'pending' => false, 'interrupted' => false, 'processed' => true], $free, 'Final events need no lock: nothing can start them');
        // And once it ends everything is free again.
        $lock = unit_of_work::lock_factory()->get_lock(unit_of_work::privacy_user_resource((int) $this->student->id), 1);
        $this->assertNotFalse($lock);
        $lock->release();
    }

    /**
     * With the lock of the student held, as it is during the erasure, the creation of an event of that student is refused and nothing is born;
     * the events of other students and the events that have no student are not held back; with the lock released the event is created.
     */
    public function test_an_event_of_the_student_cannot_be_created_while_the_erasure_holds_the_lock(): void {
        global $DB;
        $held = unit_of_work::lock_factory()->get_lock(unit_of_work::privacy_user_resource((int) $this->student->id), 1);
        $this->assertNotFalse($held);
        $service = new event_service();
        $before = $DB->count_records('local_assistente_event');

        try {
            $service->enqueue((int) $this->course->id, event_type::CRITERIA_CHECK_REQUESTED, ['userid' => (int) $this->student->id]);
            $this->fail('An event of the student was created while the erasure held the lock');
        } catch (lock_unavailable_exception $e) {
            $this->assertStringContainsString('privacy_user_' . $this->student->id, $e->getMessage());
        }
        $this->assertSame($before, $DB->count_records('local_assistente_event'), 'Nothing was born');
        $other = $service->enqueue((int) $this->course->id, event_type::CRITERIA_CHECK_REQUESTED, ['userid' => (int) $this->other->id]);
        $section = $service->enqueue((int) $this->course->id, event_type::SYNTHESIS_REQUESTED, ['section_id' => 1]);
        $held->release();

        $this->assertSame(\local_assistente\application\enqueue_status::CREATED, $other->status);
        $this->assertSame(\local_assistente\application\enqueue_status::CREATED, $section->status);
        $later = $service->enqueue((int) $this->course->id, event_type::CRITERIA_CHECK_REQUESTED, ['userid' => (int) $this->student->id]);
        $this->assertSame((int) $this->student->id, $this->payload((int) $later->event->id)['userid']);
        $this->assertNotSame(erased_subject::IDENTITY, $this->payload((int) $later->event->id)['identity']);
    }

    /**
     * An event that is held by a running handler cannot be started by the queue while the erasure holds its lock: the queue skips it, as it
     * does for any event whose lock is taken, and it is processed afterwards as an event of an erased subject.
     */
    public function test_the_queue_skips_an_event_that_the_erasure_holds(): void {
        global $DB;
        [$pending] = $this->grade_event($this->student, 'pending');
        $held = unit_of_work::lock_factory()->get_lock(unit_of_work::event_resource($pending), 1);
        $this->assertNotFalse($held);

        $report = (new queue_processor(null, handler_registry::default()))->run_cycle();
        $held->release();

        $this->assertSame([$pending], $report->events(\local_assistente\application\queue\cycle_outcome::SKIPPED_LOCKED));
        $this->assertSame('pending', $DB->get_field('local_assistente_event', 'status', ['id' => $pending]));
    }
    /**
     * The attempt of a quiz: only the userid is neutralized, the identity (derived from the attempt, not from the user) is kept.
     */
    public function test_a_quiz_attempt_event_loses_only_the_userid(): void {
        $event = $this->events->create_event((int) $this->course->id, event_type::QUIZ_ATTEMPT_SUBMITTED, [
            'cmid' => 5, 'attemptid' => 77, 'userid' => (int) $this->student->id,
        ], time());
        $before = $this->payload((int) $event->id);

        $this->eraser()->erase((int) $this->student->id);

        $after = $this->payload((int) $event->id);
        $this->assertSame(0, $after['userid']);
        $this->assertSame($before['identity'], $after['identity']);
        $this->assertSame([5, 77], [$after['cmid'], $after['attemptid']]);
    }

    /**
     * The same request again changes nothing, and so does a request for a student with nothing.
     */
    public function test_repeating_the_erasure_changes_nothing(): void {
        $this->grade_event($this->student, 'processed');
        $this->grade_event($this->student, 'pending');
        $first = $this->eraser()->erase((int) $this->student->id);
        $after = $this->snapshot();

        $again = $this->eraser()->erase((int) $this->student->id);
        $nobody = $this->eraser()->erase((int) $this->getDataGenerator()->create_user()->id);

        $this->assertSame(2, $first['events']);
        $this->assertSame([0, 0, 1], [$again['events'], $again['not_final'], $again['rounds']]);
        $this->assertSame([0, 0], [$nobody['events'], array_sum($nobody['rows'])]);
        $this->assertSame($after, $this->snapshot());
    }

    /**
     * A new request for the criteria of the student after the erasure is a new event with the student, not merged with the neutralized one.
     */
    public function test_a_new_request_after_the_erasure_creates_a_new_event(): void {
        $service = new event_service();
        $old = $service->enqueue((int) $this->course->id, event_type::CRITERIA_CHECK_REQUESTED, ['userid' => (int) $this->student->id]);
        $this->eraser()->erase((int) $this->student->id);

        $new = $service->enqueue((int) $this->course->id, event_type::CRITERIA_CHECK_REQUESTED, ['userid' => (int) $this->student->id]);

        $this->assertSame(\local_assistente\application\enqueue_status::CREATED, $new->status);
        $this->assertNotSame((int) $old->event->id, (int) $new->event->id);
        $this->assertSame(0, $this->payload((int) $old->event->id)['userid']);
        $this->assertSame((int) $this->student->id, $this->payload((int) $new->event->id)['userid']);
    }

    /**
     * A failure in the other steps of the erasure undoes everything: events, results and all.
     */
    public function test_a_failure_in_the_other_steps_undoes_everything(): void {
        $this->grade_event($this->student, 'processed');
        $this->grade_event($this->student, 'pending');
        $before = $this->snapshot();

        try {
            $this->eraser()->erase((int) $this->student->id, function (int $userid) {
                throw new \RuntimeException('the next step failed');
            });
            $this->fail('The failure did not reach the caller');
        } catch (\RuntimeException $e) {
            $this->assertSame('the next step failed', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
        $this->eraser()->erase((int) $this->student->id);
        $this->assertSame(0, $this->results_of($this->student));
    }

    /**
     * Other students are untouched, in the same course and in another one; the student is erased in every course.
     */
    public function test_other_students_are_untouched_and_the_student_is_erased_in_every_course(): void {
        global $DB;
        $second = $this->getDataGenerator()->create_course();
        (new config_repository())->create($second->id, true);
        [$mine1] = $this->grade_event($this->student, 'processed');
        [$mine2] = $this->grade_event($this->student, 'processed', $second);
        [$theirs] = $this->grade_event($this->other, 'processed');
        $keep = $this->payload($theirs);

        $this->eraser()->erase((int) $this->student->id);

        $this->assertSame([0, 0], [$this->payload($mine1)['userid'], $this->payload($mine2)['userid']]);
        $this->assertSame($keep, $this->payload($theirs));
        $this->assertSame(0, $this->results_of($this->student));
        $this->assertSame(1, $this->results_of($this->other));
        $this->assertSame(1, $DB->count_records('local_assistente_performance'), 'Only the other student keeps a result');
    }

    /**
     * Invalid students are refused.
     */
    public function test_an_invalid_student_is_refused(): void {
        foreach ([0, -1] as $id) {
            try {
                $this->eraser()->erase($id);
                $this->fail("{$id} was accepted");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
