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

use local_assistente\domain\criteria_status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the criteria evaluator (T091; quickstart 015 Q1, Q2, Q5 to Q12; DEC-11, DEC-40, DEC-39, DEC-24, DEC-54).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(criteria_evaluator::class)]
#[CoversClass(criteria_evaluation::class)]
final class criteria_evaluator_test extends \basic_testcase {
    /** @var int the moment of the evaluation */
    private const NOW = 1_000_000;

    /**
     * The criteria of a course; by default 60 for the activities, forum required, 40 for the frequency.
     *
     * @param array $overrides
     * @return array
     */
    private static function criteria(array $overrides = []): array {
        return $overrides + ['activity_criterion' => 60.0, 'forum_criterion' => 1, 'frequency_criterion' => 40.0];
    }

    /**
     * Facts of a forum that is eligible, available and past its reference date.
     *
     * @param int $cmid
     * @param bool $posted
     * @param array $overrides
     * @return array
     */
    private static function forum(int $cmid, bool $posted, array $overrides = []): array {
        return $overrides + [
            'cmid' => $cmid, 'eligible' => true, 'available' => true, 'reference_date' => self::NOW - 100, 'posted' => $posted,
        ];
    }

    /**
     * A row of the frequency of a section.
     *
     * @param int $participating
     * @param int $total
     * @return array
     */
    private static function section(int $participating, int $total): array {
        return ['total_activities' => $total, 'participating_activities' => $participating];
    }

    /**
     * Evaluate with the moment fixed.
     *
     * @param array $criteria
     * @param float|null $total
     * @param array $forums
     * @param array $frequency
     * @return criteria_evaluation
     */
    private function evaluate(array $criteria, ?float $total = 80.0, array $forums = [], array $frequency = []): criteria_evaluation {
        return (new criteria_evaluator())->evaluate($criteria, $total, $forums, $frequency, self::NOW);
    }

    /**
     * Q1, Q2, Q5: just below, equal and just above the criterion of the activities.
     *
     * @return array
     */
    public static function activity_boundaries(): array {
        return [
            'just below' => [59.999, criteria_status::BELOW],
            'equal' => [60.0, criteria_status::MEETS],
            'just above' => [60.001, criteria_status::MEETS],
            'zero' => [0.0, criteria_status::BELOW],
            'full' => [100.0, criteria_status::MEETS],
            'noise below the third decimal is truncated, not rounded' => [59.9999, criteria_status::BELOW],
        ];
    }

    #[DataProvider('activity_boundaries')]
    public function test_the_activity_criterion_meets_with_greater_or_equal(float $total, criteria_status $expected): void {
        $result = $this->evaluate(self::criteria(), $total);

        $this->assertSame($expected, $result->activity);
    }

    /**
     * Q6: a student without a course total has no data, which is not a zero.
     */
    public function test_a_student_without_a_course_total_has_no_data(): void {
        $result = $this->evaluate(self::criteria(), null);

        $this->assertSame(criteria_status::NO_DATA, $result->activity);
        $this->assertNull($result->activityvalue);
    }

    /**
     * A criterion of zero is met by any total, even a real zero, and the total of zero is a value, not an absence.
     */
    public function test_a_zero_total_is_a_value_and_a_zero_criterion_is_met_by_it(): void {
        $this->assertSame(criteria_status::MEETS, $this->evaluate(self::criteria(['activity_criterion' => 0.0]), 0.0)->activity);
        $this->assertSame(0.0, $this->evaluate(self::criteria(['activity_criterion' => 0.0]), 0.0)->activityvalue);
    }

    /**
     * The criterion is read as stored: a decimal string and an integer are valid, and the limit is not a constant of the class.
     */
    public function test_the_limits_come_from_the_criteria_only(): void {
        $this->assertSame(criteria_status::MEETS, $this->evaluate(self::criteria(['activity_criterion' => '33.333']), 33.333)->activity);
        $this->assertSame(criteria_status::BELOW, $this->evaluate(self::criteria(['activity_criterion' => 33.334]), 33.333)->activity);
        $this->assertSame(criteria_status::MEETS, $this->evaluate(self::criteria(['activity_criterion' => 90]), 90.0)->activity);
        $this->assertSame(criteria_status::BELOW, $this->evaluate(self::criteria(['activity_criterion' => 90]), 89.0)->activity);
    }

