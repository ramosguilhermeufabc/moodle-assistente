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

namespace local_assistente\domain\frequency;

use local_assistente\domain\result_state;

/**
 * Frequency of a student in a section, with the activities that entered the calculation (T085; spec-014, DEC-39).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class frequency_result {
    /**
     * Constructor.
     *
     * @param float $percentage participating over total, in percent, truncated to three decimals; 0.0 with NO_DATA
     * @param result_state $state VALID when the total is above zero, NO_DATA when it is zero
     * @param int $total activities in the denominator
     * @param int $participating activities with participation
     * @param array $activities one ['activity_id' => int, 'participated' => 0|1, 'reference_date' => int] for each activity of the
     *                          denominator, by activity id
     * @param int[] $withoutdate activities left out because no date could be found for them (to be listed in the context)
     */
    public function __construct(
        /** @var float percentage */
        public readonly float $percentage,
        /** @var result_state state */
        public readonly result_state $state,
        /** @var int activities in the denominator */
        public readonly int $total,
        /** @var int activities with participation */
        public readonly int $participating,
        /** @var array rows of the activities of the denominator */
        public readonly array $activities,
        /** @var int[] activities without any date */
        public readonly array $withoutdate
    ) {
    }
}
