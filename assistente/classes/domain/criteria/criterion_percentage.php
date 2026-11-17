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

namespace local_assistente\domain\criteria;

/**
 * Validity of a numeric criterion: a percentage from 0 to 100 with at most three decimals (DEC-40 D1).
 *
 * Used for the activity criterion and the frequency criterion (number(10,3) fields). The forum criterion is not a
 * percentage: it is 0 (disabled) or 1 (participation required), validated by the repository domain.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criterion_percentage {
    /** @var int number of decimals allowed (number(10,3) in the schema) */
    public const DECIMALS = 3;

    /**
     * Whether a value is a valid percentage.
     *
     * Accepted: integers, floats and plain decimal strings ("60", "33.333"). Rejected: null, booleans, empty
     * strings, scientific notation, values below 0 or above 100, and more than three decimals.
     *
     * @param mixed $value
     * @return bool
     */
    public static function is_valid(mixed $value): bool {
        if (is_string($value)) {
            if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
                return false;
            }
            $value = (float) $value;
        } else if (is_int($value)) {
            $value = (float) $value;
        } else if (!is_float($value) || is_nan($value) || is_infinite($value)) {
            return false;
        }
        if ($value < 0.0 || $value > 100.0) {
            return false;
        }
        return abs(round($value, self::DECIMALS) - $value) < 1e-9;
    }
}
