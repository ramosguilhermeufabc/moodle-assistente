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

use local_assistente\application\frequency\participation_event_service;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the participation observers (T089; spec-014, DEC-39 D2), through the real APIs of Moodle 5.2.4 and the real queue.
 *
 * Emission validated by running the real operations: the completion of a module (completion_info::update_state), the grade of the
 * main item of an activity (grade_update), the creation and deletion of a message (the events of mod_forum). The submission is built from
 * a real submission of mod_assign with its own event class; what the pages of the assignment and of the completion emit is for CL-36 (T090).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(participation_observer::class)]
#[CoversClass(participation_event_service::class)]
final class participation_observer_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \stdClass assignment with cmid */
    private \stdClass $assign;

    /**
     * Real transactions and locks are used by the queue. A course with the assistant, a student and an assignment with completion.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enablecompletion = 1;
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        (new config_repository())->create($this->course->id, true);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id, 'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assign->id, 0, null, [
            'itemname' => 'Atividade', 'gradetype' => GRADE_TYPE_VALUE, 'grademin' => 0, 'grademax' => 100,
        ]);
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        foreach ($observers as $i => $observer) {
            $observers[$i] += ['includefile' => null, 'priority' => 0];
        }
        \core\event\manager::phpunit_replace_observers($observers);
    }

    /**
     * Restore the replaced services.
     */
    protected function tearDown(): void {
        participation_observer::use_service(null);
        grade_observer::use_participation_service(null);
        forum_observer::use_participation_service(null);
        parent::tearDown();
    }

    /**
     * Queued `participation_changed` events, as payloads.
     *
     * @return array[]
     */
    private function queued(): array {
        global $DB;
        $rows = $DB->get_records('local_assistente_event', ['event_type' => event_type::PARTICIPATION_CHANGED->value], 'id ASC');
        return array_values(array_map(static fn($r) => json_decode($r->payload, true), $rows));
    }

    /**
     * Queued sources, in order.
     *
     * @return string[]
     */
    private function sources(): array {
        return array_column($this->queued(), 'source');
    }

    /**
     * Mark the module as completed by the student.
     *
     * @param int $state COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    private function complete(int $state = COMPLETION_COMPLETE): void {
        $cm = get_coursemodule_from_id('assign', $this->assign->cmid, $this->course->id, false, MUST_EXIST);
        (new \completion_info(get_course($this->course->id)))->update_state($cm, $state, $this->student->id);
    }

    /**
     * Give or remove the grade of the student in the main item of the assignment.
     *
     * @param float|null $grade
     */
    private function grade(?float $grade): void {
        $this->assertSame(GRADE_UPDATE_OK, grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assign->id, 0, [
            'userid' => $this->student->id, 'rawgrade' => $grade,
        ]));
    }

    /**
     * A submission of the student, as the event of mod_assign.
     *
     * @param int|null $actor the user that acts, the student when null
     */
    private function submit(?int $actor = null): void {
        $context = \context_module::instance($this->assign->cmid);
        $assign = new \assign($context, get_coursemodule_from_id('assign', $this->assign->cmid), $this->course);
        $submission = $assign->get_user_submission($this->student->id, true);
        $params = ['context' => $context, 'objectid' => $submission->id, 'courseid' => $this->course->id,
            'userid' => $actor ?? $this->student->id, 'other' => ['submission_editable' => true]];
        if ($actor !== null && $actor !== (int) $this->student->id) {
            $params['relateduserid'] = $this->student->id;
        }
        \mod_assign\event\assessable_submitted::create($params)->trigger();
    }

    /**
     * The registration in db/events.php: the two events of the observer, outside the transaction (T046).
     */
    public function test_the_events_are_registered_outside_the_transaction(): void {
        global $CFG;
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        $byevent = array_column($observers, null, 'eventname');
        $expected = [
            '\mod_assign\event\assessable_submitted' => 'participation_observer::assessable_submitted',
            '\core\event\course_module_completion_updated' => 'participation_observer::completion_updated',
        ];
        foreach ($expected as $event => $callback) {
            $this->assertArrayHasKey($event, $byevent);
            $this->assertSame('\local_assistente\observer\\' . $callback, $byevent[$event]['callback']);
            $this->assertFalse($byevent[$event]['internal'], "{$event} must run after the commit");
        }
        foreach ($observers as $observer) {
            $this->assertFalse($observer['internal'], $observer['eventname'] . ' must be internal => false');
        }
    }

    /**
     * An assignment sent queues the participation of the student, with the module and the moment of the event.
     */
    public function test_a_submission_queues_the_participation(): void {
        $this->submit();

        $queued = $this->queued();
        $this->assertCount(1, $queued);
        $this->assertSame((int) $this->assign->cmid, $queued[0]['cmid']);
        $this->assertSame((int) $this->student->id, $queued[0]['userid']);
        $this->assertSame('submission', $queued[0]['source']);
    }

    /**
     * When a teacher submits for the student, the affected student is the related user, not the actor.
     */
    public function test_a_submission_by_somebody_else_counts_for_the_student(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->submit((int) $teacher->id);

        $queued = $this->queued();
        $this->assertCount(1, $queued);
        $this->assertSame((int) $this->student->id, $queued[0]['userid']);
    }

    /**
     * The completion of a module queues the participation, and so does undoing it.
     */
    public function test_a_completion_queues_the_participation(): void {
        $this->complete();
        $this->waitForSecond();
        $this->complete(COMPLETION_INCOMPLETE);

        $queued = $this->queued();
        $this->assertSame(['completion', 'completion'], array_column($queued, 'source'));
        $this->assertSame([(int) $this->student->id, (int) $this->student->id], array_column($queued, 'userid'));
        $this->assertSame([(int) $this->assign->cmid, (int) $this->assign->cmid], array_column($queued, 'cmid'));
    }

    /**
     * A grade of the main item of the activity is participation; the total of the course and the category are not.
     */
    public function test_a_grade_of_the_main_item_queues_the_participation_and_the_totals_do_not(): void {
        $this->grade(80.0);

        $this->assertSame(['grade'], $this->sources(), 'The totals emit events too, and only the item of the activity counts');
        $this->assertSame((int) $this->assign->cmid, $this->queued()[0]['cmid']);
        $this->assertSame((int) $this->student->id, $this->queued()[0]['userid']);
    }

    /**
     * A grade of a manual item is not a signal of participation in a module.
     */
    public function test_a_manual_item_does_not_queue_the_participation(): void {
        $item = new \grade_item(['courseid' => $this->course->id, 'itemtype' => 'manual', 'itemname' => 'Manual',
            'gradetype' => GRADE_TYPE_VALUE, 'grademin' => 0, 'grademax' => 10], false);
        $item->insert();
        $item->update_final_grade($this->student->id, 7.0);

        $this->assertSame([], $this->sources());
    }

    /**
     * The same grade written again does not change the participation: Moodle emits no event when nothing changed, and the queue
     * deduplicates the occurrences of the same moment.
     */
    public function test_the_same_grade_does_not_queue_again(): void {
        $this->grade(80.0);
        $this->grade(80.0);

        $this->assertSame(['grade'], $this->sources());
    }

    /**
     * The messages: created (a reply and the first message of a discussion) and deleted count for their author; an edit does not.
     */
    public function test_the_messages_queue_the_participation_of_the_author_and_an_edit_does_not(): void {
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $discussion = $generator->create_discussion([
            'course' => $this->course->id, 'forum' => $forum->id, 'userid' => $this->student->id,
        ]);
        $context = \context_module::instance($forum->cmid);
        $other = ['discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => 'general'];

        \mod_forum\event\discussion_created::create(['objectid' => $discussion->id, 'context' => $context, 'userid' => $this->student->id,
            'courseid' => $this->course->id, 'other' => ['forumid' => $forum->id]])->trigger();
        \mod_forum\event\post_created::create(['objectid' => $discussion->firstpost, 'context' => $context, 'userid' => $this->student->id,
            'courseid' => $this->course->id, 'other' => $other + ['triggeredfromrecentactivity' => false]])->trigger();
        // Same module, student, source and second: one occurrence, so the pair is queued once.
        $this->assertSame(['post'], $this->sources());
        $this->waitForSecond();

        \mod_forum\event\post_updated::create(['objectid' => $discussion->firstpost, 'context' => $context, 'userid' => $this->student->id,
            'courseid' => $this->course->id, 'other' => $other])->trigger();
        $this->assertCount(1, $this->queued(), 'An edit changes no participation');
        $this->waitForSecond();

        // A teacher deletes the message of the student: the affected one is the author, not the actor.
        \mod_forum\event\post_deleted::create(['objectid' => $discussion->firstpost, 'context' => $context, 'userid' => get_admin()->id,
            'relateduserid' => $this->student->id, 'courseid' => $this->course->id, 'other' => $other])->trigger();
        $queued = $this->queued();
        $this->assertCount(2, $queued);
        $this->assertSame((int) $this->student->id, $queued[1]['userid']);
        $this->assertSame((int) $forum->cmid, $queued[1]['cmid']);
    }

    /**
     * The deletion of a whole discussion does not say who posted in it, so it queues no participation (the deletion of each message does).
     */
    public function test_a_deleted_discussion_queues_no_participation(): void {
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        \mod_forum\event\discussion_deleted::create(['objectid' => 5, 'context' => \context_module::instance($forum->cmid),
            'courseid' => $this->course->id, 'other' => ['forumid' => $forum->id]])->trigger();

        $this->assertSame([], $this->sources());
    }

    /**
     * Users and contexts that are not valid queue nothing and the failure is logged, not thrown to the action.
     */
    public function test_invalid_users_and_contexts_are_refused_safely(): void {
        $context = \context_module::instance($this->assign->cmid);
        \core\event\course_module_completion_updated::create([
            'objectid' => 1, 'context' => $context, 'userid' => get_admin()->id, 'courseid' => $this->course->id,
            'relateduserid' => 0,
            'other' => ['relateduserid' => 0],
        ])->trigger();
        $this->assertSame([], $this->sources());

        $service = new participation_event_service();
        foreach ([[0, 1, 1], [1, 0, 1], [1, 1, 0], [1, 1, -4]] as [$courseid, $cmid, $userid]) {
            try {
                $service->participation_changed($courseid, $cmid, $userid, 'post', time());
                $this->fail('An identifier that is not positive must be refused');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('missing', $e->getMessage());
            }
        }
        try {
            $service->participation_changed(1, 1, 1, 'unknown', time());
            $this->fail('An unknown source must be refused');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('unknown', strtolower($e->getMessage()));
        }
        $this->assertNull($service->grade_item_changed($this->course->id, $this->student->id, 999999, time()));
        $this->assertSame([], $this->sources());
        $this->resetDebugging();
    }

    /**
     * A failure of the queue is logged and does not break the action that triggered the event; the other delivery still happens.
     */
    public function test_a_failure_is_contained_and_logged(): void {
        $failing = new class extends participation_event_service {
            /**
             * Constructor, without the real collaborators.
             */
            public function __construct() {
            }

            #[\Override]
            public function participation_changed(int $courseid, int $cmid, int $userid, string $source, int $occurredat): \local_assistente\application\enqueue_result {
                throw new \RuntimeException('queue unavailable');
            }
        };
        participation_observer::use_service($failing);

        $this->submit();

        $this->assertDebuggingCalled();
        $this->assertSame([], $this->sources());
    }

    /**
     * No cycle: the events the queue creates emit no event of Moodle, and processing them changes no participation.
     */
    public function test_processing_the_queue_does_not_start_a_cycle(): void {
        global $DB;
        $this->complete();
        $this->assertCount(1, $this->queued());

        $registry = handler_registry::default();
        $processor = new queue_processor(null, $registry);
        $processor->run_cycle();
        $processor->run_cycle();

        $this->assertCount(1, $this->queued(), 'Handling the event queues no other participation event');
        $this->assertSame(1, $DB->count_records('local_assistente_event', ['event_type' => event_type::PARTICIPATION_CHANGED->value]));
    }

    /**
     * Repetition: the same event twice is one event; two occurrences at different moments are two, each idempotent for the handler.
     */
    public function test_the_same_occurrence_is_queued_once(): void {
        $service = new participation_event_service();
        $now = time();
        $first = $service->participation_changed($this->course->id, $this->assign->cmid, $this->student->id, 'submission', $now);
        $again = $service->participation_changed($this->course->id, $this->assign->cmid, $this->student->id, 'submission', $now);
        $this->assertSame($first->event->id, $again->event->id);
        $this->assertCount(1, $this->queued());

        $service->participation_changed($this->course->id, $this->assign->cmid, $this->student->id, 'submission', $now + 5);
        $this->assertCount(2, $this->queued());
    }

    /**
     * Isolation: a course without the assistant queues nothing, and each event carries its own student and module.
     */
    public function test_a_course_without_the_assistant_queues_nothing_and_students_are_isolated(): void {
        $other = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $stranger = $this->getDataGenerator()->create_and_enrol($other, 'student');
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $other->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $cm = get_coursemodule_from_id('assign', $module->cmid, $other->id, false, MUST_EXIST);
        (new \completion_info(get_course($other->id)))->update_state($cm, COMPLETION_COMPLETE, $stranger->id);
        $this->assertSame([], $this->queued(), 'The other course has no assistant');

        $second = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->complete();
        $cm = get_coursemodule_from_id('assign', $this->assign->cmid, $this->course->id, false, MUST_EXIST);
        (new \completion_info(get_course($this->course->id)))->update_state($cm, COMPLETION_COMPLETE, $second->id);
        $users = array_column($this->queued(), 'userid');
        sort($users);
        $expected = [(int) $this->student->id, (int) $second->id];
        sort($expected);
        $this->assertSame($expected, $users);
    }

    /**
     * From the event to the result: the real queue calls the frequency handler, which recalculates only the student of the event.
     */
    public function test_a_real_event_reaches_the_frequency_handler_through_the_queue(): void {
        global $DB;
        $second = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->complete();

        (new queue_processor(null, handler_registry::default()))->run_cycle();

        $this->assertSame(0, $DB->count_records('local_assistente_event', [
            'event_type' => event_type::PARTICIPATION_CHANGED->value, 'status' => 'pending',
        ]), 'The event left the queue');
        $repository = new \local_assistente\infrastructure\persistence\frequency_repository();
        $this->assertSame([], $repository->find_history_for_student((int) $this->course->id, (int) $second->id), 'Only the student of the event');
        $this->assertCount(1, $this->queued(), 'Handling the event queued nothing');
    }
}
