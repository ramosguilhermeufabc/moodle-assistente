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

namespace local_assistente\output;

use local_assistente\application\course_config_service;
use local_assistente\application\sync\initial_sync;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the configuration page (T126; quickstart 020 Q1, Q5, Q7, Q9 and Q10; DEC-45).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(configure_page::class)]
final class configure_page_test extends \advanced_testcase {
    /** @var int[] courses synchronized by the recorder */
    public array $synced = [];

    /**
     * The service uses real transactions, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->synced = [];
    }

    /**
     * A page whose initial synchronization records the courses.
     *
     * @return configure_page
     */
    private function page(): configure_page {
        $recorder = new class($this) implements initial_sync {
            /**
             * Constructor.
             *
             * @param configure_page_test $test
             */
            public function __construct(
                /** @var configure_page_test test that collects the calls */
                private readonly configure_page_test $test
            ) {
            }

            #[\Override]
            public function run(int $courseid): void {
                $this->test->synced[] = $courseid;
            }
        };
        return new configure_page(new course_config_service(null, null, null, null, $recorder));
    }

    /**
     * Q1 and Q5: without `manage` the page gives nothing, not even the data of the form.
     */
    public function test_only_manage_opens_the_page(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->expectException(\core\exception\required_capability_exception::class);
        $this->page()->custom_data($course->id, $student->id);
    }

    /**
     * Q9 and Q10: a new course shows the assistant off and the criteria editable; saving enables it and runs the synchronization.
     */
    public function test_saving_enables_and_synchronizes(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $page = $this->page();

        $custom = $page->custom_data($course->id);
        $this->assertFalse($custom['enabled']);
        $this->assertFalse($custom['criteria_locked']);
        $this->assertNull($custom['criteria']);

        $page->save((object) ['id' => $course->id, 'enabled' => 1, 'activity_criterion' => '60', 'forum_criterion' => 1, 'frequency_criterion' => '75']);
        $this->assertSame([(int) $course->id], $this->synced);
        $after = $page->custom_data($course->id);
        $this->assertTrue($after['enabled']);
        $this->assertEqualsWithDelta(60.0, (float) $after['criteria']->activity_criterion, 1e-9);
    }

    /**
     * Q7: after the criteria are saved the form comes read only for the teacher, and editable for the administrator.
     */
    public function test_criteria_are_locked_for_the_teacher_after_saving(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $page = $this->page();
        $page->save((object) ['id' => $course->id, 'enabled' => 0, 'activity_criterion' => '60', 'forum_criterion' => 0, 'frequency_criterion' => '75']);

        $this->assertTrue($page->custom_data($course->id)['criteria_locked']);
        $this->setAdminUser();
        $this->assertFalse($page->custom_data($course->id)['criteria_locked']);
    }
    /**
     * A request built by hand over criteria that are already saved is answered with a message, nothing is changed, and the page does not
     * end in the error page of Moodle. A user without `manage` is still refused with the exception: it is not turned into a message.
     */
    public function test_a_forged_change_of_saved_criteria_is_explained_and_changes_nothing(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $page = $this->page();
        $this->assertNull($page->save_or_explain((object) [
            'id' => $course->id, 'enabled' => 0, 'activity_criterion' => '60', 'forum_criterion' => 0, 'frequency_criterion' => '75',
        ]), 'The first save is accepted');

        $message = $page->save_or_explain((object) [
            'id' => $course->id, 'enabled' => 1, 'activity_criterion' => '10', 'forum_criterion' => 1, 'frequency_criterion' => '20',
        ]);

        $this->assertSame(get_string('form:criteria_immutable', 'local_assistente'), $message);
        $criteria = (new course_config_service())->current_criteria($course->id);
        $this->assertEqualsWithDelta(60.0, (float) $criteria->activity_criterion, 1e-9);
        $this->assertEqualsWithDelta(75.0, (float) $criteria->frequency_criterion, 1e-9);
        $this->assertFalse((new course_config_service())->is_enabled($course->id), 'Not even the enabled flag of the refused request');

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->expectException(\core\exception\required_capability_exception::class);
        $page->save_or_explain((object) ['id' => $course->id, 'enabled' => 1, 'activity_criterion' => '60', 'forum_criterion' => 0, 'frequency_criterion' => '75'], $student->id);
    }
}
