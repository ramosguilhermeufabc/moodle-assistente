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

namespace local_assistente\application\forum;

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\forum_reader;

/**
 * Turns the forum events of mod_forum into events of the queue that analyse the forum (T083; spec-013, DEC-03, DEC-38).
 *
 * It only builds the payload and hands it to the event service, which deduplicates by identity and does nothing for a course without the
 * assistant. It reads no message and calls no LLM: that is forum_analysis_handler (T082). An event with a missing identifier (zero) is
 * refused with an InvalidArgumentException, so that nothing is queued from incomplete data.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_event_service {
    /** @var event_service */
    private event_service $events;

    /** @var forum_reader */
    private forum_reader $forums;

    /**
     * Constructor.
     *
     * @param event_service|null $events
     * @param forum_reader|null $forums
     */
    public function __construct(?event_service $events = null, ?forum_reader $forums = null) {
        $this->events = $events ?? new event_service();
        $this->forums = $forums ?? new forum_reader();
    }

    /**
     * A message was created.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $discussionid
     * @param int $postid
     * @return enqueue_result
     */
    public function post_created(int $courseid, int $cmid, int $discussionid, int $postid): enqueue_result {
        $this->require_positive(['course' => $courseid, 'forum' => $cmid, 'discussion' => $discussionid, 'post' => $postid]);
        return $this->events->enqueue($courseid, event_type::FORUM_POST_CREATED, [
            'cmid' => $cmid, 'discussionid' => $discussionid, 'postid' => $postid,
        ]);
    }

    /**
     * A discussion was created: its first message is a message created. Moodle emits `discussion_created` for it and no `post_created`,
     * so the first message is found through the discussion (T083, CL-35).
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $discussionid
     * @return enqueue_result|null null when the discussion is not found in the forum
     */
    public function discussion_created(int $courseid, int $cmid, int $discussionid): ?enqueue_result {
        $this->require_positive(['course' => $courseid, 'forum' => $cmid, 'discussion' => $discussionid]);
        foreach ($this->forums->get_discussions($cmid) as $discussion) {
            if ($discussion->discussion_id === $discussionid) {
                return $this->post_created($courseid, $cmid, $discussionid, $discussion->first_post_id);
            }
        }
        return null;
    }
    /**
     * A message was edited. Each edit is its own occurrence (identity: message and moment).
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $discussionid
     * @param int $postid
     * @param int $occurredat
     * @return enqueue_result
     */
    public function post_updated(int $courseid, int $cmid, int $discussionid, int $postid, int $occurredat): enqueue_result {
        $this->require_positive(['course' => $courseid, 'forum' => $cmid, 'discussion' => $discussionid, 'post' => $postid]);
        return $this->events->enqueue($courseid, event_type::FORUM_POST_UPDATED, [
            'cmid' => $cmid, 'discussionid' => $discussionid, 'postid' => $postid, 'occurred_at' => $occurredat,
        ]);
    }

    /**
     * A message was deleted.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $discussionid
     * @param int $postid
     * @return enqueue_result
     */
    public function post_deleted(int $courseid, int $cmid, int $discussionid, int $postid): enqueue_result {
        $this->require_positive(['course' => $courseid, 'forum' => $cmid, 'discussion' => $discussionid, 'post' => $postid]);
        return $this->events->enqueue($courseid, event_type::FORUM_POST_DELETED, [
            'cmid' => $cmid, 'discussionid' => $discussionid, 'postid' => $postid,
        ]);
    }

    /**
     * A discussion was deleted.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $discussionid
     * @return enqueue_result
     */
    public function discussion_deleted(int $courseid, int $cmid, int $discussionid): enqueue_result {
        $this->require_positive(['course' => $courseid, 'forum' => $cmid, 'discussion' => $discussionid]);
        return $this->events->enqueue($courseid, event_type::FORUM_DISCUSSION_DELETED, [
            'cmid' => $cmid, 'discussionid' => $discussionid,
        ]);
    }

    /**
     * The complete rebuild of a forum was requested (the initial synchronization, CL-47). The event has no discussion or message and
     * coalesces while pending: a second request before the first runs is the first, and a request made after it ran creates a new event.
     * The deletion events are another type and are never merged into it.
     *
     * @param int $courseid
     * @param int $cmid
     * @return enqueue_result
     */
    public function rebuild_requested(int $courseid, int $cmid): enqueue_result {
        $this->require_positive(['course' => $courseid, 'forum' => $cmid]);
        return $this->events->enqueue($courseid, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => $cmid]);
    }

    /**
     * Refuse an identifier that is missing.
     *
     * @param array<string, int> $ids
     * @throws \InvalidArgumentException
     */
    private function require_positive(array $ids): void {
        foreach ($ids as $name => $id) {
            if ($id <= 0) {
                throw new \InvalidArgumentException("The {$name} of the forum event is missing.");
            }
        }
    }
}
