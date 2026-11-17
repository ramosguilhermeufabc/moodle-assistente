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

namespace local_assistente\application\privacy;

use core\task\manager;
use local_assistente\domain\privacy\erasure_key;
use local_assistente\domain\privacy\erasure_state_calculator;
use local_assistente\domain\privacy\erasure_task_fact;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\task\retry_student_erasure;

/**
 * Schedules and reads the retry tasks of the erasures (T142; DEC-58), only through the task API of Moodle.
 *
 * A task is the case: its custom data is the canonical key, so Moodle itself refuses a duplicate, and its identifier is the generation. The
 * attempts and the delay between them are those of the ad hoc tasks of Moodle: 8 attempts, with a delay that starts at 60 seconds and
 * doubles. The task is not given a user, because Moodle refuses a task of a user whose account is deleted or suspended.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_retry_scheduler {
    /** Attempts of a retry task. */
    public const ATTEMPTS = 8;

    /** @var erasure_audit */
    private erasure_audit $audit;

    /** @var erasure_state_calculator */
    private erasure_state_calculator $calculator;

    /**
     * Constructor.
     *
     * @param erasure_audit|null $audit
     * @param erasure_state_calculator|null $calculator
     */
    public function __construct(?erasure_audit $audit = null, ?erasure_state_calculator $calculator = null) {
        $this->audit = $audit ?? new erasure_audit();
        $this->calculator = $calculator ?? new erasure_state_calculator();
    }

    /**
     * The code of a failure that is worth a retry: a lock that did not come, or events that kept changing. Nothing else is (no database
     * failure).
     *
     * @param \Throwable $e
     * @return string|null a code of student_erasure_event::REASONS, or null when the failure is not transient
     */
    public static function reason_of(\Throwable $e): ?string {
        return match (true) {
            $e instanceof lock_unavailable_exception => 'lock_unavailable',
            $e instanceof student_event_stabilisation_exception => 'event_set_unstable',
            default => null,
        };
    }

    /**
     * Schedule the retry of a case. When Moodle already has the same task waiting, nothing is queued and nothing is written.
     *
     * @param erasure_key $key
     * @param string $reason
     * @return int the task: the new one, or the one that was already waiting
     * @throws \RuntimeException when the task could not be queued
     */
    public function schedule(erasure_key $key, string $reason): int {
        $task = $this->task($key, self::ATTEMPTS);
        $id = manager::queue_adhoc_task($task, true);
        if ($id === false) {
            $existing = manager::get_queued_adhoc_task_record($task, false);
            if ($existing === false) {
                throw new \RuntimeException('The retry of the erasure could not be scheduled.');
            }
            return (int) $existing->id;
        }
        $this->audit->deferred_once($key, (int) $id, $reason, self::ATTEMPTS);
        return (int) $id;
    }

    /**
     * Leave a task with no attempts, which never runs, as the visible mark of an erasure that needs an action. An existing task of the
     * case, stopped or waiting, is reused.
     *
     * @param erasure_key $key
     * @return int the task
     * @throws \RuntimeException when the task could not be queued
     */
    public function mark(erasure_key $key): int {
        $task = $this->task($key, 0);
        $existing = manager::get_queued_adhoc_task_record($task, true);
        if ($existing !== false) {
            return (int) $existing->id;
        }
        $id = manager::queue_adhoc_task($task, false);
        if ($id === false) {
            throw new \RuntimeException('The mark of the erasure could not be queued.');
        }
        return (int) $id;
    }

    /**
     * The retry tasks of a student.
     *
     * @param int $userid
     * @return erasure_task_fact[]
     */
    public function tasks_of(int $userid): array {
        $facts = [];
        foreach (manager::get_adhoc_tasks(retry_student_erasure::class) as $task) {
            try {
                $key = erasure_key::from_custom_data($task->get_custom_data());
            } catch (\InvalidArgumentException) {
                continue;
            }
            if ($key->userid() === $userid) {
                $facts[] = new erasure_task_fact(
                    (int) $task->get_id(),
                    $key->courses(),
                    $task->get_attempts_available(),
                    !empty($task->get_timestarted())
                );
            }
        }
        return $facts;
    }

    /**
     * Delete the stopped tasks that a completed erasure made obsolete. A task with attempts left, a running task and a newer task are
     * never deleted (see erasure_state_calculator::obsolete_tasks).
     *
     * @param erasure_key $key
     * @param int $completedtask the generation that was completed
     * @return int how many tasks were deleted
     */
    public function remove_obsolete(erasure_key $key, int $completedtask): int {
        $ids = $this->calculator->obsolete_tasks($this->tasks_of($key->userid()), $key->courses(), $completedtask);
        foreach ($ids as $id) {
            manager::delete_adhoc_task($id);
        }
        return count($ids);
    }

    /**
     * A retry task of a case.
     *
     * @param erasure_key $key
     * @param int $attempts
     * @return retry_student_erasure
     */
    private function task(erasure_key $key, int $attempts): retry_student_erasure {
        $task = new retry_student_erasure();
        $task->set_custom_data($key->to_custom_data());
        $task->set_attempts_available($attempts);
        return $task;
    }
}
