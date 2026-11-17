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

/**
 * Reads the students of a course and its groups (spec-004; DEC-37, DEC-43, DEC-47).
 *
 * A student is a user with an active enrolment and a role that the gradebook evaluates, read at the
 * moment of the query. Groups are the Moodle groups of the course ("turma", DEC-43 D1).
 *
 * Moodle API: get_enrolled_sql() (active enrolments, optionally by group) and the gradebook roles of $CFG->gradebookroles in the
 * role assignments of the course and of its parent contexts, which is the definition used by the gradebook; groups_get_all_groups(),
 * groups_get_members(), groups_get_course_groupmode(); has_capability() with moodle/site:accessallgroups; fullname().
 * get_gradable_users() is NOT used: it goes through graded_users_iterator, which refuses to list anyone while the course has grades
 * waiting to be recalculated (exception grades/gradesneedregrading), and restricts the list to the groups of the current user in
 * separate-groups courses; the list of students depends on neither (T022 fix).
 * Verified by reading the MOODLE_502_STABLE sources; behaviour in a
 * running Moodle 5.2 is validated in CL-38 (task T031). Hypothesis: only the course-level group mode is
 * considered, not the group mode of each activity.
 *
 * DML justification: reading the name fields of user records, with get_records_list(), because no API returns the display names of a
 * list of ids; and the role assignments of the gradebook roles in get_students(), because no API lists the users of a course by the
 * roles the gradebook evaluates without going through the grade recalculation check.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_reader {
    /**
     * Students of the course: active enrolment and a role evaluated by the gradebook.
     *
     * @param int $courseid
     * @param int|null $groupid restrict to the members of this group
     * @return int[] user ids, ascending
     */
    public function get_students(int $courseid, ?int $groupid = null): array {
        global $CFG, $DB;

        $context = \context_course::instance($courseid);
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($context, '', $groupid, true);
        [$rolesql, $roleparams] = $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'grbr');
        [$contextsql, $contextparams] = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');

        $ids = $DB->get_fieldset_sql(
            "SELECT u.id
               FROM {user} u
               JOIN ({$enrolledsql}) je ON je.id = u.id
              WHERE u.deleted = 0
                AND EXISTS (SELECT 1
                              FROM {role_assignments} ra
                             WHERE ra.userid = u.id
                               AND ra.roleid {$rolesql}
                               AND ra.contextid {$contextsql})
           ORDER BY u.id ASC",
            $enrolledparams + $roleparams + $contextparams
        );
        return array_map('intval', $ids);
    }

    /**
     * Whether a user is a student of the course right now (DEC-47).
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public function is_student(int $courseid, int $userid): bool {
        return in_array($userid, $this->get_students($courseid), true);
    }

    /**
     * Display names of users, for presentation and alphabetical ordering (DEC-02). Never send them to the LLM.
     *
     * @param int[] $userids
     * @return string[] id => full name
     */
    public function get_names(array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        $fields = 'id, ' . implode(', ', \core_user\fields::for_name()->get_required_fields());
        $names = [];
        foreach ($DB->get_records_list('user', 'id', $userids, '', $fields) as $user) {
            $names[(int) $user->id] = fullname($user);
        }
        return $names;
    }

    /**
     * Groups of a course.
     *
     * @param int $courseid
     * @return string[] group id => name
     */
    public function get_groups(int $courseid): array {
        $groups = [];
        foreach (groups_get_all_groups($courseid, 0, 0, 'g.id, g.name') as $group) {
            $groups[(int) $group->id] = (string) $group->name;
        }
        return $groups;
    }

    /**
     * Course-level group mode: NOGROUPS, SEPARATEGROUPS or VISIBLEGROUPS.
     *
     * @param int $courseid
     * @return int
     */
    public function group_mode(int $courseid): int {
        return (int) groups_get_course_groupmode(get_course($courseid));
    }

    /**
     * Whether the user only sees the students of their own groups: separate groups in the course and no
     * capability to access all groups (DEC-43 D2).
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public function is_restricted_to_own_groups(int $courseid, int $userid): bool {
        if ($this->group_mode($courseid) !== SEPARATEGROUPS) {
            return false;
        }
        return !has_capability('moodle/site:accessallgroups', \context_course::instance($courseid), $userid);
    }

    /**
     * Groups visible to a user.
     *
     * @param int $courseid
     * @param int $userid
     * @return string[] group id => name
     */
    public function visible_groups(int $courseid, int $userid): array {
        if (!$this->is_restricted_to_own_groups($courseid, $userid)) {
            return $this->get_groups($courseid);
        }
        $groups = [];
        foreach (groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name') as $group) {
            $groups[(int) $group->id] = (string) $group->name;
        }
        return $groups;
    }

    /**
     * Students that the user may see in individual queries (DEC-43 D2), optionally filtered by group.
     *
     * @param int $courseid
     * @param int $userid the user that queries (teacher, manager)
     * @param int|null $groupid
     * @return int[] user ids, ascending
     */
    public function visible_students(int $courseid, int $userid, ?int $groupid = null): array {
        if (!$this->is_restricted_to_own_groups($courseid, $userid)) {
            return $this->get_students($courseid, $groupid);
        }

        $groupids = array_keys($this->visible_groups($courseid, $userid));
        if ($groupid !== null) {
            if (!in_array($groupid, $groupids, true)) {
                return [];
            }
            $groupids = [$groupid];
        }
        $ids = [];
        foreach ($groupids as $id) {
            $ids = array_merge($ids, $this->get_students($courseid, $id));
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }
}
