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

namespace local_assistente\application\delivery;

use core\task\manager;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\infrastructure\persistence\redelivery_task_repository;
use local_assistente\task\redeliver_event;

/**
 * Hands the delivery of an event that failed for lack of a lock to a retry task of Moodle (exception to DEC-32 D2).
 *
 * DEC-32 D2 keeps ad hoc tasks out of the processing of events. This is not that: the event is not processed here, it is only created
 * again, by the same event_service::enqueue(), when the lock that kept an observer from creating it was busy. The task is the pattern of
 * the retry of an erasure (DEC-58): an ad hoc task of Moodle, 8 attempts, a delay that starts at 60 seconds and doubles, no user (Moodle
 * refuses a task of a deleted or suspended account), and a custom data that holds identifiers only, never a name.
 *
 * Custom data: `course_id`, `event_type` and `payload`, the payload as event_type::normalize_payload() leaves it, with its `occurred_at`
 * where the type has one. So the identity of the occurrence is the one of the original delivery, and Moodle itself refuses a second task
 * for the same occurrence. The time of creation of the event is the time of the retry (the entry into the queue, DEC-32 D6).
 *
 * An event counts as preserved only when this class has confirmed the task: Moodle returned its identifier, or an equal task with attempts
 * left already exists. Anything else throws, after a line in the log of PHP; there is no recovery for a database that is down (that
 * line is not a durable record).
 *
 * It also keeps the memory of the locks that failed (lock_failure_budget) and finds and removes the tasks of a student, which the
 * erasure of the data of the student does inside its transaction. Reading the tasks is the work of redelivery_task_repository, the one reader
 * of their custom data, which the erasure of a course and the discovery of the Privacy API use too.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_redelivery_scheduler {
    /** Attempts of a redelivery task. */
    public const ATTEMPTS = 8;

    /** @var lock_failure_budget */
    private lock_failure_budget $budget;

    /** @var redelivery_task_repository */
    private redelivery_task_repository $tasks;

    /**
     * Constructor.
     *
     * @param lock_failure_budget|null $budget
     * @param redelivery_task_repository|null $tasks
     */
    public function __construct(?lock_failure_budget $budget = null, ?redelivery_task_repository $tasks = null) {
        $this->budget = $budget ?? new lock_failure_budget();
        $this->tasks = $tasks ?? new redelivery_task_repository();
    }

    /**
     * Whether a delivery that needs these locks should go straight to the retry, because one of them failed a moment ago in this process.
     *
     * @param string[] $resources
     * @return bool
     */
    public function is_blocked(array $resources): bool {
        return $this->budget->is_blocked($resources);
    }

    /**
     * Remember the lock that did not come.
     *
     * @param lock_unavailable_exception $e
     */
    public function record_failure(lock_unavailable_exception $e): void {
        $this->budget->record($e->resource());
    }

    /**
     * Schedule the delivery of an occurrence again.
     *
     * @param int $courseid
     * @param event_type $type
     * @param array $payload as event_type::normalize_payload() leaves it
     * @return int the task that holds the delivery: the one just queued, or the equal one that was already waiting
     * @throws \RuntimeException when the task could not be confirmed; the event is not preserved
     */
    public function defer(int $courseid, event_type $type, array $payload): int {
        try {
            $task = $this->task($courseid, $type, $payload);
            $id = $this->queue_task($task);
            if ($id === false) {
                $existing = $this->waiting_record($task);
                if ($existing === false) {
                    throw new \RuntimeException('The task was neither queued nor already waiting.');
                }
                return (int) $existing->id;
            }
            return (int) $id;
        } catch (\Throwable $e) {
            // The only record left is this line, and it has no guarantee: if the database is down, nothing durable can be written.
            error_log('local_assistente: the redelivery of an event could not be scheduled and the event is NOT preserved ('
                . self::case_label($courseid, $type->value, $payload) . ')' . self::warning_for($type->value) . '.');
            throw new \RuntimeException('The redelivery of an event could not be scheduled; the event is not preserved.');
        }
    }

    /**
     * Ask Moodle to queue the task, refusing a task equal to one that is waiting (a seam for tests).
     *
     * @param redeliver_event $task
     * @return int|false the identifier, or false when an equal task is waiting
     */
    protected function queue_task(redeliver_event $task): int|false {
        return manager::queue_adhoc_task($task, true);
    }

    /**
     * The equal task that is waiting, with attempts left (a seam for tests).
     *
     * @param redeliver_event $task
     * @return \stdClass|false
     */
    protected function waiting_record(redeliver_event $task): \stdClass|false {
        return manager::get_queued_adhoc_task_record($task, false);
    }

    /**
     * The tasks of redelivery of a student, in some courses.
     *
     * @param int $userid
     * @param int[]|null $courseids null for every course
     * @return int[] task ids
     */
    public function tasks_of(int $userid, ?array $courseids = null): array {
        return $this->tasks->task_ids_of_user($userid, $courseids);
    }

    /**
     * Remove the tasks of redelivery of a student, in some courses. A task that already started is not stopped by this: the guard it
     * runs inside event_service::enqueue() finds its record gone and creates nothing.
     *
     * @param int $userid
     * @param int[]|null $courseids null for every course
     * @return int how many tasks were removed
     */
    public function remove_tasks_of(int $userid, ?array $courseids = null): int {
        return $this->tasks->delete_of_user($userid, $courseids);
    }

    /**
     * What identifies a delivery in a message: course, type and the module (and the attempt, for a questionnaire). Never a student.
     *
     * @param int $courseid
     * @param string $type event_type value
     * @param array $payload
     * @return string
     */
    public static function case_label(int $courseid, string $type, array $payload): string {
        $label = "course {$courseid}, type {$type}";
        foreach (['cmid', 'attemptid'] as $key) {
            if (isset($payload[$key]) && is_int($payload[$key])) {
                $label .= ", {$key} {$payload[$key]}";
            }
        }
        return $label;
    }

    /**
     * What to add to a message about an event that may be lost, by type.
     *
     * @param string $type event_type value
     * @return string empty when nothing more is known
     */
    public static function warning_for(string $type): string {
        return $type === event_type::QUIZ_ATTEMPT_SUBMITTED->value
            ? '; this attempt may not be recovered automatically: there is no periodic check, and only a later event of the questionnaire or its closing recalculates it'
            : '';
    }

    /**
     * The custom data of a task, validated.
     *
     * @param mixed $customdata what Moodle gives back
     * @return array{course_id: int, event_type: string, payload: array}|null null when it is not a delivery this class made
     */
    public static function decode(mixed $customdata): ?array {
        return redelivery_task_repository::decode($customdata);
    }

    /**
     * The task of a delivery.
     *
     * @param int $courseid
     * @param event_type $type
     * @param array $payload
     * @return redeliver_event
     */
    private function task(int $courseid, event_type $type, array $payload): redeliver_event {
        $task = new redeliver_event();
        $task->set_custom_data(['course_id' => $courseid, 'event_type' => $type->value, 'payload' => $payload]);
        $task->set_attempts_available(self::ATTEMPTS);
        return $task;
    }
}
