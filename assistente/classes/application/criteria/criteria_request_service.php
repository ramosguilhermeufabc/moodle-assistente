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

namespace local_assistente\application\criteria;

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;

/**
 * Queues the application of the criteria: to one student after a performance or frequency analysis of that student, and to the whole
 * course after a reference date passed (T093, T141; DEC-40, DEC-55).
 *
 * It only builds the event and hands it to the event service, which does nothing for a course without the assistant and reuses a request
 * that is still pending (the identity is coalesced). It calculates nothing and reads nothing.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class criteria_request_service {
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
     * Ask for the criteria of a student to be applied.
     *
     * @param int $courseid
     * @param int $userid
     * @return enqueue_result
     * @throws \InvalidArgumentException when the course or the user is missing
     */
    public function request(int $courseid, int $userid): enqueue_result {
        if ($courseid <= 0 || $userid <= 0) {
            throw new \InvalidArgumentException('The course and the user of the criteria request are required.');
        }
        return $this->events->enqueue($courseid, event_type::CRITERIA_CHECK_REQUESTED, ['userid' => $userid]);
    }

    /**
     * Ask for the criteria of every student of a course to be applied, in one processing (DEC-55 D2). A request that is still pending for
     * the course is reused.
     *
     * @param int $courseid
     * @return enqueue_result
     * @throws \InvalidArgumentException when the course is missing
     */
    public function request_course(int $courseid): enqueue_result {
        if ($courseid <= 0) {
            throw new \InvalidArgumentException('The course of the criteria request is required.');
        }
        return $this->events->enqueue($courseid, event_type::COURSE_CRITERIA_CHECK_REQUESTED, ['course_id' => $courseid]);
    }
}
