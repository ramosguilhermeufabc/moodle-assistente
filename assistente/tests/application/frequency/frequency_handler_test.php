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

namespace local_assistente\application\frequency;

use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\participation_reader;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\frequency_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the frequency handler (T087; quickstart 014 Q1, Q3, Q6, Q8, Q9, Q10, Q12, Q13; DEC-25, DEC-39, DEC-24). Real course, real
 * readers and queue; no LLM exists in this flow. The moment of the calculation is fixed by the clock of the handler.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(frequency_handler::class)]
final class frequency_handler_test extends \advanced_testcase {
    /** @var int fixed moment of the calculation */
    private int $now;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass[] students */
    private array $students;

    /** @var int[] section ids of the course, 1 and 2 */
    private array $sections;

    /** @var \stdClass assignment past due, section 1 */
    private \stdClass $pastassign;

    /** @var \stdClass forum without a date, section 1 */
    private \stdClass $forum;

    /** @var \stdClass page with completion (a resource), section 1 */
    private \stdClass $page;

    /** @var \stdClass assignment with a future due date, section 2 */
    private \stdClass $futureassign;

    /** @var event_repository */
    private event_repository $events;

    /** @var frequency_repository */
    private frequency_repository $repository;

    /** @var frequency_handler */
    private frequency_handler $handler;

    /**
     * A course that ended 500 seconds before the moment of the calculation, with the assistant, two students, and four modules.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->now = time();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2, 'enablecompletion' => 1, 'startdate' => $this->now - 500 - DAYSECS, 'enddate' => $this->now - 500]);
        (new config_repository())->create($this->course->id, true);
        $this->students = [
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
        ];
        $this->sections = array_map('intval', array_values($DB->get_fieldset_select('course_sections', 'id', 'course = ? AND section > 0', [$this->course->id], 'section')));
        $this->pastassign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id, 'duedate' => $this->now - 1000, 'grade' => 100,
        ], ['section' => 1]);
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id], ['section' => 1]);
        $this->page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id, 'completion' => COMPLETION_TRACKING_MANUAL,
        ], ['section' => 1]);
        $this->futureassign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id, 'duedate' => $this->now + 100000, 'grade' => 0,
        ], ['section' => 2]);
        $this->events = new event_repository();
        $this->repository = new frequency_repository();
        $this->handler = new frequency_handler(null, null, null, null, null, null, null, null, fn() => $this->now);
    }

    /**
     * An event of participation of a student and its processing.
     *
     * @param \stdClass|null $student
     * @return array handler_result, processing and event
     */
    private function participation_event(?\stdClass $student = null): array {
        static $occurred = 4000;
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::PARTICIPATION_CHANGED,
            ['cmid' => (int) $this->pastassign->cmid, 'userid' => (int) ($student ?? $this->students[0])->id, 'source' => 'submission', 'occurred_at' => ++$occurred],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        return [$this->handler->handle($event, $processing), $processing, $event];
    }

    /**
     * Rows of an analysis by section id for a student: [total, participating, percentage].
     *
     * @param \stdClass $processing
     * @param \stdClass $student
     * @return array
     */
    private function rows_of(\stdClass $processing, \stdClass $student): array {
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $rows = [];
        foreach ($this->repository->find_frequencies((int) $analysis->id, (int) $student->id) as $row) {
            $rows[(int) $row->section_id] = [(int) $row->total_activities, (int) $row->participating_activities, (float) $row->percentage];
        }
        return $rows;
    }

    /**
     * The context of a processing.
     *
     * @param \stdClass $processing
     * @return array
     */
    private function context_of(\stdClass $processing): array {
        return json_decode($this->events->find_context((int) $processing->id)->context_json, true);
    }

