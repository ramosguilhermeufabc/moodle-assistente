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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the metrics of a questionnaire (T070; quickstart 011 Q1, Q2, Q5, Q6, Q8, Q13; DEC-24, DEC-36).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(metrics_calculator::class)]
#[CoversClass(question_metrics::class)]
#[CoversClass(aggregate_metrics::class)]
final class metrics_calculator_test extends \basic_testcase {
    /**
     * Answer to a question with one correct item.
     *
     * @param float|null $fraction
     * @return array
     */
    private static function answer(?float $fraction): array {
        return ['fraction' => $fraction];
    }

    /**
     * Q1: three correct items, a student marks two of them: 2/3 is 66.666 (truncated).
     */
    public function test_partial_result_is_truncated_to_three_decimals(): void {
        $metrics = (new metrics_calculator())->question_metrics([
            ['fraction' => 0.5, 'correct_items_answered' => 2, 'total_correct_items' => 3],
        ]);

        $this->assertSame(66.666, $metrics->partialresult);
    }

    /**
     * The partial result is the mean among the answers.
     */
    public function test_partial_result_is_the_mean_among_the_answers(): void {
        $metrics = (new metrics_calculator())->question_metrics([
            ['fraction' => 1.0, 'correct_items_answered' => 3, 'total_correct_items' => 3],
            ['fraction' => 0.0, 'correct_items_answered' => 0, 'total_correct_items' => 3],
            ['fraction' => 0.5, 'correct_items_answered' => 1, 'total_correct_items' => 2],
        ]);

        $this->assertSame(50.0, $metrics->partialresult);
    }

    /**
     * Q5: fractions 1, 0 and 0.5 give 50.000, one correct and one incorrect; the partial one is in neither count.
     */
    public function test_counts_and_average_correctness(): void {
        $metrics = (new metrics_calculator())->question_metrics([self::answer(1.0), self::answer(0.0), self::answer(0.5)]);

        $this->assertSame(50.0, $metrics->averagecorrectness);
        $this->assertSame(1, $metrics->correctcount);
        $this->assertSame(1, $metrics->incorrectcount);
    }

    /**
     * Q6: a question with one correct item has no partial result (null, not zero).
     */
    public function test_single_answer_question_has_no_partial_result(): void {
        $calculator = new metrics_calculator();

        $this->assertNull($calculator->question_metrics([self::answer(1.0), self::answer(0.0)])->partialresult);
        $this->assertNull($calculator->question_metrics([
            ['fraction' => 1.0, 'correct_items_answered' => 1, 'total_correct_items' => 1],
        ])->partialresult);
    }

    /**
     * Q13: no graded answer is no data (null), not zero; every graded answer at 0 is a valid zero.
     */
    public function test_no_graded_answer_is_no_data_and_zeros_are_valid(): void {
        $calculator = new metrics_calculator();

        $this->assertNull($calculator->question_metrics([]));
        $this->assertNull($calculator->question_metrics([self::answer(null), self::answer(null)]), 'Waiting for manual grading');
        $zero = $calculator->question_metrics([self::answer(0.0), self::answer(0.0)]);
        $this->assertSame(0.0, $zero->averagecorrectness);
        $this->assertSame(2, $zero->incorrectcount);
        $this->assertNull($calculator->aggregate([]));
        $this->assertSame(0.0, $calculator->aggregate([0.0, 0.0])->mean);
    }

    /**
     * Q7 (part): an answer not graded yet is left out; once graded it counts.
     */
    public function test_answer_waiting_for_manual_grading_is_left_out(): void {
        $calculator = new metrics_calculator();

        $waiting = $calculator->question_metrics([self::answer(1.0), self::answer(null)]);
        $graded = $calculator->question_metrics([self::answer(1.0), self::answer(0.0)]);

        $this->assertSame(100.0, $waiting->averagecorrectness);
        $this->assertSame(50.0, $graded->averagecorrectness);
    }

    /**
     * Binary noise does not lower a value: 0.29 is 29.000, not 28.999.
     */
    public function test_truncation_ignores_binary_noise(): void {
        $metrics = (new metrics_calculator())->question_metrics([self::answer(0.29)]);

        $this->assertSame(29.0, $metrics->averagecorrectness);
        $this->assertSame(33.333, (new metrics_calculator())->question_metrics([
            self::answer(0.0), self::answer(0.0), self::answer(1.0),
        ])->averagecorrectness);
    }

