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

/**
 * Decides whether a change in a material creates a new version (DEC-33 D3; quickstart 008 Q8, Q9, Q10).
 *
 * - A new version only when the processable content changes: the file, the text of the page or the URL. That is, when the
 *   content hash differs, or the type of the material differs.
 * - Changing only the name, the visibility or the access restrictions creates no version: visibility and restrictions are not
 *   even an input here, because the availability is evaluated when the snapshot is built (DEC-26).
 * - Moving the material to another section updates its section and creates no version: the context uses the section in force
 *   at the moment of the snapshot.
 *
 * The comparison needs the content hash recorded for the current version. Where it is recorded is an open decision (CL-45); the
 * caller passes it in. This class stores and reads nothing.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class change_detector {
    /**
     * Compare what Moodle holds now with what the plugin recorded.
     *
     * @param material_state|null $known the recorded state of the current version; null when the material is not registered
     * @param material_state $current what Moodle holds now
     * @return material_change
     */
    public function detect(?material_state $known, material_state $current): material_change {
        if ($known === null) {
            return new material_change(material_change::NEW_MATERIAL);
        }

        $contentchanged = $known->contenthash !== $current->contenthash || $known->type !== $current->type;
        return new material_change(
            $contentchanged ? material_change::NEW_VERSION : material_change::NONE,
            $known->sectionid !== $current->sectionid,
            $known->name !== $current->name
        );
    }
}
