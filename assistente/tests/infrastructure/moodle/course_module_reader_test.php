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
 * Tests of the course module reader (DEC-26, DEC-39).
 *
 * Availability per user and the date columns are validated in a running Moodle 5.2 by CL-36 (T032).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_module_reader::class)]
final class course_module_reader_test extends \advanced_testcase {
    /**
     * Section, name, type and kind of a module; a missing module or course gives nothing.
     */
    public function test_basic_module_data(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Intro'], ['section' => 2]);
        $reader = new course_module_reader();

        $module = $reader->get_module($course->id, $page->cmid);

        $this->assertSame((int) $page->cmid, $module->cmid);
        $this->assertSame((int) $course->id, $module->course_id);
        $this->assertSame('page', $module->modname);
        $this->assertSame('Intro', $module->name);
        $this->assertSame(2, $module->section_number);
        $this->assertSame((int) get_fast_modinfo($course->id)->get_section_info(2)->id, $module->section_id);
        $this->assertTrue($module->visible);
        $this->assertNull($module->available_to_user, 'Unset when no user is given');
        $this->assertNull($reader->get_module($course->id, 987654));
        $this->assertSame([], $reader->get_modules(987654));
    }

    /**
     * Resources and activities are told apart (DEC-39 D1).
     */
    public function test_resource_versus_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $reader = new course_module_reader();

        $this->assertTrue($reader->get_module($course->id, $page->cmid)->is_resource);
        $this->assertFalse($reader->get_module($course->id, $assign->cmid)->is_resource);
        $this->assertFalse($reader->get_module($course->id, $quiz->cmid)->is_resource);
    }

    /**
     * Availability per user: a hidden module and a module with an unmet restriction are not available to a
     * student; a visible, unrestricted one is (DEC-26).
     */
    public function test_availability_for_a_student(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $open = $generator->create_module('page', ['course' => $course->id]);
        $hidden = $generator->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $future = time() + 30 * DAYSECS;
        $restricted = $generator->create_module('page', [
            'course' => $course->id,
            'availability' => json_encode([
                'op' => '&', 'showc' => [true],
                'c' => [['type' => 'date', 'd' => '>=', 't' => $future]],
            ]),
        ]);

        $modules = (new course_module_reader())->get_modules($course->id, (int) $student->id);

        $this->assertTrue($modules[(int) $open->cmid]->available_to_user);
        $this->assertFalse($modules[(int) $hidden->cmid]->available_to_user);
        $this->assertFalse($modules[(int) $restricted->cmid]->available_to_user);
        $this->assertFalse($modules[(int) $hidden->cmid]->visible);
    }

    /**
     * The count by module follows the rule of get_modules() for each user: all, part and none of the users (T058, T064).
     */
    public function test_count_available_to_users(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $s1 = $generator->create_and_enrol($course, 'student');
        $s2 = $generator->create_and_enrol($course, 'student');
        $all = $generator->create_module('page', ['course' => $course->id]);
        $part = $generator->create_module('page', ['course' => $course->id]);
        $none = $generator->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $s1->id]);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&', 'showc' => [false], 'c' => [['type' => 'group', 'id' => (int) $group->id]],
        ]), ['id' => $part->cmid]);
        rebuild_course_cache((int) $course->id, true);
        $reader = new course_module_reader();

        $counts = $reader->count_available_to_users((int) $course->id, [(int) $s1->id, (int) $s2->id]);

        $this->assertSame(2, $counts[(int) $all->cmid]);
        $this->assertSame(1, $counts[(int) $part->cmid]);
        $this->assertArrayNotHasKey((int) $none->cmid, $counts, 'A module available to nobody is absent');
        $this->assertSame([], $reader->count_available_to_users((int) $course->id, []), 'No users, no counts');
        $this->assertSame(
            $counts,
            $reader->count_available_to_users((int) $course->id, [(int) $s1->id, (int) $s2->id, (int) $s1->id]),
            'A user listed twice is counted once'
        );
    }

    /**
     * "At least one user" gives the same answer as the count for every module, is false with no users, and stops at the first user
     * that has access (T058).
     */
    public function test_is_available_to_any_user_follows_the_count_and_stops_early(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $s1 = $generator->create_and_enrol($course, 'student');
        $s2 = $generator->create_and_enrol($course, 'student');
        $all = $generator->create_module('page', ['course' => $course->id]);
        $second = $generator->create_module('page', ['course' => $course->id]);
        $none = $generator->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $s2->id]);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&', 'showc' => [false], 'c' => [['type' => 'group', 'id' => (int) $group->id]],
        ]), ['id' => $second->cmid]);
        rebuild_course_cache((int) $course->id, true);
        $reader = new course_module_reader();
        $users = [(int) $s1->id, (int) $s2->id];
        $counts = $reader->count_available_to_users((int) $course->id, $users);

        foreach ([$all, $second, $none] as $module) {
            $this->assertSame(
                ($counts[(int) $module->cmid] ?? 0) > 0,
                $reader->is_available_to_any_user((int) $course->id, (int) $module->cmid, $users),
                'Same answer as the count'
            );
        }
        $this->assertTrue($reader->is_available_to_any_user((int) $course->id, (int) $second->cmid, $users), 'Only the second user has it');
        $this->assertFalse($reader->is_available_to_any_user((int) $course->id, (int) $all->cmid, []), 'No users');

        // The modinfo of each user is kept in memory by Moodle (DEC-72); it is cleared so that each measure pays for the users it reads.
        \course_modinfo::clear_instance_cache();
        $reads = $DB->perf_get_reads();
        $this->assertTrue($reader->is_available_to_any_user((int) $course->id, (int) $all->cmid, $users));
        $first = $DB->perf_get_reads() - $reads;
        \course_modinfo::clear_instance_cache();
        $reads = $DB->perf_get_reads();
        $reader->count_available_to_users((int) $course->id, $users);
        $this->assertLessThan($DB->perf_get_reads() - $reads, $first, 'Stops at the first user that has access');
    }

    /**
     * Due and closing dates come from the module table; unset dates are null; modules outside the map have none.
     */
    public function test_due_dates(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $due = time() + 7 * DAYSECS;
        $assign = $generator->create_module('assign', ['course' => $course->id, 'duedate' => $due]);
        $assignnodate = $generator->create_module('assign', ['course' => $course->id]);
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'timeclose' => $due + 3600]);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $reader = new course_module_reader();

        $this->assertSame($due, $reader->get_module($course->id, $assign->cmid)->due_date);
        $this->assertSame('duedate', $reader->get_module($course->id, $assign->cmid)->due_date_field);
        $this->assertNull($reader->get_module($course->id, $assignnodate->cmid)->due_date);
        $this->assertSame($due + 3600, $reader->get_module($course->id, $quiz->cmid)->due_date);
        $this->assertSame('timeclose', $reader->get_module($course->id, $quiz->cmid)->due_date_field);
        $this->assertNull($reader->get_module($course->id, $page->cmid)->due_date);
        $this->assertNull($reader->get_module($course->id, $page->cmid)->due_date_field);
    }

    /**
     * A module scheduled for deletion is not listed.
     */
    public function test_module_being_deleted_is_not_listed(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);

        $this->assertNull((new course_module_reader())->get_module($course->id, $page->cmid));
    }
}
