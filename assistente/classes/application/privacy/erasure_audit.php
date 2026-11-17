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

use local_assistente\domain\privacy\erasure_event_fact;
use local_assistente\domain\privacy\erasure_key;
use local_assistente\event\student_erasure_completed;
use local_assistente\event\student_erasure_deferred;
use local_assistente\event\student_erasure_event;
use local_assistente\event\student_erasure_stopped;
use local_assistente\infrastructure\moodle\erasure_audit_reader;

/**
 * The only writer of the audit events of the erasures (T142; DEC-58).
 *
 * Each event is written at most once for a type, a task and a case: the reader says whether it is already in the log, and only then the
 * event is triggered. The reading belongs to the reader and the writing to this class; the log is never queried here. A failure to write is
 * not allowed to change the outcome of an erasure: it becomes a fixed line in the PHP log, without any data. Each event is flushed to the log
 * store at once (see write()), because the buffer of the store is kept until the end of the process.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_audit {
    /** @var erasure_audit_reader */
    private erasure_audit_reader $reader;

    /**
     * Constructor.
     *
     * @param erasure_audit_reader|null $reader
     */
    public function __construct(?erasure_audit_reader $reader = null) {
        $this->reader = $reader ?? new erasure_audit_reader();
    }

    /**
     * A retry was scheduled: the case opens.
     *
     * @param erasure_key $key
     * @param int $task the task, which is the generation of the case
     * @param string $reason a code of student_erasure_event::REASONS
     * @param int $remaining attempts left
     * @return bool whether the event was written now
     */
    public function deferred_once(erasure_key $key, int $task, string $reason, int $remaining): bool {
        return $this->write(erasure_event_fact::DEFERRED, student_erasure_deferred::class, $key, $task, $reason, $remaining);
    }

    /**
     * The case was completed.
     *
     * @param erasure_key $key
     * @param int $task the generation of the case
     * @param string $via erasure_event_fact::VIA_RETRY or VIA_REQUEST
     * @return bool whether the event was written now
     */
    public function completed_once(erasure_key $key, int $task, string $via): bool {
        return $this->write(erasure_event_fact::COMPLETED, student_erasure_completed::class, $key, $task, $via, 0);
    }

    /**
     * The erasure stopped.
     *
     * @param erasure_key $key
     * @param int $task the generation of the case
     * @param string $reason a code of student_erasure_event::REASONS
     * @return bool whether the event was written now
     */
    public function stopped_once(erasure_key $key, int $task, string $reason): bool {
        return $this->write(erasure_event_fact::STOPPED, student_erasure_stopped::class, $key, $task, $reason, 0);
    }

    /**
     * Write an event unless the log already has it.
     *
     * @param string $type
     * @param class-string<student_erasure_event> $class
     * @param erasure_key $key
     * @param int $task
     * @param string $detail
     * @param int $remaining
     * @return bool
     */
    private function write(string $type, string $class, erasure_key $key, int $task, string $detail, int $remaining): bool {
        try {
            if ($this->reader->has($type, $task, $key)) {
                return false;
            }
            $class::for_case($key, $task, $detail, $remaining)->trigger();
            // The standard log store keeps events in memory until the process ends or the buffer fills. A task that runs for long (a runner that
            // waits for tasks) would hold the audit trail back, or lose it if it is killed, so it is written now. Later events still work.
            get_log_manager()->dispose();
            return true;
        } catch (\Throwable) {
            error_log("local_assistente: the audit event '{$type}' of an erasure could not be recorded.");
            return false;
        }
    }
}
