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

use local_assistente\application\privacy\erasure_audit;
use local_assistente\application\privacy\erasure_pending_exception;
use local_assistente\application\privacy\erasure_retry_scheduler;
use local_assistente\application\privacy\student_data_eraser;
use local_assistente\domain\privacy\erasure_event_fact;
use local_assistente\domain\privacy\erasure_key;

/**
 * Retries the erasure of a student that failed for lack of a lock (T142; DEC-58).
 *
 * An ad hoc task of Moodle, with its own attempts (8) and delay (60 seconds, doubling). Its custom data is the canonical key of the case
 * (the student and the courses), never a name; its identifier is the generation of the case. The erasure is the same idempotent one of the
 * Privacy API, with the same wait of 30 seconds for a lock and the same order of locks.
 *
 * - It worked: a `completed` event, and the stopped tasks that this made obsolete are deleted.
 * - A lock did not come: Moodle takes one attempt and tries again later. On the last attempt (the task sees only one left) a `stopped`
 *   event is written BEFORE the exception goes up, once. If Moodle spends the last attempt outside this code (a fatal error or an orphan
 *   task), no event exists and the state is read from the task alone.
 * - The name is unavailable, or the failure is not transient: nothing would change in another attempt, so the task gives up its attempts
 *   at once and a `stopped` event is written.
 * The exception that leaves has fixed text and counts: Moodle prints its message in the output and in the log of the task.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retry_student_erasure extends \core\task\adhoc_task {
    #[\Override]
    public function get_name() {
        return get_string('task:retry_student_erasure', 'local_assistente');
    }

    #[\Override]
    public function execute() {
        $key = erasure_key::from_custom_data($this->get_custom_data());
        $audit = new erasure_audit();
        $last = $this->get_attempts_available() <= 1;

        try {
            $result = (new student_data_eraser())->erase($key->userid(), $key->courses());
        } catch (\Throwable $e) {
            $reason = erasure_retry_scheduler::reason_of($e);
            if ($reason !== null) {
                if ($last && $audit->stopped_once($key, (int) $this->get_id(), 'attempts_exhausted')) {
                    error_log('local_assistente: the attempts to erase a student ended (task ' . $this->get_id() . ').');
                }
                throw new erasure_pending_exception(1, 0, $e);
            }
            $audit->stopped_once($key, (int) $this->get_id(), 'unexpected_error');
            $this->set_attempts_available(0);
            throw new erasure_pending_exception(0, 1, $e);
        }

        if (!$result['redaction_possible']) {
            $audit->stopped_once($key, (int) $this->get_id(), 'names_unavailable');
            $this->set_attempts_available(0);
            throw new erasure_pending_exception(0, 1);
        }

        $audit->completed_once($key, (int) $this->get_id(), erasure_event_fact::VIA_RETRY);
        (new erasure_retry_scheduler())->remove_obsolete($key, (int) $this->get_id());
        mtrace(student_data_eraser::summary($result));
    }
}
