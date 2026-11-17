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

namespace local_assistente\domain\material;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the value domains of the summary status (DEC-50) and of the availability (DEC-51).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(summary_status::class)]
#[CoversClass(material_availability::class)]
final class material_domains_test extends \basic_testcase {
    /**
     * The approved values, and only them.
     */
    public function test_approved_values(): void {
        $this->assertSame(['pending', 'completed', 'failed'], summary_status::values());
        $this->assertSame(['available', 'unavailable', 'pending'], material_availability::values());
    }

    /**
     * Values outside the domains do not exist.
     */
    public function test_invalid_values_do_not_exist(): void {
        foreach (['', 'done', 'success', 'Completed', 'processing', 'extraction_pending'] as $value) {
            $this->assertNull(summary_status::tryFrom($value), $value);
        }
        foreach (['', 'yes', '1', 'Available', 'extraction_pending', 'completed'] as $value) {
            $this->assertNull(material_availability::tryFrom($value), $value);
        }
    }

    /**
     * Transitions of the summary status: pending to completed or failed; failed back to pending; completed is final.
     *
     * @return array
     */
    public static function transitions(): array {
        return [
            'pending to completed' => [summary_status::PENDING, summary_status::COMPLETED, true],
            'pending to failed' => [summary_status::PENDING, summary_status::FAILED, true],
            'pending to pending' => [summary_status::PENDING, summary_status::PENDING, false],
            'failed to pending (new attempt)' => [summary_status::FAILED, summary_status::PENDING, true],
            'failed to completed' => [summary_status::FAILED, summary_status::COMPLETED, false],
            'failed to failed' => [summary_status::FAILED, summary_status::FAILED, false],
            'completed to pending' => [summary_status::COMPLETED, summary_status::PENDING, false],
            'completed to failed' => [summary_status::COMPLETED, summary_status::FAILED, false],
            'completed to completed' => [summary_status::COMPLETED, summary_status::COMPLETED, false],
        ];
    }

    /**
     * @param summary_status $from
     * @param summary_status $to
     * @param bool $allowed
     */
    #[DataProvider('transitions')]
    public function test_transitions(summary_status $from, summary_status $to, bool $allowed): void {
        $this->assertSame($allowed, $from->can_become($to));
    }
}
