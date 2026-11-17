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

use local_assistente\application\grade\grade_event_service;
use local_assistente\application\grade\performance_handler;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the grade observer (T079; DEC-37 D1), through the real gradebook API of Moodle 5.2.4 and the real queue.
 *
 * Emission validated by running the real operations (`grade_update`, a change, a removal with a null grade, the deletion of a grade
 * with grade_grade::delete(), and the totals recalculated by the gradebook); what the pages of the grader report and of the assignment
 * emit is for CL-34 (T080).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(grade_observer::class)]
#[CoversClass(grade_event_service::class)]
final class grade_observer_test extends \advanced_testcase {
    /** @var string[] short names of the grade events recorded by the current action */
    private static array $recorded = [];

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \stdClass */
    private \stdClass $assign;

    /**
     * Real transactions and locks are used by the queue. A course with the assistant, a student and a graded activity.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($this->course->id, true);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assign->id, 0, null, [
            'itemname' => 'Atividade', 'gradetype' => GRADE_TYPE_VALUE, 'grademin' => 0, 'grademax' => 100,
        ]);
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        foreach ($observers as $i => $observer) {
            $observers[$i] += ['includefile' => null, 'priority' => 0];
        }
        $observers[] = [
            'eventname' => '*',
            'callback' => 'local_assistente\observer\grade_observer_test::record',
            'includefile' => null,
            'priority' => 0,
            'internal' => false,
        ];
        \core\event\manager::phpunit_replace_observers($observers);
        self::$recorded = [];
    }

    /**
     * Observer that records the grade events next to the observers of the plugin.
     *
     * @param \core\event\base $event
     */
    public static function record(\core\event\base $event): void {
        if ($event instanceof \core\event\user_graded || $event instanceof \core\event\grade_deleted) {
            self::$recorded[] = (new \ReflectionClass($event))->getShortName() . ':' . (int) ($event->other['itemid'] ?? 0);
        }
    }

