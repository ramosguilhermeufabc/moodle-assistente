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

namespace local_assistente\application\queue;

/**
 * What a cycle did with an event. These are outcomes of a cycle, not states of an event: events only have the four states
 * of spec-006.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum cycle_outcome: string {
    /** The attempt succeeded; the event is processed. */
    case PROCESSED = 'processed';
    /** The attempt failed and attempts are left; the event is pending again, for the next cycle (DEC-32 D3). */
    case RETRY_LATER = 'retry_later';
    /** The attempt failed and it was the third; the event is in failure (DEC-09). */
    case FAILED = 'failed';
    /** The attempts were already used up (by interrupted runs); the event went to failure without calling the handler. */
    case EXHAUSTED = 'exhausted';
    /** Another run holds the lock of the event: it is not restarted and nothing is duplicated (DEC-16). */
    case SKIPPED_LOCKED = 'skipped_locked';
    /** Between the choice and the lock the event stopped being runnable (done by someone else, or the course was disabled). */
    case SKIPPED_NOT_RUNNABLE = 'skipped_not_runnable';
}
