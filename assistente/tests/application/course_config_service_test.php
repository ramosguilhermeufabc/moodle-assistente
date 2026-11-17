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

use local_assistente\application\sync\initial_sync;
use local_assistente\domain\criteria\assistant_state;
use local_assistente\infrastructure\persistence\invalid_domain_value_exception;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the course configuration service (quickstart 005 Q4 to Q6; quickstart 020 Q5, Q7 to Q9, Q11 to Q13; DEC-45;
 * CL-42, the manager recognised through `local/assistente:correctcriteria`).
 *
 * The real access service and repositories are used, so the permissions are those of Moodle. The technical way the
 * initial synchronization is triggered, and the permission model in a running Moodle 5.2, are validated by CL-40 and
 * CL-26 (T122 and T041).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_config_service::class)]
#[CoversClass(capability_correction_policy::class)]
final class course_config_service_test extends \advanced_testcase {
    /** @var int[] courses synchronized, in order */
    private array $synced = [];

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
     * Real transactions are used by the service, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->synced = [];
    }

    /**
     * A service whose initial synchronization records the courses it ran for.
     *
     * @return course_config_service
     */
    private function service(): course_config_service {
        $test = $this;
        $sync = new class($test) implements initial_sync {
            /**
             * Constructor.
             *
             * @param course_config_service_test $test
             */
            public function __construct(
                /** @var course_config_service_test test that collects the calls */
                private readonly course_config_service_test $test
            ) {
            }

            #[\Override]
            public function run(int $courseid): void {
                $this->test->record_sync($courseid);
            }
        };
        return new course_config_service(null, null, null, null, $sync);
    }

    /**
     * Record a synchronization (called by the fake).
     *
     * @param int $courseid
     * @internal
     */
    public function record_sync(int $courseid): void {
        $this->synced[] = $courseid;
    }

    /**
     * A course and an editing teacher of it.
     *
     * @return array [course, teacher]
     */
    private function course_with_teacher(): array {
        $course = $this->getDataGenerator()->create_course();
        return [$course, $this->getDataGenerator()->create_and_enrol($course, 'editingteacher')];
    }

    /**
     * Q9: a new course has no configuration; the assistant is not enabled and nothing is processed.
     */
    public function test_new_course_is_not_enabled(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $service = $this->service();

        $this->assertSame(assistant_state::NOT_CONFIGURED, $service->state($course->id));
        $this->assertFalse($service->is_enabled($course->id));
        $this->assertFalse($service->state($course->id)->is_enabled());
        $this->assertNull($service->current_criteria($course->id));
        $this->assertSame(0, $DB->count_records('local_assistente_config'), 'Reading the state creates nothing');
        $this->assertFalse($service->is_enabled(987654), 'A missing course is not enabled');
    }

    /**
     * Enabling creates the configuration enabled, runs the initial synchronization once, and is idempotent.
     */
    public function test_enable_creates_the_configuration_and_runs_the_initial_sync_once(): void {
        global $DB;
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();

        $this->assertTrue($service->enable($course->id, (int) $teacher->id));
        $this->assertSame(assistant_state::ENABLED, $service->state($course->id));
        $this->assertTrue($service->is_enabled($course->id));
        $this->assertSame([(int) $course->id], $this->synced);
        $this->assertSame(1, $DB->count_records('local_assistente_config'));

        $this->assertFalse($service->enable($course->id, (int) $teacher->id), 'Already enabled: nothing to do');
        $this->assertSame([(int) $course->id], $this->synced, 'No second synchronization while enabled');
    }

    /**
     * Q13: enabling without criteria is allowed; the course stays enabled with no criteria.
     */
    public function test_enable_without_criteria(): void {
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();

        $service->enable($course->id, (int) $teacher->id);

        $this->assertTrue($service->is_enabled($course->id));
        $this->assertNull($service->current_criteria($course->id));
    }

    /**
     * Disabling clears only the flag: the configuration, the criteria and the history stay (DEC-45 D3).
     */
    public function test_disable_keeps_the_history(): void {
        global $DB;
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();
        $service->enable($course->id, (int) $teacher->id);
        $service->save_criteria($course->id, 60, 1, 40, (int) $teacher->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $generator->create_event(['course_id' => $course->id, 'status' => 'processed']);
        $generator->create_event(['course_id' => $course->id, 'status' => 'pending']);

        $this->assertTrue($service->disable($course->id, (int) $teacher->id));

        $this->assertSame(assistant_state::DISABLED, $service->state($course->id));
        $this->assertFalse($service->is_enabled($course->id));
        $this->assertSame(1, $DB->count_records('local_assistente_config'));
        $this->assertNotNull($service->current_criteria($course->id), 'The criteria stay');
        $this->assertSame(2, $DB->count_records('local_assistente_event'), 'Events and history are not deleted');
        $this->assertFalse($service->disable($course->id, (int) $teacher->id), 'Already disabled: nothing to do');
    }

    /**
     * Disabling a course that has no configuration does nothing and creates nothing.
     */
    public function test_disable_without_configuration_creates_nothing(): void {
        global $DB;
        [$course, $teacher] = $this->course_with_teacher();

        $this->assertFalse($this->service()->disable($course->id, (int) $teacher->id));

        $this->assertSame(0, $DB->count_records('local_assistente_config'));
    }

    /**
     * Enabling again after disabling runs the initial synchronization again (DEC-45 D3), without duplicating the
     * configuration.
     */
    public function test_reenabling_runs_the_initial_sync_again(): void {
        global $DB;
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();

        $service->enable($course->id, (int) $teacher->id);
        $service->disable($course->id, (int) $teacher->id);
        $this->assertTrue($service->enable($course->id, (int) $teacher->id));

        $this->assertTrue($service->is_enabled($course->id));
        $this->assertSame([(int) $course->id, (int) $course->id], $this->synced);
        $this->assertSame(1, $DB->count_records('local_assistente_config'));
    }

    /**
     * The synchronization runs after the enabled flag is saved, so the events it creates see an enabled course.
     */
    public function test_sync_sees_the_course_already_enabled(): void {
        [$course, $teacher] = $this->course_with_teacher();
        $seen = new \ArrayObject();
        $service = new course_config_service(null, null, null, null, new class($seen) implements initial_sync {
            /**
             * Constructor.
             *
             * @param \ArrayObject $seen
             */
            public function __construct(
                /** @var \ArrayObject states seen by the sync */
                private readonly \ArrayObject $seen
            ) {
            }

            #[\Override]
            public function run(int $courseid): void {
                global $DB;
                $this->seen->append([(new course_config_service())->is_enabled($courseid), $DB->is_transaction_started()]);
            }
        });

        $service->enable($course->id, (int) $teacher->id);

        $this->assertSame([[true, false]], $seen->getArrayCopy(), 'Enabled, and no transaction open, when the sync runs');
    }

    /**
     * Without the manage capability nobody enables, disables or saves criteria, and nothing is written.
     */
    public function test_writes_require_the_manage_capability(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $nonediting = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $foreign = $this->getDataGenerator()->create_and_enrol($other, 'editingteacher');
        $service = $this->service();

        foreach ([$student, $nonediting, $foreign] as $user) {
            foreach ([
                'enable' => fn() => $service->enable($course->id, (int) $user->id),
                'disable' => fn() => $service->disable($course->id, (int) $user->id),
                'save_criteria' => fn() => $service->save_criteria($course->id, 60, 1, 40, (int) $user->id),
            ] as $operation => $call) {
                try {
                    $call();
                    $this->fail("User {$user->id} must not be able to {$operation}");
                } catch (\core\exception\required_capability_exception $e) {
                    $this->assertTrue(true);
                }
            }
        }
        $this->assertSame(0, $DB->count_records('local_assistente_config'));
        $this->assertSame(0, $DB->count_records('local_assistente_criteria'));
        $this->assertSame([], $this->synced);
    }

    /**
     * Having only the view capability is not enough to configure (quickstart 020 Q5).
     */
    public function test_view_without_manage_cannot_write(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability(access_service::VIEW, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        $service = $this->service();

        $this->assertNull($service->get_criteria($course->id, (int) $teacher->id), 'Reading is allowed');
        $this->expectException(\core\exception\required_capability_exception::class);
        $service->enable($course->id, (int) $teacher->id);
    }

    /**
     * Q4: the first save of the criteria creates the configuration (disabled) and the criteria, one set per course.
     */
    public function test_first_save_creates_configuration_and_criteria(): void {
        global $DB;
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();

        $criteria = $service->save_criteria($course->id, 60, 1, '40.5', (int) $teacher->id);

        $this->assertEqualsWithDelta(60.0, (float) $criteria->activity_criterion, 0.0001);
        $this->assertSame('1', (string) $criteria->forum_criterion);
        $this->assertEqualsWithDelta(40.5, (float) $criteria->frequency_criterion, 0.0001);
        $this->assertSame(assistant_state::DISABLED, $service->state($course->id), 'Saving criteria does not enable');
        $this->assertSame(1, $DB->count_records('local_assistente_config'));
        $this->assertSame(1, $DB->count_records('local_assistente_criteria'));
        $this->assertSame((int) $criteria->id, (int) $service->get_criteria($course->id, (int) $teacher->id)->id);
    }

    /**
     * Q5 and quickstart 020 Q7: a teacher cannot change saved criteria; they are read only.
     * Q6: nothing is recalculated.
     */
    public function test_teacher_cannot_change_saved_criteria(): void {
        global $DB;
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();
        $service->save_criteria($course->id, 60, 1, 40, (int) $teacher->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $generator->create_event(['course_id' => $course->id]);

        $this->assertFalse($service->can_edit_criteria($course->id, (int) $teacher->id));
        try {
            $service->save_criteria($course->id, 50, 0, 30, (int) $teacher->id);
            $this->fail('The criteria are read only for the teacher');
        } catch (criteria_immutable_exception $e) {
            $this->assertTrue(true);
        }

        $stored = $service->current_criteria($course->id);
        $this->assertEqualsWithDelta(60.0, (float) $stored->activity_criterion, 0.0001);
        $this->assertSame(1, $DB->count_records('local_assistente_event'), 'No event was created by the attempt');
        $this->assertSame(0, $DB->count_records('local_assistente_analysis'), 'Nothing was recalculated (Q6)');
    }

    /**
     * Quickstart 020 Q8: the site administrator corrects the criteria; no reprocessing is triggered; a teacher still cannot.
     */
    public function test_administrator_corrects_without_reprocessing(): void {
        global $DB;
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();
        $service->save_criteria($course->id, 60, 1, 40, (int) $teacher->id);
        $before = [
            'events' => $DB->count_records('local_assistente_event'),
            'analyses' => $DB->count_records('local_assistente_analysis'),
            'results' => $DB->count_records('local_assistente_criteria_result'),
        ];
        $this->setAdminUser();

        $this->assertTrue($service->can_edit_criteria($course->id));
        $corrected = $service->save_criteria($course->id, 50, 1, 40);

        $this->assertEqualsWithDelta(50.0, (float) $corrected->activity_criterion, 0.0001);
        $this->assertSame(1, $DB->count_records('local_assistente_criteria'), 'Corrected in place, not duplicated');
        $this->assertSame($before, [
            'events' => $DB->count_records('local_assistente_event'),
            'analyses' => $DB->count_records('local_assistente_analysis'),
            'results' => $DB->count_records('local_assistente_criteria_result'),
        ]);
        $this->assertFalse($service->can_edit_criteria($course->id, (int) $teacher->id));
    }

    /**
     * Quickstart 020 Q8 (CL-42): the manager corrects the criteria like the site administrator; no reprocessing is
     * triggered, and the teacher is still refused.
     */
    public function test_manager_corrects_without_reprocessing(): void {
        global $DB;
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();
        $service->save_criteria($course->id, 60, 1, 40, (int) $teacher->id);
        $before = [
            'events' => $DB->count_records('local_assistente_event'),
            'analyses' => $DB->count_records('local_assistente_analysis'),
            'results' => $DB->count_records('local_assistente_criteria_result'),
        ];
        $manager = $this->getDataGenerator()->create_user();
        role_assign($this->role_id('manager'), $manager->id, \context_system::instance()->id);

        $this->assertTrue($service->can_edit_criteria($course->id, (int) $manager->id));
        $corrected = $service->save_criteria($course->id, 50, 1, 40, (int) $manager->id);

        $this->assertEqualsWithDelta(50.0, (float) $corrected->activity_criterion, 0.0001);
        $this->assertSame(1, $DB->count_records('local_assistente_criteria'), 'Corrected in place, not duplicated');
        $this->assertSame($before, [
            'events' => $DB->count_records('local_assistente_event'),
            'analyses' => $DB->count_records('local_assistente_analysis'),
            'results' => $DB->count_records('local_assistente_criteria_result'),
        ]);
        $this->assertFalse($service->can_edit_criteria($course->id, (int) $teacher->id));
    }

    /**
     * CL-42: `manage` is checked before `correctcriteria`, and that order stays. A manager whose `manage` was
     * prohibited in the course is refused before the correction policy is even reached.
     */
    public function test_manager_without_manage_cannot_correct(): void {
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();
        $service->save_criteria($course->id, 60, 1, 40, (int) $teacher->id);

        $manager = $this->getDataGenerator()->create_user();
        $roleid = $this->role_id('manager');
        role_assign($roleid, $manager->id, \context_system::instance()->id);
        assign_capability(access_service::MANAGE, CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id, true);

        $this->assertFalse($service->can_edit_criteria($course->id, (int) $manager->id));
        $this->expectException(\core\exception\required_capability_exception::class);
        $service->save_criteria($course->id, 50, 1, 40, (int) $manager->id);
    }

    /**
     * The default correction policy (CL-42) recognises the site administrator through has_capability()'s
     * `$doanything`, with no role assignment needed; and uses the current user when `$userid` is null.
     */
    public function test_capability_policy_recognises_the_site_administrator(): void {
        [$course, $teacher] = $this->course_with_teacher();
        $policy = new capability_correction_policy();

        $this->assertFalse($policy->can_correct($course->id, (int) $teacher->id));
        $this->assertTrue($policy->can_correct($course->id, (int) get_admin()->id));

        $this->setAdminUser();
        $this->assertTrue($policy->can_correct($course->id), 'Current user by default when $userid is null');
    }

    /**
     * CL-42: a manager is recognised in the "contexto apropriado" of DEC-45 D2 at the system, category and course
     * context, through the manager's own default context levels and role inheritance (never by role name); a
     * manager of a different category or course is not recognised outside it. Also covers the current user when
     * `$userid` is null.
     */
    public function test_capability_policy_recognises_a_manager_at_system_category_or_course_context(): void {
        [$course] = $this->course_with_teacher();
        $policy = new capability_correction_policy();
        $roleid = $this->role_id('manager');

        $atsystem = $this->getDataGenerator()->create_user();
        role_assign($roleid, $atsystem->id, \context_system::instance()->id);
        $this->assertTrue($policy->can_correct($course->id, (int) $atsystem->id));
        $this->setUser($atsystem);
        $this->assertTrue($policy->can_correct($course->id), 'Current user by default when $userid is null');

        $atcategory = $this->getDataGenerator()->create_user();
        role_assign($roleid, $atcategory->id, \context_coursecat::instance($course->category)->id);
        $this->assertTrue($policy->can_correct($course->id, (int) $atcategory->id));

        $othercategory = $this->getDataGenerator()->create_category();
        $othercourse = $this->getDataGenerator()->create_course(['category' => $othercategory->id]);
        $this->assertFalse(
            $policy->can_correct((int) $othercourse->id, (int) $atcategory->id),
            'A manager of another category does not correct a course outside it'
        );

        $atcourse = $this->getDataGenerator()->create_user();
        role_assign($roleid, $atcourse->id, \context_course::instance($course->id)->id);
        $this->assertTrue($policy->can_correct($course->id, (int) $atcourse->id));
        $this->assertFalse(
            $policy->can_correct((int) $othercourse->id, (int) $atcourse->id),
            'A manager assigned only in one course does not correct another'
        );
    }

    /**
     * CL-42: the editing teacher, who has `manage` by default, does not have `correctcriteria` by default.
     */
    public function test_capability_policy_denies_the_teacher(): void {
        [$course, $teacher] = $this->course_with_teacher();
        $policy = new capability_correction_policy();

        $this->assertFalse($policy->can_correct($course->id, (int) $teacher->id));
    }

    /**
     * CL-42: the policy is by capability, never by role name; a custom role granted `correctcriteria` is recognised
     * the same way the manager archetype is.
     */
    public function test_capability_policy_recognises_a_custom_role_with_the_capability_granted(): void {
        [$course] = $this->course_with_teacher();
        $systemcontext = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'assistentecorretor', 'name' => 'Corretor']);
        assign_capability(capability_correction_policy::CORRECT, CAP_ALLOW, $roleid, $systemcontext->id, true);
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, $systemcontext->id);
        $policy = new capability_correction_policy();

        $this->assertTrue($policy->can_correct($course->id, (int) $user->id));
    }

    /**
     * CL-42: a prohibition in the course context denies a manager who would otherwise be recognised through the
     * system context. A CAP_PROHIBIT found anywhere on the context path overrides any CAP_ALLOW for anyone but the
     * site administrator (verified by reading has_capability_in_accessdata() in the MOODLE_502_STABLE sources).
     */
    public function test_capability_policy_denies_a_manager_with_the_capability_prohibited(): void {
        [$course] = $this->course_with_teacher();
        $roleid = $this->role_id('manager');
        $manager = $this->getDataGenerator()->create_user();
        role_assign($roleid, $manager->id, \context_system::instance()->id);
        $policy = new capability_correction_policy();
        $this->assertTrue($policy->can_correct($course->id, (int) $manager->id), 'Precondition');

        assign_capability(
            capability_correction_policy::CORRECT,
            CAP_PROHIBIT,
            $roleid,
            \context_course::instance($course->id)->id,
            true
        );

        $this->assertFalse($policy->can_correct($course->id, (int) $manager->id));
    }

    /**
     * Quickstart 020 Q6: invalid values are rejected, and the first save leaves neither configuration nor criteria.
     */
    public function test_invalid_criteria_leave_nothing_behind(): void {
        global $DB;
        [$course, $teacher] = $this->course_with_teacher();
        $service = $this->service();

        foreach ([[100.5, 1, 40], [-1, 1, 40], [60.1234, 1, 40], [60, 2, 40], [60, 1, 101]] as [$activity, $forum, $frequency]) {
            try {
                $service->save_criteria($course->id, $activity, $forum, $frequency, (int) $teacher->id);
                $this->fail('An invalid value must be rejected');
            } catch (invalid_domain_value_exception $e) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, $DB->count_records('local_assistente_config'), 'The configuration created in the unit is rolled back');
        $this->assertSame(0, $DB->count_records('local_assistente_criteria'));
        $this->assertSame(assistant_state::NOT_CONFIGURED, $service->state($course->id));
    }

    /**
     * Reading the criteria needs the view capability; internal processing reads without a user.
     */
    public function test_reading_criteria_needs_the_view_capability(): void {
        [$course, $teacher] = $this->course_with_teacher();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $service = $this->service();
        $service->save_criteria($course->id, 60, 0, 40, (int) $teacher->id);

        $this->assertSame((int) $service->current_criteria($course->id)->id, (int) $service->get_criteria($course->id, (int) $teacher->id)->id);
        $this->expectException(\core\exception\required_capability_exception::class);
        $service->get_criteria($course->id, (int) $student->id);
    }

    /**
     * Editing is offered only to who may manage: a student never edits, a teacher edits until saving.
     */
    public function test_can_edit_criteria(): void {
        [$course, $teacher] = $this->course_with_teacher();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $service = $this->service();

        $this->assertTrue($service->can_edit_criteria($course->id, (int) $teacher->id), 'Nothing saved yet');
        $this->assertFalse($service->can_edit_criteria($course->id, (int) $student->id));
        $service->save_criteria($course->id, 60, 0, 40, (int) $teacher->id);
        $this->assertFalse($service->can_edit_criteria($course->id, (int) $teacher->id), 'Read only once saved');
    }
}
