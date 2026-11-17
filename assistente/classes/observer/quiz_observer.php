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
use local_assistente\application\quiz\quiz_attempt_event_service;

/**
 * Observer of the attempt events of mod_quiz that change the results of a questionnaire (T073; DEC-36 D4; plan 022).
 *
 * Thin by contract: it reads the identifiers from the Moodle event (course, course module, attempt, user, slot, moment), never from
 * the request, and delegates to quiz_attempt_event_service. No result is calculated and no LLM is called here (that is results_handler).
 *
 * Events observed, as seen in Moodle 5.2.4 (CL-33/T075 validates them further):
 * - `\mod_quiz\event\attempt_submitted`: when the attempt is submitted, never for a preview, BEFORE the automatic grading
 *   (`attempt_graded` comes after it, and is not observed: the queue runs later and reads only attempts already finished);
 * - `\mod_quiz\event\question_manually_graded`: each time the teacher saves the grade of a question, in the comment page and in the
 *   grading report; one event per question saved, so several for one attempt;
 * - `\mod_quiz\event\attempt_regraded`: when the overview report regrades an attempt (not in a dry run), once per attempt regraded.
 * The closing of the questionnaire is not an event of Moodle: it is detected by a scheduled task (T074).
 *
 * Failure isolation, as in the course module observer: a failure of the queue is logged and not rethrown, so it does not break the
 * action of the teacher or student. The observers are registered after the commit (`internal => false`, T046).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_observer {
    /** @var quiz_attempt_event_service|null replacement used by tests */
    private static ?quiz_attempt_event_service $service = null;

    /**
     * Replace the service, for tests. Pass null to restore the default.
     *
     * @param quiz_attempt_event_service|null $service
     */
    public static function use_service(?quiz_attempt_event_service $service): void {
        self::$service = $service;
    }

    /**
     * An attempt was submitted.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::run($event, static fn(quiz_attempt_event_service $s) => $s->attempt_submitted(
            (int) $event->courseid,
            (int) $event->contextinstanceid,
            (int) $event->objectid,
            (int) $event->relateduserid
        ));
    }

    /**
     * A question of an attempt was graded manually.
     *
     * @param \mod_quiz\event\question_manually_graded $event
     */
    public static function question_manually_graded(\mod_quiz\event\question_manually_graded $event): void {
        self::run($event, static fn(quiz_attempt_event_service $s) => $s->question_manually_graded(
            (int) $event->courseid,
            (int) $event->contextinstanceid,
            (int) ($event->other['attemptid'] ?? 0),
            (int) ($event->other['slot'] ?? 0),
            (int) $event->timecreated
        ));
    }

    /**
     * An attempt was regraded.
     *
     * @param \mod_quiz\event\attempt_regraded $event
     */
    public static function attempt_regraded(\mod_quiz\event\attempt_regraded $event): void {
        self::run($event, static fn(quiz_attempt_event_service $s) => $s->attempt_regraded(
            (int) $event->courseid,
            (int) $event->contextinstanceid,
            (int) $event->objectid,
            (int) $event->timecreated
        ));
    }

    /**
     * Run the delegation, containing any failure.
     *
     * @param \core\event\base $event
     * @param \Closure $delegate receives the service
     */
    private static function run(\core\event\base $event, \Closure $delegate): void {
        try {
            $delegate(self::$service ?? new quiz_attempt_event_service(observer_event_service::get()));
        } catch (\Throwable $e) {
            $message = 'local_assistente: could not process the quiz event ' . $event->eventname . ' of object '
                . (int) $event->objectid . ': ' . $e->getMessage();
            debugging($message, DEBUG_NORMAL);
            error_log($message);
        }
    }
}
