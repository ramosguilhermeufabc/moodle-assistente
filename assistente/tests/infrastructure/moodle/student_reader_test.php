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
 * Tests of the student reader (DEC-37, DEC-43, DEC-47).
 *
 * Behaviour of the group and enrolment APIs in a running Moodle 5.2 is validated by CL-38 (T031).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(student_reader::class)]
final class student_reader_test extends \advanced_testcase {
    /**
     * Only active enrolments with a role evaluated by the gradebook are students.
     */
    public function test_students_are_active_enrolments_with_a_graded_role(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $active = $generator->create_and_enrol($course, 'student');
        $suspended = $generator->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $unenrolled = $generator->create_user();

        $reader = new student_reader();
        $students = $reader->get_students($course->id);

        $this->assertSame([(int) $active->id], $students);
        $this->assertNotContains((int) $suspended->id, $students, 'Suspended enrolment is not a student');
        $this->assertNotContains((int) $teacher->id, $students, 'A role not evaluated by the gradebook is not a student');
        $this->assertFalse($reader->is_student($course->id, (int) $unenrolled->id));
        $this->assertTrue($reader->is_student($course->id, (int) $active->id));
    }

    /**
     * The list of students does not depend on the state of the gradebook (T022 fix): with grades waiting to be recalculated, which
     * made get_gradable_users() throw grades/gradesneedregrading, the students are still listed; nothing is written.
     */
    public function test_students_are_listed_while_the_gradebook_needs_regrading(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'editingteacher');
        \grade_item::fetch_course_item($course->id);
        $DB->set_field('grade_items', 'needsupdate', 1, ['courseid' => $course->id]);
        $this->assertNotEmpty(grade_needs_regrade_final_grades($course->id), 'The fixture: the course needs regrading');
        $before = $DB->get_records('grade_items', ['courseid' => $course->id]);

        $students = (new student_reader())->get_students($course->id);

        $this->assertSame([(int) $student->id], $students);
        $this->assertEquals($before, $DB->get_records('grade_items', ['courseid' => $course->id]), 'Nothing was written');
    }
    /**
     * A student removed from the course leaves the list; a newly enrolled one appears (DEC-47).
     */
    public function test_list_follows_enrolment_at_query_time(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $first = $generator->create_and_enrol($course, 'student');
        $reader = new student_reader();
        $this->assertSame([(int) $first->id], $reader->get_students($course->id));

        $second = $generator->create_and_enrol($course, 'student');
        $this->assertCount(2, $reader->get_students($course->id));

        $plugin = enrol_get_plugin('manual');
        $instance = $this->get_manual_instance($course->id);
        $plugin->unenrol_user($instance, $first->id);
        $this->assertSame([(int) $second->id], $reader->get_students($course->id));
    }

    /**
     * The manual enrol instance of a course.
     *
     * @param int $courseid
     * @return \stdClass
     */
    private function get_manual_instance(int $courseid): \stdClass {
        global $DB;
        return $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
    }

    /**
     * Groups of the course and students by group, including a student in two groups.
     */
    public function test_groups_and_students_by_group(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $a = $generator->create_group(['courseid' => $course->id, 'name' => 'A']);
        $b = $generator->create_group(['courseid' => $course->id, 'name' => 'B']);
        $both = $generator->create_and_enrol($course, 'student');
        $onlya = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $both->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $both->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $onlya->id]);

        $reader = new student_reader();

        $this->assertSame([(int) $a->id => 'A', (int) $b->id => 'B'], $reader->get_groups($course->id));
        $this->assertEqualsCanonicalizing([(int) $both->id, (int) $onlya->id], $reader->get_students($course->id, $a->id));
        $this->assertSame([(int) $both->id], $reader->get_students($course->id, $b->id));
        $this->assertSame([], $reader->get_students($course->id, 987654));
    }

    /**
     * DEC-43 D2: in separate groups, a teacher without access to all groups sees only their own groups;
     * with the capability, sees every student.
     */
    public function test_separate_groups_restrict_the_teacher(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $a = $generator->create_group(['courseid' => $course->id, 'name' => 'A']);
        $b = $generator->create_group(['courseid' => $course->id, 'name' => 'B']);
        $studenta = $generator->create_and_enrol($course, 'student');
        $studentb = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $studenta->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $studentb->id]);

        // Non-editing teacher does not have moodle/site:accessallgroups by default.
        $teacher = $generator->create_and_enrol($course, 'teacher');
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $teacher->id]);
        // Editing teacher has the capability by default.
        $editing = $generator->create_and_enrol($course, 'editingteacher');

        $reader = new student_reader();

        $this->assertSame(SEPARATEGROUPS, $reader->group_mode($course->id));
        $this->assertTrue($reader->is_restricted_to_own_groups($course->id, (int) $teacher->id));
        $this->assertFalse($reader->is_restricted_to_own_groups($course->id, (int) $editing->id));

        $this->assertSame([(int) $a->id => 'A'], $reader->visible_groups($course->id, (int) $teacher->id));
        $this->assertSame([(int) $studenta->id], $reader->visible_students($course->id, (int) $teacher->id));
        $this->assertSame([], $reader->visible_students($course->id, (int) $teacher->id, (int) $b->id));
        $this->assertSame([(int) $studenta->id], $reader->visible_students($course->id, (int) $teacher->id, (int) $a->id));

        $this->assertCount(2, $reader->visible_groups($course->id, (int) $editing->id));
        $this->assertCount(2, $reader->visible_students($course->id, (int) $editing->id));

        // Granting the capability to the non-editing teacher lifts the restriction.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, \context_course::instance($course->id)->id, true);
        $this->assertFalse($reader->is_restricted_to_own_groups($course->id, (int) $teacher->id));
        $this->assertCount(2, $reader->visible_students($course->id, (int) $teacher->id));
    }

    /**
     * Names for presentation; empty input and unknown ids give nothing.
     */
    public function test_names(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Maria', 'lastname' => 'Silva']);
        $reader = new student_reader();

        $this->assertSame([], $reader->get_names([]));
        $names = $reader->get_names([(int) $user->id, 987654]);
        $this->assertCount(1, $names);
        $this->assertStringContainsString('Maria', $names[(int) $user->id]);
    }
}
