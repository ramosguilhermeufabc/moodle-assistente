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

namespace local_assistente\form;

use local_assistente\application\criteria_immutable_exception;
use local_assistente\application\course_config_service;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the configuration form (quickstart 020 Q1 to Q3 and Q5 to Q8; DEC-45 D1 and D2; DEC-40 D1; CL-42, the
 * manager recognised through `local/assistente:correctcriteria`).
 *
 * The form is submitted through moodleform::mock_submit() and the validated data goes through course_config_form::submit(), which
 * calls the real configuration service, so the permissions are those of Moodle. The rejection of a form with an invalid session key is
 * the Form API itself (moodleform::_process_submission() throws `invalidsesskey`), exercised in test_invalid_sesskey_is_not_accepted (CL-40, T122).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_config_form::class)]
final class course_config_form_test extends \advanced_testcase {
    /**
     * The service uses real transactions, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
    }

    /**
     * A course and a user with a role in it.
     *
     * @param string $role
     * @return array [course, user]
     */
    private function course_with(string $role): array {
        $course = $this->getDataGenerator()->create_course();
        return [$course, $this->getDataGenerator()->create_and_enrol($course, $role)];
    }

    /**
     * Submit the form with the given values and return it.
     *
     * @param int $courseid
     * @param array $values submitted fields
     * @param bool $locked whether the criteria are shown read only
     * @return course_config_form
     */
    private function submitted(int $courseid, array $values, bool $locked = false): course_config_form {
        $custom = ['courseid' => $courseid, 'criteria_locked' => $locked];
        $service = new course_config_service();
        $custom['criteria'] = $service->current_criteria($courseid);
        $custom['enabled'] = $service->is_enabled($courseid);
        course_config_form::mock_submit(['id' => $courseid] + $values);
        return new course_config_form(null, $custom);
    }

    /**
     * Q1: valid criteria and the enabled flag are persisted through the service.
     */
    public function test_valid_submission_is_persisted(): void {
        [$course, $teacher] = $this->course_with('editingteacher');
        $this->setUser($teacher);

        $form = $this->submitted($course->id, [
            'enabled' => 1, 'activity_criterion' => '60', 'forum_criterion' => 1, 'frequency_criterion' => '75,5',
        ]);
        $this->assertTrue($form->is_validated());
        course_config_form::submit($form->get_data());

        $service = new course_config_service();
        $this->assertTrue($service->is_enabled($course->id));
        $criteria = $service->current_criteria($course->id);
        $this->assertEqualsWithDelta(60.0, (float) $criteria->activity_criterion, 1e-9);
        $this->assertSame(1, (int) $criteria->forum_criterion);
        $this->assertEqualsWithDelta(75.5, (float) $criteria->frequency_criterion, 1e-9, 'A decimal comma is read as a decimal point');
    }

    /**
     * Q6: values out of the range or with too many decimals are refused by the validation of the form.
     */
    public function test_invalid_percentages_are_rejected(): void {
        [$course, $teacher] = $this->course_with('editingteacher');
        $this->setUser($teacher);

        foreach (['100.5', '-1', '60.1234', '', 'abc', '1e2'] as $bad) {
            $form = $this->submitted($course->id, ['activity_criterion' => $bad, 'frequency_criterion' => '50']);
            $this->assertFalse($form->is_validated(), "'{$bad}' must be refused");
        }
        $form = $this->submitted($course->id, ['activity_criterion' => '50', 'frequency_criterion' => '101']);
        $this->assertFalse($form->is_validated());
        foreach (['0', '100', '33.333'] as $good) {
            $this->assertTrue($this->submitted($course->id, ['activity_criterion' => $good, 'frequency_criterion' => '50'])->is_validated());
        }
        $this->assertNull((new course_config_service())->current_criteria($course->id), 'Validation alone persists nothing');
    }

    /**
     * Q2: a teacher who submits other values over saved criteria is rejected and the criteria stay.
     */
    public function test_teacher_cannot_change_saved_criteria(): void {
        [$course, $teacher] = $this->course_with('editingteacher');
        $this->setUser($teacher);
        $first = $this->submitted($course->id, ['activity_criterion' => '60', 'frequency_criterion' => '70', 'forum_criterion' => 0]);
        course_config_form::submit($first->get_data());

        $again = (object) ['id' => $course->id, 'enabled' => 0, 'activity_criterion' => '10', 'forum_criterion' => 1, 'frequency_criterion' => '20'];
        try {
            course_config_form::submit($again);
            $this->fail('A teacher must not change saved criteria');
        } catch (criteria_immutable_exception $e) {
            $criteria = (new course_config_service())->current_criteria($course->id);
            $this->assertEqualsWithDelta(60.0, (float) $criteria->activity_criterion, 1e-9);
            $this->assertEqualsWithDelta(70.0, (float) $criteria->frequency_criterion, 1e-9);
        }
    }

