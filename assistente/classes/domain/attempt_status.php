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
 * Status of a processing attempt (spec-007, DEC-32 D4).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum attempt_status: string {
    /** The attempt is running. */
    case PROCESSING = 'processing';
    /** The attempt finished successfully. */
    case PROCESSED = 'processed';
    /** The attempt failed. */
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
