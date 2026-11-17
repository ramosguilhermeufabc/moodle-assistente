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

use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the delivery of occurrences in batches (A2; DEC-16, DEC-22): the same events, the same answers and the same identities as
 * delivering them one by one, with one read of the identities of the course for each chunk.
 *
 * Two real processes delivering at the same time are in event_service_concurrency_test.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(event_service::class)]
#[CoversClass(event_repository::class)]
final class event_service_batch_test extends \advanced_testcase {
    /**
     * Real transactions and locks are used.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
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
     * Grade occurrences of students 1..$count in the same item.
     *
     * @param int $count
     * @param int $first
     * @return array[]
     */
    private function grades(int $count, int $first = 1): array {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items['u' . ($first + $i)] = [
                'type' => event_type::GRADE_CHANGED,
                'payload' => ['userid' => $first + $i, 'itemid' => 5, 'occurred_at' => 1790000000],
            ];
        }
        return $items;
    }

    /**
     * Number of events in the course.
     *
     * @param \stdClass $course
     * @return int
     */
    private function count_events(\stdClass $course): int {
        global $DB;
        return $DB->count_records('local_assistente_event', ['course_id' => $course->id]);
    }

    /**
     * New occurrences are all created, pending, with the identity in the payload, keys and order of the input kept.
     */
    public function test_new_occurrences_are_created_with_the_keys_of_the_input(): void {
        $course = $this->enabled_course();
        $items = $this->grades(5);

        $results = (new event_service())->enqueue_many($course->id, $items, 1790000100, 2);

        $this->assertSame(array_keys($items), array_keys($results));
        foreach ($results as $result) {
            $this->assertSame(enqueue_status::CREATED, $result->status);
            $this->assertSame('pending', $result->event->status);
            $this->assertSame(1790000100, (int) $result->event->created_at);
            $this->assertArrayHasKey('identity', json_decode($result->event->payload, true));
        }
        $this->assertSame(5, $this->count_events($course));
    }

    /**
     * The batch and the single delivery recognise each other's events, in both orders, whatever the size of the chunk.
     */
    public function test_batch_and_single_delivery_give_the_same_events(): void {
        $course = $this->enabled_course();
        $items = $this->grades(6);
        $service = new event_service();

        // One by one first, then the batch: nothing new, every answer a reuse of the same event.
        $single = [];
        foreach ($items as $key => $item) {
            $single[$key] = $service->enqueue($course->id, $item['type'], $item['payload']);
        }
        $batch = $service->enqueue_many($course->id, $items, null, 4);
        $this->assertSame(6, $this->count_events($course));
        foreach ($items as $key => $unused) {
            $this->assertSame(enqueue_status::CREATED, $single[$key]->status);
            $this->assertSame(enqueue_status::REUSED, $batch[$key]->status);
            $this->assertSame((int) $single[$key]->event->id, (int) $batch[$key]->event->id);
        }

        // The batch first, then one by one.
        $other = $this->grades(6, 100);
        $batch = $service->enqueue_many($course->id, $other, null, 1000);
        foreach ($other as $key => $item) {
            $again = $service->enqueue($course->id, $item['type'], $item['payload']);
            $this->assertSame(enqueue_status::REUSED, $again->status);
            $this->assertSame((int) $batch[$key]->event->id, (int) $again->event->id);
        }
        $this->assertSame(12, $this->count_events($course));
    }

    /**
     * An occurrence already in the queue is reused, a new one is created, in the same call; different students are different events.
     */
    public function test_existing_and_new_occurrences_in_the_same_call(): void {
        $course = $this->enabled_course();
        $service = new event_service();
        $existing = $service->enqueue_many($course->id, $this->grades(3));

        $results = $service->enqueue_many($course->id, $this->grades(5));

        $this->assertSame(
            ['reused', 'reused', 'reused', 'created', 'created'],
            array_map(static fn(enqueue_result $r) => $r->status->value, array_values($results))
        );
        $this->assertSame((int) $existing['u2']->event->id, (int) $results['u2']->event->id);
        $this->assertSame(5, $this->count_events($course));
    }

    /**
     * The same occurrence twice in the call is one event: the first creates it, the repetition reuses it, also across chunks.
     */
    public function test_a_repeated_occurrence_in_the_call_is_created_once(): void {
        $course = $this->enabled_course();
        $one = $this->grades(1)['u1'];
        $items = ['a' => $one, 'b' => $this->grades(1, 2)['u2'], 'c' => $one];

        $results = (new event_service())->enqueue_many($course->id, $items, null, 1);

        $this->assertSame(enqueue_status::CREATED, $results['a']->status);
        $this->assertSame(enqueue_status::REUSED, $results['c']->status);
        $this->assertSame((int) $results['a']->event->id, (int) $results['c']->event->id);
        $this->assertSame(2, $this->count_events($course));
    }

    /**
     * The same student and item at another instant is another occurrence (identity includes occurred_at).
     */
    public function test_another_instant_is_another_occurrence(): void {
        $course = $this->enabled_course();
        $first = $this->grades(1)['u1'];
        $second = $first;
        $second['payload']['occurred_at'] += 1;

        (new event_service())->enqueue_many($course->id, ['a' => $first, 'b' => $second]);

        $this->assertSame(2, $this->count_events($course));
    }

    /**
     * A course without the assistant enabled, or without configuration, gets nothing.
     */
    public function test_a_course_not_enabled_creates_nothing(): void {
        $disabled = $this->getDataGenerator()->create_course();
        (new config_repository())->create($disabled->id, false);
        $none = $this->getDataGenerator()->create_course();

        foreach ([$disabled, $none] as $course) {
            $results = (new event_service())->enqueue_many($course->id, $this->grades(3), null, 2);
            $this->assertSame(['skipped_not_enabled'], array_values(array_unique(array_map(static fn($r) => $r->status->value, $results))));
            $this->assertSame(0, $this->count_events($course));
        }
    }

    /**
     * Types that coalesce while pending go through the single delivery: two requests are one pending event, as before.
     */
    public function test_coalescing_types_keep_their_rule(): void {
        $course = $this->enabled_course();
        $items = [
            'a' => ['type' => event_type::SYNTHESIS_REQUESTED, 'payload' => ['section_id' => 9]],
            'b' => ['type' => event_type::SYNTHESIS_REQUESTED, 'payload' => ['section_id' => 9]],
            'c' => ['type' => event_type::SYNTHESIS_REQUESTED, 'payload' => ['section_id' => 10]],
        ];

        $results = (new event_service())->enqueue_many($course->id, $items);

        $this->assertSame(
            ['created', 'reused', 'created'],
            array_map(static fn(enqueue_result $r) => $r->status->value, array_values($results))
        );
        $this->assertSame(2, $this->count_events($course));
    }

    /**
     * An invalid occurrence fails before anything is written: no partial batch.
     */
    public function test_an_invalid_payload_writes_nothing(): void {
        $course = $this->enabled_course();
        $items = $this->grades(3);
        $items['bad'] = ['type' => event_type::GRADE_CHANGED, 'payload' => ['userid' => 1]];

        try {
            (new event_service())->enqueue_many($course->id, $items, null, 1);
            $this->fail('An invalid payload must be refused');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(0, $this->count_events($course));
        }
    }

    /**
     * A chunk smaller than one is refused.
     */
    public function test_a_chunk_below_one_is_refused(): void {
        $course = $this->enabled_course();
        $this->expectException(\InvalidArgumentException::class);
        (new event_service())->enqueue_many($course->id, $this->grades(1), null, 0);
    }

    /**
     * The identities of a type in a course are read in one query, whatever the number of events, and they map to the oldest event of each.
     */
    public function test_identities_are_read_in_one_query(): void {
        global $DB;
        $course = $this->enabled_course();
        $service = new event_service();
        $created = $service->enqueue_many($course->id, $this->grades(30));
        $repository = new event_repository();

        $before = $DB->perf_get_reads();
        $map = $repository->identities_of($course->id, event_type::GRADE_CHANGED);
        $reads = $DB->perf_get_reads() - $before;

        $this->assertCount(30, $map);
        $this->assertLessThanOrEqual(1, $reads);
        $this->assertSame((int) $created['u7']->event->id, $map[json_decode($created['u7']->event->payload, true)['identity']]);
        $this->assertSame([], $repository->identities_of($course->id, event_type::MATERIAL_CHANGED));
    }
}