    /**
     * The student posts in the forum: section 1 has the assignment (past due) and the forum (end date of the course, past) in the
     * denominator, the page is a resource and stays out, and one of two is 50.000. Section 2 has only an activity not yet due: a row with
     * total zero.
     */
    public function test_forum_post_gives_one_of_two_in_the_section_and_the_rest_follows_the_rules(): void {
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $this->students[0]->id,
        ]);

        [$result, $processing] = $this->participation_event();

        $this->assertTrue($result->success, (string) $result->response());
        $rows = $this->rows_of($processing, $this->students[0]);
        $this->assertSame([2, 1, 50.0], $rows[$this->sections[0]], 'Section 1: assignment and forum, one of them with a post');
        $this->assertSame([0, 0, 0.0], $rows[$this->sections[1]], 'Section 2: nothing in the denominator yet, no_data');
    }

    /**
     * Q3 and Q8: the reference date of each activity is its due date, and the end of the course when there is none.
     */
    public function test_reference_dates_are_the_due_date_or_the_end_of_the_course(): void {
        [, $processing] = $this->participation_event();

        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $frequency = array_values(array_filter(
            $this->repository->find_frequencies((int) $analysis->id, (int) $this->students[0]->id),
            fn($r) => (int) $r->section_id === $this->sections[0]
        ))[0];
        $dates = [];
        foreach ($this->repository->find_activities((int) $frequency->id) as $activity) {
            $dates[(int) $activity->activity_id] = [(int) $activity->participated, (int) $activity->reference_date];
        }

        $this->assertSame([0, $this->now - 1000], $dates[(int) $this->pastassign->cmid]);
        $this->assertSame([0, $this->now - 500], $dates[(int) $this->forum->cmid]);
        $this->assertArrayNotHasKey((int) $this->page->cmid, $dates, 'A resource, even with completion, is out');
    }

    /**
     * Q10: an activity not yet due enters the denominator of the student who already took part (a submission) and not of the other.
     */
    public function test_a_future_activity_enters_only_for_the_student_who_took_part(): void {
        global $DB;
        $DB->insert_record('assign_submission', (object) [
            'assignment' => $this->futureassign->id, 'userid' => $this->students[0]->id, 'timecreated' => $this->now,
            'timemodified' => $this->now, 'status' => 'submitted', 'groupid' => 0, 'attemptnumber' => 0, 'latest' => 1,
        ]);

        [, $first] = $this->participation_event($this->students[0]);
        [, $second] = $this->participation_event($this->students[1]);

        $this->assertSame([1, 1, 100.0], $this->rows_of($first, $this->students[0])[$this->sections[1]]);
        $this->assertSame([0, 0, 0.0], $this->rows_of($second, $this->students[1])[$this->sections[1]]);
    }

    /**
     * Q12: access to the course is evidence only: the context records, by student, whether it happened and the last time, and the
     * percentage is the same with or without it.
     */
    public function test_course_access_goes_in_the_context_and_does_not_change_the_percentage(): void {
        global $DB;
        [, $without] = $this->participation_event($this->students[0]);
        $DB->insert_record('user_lastaccess', (object) ['userid' => $this->students[0]->id, 'courseid' => $this->course->id, 'timeaccess' => $this->now - 60]);

        [, $with] = $this->participation_event($this->students[0]);

        $this->assertSame($this->rows_of($without, $this->students[0]), $this->rows_of($with, $this->students[0]));
        $this->assertSame([['student_id' => (int) $this->students[0]->id, 'accessed' => false, 'last_access' => null]], $this->context_of($without)['course_access']);
        $this->assertSame([['student_id' => (int) $this->students[0]->id, 'accessed' => true, 'last_access' => $this->now - 60]], $this->context_of($with)['course_access']);
    }

    /**
     * Q9: with no due date and no end date for the course, an activity is out of the calculation and listed in the context.
     */
    public function test_an_activity_without_any_date_is_listed_in_the_context(): void {
        global $DB;
        $DB->set_field('course', 'enddate', 0, ['id' => $this->course->id]);

        [$result, $processing] = $this->participation_event();

        $this->assertTrue($result->success);
        $this->assertSame([(int) $this->forum->cmid], $this->context_of($processing)['activities_without_date']);
        $this->assertSame([1, 0, 0.0], $this->rows_of($processing, $this->students[0])[$this->sections[0]], 'Only the assignment is left');
    }

    /**
     * Q6: an activity with a restriction that the student does not meet is out of the denominator of that student only.
     */
    public function test_an_activity_the_student_cannot_reach_is_out_for_that_student(): void {
        global $DB;
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $this->students[0]->id]);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&', 'showc' => [false], 'c' => [['type' => 'group', 'id' => (int) $group->id]],
        ]), ['id' => $this->forum->cmid]);
        rebuild_course_cache((int) $this->course->id, true);

        [, $inside] = $this->participation_event($this->students[0]);
        [, $outside] = $this->participation_event($this->students[1]);

        $this->assertSame(2, $this->rows_of($inside, $this->students[0])[$this->sections[0]][0]);
        $this->assertSame(1, $this->rows_of($outside, $this->students[1])[$this->sections[0]][0]);
    }

    /**
     * The reference date event recalculates every student of the course; a participation event recalculates one.
     */
    public function test_a_reference_date_event_recalculates_the_whole_course(): void {
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::REFERENCE_DATE_REACHED,
            ['cmid' => (int) $this->pastassign->cmid, 'reference_date' => $this->now - 1000],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);

        $result = $this->handler->handle($event, $processing);

        $this->assertTrue($result->success, (string) $result->response());
        foreach ($this->students as $student) {
            $this->assertArrayHasKey($this->sections[0], $this->rows_of($processing, $student));
        }
        $this->assertCount(2, $this->context_of($processing)['course_access']);
    }

    /**
     * A user who is not a student is done with nothing written.
     */
    public function test_a_user_who_is_not_a_student_writes_nothing(): void {
        global $DB;
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        [$result, $processing] = $this->participation_event($teacher);

        $this->assertTrue($result->success);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id));
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'frequency']));
    }

    /**
     * Q13, error: a failure of a reader fails the attempt and writes nothing; it is never turned into a frequency of zero.
     */
    public function test_a_reader_failure_is_an_error_and_writes_nothing(): void {
        global $DB;
        $broken = new class extends participation_reader {
            #[\Override]
            public function get_participation(int $courseid, int $userid, int $cmid, \stdClass|false|null $finalgrade = false): ?\stdClass {
                throw new \RuntimeException('completion unavailable');
            }
        };
        $handler = new frequency_handler(null, null, null, null, $broken, null, null, null, fn() => $this->now);
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::PARTICIPATION_CHANGED,
            ['cmid' => (int) $this->pastassign->cmid, 'userid' => (int) $this->students[0]->id, 'source' => 'x', 'occurred_at' => 1],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());

        $result = $handler->handle($event, $this->events->find_processing((int) $event->id));

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'frequency']));
        $this->assertSame(0, $DB->count_records('local_assistente_frequency'));
    }

    /**
     * Idempotency and history: a repeated delivery does nothing more; a new event is a new analysis and the earlier one is untouched.
     */
    public function test_repeated_delivery_is_idempotent_and_history_is_kept(): void {
        global $DB;
        [, $first, $event] = $this->participation_event();
        $again = $this->handler->handle($event, $first);
        $this->now += 100;
        [, $second] = $this->participation_event();

        $this->assertTrue($again->success);
        $this->assertSame(2, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'frequency']));
        $this->assertCount(2, $this->repository->find_history_for_student((int) $this->course->id, (int) $this->students[0]->id));
        $this->assertSame($this->rows_of($first, $this->students[0]), $this->rows_of($first, $this->students[0]));
        $this->assertSame($this->now - 100, $this->context_of($first)['calculated_at']);
        $this->assertSame($this->now, $this->context_of($second)['calculated_at']);
    }

    /**
     * Registered for the two events and run by the real queue, under the lock of the event.
     */
    public function test_registered_and_run_by_the_queue(): void {
        global $DB;
        $this->assertInstanceOf(frequency_handler::class, handler_registry::default()->handler_for(event_type::PARTICIPATION_CHANGED));
        $this->assertInstanceOf(frequency_handler::class, handler_registry::default()->handler_for(event_type::REFERENCE_DATE_REACHED));
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::PARTICIPATION_CHANGED,
            ['cmid' => (int) $this->pastassign->cmid, 'userid' => (int) $this->students[0]->id, 'source' => 'x', 'occurred_at' => 9],
            time()
        );
        $registry = new handler_registry();
        $registry->register(event_type::PARTICIPATION_CHANGED, $this->handler);

        (new queue_processor(null, $registry))->run_cycle();

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $event->id]));
        $this->assertNotNull($this->repository->find_by_processing((int) $this->events->find_processing((int) $event->id)->id));
    }
}
