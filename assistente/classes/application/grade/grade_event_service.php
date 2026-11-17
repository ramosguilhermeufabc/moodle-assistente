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

namespace local_assistente\application\grade;

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;

/**
 * Turns the grade events of the Moodle gradebook into `grade_changed` events of the queue (T079; spec-012, DEC-37 D1).
 *
 * It only builds the payload and hands it to the event service, which deduplicates by identity (student, item and moment) and does
 * nothing for a course without the assistant. It reads no grade and calculates nothing: that is performance_handler (T078), which
 * recalculates the whole student when it runs. An event with a missing identifier (zero) is refused with an InvalidArgumentException,
 * so that nothing is queued from incomplete data.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_event_service {
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
     * A grade of a student was given, changed or removed.
     *
     * @param int $courseid
     * @param int $userid the student
     * @param int $itemid the grade item
     * @param int $occurredat moment of the Moodle event
     * @return enqueue_result
     * @throws \InvalidArgumentException when an identifier is missing
     */
    public function grade_changed(int $courseid, int $userid, int $itemid, int $occurredat): enqueue_result {
        foreach (['course' => $courseid, 'user' => $userid, 'item' => $itemid] as $name => $id) {
            if ($id <= 0) {
                throw new \InvalidArgumentException("The {$name} of the grade event is missing.");
            }
        }
        return $this->events->enqueue($courseid, event_type::GRADE_CHANGED, [
            'userid' => $userid, 'itemid' => $itemid, 'occurred_at' => $occurredat,
        ]);
    }
    /**
     * Many grades at once: the same events as grade_changed() for each pair, in chunks (event_service::enqueue_many()).
     *
     * @param int $courseid
     * @param array<int, array{userid: int, itemid: int}> $pairs a student and a grade item each
     * @param int $occurredat moment of the Moodle event, the same for all
     * @return enqueue_result[] one for each pair, in order
     * @throws \InvalidArgumentException when an identifier is missing
     */
    public function grade_changed_many(int $courseid, array $pairs, int $occurredat): array {
        $occurrences = [];
        foreach ($pairs as $key => $pair) {
            foreach (['course' => $courseid, 'user' => $pair['userid'], 'item' => $pair['itemid']] as $name => $id) {
                if ($id <= 0) {
                    throw new \InvalidArgumentException("The {$name} of the grade event is missing.");
                }
            }
            $occurrences[$key] = ['type' => event_type::GRADE_CHANGED, 'payload' => [
                'userid' => $pair['userid'], 'itemid' => $pair['itemid'], 'occurred_at' => $occurredat,
            ]];
        }
        return $this->events->enqueue_many($courseid, $occurrences);
    }
}
