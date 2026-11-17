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

use local_assistente\domain\event\event_type;
use local_assistente\domain\event\occurrence_identity;
use local_assistente\domain\event_status;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the event aggregate repository (quickstart 006 Q5; quickstart 007 Q4, Q7, Q12; DEC-09, DEC-16, DEC-22, DEC-32).
 *
 * The repository does not open transactions or locks (the caller does), so these tests cover the states and the rules.
 * Concurrency between real processes is validated by CL-02 (T051).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(event_repository::class)]
#[CoversClass(table_repository::class)]
final class event_repository_test extends \advanced_testcase {
    /**
     * A pending event of a quiz publication.
     *
     * @param event_repository $repository
     * @param int $courseid
     * @param int $cmid
     * @param int $now
     * @return \stdClass
     */
    private function publication(event_repository $repository, int $courseid, int $cmid = 1, int $now = 1000): \stdClass {
        return $repository->create_event($courseid, event_type::QUIZ_PUBLISHED, ['cmid' => $cmid], $now);
    }

    /**
     * An event is created pending, with the payload of its type and the identity inside it.
     */
    public function test_event_is_created_pending_with_identity_in_the_payload(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new event_repository();

        $event = $this->publication($repository, $course->id, 77, 1234);

        $this->assertSame('pending', $event->status);
        $this->assertSame('quiz_published', $event->event_type);
        $this->assertSame(1234, (int) $event->created_at);
        $this->assertNull($event->processed_at);
        $this->assertNull($event->response);
        $payload = json_decode($event->payload, true);
        $this->assertSame(77, $payload['cmid']);
        $this->assertSame(occurrence_identity::of(event_type::QUIZ_PUBLISHED, ['cmid' => 77]), $payload['identity']);
    }

