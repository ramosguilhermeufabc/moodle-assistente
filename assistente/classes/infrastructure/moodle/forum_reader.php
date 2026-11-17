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

namespace local_assistente\infrastructure\moodle;

/**
 * Reads forums, discussions and messages that exist now (spec-004; DEC-34 D3, DEC-38, DEC-39, DEC-40).
 *
 * The reader returns the author id and a generic role (teacher or student) because participation counts postings
 * per user (DEC-39, DEC-40). It does not minimise data for the LLM: the consuming services must leave author,
 * user id, name and e-mail out of what is sent to the LLM (DEC-34 D3).
 *
 * Moodle API: get_coursemodule_from_id('forum', ...) and has_capability() with moodle/course:manageactivities to
 * tell teachers from students. Verified in the MOODLE_502_STABLE sources; behaviour in a running Moodle 5.2
 * is validated in CL-35 (T035). Hypothesis: a poster who can manage activities in the course is a "teacher", any
 * other is a "student" (to confirm in CL-35 (e)).
 *
 * DML justification: forum_discussions and forum_posts are read directly because the mod_forum
 * API (vaults and entities) is built for rendering a forum page for one viewer, not for listing every existing
 * message of a forum. Only existing, non-deleted messages are returned. The reader never writes.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_reader {
    /** @var string[] cache of the generic role by "course:user" */
    private array $roles = [];

    /**
     * Read the forum of a course module.
     *
     * @param int $cmid
     * @return \stdClass|null cmid, forum_id, course_id, name, type; null when the cmid is not a forum
     */
    public function get_forum(int $cmid): ?\stdClass {
        global $DB;
        $cm = get_coursemodule_from_id('forum', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        $forum = $DB->get_record('forum', ['id' => $cm->instance], 'id, course, name, type', IGNORE_MISSING);
        if (!$forum) {
            return null;
        }
        return (object) [
            'cmid' => (int) $cmid,
            'forum_id' => (int) $forum->id,
            'course_id' => (int) $forum->course,
            'name' => (string) $forum->name,
            'type' => (string) $forum->type,
        ];
    }

    /**
     * Discussions of a forum.
     *
     * @param int $cmid
     * @return \stdClass[] discussion_id, name, first_post_id, group_id (-1 = all participants, as in Moodle); [] when not a forum
     */
    public function get_discussions(int $cmid): array {
        global $DB;
        $forum = $this->get_forum($cmid);
        if ($forum === null) {
            return [];
        }
        $discussions = [];
        $records = $DB->get_records('forum_discussions', ['forum' => $forum->forum_id], 'id ASC', 'id, name, firstpost, groupid');
        foreach ($records as $record) {
            $discussions[] = (object) [
                'discussion_id' => (int) $record->id,
                'name' => (string) $record->name,
                'first_post_id' => (int) $record->firstpost,
                'group_id' => (int) $record->groupid,
            ];
        }
        return $discussions;
    }

    /**
     * Existing messages of a discussion, in order of creation. Deleted messages are not returned.
     *
     * Each item has: post_id, discussion_id, parent_id (0 for the first message), user_id, role (teacher or
     * student), created, modified, subject, message (as stored, with its format in message_format) and
     * private_reply (bool). Private replies are returned and flagged; whether they enter an analysis is for the
     * consumer to decide.
     *
     * @param int $discussionid
     * @return \stdClass[] [] when the discussion does not exist
     */
    public function get_messages(int $discussionid): array {
        global $DB;
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], 'id, course', IGNORE_MISSING);
        if (!$discussion) {
            return [];
        }
        $messages = [];
        $records = $DB->get_records(
            'forum_posts',
            ['discussion' => $discussionid, 'deleted' => 0],
            'created ASC, id ASC',
            'id, discussion, parent, userid, created, modified, subject, message, messageformat, privatereplyto'
        );
        foreach ($records as $record) {
            $messages[] = (object) [
                'post_id' => (int) $record->id,
                'discussion_id' => (int) $record->discussion,
                'parent_id' => (int) $record->parent,
                'user_id' => (int) $record->userid,
                'role' => $this->role((int) $discussion->course, (int) $record->userid),
                'created' => (int) $record->created,
                'modified' => (int) $record->modified,
                'subject' => (string) $record->subject,
                'message' => (string) $record->message,
                'message_format' => (int) $record->messageformat,
                'private_reply' => (int) $record->privatereplyto !== 0,
            ];
        }
        return $messages;
    }

    /**
     * Read one existing message.
     *
     * @param int $postid
     * @return \stdClass|null same fields as get_messages(); null when it does not exist or was deleted
     */
    public function get_message(int $postid): ?\stdClass {
        global $DB;
        $discussionid = $DB->get_field('forum_posts', 'discussion', ['id' => $postid, 'deleted' => 0], IGNORE_MISSING);
        if ($discussionid === false) {
            return null;
        }
        foreach ($this->get_messages((int) $discussionid) as $message) {
            if ($message->post_id === $postid) {
                return $message;
            }
        }
        return null;
    }

    /**
     * Number of existing messages of each user in a forum (participation, DEC-39 and DEC-40).
     *
     * @param int $cmid
     * @return int[] user id => number of messages; [] when not a forum
     */
    public function get_post_counts(int $cmid): array {
        global $DB;
        $forum = $this->get_forum($cmid);
        if ($forum === null) {
            return [];
        }
        $counts = [];
        $records = $DB->get_records_sql(
            'SELECT p.userid, COUNT(p.id) AS total
               FROM {forum_posts} p
               JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE d.forum = :forum AND p.deleted = 0
           GROUP BY p.userid',
            ['forum' => $forum->forum_id]
        );
        foreach ($records as $record) {
            $counts[(int) $record->userid] = (int) $record->total;
        }
        return $counts;
    }

    /**
     * Whether a user has at least one existing message in the forum.
     *
     * @param int $cmid
     * @param int $userid
     * @return bool
     */
    public function has_posted(int $cmid, int $userid): bool {
        return ($this->get_post_counts($cmid)[$userid] ?? 0) > 0;
    }

    /**
     * Generic role of a poster in the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return string teacher or student
     */
    private function role(int $courseid, int $userid): string {
        $key = "{$courseid}:{$userid}";
        if (!isset($this->roles[$key])) {
            $this->roles[$key] = has_capability('moodle/course:manageactivities', \context_course::instance($courseid), $userid)
                ? 'teacher'
                : 'student';
        }
        return $this->roles[$key];
    }
}
