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


namespace local_assistente\application\history;

use local_assistente\application\forum\forum_analysis_handler;
use local_assistente\domain\event\event_type;
use local_assistente\domain\source_status;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\moodle\source_record_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\performance_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the source status resolver (T102; quickstart 017 Q3 and Q6; DEC-42 D3). Real Moodle records removed with the Moodle APIs; the
 * forum analysis is made by the real handler with the simulated adapter.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(source_status_resolver::class)]
#[CoversClass(source_record_reader::class)]
final class source_status_resolver_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var source_status_resolver */
    private source_status_resolver $resolver;

    /** @var event_repository */
    private event_repository $events;

    /**
     * A course with the assistant and a student.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        (new \local_assistente\infrastructure\persistence\config_repository())->create($this->course->id, true);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->resolver = new source_status_resolver();
        $this->events = new event_repository();
    }

    /**
     * Delete a course module for good, with the Moodle action of the course format.
     *
     * @param int $cmid
     */
    private function delete_module(int $cmid): void {
        (new \core_courseformat\local\cmactions(get_course($this->course->id)))->delete($cmid);
    }

    /**
     * The state of a record of the course.
     *
     * @param string $type
     * @param int $id
     * @return source_status
     */
    private function state_of(string $type, int $id): source_status {
        return $this->resolver->status_of($type, $id, (int) $this->course->id);
    }

    /**
     * Q3: a module that is removed in Moodle is reported as removed, a module that is there as existing, and the one being deleted counts as
     * removed (hypothesis CL-16).
     */
    public function test_a_removed_activity_is_reported_as_removed(): void {
        $kept = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $gone = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $leaving = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->assertSame(source_status::EXISTS, $this->state_of('course_module', (int) $kept->cmid));

        $this->delete_module((int) $gone->cmid);
        (new \core_courseformat\local\cmactions(get_course($this->course->id)))->delete((int) $leaving->cmid, true);

        $this->assertSame(source_status::REMOVED, $this->state_of('course_module', (int) $gone->cmid));
        $this->assertSame(source_status::REMOVED, $this->state_of('course_module', (int) $leaving->cmid), 'Being deleted counts as removed');
        $this->assertSame(source_status::EXISTS, $this->state_of('course_module', (int) $kept->cmid));
        $this->assertTrue($this->resolver->source_removed('course_module', (int) $gone->cmid, (int) $this->course->id));
        $this->assertFalse($this->resolver->source_removed('course_module', (int) $kept->cmid, (int) $this->course->id));
    }

    /**
     * Q3 on a result: the history of an activity that was removed stays whole and traceable; only its source is reported as removed.
     */
    public function test_the_history_of_a_removed_activity_stays_and_its_source_is_removed(): void {
        global $DB;
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $event = $this->events->create_event((int) $this->course->id, event_type::GRADE_CHANGED, [
            'userid' => (int) $this->student->id, 'itemid' => 1, 'occurred_at' => time(),
        ], time());
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        $analysisid = (new performance_repository())->save(
            (int) $this->course->id,
            (int) $processing->id,
            (int) $this->student->id,
            [['activity_id' => (int) $assign->cmid, 'grade' => 80.0]]
        );
        $row = $DB->get_record('local_assistente_performance', ['analysis_id' => $analysisid], '*', MUST_EXIST);
        $reader = new lineage_reader();
        $before = $DB->count_records('local_assistente_performance');

        $this->delete_module((int) $assign->cmid);

        $lineage = $reader->trace_result('local_assistente_performance', (int) $row->id);
        $this->assertNotNull($lineage, 'The result is still there');
        $this->assertSame($before, $DB->count_records('local_assistente_performance'));
        $this->assertEquals(80.0, $DB->get_field('local_assistente_performance', 'grade', ['id' => $row->id]));
        $states = [];
        foreach ($this->resolver->resolve($lineage) as $source) {
            $states[$source['type'] . ':' . $source['id']] = $source['status'];
        }
        $this->assertSame(source_status::REMOVED, $states['course_module:' . $assign->cmid]);
        $this->assertSame(source_status::EXISTS, $states['user:' . $this->student->id]);
        $this->assertArrayHasKey('grade_item:1', $states);
        $this->assertSame(count($lineage->sources), count($this->resolver->resolve($lineage)), 'Same references, in the same order');
    }

    /**
     * Q6: a discussion that is deleted keeps its text in the historical analysis, the source is reported as removed, the forum itself exists,
     * and the current snapshot, rebuilt after the deletion, does not contain it.
     */
    public function test_a_deleted_discussion_keeps_its_text_in_the_history(): void {
        global $DB;
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $forum->id, 'userid' => $this->student->id,
        ]);
        $adapter = (new fake_adapter())->respond_with_callback(static fn($context) => [
            'use_case' => 'forum_analysis', 'status' => 'valid',
            'data' => ['forum_summary' => 'Resumo antes', 'difficulties' => [
                ['discussion_id' => $context->discussion_ids()[0], 'description' => 'Dúvida do período'],
            ]],
        ]);
        $handler = new forum_analysis_handler($adapter);
        $started = function (event_type $type, array $payload) {
            $event = $this->events->create_event((int) $this->course->id, $type, $payload, time());
            $this->events->begin_attempt((int) $event->id, time());
            return [$event, $this->events->find_processing((int) $event->id)];
        };
        [$e1, $p1] = $started(event_type::FORUM_POST_CREATED, [
            'cmid' => (int) $forum->cmid, 'discussionid' => (int) $discussion->id, 'postid' => (int) $discussion->firstpost,
        ]);
        $this->assertTrue($handler->handle($e1, $p1)->success);
        $old = $DB->get_record('local_assistente_analysis', ['processing_id' => $p1->id], '*', MUST_EXIST);
        $difficulty = $DB->get_record('local_assistente_forum_difficulty', ['analysis_id' => $old->id], '*', MUST_EXIST);

        $cm = get_coursemodule_from_id('forum', $forum->cmid);
        $record = $DB->get_record('forum_discussions', ['id' => $discussion->id], '*', MUST_EXIST);
        $this->assertTrue(forum_delete_discussion($record, false, $this->course, $cm, $DB->get_record('forum', ['id' => $forum->id])));
        $this->waitForSecond();
        [$e2, $p2] = $started(event_type::FORUM_DISCUSSION_DELETED, ['cmid' => (int) $forum->cmid, 'discussionid' => (int) $discussion->id]);
        $this->assertTrue($handler->handle($e2, $p2)->success);
        $new = $DB->get_record('local_assistente_analysis', ['processing_id' => $p2->id], '*', MUST_EXIST);

        $lineage = (new lineage_reader())->trace_result('local_assistente_forum_difficulty', (int) $difficulty->id);
        $states = [];
        foreach ($this->resolver->resolve($lineage) as $source) {
            $states[$source['type']] = $source['status'];
        }
        $this->assertSame('Dúvida do período', $DB->get_field('local_assistente_forum_difficulty', 'description', ['id' => $difficulty->id]));
        $this->assertSame('Resumo antes', $DB->get_field('local_assistente_analysis', 'summary', ['id' => $old->id]));
        $this->assertSame(source_status::REMOVED, $states['forum_discussion']);
        $this->assertSame(source_status::EXISTS, $states['course_module'], 'The forum itself is still there');
        $this->assertSame(0, $DB->count_records('local_assistente_forum_difficulty', ['analysis_id' => $new->id]), 'The current snapshot has no trace of it');
        $this->assertNull($new->summary);
    }

    /**
     * The other kinds of record: a message deleted (or kept by Moodle as deleted), an attempt, a grade item, a user and a section.
     */
    public function test_messages_attempts_grade_items_users_and_sections(): void {
        global $DB;
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $discussion = $generator->create_discussion(['course' => $this->course->id, 'forum' => $forum->id, 'userid' => $this->student->id]);
        $reply = $generator->create_post(['discussion' => $discussion->id, 'parent' => $discussion->firstpost, 'userid' => $this->student->id]);
        $this->assertSame(source_status::EXISTS, $this->state_of('forum_post', (int) $reply->id));
        $DB->set_field('forum_posts', 'deleted', 1, ['id' => $reply->id]);
        $this->assertSame(source_status::REMOVED, $this->state_of('forum_post', (int) $reply->id), 'Kept by Moodle as deleted');
        $DB->delete_records('forum_posts', ['id' => $reply->id]);
        $this->assertSame(source_status::REMOVED, $this->state_of('forum_post', (int) $reply->id));
        $this->assertSame(source_status::EXISTS, $this->state_of('forum_post', (int) $discussion->firstpost));

        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $attemptid = $DB->insert_record('quiz_attempts', (object) [
            'quiz' => $quiz->id, 'userid' => $this->student->id, 'attempt' => 1, 'uniqueid' => 987001, 'layout' => '', 'currentpage' => 0,
            'preview' => 0, 'state' => 'finished', 'timestart' => time(), 'timefinish' => time(), 'timemodified' => time(),
            'timemodifiedoffline' => 0, 'timecheckstate' => null, 'sumgrades' => 0,
        ]);
        $this->assertSame(source_status::EXISTS, $this->state_of('quiz_attempt', $attemptid));
        $DB->delete_records('quiz_attempts', ['id' => $attemptid]);
        $this->assertSame(source_status::REMOVED, $this->state_of('quiz_attempt', $attemptid));

        $courseitem = \grade_item::fetch_course_item($this->course->id);
        $this->assertSame(source_status::EXISTS, $this->state_of('grade_item', (int) $courseitem->id), 'The total of the course is an item too');
        $manual = new \grade_item(['courseid' => $this->course->id, 'itemtype' => 'manual', 'itemname' => 'M', 'gradetype' => GRADE_TYPE_VALUE], false);
        $manual->insert();
        $this->assertSame(source_status::EXISTS, $this->state_of('grade_item', (int) $manual->id));
        $manual->delete();
        $this->assertSame(source_status::REMOVED, $this->state_of('grade_item', (int) $manual->id));

        $other = $this->getDataGenerator()->create_user();
        $this->assertSame(source_status::EXISTS, $this->state_of('user', (int) $other->id));
        delete_user($other);
        $this->assertSame(source_status::REMOVED, $this->state_of('user', (int) $other->id), 'A deleted account');
        $this->assertSame(source_status::REMOVED, $this->state_of('user', 987654));

        $section = (int) $DB->get_field('course_sections', 'id', ['course' => $this->course->id, 'section' => 2]);
        $this->assertSame(source_status::EXISTS, $this->state_of('course_section', $section));
        course_delete_section($this->course, 2, true);
        $this->assertSame(source_status::REMOVED, $this->state_of('course_section', $section));
    }

    /**
     * A record of another course contradicts the chain: it is an error, not a removal.
     */
    public function test_a_record_of_another_course_is_an_error_not_a_removal(): void {
        $other = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('page', ['course' => $other->id]);
        $section = $this->getDataGenerator()->create_course_section(['course' => $other->id, 'section' => 1]);

        foreach ([['course_module', (int) $module->cmid], ['course_section', (int) $section->id]] as [$type, $id]) {
            try {
                $this->state_of($type, $id);
                $this->fail("{$type} of another course was accepted");
            } catch (lineage_exception $e) {
                $this->assertStringContainsString('another course', $e->getMessage());
            }
        }
        $this->assertSame(source_status::EXISTS, $this->resolver->status_of('course_module', (int) $module->cmid, (int) $other->id));
    }

    /**
     * Unknown kinds and invalid identifiers are errors, never a removal.
     */
    public function test_invalid_references_are_errors(): void {
        foreach ([['mystery', 1, 1], ['course_module', 0, 1], ['course_module', -4, 1], ['course_module', 1, 0]] as [$type, $id, $courseid]) {
            try {
                $this->resolver->status_of($type, $id, $courseid);
                $this->fail("{$type} {$id} {$courseid} was accepted");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * A record that could not be looked at is an exception, not a removal and not an existence.
     */
    public function test_a_failure_to_read_is_not_taken_as_a_removal(): void {
        $failing = new class extends source_record_reader {
            #[\Override]
            public function locate(string $type, int $id, int $courseid): string {
                throw new \dml_read_exception('database unavailable');
            }
        };

        $this->expectException(\dml_read_exception::class);

        (new source_status_resolver($failing))->status_of('course_module', 5, (int) $this->course->id);
    }

    /**
     * Resolving writes nothing: the plugin tables are the same after the lookups.
     */
    public function test_resolving_writes_nothing(): void {
        global $DB;
        $module = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $tables = ['local_assistente_event', 'local_assistente_analysis', 'local_assistente_processing', 'local_assistente_processing_context'];
        $count = fn() => array_map(fn($t) => $DB->count_records($t), $tables);
        $before = $count();

        $this->state_of('course_module', (int) $module->cmid);
        $this->delete_module((int) $module->cmid);
        $this->state_of('course_module', (int) $module->cmid);

        $this->assertSame($before, $count());
    }
}
