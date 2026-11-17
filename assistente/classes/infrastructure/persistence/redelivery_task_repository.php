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

use core\task\manager;

/**
 * The retry tasks of Moodle that hold the delivery of an event (redelivery of an event; exception to DEC-32 D2), read and removed.
 *
 * A task holds the course and the identifiers of what the observer could not create now, among them the student, so it is data of the
 * student and of the course like the events are: the erasure of the student finds it (student_data_repository) and removes it, and the
 * erasure of a deleted course removes it with the rest (course_erasure_repository). This is the one place that reads the custom data of
 * those tasks, so the format has one reader; event_redelivery_scheduler is the one that writes it.
 *
 * Every task of the class counts, also one that has no attempts left: it stays in `task_adhoc` until somebody removes it, and holds the same
 * identifiers.
 *
 * DML justification: (1) `task_adhoc` is read by class and by custom data, which the task API of Moodle cannot filter, and removed by
 * identifier; (2) the guard of the retry task asks, inside the critical section of the queue, whether its own record is still there, and
 * the task API has no read by identifier that does not take the lock of the task, which the running task itself holds; (3) the same guard
 * asks whether the account of the student is still active, a plain read of `user`. The removals run in
 * the transaction of the caller, so an erasure that fails brings the tasks back with the rest.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class redelivery_task_repository {
    /** The class of the retry task. */
    private const TASK_CLASS = 'local_assistente\task\redeliver_event';

    /**
     * Whether the record of a task exists.
     *
     * @param int $taskid
     * @return bool
     */
    public function exists(int $taskid): bool {
        global $DB;
        return $DB->record_exists('task_adhoc', ['id' => $taskid]);
    }

    /**
     * Whether the account of a student exists and is not deleted. Moodle deletes the account right after the plugins erased the data of the
     * student (the privacy request of a user), so a delivery that wakes up after that is about a student who is gone.
     *
     * @param int $userid
     * @return bool
     */
    public function user_is_active(int $userid): bool {
        global $DB;
        return $DB->record_exists('user', ['id' => $userid, 'deleted' => 0]);
    }

    /**
     * What a task custom data holds, when it is a delivery.
     *
     * @param mixed $customdata as decoded by Moodle (an object or an array)
     * @return array{course_id: int, event_type: string, payload: array}|null null when it is not a delivery this plugin queued
     */
    public static function decode(mixed $customdata): ?array {
        $data = json_decode(json_encode($customdata), true);
        if (!is_array($data) || !is_int($data['course_id'] ?? null) || !is_string($data['event_type'] ?? null) || !is_array($data['payload'] ?? null)) {
            return null;
        }
        return ['course_id' => $data['course_id'], 'event_type' => $data['event_type'], 'payload' => $data['payload']];
    }

    /**
     * Every delivery that is held by a task, with or without attempts left.
     *
     * @return array<int, array{course_id: int, event_type: string, payload: array}> task id => delivery
     */
    public function deliveries(): array {
        global $DB;
        $deliveries = [];
        $records = $DB->get_records('task_adhoc', ['classname' => manager::get_canonical_class_name(self::TASK_CLASS)], 'id ASC', 'id, customdata');
        foreach ($records as $record) {
            $data = self::decode(json_decode((string) $record->customdata));
            if ($data !== null) {
                $deliveries[(int) $record->id] = $data;
            }
        }
        return $deliveries;
    }

    /**
     * The tasks that hold a delivery of a student.
     *
     * @param int $userid
     * @param int[]|null $courseids only these courses; null for every course
     * @return int[] task ids
     */
    public function task_ids_of_user(int $userid, ?array $courseids = null): array {
        $ids = [];
        foreach ($this->deliveries() as $id => $data) {
            if (($data['payload']['userid'] ?? null) === $userid && ($courseids === null || in_array($data['course_id'], $courseids, true))) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * The courses where a student has a delivery waiting.
     *
     * @param int $userid
     * @return int[] ascending
     */
    public function course_ids_of_user(int $userid): array {
        $courses = [];
        foreach ($this->deliveries() as $data) {
            if (($data['payload']['userid'] ?? null) === $userid) {
                $courses[] = $data['course_id'];
            }
        }
        $courses = array_values(array_unique($courses));
        sort($courses);
        return $courses;
    }

    /**
     * The students that have a delivery waiting in a course.
     *
     * @param int $courseid
     * @return int[] ascending
     */
    public function user_ids_in_course(int $courseid): array {
        $users = [];
        foreach ($this->deliveries() as $data) {
            $userid = $data['payload']['userid'] ?? null;
            if ($data['course_id'] === $courseid && is_int($userid) && $userid > 0) {
                $users[] = $userid;
            }
        }
        $users = array_values(array_unique($users));
        sort($users);
        return $users;
    }

    /**
     * Remove the tasks that hold a delivery of a student.
     *
     * @param int $userid
     * @param int[]|null $courseids only these courses; null for every course
     * @return int how many tasks were removed
     */
    public function delete_of_user(int $userid, ?array $courseids = null): int {
        return $this->delete($this->task_ids_of_user($userid, $courseids));
    }

    /**
     * Remove the tasks that hold a delivery of a course, of any student or of none.
     *
     * @param int $courseid
     * @return int how many tasks were removed
     */
    public function delete_of_course(int $courseid): int {
        $ids = [];
        foreach ($this->deliveries() as $id => $data) {
            if ($data['course_id'] === $courseid) {
                $ids[] = $id;
            }
        }
        return $this->delete($ids);
    }

    /**
     * Remove tasks by identifier.
     *
     * @param int[] $ids
     * @return int how many
     */
    private function delete(array $ids): int {
        global $DB;
        if ($ids) {
            $DB->delete_records_list('task_adhoc', 'id', $ids);
        }
        return count($ids);
    }
}
