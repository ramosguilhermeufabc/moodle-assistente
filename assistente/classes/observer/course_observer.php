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

use local_assistente\application\privacy\course_data_eraser;

/**
 * Observer of the deletion of a course (T103; spec-017, DEC-42 D2).
 *
 * Mechanism, as read in the sources of Moodle 5.2.4 (delete_course in lib/moodlelib.php): the plugin callback `pre_course_delete` and the hook
 * `\core_course\hook\before_course_deleted` run BEFORE Moodle empties the course, when it can still fail; the event
 * `\core\event\course_deleted` is triggered at the very end, after the course row, its contents and its context are gone. Only the event is
 * observed, so that the data of the plugin never go before the course does. `objectid` of the event is the id of the course. The behaviour in
 * a running site is validated in CL-37 (T106).
 *
 * Thin, as the other observers: it hands the course to the eraser. A failure is logged and not rethrown, because the deletion of the course
 * has already happened and cannot be undone; the erasure is idempotent, so the same course can be erased again. The observer is registered
 * after the commit (`internal => false`).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_observer {
    /** @var course_data_eraser|null replacement used by tests */
    private static ?course_data_eraser $eraser = null;

    /**
     * Replace the eraser, for tests. Pass null to restore the default.
     *
     * @param course_data_eraser|null $eraser
     */
    public static function use_eraser(?course_data_eraser $eraser): void {
        self::$eraser = $eraser;
    }

    /**
     * A course was deleted.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        try {
            (self::$eraser ?? new course_data_eraser())->erase((int) $event->objectid);
        } catch (\Throwable $e) {
            $message = 'local_assistente: could not erase the data of the deleted course ' . (int) $event->objectid . ': ' . $e->getMessage();
            debugging($message, DEBUG_NORMAL);
            error_log($message);
        }
    }
}
