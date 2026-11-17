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
use local_assistente\application\forum\forum_event_service;
use local_assistente\application\frequency\participation_event_service;

/**
 * Observer of the message events of mod_forum (T083; DEC-03, DEC-38; plan 022).
 *
 * Thin by contract: it reads the identifiers from the Moodle event (course, forum module, discussion, message, moment), never from the
 * request, and delegates to forum_event_service. No message is read and no LLM is called here (that is forum_analysis_handler).
 *
 * Events observed, as read in Moodle 5.2.4 (CL-35/T084 validates them further):
 * - `\mod_forum\event\post_created`, `post_updated` and `post_deleted`: `objectid` is the message, `other['discussionid']` its discussion,
 *   `contextinstanceid` the course module of the forum;
 * - `\mod_forum\event\discussion_deleted`: `objectid` is the discussion;
 * - `\mod_forum\event\discussion_created`: `objectid` is the discussion. Moodle emits it, and not `post_created`, for the first message of a
 *   discussion, so it is read as the creation of that message (found in `firstpost`). FINDING of T083, to confirm in CL-35: `post_created`
 *   and `discussion_created` are triggered by the scripts of the forum (post.php, the web service, the inbound e-mail handler), not by
 *   forum_add_discussion() or forum_add_new_post(), so a message created by code that only calls those functions emits neither.
 * Deleting a discussion in Moodle also deletes its messages, so several events can come for one action; each one rebuilds the forum.
 * A message created (or the first message of a discussion) or deleted also queues `participation_changed` for its author (T089); an edit and
 * the deletion of a whole discussion do not (the latter does not say who posted, but it emits `post_deleted` for each message).
 *
 * Failure isolation, as in the other observers: a failure of the queue is logged and not rethrown. The observers are registered after the
 * commit (`internal => false`, T046).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_observer {
    /** @var forum_event_service|null replacement used by tests */
    private static ?forum_event_service $service = null;

    /** @var participation_event_service|null replacement used by tests */
    private static ?participation_event_service $participation = null;

    /**
     * Replace the service, for tests. Pass null to restore the default.
     *
     * @param forum_event_service|null $service
     */
    public static function use_service(?forum_event_service $service): void {
        self::$service = $service;
    }

    /**
     * Replace the participation service, for tests. Pass null to restore the default.
     *
     * @param participation_event_service|null $service
     */
    public static function use_participation_service(?participation_event_service $service): void {
        self::$participation = $service;
    }

    /**
     * A message was created.
     *
     * @param \mod_forum\event\post_created $event
     */
    public static function post_created(\mod_forum\event\post_created $event): void {
        self::run($event, static fn(forum_event_service $s) => $s->post_created(
            (int) $event->courseid,
            (int) $event->contextinstanceid,
            (int) ($event->other['discussionid'] ?? 0),
            (int) $event->objectid
        ));
        self::post_signal($event, (int) $event->userid);
    }

    /**
     * A discussion was created: its first message is a message created.
     *
     * @param \mod_forum\event\discussion_created $event
     */
    public static function discussion_created(\mod_forum\event\discussion_created $event): void {
        self::run($event, static fn(forum_event_service $s) => $s->discussion_created(
            (int) $event->courseid,
            (int) $event->contextinstanceid,
            (int) $event->objectid
        ));
        self::post_signal($event, (int) $event->userid);
    }

    /**
     * A message was edited.
     *
     * @param \mod_forum\event\post_updated $event
     */
    public static function post_updated(\mod_forum\event\post_updated $event): void {
        self::run($event, static fn(forum_event_service $s) => $s->post_updated(
            (int) $event->courseid,
            (int) $event->contextinstanceid,
            (int) ($event->other['discussionid'] ?? 0),
            (int) $event->objectid,
            (int) $event->timecreated
        ));
    }

    /**
     * A message was deleted.
     *
     * @param \mod_forum\event\post_deleted $event
     */
    public static function post_deleted(\mod_forum\event\post_deleted $event): void {
        self::run($event, static fn(forum_event_service $s) => $s->post_deleted(
            (int) $event->courseid,
            (int) $event->contextinstanceid,
            (int) ($event->other['discussionid'] ?? 0),
            (int) $event->objectid
        ));
        self::post_signal($event, (int) ($event->relateduserid ?: $event->userid));
    }

    /**
     * A discussion was deleted.
     *
     * @param \mod_forum\event\discussion_deleted $event
     */
    public static function discussion_deleted(\mod_forum\event\discussion_deleted $event): void {
        self::run($event, static fn(forum_event_service $s) => $s->discussion_deleted(
            (int) $event->courseid,
            (int) $event->contextinstanceid,
            (int) $event->objectid
        ));
    }

    /**
     * A message created or deleted changes the participation of its author in the forum, a signal of the frequency (T089). The author is
     * the user of the event when the author acts (creating, or deleting one's own message) and the related user when somebody else
     * deletes the message. An edit changes no participation and a deleted discussion does not say who posted in it. A failure is contained
     * and does not stop the other delivery.
     *
     * @param \core\event\base $event
     * @param int $authorid
     */
    private static function post_signal(\core\event\base $event, int $authorid): void {
        try {
            (self::$participation ?? new participation_event_service(observer_event_service::get()))->participation_changed(
                (int) $event->courseid,
                (int) $event->contextinstanceid,
                $authorid,
                'post',
                (int) $event->timecreated
            );
        } catch (\Throwable $e) {
            $message = 'local_assistente: could not process the participation of the forum event ' . $event->eventname . ' of object '
                . (int) $event->objectid . ': ' . $e->getMessage();
            debugging($message, DEBUG_NORMAL);
            error_log($message);
        }
    }

    /**
     * Run the delegation, containing any failure.
     *
     * @param \core\event\base $event
     * @param \Closure $delegate receives the service
     */
    private static function run(\core\event\base $event, \Closure $delegate): void {
        try {
            $delegate(self::$service ?? new forum_event_service(observer_event_service::get()));
        } catch (\Throwable $e) {
            $message = 'local_assistente: could not process the forum event ' . $event->eventname . ' of object '
                . (int) $event->objectid . ': ' . $e->getMessage();
            debugging($message, DEBUG_NORMAL);
            error_log($message);
        }
    }
}
