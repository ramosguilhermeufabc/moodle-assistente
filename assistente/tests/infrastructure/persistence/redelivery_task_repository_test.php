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

use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\domain\event\event_type;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the repository of the retry tasks that hold a delivery (redelivery of an event; exception to DEC-32 D2; DEC-57).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(redelivery_task_repository::class)]
final class redelivery_task_repository_test extends \advanced_testcase {
    /** @var redelivery_task_repository */
    private redelivery_task_repository $tasks;

    /** @var event_redelivery_scheduler */
    private event_redelivery_scheduler $scheduler;

    /**
     * Real tasks are used.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->tasks = new redelivery_task_repository();
        $this->scheduler = new event_redelivery_scheduler();
    }

    /**
     * Schedule the delivery of a grade of a student in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $item
     * @return int the task
     */
    private function grade(int $userid, int $courseid, int $item = 7): int {
        return $this->scheduler->defer($courseid, event_type::GRADE_CHANGED, ['userid' => $userid, 'itemid' => $item, 'occurred_at' => 1790000000]);
    }

    /**
     * Take the attempts of a task, as Moodle does after the last one.
     *
     * @param int $taskid
     */
    private function exhaust(int $taskid): void {
        global $DB;
        $DB->set_field('task_adhoc', 'attemptsavailable', 0, ['id' => $taskid]);
        $DB->set_field('task_adhoc', 'faildelay', 7680, ['id' => $taskid]);
    }

    /**
     * What the scheduler writes this reader reads, the exhausted ones included; and what is not a delivery is left out.
     */
    public function test_it_reads_what_the_scheduler_writes_and_only_that(): void {
        global $DB;
        $first = $this->grade(11, 5);
        $second = $this->scheduler->defer(6, event_type::FORUM_POST_CREATED, ['cmid' => 4, 'discussionid' => 5, 'postid' => 6]);
        $this->exhaust($second);
        // A task of the same class with data that is not a delivery, and a task of another class that looks like one.
        $broken = new \local_assistente\task\redeliver_event();
        $broken->set_custom_data(['unexpected' => 1]);
        \core\task\manager::queue_adhoc_task($broken);
        $other = new \local_assistente\task\retry_student_erasure();
        $other->set_custom_data(['course_id' => 5, 'event_type' => 'grade_changed', 'payload' => ['userid' => 11]]);
        \core\task\manager::queue_adhoc_task($other);

        $deliveries = $this->tasks->deliveries();

        $this->assertSame([$first, $second], array_keys($deliveries));
        $this->assertSame(['course_id' => 5, 'event_type' => 'grade_changed', 'payload' => ['userid' => 11, 'itemid' => 7, 'occurred_at' => 1790000000]], $deliveries[$first]);
        $this->assertSame(3, $DB->count_records('task_adhoc', ['classname' => '\\local_assistente\\task\\redeliver_event']), 'The one without a delivery exists and is left out');
        $this->assertSame(4, $DB->count_records('task_adhoc'), 'The task of another class exists and is left out');
    }

    /**
     * By student and by course, with or without attempts left; the ones of other students and other courses are not reached.
     */
    public function test_it_finds_by_student_and_by_course_and_does_not_reach_the_others(): void {
        $a5 = $this->grade(11, 5);
        $a6 = $this->grade(11, 6);
        $b5 = $this->grade(12, 5);
        $this->exhaust($a6);

        $this->assertSame([$a5, $a6], $this->tasks->task_ids_of_user(11));
        $this->assertSame([$a6], $this->tasks->task_ids_of_user(11, [6]));
        $this->assertSame([5, 6], $this->tasks->course_ids_of_user(11));
        $this->assertSame([5], $this->tasks->course_ids_of_user(12));
        $this->assertSame([], $this->tasks->course_ids_of_user(13));
        $this->assertSame([11, 12], $this->tasks->user_ids_in_course(5));
        $this->assertSame([11], $this->tasks->user_ids_in_course(6));
        $this->assertSame([], $this->tasks->user_ids_in_course(7));
        $this->assertSame([$b5], $this->tasks->task_ids_of_user(12));
    }

    /**
     * An event of no student is found by course, not by student.
     */
    public function test_a_delivery_of_no_student_belongs_to_the_course_only(): void {
        $this->scheduler->defer(5, event_type::FORUM_POST_CREATED, ['cmid' => 4, 'discussionid' => 5, 'postid' => 6]);

        $this->assertSame([], $this->tasks->user_ids_in_course(5));
        $this->assertSame(1, $this->tasks->delete_of_course(5));
        $this->assertSame([], $this->tasks->deliveries());
    }

    /**
     * Removing a student removes that student only, in the courses asked or in all; removing a course removes that course only, the
     * exhausted tasks included.
     */
    public function test_removal_by_student_and_by_course_touches_nothing_else(): void {
        $a5 = $this->grade(11, 5);
        $a6 = $this->grade(11, 6);
        $b5 = $this->grade(12, 5);
        $b6 = $this->grade(12, 6);
        $this->exhaust($b5);

        $this->assertSame(1, $this->tasks->delete_of_user(11, [5]));
        $this->assertSame([$a6, $b5, $b6], array_keys($this->tasks->deliveries()));
        $this->assertSame(1, $this->tasks->delete_of_course(5), 'The course 5 has only the exhausted task of 12 left');
        $this->assertSame([$a6, $b6], array_keys($this->tasks->deliveries()));
        $this->assertSame(1, $this->tasks->delete_of_user(11));
        $this->assertSame([$b6], array_keys($this->tasks->deliveries()));
        $this->assertSame(0, $this->tasks->delete_of_user(99));
        $this->assertSame(0, $this->tasks->delete_of_course(99));
        $this->assertTrue($this->tasks->exists($b6));
        $this->assertFalse($this->tasks->exists($a5));
    }
}
