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
 * Result of event_service::enqueue(): what happened and, when there is one, the event.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enqueue_result {
    /**
     * Constructor.
     *
     * @param enqueue_status $status
     * @param \stdClass|null $event the created or reused event; null when skipped
     */
    public function __construct(
        /** @var enqueue_status what happened */
        public readonly enqueue_status $status,
        /** @var \stdClass|null the created or reused event */
        public readonly ?\stdClass $event = null
    ) {
    }

    /**
     * Whether an event exists for the occurrence after the call (created now or reused).
     *
     * @return bool
     */
    public function has_event(): bool {
        return $this->event !== null;
    }
}
