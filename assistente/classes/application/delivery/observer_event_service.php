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

namespace local_assistente\application\delivery;

use local_assistente\application\event_service;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * The queue entry point the observers use: it does not wait for a busy lock as long as the queue does, and hands what it could not
 * deliver to a retry (exception to DEC-32 D2; see event_redelivery_scheduler).
 *
 * An observer runs inside the action of a teacher or a student, so it waits LOCK_TIMEOUT seconds for a lock, not the 30 of the queue. The
 * value is an initial one, not measured on a real site: the erasure of the data of a student holds the lock of that student for roughly
 * 0.6 ms for each event of the student in the test environment. The queue, its handlers and the synchronization keep the default wait.
 *
 * One instance for the process, so that the memory of the locks that failed (lock_failure_budget) is shared by every observer. It is held
 * by the dependency container of Moodle, which lives as long as the process and which Moodle empties between the tests of PHPUnit: the
 * memory of a failed lock never reaches another test, where the same user identifier would meet the same lock.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer_event_service {
    /** Seconds an observer waits for each lock. */
    public const LOCK_TIMEOUT = 3;

    /** @var event_service|null */
    private ?event_service $events = null;

    /**
     * The event service of this instance, built when first asked.
     *
     * @return event_service
     */
    public function events(): event_service {
        return $this->events ??= new event_service(null, null, new unit_of_work(self::LOCK_TIMEOUT), new event_redelivery_scheduler());
    }

    /**
     * The event service of the observers, the same for the whole process.
     *
     * @return event_service
     */
    public static function get(): event_service {
        return \core\di::get(self::class)->events();
    }

    /**
     * Forget the memory of the locks that failed (for tests).
     */
    public static function reset(): void {
        \core\di::set(self::class, new self());
    }
}
