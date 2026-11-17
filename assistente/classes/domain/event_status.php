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
 * Status of an assistant event and of its processing (spec-006, DEC-32 D4).
 *
 * The XMLDB does not restrict the values of char fields, so the application validates them.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum event_status: string {
    /** Waiting to be processed (also after a failed attempt with attempts left). */
    case PENDING = 'pending';
    /** Being processed under the event lock. */
    case PROCESSING = 'processing';
    /** Processed successfully. */
    case PROCESSED = 'processed';
    /** Failed after the third and last attempt. */
    case FAILURE = 'failure';

    /**
     * All allowed raw values.
     *
     * @return string[]
     */
    public static function values(): array {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }
}
