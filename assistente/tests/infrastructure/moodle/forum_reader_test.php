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

namespace local_assistente\infrastructure\moodle;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the forum reader (DEC-38, DEC-39).
 *
 * Events and the exact API in a running Moodle 5.2 are validated by CL-35 (T035).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(forum_reader::class)]
final class forum_reader_test extends \advanced_testcase {
    /**
     * Forum, a teacher's discussion and a student's reply, and a second discussion.
     *
     * @return array [course, forum, teacher, student, first discussion, reply]
     */
    private function forum_with_messages(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $forum = $generator->create_module('forum', ['course' => $course->id, 'name' => 'Duvidas']);
        $forumgenerator = $generator->get_plugin_generator('mod_forum');

        $discussion = $forumgenerator->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $teacher->id, 'name' => 'Topico 1', 'message' => 'Abertura',
        ]);
        $reply = $forumgenerator->create_post([
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost, 'userid' => $student->id,
            'subject' => 'Re: Topico 1', 'message' => 'Nao entendi a regra',
        ]);
        return [$course, $forum, $teacher, $student, $discussion, $reply];
    }

    /**
     * Forum, discussions and messages with the generic role of the poster.
     */
    public function test_forum_discussions_and_messages(): void {
        $this->resetAfterTest();
        [$course, $forum, $teacher, $student, $discussion, $reply] = $this->forum_with_messages();
        $reader = new forum_reader();

        $data = $reader->get_forum($forum->cmid);
        $this->assertSame((int) $course->id, $data->course_id);
        $this->assertSame('Duvidas', $data->name);

        $discussions = $reader->get_discussions($forum->cmid);
        $this->assertCount(1, $discussions);
        $this->assertSame((int) $discussion->id, $discussions[0]->discussion_id);
        $this->assertSame('Topico 1', $discussions[0]->name);

        $messages = $reader->get_messages((int) $discussion->id);
        $this->assertCount(2, $messages);
        $this->assertSame(0, $messages[0]->parent_id);
        $this->assertSame('teacher', $messages[0]->role);
        $this->assertSame((int) $teacher->id, $messages[0]->user_id);
        $this->assertSame((int) $messages[0]->post_id, $messages[1]->parent_id);
        $this->assertSame('student', $messages[1]->role);
        $this->assertSame('Nao entendi a regra', strip_tags($messages[1]->message));
        $this->assertFalse($messages[1]->private_reply);
        $this->assertSame((int) $reply->id, $reader->get_message((int) $reply->id)->post_id);
    }

    /**
     * Missing data: not a forum, no discussion, no message.
     */
    public function test_missing_data(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $reader = new forum_reader();

        $this->assertNull($reader->get_forum($page->cmid));
        $this->assertNull($reader->get_forum(987654));
        $this->assertSame([], $reader->get_discussions($page->cmid));
        $this->assertSame([], $reader->get_messages(987654));
        $this->assertNull($reader->get_message(987654));
        $this->assertSame([], $reader->get_post_counts($page->cmid));
    }

    /**
     * Deleted messages are not returned and do not count; edits are reflected at read time (DEC-38 D2).
     */
    public function test_deleted_messages_are_not_returned(): void {
        global $DB;
        $this->resetAfterTest();
        [, $forum, , $student, $discussion, $reply] = $this->forum_with_messages();
        $reader = new forum_reader();
        $this->assertTrue($reader->has_posted($forum->cmid, (int) $student->id));

        $DB->set_field('forum_posts', 'deleted', 1, ['id' => $reply->id]);

        $this->assertCount(1, $reader->get_messages((int) $discussion->id));
        $this->assertNull($reader->get_message((int) $reply->id));
        $this->assertFalse($reader->has_posted($forum->cmid, (int) $student->id));

        $DB->set_field('forum_posts', 'message', 'Texto editado', ['id' => $discussion->firstpost]);
        $this->assertSame('Texto editado', $reader->get_messages((int) $discussion->id)[0]->message);
    }

    /**
     * Postings per user, for the participation and the binary forum criterion (DEC-39, DEC-40).
     */
    public function test_post_counts_by_user(): void {
        $this->resetAfterTest();
        [, $forum, $teacher, $student, $discussion] = $this->forum_with_messages();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_post([
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost, 'userid' => $student->id, 'message' => 'Outra',
        ]);
        $reader = new forum_reader();

        $this->assertSame([(int) $teacher->id => 1, (int) $student->id => 2], $reader->get_post_counts($forum->cmid));
        $this->assertTrue($reader->has_posted($forum->cmid, (int) $student->id));
        $this->assertFalse($reader->has_posted($forum->cmid, (int) $other->id));
    }
}
