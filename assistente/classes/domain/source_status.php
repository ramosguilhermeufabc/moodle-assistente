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


namespace local_assistente\domain;

/**
 * Whether the Moodle record that a result came from still exists (T102; spec-017, DEC-42 D3). Derived when it is read: no field marks it.
 *
 * Only two states exist in the contract (`sourceRemoved(ref)` is true when the record is no longer there). A record that could not be
 * looked at is not a state: the lookup fails with an exception, and it is never taken as a removal.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum source_status: string {
    /** The Moodle record exists. */
    case EXISTS = 'exists';
    /** The Moodle record no longer exists (or is being deleted); the history that came from it stays. */
    case REMOVED = 'removed';
}