    /**
     * Q2: grades known by hand: mean, population standard deviation, minimum and maximum.
     */
    public function test_aggregate_with_known_values(): void {
        $aggregate = (new metrics_calculator())->aggregate([60.0, 80.0, 100.0]);

        $this->assertSame(80.0, $aggregate->mean);
        $this->assertSame(16.329, $aggregate->populationstandarddeviation, 'sqrt(800/3) = 16.3299..., truncated');
        $this->assertSame(60.0, $aggregate->minimum);
        $this->assertSame(100.0, $aggregate->maximum);
    }

    /**
     * The standard deviation is the population one: dividing by n, not n - 1.
     */
    public function test_standard_deviation_is_populational(): void {
        $aggregate = (new metrics_calculator())->aggregate([2.0, 4.0, 4.0, 4.0, 5.0, 5.0, 7.0, 9.0]);

        $this->assertSame(5.0, $aggregate->mean);
        $this->assertSame(2.0, $aggregate->populationstandarddeviation);
        $this->assertSame(0.0, (new metrics_calculator())->aggregate([70.0])->populationstandarddeviation);
    }

    /**
     * Q-011-07: the partial result is independent of the grade. An answer without a grade but with its items counts for the partial
     * result; an answer without items is left out of that mean (no data), and is not a zero.
     */
    public function test_partial_result_is_independent_of_the_grade_and_leaves_out_answers_without_items(): void {
        $metrics = (new metrics_calculator())->question_metrics([
            ['fraction' => 1.0, 'correct_items_answered' => 3, 'total_correct_items' => 3],
            ['fraction' => null, 'correct_items_answered' => 0, 'total_correct_items' => 3],
            ['fraction' => 0.5],
            ['fraction' => 0.0, 'total_correct_items' => 3],
        ]);

        $this->assertSame(50.0, $metrics->partialresult, 'Mean of 3/3 and 0/3 only; the two answers without items are not zeros');
        $this->assertSame(50.0, $metrics->averagecorrectness);
    }

    /**
     * Q-011-07: null is not zero. With no answer that has its items, the partial result is null; with answers whose items are all
     * zero it is a real 0.
     */
    public function test_partial_result_null_is_distinct_from_zero(): void {
        $calculator = new metrics_calculator();

        $none = $calculator->question_metrics([['fraction' => 1.0], ['fraction' => 0.0, 'total_correct_items' => 3]]);
        $zero = $calculator->question_metrics([['fraction' => 0.0, 'correct_items_answered' => 0, 'total_correct_items' => 3]]);

        $this->assertNull($none->partialresult);
        $this->assertSame(0.0, $zero->partialresult);
    }
    /**
     * Q8: attempts of 40% and 80%: the highest grade gives 80, the average 60; first and last follow the order of the attempts.
     */
    public function test_final_result_follows_the_grading_method(): void {
        $calculator = new metrics_calculator();
        $attempts = [40.0, 80.0];

        $this->assertSame(80.0, $calculator->final_result($attempts, 'highest'));
        $this->assertSame(60.0, $calculator->final_result($attempts, 'average'));
        $this->assertSame(40.0, $calculator->final_result($attempts, 'first'));
        $this->assertSame(80.0, $calculator->final_result($attempts, 'last'));
        $this->assertNull($calculator->final_result([], 'highest'), 'No attempt, no result');
    }

    /**
     * Invalid input is refused, never turned into a number.
     *
     * @param callable $call
     */
    #[DataProvider('invalid_calls')]
    public function test_invalid_input_is_refused(callable $call): void {
        $this->expectException(\InvalidArgumentException::class);

        $call(new metrics_calculator());
    }

    /**
     * Invalid inputs.
     *
     * @return array
     */
    public static function invalid_calls(): array {
        return [
            'fraction above 1' => [fn($c) => $c->question_metrics([['fraction' => 1.2]])],
            'negative fraction' => [fn($c) => $c->question_metrics([['fraction' => -0.5]])],
            'more items answered than correct' => [fn($c) => $c->question_metrics([
                ['fraction' => 1.0, 'correct_items_answered' => 4, 'total_correct_items' => 3],
            ])],
            'unknown method' => [fn($c) => $c->final_result([50.0], 'median')],
        ];
    }
}
