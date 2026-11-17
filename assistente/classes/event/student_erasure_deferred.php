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

namespace local_assistente\event;

/**
 * A retry of the erasure of a student was scheduled (T142; DEC-58): the erasure failed for lack of a lock or of a stable set of events, and nothing was changed. It opens the case; its task is the generation.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_erasure_deferred extends student_erasure_event {
    #[\Override]
    public static function get_name() {
        return get_string('event:student_erasure_deferred', 'local_assistente');
    }
}
