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

namespace local_assistente\domain;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Value domains approved for the char and int(1) fields that XMLDB does not restrict.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(event_status::class)]
#[CoversClass(attempt_status::class)]
#[CoversClass(analysis_type::class)]
#[CoversClass(material_type::class)]
#[CoversClass(criteria_status::class)]
#[CoversClass(result_state::class)]
final class vocabulary_test extends \basic_testcase {
    /**
     * Only the four states of spec-006 exist for events (quickstart 006 Q5).
     */
    public function test_event_status_has_only_the_four_states(): void {
        $this->assertSame(['pending', 'processing', 'processed', 'failure'], event_status::values());
    }

    /**
     * Attempt states (DEC-32 D4).
     */
    public function test_attempt_status_values(): void {
        $this->assertSame(['processing', 'processed', 'failure'], attempt_status::values());
    }

    /**
     * The seven analysis types, all fitting char(20).
     */
    public function test_analysis_types_fit_the_field(): void {
        $this->assertSame([
            'quiz_initial', 'quiz_results', 'grade_performance', 'forum_analysis',
            'frequency', 'criteria_check', 'section_synthesis',
        ], analysis_type::values());
        foreach (analysis_type::values() as $value) {
            $this->assertLessThanOrEqual(20, strlen($value));
        }
    }

    /**
     * Only PDF, text and YouTube are functional materials (DEC-33 D2); Book and Folder are not.
     */
    public function test_material_types_are_only_pdf_text_youtube(): void {
        $this->assertSame(['pdf', 'text', 'youtube'], material_type::values());
        $this->assertNull(material_type::tryFrom('book'));
        $this->assertNull(material_type::tryFrom('folder'));
    }

    /**
     * Criteria status: 1 meets, 0 below, 2 no data (DEC-40).
     */
    public function test_criteria_status_values(): void {
        $this->assertSame(1, criteria_status::MEETS->value);
        $this->assertSame(0, criteria_status::BELOW->value);
        $this->assertSame(2, criteria_status::NO_DATA->value);
        $this->assertNull(criteria_status::tryFrom(3));
    }

    /**
     * Only an enabled assistant processes data; no configuration and disabled are both "not enabled" (DEC-45 D3).
     */
    public function test_only_enabled_processes_data(): void {
        $this->assertTrue(\local_assistente\domain\criteria\assistant_state::ENABLED->is_enabled());
        $this->assertFalse(\local_assistente\domain\criteria\assistant_state::DISABLED->is_enabled());
        $this->assertFalse(\local_assistente\domain\criteria\assistant_state::NOT_CONFIGURED->is_enabled());
    }

    /**
     * Result states valid, no_data and error (DEC-24, P-06).
     */
    public function test_result_states(): void {
        $this->assertSame(['valid', 'no_data', 'error'], result_state::values());
    }
}
