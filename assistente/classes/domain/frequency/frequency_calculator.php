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
use local_assistente\domain\truncated_percentage;

/**
 * Frequency of a student in a section from facts already read (T085; spec-014, DEC-25, DEC-39, DEC-24). Pure: no Moodle, no database,
 * no clock (the moment is an argument) and no LLM. The caller (the handler, T087) reads the facts; this class applies the rules.
 *
 * Rules:
 * - An activity is eligible when it is not a resource, has at least one of the categories (`graded`: numeric or scale grade,
 *   `completion`, `submission`, `forum`) and is available to the student. Resources are out even with completion (DEC-39 D1). A repeated
 *   activity counts once, with its participation joined.
 * - Participation, one is enough: a submission sent, a finished attempt, a grade given, a completion state of complete, passed or failed,
 *   or a post of the student in the forum. Access to the course is not participation and is not in this calculation (D3).
 * - Reference date: the due or closing date of the activity, otherwise the end date of the course, otherwise none, and then the activity is
 *   out of the calculation and listed in `withoutdate` (D2), also when the student took part.
 * - An eligible activity with a date enters the denominator when its date has passed (the moment is after it) or, before that, when the
 *   student already took part (D2). `participating` never exceeds `total`.
 * - Percentage: participating over total times 100, truncated (never rounded) to three decimals; 1/3 is 33.333. With a total of zero the
 *   value is 0 and the state NO_DATA; zero with a positive total is VALID (DEC-24). A technical failure is not handled here: it is an
 *   exception of the caller, never a zero.
 *
 * Temporal rule, confirmed by the person in charge on 27/10/2026 (DEC-54): "the date has passed" only when the moment of the calculation
 * is strictly after the date; on the very second of the date the activity has not entered yet.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class frequency_calculator {
    /** @var string[] categories that make an activity eligible */
    public const CATEGORIES = ['graded', 'completion', 'submission', 'forum'];

    /** @var string[] completion states that count as participation */
    public const PARTICIPATING_COMPLETION = ['complete', 'pass', 'fail'];

    /**
     * Calculate the frequency.
     *
     * @param array $activities list of facts, one for each module of the section, each with:
     *        activity_id (int > 0); is_resource (bool); categories (string[] among CATEGORIES); available (bool: visible to the student
     *        with the access restrictions met); due_date (int|null: delivery or closing time, null or 0 when none); participation (array
     *        with bool submitted, attempt_finished, graded and posted, and completion: null or one of complete, pass, fail, incomplete)
     * @param int|null $courseend end date of the course (null or 0 when unset)
     * @param int $now moment of the calculation, unix time
     * @return frequency_result
     * @throws \InvalidArgumentException for an invalid fact
     */
    public function calculate(array $activities, ?int $courseend, int $now): frequency_result {
        $courseend = ($courseend ?? 0) > 0 ? $courseend : null;
        $merged = [];
        foreach ($activities as $fact) {
            $id = $this->activity_id($fact);
            $participated = $this->participated($fact);
            if (isset($merged[$id])) {
                $merged[$id]['participated'] = $merged[$id]['participated'] || $participated;
                continue;
            }
            $merged[$id] = ['fact' => $fact, 'participated' => $participated];
        }

        $rows = [];
        $withoutdate = [];
        foreach ($merged as $id => $entry) {
            $fact = $entry['fact'];
            if (!$this->eligible($fact)) {
                continue;
            }
            $due = (int) ($fact['due_date'] ?? 0);
            $reference = $due > 0 ? $due : $courseend;
            if ($reference === null) {
                $withoutdate[] = $id;
                continue;
            }
            if ($now > $reference || $entry['participated']) {
                $rows[$id] = [
                    'activity_id' => $id,
                    'participated' => $entry['participated'] ? 1 : 0,
                    'reference_date' => $reference,
                ];
            }
        }

        $total = count($rows);
        $participating = array_sum(array_column($rows, 'participated'));
        return new frequency_result(
            $total === 0 ? 0.0 : truncated_percentage::of($participating / $total * 100),
            $total === 0 ? result_state::NO_DATA : result_state::VALID,
            $total,
            (int) $participating,
            $rows,
            $withoutdate
        );
    }

    /**
     * Whether an activity is eligible for the student.
     *
     * @param array $fact
     * @return bool
     */
    private function eligible(array $fact): bool {
        if (($fact['is_resource'] ?? false) || !($fact['available'] ?? false)) {
            return false;
        }
        return array_intersect(self::CATEGORIES, (array) ($fact['categories'] ?? [])) !== [];
    }

    /**
     * Whether the student took part in an activity.
     *
     * @param array $fact
     * @return bool
     * @throws \InvalidArgumentException for a completion state that is not known
     */
    private function participated(array $fact): bool {
        $p = (array) ($fact['participation'] ?? []);
        $completion = $p['completion'] ?? null;
        if ($completion !== null && !in_array($completion, [...self::PARTICIPATING_COMPLETION, 'incomplete'], true)) {
            throw new \InvalidArgumentException("Unknown completion state '{$completion}'.");
        }
        return !empty($p['submitted']) || !empty($p['attempt_finished']) || !empty($p['graded'])
            || in_array($completion, self::PARTICIPATING_COMPLETION, true) || !empty($p['posted']);
    }

    /**
     * The id of an activity, validated together with the other fields of the fact.
     *
     * @param mixed $fact
     * @return int
     * @throws \InvalidArgumentException for an invalid fact
     */
    private function activity_id(mixed $fact): int {
        if (!is_array($fact) || !is_int($fact['activity_id'] ?? null) || $fact['activity_id'] <= 0) {
            throw new \InvalidArgumentException('Each activity needs a positive integer activity_id.');
        }
        foreach ((array) ($fact['categories'] ?? []) as $category) {
            if (!in_array($category, self::CATEGORIES, true)) {
                throw new \InvalidArgumentException("Unknown category '{$category}'.");
            }
        }
        $due = $fact['due_date'] ?? null;
        if ($due !== null && (!is_int($due) || $due < 0)) {
            throw new \InvalidArgumentException('The due date must be a unix time or null.');
        }
        return $fact['activity_id'];
    }
}
