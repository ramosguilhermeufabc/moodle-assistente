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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the percentage rule of the numeric criteria (DEC-40 D1; quickstart 020 Q6).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(criterion_percentage::class)]
final class criterion_percentage_test extends \basic_testcase {
    /**
     * Valid values.
     *
     * @return array
     */
    public static function valid_values(): array {
        return [
            'zero' => [0], 'hundred' => [100], 'integer' => [60], 'float' => [60.5], 'three decimals' => [33.333],
            'zero float' => [0.0], 'string integer' => ['60'], 'string decimal' => ['33.333'], 'one decimal' => ['0.1'],
            'hundred decimal' => ['100.000'],
        ];
    }

    /**
     * Invalid values.
     *
     * @return array
     */
    public static function invalid_values(): array {
        return [
            'above' => [100.5], 'above integer' => [101], 'negative' => [-1], 'negative float' => [-0.001],
            'four decimals' => [60.1234], 'string four decimals' => ['60.1234'], 'null' => [null], 'true' => [true],
            'false' => [false], 'empty' => [''], 'text' => ['abc'], 'scientific' => ['1e2'], 'comma' => ['60,5'],
            'nan' => [NAN], 'infinite' => [INF], 'array' => [[60]], 'spaces' => [' 60 '], 'sign' => ['+60'],
        ];
    }

    /**
     * A valid percentage is accepted.
     *
     * @param mixed $value
     */
    #[DataProvider('valid_values')]
    public function test_valid_values_are_accepted(mixed $value): void {
        $this->assertTrue(criterion_percentage::is_valid($value));
    }

    /**
     * Anything else is rejected.
     *
     * @param mixed $value
     */
    #[DataProvider('invalid_values')]
    public function test_invalid_values_are_rejected(mixed $value): void {
        $this->assertFalse(criterion_percentage::is_valid($value));
    }
}
