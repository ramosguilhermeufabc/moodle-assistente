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
 * Tests of the rule of the partial result of multiple choice (Q-011-07, decided 26/10/2026).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(multiple_choice_selection::class)]
final class multiple_choice_selection_test extends \basic_testcase {
    /**
     * Cases of the decision: all correct, some correct, an incorrect one, nothing selected and nothing recorded.
     *
     * @return array
     */
    public static function cases(): array {
        return [
            'all correct, no incorrect' => [3, 0, true, 3],
            'some correct' => [1, 0, true, 1],
            'any incorrect gives zero, even with all correct' => [3, 1, true, 0],
            'only incorrect' => [0, 2, true, 0],
            'recorded and nothing selected' => [0, 0, true, 0],
            'nothing recorded is no data' => [0, 0, false, null],
            'nothing recorded wins over counts' => [2, 0, false, null],
        ];
    }

    /**
     * The items answered by case.
     *
     * @param int $correct
     * @param int $incorrect
     * @param bool $recorded
     * @param int|null $expected
     */
    #[DataProvider('cases')]
    public function test_items_answered(int $correct, int $incorrect, bool $recorded, ?int $expected): void {
        $this->assertSame($expected, multiple_choice_selection::items_answered($correct, $incorrect, $recorded));
    }

    /**
     * A negative count is refused.
     */
    public function test_negative_count_is_refused(): void {
        $this->expectException(\InvalidArgumentException::class);

        multiple_choice_selection::items_answered(-1, 0, true);
    }
}
