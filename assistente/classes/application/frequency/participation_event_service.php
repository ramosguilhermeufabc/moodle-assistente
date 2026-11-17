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

namespace local_assistente\application\frequency;

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\gradebook_reader;

/**
 * Turns the events of Moodle that change a signal of participation into `participation_changed` events of the queue (T089; spec-014,
 * DEC-25, DEC-39 D1 and D2).
 *
 * The signals that count are a submission sent, a completion, a grade and a post (and a finished attempt, which reaches the plugin through
 * the grade of the attempt). Each source says where the change came from (`submission`, `completion`, `grade`, `post`). The event carries the
 * module and the student whose signal changed; the frequency handler (T087) recalculates that student whatever the source.
 *
 * It only builds the payload and hands it to the event service, which deduplicates by identity (module, student, source and moment) and does
 * nothing for a course without the assistant. It calculates nothing. An event with a missing identifier is refused with an
 * InvalidArgumentException, so that nothing is queued from incomplete data. The events it queues emit nothing in Moodle, so it cannot start
 * a cycle with the observers.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participation_event_service {
    /** @var string[] sources of a change of participation */
    public const SOURCES = ['submission', 'completion', 'grade', 'post'];

    /** @var event_service */
    private event_service $events;

    /** @var gradebook_reader */
    private gradebook_reader $gradebook;

    /**
     * Constructor.
     *
     * @param event_service|null $events
     * @param gradebook_reader|null $gradebook
     */
    public function __construct(?event_service $events = null, ?gradebook_reader $gradebook = null) {
        $this->events = $events ?? new event_service();
        $this->gradebook = $gradebook ?? new gradebook_reader();
    }

    /**
     * The participation of a student in a module changed.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $userid the student whose signal changed
     * @param string $source one of SOURCES
     * @param int $occurredat moment of the Moodle event
     * @return enqueue_result
     * @throws \InvalidArgumentException when an identifier is missing or the source is not known
     */
    public function participation_changed(int $courseid, int $cmid, int $userid, string $source, int $occurredat): enqueue_result {
        foreach (['course' => $courseid, 'module' => $cmid, 'user' => $userid] as $name => $id) {
            if ($id <= 0) {
                throw new \InvalidArgumentException("The {$name} of the participation event is missing.");
            }
        }
        if (!in_array($source, self::SOURCES, true)) {
            throw new \InvalidArgumentException("Unknown source '{$source}' of participation.");
        }
        return $this->events->enqueue($courseid, event_type::PARTICIPATION_CHANGED, [
            'cmid' => $cmid, 'userid' => $userid, 'source' => $source, 'occurred_at' => $occurredat,
        ]);
    }

    /**
     * A grade of a student in a grade item changed. Only the main grade item of an activity module is a signal of participation (the
     * one the participation reader looks at); a category, the course total, a manual item and any other item of a module are not.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $itemid the grade item
     * @param int $occurredat
     * @return enqueue_result|null null when the item is not the main grade item of an activity module
     */
    public function grade_item_changed(int $courseid, int $userid, int $itemid, int $occurredat): ?enqueue_result {
        if ($courseid <= 0 || $userid <= 0 || $itemid <= 0) {
            throw new \InvalidArgumentException('The grade event is missing its course, user or item.');
        }
        foreach ($this->gradebook->get_items($courseid) as $item) {
            if ($item->item_id === $itemid && $item->item_number === 0) {
                return $this->participation_changed($courseid, $item->cmid, $userid, 'grade', $occurredat);
            }
        }
        return null;
    }
    /**
     * Many signals of participation at once: the same events as participation_changed() for each item, in chunks
     * (event_service::enqueue_many()).
     *
     * @param int $courseid
     * @param array<int, array{cmid: int, userid: int, source: string}> $items
     * @param int $occurredat moment of the Moodle event, the same for all
     * @return enqueue_result[] one for each item, in order
     * @throws \InvalidArgumentException when an identifier is missing or the source is not known
     */
    public function participation_changed_many(int $courseid, array $items, int $occurredat): array {
        $occurrences = [];
        foreach ($items as $key => $item) {
            foreach (['course' => $courseid, 'module' => $item['cmid'], 'user' => $item['userid']] as $name => $id) {
                if ($id <= 0) {
                    throw new \InvalidArgumentException("The {$name} of the participation event is missing.");
                }
            }
            if (!in_array($item['source'], self::SOURCES, true)) {
                throw new \InvalidArgumentException("Unknown source '{$item['source']}' of participation.");
            }
            $occurrences[$key] = ['type' => event_type::PARTICIPATION_CHANGED, 'payload' => [
                'cmid' => $item['cmid'], 'userid' => $item['userid'], 'source' => $item['source'], 'occurred_at' => $occurredat,
            ]];
        }
        return $this->events->enqueue_many($courseid, $occurrences);
    }
}
