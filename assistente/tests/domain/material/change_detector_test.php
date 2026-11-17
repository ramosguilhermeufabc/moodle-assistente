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

use local_assistente\domain\material_type;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the detection of change of a material (quickstart 008 Q8, Q9, Q10; DEC-33 D3).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(change_detector::class)]
#[CoversClass(material_change::class)]
#[CoversClass(material_state::class)]
final class change_detector_test extends \basic_testcase {
    /**
     * A material state.
     *
     * @param string $hash
     * @param int $section
     * @param string $name
     * @param material_type $type
     * @return material_state
     */
    private function state(string $hash = 'hash-1', int $section = 10, string $name = 'Apostila', material_type $type = material_type::PDF): material_state {
        return new material_state($type, $hash, $section, $name);
    }

    /**
     * A material that is not registered yet is new: register it and create version 1.
     */
    public function test_unknown_material_is_new(): void {
        $change = (new change_detector())->detect(null, $this->state());

        $this->assertSame(material_change::NEW_MATERIAL, $change->kind);
        $this->assertTrue($change->creates_version());
        $this->assertFalse($change->updates_material(), 'There is no registered material to update');
    }

    /**
     * The same content, section and name: nothing to do.
     */
    public function test_no_change(): void {
        $change = (new change_detector())->detect($this->state(), $this->state());

        $this->assertSame(material_change::NONE, $change->kind);
        $this->assertFalse($change->creates_version());
        $this->assertFalse($change->updates_material());
        $this->assertFalse($change->sectionchanged);
        $this->assertFalse($change->renamed);
    }

    /**
     * Quickstart 008 Q10: replacing the file, the text of the page or the URL creates a version, for each type.
     */
    public function test_a_different_content_creates_a_version(): void {
        foreach ([material_type::PDF, material_type::TEXT, material_type::YOUTUBE] as $type) {
            $change = (new change_detector())->detect($this->state('old', 10, 'M', $type), $this->state('new', 10, 'M', $type));

            $this->assertSame(material_change::NEW_VERSION, $change->kind, $type->value);
            $this->assertTrue($change->creates_version());
        }
    }

    /**
     * The hash is compared exactly: a difference of case is a different content.
     */
    public function test_hash_comparison_is_exact(): void {
        $change = (new change_detector())->detect($this->state('ABC'), $this->state('abc'));

        $this->assertSame(material_change::NEW_VERSION, $change->kind);
    }

    /**
     * Quickstart 008 Q8: renaming creates no version, and the new name is reported so that the material is updated.
     */
    public function test_rename_alone_creates_no_version(): void {
        $change = (new change_detector())->detect($this->state(), $this->state('hash-1', 10, 'Outro nome'));

        $this->assertSame(material_change::NONE, $change->kind);
        $this->assertFalse($change->creates_version());
        $this->assertTrue($change->renamed);
        $this->assertFalse($change->sectionchanged);
        $this->assertTrue($change->updates_material());
    }

    /**
     * Quickstart 008 Q8: visibility and access restrictions are not an input at all, so they cannot create a version.
     */
    public function test_visibility_and_restrictions_are_not_part_of_the_state(): void {
        $properties = array_map(static fn(\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(material_state::class))->getProperties());

        $this->assertEqualsCanonicalizing(['type', 'contenthash', 'sectionid', 'name'], $properties);
    }

    /**
     * Quickstart 008 Q9: moving to another section updates the section and creates no version.
     */
    public function test_moving_to_another_section_creates_no_version(): void {
        $change = (new change_detector())->detect($this->state('hash-1', 10), $this->state('hash-1', 20));

        $this->assertSame(material_change::NONE, $change->kind);
        $this->assertFalse($change->creates_version());
        $this->assertTrue($change->sectionchanged);
        $this->assertFalse($change->renamed);
        $this->assertTrue($change->updates_material());
    }

    /**
     * A content change together with a rename and a move creates one version and reports both updates.
     */
    public function test_content_rename_and_move_together(): void {
        $change = (new change_detector())->detect($this->state('old', 10, 'A'), $this->state('new', 20, 'B'));

        $this->assertSame(material_change::NEW_VERSION, $change->kind);
        $this->assertTrue($change->sectionchanged);
        $this->assertTrue($change->renamed);
        $this->assertTrue($change->updates_material());
    }

    /**
     * A change of the type of the material is a change of the processable content.
     */
    public function test_a_different_type_is_a_different_content(): void {
        $change = (new change_detector())->detect(
            $this->state('same', 10, 'M', material_type::PDF),
            $this->state('same', 10, 'M', material_type::TEXT)
        );

        $this->assertSame(material_change::NEW_VERSION, $change->kind);
    }

    /**
     * Invalid states are rejected: an empty hash (no content to compare) and a section that is not an id.
     */
    public function test_invalid_states_are_rejected(): void {
        foreach ([['', 10], ['   ', 10], ['hash', 0], ['hash', -3]] as [$hash, $section]) {
            try {
                new material_state(material_type::PDF, $hash, $section, 'M');
                $this->fail('The state must be rejected');
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * Only the three kinds exist.
     */
    public function test_unknown_kind_is_rejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        new material_change('new_state');
    }
}
