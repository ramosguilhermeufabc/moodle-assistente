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

/**
 * Behat data generator of local_assistente: the entities a scenario can create (T128).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Entities of the assistant that Behat scenarios create: `local_assistente > configs`, `criteria`, `syntheses` and `report_versions`.
 *
 * Each entity is created through the data generator of the plugin (tests/generator/lib.php); the columns that name a course or a section
 * are turned into identifiers here.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_assistente_generator extends behat_generator_base {
    #[\Override]
    protected function get_creatable_entities(): array {
        return [
            'configs' => [
                'singular' => 'config',
                'datagenerator' => 'config',
                'required' => ['course'],
                'switchids' => ['course' => 'course_id'],
            ],
            'criteria' => [
                'singular' => 'criterion',
                'datagenerator' => 'criteria',
                'required' => ['course'],
                'switchids' => ['course' => 'course_id'],
            ],
            'syntheses' => [
                'singular' => 'synthesis',
                'datagenerator' => 'pedagogical_synthesis',
                'required' => ['course', 'section'],
                'switchids' => ['course' => 'course_id'],
            ],
            'report_versions' => [
                'singular' => 'report_version',
                'datagenerator' => 'report_version',
                'required' => ['course'],
                'switchids' => ['course' => 'course_id'],
            ],
        ];
    }

    /**
     * The criteria belong to the configuration of the course, created when there is none.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_criteria(array $data): array {
        global $DB;
        $config = $DB->get_record('local_assistente_config', ['course_id' => $data['course_id']]);
        $generator = testing_util::get_data_generator()->get_plugin_generator('local_assistente');
        $data['config_id'] = $config ? $config->id : $generator->create_config(['course_id' => $data['course_id']])->id;
        unset($data['course_id']);
        return $data;
    }

    /**
     * The section is given by its number in the course.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_pedagogical_synthesis(array $data): array {
        global $DB;
        $data['section_id'] = $DB->get_field(
            'course_sections',
            'id',
            ['course' => $data['course_id'], 'section' => (int) $data['section']],
            MUST_EXIST
        );
        unset($data['section']);
        return $data;
    }

    /**
     * The version goes in the report of the course, created when there is none, with an empty manifest.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_report_version(array $data): array {
        global $DB;
        $generator = testing_util::get_data_generator()->get_plugin_generator('local_assistente');
        $report = $DB->get_record('local_assistente_report', ['course_id' => $data['course_id']]);
        $data['report_id'] = $report ? $report->id : $generator->create_report(['course_id' => $data['course_id']])->id;
        $builder = new \local_assistente\application\report\report_builder();
        $blocks = array_fill_keys(
            $builder::BLOCKS,
            ['state' => 'no_data', 'analysis_ids' => [], 'material_versions' => [], 'synthesis_versions' => []]
        );
        // The execution date can be given (`created_at`), and the manifest records the same instant, as the generator of reports does.
        $data['snapshot_json'] = $builder->build('periodic', (int) ($data['created_at'] ?? time()), $blocks);
        unset($data['course_id']);
        return $data;
    }
}
