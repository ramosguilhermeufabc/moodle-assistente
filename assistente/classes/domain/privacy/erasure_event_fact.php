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

namespace local_assistente\domain\privacy;

/**
 * An audit event of an erasure as the state sees it (T142): where it sits in the log, its type, its generation (the task) and its courses.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_event_fact {
    /** Event that opens a case: a retry was scheduled. */
    public const DEFERRED = 'deferred';
    /** Event that closes a case. */
    public const COMPLETED = 'completed';
    /** Event of an erasure that stopped. */
    public const STOPPED = 'stopped';

    /** The case was closed by the retry task. */
    public const VIA_RETRY = 'retry_task';
    /** The case was closed by a new request. */
    public const VIA_REQUEST = 'new_request';

    /**
     * Constructor.
     *
     * @param int $id identifier of the event in the log, ascending in time
     * @param string $type DEFERRED, COMPLETED or STOPPED
     * @param int $task the task of the case (its generation)
     * @param int[] $courses
     * @param string $detail the reason of a deferred or stopped event, or the way a case was closed
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly int $task,
        public readonly array $courses,
        public readonly string $detail = ''
    ) {
    }
}
