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

namespace local_assistente\application;

/**
 * What happened to an occurrence handed to the queue.
 *
 * These are outcomes of the enqueue operation, not states of an event: events only have the four states of spec-006.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum enqueue_status: string {
    /** A new event was created. */
    case CREATED = 'created';
    /** The same occurrence already had an event; it was reused and nothing was created (DEC-08, DEC-21). */
    case REUSED = 'reused';
    /** The course has no configuration or the assistant is disabled; nothing was created (DEC-45 D3). */
    case SKIPPED_NOT_ENABLED = 'skipped_not_enabled';
    /**
     * The lock did not come in time and the delivery was handed to a retry task, which is confirmed to exist; no event was created now.
     * Only a queue given a redelivery scheduler (the one of the observers) returns it.
     */
    case DEFERRED = 'deferred';
    /** The guard given to enqueue() refused, inside the critical section; nothing was created (a revoked redelivery). */
    case SKIPPED_REVOKED = 'skipped_revoked';
}
