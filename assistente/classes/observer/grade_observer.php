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
use local_assistente\application\grade\grade_event_service;

/**
 * Observer of the grade events of the Moodle gradebook (T079; DEC-37 D1; plan 022).
 *
 * Thin by contract: it reads the identifiers from the Moodle event (course, graded user, grade item, moment), never from the request,
 * and delegates to grade_event_service. No grade is read and nothing is calculated here (that is performance_handler).
 *
 * Events observed, as read in Moodle 5.2.4 (CL-34/T080 validates them further):
 * - `\core\event\user_graded`: a grade was given (final grade not null), changed, or recalculated by an aggregation. It is emitted for
 *   every grade item, also categories and the course total, so a change of weight or aggregation that moves the total reaches the queue
 *   through the event of the total (DEC-37 D1);
 * - `\core\event\grade_deleted`: the grade of a user in an item was deleted.
 * Several events for one change of a grade are expected (the item and the totals above it); the handler recalculates the whole student.
 * A grade of the main item of an activity is also a signal of participation, so it queues `participation_changed` too (T089); the totals,
 * categories and manual items do not.
 *
 * Failure isolation, as in the other observers: a failure of the queue is logged and not rethrown. The observers are registered after
 * the commit (`internal => false`, T046).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_observer {
    /** @var grade_event_service|null replacement used by tests */
    private static ?grade_event_service $service = null;

    /** @var participation_event_service|null replacement used by tests */
    private static ?participation_event_service $participation = null;

    /**
     * Replace the service, for tests. Pass null to restore the default.
     *
     * @param grade_event_service|null $service
     */
    public static function use_service(?grade_event_service $service): void {
        self::$service = $service;
    }

    /**
     * Replace the participation service, for tests. Pass null to restore the default.
     *
     * @param participation_event_service|null $service
     */
    public static function use_participation_service(?participation_event_service $service): void {
        self::$participation = $service;
    }

    /**
     * A grade was given, changed or recalculated.
     *
     * @param \core\event\user_graded $event
     */
    public static function user_graded(\core\event\user_graded $event): void {
        self::run($event);
    }

    /**
     * A grade was deleted.
     *
     * @param \core\event\grade_deleted $event
     */
    public static function grade_deleted(\core\event\grade_deleted $event): void {
        self::run($event);
    }

    /**
     * Delegate, containing any failure.
     *
     * @param \core\event\base $event
     */
    private static function run(\core\event\base $event): void {
        $args = [(int) $event->courseid, (int) $event->relateduserid, (int) ($event->other['itemid'] ?? 0), (int) $event->timecreated];
        // Two independent deliveries: the performance of the student and, for the main grade item of an activity, its participation
        // (a grade is a signal of participation in the frequency, T089). A failure of one does not stop the other.
        foreach ([
            static fn() => (self::$service ?? new grade_event_service(observer_event_service::get()))->grade_changed(...$args),
            static fn() => (self::$participation ?? new participation_event_service(observer_event_service::get()))->grade_item_changed(...$args),
        ] as $delegate) {
            try {
                $delegate();
            } catch (\Throwable $e) {
                $message = 'local_assistente: could not process the grade event ' . $event->eventname . ' of object '
                    . (int) $event->objectid . ': ' . $e->getMessage();
                debugging($message, DEBUG_NORMAL);
                error_log($message);
            }
        }
    }
}
