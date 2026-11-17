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

namespace local_assistente\domain\grade;

use local_assistente\domain\truncated_percentage;

/**
 * The grade of a student in an activity as a percentage of the range of the item (T076; spec-012, DEC-37 D2).
 *
 * `grade = (final grade - minimum) / (maximum - minimum) * 100`, truncated to three decimals. The same formula serves numeric items
 * and scales, using the minimum and the maximum of the scale (a scale of four levels goes from 1 to 4, so level 3 is 66.666). No
 * weight of the plugin enters: the final grade is the one of the gradebook.
 *
 * `no_data` is `null`, never zero (DEC-24, P-06): no grade given, or an item whose range is null (maximum equal to minimum, where the
 * percentage is undefined). A real zero is 0.0. The value is not limited to 0 to 100, because the specification does not say what to do
 * with a grade outside the range of its item; it is reported as the gradebook has it.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class grade_normalizer {
    /**
     * Percentage of the range of the item.
     *
     * @param float|null $grade final grade of the gradebook; null when none was given
     * @param float $minimum minimum of the item (or of the scale)
     * @param float $maximum maximum of the item (or of the scale)
     * @return float|null null for `no_data`
     * @throws \InvalidArgumentException when a number is not finite or the maximum is below the minimum
     */
    public function percentage(?float $grade, float $minimum, float $maximum): ?float {
        foreach ([$minimum, $maximum] as $limit) {
            if (!is_finite($limit)) {
                throw new \InvalidArgumentException('The range of the item must be finite.');
            }
        }
        if ($maximum < $minimum) {
            throw new \InvalidArgumentException('The maximum of the item must not be below its minimum.');
        }
        if ($grade === null) {
            return null;
        }
        if (!is_finite($grade)) {
            throw new \InvalidArgumentException('The grade must be finite.');
        }
        if ($maximum === $minimum) {
            return null;
        }
        return truncated_percentage::of(($grade - $minimum) / ($maximum - $minimum) * 100);
    }
}
