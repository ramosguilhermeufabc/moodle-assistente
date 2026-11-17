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
use local_assistente\domain\event\occurrence_identity;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the event service (quickstart 006 Q8 to Q10; quickstart 022 Q4 and Q5; DEC-08, DEC-16, DEC-21, DEC-45 D3).
 *
 * The concurrency tests show the semantics inside one process (the identity lock excludes a second delivery and is
 * released). Two real concurrent PHP processes, and the lock backend of the site, are validated by CL-02 (T051).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(event_service::class)]
#[CoversClass(enqueue_result::class)]
final class event_service_test extends \advanced_testcase {
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
     * Number of events of a type in a course.
     *
     * @param int $courseid
     * @param event_type $type
     * @return int
     */
    private function count_events(int $courseid, event_type $type): int {
        global $DB;
        return $DB->count_records('local_assistente_event', ['course_id' => $courseid, 'event_type' => $type->value]);
    }

    /**
     * An occurrence creates one pending event with the identity inside the payload.
     */
    public function test_enqueue_creates_a_pending_event(): void {
        $course = $this->enabled_course();

        $result = (new event_service())->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5], 1500);

        $this->assertSame(enqueue_status::CREATED, $result->status);
        $this->assertTrue($result->has_event());
        $this->assertSame('pending', $result->event->status);
        $this->assertSame(1500, (int) $result->event->created_at);
        $this->assertSame(
            occurrence_identity::of(event_type::QUIZ_PUBLISHED, ['cmid' => 5]),
            json_decode($result->event->payload, true)['identity']
        );
    }

    /**
     * Quickstart 006 Q9: the same occurrence received twice is one event.
     */
    public function test_duplicate_delivery_is_one_event(): void {
        $course = $this->enabled_course();
        $service = new event_service();

        $first = $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);
        $second = $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);

        $this->assertSame(enqueue_status::CREATED, $first->status);
        $this->assertSame(enqueue_status::REUSED, $second->status);
        $this->assertSame((int) $first->event->id, (int) $second->event->id);
        $this->assertSame(1, $this->count_events($course->id, event_type::QUIZ_PUBLISHED));
    }

    /**
     * Quickstart 006 Q8: two legitimate edits of the same post are two events; the same edit twice is one.
     */
    public function test_legitimate_occurrences_of_the_same_object_are_distinct_events(): void {
        $course = $this->enabled_course();
        $service = new event_service();
        $post = ['cmid' => 1, 'discussionid' => 2, 'postid' => 3];

        $first = $service->enqueue($course->id, event_type::FORUM_POST_UPDATED, $post + ['occurred_at' => 1000]);
        $second = $service->enqueue($course->id, event_type::FORUM_POST_UPDATED, $post + ['occurred_at' => 1010]);
        $again = $service->enqueue($course->id, event_type::FORUM_POST_UPDATED, $post + ['occurred_at' => 1010]);

        $this->assertSame(enqueue_status::CREATED, $first->status);
        $this->assertSame(enqueue_status::CREATED, $second->status);
        $this->assertSame(enqueue_status::REUSED, $again->status);
        $this->assertSame(2, $this->count_events($course->id, event_type::FORUM_POST_UPDATED));
    }

    /**
     * Two messages are two events (DEC-03), even in the same discussion.
     */
    public function test_two_messages_are_two_events(): void {
        $course = $this->enabled_course();
        $service = new event_service();

        $service->enqueue($course->id, event_type::FORUM_POST_CREATED, ['cmid' => 1, 'discussionid' => 2, 'postid' => 10]);
        $service->enqueue($course->id, event_type::FORUM_POST_CREATED, ['cmid' => 1, 'discussionid' => 2, 'postid' => 11]);

        $this->assertSame(2, $this->count_events($course->id, event_type::FORUM_POST_CREATED));
    }

    /**
     * An occurrence that already has an event is reused whatever the state of that event: a published quiz is not
     * published again after it was processed or failed.
     */
    public function test_a_processed_event_is_still_reused_for_once_per_object_types(): void {
        $course = $this->enabled_course();
        $service = new event_service();
        $repository = new event_repository();
        $created = $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);
        $attempt = $repository->begin_attempt((int) $created->event->id, 2000);
        $repository->complete_attempt((int) $attempt->id, 2001);

        $again = $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);

        $this->assertSame(enqueue_status::REUSED, $again->status);
        $this->assertSame('processed', $again->event->status);
        $this->assertSame(1, $this->count_events($course->id, event_type::QUIZ_PUBLISHED));
    }

    /**
     * Synthesis requests coalesce while one is pending (DEC-41 D3), and a request after it ran creates a new event.
     */
    public function test_synthesis_requests_coalesce_only_while_pending(): void {
        $course = $this->enabled_course();
        $service = new event_service();
        $repository = new event_repository();

        $first = $service->enqueue($course->id, event_type::SYNTHESIS_REQUESTED, ['section_id' => 4]);
        $second = $service->enqueue($course->id, event_type::SYNTHESIS_REQUESTED, ['section_id' => 4]);
        $other = $service->enqueue($course->id, event_type::SYNTHESIS_REQUESTED, ['section_id' => 5]);
        $this->assertSame(enqueue_status::REUSED, $second->status);
        $this->assertSame((int) $first->event->id, (int) $second->event->id);
        $this->assertSame(enqueue_status::CREATED, $other->status, 'Another section is another request');

        $repository->complete_attempt((int) $repository->begin_attempt((int) $first->event->id, 2000)->id, 2001);
        $after = $service->enqueue($course->id, event_type::SYNTHESIS_REQUESTED, ['section_id' => 4]);

        $this->assertSame(enqueue_status::CREATED, $after->status);
        $this->assertNotSame((int) $first->event->id, (int) $after->event->id);
        $this->assertSame(3, $this->count_events($course->id, event_type::SYNTHESIS_REQUESTED));
    }

    /**
     * CL-47: the request of the rebuild of a forum is persisted with only the forum and its identity, and coalesces while pending.
     */
    public function test_forum_rebuild_requests_coalesce_while_pending(): void {
        global $DB;
        $course = $this->enabled_course();
        $service = new event_service();

        $first = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40]);
        $second = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40]);
        $other = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 41]);

        $this->assertSame(enqueue_status::CREATED, $first->status);
        $this->assertSame(enqueue_status::REUSED, $second->status);
        $this->assertSame((int) $first->event->id, (int) $second->event->id);
        $this->assertSame(enqueue_status::CREATED, $other->status, 'Another forum is another request');
        $this->assertSame(2, $this->count_events($course->id, event_type::FORUM_REBUILD_REQUESTED));
        $stored = $DB->get_record('local_assistente_event', ['id' => $first->event->id], '*', MUST_EXIST);
        $this->assertSame('forum_rebuild_requested', $stored->event_type);
        $this->assertSame('pending', $stored->status);
        $this->assertSame(
            ['cmid' => 40, 'identity' => occurrence_identity::of(event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40])],
            json_decode($stored->payload, true)
        );
    }

    /**
     * CL-47: after the request ran (or while it runs) a new request is a new event, because the forum may have changed since it read it.
     */
    public function test_a_forum_rebuild_request_after_the_processing_creates_a_new_event(): void {
        $course = $this->enabled_course();
        $service = new event_service();
        $repository = new event_repository();
        $first = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40]);

        $attempt = $repository->begin_attempt((int) $first->event->id, 2000);
        $during = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40]);
        $repository->complete_attempt((int) $attempt->id, 2001);
        $after = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40]);
        $again = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40]);

        $this->assertSame(enqueue_status::CREATED, $during->status, 'A request made while the first runs is not lost');
        $this->assertSame(enqueue_status::REUSED, $after->status, 'The one made during the processing is still pending');
        $this->assertSame((int) $during->event->id, (int) $after->event->id);
        $this->assertSame(enqueue_status::REUSED, $again->status);

        $repository->complete_attempt((int) $repository->begin_attempt((int) $during->event->id, 2002)->id, 2003);
        $last = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40]);
        $this->assertSame(enqueue_status::CREATED, $last->status);
        $this->assertSame(3, $this->count_events($course->id, event_type::FORUM_REBUILD_REQUESTED));
    }

    /**
     * CL-47: a pending rebuild request never swallows a deletion of the same forum, and a processed one never does either: the deletion
     * types keep their own identity (once per object).
     */
    public function test_a_pending_rebuild_request_does_not_discard_a_deletion(): void {
        $course = $this->enabled_course();
        $service = new event_service();
        $repository = new event_repository();
        $rebuild = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40]);

        $post = $service->enqueue($course->id, event_type::FORUM_POST_DELETED, ['cmid' => 40, 'discussionid' => 3, 'postid' => 9]);
        $discussion = $service->enqueue($course->id, event_type::FORUM_DISCUSSION_DELETED, ['cmid' => 40, 'discussionid' => 3]);
        $this->assertSame(enqueue_status::CREATED, $post->status);
        $this->assertSame(enqueue_status::CREATED, $discussion->status);

        $repository->complete_attempt((int) $repository->begin_attempt((int) $rebuild->event->id, 2000)->id, 2001);
        $laterpost = $service->enqueue($course->id, event_type::FORUM_POST_DELETED, ['cmid' => 40, 'discussionid' => 3, 'postid' => 10]);
        $laterdiscussion = $service->enqueue($course->id, event_type::FORUM_DISCUSSION_DELETED, ['cmid' => 40, 'discussionid' => 4]);
        $this->assertSame(enqueue_status::CREATED, $laterpost->status);
        $this->assertSame(enqueue_status::CREATED, $laterdiscussion->status);
        $this->assertSame(1, $this->count_events($course->id, event_type::FORUM_REBUILD_REQUESTED));
        $this->assertSame(2, $this->count_events($course->id, event_type::FORUM_POST_DELETED));
        $this->assertSame(2, $this->count_events($course->id, event_type::FORUM_DISCUSSION_DELETED));
    }

    /**
     * CL-47: many requests at once (enqueue_many) coalesce as the one-by-one path does, because the type goes through enqueue().
     */
    public function test_forum_rebuild_requests_coalesce_in_a_batch(): void {
        $course = $this->enabled_course();
        $service = new event_service();
        $pending = $service->enqueue($course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40]);

        $results = $service->enqueue_many($course->id, [
            ['type' => event_type::FORUM_REBUILD_REQUESTED, 'payload' => ['cmid' => 40]],
            ['type' => event_type::FORUM_REBUILD_REQUESTED, 'payload' => ['cmid' => 41]],
            ['type' => event_type::FORUM_REBUILD_REQUESTED, 'payload' => ['cmid' => 41]],
        ]);

        $this->assertSame(enqueue_status::REUSED, $results[0]->status);
        $this->assertSame((int) $pending->event->id, (int) $results[0]->event->id);
        $this->assertSame(enqueue_status::CREATED, $results[1]->status);
        $this->assertSame(enqueue_status::REUSED, $results[2]->status);
        $this->assertSame(2, $this->count_events($course->id, event_type::FORUM_REBUILD_REQUESTED));
    }

    /**
     * Quickstart 022 Q4 and DEC-45 D3: no event for a course without configuration or with the assistant disabled; and
     * events are created again once it is enabled.
     */
    public function test_no_event_for_a_course_that_is_not_enabled(): void {
        global $DB;
        $unconfigured = $this->getDataGenerator()->create_course();
        $disabled = $this->getDataGenerator()->create_course();
        $config = new config_repository();
        $row = $config->create($disabled->id, false);
        $service = new event_service();

        foreach ([$unconfigured, $disabled] as $course) {
            $result = $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);
            $this->assertSame(enqueue_status::SKIPPED_NOT_ENABLED, $result->status);
            $this->assertFalse($result->has_event());
        }
        $this->assertSame(0, $DB->count_records('local_assistente_event'));

        $config->set_enabled((int) $row->id, true);
        $this->assertSame(enqueue_status::CREATED, $service->enqueue($disabled->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5])->status);
    }

    /**
     * Isolation by course: the same object in two courses is two events, and a course never reuses another's.
     */
    public function test_events_are_isolated_by_course(): void {
        $first = $this->enabled_course();
        $second = $this->enabled_course();
        $service = new event_service();

        $a = $service->enqueue($first->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);
        $b = $service->enqueue($second->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);

        $this->assertSame(enqueue_status::CREATED, $b->status);
        $this->assertNotSame((int) $a->event->id, (int) $b->event->id);
        $this->assertSame((int) $second->id, (int) $b->event->course_id);
    }

    /**
     * A payload that does not fit the type is an error, also for a course that is not enabled.
     */
    public function test_invalid_payload_is_rejected(): void {
        global $DB;
        $enabled = $this->enabled_course();
        $disabled = $this->getDataGenerator()->create_course();
        $service = new event_service();

        foreach ([$enabled, $disabled] as $course) {
            try {
                $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['postid' => 1]);
                $this->fail('The payload must be rejected');
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, $DB->count_records('local_assistente_event'));
    }

    /**
     * Quickstart 006 Q10 (semantics): while a delivery of an occurrence holds its lock, a concurrent delivery cannot start and
     * creates nothing; once the lock is released, the occurrence is handled once.
     */
    public function test_concurrent_delivery_is_excluded_by_the_identity_lock(): void {
        $course = $this->enabled_course();
        $identity = occurrence_identity::of(event_type::QUIZ_PUBLISHED, ['cmid' => 5]);
        $resource = occurrence_identity::lock_resource($course->id, event_type::QUIZ_PUBLISHED, $identity);
        $factory = unit_of_work::lock_factory();
        $held = $factory->get_lock($resource, 1);
        $this->assertNotFalse($held);
        $service = new event_service(null, null, new unit_of_work(1));

        try {
            $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated in CL-02 (T051).');
        } catch (lock_unavailable_exception $e) {
            $this->assertSame(0, $this->count_events($course->id, event_type::QUIZ_PUBLISHED), 'The excluded delivery creates nothing');
        } finally {
            $held->release();
        }

        $this->assertSame(enqueue_status::CREATED, $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5])->status);
        $this->assertSame(enqueue_status::REUSED, $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5])->status);
        $this->assertSame(1, $this->count_events($course->id, event_type::QUIZ_PUBLISHED));
    }

    /**
     * The lock of the identity is released after the call, including a call that reuses an event.
     */
    public function test_the_identity_lock_is_released_after_enqueue(): void {
        $course = $this->enabled_course();
        $service = new event_service();
        $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);
        $service->enqueue($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 5]);
        $resource = occurrence_identity::lock_resource(
            $course->id,
            event_type::QUIZ_PUBLISHED,
            occurrence_identity::of(event_type::QUIZ_PUBLISHED, ['cmid' => 5])
        );

        $lock = unit_of_work::lock_factory()->get_lock($resource, 1);

        $this->assertNotFalse($lock, 'The lock must be free after enqueue');
        $lock->release();
    }

    /**
     * The observer path never runs the LLM: the service and the observers do not refer to it.
     */
    public function test_enqueue_path_does_not_refer_to_the_llm(): void {
        global $CFG;
        $files = array_merge(
            [$CFG->dirroot . '/local/assistente/classes/application/event_service.php'],
            glob($CFG->dirroot . '/local/assistente/classes/observer/*.php') ?: []
        );
        foreach ($files as $file) {
            $code = '';
            foreach (token_get_all(file_get_contents($file)) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }
            $this->assertDoesNotMatchRegularExpression('/llm/i', $code, basename($file));
        }
    }
}
