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

namespace local_assistente\application\forum;

use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\moodle\forum_reader;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * CL-35 (T084): a forum in separate groups mode, with messages of students of different groups. The reader reads every message and the
 * analysis considers all of them, whichever group wrote them (the analysis is of the course, not of a viewer).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(forum_reader::class)]
#[CoversClass(forum_analysis_handler::class)]
final class forum_separate_groups_test extends \advanced_testcase {
    /**
     * Messages of two groups in a forum with separate groups: reader and rebuild analysis see all of them.
     */
    public function test_the_reader_and_the_analysis_consider_the_messages_of_every_group(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        (new config_repository())->create($course->id, true);
        $forum = $generator->create_module('forum', ['course' => $course->id, 'groupmode' => SEPARATEGROUPS]);
        $this->assertEquals(SEPARATEGROUPS, groups_get_activity_groupmode(get_coursemodule_from_id('forum', $forum->cmid)));
        $groupa = $generator->create_group(['courseid' => $course->id]);
        $groupb = $generator->create_group(['courseid' => $course->id]);
        $studenta = $generator->create_and_enrol($course, 'student');
        $studentb = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $studenta->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $studentb->id]);
        $forumgen = $generator->get_plugin_generator('mod_forum');
        $da = $forumgen->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $studenta->id, 'groupid' => $groupa->id,
            'name' => 'Grupo A', 'message' => '<p>mensagem do grupo A</p>',
        ]);
        $db = $forumgen->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $studentb->id, 'groupid' => $groupb->id,
            'name' => 'Grupo B', 'message' => '<p>mensagem do grupo B</p>',
        ]);
        $forumgen->create_post([
            'discussion' => $db->id, 'parent' => $db->firstpost, 'userid' => $studentb->id, 'subject' => 'Re',
            'message' => '<p>resposta do grupo B</p>',
        ]);

        // The reader reads the discussions and messages of both groups.
        $reader = new forum_reader();
        $discussions = $reader->get_discussions((int) $forum->cmid);
        $this->assertEqualsCanonicalizing([(int) $groupa->id, (int) $groupb->id], array_column($discussions, 'group_id'));
        $messages = array_merge(...array_map(
            static fn($d) => $reader->get_messages($d->discussion_id),
            $discussions
        ));
        $this->assertCount(3, $messages);
        $this->assertEqualsCanonicalizing([(int) $studenta->id, (int) $studentb->id], array_values(array_unique(array_column($messages, 'user_id'))));

        // The analysis (rebuild of the forum) sends the messages of both groups to the LLM.
        $llm = new fake_adapter();
        $llm->respond_valid(['forum_summary' => 'Resumo', 'difficulties' => []]);
        $events = new event_repository();
        $event = $events->create_event((int) $course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => (int) $forum->cmid], time());
        $events->begin_attempt((int) $event->id, time());
        $result = (new forum_analysis_handler($llm))->handle($event, $events->find_processing((int) $event->id));

        $this->assertTrue($result->success, (string) $result->response());
        $context = end($llm->requests)['context']->data;
        $this->assertEqualsCanonicalizing([(int) $da->id, (int) $db->id], $context['discussion_ids']);
        $json = json_encode($context);
        foreach (['mensagem do grupo A', 'mensagem do grupo B', 'resposta do grupo B'] as $text) {
            $this->assertStringContainsString($text, $json);
        }
    }
}
