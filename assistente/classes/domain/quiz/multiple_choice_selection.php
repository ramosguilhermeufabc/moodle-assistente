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
 * Rule of the partial result of a multiple choice answer with several correct alternatives (Q-011-07, decided 26/10/2026).
 *
 * The indicator is the proportion of correct alternatives selected over all correct alternatives of the question, and it is
 * independent of the grade of Moodle. The count of items answered that this rule returns is the numerator; the total of correct
 * alternatives is the denominator and is passed on by the caller:
 * - any incorrect alternative selected gives 0 items answered, whatever the correct ones selected;
 * - otherwise it is the number of correct alternatives selected, so all of them give the total (1) and a recorded empty selection
 *   gives 0;
 * - an answer with nothing recorded gives null: no data, left out of the average and never turned into zero.
 *
 * Whether a response is "recorded" is a fact of the attempt data: Moodle 5.2.4 keeps only the selected choices, so a submission with
 * nothing selected leaves no choice in the data and is the same as an unanswered question (observed, T075/Q-011-07); the empty
 * recorded selection is kept in this rule for data that does record it.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class multiple_choice_selection {
    /**
     * Correct alternatives answered, for the numerator of the partial result.
     *
     * @param int $selectedcorrect correct alternatives selected
     * @param int $selectedincorrect incorrect alternatives selected
     * @param bool $recorded whether the attempt holds a response for the question
     * @return int|null null when nothing is recorded
     * @throws \InvalidArgumentException for a negative count
     */
    public static function items_answered(int $selectedcorrect, int $selectedincorrect, bool $recorded): ?int {
        if ($selectedcorrect < 0 || $selectedincorrect < 0) {
            throw new \InvalidArgumentException('The counts of selected alternatives must not be negative.');
        }
        if (!$recorded) {
            return null;
        }
        return $selectedincorrect > 0 ? 0 : $selectedcorrect;
    }
}
