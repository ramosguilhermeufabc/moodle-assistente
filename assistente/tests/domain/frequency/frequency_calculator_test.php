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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the frequency calculator (T085; quickstart 014 Q1, Q2, Q5 to Q10, Q13; DEC-25, DEC-39, DEC-24).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(frequency_calculator::class)]
#[CoversClass(frequency_result::class)]
final class frequency_calculator_test extends \basic_testcase {
    /** @var int the moment of the calculation */
    private const NOW = 1_000_000;

    /**
     * Facts of an activity; by default an assignment that is available, graded and past due, without participation.
     *
     * @param int $id
     * @param array $overrides
     * @return array
     */
    private static function activity(int $id, array $overrides = []): array {
        return $overrides + [
            'activity_id' => $id,
            'is_resource' => false,
            'categories' => ['submission'],
            'available' => true,
            'due_date' => self::NOW - 100,
            'participation' => [],
        ];
    }

    /**
     * Facts of an activity in which the student took part.
     *
     * @param int $id
     * @param array $overrides
     * @return array
     */
    private static function taken(int $id, array $overrides = []): array {
        return self::activity($id, $overrides + ['participation' => ['submitted' => true]]);
    }

    /**
     * Calculate with the moment fixed.
     *
     * @param array $activities
     * @param int|null $courseend
     * @return frequency_result
     */
    private function calc(array $activities, ?int $courseend = null): frequency_result {
        return (new frequency_calculator())->calculate($activities, $courseend, self::NOW);
    }

    /**
     * Q1: one of three activities is 33.333; two of three is 66.666 (truncated, not rounded); all of them is 100.
     */
    public function test_percentage_is_participating_over_total_truncated(): void {
        $one = $this->calc([self::taken(1), self::activity(2), self::activity(3)]);
        $two = $this->calc([self::taken(1), self::taken(2), self::activity(3)]);
        $all = $this->calc([self::taken(1), self::taken(2)]);

        $this->assertSame(33.333, $one->percentage);
        $this->assertSame([1, 3], [$one->participating, $one->total]);
        $this->assertSame(66.666, $two->percentage);
        $this->assertSame(100.0, $all->percentage);
        $this->assertSame(result_state::VALID, $one->state);
    }

    /**
     * Q2: several participations in one activity count as one, and a repeated activity is one row.
     */
    public function test_an_activity_counts_once(): void {
        $result = $this->calc([
            self::activity(1, ['participation' => ['submitted' => true, 'posted' => true, 'graded' => true, 'completion' => 'complete']]),
            self::activity(1, ['participation' => ['attempt_finished' => true]]),
            self::activity(2),
        ]);

        $this->assertSame(2, $result->total);
        $this->assertSame(1, $result->participating);
        $this->assertSame(50.0, $result->percentage);
        $this->assertSame([1, 2], array_keys($result->activities));
    }

    /**
     * A repeated activity joins the participation of its facts.
     */
    public function test_a_repeated_activity_joins_the_participation(): void {
        $result = $this->calc([self::activity(1), self::activity(1, ['participation' => ['posted' => true]])]);

        $this->assertSame(1, $result->participating);
        $this->assertSame(1, $result->total);
    }

    /**
     * Q7: a submission, a finished attempt, a grade, a completion of complete, passed or failed, and a post each count as participation;
     * an incomplete completion, an empty record and nothing do not.
     *
     * @param array $participation
     * @param bool $counts
     */
    #[DataProvider('participations')]
    public function test_what_counts_as_participation(array $participation, bool $counts): void {
        $result = $this->calc([self::activity(1, ['participation' => $participation])]);

        $this->assertSame($counts ? 1 : 0, $result->participating);
        $this->assertSame(1, $result->total, 'The activity is in the denominator either way');
    }

