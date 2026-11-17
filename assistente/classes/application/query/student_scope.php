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
use local_assistente\domain\result_state;
use local_assistente\infrastructure\moodle\student_reader;

/**
 * The students that a query may show to a user (T108; spec-018 common rules, DEC-43, DEC-47, spec-022 Q6 and Q7).
 *
 * A student is an active enrolment with a role that the gradebook evaluates, read when the query runs (student_reader, T022); nothing is
 * stored. Individual queries (grades, frequency, students below the criteria) may be filtered by a Moodle group, and in a course with
 * separate groups a user without the capability to access all groups only gets the students of the groups the user can see (DEC-43 D2).
 * Aggregate queries are never filtered nor restricted by group, and refuse a group filter. The backend authorizes first, with
 * `local/assistente:view` (T038), and never trusts the caller.
 *
 * It reads and writes nothing: a student removed from the course leaves the lists, and the results kept in the history are untouched.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_scope {
    /** @var student_reader */
    private student_reader $students;

    /** @var access_service */
    private access_service $access;

    /**
     * Constructor.
     *
     * @param student_reader|null $students
     * @param access_service|null $access
     */
    public function __construct(?student_reader $students = null, ?access_service $access = null) {
        $this->students = $students ?? new student_reader();
        $this->access = $access ?? new access_service();
    }

    /**
     * The students of an individual query, optionally of one group.
     *
     * A group the user cannot see (separate groups) or that has no students gives no students, never the students of another group.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param int|null $groupid the group of the filter
     * @return array[] each ['id' => int, 'name' => string], by id; the query orders them (presentation::sort_by_name)
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     */
    public function individual(int $courseid, int $userid, ?int $groupid = null): array {
        $this->access->require_view($courseid, $userid);
        return $this->named($this->students->visible_students($courseid, $userid, $groupid));
    }

    /**
     * The students behind an aggregate query: all those of the course, whatever the groups of the user (DEC-43 D2).
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $groupid must be null: the filter by group is not available in aggregate queries (DEC-43 D1)
     * @return array[] each ['id' => int, 'name' => string], by id
     * @throws \InvalidArgumentException when a group is given
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     */
    public function aggregate(int $courseid, int $userid, ?int $groupid = null): array {
        $this->access->require_view($courseid, $userid);
        if ($groupid !== null) {
            throw new \InvalidArgumentException('The filter by group is not available in aggregate queries.');
        }
        return $this->named($this->students->get_students($courseid));
    }

    /**
     * The groups that the user may choose in the filter of an individual query.
     *
     * @param int $courseid
     * @param int $userid
     * @return string[] group id => name
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     */
    public function groups(int $courseid, int $userid): array {
        $this->access->require_view($courseid, $userid);
        return $this->students->visible_groups($courseid, $userid);
    }

    /**
     * Join the students with the results that the query read: a student without a result (just enrolled, spec-022 Q6) is listed with no
     * data, never with zero; a result of someone who is not in the list (removed from the course, spec-022 Q7) is left out, and is not
     * deleted by this.
     *
     * @param array[] $students as individual() gives
     * @param array $results user id => ['state' => result_state, 'value' => float|int|string|null]
     * @return array[] each ['id', 'name', 'state' => result_state, 'value']
     */
    public function with_results(array $students, array $results): array {
        $rows = [];
        foreach ($students as $student) {
            $found = $results[$student['id']] ?? null;
            $rows[] = $student + ($found === null
                ? ['state' => result_state::NO_DATA, 'value' => null]
                : ['state' => $found['state'], 'value' => $found['value'] ?? null]);
        }
        return $rows;
    }

    /**
     * Ids with the display names.
     *
     * @param int[] $ids
     * @return array[]
     */
    private function named(array $ids): array {
        $names = $this->students->get_names($ids);
        return array_map(static fn(int $id): array => ['id' => $id, 'name' => $names[$id] ?? ''], $ids);
    }
}
