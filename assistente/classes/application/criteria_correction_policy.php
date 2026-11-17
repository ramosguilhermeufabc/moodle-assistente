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

namespace local_assistente\application;

/**
 * Who may correct criteria that were already saved (DEC-45 D2).
 *
 * DEC-45 D2 says an "administrador (`manager` ou administrador do site, no contexto apropriado)" may correct them.
 * CL-42 closed which capability identifies the manager: `local/assistente:correctcriteria`, granted by default only
 * to `manager` (db/access.php; see the default implementation, capability_correction_policy).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface criteria_correction_policy {
    /**
     * Whether the user may correct the saved criteria of the course.
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @return bool
     */
    public function can_correct(int $courseid, ?int $userid = null): bool;
}
