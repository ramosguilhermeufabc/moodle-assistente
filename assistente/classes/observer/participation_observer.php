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

namespace local_assistente\observer;

use local_assistente\application\delivery\observer_event_service;
use local_assistente\application\frequency\participation_event_service;

/**
 * Observer of the events of Moodle that change the participation of a student in a module, for the frequency (T089; spec-014, DEC-39 D2).
 *
 * Thin by contract: it reads the module, the student and the moment from the Moodle event, never from the request, and delegates to
 * participation_event_service. No frequency is calculated here (that is frequency_handler).
 *
 * Events observed, as read in Moodle 5.2.4 (CL-36/T090 validates them):
 * - `\mod_assign\event\assessable_submitted`: the student sent the assignment for grading (not a draft). The student is `relateduserid`
 *   when someone else acts for the student and `userid` otherwise;
 * - `\core\event\course_module_completion_updated`: the completion state of a user in a module changed; the user is `relateduserid`.
 * Grades and posts reach the frequency through grade_observer (T079) and forum_observer (T083), which also queue `participation_changed`.
 *
 * Failure isolation, as in the other observers: a failure is logged and not rethrown. Registered after the commit (`internal => false`).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participation_observer {
    /** @var participation_event_service|null replacement used by tests */
    private static ?participation_event_service $service = null;

    /**
     * Replace the service, for tests. Pass null to restore the default.
     *
     * @param participation_event_service|null $service
     */
    public static function use_service(?participation_event_service $service): void {
        self::$service = $service;
    }

    /**
     * An assignment was sent for grading.
     *
     * @param \mod_assign\event\assessable_submitted $event
     */
    public static function assessable_submitted(\mod_assign\event\assessable_submitted $event): void {
        self::run($event, 'submission', (int) ($event->relateduserid ?: $event->userid));
    }

    /**
     * The completion state of a user in a module changed.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function completion_updated(\core\event\course_module_completion_updated $event): void {
        self::run($event, 'completion', (int) $event->relateduserid);
    }

    /**
     * Delegate, containing any failure.
     *
     * @param \core\event\base $event
     * @param string $source
     * @param int $userid the student whose signal changed
     */
    private static function run(\core\event\base $event, string $source, int $userid): void {
        try {
            (self::$service ?? new participation_event_service(observer_event_service::get()))->participation_changed(
                (int) $event->courseid,
                (int) $event->contextinstanceid,
                $userid,
                $source,
                (int) $event->timecreated
            );
        } catch (\Throwable $e) {
            $message = 'local_assistente: could not process the participation event ' . $event->eventname . ' of object '
                . (int) $event->objectid . ': ' . $e->getMessage();
            debugging($message, DEBUG_NORMAL);
            error_log($message);
        }
    }
}
