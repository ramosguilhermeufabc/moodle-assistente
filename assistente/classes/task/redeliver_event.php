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

namespace local_assistente\task;

use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\infrastructure\persistence\redelivery_task_repository;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * Delivers again an event that an observer could not create for lack of a lock (exception to DEC-32 D2).
 *
 * It does not process anything: it repeats event_service::enqueue() with the course, type and payload the observer had, so the identity
 * of the occurrence is the original one and a delivery that was made meanwhile by any other path is reused, not duplicated. It runs in
 * the background, so it waits the default 30 seconds for a lock, as the queue does. It is the pattern of the retry of an erasure
 * (DEC-58): an ad hoc task of Moodle with its own attempts (8) and delay (60 seconds, doubling).
 *
 * The whole delivery runs under the lock of the erasure of the course (unit_of_work::course_erasure_resource()), taken before anything else,
 * the configuration of the course included, and held until the delivery ends. The erasure of a course holds the same lock until after its
 * commit, so the two never overlap: a task that comes after the erasure finds the course gone, and an erasure that comes after a task removes
 * the event the task made. The lock comes first in the global order of the locks, so taking the lock of the identity and the lock of the
 * student after it goes upward. Only this task takes it: observers, the queue and the handlers do not.
 *
 * - It delivered, reused an event, or the course no longer has the assistant enabled: done.
 * - The guard refused: the task was removed (the erasure of the data of the student removes the tasks of the student) after Moodle handed
 *   it to this run, it has no identifier to confirm it still exists (it fails closed), or the account of the student is gone. Inside the
 *   critical section, under the lock of the student, the guard looks for the record of this very task and, when the delivery names a
 *   student, for the active account of that student; if either is missing, nothing is created. This is what keeps a task that had already
 *   started from recreating an event after the erasure. It does not close every window: Moodle deletes the account right after the plugins
 *   erased the data, so a task that runs between the end of the erasure and that deletion, for a student who is still active, creates its
 *   event, as a delivery that arrives right after the erasure always did.
 * - A lock did not come: Moodle takes one attempt and tries again later. On the last attempt the task says so in the log before the
 *   exception goes up.
 * - Anything else: another attempt would not change it, so the task gives up its attempts at once. This includes a failure of the database
 *   that would have passed on its own: the delivery is lost, with the line in the log that says so (the policy inherited from the retry of
 *   an erasure, DEC-58; it is not classified here).
 * The messages carry the course, the type and the module (and the attempt, for a questionnaire), never a student. Moodle prints the message
 * of the exception in the log of the task.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class redeliver_event extends \core\task\adhoc_task {
    #[\Override]
    public function get_name() {
        return get_string('task:redeliver_event', 'local_assistente');
    }

    #[\Override]
    public function execute() {
        $data = event_redelivery_scheduler::decode($this->get_custom_data());
        $type = $data === null ? null : event_type::tryFrom($data['event_type']);
        if ($data === null || $type === null) {
            $this->set_attempts_available(0);
            throw new \RuntimeException('local_assistente: a redelivery task has no valid data and will not be tried again (task ' . $this->get_id() . ').');
        }
        $courseid = $data['course_id'];
        $label = event_redelivery_scheduler::case_label($courseid, $data['event_type'], $data['payload']);
        $last = $this->get_attempts_available() <= 1;
        $taskid = $this->get_id();
        $userid = is_int($data['payload']['userid'] ?? null) && $data['payload']['userid'] > 0 ? $data['payload']['userid'] : null;
        // Under the lock of the student, inside the critical section. A task without an identifier cannot confirm that it is still wanted: it
        // fails closed. Otherwise its own record must still be there and, when the delivery names a student, the account must be active.
        $guard = static function () use ($taskid, $userid): bool {
            if ($taskid === null) {
                return false;
            }
            $repository = new redelivery_task_repository();
            return $repository->exists($taskid) && ($userid === null || $repository->user_is_active($userid));
        };

        try {
            // The lock of the erasure of the course first: the configuration of the course is read, and the event is made, under it.
            $result = $this->locks()->run_locked(
                unit_of_work::course_erasure_resource($courseid),
                fn() => $this->queue()->enqueue($courseid, $type, $data['payload'], null, $guard)
            );
        } catch (lock_unavailable_exception) {
            if ($last) {
                error_log('local_assistente: the attempts to redeliver an event ended; the event is NOT preserved (' . $label . ')'
                    . event_redelivery_scheduler::warning_for($data['event_type']) . '.');
            }
            throw new \RuntimeException("local_assistente: a redelivery is waiting for a lock ({$label}).");
        } catch (\Throwable) {
            $this->set_attempts_available(0);
            error_log('local_assistente: a redelivery failed and will not be tried again; the event is NOT preserved (' . $label . ')'
                . event_redelivery_scheduler::warning_for($data['event_type']) . '.');
            throw new \RuntimeException("local_assistente: a redelivery failed and will not be tried again ({$label}).");
        }
        mtrace('local_assistente: redelivery ' . $result->status->value . ' (' . $label . ')');
    }

    /**
     * The locks of the delivery: the default wait of 30 seconds, like the queue (a seam for tests).
     *
     * @return unit_of_work
     */
    protected function locks(): unit_of_work {
        return new unit_of_work();
    }

    /**
     * The queue the task delivers to: the default one, which waits 30 seconds for a lock like the queue does (a seam for tests).
     *
     * @return event_service
     */
    protected function queue(): event_service {
        return new event_service();
    }
}
