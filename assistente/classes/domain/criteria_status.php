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
 * Value of local_assistente_criteria_result.activity_status, forum_status and frequency_status (DEC-40).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum criteria_status: int {
    /** The student is below the criterion. */
    case BELOW = 0;
    /** The student meets the criterion. */
    case MEETS = 1;
    /** No data to evaluate the criterion (DEC-24). */
    case NO_DATA = 2;

    /**
     * All allowed raw values.
     *
     * @return int[]
     */
    public static function values(): array {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }
}
