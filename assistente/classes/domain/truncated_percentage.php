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
 * Truncation of percentages to three decimals (DEC-36 D2, DEC-37 D2): truncated, never rounded, for the `number(10,3)` fields.
 *
 * The value is first rounded to six decimals of the scaled number to discard binary noise, so that 0.29 * 100, which is
 * 28.999999999999996, gives 29.000 and not 28.999.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class truncated_percentage {
    /**
     * Truncate a number to three decimals.
     *
     * @param float $value
     * @return float
     */
    public static function of(float $value): float {
        return floor(round($value * 1000, 6)) / 1000;
    }
}
