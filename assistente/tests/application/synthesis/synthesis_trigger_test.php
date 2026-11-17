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


namespace local_assistente\application\synthesis;

use local_assistente\application\forum\forum_analysis_handler;
use local_assistente\application\frequency\frequency_handler;
use local_assistente\application\grade\performance_handler;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\quiz\initial_analysis_handler;
use local_assistente\application\quiz\results_handler;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the trigger of the synthesis and of its chaining after the analyses of 010 to 014 (T098; quickstart 016 Q10; DEC-41 D3, DEC-21).
 * Real course, readers, repositories and queue; no handler of the synthesis runs here, only the requests are looked at.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(synthesis_trigger::class)]
final class synthesis_trigger_test extends \advanced_testcase {
    /** @var int counter that keeps the occurrences distinct */
    private static int $occurred = 12000;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var int[] ids of the sections 1, 2 and 3 */
    private array $sections;

    /** @var \stdClass[] an assignment with a grade in each section, by section index */
    private array $assigns;

    /** @var \stdClass forum in the third section */
    private \stdClass $forum;

    /** @var \stdClass quiz in the second section */
    private \stdClass $quiz;

    /** @var event_repository */
    private event_repository $events;

    /**
     * A course with the assistant, three sections with an assignment each, a forum in the third and a quiz in the second.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 3, 'startdate' => $now - 500 - DAYSECS, 'enddate' => $now - 500]);
        (new config_repository())->create($this->course->id, true);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->sections = array_map('intval', array_values(
            $DB->get_fieldset_select('course_sections', 'id', 'course = ? AND section > 0', [$this->course->id], 'section')
        ));
        foreach ([1, 2, 3] as $number) {
            $this->assigns[$number - 1] = $this->getDataGenerator()->create_module('assign', [
                'course' => $this->course->id, 'duedate' => $now - 1000, 'grade' => 100,
            ], ['section' => $number]);
        }
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id], ['section' => 3]);
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id], ['section' => 2]);
        $this->events = new event_repository();
    }

    /**
     * Create an event, begin its attempt and hand it to a handler.
     *
     * @param event_type $type
     * @param array $payload
     * @param \local_assistente\application\queue\event_handler $handler
     * @return array handler_result, processing and event
     */
    private function deliver(event_type $type, array $payload, $handler): array {
        $event = $this->events->create_event((int) $this->course->id, $type, $payload, time());
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        return [$handler->handle($event, $processing), $processing, $event];
    }

    /**
     * The sections asked for, as indexes 0 to 2, sorted; the events that are still pending.
     *
     * @return int[]
     */
    private function requested(): array {
        global $DB;
        $ids = [];
        foreach ($DB->get_records('local_assistente_event', [
            'course_id' => $this->course->id, 'event_type' => 'synthesis_requested', 'status' => 'pending',
        ]) as $row) {
            $ids[] = array_search((int) json_decode($row->payload, true)['section_id'], $this->sections, true);
        }
        sort($ids);
        return $ids;
    }

    /**
     * Forget the requests, as if they had been lost.
     */
    private function forget_requests(): void {
        global $DB;
        $DB->delete_records('local_assistente_event', ['event_type' => 'synthesis_requested']);
    }

    /**
     * The id of the main grade item of an assignment.
     *
     * @param \stdClass $assign
     * @return int
     */
    private function item_of(\stdClass $assign): int {
        global $DB;
        return (int) $DB->get_field('grade_items', 'id', [
            'courseid' => $this->course->id, 'itemmodule' => 'assign', 'iteminstance' => $assign->id, 'itemnumber' => 0,
        ]);
    }

