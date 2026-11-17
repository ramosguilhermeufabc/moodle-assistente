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
 * Metrics of one question, from its graded answers (T070; spec-011, DEC-36 D1, D2).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_metrics {
    /**
     * Constructor.
     *
     * @param float $averagecorrectness average of the fraction obtained, in percent (0 to 100, truncated to three decimals)
     * @param int $correctcount answers with the full fraction
     * @param int $incorrectcount answers with a zero fraction (partial answers are in neither count)
     * @param float|null $partialresult average of correct items answered over correct items, in percent; null for a question
     *                                  that does not have several correct items
     */
    public function __construct(
        /** @var float average correctness in percent */
        public readonly float $averagecorrectness,
        /** @var int answers with the full fraction */
        public readonly int $correctcount,
        /** @var int answers with a zero fraction */
        public readonly int $incorrectcount,
        /** @var float|null partial result in percent */
        public readonly ?float $partialresult
    ) {
    }
}
