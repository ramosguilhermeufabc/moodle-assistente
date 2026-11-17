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


namespace local_assistente\infrastructure\persistence;

use local_assistente\domain\event\erased_subject;
use local_assistente\domain\event\event_type;

/**
 * The rows of the plugin that belong to one student, for the erasure of the data of that student (T104; DEC-42 D1, DEC-57).
 *
 * Two jobs and nothing else, both reached only by the erasure (application/privacy; architecture_test keeps it so):
 * - the events of the student, found by the `userid` in the payload (the schema has no column for it), and the neutralization of one of them:
 *   only the `payload` of that row changes (`userid` to 0 and, for the types that derive the identity from the `userid`, the identity to forty
 *   zeros); the id, the type, the status, the dates, the response, the processing and the attempts stay;
 * - the individual results of the student (`performance`, `frequency` with its `frequency_activity`, `criteria_result`), which are deleted in
 *   every course; the analyses they belonged to stay, as DEC-57 says, and so does everything of the other students.
 *
 * It takes no lock and opens no transaction: the caller holds the locks and the transaction (student_event_eraser). It is not a repository of
 * an aggregate and adds no way to change history to those that are.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_data_repository {
    /**
     * The events whose payload names the student, whatever their status, oldest first.
     *
     * @param int $userid
     * @param int[]|null $courseids only the events of these courses; null for every course
     * @return \stdClass[]
     */
    public function find_events(int $userid, ?array $courseids = null): array {
        global $DB;
        if ($userid <= 0) {
            throw new \InvalidArgumentException('The student must be a positive identifier.');
        }
        // The payload is compact JSON, so the userid is followed by a comma or by the closing brace; the decoded value is checked afterwards.
        $select = '(' . $DB->sql_like('payload', ':followed', true, true, false) . ' OR ' . $DB->sql_like('payload', ':closing', true, true, false) . ')';
        $params = ['followed' => '%"userid":' . $userid . ',%', 'closing' => '%"userid":' . $userid . '}%'];
        if ($courseids !== null) {
            if (!$courseids) {
                return [];
            }
            [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $courseids), SQL_PARAMS_NAMED, 'course');
            $select .= ' AND course_id ' . $insql;
            $params += $inparams;
        }
        $found = [];
        foreach ($DB->get_records_select('local_assistente_event', $select, $params, 'id ASC') as $event) {
            $payload = json_decode((string) $event->payload, true);
            if (is_array($payload) && ($payload['userid'] ?? null) === $userid) {
                $found[] = $event;
            }
        }
        return $found;
    }

    /**
     * Neutralize the payload of one event: only the payload of that row is written.
     *
     * @param \stdClass $event row of `local_assistente_event`
     * @return bool whether it was changed; false for an event that has no student or was already neutralized
     */
    public function neutralize_event(\stdClass $event): bool {
        global $DB;
        $payload = json_decode((string) $event->payload, true);
        if (!is_array($payload) || !is_int($payload['userid'] ?? null) || $payload['userid'] <= 0) {
            return false;
        }
        $type = event_type::tryFrom((string) $event->event_type);
        $payload['userid'] = erased_subject::USERID;
        if ($type !== null && in_array('userid', $type->identity_fields(), true) && array_key_exists('identity', $payload)) {
            $payload['identity'] = erased_subject::IDENTITY;
        }
        $DB->set_field(
            'local_assistente_event',
            'payload',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ['id' => $event->id]
        );
        return true;
    }

    /**
     * Delete the individual results of the student.
     *
     * @param int $userid
     * @param int[]|null $courseids only the results of the analyses of these courses; null for every course
     * @return array<string, int> rows deleted from each table
     */
    public function delete_result_rows(int $userid, ?array $courseids = null): array {
        global $DB;
        if ($userid <= 0) {
            throw new \InvalidArgumentException('The student must be a positive identifier.');
        }
        $deleted = [];
        $scope = '';
        $scopeparams = [];
        if ($courseids !== null) {
            if (!$courseids) {
                return array_fill_keys(['local_assistente_frequency_activity', 'local_assistente_frequency', 'local_assistente_performance', 'local_assistente_criteria_result'], 0);
            }
            [$insql, $scopeparams] = $DB->get_in_or_equal(array_map('intval', $courseids), SQL_PARAMS_NAMED, 'course');
            $scope = ' AND analysis_id IN (SELECT id FROM {local_assistente_analysis} WHERE course_id ' . $insql . ')';
        }
        $own = 'frequency_id IN (SELECT id FROM {local_assistente_frequency} WHERE student_id = :student' . $scope . ')';
        foreach ([
            'local_assistente_frequency_activity' => $own,
            'local_assistente_frequency' => 'student_id = :student' . $scope,
            'local_assistente_performance' => 'student_id = :student' . $scope,
            'local_assistente_criteria_result' => 'student_id = :student' . $scope,
        ] as $table => $condition) {
            $params = ['student' => $userid] + $scopeparams;
            $deleted[$table] = $DB->count_records_select($table, $condition, $params);
            if ($deleted[$table] > 0) {
                $DB->delete_records_select($table, $condition, $params);
            }
        }
        return $deleted;
    }

    /**
     * First and last name of a user, as they are in the user record (they stay there when the account is deleted).
     *
     * @param int $userid
     * @return array{0: string, 1: string}|null null when there is no such user
     */
    public function names(int $userid): ?array {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname');
        return $user ? [(string) $user->firstname, (string) $user->lastname] : null;
    }

    /**
     * Whether the first and the last name of the student can be searched in the preserved texts: the account exists (deleted or not, Moodle keeps
     * the names of a deleted account) and both names have something besides spaces.
     *
     * @param int $userid
     * @return bool
     */
    public function names_available(int $userid): bool {
        $names = $this->names($userid);
        return $names !== null && trim($names[0]) !== '' && trim($names[1]) !== '';
    }

    /**
     * The courses where the plugin holds something of the student: individual results, events that name the student, entries in a snapshot,
     * or a delivery of an event of the student that waits in a retry task (also one that has no attempts left).
     *
     * @param int $userid
     * @return int[] ascending
     */
    public function course_ids_with_data(int $userid): array {
        global $DB;
        $ids = [];
        $own = ['local_assistente_performance', 'local_assistente_frequency', 'local_assistente_criteria_result'];
        foreach ($own as $table) {
            $sql = "SELECT DISTINCT a.course_id FROM {{$table}} t JOIN {local_assistente_analysis} a ON a.id = t.analysis_id WHERE t.student_id = :student";
            $ids = array_merge($ids, $DB->get_fieldset_sql($sql, ['student' => $userid]));
        }
        foreach ($this->find_events($userid) as $event) {
            $ids[] = $event->course_id;
        }
        foreach ($this->context_rows($userid) as $row) {
            $ids[] = $row->course_id;
        }
        $ids = array_merge($ids, (new redelivery_task_repository())->course_ids_of_user($userid));
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return $ids;
    }

    /**
     * The users that have something in the plugin in a course, a delivery that waits in a retry task included.
     *
     * @param int $courseid
     * @return int[] ascending
     */
    public function user_ids_in_course(int $courseid): array {
        global $DB;
        $ids = [];
        foreach (['local_assistente_performance', 'local_assistente_frequency', 'local_assistente_criteria_result'] as $table) {
            $sql = "SELECT DISTINCT t.student_id FROM {{$table}} t JOIN {local_assistente_analysis} a ON a.id = t.analysis_id WHERE a.course_id = :course";
            $ids = array_merge($ids, $DB->get_fieldset_sql($sql, ['course' => $courseid]));
        }
        $like = $DB->sql_like('payload', ':p', true, true, false);
        foreach ($DB->get_records_select('local_assistente_event', "course_id = :course AND {$like}", ['course' => $courseid, 'p' => '%"userid":%']) as $event) {
            $userid = (json_decode((string) $event->payload, true)['userid'] ?? 0);
            if (is_int($userid) && $userid > 0) {
                $ids[] = $userid;
            }
        }
        $sql = "SELECT pc.id, pc.context_json
                  FROM {local_assistente_processing_context} pc
                  JOIN {local_assistente_processing} p ON p.id = pc.processing_id
                  JOIN {local_assistente_event} e ON e.id = p.event_id
                 WHERE e.course_id = :course AND " . $DB->sql_like('pc.context_json', ':s', true, true, false);
        foreach ($DB->get_records_sql($sql, ['course' => $courseid, 's' => '%"student_id":%']) as $row) {
            $ids = array_merge($ids, self::student_ids_of_context((string) $row->context_json));
        }
        $ids = array_merge($ids, (new redelivery_task_repository())->user_ids_in_course($courseid));
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return array_values(array_filter($ids, static fn($id) => $id > 0));
    }

    /**
     * The sections of the courses that have a version of a synthesis, for the locks of their writers.
     *
     * @param int[] $courseids
     * @return array<int, int[]> course => sections
     */
    public function synthesis_sections(array $courseids): array {
        global $DB;
        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $courseids), SQL_PARAMS_NAMED, 'course');
        $sections = [];
        foreach ($DB->get_records_sql("SELECT DISTINCT course_id, section_id FROM {local_assistente_pedagogical_synthesis} WHERE course_id {$insql}", $params) as $row) {
            $sections[(int) $row->course_id][] = (int) $row->section_id;
        }
        return $sections;
    }

    /**
     * The snapshots of the courses that name the student.
     *
     * @param int $userid
     * @param int[]|null $courseids restrict to these courses
     * @return \stdClass[] each with id, context_json and course_id
     */
    public function context_rows(int $userid, ?array $courseids = null): array {
        global $DB;
        $sql = "SELECT pc.id, pc.context_json, e.course_id
                  FROM {local_assistente_processing_context} pc
                  JOIN {local_assistente_processing} p ON p.id = pc.processing_id
                  JOIN {local_assistente_event} e ON e.id = p.event_id
                 WHERE (" . $DB->sql_like('pc.context_json', ':followed', true, true, false) . ' OR ' . $DB->sql_like('pc.context_json', ':closing', true, true, false) . ')';
        $params = ['followed' => '%"student_id":' . $userid . ',%', 'closing' => '%"student_id":' . $userid . '}%'];
        if ($courseids !== null) {
            if (!$courseids) {
                return [];
            }
            [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $courseids), SQL_PARAMS_NAMED, 'course');
            $sql .= ' AND e.course_id ' . $insql;
            $params += $inparams;
        }
        $found = [];
        foreach ($DB->get_records_sql($sql . ' ORDER BY pc.id', $params) as $row) {
            if (in_array($userid, self::student_ids_of_context((string) $row->context_json), true)) {
                $found[] = $row;
            }
        }
        return $found;
    }

    /**
     * What the plugin holds of a student in one course, for the export.
     *
     * @param int $userid
     * @param int $courseid
     * @return array{performance: array, frequency: array, criteria: array, snapshots: array}
     */
    public function export(int $userid, int $courseid): array {
        global $DB;
        $params = ['student' => $userid, 'course' => $courseid];
        $performance = array_values($DB->get_records_sql(
            "SELECT t.id, t.activity_id AS cmid, t.grade, a.created_at FROM {local_assistente_performance} t
               JOIN {local_assistente_analysis} a ON a.id = t.analysis_id WHERE t.student_id = :student AND a.course_id = :course ORDER BY t.id",
            $params
        ));
        $frequency = [];
        foreach ($DB->get_records_sql(
            "SELECT t.id, t.section_id, t.total_activities, t.participating_activities, t.percentage, a.created_at FROM {local_assistente_frequency} t
               JOIN {local_assistente_analysis} a ON a.id = t.analysis_id WHERE t.student_id = :student AND a.course_id = :course ORDER BY t.id",
            $params
        ) as $row) {
            $row->activities = array_values($DB->get_records('local_assistente_frequency_activity', ['frequency_id' => $row->id], 'activity_id ASC', 'activity_id AS cmid, participated, reference_date'));
            $frequency[] = $row;
        }
        $criteria = array_values($DB->get_records_sql(
            "SELECT t.id, t.activity_status, t.forum_status, t.frequency_status, a.created_at FROM {local_assistente_criteria_result} t
               JOIN {local_assistente_analysis} a ON a.id = t.analysis_id WHERE t.student_id = :student AND a.course_id = :course ORDER BY t.id",
            $params
        ));
        $snapshots = [];
        foreach ($this->context_rows($userid, [$courseid]) as $row) {
            $snapshots[] = self::entries_of_context((string) $row->context_json, $userid);
        }
        return ['performance' => $performance, 'frequency' => $frequency, 'criteria' => $criteria, 'snapshots' => $snapshots];
    }

    /**
     * The student ids that a snapshot names: the one of an individual snapshot and those of the lists `course_access` and `students`.
     *
     * @param string $json
     * @return int[]
     */
    public static function student_ids_of_context(string $json): array {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $ids = [];
        is_int($data['student_id'] ?? null) && $ids[] = $data['student_id'];
        foreach (['course_access', 'students'] as $list) {
            foreach (is_array($data[$list] ?? null) ? $data[$list] : [] as $entry) {
                is_array($entry) && is_int($entry['student_id'] ?? null) && $ids[] = $entry['student_id'];
            }
        }
        return $ids;
    }

    /**
     * The entries of one student in a snapshot, as they are.
     *
     * @param string $json
     * @param int $userid
     * @return array
     */
    public static function entries_of_context(string $json, int $userid): array {
        $data = json_decode($json, true);
        $kind = is_array($data) ? ($data['kind'] ?? null) : null;
        if (!is_array($data)) {
            return [];
        }
        if (($data['student_id'] ?? null) === $userid) {
            return ['kind' => $kind] + array_diff_key($data, array_flip(['format', 'kind', 'course_id', 'limits']));
        }
        $entries = [];
        foreach (['course_access', 'students'] as $list) {
            foreach (is_array($data[$list] ?? null) ? $data[$list] : [] as $entry) {
                if (is_array($entry) && ($entry['student_id'] ?? null) === $userid) {
                    $entries[$list][] = $entry;
                }
            }
        }
        return ['kind' => $kind] + $entries;
    }

    /**
     * SQL that selects the ids of the course contexts of some courses, for the list of contexts of the Privacy API (which takes SQL).
     *
     * @param int[] $courseids
     * @return array{0: string, 1: array}|null null when there is no course
     */
    public function course_context_sql(array $courseids): ?array {
        global $DB;
        if (!$courseids) {
            return null;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $courseids), SQL_PARAMS_NAMED, 'course');
        return ['SELECT id FROM {context} WHERE contextlevel = :level AND instanceid ' . $insql, ['level' => CONTEXT_COURSE] + $params];
    }
}