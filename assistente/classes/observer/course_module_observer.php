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
use local_assistente\application\material\material_event_service;
use local_assistente\application\quiz\quiz_publication_service;

/**
 * Observer of the creation and the update of course modules (DEC-31; plan 006 phase 1; spec-022).
 *
 * Thin by contract: it recognises the questionnaires and delegates to quiz_publication_service, and recognises the module types
 * that can be a material (T065) and delegates to material_event_service. There is no business rule and no LLM here. The
 * quiz event is only created (or not) by the service, once per questionnaire; a material event only enqueues `material_changed`,
 * and the handler decides whether there is a new version.
 *
 * Failure isolation (hypothesis pending CL-02 and T131): a failure of the queue must not break the teacher's action, so it
 * is logged and not rethrown. A publication that was lost this way is created the next time the questionnaire is handled
 * with the assistant enabled, or by the initial synchronization (T121).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_observer {
    /** @var quiz_publication_service|null replacement used by tests */
    private static ?quiz_publication_service $service = null;

    /** @var material_event_service|null replacement used by tests */
    private static ?material_event_service $materials = null;

    /**
     * Replace the service, for tests. Pass null to restore the default.
     *
     * @param quiz_publication_service|null $service
     */
    public static function use_service(?quiz_publication_service $service): void {
        self::$service = $service;
    }

    /**
     * Replace the material service, for tests. Pass null to restore the default.
     *
     * @param material_event_service|null $service
     */
    public static function use_material_service(?material_event_service $service): void {
        self::$materials = $service;
    }

    /**
     * A course module was created.
     *
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        self::handle($event);
    }

    /**
     * A course module was updated.
     *
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        self::handle($event);
    }

    /**
     * Hand a questionnaire to the publication service, or a possible material to the material service (T065).
     *
     * @param \core\event\base $event a course_module_created or course_module_updated event
     */
    private static function handle(\core\event\base $event): void {
        $modulename = (string) ($event->other['modulename'] ?? '');
        $isquiz = $modulename === 'quiz';
        if (!$isquiz && !material_event_service::is_candidate($modulename)) {
            return;
        }
        try {
            if ($isquiz) {
                (self::$service ?? new quiz_publication_service(observer_event_service::get()))->handle_module_event((int) $event->courseid, (int) $event->objectid);
            } else {
                (self::$materials ?? new material_event_service(observer_event_service::get()))->handle_module_event(
                    (int) $event->courseid,
                    (int) $event->objectid,
                    $modulename,
                    $event->eventname,
                    (int) $event->timecreated
                );
            }
        } catch (\Throwable $e) {
            $message = 'local_assistente: could not process the course module event of cmid ' . (int) $event->objectid
                . ': ' . $e->getMessage();
            debugging($message, DEBUG_NORMAL);
            error_log($message);
        }
    }
}
