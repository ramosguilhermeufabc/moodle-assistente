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

use local_assistente\application\grade\performance_handler;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * CL-34 (T080): a grade written by the API of mod_assign, the path of the grading screen, reaches the performance analysis (DEC-37, DEC-53).
 *
 * The teacher's grade goes through assign::save_grade(), which is what the grading page calls; Moodle then emits `user_graded` for the
 * activity grade item (itemnumber 0, DEC-53) and for the totals above it, the observer queues `grade_changed`, and the queue processes
 * it with the real performance handler.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(grade_observer::class)]
#[CoversClass(performance_handler::class)]
final class assign_grading_api_test extends \advanced_testcase {
    /**
     * Real transactions and locks are used by the queue; the observers of the plugin, as in db/events.php.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        foreach ($observers as $i => $observer) {
            $observers[$i] += ['includefile' => null, 'priority' => 0];
        }
        \core\event\manager::phpunit_replace_observers($observers);
    }

    /**
     * The teacher grades the student with assign::save_grade(); the grade reaches the performance analysis.
     */
    public function test_a_grade_saved_by_the_assign_api_reaches_the_performance_analysis(): void {
        global $DB;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($course->id, true);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $cm = get_coursemodule_from_id('assign', $module->cmid, $course->id, false, MUST_EXIST);
        $assign = new \assign(\context_module::instance($module->cmid), $cm, $course);
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => 'grade_changed']));

        // The grading screen: the teacher saves the grade of the student.
        $this->setUser($teacher);
        $this->assertTrue($assign->save_grade($student->id, (object) [
            'grade' => 70, 'attemptnumber' => -1, 'addattempt' => 0, 'workflowstate' => '', 'applytoall' => 1,
        ]));

        // The grade is in the gradebook and the event of the activity item (itemnumber 0) is in the queue (DEC-53).
        $item = \grade_item::fetch([
            'courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $module->id, 'itemnumber' => 0,
        ]);
        $this->assertEquals(70.0, (float) $item->get_grade($student->id)->finalgrade);
        $events = array_map(
            static fn($r) => json_decode($r->payload, true),
            $DB->get_records('local_assistente_event', ['event_type' => 'grade_changed'], 'id ASC')
        );
        $this->assertContains((int) $item->id, array_column($events, 'itemid'), 'user_graded of the activity item reached the queue');
        $this->assertSame([(int) $student->id], array_values(array_unique(array_column($events, 'userid'))));
        $this->assertGreaterThanOrEqual(1, $DB->count_records('local_assistente_event', ['event_type' => 'participation_changed']));

        // The queue processes it with the real handler: the analysis of the student has the activity at 70%.
        $registry = new handler_registry();
        $registry->register(event_type::GRADE_CHANGED, new performance_handler());
        (new queue_processor(null, $registry))->run_cycle();

        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => 'grade_changed', 'status' => 'pending']));
        $repository = new \local_assistente\infrastructure\persistence\performance_repository();
        $current = $repository->find_current_for_student((int) $course->id, (int) $student->id);
        $this->assertNotNull($current);
        $rows = array_values($repository->find_rows((int) $current->id));
        $this->assertCount(1, $rows, 'One row per activity (DEC-53)');
        $this->assertEquals(70.0, (float) $rows[0]->grade);
    }
}
