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
 * Availability recorded for a material or a material version (DEC-51; material.availability and
 * material_version.availability).
 *
 * PENDING is "not evaluated yet" (adopted reading). It is not the same as the pending extraction of the content of a PDF or a
 * YouTube video (extracted_content::extraction_pending), and it is independent of summary_status (DEC-50). The availability used
 * in a context is the one evaluated in the snapshot (DEC-26).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum material_availability: string {
    /** Available. */
    case AVAILABLE = 'available';
    /** Unavailable. */
    case UNAVAILABLE = 'unavailable';
    /** Not evaluated yet. */
    case PENDING = 'pending';

    /**
     * All allowed raw values.
     *
     * @return string[]
     */
    public static function values(): array {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }
}
