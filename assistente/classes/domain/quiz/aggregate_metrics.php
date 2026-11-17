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

namespace local_assistente\domain\quiz;

/**
 * Aggregate of a questionnaire over the final result of each student (T070; spec-011, DEC-36 D3).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class aggregate_metrics {
    /**
     * Constructor; every value is in percent of the maximum grade (0 to 100, truncated to three decimals).
     *
     * @param float $mean
     * @param float $populationstandarddeviation
     * @param float $minimum
     * @param float $maximum
     */
    public function __construct(
        /** @var float mean */
        public readonly float $mean,
        /** @var float population standard deviation */
        public readonly float $populationstandarddeviation,
        /** @var float minimum */
        public readonly float $minimum,
        /** @var float maximum */
        public readonly float $maximum
    ) {
    }
}
