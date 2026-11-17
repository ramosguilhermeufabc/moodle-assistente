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

namespace local_assistente;

use core\hook\navigation\secondary_extend;
use local_assistente\application\access_service;

/**
 * Callbacks of the Moodle hooks used by the plugin (db/hooks.php).
 *
 * The entry is added to the secondary navigation of a course, only for a user with `local/assistente:view` in that course. The
 * visibility of the link is a convenience: every page and service checks the capability again in the backend (DEC-30).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /** @var string key of the node in the navigation */
    public const NODE_KEY = 'local_assistente';

    /**
     * Add the "Assistente" entry to the secondary navigation of the course.
     *
     * @param secondary_extend $hook
     */
    public static function extend_secondary_navigation(secondary_extend $hook): void {
        global $PAGE;

        $course = $PAGE->course;
        if (!$course || (int) $course->id === SITEID || $PAGE->context->get_course_context(false) === false) {
            return;
        }
        if (!(new access_service())->can_view((int) $course->id)) {
            return;
        }
        $view = $hook->get_secondaryview();
        if ($view->find(self::NODE_KEY, null) !== false) {
            return;
        }
        $view->add(
            get_string('nav:assistant', 'local_assistente'),
            new \moodle_url('/local/assistente/index.php', ['id' => $course->id]),
            \navigation_node::TYPE_CUSTOM,
            null,
            self::NODE_KEY
        );
    }
}
