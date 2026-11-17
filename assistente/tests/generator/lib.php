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
 * Data generator of the plugin tables for PHPUnit and Behat (DEC-48; plan 023 Q-023-05).
 *
 * Courses, users, groups, modules, grades and the other Moodle data come from the Moodle generators;
 * this class only creates rows of the plugin tables. It writes directly with $DB, independent of
 * the repositories under test, and is extended in each delivery with its own aggregates.
 *
 * @package    local_assistente
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Generator of rows of the local_assistente tables.
 *
 * @package    local_assistente
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_assistente_generator extends component_generator_base {
    /** @var int counter used to make natural keys unique */
    private int $sequence = 0;

    /**
     * Next unique number of this generator.
     *
     * @return int
     */
    private function next(): int {
        return ++$this->sequence;
    }

    /**
     * Insert a row, merging the defaults with the given record, and return it as stored.
     *
     * @param string $table logical table name
     * @param array $defaults
     * @param array|stdClass|null $record
     * @return stdClass
     */
    private function insert(string $table, array $defaults, $record): stdClass {
        global $DB;
        $row = (object) ((array) ($record ?? []) + $defaults);
        $row->id = $DB->insert_record($table, $row);
        return $DB->get_record($table, ['id' => $row->id], '*', MUST_EXIST);
    }

    /**
     * Id of the course of the record, creating a course when none is given.
     *
     * @param array|stdClass|null $record
     * @return int
     */
    private function course_id($record): int {
        $record = (array) ($record ?? []);
        return (int) ($record['course_id'] ?? $this->datagenerator->create_course()->id);
    }

    /**
     * Create the configuration of a course (disabled by default, DEC-45 D3).
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_config($record = null): stdClass {
        return $this->insert('local_assistente_config', [
            'course_id' => $this->course_id($record),
            'enabled' => 0,
            'created_at' => time(),
        ], $record);
    }

    /**
     * Create the criteria of a configuration (percentages from 0 to 100, DEC-40 D1).
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_criteria($record = null): stdClass {
        $given = (array) ($record ?? []);
        return $this->insert('local_assistente_criteria', [
            'config_id' => $given['config_id'] ?? $this->create_config()->id,
            'activity_criterion' => 60,
            'forum_criterion' => 0,
            'frequency_criterion' => 40,
        ], $record);
    }

    /**
     * Create an assistant event, pending by default.
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_event($record = null): stdClass {
        return $this->insert('local_assistente_event', [
            'course_id' => $this->course_id($record),
            'event_type' => 'test_event',
            'payload' => '{}',
            'created_at' => time(),
            'status' => 'pending',
            'processed_at' => null,
            'response' => null,
        ], $record);
    }

    /**
     * Create a processing, with a new pending event unless an event is given (T067).
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_processing($record = null): stdClass {
        $given = (array) ($record ?? []);
        return $this->insert('local_assistente_processing', [
            'event_id' => $given['event_id'] ?? $this->create_event()->id,
            'status' => 'pending',
            'created_at' => time(),
            'finished_at' => null,
        ], $record);
    }

    /**
     * Create a material. Unless given, the section is the first section of the course and the
     * cmid is a unique number that does not refer to a real course module.
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_material($record = null): stdClass {
        global $DB;
        $given = (array) ($record ?? []);
        $courseid = $this->course_id($record);
        $sectionid = $given['section_id']
            ?? $DB->get_field('course_sections', 'id', ['course' => $courseid, 'section' => 1], MUST_EXIST);

        return $this->insert('local_assistente_material', [
            'course_id' => $courseid,
            'section_id' => $sectionid,
            'cmid' => 900000 + $this->next(),
            'material_type' => 'pdf',
            'name' => 'Material ' . $this->sequence,
            'availability' => 'available',
            'current_version' => null,
            'created_at' => time(),
        ], $record);
    }

    /**
     * Create an analysis of a course, of the type `criteria_check` unless another is given, with a new processing unless one is given
     * (T092).
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_analysis($record = null): stdClass {
        $given = (array) ($record ?? []);
        return $this->insert('local_assistente_analysis', [
            'course_id' => $this->course_id($record),
            'processing_id' => $given['processing_id'] ?? $this->create_processing()->id,
            'analysis_type' => 'criteria_check',
            'cmid' => null,
            'summary' => null,
            'created_at' => time(),
        ], $record);
    }

    /**
     * Create the result of the criteria of a student, meeting every criterion unless other statuses are given; a new analysis and a
     * new student unless given (T092).
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_criteria_result($record = null): stdClass {
        $given = (array) ($record ?? []);
        return $this->insert('local_assistente_criteria_result', [
            'analysis_id' => $given['analysis_id'] ?? $this->create_analysis()->id,
            'student_id' => $given['student_id'] ?? $this->datagenerator->create_user()->id,
            'activity_status' => 1,
            'forum_status' => 1,
            'frequency_status' => 1,
        ], $record);
    }

    /**
     * Create a version of the synthesis of a section, with its analysis of the type `section_synthesis` (T096). Unless given, the
     * section is the first of the course and the version is the next one of that section.
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_pedagogical_synthesis($record = null): stdClass {
        global $DB;
        $given = (array) ($record ?? []);
        $courseid = $this->course_id($record);
        $sectionid = $given['section_id']
            ?? $DB->get_field('course_sections', 'id', ['course' => $courseid, 'section' => 1], MUST_EXIST);
        $version = $given['version'] ?? (1 + (int) $DB->get_field_select(
            'local_assistente_pedagogical_synthesis',
            'MAX(version)',
            'course_id = ? AND section_id = ?',
            [$courseid, $sectionid]
        ));
        return $this->insert('local_assistente_pedagogical_synthesis', [
            'analysis_id' => $given['analysis_id'] ?? $this->create_analysis([
                'course_id' => $courseid, 'analysis_type' => 'section_synthesis',
            ])->id,
            'course_id' => $courseid,
            'section_id' => $sectionid,
            'version' => $version,
            'content' => 'Síntese ' . $version,
            'input_fingerprint' => hash('sha256', "{$courseid}:{$sectionid}:{$version}"),
            'created_at' => time(),
        ], $record);
    }

    /**
     * Create the report of a course, with no version and no schedule unless given (T113).
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_report($record = null): stdClass {
        return $this->insert('local_assistente_report', [
            'course_id' => $this->course_id($record),
            'created_at' => time(),
            'next_update_at' => null,
            'final_activity_date' => null,
            'final_update_at' => null,
            'final_completed_at' => null,
            'current_version' => null,
        ], $record);
    }

    /**
     * Create a version of a report, numbered after the highest of that report, and make it the current one (T113). A report is created
     * when none is given.
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_report_version($record = null): stdClass {
        global $DB;
        $given = (array) ($record ?? []);
        $reportid = $given['report_id'] ?? $this->create_report()->id;
        $version = $given['version'] ?? (1 + (int) $DB->get_field_select('local_assistente_report_version', 'MAX(version)', 'report_id = ?', [$reportid]));
        $row = $this->insert('local_assistente_report_version', [
            'report_id' => $reportid,
            'version' => $version,
            'snapshot_json' => '{"type":"periodic","blocks":[]}',
            'created_at' => time(),
        ], $record);
        $DB->set_field('local_assistente_report', 'current_version', $row->id, ['id' => $reportid]);
        return $row;
    }
}