    /**
     * Q7: two eligible forums past their date and a post in only one of them is below.
     */
    public function test_a_missing_post_in_a_due_forum_is_below(): void {
        $result = $this->evaluate(self::criteria(), 80.0, [self::forum(10, true), self::forum(11, false)]);

        $this->assertSame(criteria_status::BELOW, $result->forum);
        $this->assertSame([10, 11], $result->forumsdue);
        $this->assertSame([11], $result->forumsmissing);
    }

    /**
     * Q8: posts in both due forums meet the criterion, and a third forum that is not due yet is not required.
     */
    public function test_a_forum_not_due_yet_is_not_required(): void {
        $result = $this->evaluate(self::criteria(), 80.0, [
            self::forum(10, true), self::forum(11, true), self::forum(12, false, ['reference_date' => self::NOW + 100]),
        ]);

        $this->assertSame(criteria_status::MEETS, $result->forum);
        $this->assertSame([10, 11], $result->forumsdue);
        $this->assertSame([], $result->forumsmissing);
    }

    /**
     * Q9: a forum criterion of 0 is disabled: always met, with or without forums, with or without posts.
     */
    public function test_a_disabled_forum_criterion_is_always_met(): void {
        $off = self::criteria(['forum_criterion' => 0]);

        $this->assertSame(criteria_status::MEETS, $this->evaluate($off, 80.0, [self::forum(10, false)])->forum);
        $this->assertSame(criteria_status::MEETS, $this->evaluate($off, 80.0, [])->forum);
    }

    /**
     * Q10: no eligible forum past its date, or a forum the student cannot see, has no data.
     */
    public function test_without_a_due_forum_available_to_the_student_the_forum_has_no_data(): void {
        $none = $this->evaluate(self::criteria(), 80.0, []);
        $notdue = $this->evaluate(self::criteria(), 80.0, [self::forum(10, false, ['reference_date' => self::NOW + 1])]);
        $unavailable = $this->evaluate(self::criteria(), 80.0, [self::forum(10, false, ['available' => false])]);
        $noteligible = $this->evaluate(self::criteria(), 80.0, [self::forum(10, false, ['eligible' => false])]);
        $nodate = $this->evaluate(self::criteria(), 80.0, [self::forum(10, false, ['reference_date' => null])]);

        foreach ([$none, $notdue, $unavailable, $noteligible, $nodate] as $result) {
            $this->assertSame(criteria_status::NO_DATA, $result->forum);
            $this->assertSame([], $result->forumsmissing);
        }
    }

    /**
     * DEC-54: the reference date of the forum has passed only when the moment is strictly after it.
     */
    public function test_on_the_second_of_the_reference_date_the_forum_is_not_due(): void {
        $on = $this->evaluate(self::criteria(), 80.0, [self::forum(10, false, ['reference_date' => self::NOW])]);
        $after = $this->evaluate(self::criteria(), 80.0, [self::forum(10, false, ['reference_date' => self::NOW - 1])]);

        $this->assertSame(criteria_status::NO_DATA, $on->forum);
        $this->assertSame(criteria_status::BELOW, $after->forum);
    }

    /**
     * A forum repeated in the facts is one forum, and a post in any of the repetitions counts.
     */
    public function test_a_repeated_forum_counts_once_and_a_post_in_any_repetition_counts(): void {
        $result = $this->evaluate(self::criteria(), 80.0, [self::forum(10, false), self::forum(10, true)]);

        $this->assertSame(criteria_status::MEETS, $result->forum);
        $this->assertSame([10], $result->forumsdue);
    }

    /**
     * Q11: sections of 1/2 and 3/8 give 4/10 = 40.000 globally, which meets 40; the mean of the sections (43.75) is not used.
     */
    public function test_the_frequency_is_global_and_not_the_mean_of_the_sections(): void {
        $result = $this->evaluate(self::criteria(), 80.0, [], [self::section(1, 2), self::section(3, 8)]);

        $this->assertSame(40.0, $result->frequencyvalue);
        $this->assertSame(criteria_status::MEETS, $result->frequency);

        $above = $this->evaluate(self::criteria(['frequency_criterion' => 40.001]), 80.0, [], [self::section(1, 2), self::section(3, 8)]);
        $this->assertSame(criteria_status::BELOW, $above->frequency);
    }

    /**
     * The global frequency is truncated to three decimals, never rounded: 2/3 is 66.666 and does not meet 66.667.
     */
    public function test_the_frequency_is_truncated(): void {
        $rows = [self::section(2, 3)];

        $this->assertSame(66.666, $this->evaluate(self::criteria(['frequency_criterion' => 66.666]), 80.0, [], $rows)->frequencyvalue);
        $this->assertSame(
            criteria_status::BELOW,
            $this->evaluate(self::criteria(['frequency_criterion' => 66.667]), 80.0, [], $rows)->frequency
        );
    }

