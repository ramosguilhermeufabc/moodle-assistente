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
 * State that accompanies a calculated value in the service contract (DEC-01, DEC-24, P-06).
 *
 * A zero denominator keeps the numeric value 0 with NO_DATA; a real zero with a positive
 * denominator is VALID; a technical failure is ERROR and is never converted to zero.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum result_state: string {
    /** The value was calculated from data. */
    case VALID = 'valid';
    /** There is no data to calculate (zero denominator). */
    case NO_DATA = 'no_data';
    /** Technical failure. */
    case ERROR = 'error';

    /**
     * All allowed raw values.
     *
     * @return string[]
     */
    public static function values(): array {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }
}
