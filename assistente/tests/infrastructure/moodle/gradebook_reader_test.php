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
 * Tests of the gradebook reader (quickstart 004 Q2; DEC-37).
 *
 * Substitutions, scales and the total after a regrade in a running Moodle 5.2 are validated by CL-34 (T034).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(gradebook_reader::class)]
final class gradebook_reader_test extends \advanced_testcase {
    /**
     * Set the final grade of a user in the grade item of an activity.
     *
     * @param \stdClass $course
     * @param string $modname
     * @param \stdClass $module generated module (instance id in ->id)
     * @param \stdClass $user
     * @param float $grade
     */
    private function grade(\stdClass $course, string $modname, \stdClass $module, \stdClass $user, float $grade): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $item = \grade_item::fetch([
            'courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => $modname,
            'iteminstance' => $module->id, 'itemnumber' => 0,
        ]);
        $this->assertNotFalse($item, 'The activity must have a grade item');
        $item->update_final_grade($user->id, $grade, 'test');
    }

    /**
     * Final grade, range and type of an activity; a student without a grade has null, not zero.
     */
    public function test_final_grade_of_an_activity(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $graded = $generator->create_and_enrol($course, 'student');
        $ungraded = $generator->create_and_enrol($course, 'student');
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $this->grade($course, 'assign', $assign, $graded, 73.5);
        $reader = new gradebook_reader();

        $grade = $reader->get_final_grade($course->id, $assign->cmid, (int) $graded->id);
        $this->assertSame((int) $assign->cmid, $grade->cmid);
        $this->assertSame('value', $grade->grade_type);
        $this->assertEqualsWithDelta(73.5, $grade->final_grade, 0.0001);
        $this->assertEqualsWithDelta(0.0, $grade->grade_min, 0.0001);
        $this->assertEqualsWithDelta(100.0, $grade->grade_max, 0.0001);
        $this->assertFalse($grade->excluded);

        $this->assertNull($reader->get_final_grade($course->id, $assign->cmid, (int) $ungraded->id)->final_grade);
    }

    /**
     * A zero grade is a grade (value 0.0), different from no grade (null).
     */
    public function test_zero_is_different_from_no_grade(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $this->grade($course, 'assign', $assign, $student, 0.0);

        $grade = (new gradebook_reader())->get_final_grade($course->id, $assign->cmid, (int) $student->id);

        $this->assertNotNull($grade->final_grade);
        $this->assertEqualsWithDelta(0.0, $grade->final_grade, 0.0001);
    }

    /**
     * Items of the activities, with the range; a minimum other than zero is preserved; scales are told apart.
     */
    public function test_items_and_scales(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $scale = $generator->create_scale(['scale' => 'Fraco,Medio,Bom,Otimo']);
        $value = $generator->create_module('assign', ['course' => $course->id, 'grade' => 20, 'name' => 'Prova']);
        $scaled = $generator->create_module('assign', ['course' => $course->id, 'grade' => -$scale->id, 'name' => 'Conceito']);
        $page = $generator->create_module('page', ['course' => $course->id]);

        $items = (new gradebook_reader())->get_items($course->id);

        $this->assertCount(2, $items, 'Only graded activities; a page has no grade item');
        $byname = array_column($items, null, 'name');
        $this->assertSame('value', $byname['Prova']->grade_type);
        $this->assertEqualsWithDelta(20.0, $byname['Prova']->grade_max, 0.0001);
        $this->assertSame('scale', $byname['Conceito']->grade_type);
        $this->assertEqualsWithDelta(4.0, $byname['Conceito']->grade_max, 0.0001);
        $this->assertSame((int) $value->cmid, $byname['Prova']->cmid);
        $this->assertSame([], (new gradebook_reader())->get_items(987654));
    }

    /**
     * A missing module gives null.
     */
    public function test_missing_module_gives_null(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $reader = new gradebook_reader();

        $this->assertNull($reader->get_final_grade($course->id, 987654, (int) $student->id));
        $this->assertNull($reader->get_final_grade($course->id, $page->cmid, (int) $student->id), 'No grade item');
    }

    /**
     * The course total is read after the regrade (R-012-01): natural aggregation of two items.
     */
    public function test_course_total_after_regrade(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $first = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $second = $generator->create_module('assign', ['course' => $course->id, 'grade' => 50]);
        $this->grade($course, 'assign', $first, $student, 80.0);
        $this->grade($course, 'assign', $second, $student, 40.0);

        $total = (new gradebook_reader())->get_course_total($course->id, (int) $student->id);

        $this->assertSame('value', $total->grade_type);
        $this->assertEqualsWithDelta(150.0, $total->grade_max, 0.0001);
        $this->assertEqualsWithDelta(120.0, $total->final_grade, 0.0001);
    }

    /**
     * A student with no grades has no course total (null), never zero.
     */
    public function test_course_total_without_grades_is_null(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);

        $total = (new gradebook_reader())->get_course_total($course->id, (int) $student->id);

        $this->assertNull($total->final_grade);
    }

    /**
     * Reading the grades of many students together gives, for each of them, what reading one at a time gives: a grade, zero, no grade, a
     * student with no row in the item, and null for all of them when the module does not exist; and it reads less (A2).
     */
    public function test_grades_read_together_equal_grades_read_one_by_one(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $graded = $generator->create_and_enrol($course, 'student');
        $zero = $generator->create_and_enrol($course, 'student');
        $ungraded = $generator->create_and_enrol($course, 'student');
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $this->grade($course, 'assign', $assign, $graded, 73.5);
        $this->grade($course, 'assign', $assign, $zero, 0.0);
        $userids = [(int) $graded->id, (int) $zero->id, (int) $ungraded->id, 987654];
        $reader = new gradebook_reader();

        $before = $DB->perf_get_reads();
        $together = $reader->get_final_grades($course->id, $assign->cmid, $userids);
        $readstogether = $DB->perf_get_reads() - $before;
        $before = $DB->perf_get_reads();
        $single = [];
        foreach ($userids as $userid) {
            $single[$userid] = $reader->get_final_grade($course->id, $assign->cmid, $userid);
        }
        $readssingle = $DB->perf_get_reads() - $before;

        $this->assertEquals($single, $together);
        $this->assertSame(array_keys($single), array_keys($together));
        $this->assertLessThan($readssingle, $readstogether);
        $this->assertSame([5 => null], $reader->get_final_grades($course->id, 987654, [5]));
    }}