    /**
     * Q7: the saved criteria are shown read only to the teacher; and a submission of the frozen form changes nothing.
     */
    public function test_saved_criteria_are_read_only_for_the_teacher(): void {
        [$course, $teacher] = $this->course_with('editingteacher');
        $this->setUser($teacher);
        $this->submitted($course->id, ['activity_criterion' => '60', 'frequency_criterion' => '70']);
        course_config_form::submit($this->submitted($course->id, ['enabled' => 1, 'activity_criterion' => '60', 'frequency_criterion' => '70'])->get_data());

        $service = new course_config_service();
        $this->assertFalse($service->can_edit_criteria($course->id));
        $form = $this->submitted($course->id, ['enabled' => 1, 'activity_criterion' => '5', 'frequency_criterion' => '5'], true);
        $quickform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        foreach (['activity_criterion', 'forum_criterion', 'frequency_criterion'] as $name) {
            $this->assertTrue($quickform->isElementFrozen($name), "{$name} must be frozen");
        }
        $this->assertTrue($form->is_validated());
        course_config_form::submit($form->get_data());
        $this->assertEqualsWithDelta(60.0, (float) $service->current_criteria($course->id)->activity_criterion, 1e-9);
    }

    /**
     * Q3 and Q5: a user without `manage` (only `view`, or none) is refused in the backend and nothing is persisted.
     */
    public function test_user_without_manage_is_refused_before_persisting(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/assistente:view', CAP_ALLOW, $roleid, \context_course::instance($course->id)->id, true);
        $this->getDataGenerator()->role_assign($roleid, $viewer->id, \context_course::instance($course->id)->id);
        $outsider = $this->getDataGenerator()->create_user();

        $data = (object) ['id' => $course->id, 'enabled' => 1, 'activity_criterion' => '60', 'forum_criterion' => 0, 'frequency_criterion' => '70'];
        foreach ([$viewer, $outsider] as $user) {
            $this->setUser($user);
            try {
                course_config_form::submit($data);
                $this->fail('A user without manage must be refused');
            } catch (\core\exception\required_capability_exception $e) {
                $this->assertSame(0, $DB->count_records('local_assistente_config'));
                $this->assertSame(0, $DB->count_records('local_assistente_criteria'));
            }
        }
    }

    /**
     * Q8: the administrator corrects the saved criteria; the new values are the ones in force.
     */
    public function test_administrator_corrects_the_criteria(): void {
        [$course, $teacher] = $this->course_with('editingteacher');
        $this->setUser($teacher);
        course_config_form::submit($this->submitted($course->id, ['activity_criterion' => '60', 'frequency_criterion' => '70'])->get_data());

        $this->setAdminUser();
        $service = new course_config_service();
        $this->assertTrue($service->can_edit_criteria($course->id));
        $form = $this->submitted($course->id, ['activity_criterion' => '50', 'frequency_criterion' => '70']);
        $this->assertTrue($form->is_validated());
        course_config_form::submit($form->get_data());

        $this->assertEqualsWithDelta(50.0, (float) $service->current_criteria($course->id)->activity_criterion, 1e-9);
    }

    /**
     * Q8 (CL-42): a manager, not only the site administrator, corrects the saved criteria the same way.
     */
    public function test_manager_corrects_the_criteria(): void {
        global $DB;
        [$course, $teacher] = $this->course_with('editingteacher');
        $this->setUser($teacher);
        course_config_form::submit($this->submitted($course->id, ['activity_criterion' => '60', 'frequency_criterion' => '70'])->get_data());

        $manager = $this->getDataGenerator()->create_user();
        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST), $manager->id, \context_system::instance()->id);
        $this->setUser($manager);
        $service = new course_config_service();
        $this->assertTrue($service->can_edit_criteria($course->id));
        $form = $this->submitted($course->id, ['activity_criterion' => '50', 'frequency_criterion' => '70']);
        $this->assertTrue($form->is_validated());
        course_config_form::submit($form->get_data());

        $this->assertEqualsWithDelta(50.0, (float) $service->current_criteria($course->id)->activity_criterion, 1e-9);
    }

    /**
     * Q14 (a), CL-40: a submission with an invalid session key is not accepted by the Form API and nothing is persisted.
     */
    public function test_invalid_sesskey_is_not_accepted(): void {
        global $DB;
        [$course, $teacher] = $this->course_with('editingteacher');
        $this->setUser($teacher);

        course_config_form::mock_submit(['id' => $course->id, 'enabled' => 1, 'activity_criterion' => '60', 'frequency_criterion' => '70']);
        $_POST['sesskey'] = 'invalidkey';
        $custom = ['courseid' => $course->id, 'criteria_locked' => false, 'criteria' => null, 'enabled' => false];
        try {
            new course_config_form(null, $custom);
            $this->fail('The Form API must refuse a submission with an invalid sesskey');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidsesskey', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_assistente_config'));
        $this->assertSame(0, $DB->count_records('local_assistente_criteria'));
    }
}