    /**
     * Q12: a sum of zero activities has no data, which is not a zero; a real zero with activities is below.
     */
    public function test_a_zero_denominator_has_no_data_and_a_real_zero_is_below(): void {
        $nodata = $this->evaluate(self::criteria(), 80.0, [], [self::section(0, 0), self::section(0, 0)]);
        $empty = $this->evaluate(self::criteria(), 80.0, [], []);
        $zero = $this->evaluate(self::criteria(), 80.0, [], [self::section(0, 3)]);

        $this->assertSame(criteria_status::NO_DATA, $nodata->frequency);
        $this->assertNull($nodata->frequencyvalue);
        $this->assertSame(criteria_status::NO_DATA, $empty->frequency);
        $this->assertSame(criteria_status::BELOW, $zero->frequency);
        $this->assertSame(0.0, $zero->frequencyvalue);
    }

    /**
     * The three dimensions are independent: one without data does not change the others.
     */
    public function test_the_dimensions_are_independent(): void {
        $result = $this->evaluate(self::criteria(), null, [self::forum(10, true)], [self::section(0, 2)]);

        $this->assertSame(
            [criteria_status::NO_DATA, criteria_status::MEETS, criteria_status::BELOW],
            [$result->activity, $result->forum, $result->frequency]
        );
    }

    /**
     * The limits that were used are returned for the context of the execution (DEC-45 D2), as received.
     */
    public function test_the_limits_used_are_returned(): void {
        $result = $this->evaluate(self::criteria(['activity_criterion' => '55.5', 'forum_criterion' => '0']), 80.0);

        $this->assertSame(['activity_criterion' => 55.5, 'forum_criterion' => 0, 'frequency_criterion' => 40.0], $result->limits);
    }

    /**
     * The evaluator is deterministic: the same facts give the same result.
     */
    public function test_the_same_facts_give_the_same_result(): void {
        $forums = [self::forum(11, false), self::forum(10, true)];
        $rows = [self::section(1, 2), self::section(3, 8)];

        $this->assertEquals($this->evaluate(self::criteria(), 59.999, $forums, $rows), $this->evaluate(self::criteria(), 59.999, $forums, $rows));
    }

    /**
     * An invalid criterion or fact is refused, never read as a zero.
     *
     * @return array
     */
    public static function invalid_input(): array {
        return [
            'missing activity criterion' => [['forum_criterion' => 1, 'frequency_criterion' => 40.0], 80.0, [], []],
            'missing frequency criterion' => [['activity_criterion' => 60.0, 'forum_criterion' => 1], 80.0, [], []],
            'missing forum criterion' => [['activity_criterion' => 60.0, 'frequency_criterion' => 40.0], 80.0, [], []],
            'activity above 100' => [self::criteria(['activity_criterion' => 100.001]), 80.0, [], []],
            'frequency below 0' => [self::criteria(['frequency_criterion' => -1]), 80.0, [], []],
            'four decimals' => [self::criteria(['activity_criterion' => 60.0001]), 80.0, [], []],
            'null criterion' => [self::criteria(['activity_criterion' => null]), 80.0, [], []],
            'forum criterion 2' => [self::criteria(['forum_criterion' => 2]), 80.0, [], []],
            'forum criterion true' => [self::criteria(['forum_criterion' => true]), 80.0, [], []],
            'total above 100' => [self::criteria(), 100.5, [], []],
            'total below 0' => [self::criteria(), -0.1, [], []],
            'forum without module' => [self::criteria(), 80.0, [['eligible' => true]], []],
            'forum module zero' => [self::criteria(), 80.0, [self::forum(0, true)], []],
            'frequency without total' => [self::criteria(), 80.0, [], [['participating_activities' => 1]]],
            'frequency negative' => [self::criteria(), 80.0, [], [self::section(-1, 2)]],
            'participating above total' => [self::criteria(), 80.0, [], [self::section(3, 2)]],
        ];
    }

    #[DataProvider('invalid_input')]
    public function test_invalid_input_is_refused(array $criteria, ?float $total, array $forums, array $frequency): void {
        $this->expectException(\InvalidArgumentException::class);

        $this->evaluate($criteria, $total, $forums, $frequency);
    }
}
