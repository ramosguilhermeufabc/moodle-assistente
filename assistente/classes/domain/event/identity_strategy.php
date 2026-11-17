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

/**
 * How the occurrence of an event type is recognised when the same occurrence is received twice (DEC-08, DEC-10, DEC-21).
 *
 * Moodle events have no universal occurrence identifier, and `timecreated` has a precision of seconds, so the identity is
 * defined per event type and every strategy has a documented limitation (see event_type::limitation()).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum identity_strategy: string {
    /** The identity is the object itself: it happens once in its whole life (a post created, a quiz published). */
    case ONCE_PER_OBJECT = 'once_per_object';
    /** The identity is the object plus the moment of the Moodle event: each legitimate occurrence is a new event. */
    case OCCURRENCE = 'occurrence';
    /** The identity is the object, and only a still pending event is reused (the work is requested again after it ran). */
    case COALESCE_PENDING = 'coalesce_pending';
}