    /**
     * A request for a section is one event; a pending one is reused and a new one is made after it was processed (DEC-21).
     */
    public function test_a_pending_request_is_reused_and_a_new_one_is_made_after_it_ran(): void {
        global $DB;
        $trigger = new synthesis_trigger();
        $first = $trigger->request_section((int) $this->course->id, $this->sections[0]);
        $second = $trigger->request_section((int) $this->course->id, $this->sections[0]);
        $other = $trigger->request_section((int) $this->course->id, $this->sections[1]);

        $this->assertSame((int) $first->event->id, (int) $second->event->id);
        $this->assertNotSame((int) $first->event->id, (int) $other->event->id);
        $this->assertSame([0, 1], $this->requested());

        $DB->set_field('local_assistente_event', 'status', 'processed', ['id' => $first->event->id]);
        $third = $trigger->request_section((int) $this->course->id, $this->sections[0]);

        $this->assertNotSame((int) $first->event->id, (int) $third->event->id);
        $this->assertSame([0, 1], $this->requested(), 'Pending: the new one for the first section and the one of the second');
        $this->assertSame(3, $DB->count_records('local_assistente_event', ['event_type' => 'synthesis_requested']), 'The one that ran stays, and a new one was made');
        $this->expectException(\InvalidArgumentException::class);
        $trigger->request_section((int) $this->course->id, 0);
    }

