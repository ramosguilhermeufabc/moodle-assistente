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

namespace local_assistente\application\query;

use local_assistente\application\access_service;
use local_assistente\application\course_config_service;
use local_assistente\application\history\source_status_resolver;
use local_assistente\domain\result_state;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\performance_repository;

/**
 * Query of the grades of the students (T109; spec-018 contract "notas", DEC-37, DEC-42 D3, DEC-43, DEC-45 D3).
 *
 * For each student of the individual scope (T108) it reads the CURRENT execution, the latest successful `grade_performance` analysis of the
 * student (spec-017), and shows only the fields of the contract: the student, the grade of each activity as a percentage and the final
 * total of the course, which is kept in the snapshot of that execution. A student with no execution is "sem dados", never zero; an activity
 * that Moodle no longer has keeps its grade and says "fonte removida". Nothing is written, nothing is sent to the LLM and no indicator is
 * added.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class grades_query {
    /** @var student_scope */
    private student_scope $scope;

    /** @var access_service */
    private access_service $access;

    /** @var course_config_service */
    private course_config_service $config;

    /** @var presentation */
    private presentation $presentation;

    /** @var performance_repository */
    private performance_repository $performance;

    /** @var event_repository */
    private event_repository $events;

    /** @var source_status_resolver */
    private source_status_resolver $sources;

    /** @var course_module_reader */
    private course_module_reader $modules;

    /**
     * Constructor; every collaborator can be replaced by tests.
     *
     * @param student_scope|null $scope
     * @param access_service|null $access
     * @param course_config_service|null $config
     * @param presentation|null $presentation
     * @param performance_repository|null $performance
     * @param event_repository|null $events
     * @param source_status_resolver|null $sources
     * @param course_module_reader|null $modules
     */
    public function __construct(
        ?student_scope $scope = null,
        ?access_service $access = null,
        ?course_config_service $config = null,
        ?presentation $presentation = null,
        ?performance_repository $performance = null,
        ?event_repository $events = null,
        ?source_status_resolver $sources = null,
        ?course_module_reader $modules = null
    ) {
        $this->scope = $scope ?? new student_scope();
        $this->access = $access ?? new access_service();
        $this->config = $config ?? new course_config_service();
        $this->presentation = $presentation ?? new presentation();
        $this->performance = $performance ?? new performance_repository();
        $this->events = $events ?? new event_repository();
        $this->sources = $sources ?? new source_status_resolver();
        $this->modules = $modules ?? new course_module_reader();
    }

    /**
     * The grades of the students of a course.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param int|null $groupid filter by group of the course (individual query, DEC-43 D1)
     * @return array{available: bool, message: string|null, rows: array} each row has `id`, `name`, `course_total` and `activities`
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     */
    public function query(int $courseid, int $userid, ?int $groupid = null): array {
        $this->access->require_view($courseid, $userid);
        return $this->presentation->results($this->config->state($courseid), function () use ($courseid, $userid, $groupid): array {
            $names = $this->modules->get_modules($courseid);
            $rows = [];
            foreach ($this->scope->individual($courseid, $userid, $groupid) as $student) {
                $rows[] = $student + $this->grades_of($courseid, $student['id'], $names);
            }
            return $this->presentation->sort_by_name($rows);
        });
    }

    /**
     * The current grades of a student.
     *
     * @param int $courseid
     * @param int $studentid
     * @param \stdClass[] $modules the modules of the course by cmid, for the names
     * @return array{course_total: array, activities: array}
     */
    private function grades_of(int $courseid, int $studentid, array $modules): array {
        $analysis = $this->performance->find_current_for_student($courseid, $studentid);
        if ($analysis === null) {
            return ['course_total' => $this->shown(result_state::NO_DATA, null), 'activities' => []];
        }
        $context = $this->events->find_context((int) $analysis->processing_id);
        $snapshot = $context === null ? null : json_decode((string) $context->context_json, true);
        $total = is_array($snapshot) && isset($snapshot['course_total_percent']) && is_numeric($snapshot['course_total_percent'])
            ? $this->shown(result_state::VALID, $snapshot['course_total_percent'])
            : $this->shown(result_state::NO_DATA, null);

        $activities = [];
        foreach ($this->performance->find_rows((int) $analysis->id) as $row) {
            $cmid = (int) $row->activity_id;
            $activities[] = [
                'activity_id' => $cmid,
                'activity' => isset($modules[$cmid]) ? $modules[$cmid]->name : null,
                'grade' => $this->shown(result_state::VALID, $row->grade),
                'source' => $this->presentation->source_note($this->sources->status_of('course_module', $cmid, $courseid)),
            ];
        }
        return ['course_total' => $total, 'activities' => $activities];
    }

    /**
     * A value with its state and its text.
     *
     * @param result_state $state
     * @param float|int|string|null $value
     * @return array{state: string, text: string}
     */
    private function shown(result_state $state, float|int|string|null $value): array {
        return ['state' => $state->value, 'text' => $this->presentation->value($state, $value)];
    }
}
