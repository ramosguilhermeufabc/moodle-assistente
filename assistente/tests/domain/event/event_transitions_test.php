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

namespace local_assistente\domain\event;

use local_assistente\domain\event_status;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the state transitions of an event (DEC-32 D4, DEC-09).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(event_transitions::class)]
final class event_transitions_test extends \basic_testcase {
    /**
     * Exactly the transitions of the contract are allowed, out of the 16 combinations of the four states.
     */
    public function test_the_transition_matrix(): void {
        $allowed = [
            'pending>processing', 'processing>processed', 'processing>pending', 'processing>failure',
        ];
        $found = [];
        foreach (event_status::cases() as $from) {
            foreach (event_status::cases() as $to) {
                if (event_transitions::is_allowed($from, $to)) {
                    $found[] = "{$from->value}>{$to->value}";
                }
            }
        }
        $this->assertEqualsCanonicalizing($allowed, $found);
    }

    /**
     * Final states have no way out.
     */
    public function test_final_states_are_final(): void {
        foreach ([event_status::PROCESSED, event_status::FAILURE] as $final) {
            foreach (event_status::cases() as $to) {
                $this->assertFalse(event_transitions::is_allowed($final, $to), "{$final->value} must be final");
            }
        }
    }

    /**
     * Three attempts in total: after the first and second failure the event goes back to pending; after the third it fails.
     */
    public function test_state_after_a_failed_attempt(): void {
        $this->assertSame(3, event_transitions::MAX_ATTEMPTS);
        $this->assertSame(event_status::PENDING, event_transitions::after_failure(1));
        $this->assertSame(event_status::PENDING, event_transitions::after_failure(2));
        $this->assertSame(event_status::FAILURE, event_transitions::after_failure(3));
        $this->assertSame(event_status::FAILURE, event_transitions::after_failure(4), 'Never more than the limit');
    }

    /**
     * Requiring a forbidden transition fails.
     */
    public function test_assert_allowed(): void {
        event_transitions::assert_allowed(event_status::PENDING, event_status::PROCESSING);
        $this->expectException(\DomainException::class);
        event_transitions::assert_allowed(event_status::PROCESSED, event_status::PENDING);
    }
}
