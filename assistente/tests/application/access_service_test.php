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

namespace local_assistente\application;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the access service (quickstart 005 Q1 to Q3 and Q7 to Q9; DEC-30, DEC-45 D1).
 *
 * The checks go through the real Access API with the default archetypes of Moodle, so they are the first thing to
 * confirm in a running Moodle 5.2 (CL-26, T041).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(access_service::class)]
final class access_service_test extends \advanced_testcase {
    /**
     * Role id by short name.
     *
     * @param string $shortname
     * @return int
     */
    private function role_id(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * Q1: a teacher (editing) accesses the course that they teach, to read and to configure.
     */
    public function test_editing_teacher_accesses_own_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $service = new access_service();

        $this->assertTrue($service->can_view($course->id, (int) $teacher->id));
        $this->assertTrue($service->can_manage($course->id, (int) $teacher->id));
        $service->require_view($course->id, (int) $teacher->id);
        $service->require_manage($course->id, (int) $teacher->id);
    }

    /**
     * Q2: a teacher cannot access a course that they do not teach, even by calling the backend directly.
     */
    public function test_other_course_is_denied_on_direct_backend_call(): void {
        $this->resetAfterTest();
        $own = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($own, 'editingteacher');
        $service = new access_service();

        $this->assertFalse($service->can_view($other->id, (int) $teacher->id));
        $this->assertFalse($service->can_manage($other->id, (int) $teacher->id));

        $this->setUser($teacher);
        $this->expectException(\core\exception\required_capability_exception::class);
        $service->require_view($other->id);
    }

    /**
     * Writing is denied on the other course as well.
     */
    public function test_manage_is_denied_on_other_course(): void {
        $this->resetAfterTest();
        $own = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($own, 'editingteacher');

        $this->setUser($teacher);
        $this->expectException(\core\exception\required_capability_exception::class);
        (new access_service())->require_manage($other->id);
    }

    /**
     * Q3: the site administrator accesses any course.
     */
    public function test_site_administrator_accesses_any_course(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_course();
        $second = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $service = new access_service();

        foreach ([$first, $second] as $course) {
            $this->assertTrue($service->can_view($course->id));
            $this->assertTrue($service->can_manage($course->id));
            $service->require_view($course->id);
        }
        $this->assertContains((int) $first->id, $service->accessible_course_ids());
        $this->assertContains((int) $second->id, $service->accessible_course_ids());
    }

    /**
     * Q7: a non-editing teacher does not receive the capability by default.
     */
    public function test_non_editing_teacher_is_denied_without_an_explicit_grant(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $service = new access_service();

        $this->assertFalse($service->can_view($course->id, (int) $teacher->id));
        $this->assertFalse($service->can_manage($course->id, (int) $teacher->id));

        // DEC-30: an administrator can grant it through the Moodle permissions.
        assign_capability(access_service::VIEW, CAP_ALLOW, $this->role_id('teacher'), \context_system::instance()->id, true);
        $this->assertTrue($service->can_view($course->id, (int) $teacher->id));
        $this->assertFalse($service->can_manage($course->id, (int) $teacher->id), 'Granting view does not grant manage');
    }

    /**
     * Students, guests and users without enrolment are denied.
     */
    public function test_students_guests_and_strangers_are_denied(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $stranger = $this->getDataGenerator()->create_user();
        $service = new access_service();

        foreach ([(int) $student->id, (int) $stranger->id, (int) guest_user()->id] as $userid) {
            $this->assertFalse($service->can_view($course->id, $userid), "user {$userid}");
            $this->assertFalse($service->can_manage($course->id, $userid), "user {$userid}");
        }
        $this->assertSame([], $service->accessible_course_ids((int) $student->id));
    }

    /**
     * Q8: the capability in the system context (default of the manager) gives access to every course.
     */
    public function test_capability_in_the_system_context_gives_access_to_all_courses(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_course();
        $second = $this->getDataGenerator()->create_course();
        $manager = $this->getDataGenerator()->create_user();
        role_assign($this->role_id('manager'), $manager->id, \context_system::instance()->id);
        $service = new access_service();

        $this->assertTrue($service->can_view($first->id, (int) $manager->id));
        $this->assertTrue($service->can_view($second->id, (int) $manager->id));
        $this->assertTrue($service->can_manage($second->id, (int) $manager->id));
        $this->assertEqualsCanonicalizing(
            [(int) $first->id, (int) $second->id],
            array_values(array_intersect($service->accessible_course_ids((int) $manager->id), [(int) $first->id, (int) $second->id]))
        );
    }

    /**
     * Q8, by capability and not by role: a custom role with the capability in the system context works the same.
     */
    public function test_a_custom_role_with_the_capability_works_like_the_manager(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $systemcontext = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'assistenteauditor', 'name' => 'Auditor']);
        assign_capability(access_service::VIEW, CAP_ALLOW, $roleid, $systemcontext->id, true);
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, $systemcontext->id);
        $service = new access_service();

        $this->assertTrue($service->can_view($course->id, (int) $user->id));
        $this->assertFalse($service->can_manage($course->id, (int) $user->id));
    }

    /**
     * Q9: a teacher with the capability removed is denied: the decision is by capability, not by role.
     */
    public function test_teacher_with_the_capability_removed_is_denied(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $service = new access_service();
        $this->assertTrue($service->can_view($course->id, (int) $teacher->id), 'Precondition');

        assign_capability(
            access_service::VIEW,
            CAP_PROHIBIT,
            $this->role_id('editingteacher'),
            \context_course::instance($course->id)->id,
            true
        );

        $this->assertFalse($service->can_view($course->id, (int) $teacher->id));
        $this->assertTrue($service->can_manage($course->id, (int) $teacher->id), 'The two capabilities are independent');
        $this->setUser($teacher);
        $this->expectException(\core\exception\required_capability_exception::class);
        $service->require_view($course->id);
    }

    /**
     * Courses listed for a teacher are only the ones they teach.
     */
    public function test_accessible_courses_of_a_teacher(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_course();
        $second = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($first, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $second->id, 'editingteacher');

        $ids = (new access_service())->accessible_course_ids((int) $teacher->id);

        $this->assertSame([(int) $first->id, (int) $second->id], $ids);
    }

    /**
     * A course that does not exist: the checks say no, and the requirement fails with an exception.
     */
    public function test_missing_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new access_service();

        $this->assertFalse($service->can_view(987654));
        $this->assertFalse($service->can_manage(987654));
        $this->expectException(\core\exception\moodle_exception::class);
        $service->require_view(987654);
    }
}
