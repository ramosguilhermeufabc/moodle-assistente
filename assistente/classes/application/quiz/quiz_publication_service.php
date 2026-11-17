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

namespace local_assistente\application\quiz;

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\questionnaire_reader;

/**
 * Decides when a questionnaire counts as published and hands that single occurrence to the queue (DEC-31; spec-006).
 *
 * A questionnaire is published when it becomes visible to students for the first time, whether it was created already
 * visible or made visible later. Each questionnaire produces one publication event in its whole existence: hiding and
 * showing it again, or editing it after the publication, produce no other. The opening time (`timeopen`) is not part of the
 * definition, and availability for each student follows DEC-26, evaluated when the context of an analysis is built.
 *
 * The "first time" is guaranteed by the identity of the event (QUIZ_PUBLISHED is once per object, keyed by the cmid), which
 * is deduplicated under a lock; this service keeps no state. So the rule does not depend on knowing what the previous visibility
 * was, which a `course_module_updated` event does not say.
 *
 * Hypotheses pending CL-02 (T047): "visible to students" is the visibility setting of the module (it does not consider a
 * hidden section or the access restrictions, which DEC-26 handles per student); and the events that reach this service are
 * the creation and the update of a course module.
 *
 * Behaviour while the assistant is not enabled: no event is created (event_service). If the questionnaire was visible
 * before and is touched afterwards with the assistant enabled, the publication event is created then; the initial
 * synchronization (T121) covers the questionnaires that were already published when the assistant was enabled.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_publication_service {
    /** @var event_service */
    private event_service $events;

    /** @var questionnaire_reader */
    private questionnaire_reader $questionnaires;

    /** @var course_module_reader */
    private course_module_reader $modules;

    /**
     * Constructor.
     *
     * @param event_service|null $events
     * @param questionnaire_reader|null $questionnaires
     * @param course_module_reader|null $modules
     */
    public function __construct(
        ?event_service $events = null,
        ?questionnaire_reader $questionnaires = null,
        ?course_module_reader $modules = null
    ) {
        $this->events = $events ?? new event_service();
        $this->questionnaires = $questionnaires ?? new questionnaire_reader();
        $this->modules = $modules ?? new course_module_reader();
    }

    /**
     * React to a creation or an update of a course module.
     *
     * @param int $courseid
     * @param int $cmid
     * @return enqueue_result|null null when nothing is to be published: the module is not a questionnaire of that course, it
     *                             is being deleted, or it is not visible to students
     */
    public function handle_module_event(int $courseid, int $cmid): ?enqueue_result {
        $questionnaire = $this->questionnaires->get_questionnaire($cmid);
        if ($questionnaire === null || $questionnaire->course_id !== $courseid) {
            return null;
        }

        $module = $this->modules->get_module($courseid, $cmid);
        if ($module === null || !$module->visible) {
            return null;
        }

        return $this->events->enqueue($courseid, event_type::QUIZ_PUBLISHED, ['cmid' => $cmid]);
    }
}
