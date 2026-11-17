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

/**
 * Tests of the plugin data generator.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generator_test extends \advanced_testcase {
    /**
     * Course configuration and criteria are created with the approved defaults.
     */
    public function test_config_and_criteria_defaults(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');

        $config = $generator->create_config();
        $this->assertSame('0', (string) $config->enabled, 'Disabled by default (DEC-45 D3)');
        $this->assertNotEmpty($config->course_id);

        $criteria = $generator->create_criteria(['config_id' => $config->id, 'forum_criterion' => 1]);
        $this->assertSame((int) $config->id, (int) $criteria->config_id);
        $this->assertEquals(60, $criteria->activity_criterion);
        $this->assertSame('1', (string) $criteria->forum_criterion);
    }

    /**
     * Values given by the test override the defaults.
     */
    public function test_given_values_override_defaults(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $course = $this->getDataGenerator()->create_course();

        $event = $generator->create_event(['course_id' => $course->id, 'status' => 'failure', 'event_type' => 'forum_post']);
        $this->assertSame((int) $course->id, (int) $event->course_id);
        $this->assertSame('failure', $event->status);
        $this->assertSame('forum_post', $event->event_type);
    }

    /**
     * Materials created in sequence do not collide on the unique index (course_id, cmid).
     */
    public function test_materials_have_unique_natural_keys(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $course = $this->getDataGenerator()->create_course();

        $first = $generator->create_material(['course_id' => $course->id]);
        $second = $generator->create_material(['course_id' => $course->id, 'material_type' => 'text']);

        $this->assertNotEquals($first->cmid, $second->cmid);
        $this->assertSame('text', $second->material_type);
        $this->assertSame(2, $DB->count_records('local_assistente_material', ['course_id' => $course->id]));
    }
    /**
     * The result of the criteria of a student comes with its analysis and the three statuses (T092).
     */
    public function test_criteria_result_has_its_analysis_and_statuses(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');

        $result = $generator->create_criteria_result(['forum_status' => 2]);
        $analysis = $DB->get_record('local_assistente_analysis', ['id' => $result->analysis_id], '*', MUST_EXIST);

        $this->assertSame('criteria_check', $analysis->analysis_type);
        $this->assertSame([1, 2, 1], [(int) $result->activity_status, (int) $result->forum_status, (int) $result->frequency_status]);
        $this->assertTrue($DB->record_exists('user', ['id' => $result->student_id]));
    }

    /**
     * The versions of a synthesis come numbered per section, each with its own analysis of the type section_synthesis (T096).
     */
    public function test_syntheses_are_numbered_per_section(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $second = (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 2]);

        $one = $generator->create_pedagogical_synthesis(['course_id' => $course->id]);
        $two = $generator->create_pedagogical_synthesis(['course_id' => $course->id]);
        $other = $generator->create_pedagogical_synthesis(['course_id' => $course->id, 'section_id' => $second]);

        $this->assertSame([1, 2, 1], [(int) $one->version, (int) $two->version, (int) $other->version]);
        $this->assertSame(64, strlen($one->input_fingerprint));
        $this->assertSame('section_synthesis', $DB->get_field('local_assistente_analysis', 'analysis_type', ['id' => $one->analysis_id]));
    }
}