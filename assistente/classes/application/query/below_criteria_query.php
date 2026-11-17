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
use local_assistente\domain\criteria_status;
use local_assistente\infrastructure\persistence\criteria_result_repository;

/**
 * Query of the students below the criteria (T109; spec-018 contract "alunos abaixo dos critérios", DEC-40, DEC-42 D3, DEC-43, DEC-45 D3).
 *
 * It lists the students of the individual scope (T108) that have `0` ("abaixo") in at least one dimension of the CURRENT execution of the
 * criteria, the latest successful `criteria_check` analysis that has the student (spec-017), and shows only the status of each criterion:
 * `1` "atende", `0` "abaixo" and `2` "sem dados" (DEC-40). A student whose only statuses are "atende" and "sem dados" is not listed: the
 * absence of data never means failing a criterion, and a student with no execution has nothing to list. The limits that were used are in
 * the snapshot of the execution and are not shown here, since the contract only has the status. Nothing is written, nothing is sent to the
 * LLM and no indicator is added.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class below_criteria_query {
    /** @var string[] column of criteria_result => name of the criterion in the view */
    private const DIMENSIONS = ['activity_status' => 'activity', 'forum_status' => 'forum', 'frequency_status' => 'frequency'];

    /** @var student_scope */
    private student_scope $scope;

    /** @var access_service */
    private access_service $access;

    /** @var course_config_service */
    private course_config_service $config;

    /** @var presentation */
    private presentation $presentation;

    /** @var criteria_result_repository */
    private criteria_result_repository $results;

    /**
     * Constructor; every collaborator can be replaced by tests.
     *
     * @param student_scope|null $scope
     * @param access_service|null $access
     * @param course_config_service|null $config
     * @param presentation|null $presentation
     * @param criteria_result_repository|null $results
     */
    public function __construct(
        ?student_scope $scope = null,
        ?access_service $access = null,
        ?course_config_service $config = null,
        ?presentation $presentation = null,
        ?criteria_result_repository $results = null
    ) {
        $this->scope = $scope ?? new student_scope();
        $this->access = $access ?? new access_service();
        $this->config = $config ?? new course_config_service();
        $this->presentation = $presentation ?? new presentation();
        $this->results = $results ?? new criteria_result_repository();
    }

    /**
     * The students below the criteria in a course.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param int|null $groupid filter by group of the course (individual query, DEC-43 D1)
     * @return array{available: bool, message: string|null, rows: array} each row has `id`, `name` and `criteria` (activity, forum, frequency)
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     */
    public function query(int $courseid, int $userid, ?int $groupid = null): array {
        $this->access->require_view($courseid, $userid);
        return $this->presentation->results($this->config->state($courseid), function () use ($courseid, $userid, $groupid): array {
            $rows = [];
            foreach ($this->scope->individual($courseid, $userid, $groupid) as $student) {
                $criteria = $this->criteria_of($courseid, $student['id']);
                if ($criteria !== null) {
                    $rows[] = $student + ['criteria' => $criteria];
                }
            }
            return $this->presentation->sort_by_name($rows);
        });
    }

    /**
     * The status of each criterion of a student in the current execution, when the student is below at least one.
     *
     * @param int $courseid
     * @param int $studentid
     * @return array<string, array{status: int, text: string}>|null null when the student has no execution or is below none
     */
    private function criteria_of(int $courseid, int $studentid): ?array {
        $analysis = $this->results->find_current_for_student($courseid, $studentid);
        $row = $analysis === null ? null : $this->results->find_result((int) $analysis->id, $studentid);
        if ($row === null) {
            return null;
        }
        $criteria = [];
        $below = false;
        foreach (self::DIMENSIONS as $column => $name) {
            $status = criteria_status::from((int) $row->{$column});
            $below = $below || $status === criteria_status::BELOW;
            $criteria[$name] = ['status' => $status->value, 'text' => $this->presentation->criteria($status)];
        }
        return $below ? $criteria : null;
    }
}
