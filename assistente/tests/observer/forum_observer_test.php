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

namespace local_assistente\observer;

use local_assistente\application\forum\forum_analysis_handler;
use local_assistente\application\forum\forum_event_service;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the forum observer (T083; quickstart 006 Q3 and Q8; DEC-03, DEC-38), through the real forum API of Moodle 5.2.4 and the real
 * queue.
 *
 * Emission validated by running the real functions of mod_forum: forum_add_discussion and forum_add_new_post (through the generator),
 * forum_trigger_post_updated_event (the function that forum_update_post calls), forum_delete_post and forum_delete_discussion. What the
 * pages of the forum emit when a user edits or deletes is for CL-35 (T084).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(forum_observer::class)]
#[CoversClass(forum_event_service::class)]
final class forum_observer_test extends \advanced_testcase {
    /** @var string[] short names of the forum events recorded by the current action */
    private static array $recorded = [];

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass forum with cmid */
    private \stdClass $forum;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \mod_forum_generator */
    private $generator;

    /**
     * Real transactions and locks are used by the queue. A course with the assistant, a student and a forum.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($this->course->id, true);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        foreach ($observers as $i => $observer) {
            $observers[$i] += ['includefile' => null, 'priority' => 0];
        }
        $observers[] = [
            'eventname' => '*',
            'callback' => 'local_assistente\observer\forum_observer_test::record',
            'includefile' => null,
            'priority' => 0,
            'internal' => false,
        ];
        \core\event\manager::phpunit_replace_observers($observers);
        self::$recorded = [];
    }

    /**
     * Observer that records the forum events next to the observers of the plugin.
     *
     * @param \core\event\base $event
     */
    public static function record(\core\event\base $event): void {
        if (str_starts_with($event::class, 'mod_forum\\event\\')) {
            self::$recorded[] = (new \ReflectionClass($event))->getShortName();
        }
    }

    /**
     * Queued forum events of a type, as payloads.
     *
     * @param string $type
     * @return array[]
     */
    private function queued(string $type): array {
        global $DB;
        return array_map(
            static fn($e) => json_decode($e->payload, true),
            array_values($DB->get_records('local_assistente_event', ['event_type' => $type], 'id ASC'))
        );
    }

