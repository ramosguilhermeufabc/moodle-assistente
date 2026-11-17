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

/**
 * Turns the attempt events of Moodle into events of the queue that recalculate the results of a questionnaire (T073; spec-011, DEC-36 D4).
 *
 * It only builds the payload of the queue event and hands it to the event service, which deduplicates by identity and does nothing for
 * a course without the assistant. It reads no attempt and calculates nothing: that is the job of results_handler (T072), which reads the
 * attempts when it runs. An event with a missing identifier (zero) is refused with an InvalidArgumentException, so that nothing is
 * queued from incomplete data.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_attempt_event_service {
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
     * An attempt was submitted.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $attemptid
     * @param int $userid the student of the attempt
     * @return enqueue_result
     */
    public function attempt_submitted(int $courseid, int $cmid, int $attemptid, int $userid): enqueue_result {
        self::require_positive(['course' => $courseid, 'cmid' => $cmid, 'attempt' => $attemptid, 'user' => $userid]);
        return $this->events->enqueue($courseid, event_type::QUIZ_ATTEMPT_SUBMITTED, [
            'cmid' => $cmid, 'attemptid' => $attemptid, 'userid' => $userid,
        ]);
    }

    /**
     * A question of an attempt was graded manually.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $attemptid
     * @param int $slot
     * @param int $occurredat
     * @return enqueue_result
     */
    public function question_manually_graded(int $courseid, int $cmid, int $attemptid, int $slot, int $occurredat): enqueue_result {
        self::require_positive(['course' => $courseid, 'cmid' => $cmid, 'attempt' => $attemptid, 'slot' => $slot]);
        return $this->events->enqueue($courseid, event_type::QUIZ_ATTEMPT_MANUALLY_GRADED, [
            'cmid' => $cmid, 'attemptid' => $attemptid, 'slot' => $slot, 'occurred_at' => $occurredat,
        ]);
    }

    /**
     * An attempt was regraded.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $attemptid
     * @param int $occurredat
     * @return enqueue_result
     */
    public function attempt_regraded(int $courseid, int $cmid, int $attemptid, int $occurredat): enqueue_result {
        self::require_positive(['course' => $courseid, 'cmid' => $cmid, 'attempt' => $attemptid]);
        return $this->events->enqueue($courseid, event_type::QUIZ_ATTEMPT_REGRADED, [
            'cmid' => $cmid, 'attemptid' => $attemptid, 'occurred_at' => $occurredat,
        ]);
    }

    /**
     * Refuse an identifier that is missing.
     *
     * @param array<string, int> $ids
     * @throws \InvalidArgumentException
     */
    private static function require_positive(array $ids): void {
        foreach ($ids as $name => $id) {
            if ($id <= 0) {
                throw new \InvalidArgumentException("The {$name} of the quiz event is missing.");
            }
        }
    }
}
