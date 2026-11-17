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

namespace local_assistente\application\material;

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\domain\material\material_classifier;

/**
 * Turns a course module event of Moodle into a `material_changed` event of the queue (T065; DEC-33).
 *
 * It only recognises the module types that can be a material and enqueues; it does not read the content, compare it or create
 * versions. That is the job of the handler of `material_changed` (material_service, T063), which compares the processable
 * content with the current version and creates a version only when it changed: renaming, moving, hiding or changing the access
 * restrictions of a module therefore reach the handler and create no version (Q8, Q9).
 *
 * Whether the module really is a material (a PDF file, a page, a YouTube URL) is decided by the handler, which reads Moodle at the
 * time of the processing. Each Moodle event is a distinct occurrence (OCCURRENCE strategy: cmid, event name and moment).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class material_event_service {
    /** @var event_service */
    private event_service $events;

    /**
     * Constructor.
     *
     * @param event_service|null $events
     */
    public function __construct(?event_service $events = null) {
        $this->events = $events ?? new event_service();
    }

    /**
     * Whether a module type can be a material.
     *
     * @param string $modulename
     * @return bool
     */
    public static function is_candidate(string $modulename): bool {
        return in_array($modulename, material_classifier::CANDIDATE_MODULES, true);
    }

    /**
     * React to a creation or an update of a course module.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string $modulename
     * @param string $moodleevent name of the Moodle event
     * @param int $occurredat moment of the Moodle event
     * @return enqueue_result|null null when the module type cannot be a material
     */
    public function handle_module_event(
        int $courseid,
        int $cmid,
        string $modulename,
        string $moodleevent,
        int $occurredat
    ): ?enqueue_result {
        if (!self::is_candidate($modulename)) {
            return null;
        }
        return $this->events->enqueue($courseid, event_type::MATERIAL_CHANGED, [
            'cmid' => $cmid,
            'moodle_event' => $moodleevent,
            'occurred_at' => $occurredat,
        ]);
    }
}
