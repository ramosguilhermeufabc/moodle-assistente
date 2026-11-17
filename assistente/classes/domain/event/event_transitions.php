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

namespace local_assistente\domain\event;

use local_assistente\domain\event_status;

/**
 * Allowed state transitions of an event and its processing (spec-006; DEC-32 D4; DEC-09).
 *
 * ```
 * pending -> processing -> processed
 *                       -> pending   (an attempt failed and attempts are left)
 *                       -> failure   (the last attempt failed)
 * ```
 * `processed` and `failure` are final. No state outside the four of spec-006 exists.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_transitions {
    /** @var int total attempts per processing, the first included (DEC-09) */
    public const MAX_ATTEMPTS = 3;

    /**
     * Whether a state may follow another.
     *
     * @param event_status $from
     * @param event_status $to
     * @return bool
     */
    public static function is_allowed(event_status $from, event_status $to): bool {
        return match ($from) {
            event_status::PENDING => $to === event_status::PROCESSING,
            event_status::PROCESSING => in_array($to, [event_status::PROCESSED, event_status::PENDING, event_status::FAILURE], true),
            event_status::PROCESSED, event_status::FAILURE => false,
        };
    }

    /**
     * Require that a transition is allowed.
     *
     * @param event_status $from
     * @param event_status $to
     * @throws \DomainException when it is not
     */
    public static function assert_allowed(event_status $from, event_status $to): void {
        if (!self::is_allowed($from, $to)) {
            throw new \DomainException("An event cannot go from '{$from->value}' to '{$to->value}'.");
        }
    }

    /**
     * State after a failed attempt.
     *
     * @param int $attemptnumber number of the attempt that failed (1-based)
     * @return event_status pending while attempts are left, failure after the last one
     */
    public static function after_failure(int $attemptnumber): event_status {
        return $attemptnumber >= self::MAX_ATTEMPTS ? event_status::FAILURE : event_status::PENDING;
    }
}
