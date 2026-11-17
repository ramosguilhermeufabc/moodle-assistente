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

namespace local_assistente;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the navigation entry of the course (T123; quickstart 021 Q1 and Q5; spec-021).
 *
 * The secondary navigation is built by the core of Moodle 5.2, which dispatches the hook; the registration of the callback in a running
 * site (cache of the hooks after the installation or the upgrade) is validated in CL-41 (T129).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
final class navigation_test extends \advanced_testcase {
    /**
     * The secondary navigation of a course page, built as the core does.
     *
     * @param \stdClass $course
     * @return \core\navigation\views\secondary
     */
    private function secondary_of(\stdClass $course): \core\navigation\views\secondary {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_course($course);
        $PAGE->set_pagelayout('course');
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->secondarynav->initialise();
        return $PAGE->secondarynav;
    }

    /**
     * The callback is registered for the secondary navigation hook.
     */
    public function test_the_hook_is_registered(): void {
        $callbacks = \core\di::get(\core\hook\manager::class)->get_callbacks_for_hook(\core\hook\navigation\secondary_extend::class);
        $found = array_filter($callbacks, static fn($callback) => ($callback['component'] ?? '') === 'local_assistente');
        $this->assertCount(1, $found);
    }

    /**
     * A user with the view capability sees the entry, pointing to the page of the course.
     */
    public function test_the_entry_is_shown_with_the_view_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $node = $this->secondary_of($course)->find(hook_callbacks::NODE_KEY, null);
        $this->assertNotFalse($node);
        $this->assertSame(get_string('nav:assistant', 'local_assistente'), $node->text);
        $this->assertStringContainsString('/local/assistente/index.php?id=' . $course->id, $node->action->out(false));
    }

    /**
     * Without the view capability (student, non-editing teacher) there is no entry.
     */
    public function test_the_entry_is_hidden_without_the_view_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        foreach (['student', 'teacher'] as $role) {
            $user = $this->getDataGenerator()->create_and_enrol($course, $role);
            $this->setUser($user);
            $this->assertFalse($this->secondary_of($course)->find(hook_callbacks::NODE_KEY, null), "{$role} must not see the entry");
        }
    }
}
