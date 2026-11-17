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

namespace local_assistente\domain\privacy;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The key of a case of erasure (T142; DEC-58): the same case always has the same key and the same custom data.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(erasure_key::class)]
final class erasure_key_test extends \basic_testcase {
    /**
     * The courses are ordered, without repetition and as integers, so the custom data and its JSON are always the same text.
     */
    public function test_the_key_is_canonical(): void {
        $a = new erasure_key(5, [8, 7, 8, '7']);
        $b = new erasure_key(5, [7, 8]);

        $this->assertSame([7, 8], $a->courses());
        $this->assertSame(json_encode($b->to_custom_data()), json_encode($a->to_custom_data()));
        $this->assertSame('{"userid":5,"courseids":[7,8]}', json_encode($a->to_custom_data()));
    }

    /**
     * Rebuilding from the custom data that Moodle stores gives the same key; anything else is refused.
     */
    public function test_a_key_is_rebuilt_from_custom_data(): void {
        $key = erasure_key::from_custom_data(json_decode('{"courseids":[9,3],"userid":4}'));

        $this->assertSame(4, $key->userid());
        $this->assertSame([3, 9], $key->courses());
        foreach ([null, [], ['userid' => 1], ['userid' => 1, 'courseids' => 'x'], ['userid' => 0, 'courseids' => [1]], ['userid' => 1, 'courseids' => []]] as $bad) {
            try {
                erasure_key::from_custom_data($bad);
                $this->fail('Accepted ' . json_encode($bad));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Exact equality of the courses is not the same as sharing a course.
     */
    public function test_exact_and_overlapping_courses_are_different_questions(): void {
        $key = new erasure_key(5, [8]);

        $this->assertTrue($key->has_courses([8]));
        $this->assertFalse($key->has_courses([7, 8]));
        $this->assertFalse($key->has_courses([7]));
        $this->assertTrue($key->intersects([7, 8]));
        $this->assertFalse($key->intersects([7]));
        $this->assertTrue((new erasure_key(5, [7, 8]))->has_courses([8, 7]));
    }
}