    /**
     * A payload that does not fit the type is rejected and nothing is stored.
     */
    public function test_invalid_payload_stores_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        try {
            (new event_repository())->create_event($course->id, event_type::QUIZ_PUBLISHED, ['wrong' => 1], 1000);
            $this->fail('The payload must be rejected');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(0, $DB->count_records('local_assistente_event'));
        }
    }

    /**
     * Quickstart 006 Q5: only the four states exist; any other value is rejected.
     */
    public function test_only_the_four_states_are_accepted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new event_repository();
        $event = $this->publication($repository, $course->id);

        $this->assertSame(['pending', 'processing', 'processed', 'failure'], event_status::values());
        $this->expectException(invalid_domain_value_exception::class);
        $repository->update((object) ['id' => $event->id, 'status' => 'done']);
    }

    /**
     * A successful processing: pending, processing, processed, with the fields of the contract.
     */
    public function test_successful_lifecycle(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new event_repository();
        $event = $this->publication($repository, $course->id);

        $attempt = $repository->begin_attempt((int) $event->id, 2000);

        $this->assertSame(1, (int) $attempt->attempt_number);
        $this->assertSame('processing', $attempt->status);
        $this->assertSame('processing', $repository->find((int) $event->id)->status);
        $processing = $repository->find_processing((int) $event->id);
        $this->assertSame('processing', $processing->status);
        $this->assertNull($processing->finished_at);

        $repository->complete_attempt((int) $attempt->id, 3000, 'ok');

        $done = $repository->find((int) $event->id);
        $this->assertSame('processed', $done->status);
        $this->assertSame(3000, (int) $done->processed_at);
        $this->assertSame('ok', $done->response);
        $this->assertSame('processed', $repository->find_processing((int) $event->id)->status);
        $this->assertSame(3000, (int) $repository->find_processing((int) $event->id)->finished_at);
        $attempts = $repository->find_attempts((int) $processing->id);
        $this->assertCount(1, $attempts);
        $this->assertSame('processed', $attempts[0]->status);
        $this->assertSame(3000, (int) $attempts[0]->finished_at);
    }

    /**
     * Quickstart 007 Q4: a handler that always fails: exactly three attempts; back to pending after the first and second
     * failure, `failure` after the third; the event is final afterwards.
     */
    public function test_three_attempts_in_total(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new event_repository();
        $event = $this->publication($repository, $course->id);
        $id = (int) $event->id;

        foreach ([1 => 'pending', 2 => 'pending', 3 => 'failure'] as $number => $expected) {
            $attempt = $repository->begin_attempt($id, 2000 + $number);
            $this->assertSame($number, (int) $attempt->attempt_number);

            $state = $repository->fail_attempt((int) $attempt->id, 3000 + $number, "error {$number}");

            $this->assertSame($expected, $state->value);
            $stored = $repository->find($id);
            $this->assertSame($expected, $stored->status);
            $this->assertSame($expected, $repository->find_processing($id)->status, 'The processing mirrors the event');
            $this->assertSame("error {$number}", $stored->response);
            if ($expected === 'pending') {
                $this->assertNull($stored->processed_at, 'Not final yet');
            } else {
                $this->assertSame(3003, (int) $stored->processed_at);
            }
        }

        $processing = $repository->find_processing($id);
        $this->assertSame([1, 2, 3], array_map(static fn($a) => (int) $a->attempt_number, $repository->find_attempts((int) $processing->id)));
        $this->assertSame(['failure', 'failure', 'failure'], array_map(static fn($a) => $a->status, $repository->find_attempts((int) $processing->id)));
        $this->expectException(\DomainException::class);
        $repository->begin_attempt($id, 4000);
    }

    /**
     * The second attempt belongs to the same processing: there is one processing per event (1:1).
     */
    public function test_retries_belong_to_the_same_processing(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new event_repository();
        $event = $this->publication($repository, $course->id);

        $first = $repository->begin_attempt((int) $event->id, 2000);
        $repository->fail_attempt((int) $first->id, 2001);
        $second = $repository->begin_attempt((int) $event->id, 2002);

        $this->assertSame((int) $first->processing_id, (int) $second->processing_id);
        $this->assertSame(1, $DB->count_records('local_assistente_processing', ['event_id' => $event->id]));
        $this->assertSame('processing', $repository->find_processing((int) $event->id)->status);
    }

    /**
     * Quickstart 007 Q7: an interrupted run is recovered by the next one on the same processing: the open attempt is closed as
     * failed (and counts), and a new attempt of the same processing starts; no second processing is created.
     */
    public function test_interrupted_run_is_recovered_on_the_same_processing(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new event_repository();
        $event = $this->publication($repository, $course->id);

        $interrupted = $repository->begin_attempt((int) $event->id, 2000);
        // The worker died: the event stays in processing with an open attempt. The next run holds the lock and begins again.
        $recovered = $repository->begin_attempt((int) $event->id, 2100);

        $this->assertSame(2, (int) $recovered->attempt_number);
        $this->assertSame((int) $interrupted->processing_id, (int) $recovered->processing_id);
        $closed = $repository->find_attempts((int) $recovered->processing_id)[0];
        $this->assertSame('failure', $closed->status);
        $this->assertSame('interrupted', $closed->response);
        $this->assertSame(2100, (int) $closed->finished_at);
        $this->assertSame('processing', $repository->find((int) $event->id)->status);
        $this->assertSame(1, $DB->count_records('local_assistente_processing'));
    }

    /**
     * Interruptions count toward the limit of three (P-04): after the third interrupted attempt the event fails, without a
     * fourth attempt.
     */
    public function test_interrupted_attempts_cannot_exceed_the_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new event_repository();
        $event = $this->publication($repository, $course->id);

        foreach ([1, 2, 3] as $number) {
            $this->assertSame($number, (int) $repository->begin_attempt((int) $event->id, 2000 + $number)->attempt_number);
        }
        $fourth = $repository->begin_attempt((int) $event->id, 2100);

        $this->assertNull($fourth);
        $stored = $repository->find((int) $event->id);
        $this->assertSame('failure', $stored->status);
        $this->assertSame(2100, (int) $stored->processed_at);
        $this->assertSame(3, $DB->count_records('local_assistente_processing_attempt'));
        $this->assertSame('failure', $repository->find_processing((int) $event->id)->status);
    }

    /**
     * Quickstart 007 Q5 and Q9: a finished attempt cannot be finished again, and a final event cannot restart.
     */
    public function test_repeated_or_late_transitions_are_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new event_repository();
        $event = $this->publication($repository, $course->id);
        $attempt = $repository->begin_attempt((int) $event->id, 2000);
        $repository->complete_attempt((int) $attempt->id, 3000, 'ok');

        foreach ([
            'complete again' => fn() => $repository->complete_attempt((int) $attempt->id, 3001),
            'fail after success' => fn() => $repository->fail_attempt((int) $attempt->id, 3001),
            'begin after success' => fn() => $repository->begin_attempt((int) $event->id, 3002),
            'unknown attempt' => fn() => $repository->complete_attempt(987654, 3002),
            'unknown event' => fn() => $repository->begin_attempt(987654, 3002),
        ] as $label => $call) {
            try {
                $call();
                $this->fail("{$label} must be rejected");
            } catch (\DomainException $e) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('processed', $repository->find((int) $event->id)->status);
        $this->assertSame(1, $DB->count_records('local_assistente_processing_attempt'), 'No result or attempt duplicated');
    }

    /**
     * Quickstart 007 Q12: pending events come in creation order, ties broken by id; only enabled courses; the cycle can exclude
     * the events it already tried (DEC-32 D3, D6).
     */
    public function test_pending_events_are_ordered_and_limited_to_enabled_courses(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $enabled = $generator->create_course();
        $disabled = $generator->create_course();
        $unconfigured = $generator->create_course();
        $config = new config_repository();
        $config->create($enabled->id, true);
        $config->create($disabled->id, false);
        $repository = new event_repository();

        $late = $this->publication($repository, $enabled->id, 1, 300);
        $early = $this->publication($repository, $enabled->id, 2, 100);
        $tiea = $this->publication($repository, $enabled->id, 3, 200);
        $tieb = $this->publication($repository, $enabled->id, 4, 200);
        $this->publication($repository, $disabled->id, 5, 50);
        $this->publication($repository, $unconfigured->id, 6, 60);

        $ids = array_map(static fn($e) => (int) $e->id, $repository->find_pending_in_enabled_courses());
        $this->assertSame([(int) $early->id, (int) $tiea->id, (int) $tieb->id, (int) $late->id], $ids);

        $this->assertSame(
            [(int) $tiea->id, (int) $late->id],
            array_map(static fn($e) => (int) $e->id, $repository->find_pending_in_enabled_courses([(int) $early->id, (int) $tieb->id]))
        );
        $this->assertSame([(int) $early->id, (int) $tiea->id], array_map(static fn($e) => (int) $e->id, $repository->find_pending_in_enabled_courses([], 2)));
    }

    /**
     * Events of a disabled course stay pending, are not offered for processing, and are offered again if the assistant is
     * enabled again (plan 020, DEC-45 D3).
     */
    public function test_disabled_course_events_stay_pending_until_it_is_enabled_again(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $config = new config_repository();
        $row = $config->create($course->id, true);
        $repository = new event_repository();
        $event = $this->publication($repository, $course->id);
        $this->assertCount(1, $repository->find_pending_in_enabled_courses());

        $config->set_enabled((int) $row->id, false);
        $this->assertSame([], $repository->find_pending_in_enabled_courses());
        $this->assertSame('pending', $repository->find((int) $event->id)->status, 'Nothing is lost or changed');

        $config->set_enabled((int) $row->id, true);
        $this->assertSame([(int) $event->id], array_map(static fn($e) => (int) $e->id, $repository->find_pending_in_enabled_courses()));
    }

    /**
     * Only pending events are offered: not those in processing, processed or failed.
     */
    public function test_only_pending_events_are_offered(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($course->id, true);
        $repository = new event_repository();
        $pending = $this->publication($repository, $course->id, 1);
        $running = $this->publication($repository, $course->id, 2);
        $done = $this->publication($repository, $course->id, 3);
        $repository->begin_attempt((int) $running->id, 2000);
        $repository->complete_attempt((int) $repository->begin_attempt((int) $done->id, 2000)->id, 2001);

        $this->assertSame([(int) $pending->id], array_map(static fn($e) => (int) $e->id, $repository->find_pending_in_enabled_courses()));
    }

    /**
     * Runnable events: pending ones and those left in processing by an interrupted run, in order, of enabled courses only,
     * optionally of given types (DEC-32 D6, DEC-22). Processed and failed ones are never offered.
     */
    public function test_runnable_events_include_interrupted_ones_and_filter_by_type(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $enabled = $generator->create_course();
        $disabled = $generator->create_course();
        $config = new config_repository();
        $config->create($enabled->id, true);
        $config->create($disabled->id, false);
        $repository = new event_repository();

        $interrupted = $this->publication($repository, $enabled->id, 1, 100);
        $repository->begin_attempt((int) $interrupted->id, 150);
        $pending = $this->publication($repository, $enabled->id, 2, 200);
        $closed = $repository->create_event($enabled->id, event_type::QUIZ_CLOSED, ['cmid' => 3, 'timeclose' => 5], 250);
        $done = $this->publication($repository, $enabled->id, 4, 50);
        $repository->complete_attempt((int) $repository->begin_attempt((int) $done->id, 60)->id, 61);
        $failed = $this->publication($repository, $enabled->id, 5, 60);
        foreach ([1, 2, 3] as $n) {
            $repository->begin_attempt((int) $failed->id, 70 + $n);
        }
        $repository->begin_attempt((int) $failed->id, 80);
        $this->publication($repository, $disabled->id, 6, 10);

        $ids = static fn(array $events) => array_map(static fn($e) => (int) $e->id, $events);
        $this->assertSame(
            [(int) $interrupted->id, (int) $pending->id, (int) $closed->id],
            $ids($repository->find_runnable_in_enabled_courses())
        );
        $this->assertSame(
            [(int) $interrupted->id, (int) $pending->id],
            $ids($repository->find_runnable_in_enabled_courses([], 0, [event_type::QUIZ_PUBLISHED]))
        );
        $this->assertSame([], $repository->find_runnable_in_enabled_courses([], 0, []), 'No type means nothing');
        $this->assertSame(
            [(int) $closed->id],
            $ids($repository->find_runnable_in_enabled_courses([(int) $interrupted->id, (int) $pending->id]))
        );
        $this->assertSame([(int) $interrupted->id], $ids($repository->find_runnable_in_enabled_courses([], 1)));
    }

    /**
     * The identity of an occurrence finds the event; another course or type does not; coalescing looks only at pending ones.
     */
    public function test_find_by_identity(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $repository = new event_repository();
        $first = $this->publication($repository, $course->id, 10);
        $this->publication($repository, $course->id, 10, 2000);
        $identity = occurrence_identity::of(event_type::QUIZ_PUBLISHED, ['cmid' => 10]);

        $this->assertSame((int) $first->id, (int) $repository->find_by_identity($course->id, event_type::QUIZ_PUBLISHED, $identity)->id, 'The oldest');
        $this->assertNull($repository->find_by_identity($other->id, event_type::QUIZ_PUBLISHED, $identity));
        $this->assertNull($repository->find_by_identity($course->id, event_type::QUIZ_CLOSED, $identity));
        $this->assertNull($repository->find_by_identity($course->id, event_type::QUIZ_PUBLISHED, str_repeat('0', 40)));

        $repository->complete_attempt((int) $repository->begin_attempt((int) $first->id, 3000)->id, 3001);
        $second = $repository->find_by_identity($course->id, event_type::QUIZ_PUBLISHED, $identity, true);
        $this->assertNotSame((int) $first->id, (int) $second->id, 'The processed one is skipped when only pending are wanted');
    }

    /**
     * The context snapshot of a processing is saved once and never replaced.
     */
    public function test_context_snapshot_is_immutable(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new event_repository();
        $event = $this->publication($repository, $course->id);
        $processingid = (int) $repository->begin_attempt((int) $event->id, 2000)->processing_id;

        $this->assertNull($repository->find_context($processingid));
        $saved = $repository->save_context($processingid, '{"materials":[]}', 2001);
        $this->assertSame('{"materials":[]}', $repository->find_context($processingid)->context_json);

        try {
            $repository->save_context($processingid, '{"materials":[1]}', 2002);
            $this->fail('A second snapshot must be rejected');
        } catch (duplicate_record_exception $e) {
            $this->assertSame(1, $DB->count_records('local_assistente_processing_context'));
            $this->assertSame((int) $saved->id, (int) $repository->find_context($processingid)->id);
        }
    }
}
