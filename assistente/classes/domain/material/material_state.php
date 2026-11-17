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

/**
 * What identifies a material for the detection of change: its type, the identifier of its processable content, its section and its
 * name.
 *
 * It is used for both sides of the comparison: what Moodle holds now, and what the plugin recorded for the current version.
 * Visibility and access restrictions are deliberately NOT part of it: they do not create versions, the availability being
 * evaluated when the context snapshot is built (DEC-26, DEC-33 D3).
 *
 * The content hash identifies the processable content only: the file for a PDF, the text of the page, the address of the URL
 * (see material_reader). Where the plugin stores it for each version is an open decision (CL-45).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class material_state {
    /**
     * Constructor.
     *
     * @param material_type $type
     * @param string $contenthash identifier of the processable content; must not be empty
     * @param int $sectionid course_sections.id
     * @param string $name
     * @throws \InvalidArgumentException when the hash is empty or the section is not a positive id
     */
    public function __construct(
        /** @var material_type type of the material */
        public readonly material_type $type,
        /** @var string identifier of the processable content */
        public readonly string $contenthash,
        /** @var int section of the material */
        public readonly int $sectionid,
        /** @var string name of the material */
        public readonly string $name
    ) {
        if (trim($contenthash) === '') {
            throw new \InvalidArgumentException('The content hash of a material must not be empty.');
        }
        if ($sectionid <= 0) {
            throw new \InvalidArgumentException('The section of a material must be a positive id.');
        }
    }
}