    /**
     * Several sections are asked for once each, repeated ones count once, and a course without the assistant queues nothing.
     */
    public function test_several_sections_once_each_and_nothing_without_the_assistant(): void {
        global $DB;
        $trigger = new synthesis_trigger();
        $trigger->request_sections((int) $this->course->id, [$this->sections[2], $this->sections[0], $this->sections[2]]);
        $off = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $section = (int) $DB->get_field('course_sections', 'id', ['course' => $off->id, 'section' => 1]);

        $result = $trigger->request_section((int) $off->id, $section);

        $this->assertSame([0, 2], $this->requested());
        $this->assertSame(\local_assistente\application\enqueue_status::SKIPPED_NOT_ENABLED, $result->status);
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['course_id' => $off->id]));
    }

    /**
     * The section of a module is the one it has in the course, read from the course; a module that does not resolve is an error that
     * names the reason, not a request dropped.
     */
    public function test_the_section_of_a_module_is_read_from_the_course_and_a_bad_one_fails_loudly(): void {
        $trigger = new synthesis_trigger();
        $trigger->request_for_module((int) $this->course->id, (int) $this->quiz->cmid);
        $trigger->request_for_module((int) $this->course->id, (int) $this->forum->cmid);
        $this->assertSame([1, 2], $this->requested());

        $other = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_module('page', ['course' => $other->id]);
        foreach ([(int) $foreign->cmid, 987654] as $cmid) {
            try {
                $trigger->request_for_module((int) $this->course->id, $cmid);
                $this->fail('The module was accepted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('not resolved', $e->getMessage());
            }
        }
        $this->assertSame([1, 2], $this->requested(), 'Nothing was queued for them');
    }

    /**
     * A grade item belongs to the section of the module it grades; the total of the course, a category and an unknown item belong to none.
     */
    public function test_a_grade_item_belongs_to_the_section_of_its_module(): void {
        $trigger = new synthesis_trigger();
        $courseitem = \grade_item::fetch_course_item($this->course->id);

        $result = $trigger->request_for_grade_item((int) $this->course->id, $this->item_of($this->assigns[1]));
        $total = $trigger->request_for_grade_item((int) $this->course->id, (int) $courseitem->id);
        $unknown = $trigger->request_for_grade_item((int) $this->course->id, 987654);

        $this->assertNotNull($result);
        $this->assertNull($total);
        $this->assertNull($unknown);
        $this->assertSame([1], $this->requested());
    }

    /**
     * 012: a grade event asks for the section of the item; the total of the course asks for none; a grade in another section asks for
     * that one, and a repeated one in the same section is one request.
     */
    public function test_performance_asks_for_the_section_of_the_item(): void {
        $handler = new performance_handler();
        $courseitem = \grade_item::fetch_course_item($this->course->id);
        $grade = function (int $section, float $value) use ($handler) {
            grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assigns[$section]->id, 0, [
                'userid' => $this->student->id, 'rawgrade' => $value,
            ]);
            return $this->deliver(event_type::GRADE_CHANGED, [
                'userid' => (int) $this->student->id, 'itemid' => $this->item_of($this->assigns[$section]), 'occurred_at' => ++self::$occurred,
            ], $handler);
        };

        [$total] = $this->deliver(event_type::GRADE_CHANGED, [
            'userid' => (int) $this->student->id, 'itemid' => (int) $courseitem->id, 'occurred_at' => ++self::$occurred,
        ], $handler);
        $this->assertTrue($total->success);
        $this->assertSame([], $this->requested(), 'The total of the course belongs to no section');

        [$one] = $grade(1, 70.0);
        $this->assertTrue($one->success);
        $this->assertSame([1], $this->requested());
        $grade(1, 71.0);
        $this->assertSame([1], $this->requested(), 'A pending request is reused');
        $grade(2, 50.0);
        $this->assertSame([1, 2], $this->requested());
    }

    /**
     * 014 and Q10: the analysis of a student with activities in three sections asks for the three; a pending one is reused, and the
     * analysis of a whole course after a reference date asks for the sections that have a row, once each.
     */
    public function test_frequency_asks_for_every_section_of_the_analysis(): void {
        $handler = new frequency_handler(null, null, null, null, null, null, null, null, fn() => time());
        $participation = fn() => $this->deliver(event_type::PARTICIPATION_CHANGED, [
            'cmid' => (int) $this->assigns[0]->cmid, 'userid' => (int) $this->student->id, 'source' => 'grade', 'occurred_at' => ++self::$occurred,
        ], $handler);

        [$result] = $participation();

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertSame([0, 1, 2], $this->requested(), 'Q10: three sections, three events');
        $participation();
        $this->assertSame([0, 1, 2], $this->requested(), 'The pending ones are reused');

        $this->forget_requests();
        [$course] = $this->deliver(event_type::REFERENCE_DATE_REACHED, [
            'cmid' => (int) $this->assigns[0]->cmid, 'reference_date' => time() - 1000,
        ], $handler);
        $this->assertTrue($course->success);
        $this->assertSame([0, 1, 2], $this->requested(), 'One for each section, not one for each student');
    }

    /**
     * 013: the analysis of a forum asks for the section of the forum; a message that no longer exists saves nothing and asks for nothing.
     */
    public function test_forum_asks_for_the_section_of_the_forum(): void {
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $this->student->id,
        ]);
        $handler = new forum_analysis_handler(new fake_adapter());

        [$gone] = $this->deliver(event_type::FORUM_POST_UPDATED, [
            'cmid' => (int) $this->forum->cmid, 'discussionid' => (int) $discussion->id, 'postid' => 987654, 'occurred_at' => ++self::$occurred,
        ], $handler);
        $this->assertTrue($gone->success);
        $this->assertSame([], $this->requested(), 'Nothing was analysed, so nothing is asked');

        [$result] = $this->deliver(event_type::FORUM_POST_CREATED, [
            'cmid' => (int) $this->forum->cmid, 'discussionid' => (int) $discussion->id, 'postid' => (int) $discussion->firstpost,
        ], $handler);

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertSame([2], $this->requested());
    }

    /**
     * 010 and 011: the analyses of a questionnaire ask for the section of the questionnaire.
     */
    public function test_questionnaire_analyses_ask_for_the_section_of_the_questionnaire(): void {
        [$initial] = $this->deliver(
            event_type::QUIZ_PUBLISHED,
            ['cmid' => (int) $this->quiz->cmid],
            new initial_analysis_handler(new fake_adapter())
        );
        $this->assertTrue($initial->success, (string) $initial->message);
        $this->assertSame([1], $this->requested());

        $this->forget_requests();
        [$results] = $this->deliver(
            event_type::QUIZ_CLOSED,
            ['cmid' => (int) $this->quiz->cmid, 'timeclose' => time() - 100],
            new results_handler()
        );

        $this->assertTrue($results->success, (string) $results->message);
        $this->assertSame([1], $this->requested());
    }

    /**
     * A retry of an analysis that already exists asks again, in every domain, so a request lost by a failure is not lost for good.
     */
    public function test_handling_an_existing_analysis_asks_again_in_every_domain(): void {
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $this->student->id,
        ]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assigns[0]->id, 0, ['userid' => $this->student->id, 'rawgrade' => 60]);
        $cases = [
            [event_type::GRADE_CHANGED, ['userid' => (int) $this->student->id, 'itemid' => $this->item_of($this->assigns[0]), 'occurred_at' => ++self::$occurred], new performance_handler(), [0]],
            [event_type::PARTICIPATION_CHANGED, ['cmid' => (int) $this->assigns[0]->cmid, 'userid' => (int) $this->student->id, 'source' => 'grade', 'occurred_at' => ++self::$occurred], new frequency_handler(null, null, null, null, null, null, null, null, fn() => time()), [0, 1, 2]],
            [event_type::FORUM_POST_CREATED, ['cmid' => (int) $this->forum->cmid, 'discussionid' => (int) $discussion->id, 'postid' => (int) $discussion->firstpost], new forum_analysis_handler(new fake_adapter()), [2]],
            [event_type::QUIZ_PUBLISHED, ['cmid' => (int) $this->quiz->cmid], new initial_analysis_handler(new fake_adapter()), [1]],
            [event_type::QUIZ_CLOSED, ['cmid' => (int) $this->quiz->cmid, 'timeclose' => time() - 100], new results_handler(), [1]],
        ];
        foreach ($cases as [$type, $payload, $handler, $expected]) {
            [$first, $processing, $event] = $this->deliver($type, $payload, $handler);
            $this->assertTrue($first->success, $type->value . ': ' . $first->message);
            $this->assertSame($expected, $this->requested(), $type->value);

            $this->forget_requests();
            $again = $handler->handle($event, $processing);

            $this->assertTrue($again->success, $type->value);
            $this->assertStringContainsString('already exist', (string) $again->message);
            $this->assertSame($expected, $this->requested(), $type->value . ': the retry asked again');
            $this->forget_requests();
        }
    }

    /**
     * A failure to queue the request fails the attempt after the analysis was saved, with the reason; the retry asks again and succeeds.
     */
    public function test_a_failure_to_queue_fails_the_attempt_and_the_retry_asks_again(): void {
        global $DB;
        $failing = new class extends synthesis_trigger {
            /** @var bool whether it still fails */
            public bool $failing = true;

            /**
             * Constructor, without the real collaborators.
             */
            public function __construct() {
                parent::__construct();
            }

            #[\Override]
            public function request_for_grade_item(int $courseid, int $itemid): ?\local_assistente\application\enqueue_result {
                if ($this->failing) {
                    throw new \RuntimeException('queue unavailable');
                }
                return parent::request_for_grade_item($courseid, $itemid);
            }
        };
        $handler = new performance_handler(null, null, null, null, null, null, $failing);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assigns[2]->id, 0, ['userid' => $this->student->id, 'rawgrade' => 80]);

        [$result, $processing, $event] = $this->deliver(event_type::GRADE_CHANGED, [
            'userid' => (int) $this->student->id, 'itemid' => $this->item_of($this->assigns[2]), 'occurred_at' => ++self::$occurred,
        ], $handler);

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $this->assertStringContainsString('queue unavailable', (string) $result->message);
        $this->assertSame([], $this->requested());
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'grade_performance']), 'The analysis was saved');

        $failing->failing = false;
        $retry = $handler->handle($event, $processing);

        $this->assertTrue($retry->success);
        $this->assertSame([2], $this->requested());
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'grade_performance']), 'Not analysed twice');
    }
}