    /**
     * Cases of participation.
     *
     * @return array
     */
    public static function participations(): array {
        return [
            'submission sent' => [['submitted' => true], true],
            'attempt finished' => [['attempt_finished' => true], true],
            'grade given' => [['graded' => true], true],
            'completion complete' => [['completion' => 'complete'], true],
            'completion passed' => [['completion' => 'pass'], true],
            'completion failed' => [['completion' => 'fail'], true],
            'a post' => [['posted' => true], true],
            'completion incomplete' => [['completion' => 'incomplete'], false],
            'everything false' => [['submitted' => false, 'attempt_finished' => false, 'graded' => false, 'posted' => false, 'completion' => null], false],
            'no record' => [[], false],
        ];
    }

    /**
     * Q5: a resource, even with completion, is out of the denominator; so is a module with none of the four categories.
     */
    public function test_resources_and_modules_without_a_category_are_out(): void {
        $result = $this->calc([
            self::taken(1),
            self::activity(2, ['is_resource' => true, 'categories' => ['completion'], 'participation' => ['completion' => 'complete']]),
            self::activity(3, ['categories' => []]),
        ]);

        $this->assertSame(1, $result->total);
        $this->assertSame([1], array_keys($result->activities));
    }

    /**
     * The four categories make an activity eligible.
     */
    public function test_each_category_makes_an_activity_eligible(): void {
        $result = $this->calc([
            self::activity(1, ['categories' => ['graded']]),
            self::activity(2, ['categories' => ['completion']]),
            self::activity(3, ['categories' => ['submission']]),
            self::activity(4, ['categories' => ['forum']]),
        ]);

        $this->assertSame(4, $result->total);
    }

    /**
     * Q6: an activity that is not available to the student is out for that student, even if the student took part.
     */
    public function test_an_unavailable_activity_is_out(): void {
        $out = $this->calc([self::taken(1, ['available' => false]), self::activity(2)]);
        $in = $this->calc([self::taken(1, ['available' => true]), self::activity(2)]);

        $this->assertSame(1, $out->total);
        $this->assertSame(0, $out->participating);
        $this->assertSame(2, $in->total);
    }

    /**
     * Q8: with no due date the end date of the course is the reference.
     */
    public function test_the_end_of_the_course_is_the_reference_without_a_due_date(): void {
        $result = $this->calc([self::activity(1, ['due_date' => null])], self::NOW - 50);

        $this->assertSame(1, $result->total);
        $this->assertSame(self::NOW - 50, $result->activities[1]['reference_date']);
    }

    /**
     * Q3 (the reference): the due date wins over the end of the course.
     */
    public function test_the_due_date_is_the_reference_when_there_is_one(): void {
        $result = $this->calc([self::activity(1, ['due_date' => self::NOW - 10])], self::NOW - 500);

        $this->assertSame(self::NOW - 10, $result->activities[1]['reference_date']);
    }

    /**
     * Q9: with no due date and no end date the activity is out of the calculation and listed, also when the student took part.
     */
    public function test_an_activity_without_any_date_is_out_and_listed(): void {
        $result = $this->calc([
            self::activity(1, ['due_date' => null]),
            self::taken(2, ['due_date' => 0]),
            self::activity(3),
        ], null);

        $this->assertSame([1, 2], $result->withoutdate);
        $this->assertSame(1, $result->total);
        $this->assertSame([3], array_keys($result->activities));
        $this->assertSame(
            $this->calc([self::activity(1, ['due_date' => null])], 0)->withoutdate,
            [1],
            'An unset end date (0) is the same as none'
        );
    }

    /**
     * Q10: an activity with a future date is in the denominator of the student who already took part and not of the one who did not.
     */
    public function test_a_future_activity_enters_only_when_the_student_took_part(): void {
        $future = self::NOW + 1000;
        $a = $this->calc([self::taken(1, ['due_date' => $future])]);
        $b = $this->calc([self::activity(1, ['due_date' => $future])]);

        $this->assertSame([1, 1, 100.0], [$a->total, $a->participating, $a->percentage]);
        $this->assertSame([0, 0, 0.0], [$b->total, $b->participating, $b->percentage]);
        $this->assertSame(result_state::NO_DATA, $b->state);
    }

