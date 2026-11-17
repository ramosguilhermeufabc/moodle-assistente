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

namespace local_assistente\application\sync;

/**
 * Extension point of the initial synchronization (DEC-45 D3; implemented in task T121).
 *
 * When the assistant is enabled in a course, or enabled again, the service calls this after the enabled flag is saved. The
 * implementation generates the events for the current state of the course (eligible materials, published questionnaires
 * without an initial analysis, grades and attendance, full forum rebuild) without simulating historical events.
 *
 * The technical way to trigger it (event on save or task) is decided by the validation CL-40.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface initial_sync {
    /**
     * Synchronize the current state of an enabled course.
     *
     * @param int $courseid
     */
    public function run(int $courseid): void;
}
