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

namespace local_assistente\task;

use core\task\manager;
use local_assistente\application\frequency\reference_date_detector;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the detection of reference dates that have passed (T088; quickstart 014 Q11; DEC-39 D2).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(detect_reference_dates::class)]
#[CoversClass(reference_date_detector::class)]
final class detect_reference_dates_test extends \advanced_testcase {
    /** @var int */
    private int $now;

    /**
     * Real transactions and locks are used by the queue.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->now = time();
    }

    /**
     * A course, with the assistant enabled or not.
     *
     * @param int $enddate
     * @param bool $enabled
     * @return \stdClass
     */
    private function course(int $enddate = 0, bool $enabled = true): \stdClass {
        // The start date is explicit: the generator would use the midnight of the PHPUnit time zone, which is after an end date of "now minus a
        // few minutes" during the first minutes of the day, and the test would fail at that time of the day.
        $course = $this->getDataGenerator()->create_course(
            $enddate > 0 ? ['startdate' => $enddate - DAYSECS, 'enddate' => $enddate] : ['enddate' => $enddate]
        );
        if ($enabled) {
            (new config_repository())->create($course->id, true);
        }
        return $course;
    }

    /**
     * An assignment with a due date.
     *
     * @param \stdClass $course
     * @param int $due
     * @param array $more
     * @return \stdClass
     */
    private function assign(\stdClass $course, int $due, array $more = []): \stdClass {
        return $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'duedate' => $due] + $more);
    }

    /**
     * Queued `reference_date_reached` events as payloads.
     *
     * @return array[]
     */
    private function queued(): array {
        global $DB;
        return array_map(
            static fn($e) => [json_decode($e->payload, true)['cmid'], json_decode($e->payload, true)['reference_date']],
            array_values($DB->get_records('local_assistente_event', ['event_type' => 'reference_date_reached'], 'id ASC'))
        );
    }

    /**
     * The task is installed from db/tasks.php, every 15 minutes, with a name.
     */
    public function test_task_is_installed_every_15_minutes(): void {
        $task = manager::get_scheduled_task(detect_reference_dates::class);

        $this->assertNotFalse($task, 'The task must be installed from db/tasks.php');
        $this->assertSame('*/15', $task->get_minute());
        $this->assertSame('Detect reference dates reached', $task->get_name());
    }

    /**
     * Q11: a due date that passes without any event of Moodle queues the recalculation of the course, with the date.
     */
    public function test_a_due_date_that_passed_queues_the_recalculation(): void {
        $course = $this->course();
        $past = $this->assign($course, $this->now - 100);
        $this->assign($course, $this->now + 100000);

        (new reference_date_detector())->detect($this->now);

        $this->assertSame([[(int) $past->cmid, $this->now - 100]], $this->queued(), 'Only the one that passed');
    }

    /**
     * The same date queues once however many times the task runs; a date that passes later is a new event.
     */
    public function test_the_same_date_queues_once_and_a_later_date_queues_another(): void {
        $course = $this->course();
        $this->assign($course, $this->now - 100);
        $later = $this->assign($course, $this->now + 50);
        $detector = new reference_date_detector();

        $detector->detect($this->now);
        $detector->detect($this->now + 10);
        $this->assertCount(1, $this->queued());

        $detector->detect($this->now + 60);
        $this->assertCount(2, $this->queued());
        $this->assertSame((int) $later->cmid, $this->queued()[1][0]);
    }

    /**
     * Modules that share a reference date, and all those that fall back to the end of the course, queue one recalculation, with the lowest
     * module.
     */
    public function test_modules_with_the_same_reference_date_queue_one_event(): void {
        $course = $this->course($this->now - 500);
        $first = $this->assign($course, 0);
        $this->assign($course, 0);
        $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        (new reference_date_detector())->detect($this->now);

        $this->assertSame([[(int) $first->cmid, $this->now - 500]], $this->queued(), 'The end of the course, once');
    }

    /**
     * Resources, hidden modules, modules with no date at all, courses without the assistant and dates not yet reached queue nothing.
     */
    public function test_what_has_no_reference_date_that_passed_queues_nothing(): void {
        $course = $this->course();
        $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->assign($course, $this->now - 100, ['visible' => 0]);
        $this->assign($course, 0);
        $other = $this->course(0, false);
        $this->assign($other, $this->now - 100);

        (new reference_date_detector())->detect($this->now);

        $this->assertSame([], $this->queued());
    }

    /**
     * The date itself has not passed yet: strictly after, as in the calculator.
     */
    public function test_on_the_second_of_the_date_nothing_is_queued(): void {
        $course = $this->course();
        $this->assign($course, $this->now);

        (new reference_date_detector())->detect($this->now);
        $this->assertSame([], $this->queued());
        (new reference_date_detector())->detect($this->now + 1);
        $this->assertCount(1, $this->queued());
    }

    /**
     * Q11, end to end: with no event of Moodle, the date passes, the detection queues the recalculation, the real queue runs the frequency
     * handler, and the student who did not take part now has the activity in the denominator with participated = 0.
     */
    public function test_after_the_date_passes_the_student_has_the_activity_with_participated_zero(): void {
        global $DB;
        $course = $this->course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->assign($course, $this->now + 5);
        $registry = new \local_assistente\application\queue\handler_registry();
        $registry->register(
            \local_assistente\domain\event\event_type::REFERENCE_DATE_REACHED,
            new \local_assistente\application\frequency\frequency_handler(null, null, null, null, null, null, null, null, fn() => $this->now + 10)
        );

        (new reference_date_detector())->detect($this->now);
        $this->assertSame([], $this->queued(), 'Not yet');
        (new reference_date_detector())->detect($this->now + 10);
        (new \local_assistente\application\queue\queue_processor(null, $registry))->run_cycle();

        $repository = new \local_assistente\infrastructure\persistence\frequency_repository();
        $analysis = $repository->find_current_for_student((int) $course->id, (int) $student->id);
        $frequency = array_values($repository->find_frequencies((int) $analysis->id, (int) $student->id))[0];
        $activities = array_values($repository->find_activities((int) $frequency->id));
        $this->assertSame([1, 0], [(int) $frequency->total_activities, (int) $frequency->participating_activities]);
        $this->assertSame([(int) $assign->cmid], array_map(static fn($a) => (int) $a->activity_id, $activities));
        $this->assertSame(0, (int) $activities[0]->participated);
        // The frequency handler asked once for the criteria of the course (T141) and for the synthesis of the section (T098); this registry has no
        // handler for them, so the requests wait.
        $pending = $DB->get_fieldset_select('local_assistente_event', 'event_type', 'status = ?', ['pending']);
        $this->assertSame(['course_criteria_check_requested', 'synthesis_requested'], $pending, 'One for the criteria and one for the only section with a module (T098)');
    }

    /**
     * The task runs the detection and logs one line.
     */
    public function test_execute_queues_and_logs_one_line(): void {
        $course = $this->course();
        $this->assign($course, time() - 100);
        $this->expectOutputRegex('/reference date detection: 1 dates found, 1 queued/');

        (new detect_reference_dates())->execute();

        $this->assertCount(1, $this->queued());
    }
}
