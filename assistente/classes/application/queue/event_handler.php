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
 * Handler of one event type, called by the queue processor (contracts/queue-processing.md; DEC-08, DEC-16, DEC-22).
 *
 * Rules for an implementation:
 * - It runs with the lock of the event held and NO transaction open. It may call the LLM; it must open its own short
 *   transactions to persist results, and persist them atomically (all or nothing).
 * - It must be idempotent: it can run again for the same event (retry after a failure, or recovery of an interrupted run), and
 *   the results must not be duplicated.
 * - It returns handler_result::success() or ::failure(class, reason). An exception it lets escape is recorded as a technical
 *   failure of the attempt.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface event_handler {
    /**
     * Process an event.
     *
     * @param \stdClass $event the event row (payload is JSON text)
     * @param \stdClass $processing the processing row of the event, with the attempt in progress
     * @return handler_result
     */
    public function handle(\stdClass $event, \stdClass $processing): handler_result;
}