    /**
     * Give or remove the grade of the student.
     *
     * @param float|null $grade
     */
    private function grade(?float $grade): void {
        $this->assertSame(GRADE_UPDATE_OK, grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assign->id, 0, [
            'userid' => $this->student->id, 'rawgrade' => $grade,
        ]));
    }

    /**
     * Queued `grade_changed` events, as payloads.
     *
     * @return array[]
     */
    private function queued(): array {
        global $DB;
        return array_map(
            static fn($e) => json_decode($e->payload, true),
            array_values($DB->get_records('local_assistente_event', ['event_type' => 'grade_changed'], 'id ASC'))
        );
    }

    /**
     * The grade item of the activity.
     *
     * @return \grade_item
     */
    private function item(): \grade_item {
        return \grade_item::fetch(['courseid' => $this->course->id, 'itemmodule' => 'assign', 'iteminstance' => $this->assign->id]);
    }

    /**
     * The observers are registered after the commit, with callbacks that exist.
     */
    public function test_observers_are_registered_after_the_commit(): void {
        global $CFG;
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        $byname = array_column($observers, null, 'eventname');

        foreach (['\core\event\user_graded', '\core\event\grade_deleted'] as $name) {
            $this->assertArrayHasKey($name, $byname);
            $this->assertTrue(is_callable($byname[$name]['callback']), $name);
            $this->assertFalse($byname[$name]['internal'], $name);
            $this->assertTrue(class_exists(ltrim($name, '\\')));
        }
    }

    /**
     * VALIDATED with the real gradebook API: giving a grade emits user_graded for the item, and also for the totals above it; the queue
     * gets one grade_changed for each, with the course, the student and the item taken from the event.
     */
    public function test_giving_a_grade_enqueues_the_item_and_its_totals(): void {
        $this->grade(80.0);

        $payloads = $this->queued();

        $itemids = array_column($payloads, 'itemid');
        $this->assertContains((int) $this->item()->id, $itemids, 'The item of the activity');
        $this->assertContains((int) \grade_item::fetch_course_item($this->course->id)->id, $itemids, 'The total of the course, recalculated');
        $this->assertSame([(int) $this->student->id], array_values(array_unique(array_column($payloads, 'userid'))));
        $this->assertContains('user_graded:' . $this->item()->id, self::$recorded);
    }

    /**
     * VALIDATED: a change of the grade emits user_graded again; the removal (a null grade) also does, so that the handler sees the
     * activity without a grade.
     */
    public function test_change_and_removal_are_enqueued(): void {
        $this->grade(80.0);
        $before = count($this->queued());
        $this->waitForSecond();
        self::$recorded = [];

        $this->grade(60.0);
        $afterchange = count($this->queued());
        $this->waitForSecond();
        $this->grade(null);

        $this->assertGreaterThan($before, $afterchange, 'A change is a new occurrence');
        $this->assertGreaterThan($afterchange, count($this->queued()), 'A removal is another');
        $this->assertContains('user_graded:' . $this->item()->id, self::$recorded);
    }

    /**
     * VALIDATED: deleting the grade of a user (grade_grade::delete) emits grade_deleted, which is queued.
     */
    public function test_deleting_a_grade_enqueues_it(): void {
        global $DB;
        $this->grade(80.0);
        $before = count($this->queued());
        $this->waitForSecond();
        self::$recorded = [];
        $grade = \grade_grade::fetch(['itemid' => $this->item()->id, 'userid' => $this->student->id]);

        $grade->delete('test');

        $this->assertContains('grade_deleted:' . $this->item()->id, self::$recorded);
        $this->assertGreaterThan($before, count($this->queued()));
        $this->assertGreaterThan(0, $DB->count_records('local_assistente_event', ['event_type' => 'grade_changed']));
    }

    /**
     * A repeated event of the same occurrence keeps one queue event.
     */
    public function test_a_repeated_occurrence_keeps_one_queue_event(): void {
        $item = $this->item();
        $this->grade(70.0);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $this->student->id]);
        $grade->load_grade_item();
        $before = count($this->queued());

        \core\event\user_graded::create_from_grade($grade)->trigger();
        \core\event\user_graded::create_from_grade($grade)->trigger();

        $this->assertLessThanOrEqual($before + 1, count($this->queued()), 'At most one new occurrence in the same second');
    }

    /**
     * A course without the assistant enabled gets nothing.
     */
    public function test_course_without_the_assistant_enqueues_nothing(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, null, ['itemname' => 'A', 'gradetype' => GRADE_TYPE_VALUE, 'grademax' => 10]);
        $before = $DB->count_records('local_assistente_event');

        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, ['userid' => $student->id, 'rawgrade' => 5.0]);

        $this->assertSame($before, $DB->count_records('local_assistente_event'));
    }

    /**
     * Incomplete data is refused: nothing is queued and the failure is logged, not thrown to the action that triggered the event.
     */
    public function test_incomplete_event_data_is_refused_safely(): void {
        global $DB;
        $before = $DB->count_records('local_assistente_event');

        \core\event\grade_deleted::create([
            'objectid' => 1,
            'context' => \context_course::instance($this->course->id),
            'relateduserid' => $this->student->id,
            'other' => ['itemid' => 0, 'overridden' => false, 'finalgrade' => null],
        ])->trigger();

        $this->assertSame($before, $DB->count_records('local_assistente_event'));
        // One failure for each independent delivery: the grade of the student and the participation (T089).
        $this->assertDebuggingCalledCount(2);
    }

    /**
     * A failure of the queue is logged and does not break the action that triggered the event.
     */
    public function test_a_failure_is_contained_and_logged(): void {
        $service = new class extends grade_event_service {
            /**
             * Constructor, without the real collaborators.
             */
            public function __construct() {
            }

            #[\Override]
            public function grade_changed(int $courseid, int $userid, int $itemid, int $occurredat): \local_assistente\application\enqueue_result {
                throw new \RuntimeException('queue unavailable');
            }
        };
        grade_observer::use_service($service);

        $this->grade(50.0);

        $this->assertDebuggingCalledCount(2, null, null, 'One failure for the item and one for the total above it');
        grade_observer::use_service(null);
    }

    /**
     * From the gradebook to the result: the events of a real grade are processed by the queue and the analysis of the student has the
     * grade of the activity.
     */
    public function test_a_real_grade_reaches_the_performance_through_the_queue(): void {
        global $DB;
        $this->grade(40.0);
        $registry = new handler_registry();
        $registry->register(event_type::GRADE_CHANGED, new performance_handler());

        (new queue_processor(null, $registry))->run_cycle();

        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => 'grade_changed', 'status' => 'pending']));
        $repository = new \local_assistente\infrastructure\persistence\performance_repository();
        $current = $repository->find_current_for_student((int) $this->course->id, (int) $this->student->id);
        $rows = array_values($repository->find_rows((int) $current->id));
        $this->assertEquals(40.0, $rows[0]->grade);
    }

    /**
     * While the data of the student are being erased (the lock of the student is held, DEC-57) the observer cannot enqueue the event of the
     * grade now. Since the redelivery (exception to DEC-32 D2) the failure is not a loss to report: the observer waits a few seconds once,
     * no event is born, nothing is reported as lost, the grade itself is saved, and each of the three deliveries is held by a retry task
     * that creates its event when the lock is free.
     */
    public function test_an_event_refused_during_the_erasure_of_the_student_is_handed_to_a_retry_not_lost(): void {
        global $DB;
        $held = \local_assistente\infrastructure\persistence\unit_of_work::lock_factory()->get_lock(
            \local_assistente\infrastructure\persistence\unit_of_work::privacy_user_resource((int) $this->student->id),
            1
        );
        $this->assertNotFalse($held);
        $before = $DB->count_records('local_assistente_event');

        try {
            $this->grade(80.0);
        } finally {
            $held->release();
        }

        $this->assertSame($before, $DB->count_records('local_assistente_event'), 'No event of the student was born');
        $this->assertSame([], $this->getDebuggingMessages(), 'Nothing is reported as lost: the deliveries are held by retry tasks');
        $this->assertEquals(80.0, $DB->get_field('grade_grades', 'finalgrade', ['itemid' => $this->item()->id, 'userid' => $this->student->id]));
        // The item (grade and participation) and the total of the course (grade): three deliveries, three tasks.
        $tasks = \core\task\manager::get_adhoc_tasks(\local_assistente\task\redeliver_event::class);
        $this->assertCount(3, $tasks);

        // With the lock free the tasks deliver, each one once: the events are born.
        foreach ($tasks as $task) {
            ob_start();
            $task->execute();
            ob_end_clean();
        }
        $this->assertSame($before + 3, $DB->count_records('local_assistente_event'));

        // A later delivery, when the memory of the failed lock is gone, is enqueued directly.
        \local_assistente\application\delivery\observer_event_service::reset();
        $this->waitForSecond(); // The identity of an occurrence has second precision.
        $this->grade(81.0);
        $this->assertGreaterThan($before + 3, $DB->count_records('local_assistente_event'));
        $this->assertCount(3, \core\task\manager::get_adhoc_tasks(\local_assistente\task\redeliver_event::class), 'No new task');
    }
}