    /**
     * A discussion with its first message.
     *
     * @return \stdClass id and post
     */
    private function discussion(): \stdClass {
        $record = $this->generator->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $this->student->id,
        ]);
        return (object) ['id' => (int) $record->id, 'post' => (int) $record->firstpost];
    }

    /**
     * Add a reply.
     *
     * @param \stdClass $discussion
     * @return int post id
     */
    private function reply(\stdClass $discussion): int {
        return (int) $this->generator->create_post([
            'discussion' => $discussion->id, 'parent' => $discussion->post, 'userid' => $this->student->id,
        ])->id;
    }

    /**
     * Emit post_created as post.php does when a user replies (SYNTHETIC: the page is not run; the generator and forum_add_new_post
     * emit no event).
     *
     * @param int $postid
     * @param int $discussionid
     */
    private function emit_post_created(int $postid, int $discussionid): void {
        \mod_forum\event\post_created::create([
            'context' => \context_module::instance($this->forum->cmid),
            'objectid' => $postid,
            'other' => ['discussionid' => $discussionid, 'forumid' => $this->forum->id, 'forumtype' => 'general'],
        ])->trigger();
    }

    /**
     * Emit discussion_created as post.php does when a user starts a discussion (SYNTHETIC).
     *
     * @param int $discussionid
     */
    private function emit_discussion_created(int $discussionid): void {
        \mod_forum\event\discussion_created::create([
            'context' => \context_module::instance($this->forum->cmid),
            'objectid' => $discussionid,
            'other' => ['forumid' => $this->forum->id],
        ])->trigger();
    }

    /**
     * Moodle objects needed by the forum functions.
     *
     * @return array course, course module and forum records
     */
    private function objects(): array {
        global $DB;
        return [
            $this->course,
            get_coursemodule_from_id('forum', $this->forum->cmid, 0, false, MUST_EXIST),
            $DB->get_record('forum', ['id' => $this->forum->id], '*', MUST_EXIST),
        ];
    }

    /**
     * The observers are registered after the commit, with callbacks that exist.
     */
    public function test_observers_are_registered_after_the_commit(): void {
        global $CFG;
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        $byname = array_column($observers, null, 'eventname');

        foreach (['discussion_created', 'post_created', 'post_updated', 'post_deleted', 'discussion_deleted'] as $name) {
            $full = '\mod_forum\event\\' . $name;
            $this->assertArrayHasKey($full, $byname);
            $this->assertTrue(is_callable($byname[$full]['callback']), $full);
            $this->assertFalse($byname[$full]['internal'], $full);
            $this->assertTrue(class_exists(ltrim($full, '\\')));
        }
    }

    /**
     * FINDING, VALIDATED with the real API: creating a discussion and a reply with the generator, which calls forum_add_discussion and
     * forum_add_new_post, emits no post_created and no discussion_created; those events come from the scripts of the forum.
     */
    public function test_the_forum_api_emits_no_creation_event(): void {
        $discussion = $this->discussion();
        $this->reply($discussion);

        $this->assertSame([], array_filter(self::$recorded, static fn($n) => in_array($n, ['post_created', 'discussion_created'], true)));
        $this->assertSame([], $this->queued('forum_post_created'));
    }

    /**
     * Quickstart 006 Q3: two messages give two events with the course, the forum module, the discussion and the message taken from the
     * event: the first message of a discussion through discussion_created (found by its first post) and a reply through post_created.
     * The two events are emitted as post.php emits them (SYNTHETIC).
     */
    public function test_two_messages_give_two_events(): void {
        $discussion = $this->discussion();
        $reply = $this->reply($discussion);

        $this->emit_discussion_created($discussion->id);
        $this->emit_post_created($reply, $discussion->id);
        $this->emit_post_created($reply, $discussion->id);

        $events = $this->queued('forum_post_created');
        $this->assertCount(2, $events, 'The repeated event of the same message is one occurrence');
        $this->assertSame([$discussion->post, $reply], array_column($events, 'postid'));
        $this->assertSame([(int) $this->forum->cmid], array_values(array_unique(array_column($events, 'cmid'))));
        $this->assertSame([$discussion->id], array_values(array_unique(array_column($events, 'discussionid'))));
    }
    /**
     * Quickstart 006 Q8: two legitimate edits of the same message, in different moments, give two events; the same moment is one
     * occurrence. The event is emitted by forum_trigger_post_updated_event, the function that forum_update_post calls.
     */
    public function test_two_edits_of_the_same_message_give_two_events(): void {
        global $DB;
        $discussion = $this->discussion();
        $post = $DB->get_record('forum_posts', ['id' => $discussion->post], '*', MUST_EXIST);
        $row = $DB->get_record('forum_discussions', ['id' => $discussion->id], '*', MUST_EXIST);
        [, , $forum] = $this->objects();
        $edit = fn() => forum_trigger_post_updated_event($post, $row, \context_module::instance($this->forum->cmid), $forum);

        $edit();
        $edit();
        $sameMoment = count($this->queued('forum_post_updated'));
        $this->waitForSecond();
        $edit();

        $this->assertSame(1, $sameMoment, 'Two events in the same second are one occurrence');
        $events = $this->queued('forum_post_updated');
        $this->assertCount(2, $events);
        $this->assertSame([$discussion->post, $discussion->post], array_column($events, 'postid'));
        $this->assertNotSame($events[0]['occurred_at'], $events[1]['occurred_at']);
    }

    /**
     * Deleting a message emits post_deleted; deleting a discussion emits discussion_deleted and also post_deleted for its messages.
     */
    public function test_deleting_a_message_and_a_discussion(): void {
        global $DB;
        [$course, $cm, $forum] = $this->objects();
        $discussion = $this->discussion();
        $reply = $this->reply($discussion);
        $other = $this->discussion();

        forum_delete_post($DB->get_record('forum_posts', ['id' => $reply], '*', MUST_EXIST), false, $course, $cm, $forum);
        $afterpost = $this->queued('forum_post_deleted');
        forum_delete_discussion($DB->get_record('forum_discussions', ['id' => $other->id], '*', MUST_EXIST), true, $course, $cm, $forum);

        $this->assertSame([$reply], array_column($afterpost, 'postid'));
        $this->assertSame([$other->id], array_column($this->queued('forum_discussion_deleted'), 'discussionid'));
        $this->assertContains('discussion_deleted', self::$recorded);
        $this->assertContains($other->post, array_column($this->queued('forum_post_deleted'), 'postid'), 'Its messages are deleted too');
    }

    /**
     * A course without the assistant enabled gets nothing.
     */
    public function test_course_without_the_assistant_enqueues_nothing(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $before = $DB->count_records('local_assistente_event');

        $discussion = $this->generator->create_discussion(['course' => $course->id, 'forum' => $forum->id, 'userid' => $this->student->id]);
        \mod_forum\event\discussion_created::create([
            'context' => \context_module::instance($forum->cmid),
            'objectid' => $discussion->id,
            'other' => ['forumid' => $forum->id],
        ])->trigger();

        $this->assertSame($before, $DB->count_records('local_assistente_event'));
    }

    /**
     * Incomplete data is refused: nothing is queued and the failure is logged, not thrown to the action that triggered the event.
     */
    public function test_incomplete_event_data_is_refused_safely(): void {
        global $DB;
        $before = $DB->count_records('local_assistente_event');

        \mod_forum\event\post_deleted::create([
            'objectid' => 1,
            'context' => \context_module::instance($this->forum->cmid),
            'other' => ['discussionid' => 0, 'forumid' => $this->forum->id, 'forumtype' => 'general'],
        ])->trigger();

        // The forum analysis is refused for the missing discussion. The user of the event (the admin) is valid, so the participation of the
        // deletion is the only event queued (T089).
        $queued = $DB->get_records_select('local_assistente_event', 'id > 0');
        $this->assertCount($before + 1, $queued);
        $this->assertSame(1, $DB->count_records('local_assistente_event', ['event_type' => event_type::PARTICIPATION_CHANGED->value]));
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => event_type::FORUM_POST_DELETED->value]));
        $this->assertDebuggingCalled();
    }

    /**
     * A failure of the queue is logged and does not break the action that triggered the event.
     */
    public function test_a_failure_is_contained_and_logged(): void {
        $service = new class extends forum_event_service {
            /**
             * Constructor, without the real collaborators.
             */
            public function __construct() {
            }

            #[\Override]
            public function discussion_created(int $courseid, int $cmid, int $discussionid): ?\local_assistente\application\enqueue_result {
                throw new \RuntimeException('queue unavailable');
            }
        };
        forum_observer::use_service($service);

        $discussion = $this->discussion();
        $this->emit_discussion_created($discussion->id);

        $this->assertNotEmpty($discussion->id, 'The discussion was created');
        $this->assertDebuggingCalled();
        forum_observer::use_service(null);
    }

    /**
     * From the forum to the result: the events of a real message are processed by the real queue and the analysis of the forum is saved.
     */
    public function test_a_real_message_reaches_the_analysis_through_the_queue(): void {
        global $DB;
        $discussion = $this->discussion();
        $this->emit_discussion_created($discussion->id);
        $registry = new handler_registry();
        $registry->register(event_type::FORUM_POST_CREATED, new forum_analysis_handler(new fake_adapter()));

        (new queue_processor(null, $registry))->run_cycle();

        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => 'forum_post_created', 'status' => 'pending']));
        $repository = new \local_assistente\infrastructure\persistence\forum_analysis_repository();
        $this->assertNotNull($repository->find_current((int) $this->course->id, (int) $this->forum->cmid));
        $this->assertNotEmpty($discussion->id);
    }
}