    /**
     * Q11: when the date passes the activity enters the denominator of the one who did not take part, with participated = 0.
     */
    public function test_when_the_date_passes_the_activity_enters_with_participated_zero(): void {
        $facts = [self::activity(1, ['due_date' => self::NOW + 10])];

        $before = (new frequency_calculator())->calculate($facts, null, self::NOW);
        $after = (new frequency_calculator())->calculate($facts, null, self::NOW + 11);

        $this->assertSame(0, $before->total);
        $this->assertSame(1, $after->total);
        $this->assertSame(0, $after->activities[1]['participated']);
        $this->assertSame(0.0, $after->percentage);
        $this->assertSame(result_state::VALID, $after->state);
    }

    /**
     * The moment of the date itself: the date has not passed yet, and only a moment strictly after it enters (DEC-54, confirmed on 27/10/2026).
     */
    public function test_on_the_second_of_the_date_the_activity_has_not_entered(): void {
        $facts = [self::activity(1, ['due_date' => self::NOW])];

        $this->assertSame(0, $this->calc($facts)->total);
        $this->assertSame(1, (new frequency_calculator())->calculate($facts, null, self::NOW + 1)->total);
    }

    /**
     * Q13: no activity in the denominator is 0 with no_data; none participated with a positive total is 0.000 valid.
     */
    public function test_no_data_and_a_valid_zero(): void {
        $nodata = $this->calc([]);
        $zero = $this->calc([self::activity(1), self::activity(2)]);

        $this->assertSame([0.0, result_state::NO_DATA, 0, 0], [$nodata->percentage, $nodata->state, $nodata->total, $nodata->participating]);
        $this->assertSame([0.0, result_state::VALID, 2, 0], [$zero->percentage, $zero->state, $zero->total, $zero->participating]);
    }

    /**
     * The percentage never goes above 100: participation is counted only in the activities that are in the denominator.
     */
    public function test_participating_never_exceeds_total(): void {
        $result = $this->calc([
            self::taken(1, ['due_date' => self::NOW + 5]),
            self::taken(2),
            self::taken(3, ['available' => false]),
            self::taken(4, ['is_resource' => true]),
            self::taken(5, ['due_date' => null]),
        ]);

        $this->assertLessThanOrEqual($result->total, $result->participating);
        $this->assertLessThanOrEqual(100.0, $result->percentage);
        $this->assertSame([2, 2], [$result->total, $result->participating]);
    }

    /**
     * Binary noise does not lower the value: 7 of 10 is 70.000.
     */
    public function test_precision_of_the_truncation(): void {
        $facts = [];
        foreach (range(1, 10) as $id) {
            $facts[] = $id <= 7 ? self::taken($id) : self::activity($id);
        }

        $this->assertSame(70.0, $this->calc($facts)->percentage);
        $this->assertSame(14.285, $this->calc([...array_map(fn($i) => self::activity($i), range(2, 7)), self::taken(1)])->percentage, '1/7');
    }

    /**
     * Invalid facts are refused, never turned into a value.
     *
     * @param array $facts
     */
    #[DataProvider('invalid_facts')]
    public function test_invalid_facts_are_refused(array $facts): void {
        $this->expectException(\InvalidArgumentException::class);

        $this->calc($facts);
    }

    /**
     * Invalid inputs.
     *
     * @return array
     */
    public static function invalid_facts(): array {
        return [
            'activity id zero' => [[self::activity(0)]],
            'activity id not an integer' => [[['activity_id' => '4'] + self::activity(4)]],
            'unknown category' => [[self::activity(1, ['categories' => ['wiki']])]],
            'negative due date' => [[self::activity(1, ['due_date' => -5])]],
            'unknown completion state' => [[self::activity(1, ['participation' => ['completion' => 'maybe']])]],
            'a fact that is not an array' => [['not a fact']],
        ];
    }
}
