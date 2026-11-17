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
use local_assistente\domain\privacy\erasure_state_calculator;
use local_assistente\infrastructure\moodle\erasure_audit_reader;

/**
 * The erasure of one student asked through the Privacy API (T142; DEC-58): one attempt, and what follows from how it ended.
 *
 * - It worked: the erasure is done. If a retry had been scheduled for exactly this case, the case is closed with a `completed` event made
 *   by the new request.
 * - A lock did not come, or the events kept changing (nothing was changed): a retry task is scheduled and the outcome is DEFERRED.
 * - It worked but the name is unavailable, so the preserved texts could not be redacted or checked: the outcome is INCOMPLETE, never done.
 *   A task with no attempts marks the case and a `stopped` event records it. A retry would not help without the name.
 * - Any other failure (a database failure, for instance) is not retried here: it is thrown as it came.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_erasure_service {
    /** The erasure was done. */
    public const DONE = 'done';
    /** The erasure failed for a transient reason and a retry was scheduled. */
    public const DEFERRED = 'deferred';
    /** The erasure was done by identifier but the texts could not be redacted or checked. */
    public const INCOMPLETE = 'incomplete';

    /**
     * Constructor.
     *
     * @param student_data_eraser|null $eraser
     * @param erasure_retry_scheduler|null $scheduler
     * @param erasure_audit|null $audit
     * @param erasure_audit_reader|null $reader
     * @param erasure_state_calculator|null $calculator
     */
    public function __construct(
        private ?student_data_eraser $eraser = null,
        private ?erasure_retry_scheduler $scheduler = null,
        private ?erasure_audit $audit = null,
        private ?erasure_audit_reader $reader = null,
        private ?erasure_state_calculator $calculator = null
    ) {
        $this->eraser ??= new student_data_eraser();
        $this->scheduler ??= new erasure_retry_scheduler();
        $this->audit ??= new erasure_audit();
        $this->reader ??= new erasure_audit_reader();
        $this->calculator ??= new erasure_state_calculator();
    }

    /**
     * Erase a student in some courses.
     *
     * @param erasure_key $key
     * @return array{outcome: string, result: array|null, cause: \Throwable|null} the result of student_data_eraser::erase (null when it was deferred) and the transient failure that caused the deferral
     * @throws \Throwable the failures that are not transient
     */
    public function request(erasure_key $key): array {
        try {
            $result = $this->eraser->erase($key->userid(), $key->courses());
        } catch (\Throwable $e) {
            $reason = erasure_retry_scheduler::reason_of($e);
            if ($reason === null) {
                throw $e;
            }
            $this->scheduler->schedule($key, $reason);
            return ['outcome' => self::DEFERRED, 'result' => null, 'cause' => $e];
        }

        if (!$result['redaction_possible']) {
            $this->audit->stopped_once($key, $this->scheduler->mark($key), 'names_unavailable');
            return ['outcome' => self::INCOMPLETE, 'result' => $result, 'cause' => null];
        }

        $generation = $this->calculator->open_generation($key, $this->reader->events_of($key->userid()));
        if ($generation !== null) {
            $this->audit->completed_once($key, $generation, erasure_event_fact::VIA_REQUEST);
            $this->scheduler->remove_obsolete($key, $generation);
        }
        return ['outcome' => self::DONE, 'result' => $result, 'cause' => null];
    }
}
