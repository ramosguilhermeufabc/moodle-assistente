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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the percentage of a grade in the range of its item (T076; quickstart 012 Q4, Q5, Q6, Q7; DEC-37 D2, DEC-24).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(grade_normalizer::class)]
#[CoversClass(\local_assistente\domain\truncated_percentage::class)]
final class grade_normalizer_test extends \basic_testcase {
    /**
     * Cases of the quickstart and edges of the formula.
     *
     * @return array
     */
    public static function percentages(): array {
        return [
            'Q4: 0 to 20, 13.333' => [13.333, 0.0, 20.0, 66.665],
            'Q5: 5 to 10, 7 (minimum other than zero)' => [7.0, 5.0, 10.0, 40.0],
            'Q6: scale of four levels, level 3' => [3.0, 1.0, 4.0, 66.666],
            'scale, lowest level' => [1.0, 1.0, 4.0, 0.0],
            'scale, highest level' => [4.0, 1.0, 4.0, 100.0],
            'real zero is a value' => [0.0, 0.0, 100.0, 0.0],
            'full grade' => [100.0, 0.0, 100.0, 100.0],
            'truncated, not rounded' => [2.0, 0.0, 3.0, 66.666],
            'binary noise does not lower the value' => [0.29, 0.0, 1.0, 29.0],
            'minimum at the end of the range' => [5.0, 5.0, 10.0, 0.0],
            'negative range' => [-5.0, -10.0, 0.0, 50.0],
        ];
    }

    /**
     * The percentage by case.
     *
     * @param float $grade
     * @param float $minimum
     * @param float $maximum
     * @param float $expected
     */
    #[DataProvider('percentages')]
    public function test_percentage(float $grade, float $minimum, float $maximum, float $expected): void {
        $this->assertSame($expected, (new grade_normalizer())->percentage($grade, $minimum, $maximum));
    }

    /**
     * Q7: no grade and a null range are no data (null); a grade of zero is a value, not no data.
     */
    public function test_no_data_is_null_and_zero_is_a_value(): void {
        $normalizer = new grade_normalizer();

        $this->assertNull($normalizer->percentage(null, 0.0, 10.0), 'No grade given');
        $this->assertNull($normalizer->percentage(5.0, 10.0, 10.0), 'Null range: the percentage is undefined');
        $this->assertNull($normalizer->percentage(null, 10.0, 10.0));
        $this->assertSame(0.0, $normalizer->percentage(0.0, 0.0, 10.0));
    }

    /**
     * A grade outside the range is reported as the gradebook has it, without being limited.
     */
    public function test_grade_outside_the_range_is_not_limited(): void {
        $normalizer = new grade_normalizer();

        $this->assertSame(110.0, $normalizer->percentage(11.0, 0.0, 10.0));
        $this->assertSame(-10.0, $normalizer->percentage(-1.0, 0.0, 10.0));
    }

    /**
     * Invalid numbers are refused, never turned into a value.
     *
     * @param callable $call
     */
    #[DataProvider('invalid_calls')]
    public function test_invalid_input_is_refused(callable $call): void {
        $this->expectException(\InvalidArgumentException::class);

        $call(new grade_normalizer());
    }

    /**
     * Invalid inputs.
     *
     * @return array
     */
    public static function invalid_calls(): array {
        return [
            'maximum below minimum' => [fn($n) => $n->percentage(5.0, 10.0, 0.0)],
            'not a number grade' => [fn($n) => $n->percentage(NAN, 0.0, 10.0)],
            'infinite grade' => [fn($n) => $n->percentage(INF, 0.0, 10.0)],
            'infinite maximum' => [fn($n) => $n->percentage(1.0, 0.0, INF)],
        ];
    }
}
