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

namespace local_assistente\domain\section;

/**
 * Outcome of a section resolution or validation (spec-004, "Resolução de seção").
 *
 * The three unresolved outcomes are distinct and explicit; there is no default value.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum section_resolution_status: string {
    /** The section was resolved (or validated) for the course. */
    case RESOLVED = 'resolved';
    /** The cmid does not exist or is not valid (for example, the module is being deleted). */
    case CMID_NOT_FOUND = 'cmid_not_found';
    /** The cmid exists but belongs to another course (distinct from CMID_NOT_FOUND). */
    case CMID_OTHER_COURSE = 'cmid_other_course';
    /** The section does not exist, or belongs to another course. */
    case SECTION_INVALID = 'section_invalid';
}
