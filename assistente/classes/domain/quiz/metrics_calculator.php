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
 * Deterministic metrics of the answers to a questionnaire (T070; spec-011; DEC-24, DEC-36). No LLM, no Moodle, no database: the
 * caller (the results handler, T072) selects the attempts and passes plain values.
 *
 * Rules:
 * - Only answers with a grade count; an answer waiting for manual grading (fraction null) is ignored (D1). A question with no graded
 *   answer has no metrics (null): the state is `no_data` and the value is not converted into zero (DEC-24, P-06).
 * - Percentages go from 0 to 100 and are truncated, never rounded, to three decimals (D2): 2/3 gives 66.666.
 * - `correct_count` counts the answers with the full fraction and `incorrect_count` those with a zero fraction; partial answers are in
 *   neither. `average_correctness` is the mean of the fractions, in percent.
 * - `partial_result` is the mean, among the answers that have the items, of correct items answered over correct items, in percent, and only
 *   for a question with several correct items; null otherwise (Q-011-07, decided 26/10/2026). It is independent of the grade: an answer
 *   without items is left out of that mean, never counted as zero. The rule that gives the items is multiple_choice_selection.
 * - The aggregate takes one final result per student, from the attempts by the grading method of the questionnaire (highest, average,
 *   first or last), as a percentage of the maximum grade (D3). It has the mean, the POPULATION standard deviation, the minimum and
 *   the maximum. With no student result the aggregate is null (`no_data`).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class metrics_calculator {
    /** @var string[] grading methods of the questionnaire */
    public const METHODS = ['highest', 'average', 'first', 'last'];

    /** @var float tolerance when comparing a fraction with 0 and 1 */
    private const EPSILON = 1e-9;

    /**
     * Metrics of one question.
     *
     * @param array $answers list of ['fraction' => float|null (0 to 1; null = not graded),
     *                                'correct_items_answered' => int|null, 'total_correct_items' => int|null]
     * @return question_metrics|null null when no answer has a grade
     * @throws \InvalidArgumentException for a fraction outside 0 to 1 or inconsistent items
     */
    public function question_metrics(array $answers): ?question_metrics {
        $fractions = [];
        $partials = [];
        $multiple = false;
        foreach ($answers as $answer) {
            $fraction = $answer['fraction'] ?? null;
            if ($fraction !== null) {
                if (!is_numeric($fraction) || $fraction < -self::EPSILON || $fraction > 1 + self::EPSILON) {
                    throw new \InvalidArgumentException('The fraction of an answer must be from 0 to 1.');
                }
                $fractions[] = min(1.0, max(0.0, (float) $fraction));
            }

            // The partial result does not depend on the grade (Q-011-07): an answer with its items counts even when it has no grade.
            // An answer with no items (null) is no data for this indicator and is left out, not taken as zero.
            $total = $answer['total_correct_items'] ?? null;
            $answered = $answer['correct_items_answered'] ?? null;
            if ($total !== null && $total > 1 && $answered !== null) {
                if ($answered < 0 || $answered > $total) {
                    throw new \InvalidArgumentException('The correct items answered must be from 0 to the total of correct items.');
                }
                $multiple = true;
                $partials[] = $answered / $total;
            }
        }
        if (!$fractions) {
            // No graded answer: the question has no metrics (no_data), and the indicator, which needs them, is not stored either.
            return null;
        }

        return new question_metrics(
            self::percent(array_sum($fractions) / count($fractions)),
            count(array_filter($fractions, static fn(float $f) => $f >= 1 - self::EPSILON)),
            count(array_filter($fractions, static fn(float $f) => $f <= self::EPSILON)),
            $multiple && $partials ? self::percent(array_sum($partials) / count($partials)) : null
        );
    }

    /**
     * Final result of a student: the percentage of the maximum grade by the grading method of the questionnaire.
     *
     * @param float[] $attempts percentages (0 to 100) of the finished attempts, in the order of the attempts
     * @param string $method one of METHODS
     * @return float|null null when the student has no attempt
     * @throws \InvalidArgumentException for an unknown method
     */
    public function final_result(array $attempts, string $method): ?float {
        if (!in_array($method, self::METHODS, true)) {
            throw new \InvalidArgumentException("Unknown grading method '{$method}'.");
        }
        $attempts = array_values($attempts);
        if (!$attempts) {
            return null;
        }
        $value = match ($method) {
            'highest' => max($attempts),
            'average' => array_sum($attempts) / count($attempts),
            'first' => $attempts[0],
            'last' => $attempts[count($attempts) - 1],
        };
        return self::truncate((float) $value);
    }

    /**
     * Aggregate over the final result of each student.
     *
     * @param float[] $results final results in percent, one per student
     * @return aggregate_metrics|null null when there is no result
     */
    public function aggregate(array $results): ?aggregate_metrics {
        $results = array_values(array_map('floatval', $results));
        if (!$results) {
            return null;
        }
        $mean = array_sum($results) / count($results);
        $variance = array_sum(array_map(static fn(float $r) => ($r - $mean) ** 2, $results)) / count($results);

        return new aggregate_metrics(
            self::truncate($mean),
            self::truncate(sqrt($variance)),
            self::truncate(min($results)),
            self::truncate(max($results))
        );
    }

    /**
     * A fraction from 0 to 1 as a percentage truncated to three decimals.
     *
     * @param float $fraction
     * @return float
     */
    private static function percent(float $fraction): float {
        return self::truncate($fraction * 100);
    }

    /**
     * Truncate (never round) to three decimals. The value is first rounded to nine decimals to discard binary noise, so that
     * 0.29 * 100 (28.999999999999996) gives 29.000 and not 28.999.
     *
     * @param float $value
     * @return float
     */
    private static function truncate(float $value): float {
        return \local_assistente\domain\truncated_percentage::of($value);
    }
}
