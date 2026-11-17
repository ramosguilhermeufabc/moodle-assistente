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
use local_assistente\infrastructure\persistence\frequency_repository;

/**
 * Query of the frequency of the students (T109; spec-018 contract "frequência", DEC-39, DEC-42 D3, DEC-43, DEC-45 D3).
 *
 * For each student of the individual scope (T108) it reads the CURRENT execution, the latest successful `frequency` analysis that has the
 * student (spec-017), and shows only the fields of the contract: for each section the total of activities, those the student took part in
 * and the percentage, and the participation in each activity. The access to the course comes from the snapshot of the same execution and
 * is shown as complementary evidence, never as part of the percentage nor in its place. A section with no activity in the denominator, and a
 * student with no execution, are "sem dados", never zero; an activity that Moodle no longer has keeps its participation and says "fonte
 * removida". Nothing is written, nothing is sent to the LLM and no indicator (such as a global percentage) is added.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class frequency_query {
    /** @var student_scope */
    private student_scope $scope;

    /** @var access_service */
    private access_service $access;

    /** @var course_config_service */
    private course_config_service $config;

    /** @var presentation */
    private presentation $presentation;

    /** @var frequency_repository */
    private frequency_repository $frequency;

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
     * @param frequency_repository|null $frequency
     * @param event_repository|null $events
     * @param source_status_resolver|null $sources
     * @param course_module_reader|null $modules
     */
    public function __construct(
        ?student_scope $scope = null,
        ?access_service $access = null,
        ?course_config_service $config = null,
        ?presentation $presentation = null,
        ?frequency_repository $frequency = null,
        ?event_repository $events = null,
        ?source_status_resolver $sources = null,
        ?course_module_reader $modules = null
    ) {
        $this->scope = $scope ?? new student_scope();
        $this->access = $access ?? new access_service();
        $this->config = $config ?? new course_config_service();
        $this->presentation = $presentation ?? new presentation();
        $this->frequency = $frequency ?? new frequency_repository();
        $this->events = $events ?? new event_repository();
        $this->sources = $sources ?? new source_status_resolver();
        $this->modules = $modules ?? new course_module_reader();
    }

    /**
     * The frequency of the students of a course.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param int|null $groupid filter by group of the course (individual query, DEC-43 D1)
     * @return array{available: bool, message: string|null, rows: array} each row has `id`, `name`, `status`, `course_access`, `sections`
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     */
    public function query(int $courseid, int $userid, ?int $groupid = null): array {
        $this->access->require_view($courseid, $userid);
        return $this->presentation->results($this->config->state($courseid), function () use ($courseid, $userid, $groupid): array {
            $names = $this->modules->get_modules($courseid);
            $rows = [];
            foreach ($this->scope->individual($courseid, $userid, $groupid) as $student) {
                $rows[] = $student + $this->frequency_of($courseid, $student['id'], $names);
            }
            return $this->presentation->sort_by_name($rows);
        });
    }

    /**
     * The current frequency of a student.
     *
     * @param int $courseid
     * @param int $studentid
     * @param \stdClass[] $modules the modules of the course by cmid, for the names
     * @return array{status: array{state: string, text: string|null}, course_access: string|null, sections: array}
     */
    private function frequency_of(int $courseid, int $studentid, array $modules): array {
        $analysis = $this->frequency->find_current_for_student($courseid, $studentid);
        if ($analysis === null) {
            return ['status' => $this->shown(result_state::NO_DATA, null), 'course_access' => null, 'sections' => []];
        }
        $sections = [];
        foreach ($this->frequency->find_frequencies((int) $analysis->id, $studentid) as $row) {
            $state = (int) $row->total_activities === 0 ? result_state::NO_DATA : result_state::VALID;
            $activities = [];
            foreach ($this->frequency->find_activities((int) $row->id) as $activity) {
                $cmid = (int) $activity->activity_id;
                $activities[] = [
                    'activity_id' => $cmid,
                    'activity' => isset($modules[$cmid]) ? $modules[$cmid]->name : null,
                    'participated' => (int) $activity->participated === 1,
                    'source' => $this->presentation->source_note($this->sources->status_of('course_module', $cmid, $courseid)),
                ];
            }
            $sections[] = [
                'section_id' => (int) $row->section_id,
                'total' => (int) $row->total_activities,
                'participating' => (int) $row->participating_activities,
                'percentage' => $this->shown($state, $row->percentage),
                'activities' => $activities,
            ];
        }
        return [
            'status' => $sections ? ['state' => result_state::VALID->value, 'text' => null] : $this->shown(result_state::NO_DATA, null),
            'course_access' => $this->course_access($analysis, $studentid),
            'sections' => $sections,
        ];
    }

    /**
     * The access to the course that the snapshot of the execution registered for the student (DEC-39 D3).
     *
     * @param \stdClass $analysis
     * @param int $studentid
     * @return string|null null when the snapshot does not say anything about the student
     */
    private function course_access(\stdClass $analysis, int $studentid): ?string {
        $context = $this->events->find_context((int) $analysis->processing_id);
        $snapshot = $context === null ? null : json_decode((string) $context->context_json, true);
        foreach (is_array($snapshot) ? ($snapshot['course_access'] ?? []) : [] as $entry) {
            if (is_array($entry) && ($entry['student_id'] ?? null) === $studentid) {
                $last = isset($entry['last_access']) && is_int($entry['last_access']) && $entry['last_access'] > 0 ? $entry['last_access'] : null;
                return $this->presentation->course_access(isset($entry['accessed']) ? (bool) $entry['accessed'] : null, $last);
            }
        }
        return null;
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
