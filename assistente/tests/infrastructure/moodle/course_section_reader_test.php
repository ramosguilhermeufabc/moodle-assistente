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

use local_assistente\domain\section\section_resolution_status;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the course/section reader (quickstart 004 Q5 to Q10).
 *
 * Which Moodle API behaves in which way is validated in a running Moodle 5.2 by CL-16 (T030).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_section_reader::class)]
final class course_section_reader_test extends \advanced_testcase {
    /**
     * Q5: a valid cmid in its course resolves to the course_sections.id of its section.
     */
    public function test_module_resolves_to_its_section_id(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id], ['section' => 2]);
        $expected = get_fast_modinfo($course->id)->get_section_info(2)->id;

        $resolution = (new course_section_reader())->resolve_module_section($course->id, $page->cmid);

        $this->assertTrue($resolution->is_resolved());
        $this->assertSame((int) $expected, $resolution->sectionid);
    }

    /**
     * Q6: a cmid that does not exist is an explicit unresolved condition, without a default value.
     */
    public function test_nonexistent_cmid_is_not_found(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $resolution = (new course_section_reader())->resolve_module_section($course->id, 987654);

        $this->assertFalse($resolution->is_resolved());
        $this->assertSame(section_resolution_status::CMID_NOT_FOUND, $resolution->status);
        $this->assertNull($resolution->sectionid);
    }

    /**
     * Q7: a cmid of another course is a condition distinct from a nonexistent cmid.
     */
    public function test_cmid_of_another_course_is_a_distinct_condition(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $other->id]);

        $resolution = (new course_section_reader())->resolve_module_section($course->id, $page->cmid);

        $this->assertSame(section_resolution_status::CMID_OTHER_COURSE, $resolution->status);
        $this->assertNotSame(section_resolution_status::CMID_NOT_FOUND, $resolution->status);
        $this->assertNull($resolution->sectionid);
    }

    /**
     * Q8: an explicit section id is accepted only if it exists in that course.
     */
    public function test_section_validation(): void {
        $this->resetAfterTest();
        $reader = new course_section_reader();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $other = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $sectionid = get_fast_modinfo($course->id)->get_section_info(1)->id;
        $othersectionid = get_fast_modinfo($other->id)->get_section_info(1)->id;

        $this->assertSame((int) $sectionid, $reader->validate_section($course->id, $sectionid)->sectionid);
        $this->assertSame(
            section_resolution_status::SECTION_INVALID,
            $reader->validate_section($course->id, $othersectionid)->status
        );
        $this->assertSame(
            section_resolution_status::SECTION_INVALID,
            $reader->validate_section($course->id, 987654)->status
        );
        $this->assertSame(
            section_resolution_status::SECTION_INVALID,
            $reader->validate_section(987654, $sectionid)->status
        );
    }

    /**
     * Q9: a module inside a subsection resolves to the delegated section.
     */
    public function test_module_in_subsection_resolves_to_the_delegated_section(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $subsection = $this->getDataGenerator()->create_module('subsection', ['course' => $course->id], ['section' => 1]);
        $delegated = get_fast_modinfo($course->id)->get_section_info_by_component('mod_subsection', $subsection->id);
        if ($delegated === null) {
            $this->markTestSkipped('mod_subsection is not available; subsection resolution is validated in CL-16 (T030).');
        }
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id], ['section' => $delegated->sectionnum]);

        $resolution = (new course_section_reader())->resolve_module_section($course->id, $page->cmid);

        $this->assertTrue($resolution->is_resolved());
        $this->assertSame((int) $delegated->id, $resolution->sectionid);
        $this->assertTrue($DB->record_exists('course_sections', ['id' => $resolution->sectionid, 'component' => 'mod_subsection']));
    }

    /**
     * Q10: resolving and validating never write to the plugin tables.
     */
    public function test_resolution_does_not_write_plugin_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $reader = new course_section_reader();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $reader->resolve_module_section($course->id, $page->cmid);
        $reader->resolve_module_section($course->id, 987654);
        $reader->validate_section($course->id, 987654);

        foreach ($DB->get_tables(false) as $table) {
            if (str_starts_with($table, 'local_assistente_')) {
                $this->assertSame(0, $DB->count_records($table), "{$table} must stay empty");
            }
        }
    }

    /**
     * A module scheduled for deletion is not resolved (hypothesis, CL-16).
     */
    public function test_module_being_deleted_is_not_resolved(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);

        $resolution = (new course_section_reader())->resolve_module_section($course->id, $page->cmid);

        $this->assertSame(section_resolution_status::CMID_NOT_FOUND, $resolution->status);
    }

    /**
     * Course data, sections, forced language, site language, time zone and end date.
     */
    public function test_course_level_reads(): void {
        $this->resetAfterTest();
        set_config('lang', 'en');
        $reader = new course_section_reader();
        $plain = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $forced = $this->getDataGenerator()->create_course(['lang' => 'pt_br', 'enddate' => 1900000000]);

        $this->assertNull($reader->get_course(987654));
        $this->assertSame([], $reader->get_sections(987654));
        $this->assertCount(3, $reader->get_sections($plain->id), 'Section 0 plus two sections');

        $this->assertNull($reader->forced_language($plain->id));
        $this->assertSame('pt_br', $reader->forced_language($forced->id));
        $this->assertSame('en', $reader->generation_language($plain->id));
        $this->assertSame('pt_br', $reader->generation_language($forced->id));

        $this->assertNull($reader->course_end_date($plain->id));
        $this->assertSame(1900000000, $reader->course_end_date($forced->id));
        $this->assertNotSame('', $reader->site_timezone());
    }
}
