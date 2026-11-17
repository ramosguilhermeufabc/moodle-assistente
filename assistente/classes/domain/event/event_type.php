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

namespace local_assistente\domain\event;

/**
 * Logical types of assistant events and the schema of their payload (plan 006 Q-006-02 and Q-006-03).
 *
 * Derived from the "Catálogo de gatilhos" of plan 022. The names of the Moodle events behind each type are candidates
 * validated by CL-02 and the other technical validations; the type is the logical trigger, not a Moodle class. The course
 * is the `course_id` column of the event, so it is not repeated in the payload.
 *
 * The payload of each type holds only the identifiers needed to recognise the occurrence and to find the source object.
 * Handlers read the current state from Moodle; the payload does not copy it. A type that a later delivery needs is added
 * here, with its payload and its identity, by the task of that delivery.
 *
 * Not in the catalog on purpose: matrícula and papel (DEC-47), the report dispatcher (it does not go through the queue,
 * P-05), and the course deletion (Privacy API and course eraser, DEC-42).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum event_type: string {
    /** A questionnaire became visible to students for the first time (DEC-31). */
    case QUIZ_PUBLISHED = 'quiz_published';
    /** A material was included or its content changed (DEC-33). */
    case MATERIAL_CHANGED = 'material_changed';
    /** A quiz attempt was submitted (DEC-36 D4). */
    case QUIZ_ATTEMPT_SUBMITTED = 'quiz_attempt_submitted';
    /** A quiz answer was graded manually (DEC-36 D4). */
    case QUIZ_ATTEMPT_MANUALLY_GRADED = 'quiz_attempt_manually_graded';
    /** A quiz attempt was regraded (DEC-36 D4). */
    case QUIZ_ATTEMPT_REGRADED = 'quiz_attempt_regraded';
    /** The closing time of a quiz passed; detected by a task, not by a Moodle event (DEC-36 D4). */
    case QUIZ_CLOSED = 'quiz_closed';
    /** A grade was given, changed or removed (DEC-37 D1). */
    case GRADE_CHANGED = 'grade_changed';
    /** A forum message was created (DEC-03, DEC-38). */
    case FORUM_POST_CREATED = 'forum_post_created';
    /** A forum message was edited (DEC-38 D2). */
    case FORUM_POST_UPDATED = 'forum_post_updated';
    /** A forum message was deleted (DEC-38 D2). */
    case FORUM_POST_DELETED = 'forum_post_deleted';
    /** A forum discussion was deleted (DEC-38 D2). */
    case FORUM_DISCUSSION_DELETED = 'forum_discussion_deleted';
    /**
     * The complete rebuild of a forum was requested by the initial synchronization (CL-47; DEC-38 D2, DEC-45 D3). Internal event of the
     * plugin: no Moodle event and no observer, it is queued by initial_sync_service and has no discussion or message.
     */
    case FORUM_REBUILD_REQUESTED = 'forum_rebuild_requested';
    /** A signal of participation of a student changed: submission, completion, post or grade (DEC-39 D2). */
    case PARTICIPATION_CHANGED = 'participation_changed';
    /** The reference date of an activity passed; detected by a task (DEC-39 D2). */
    case REFERENCE_DATE_REACHED = 'reference_date_reached';
    /** The pedagogical synthesis of a course section must be recomputed (DEC-41 D3). */
    case SYNTHESIS_REQUESTED = 'synthesis_requested';
    /** The criteria of a student must be applied again after a performance or frequency analysis (DEC-40; T093, DEC-55). */
    case CRITERIA_CHECK_REQUESTED = 'criteria_check_requested';
    /** The criteria of every student of a course must be applied again after a reference date passed (DEC-55 D2; T141). */
    case COURSE_CRITERIA_CHECK_REQUESTED = 'course_criteria_check_requested';

    /**
     * Payload fields and their kind (int or string). Every field is required; no other field is accepted.
     *
     * @return array<string, string> field => 'int' or 'string'
     */
    public function fields(): array {
        return match ($this) {
            self::QUIZ_PUBLISHED => ['cmid' => 'int'],
            self::MATERIAL_CHANGED => ['cmid' => 'int', 'moodle_event' => 'string', 'occurred_at' => 'int'],
            self::QUIZ_ATTEMPT_SUBMITTED => ['cmid' => 'int', 'attemptid' => 'int', 'userid' => 'int'],
            self::QUIZ_ATTEMPT_MANUALLY_GRADED => ['cmid' => 'int', 'attemptid' => 'int', 'slot' => 'int', 'occurred_at' => 'int'],
            self::QUIZ_ATTEMPT_REGRADED => ['cmid' => 'int', 'attemptid' => 'int', 'occurred_at' => 'int'],
            self::QUIZ_CLOSED => ['cmid' => 'int', 'timeclose' => 'int'],
            self::GRADE_CHANGED => ['userid' => 'int', 'itemid' => 'int', 'occurred_at' => 'int'],
            self::FORUM_POST_CREATED => ['cmid' => 'int', 'discussionid' => 'int', 'postid' => 'int'],
            self::FORUM_POST_UPDATED => ['cmid' => 'int', 'discussionid' => 'int', 'postid' => 'int', 'occurred_at' => 'int'],
            self::FORUM_POST_DELETED => ['cmid' => 'int', 'discussionid' => 'int', 'postid' => 'int'],
            self::FORUM_DISCUSSION_DELETED => ['cmid' => 'int', 'discussionid' => 'int'],
            self::FORUM_REBUILD_REQUESTED => ['cmid' => 'int'],
            self::PARTICIPATION_CHANGED => ['cmid' => 'int', 'userid' => 'int', 'source' => 'string', 'occurred_at' => 'int'],
            self::REFERENCE_DATE_REACHED => ['cmid' => 'int', 'reference_date' => 'int'],
            self::SYNTHESIS_REQUESTED => ['section_id' => 'int'],
            self::CRITERIA_CHECK_REQUESTED => ['userid' => 'int'],
            self::COURSE_CRITERIA_CHECK_REQUESTED => ['course_id' => 'int'],
        };
    }

    /**
     * How the same occurrence received twice is recognised.
     *
     * @return identity_strategy
     */
    public function strategy(): identity_strategy {
        return match ($this) {
            self::MATERIAL_CHANGED,
            self::QUIZ_ATTEMPT_MANUALLY_GRADED,
            self::QUIZ_ATTEMPT_REGRADED,
            self::GRADE_CHANGED,
            self::FORUM_POST_UPDATED,
            self::PARTICIPATION_CHANGED => identity_strategy::OCCURRENCE,
            self::FORUM_REBUILD_REQUESTED,
            self::SYNTHESIS_REQUESTED,
            self::CRITERIA_CHECK_REQUESTED,
            self::COURSE_CRITERIA_CHECK_REQUESTED => identity_strategy::COALESCE_PENDING,
            default => identity_strategy::ONCE_PER_OBJECT,
        };
    }

    /**
     * Payload fields that make up the identity, in order.
     *
     * @return string[]
     */
    public function identity_fields(): array {
        return match ($this) {
            self::QUIZ_PUBLISHED, self::QUIZ_CLOSED, self::REFERENCE_DATE_REACHED => array_keys($this->fields()),
            self::QUIZ_ATTEMPT_SUBMITTED => ['attemptid'],
            self::FORUM_POST_CREATED, self::FORUM_POST_DELETED => ['postid'],
            self::FORUM_DISCUSSION_DELETED => ['discussionid'],
            self::FORUM_REBUILD_REQUESTED => ['cmid'],
            self::SYNTHESIS_REQUESTED => ['section_id'],
            self::CRITERIA_CHECK_REQUESTED => ['userid'],
            self::COURSE_CRITERIA_CHECK_REQUESTED => ['course_id'],
            self::MATERIAL_CHANGED => ['cmid', 'moodle_event', 'occurred_at'],
            self::QUIZ_ATTEMPT_MANUALLY_GRADED => ['attemptid', 'slot', 'occurred_at'],
            self::QUIZ_ATTEMPT_REGRADED => ['attemptid', 'occurred_at'],
            self::GRADE_CHANGED => ['userid', 'itemid', 'occurred_at'],
            self::FORUM_POST_UPDATED => ['postid', 'occurred_at'],
            self::PARTICIPATION_CHANGED => ['cmid', 'userid', 'source', 'occurred_at'],
        };
    }

    /**
     * The limitation of the identity of this type, to be shown to whoever relies on it (DEC-21: no strong deduplication
     * is claimed when the data cannot tell identical legitimate occurrences apart).
     *
     * @return string
     */
    public function limitation(): string {
        return match ($this->strategy()) {
            identity_strategy::ONCE_PER_OBJECT => 'Recognised by the object: the same object never produces a second event of this type.',
            identity_strategy::OCCURRENCE => 'Recognised by the object and the second of the Moodle event: two legitimate occurrences '
                . 'in the same second collapse into one event.',
            identity_strategy::COALESCE_PENDING => 'Recognised by the object while an event is still pending: a request made while '
                . 'another is pending is merged into it, and a request made after it ran creates a new event.',
        };
    }

    /**
     * Validate a payload against the schema of this type and normalise it (integers are cast).
     *
     * @param array $payload
     * @return array the same fields, in the order of the schema, with the kinds enforced
     * @throws \InvalidArgumentException on a missing, unknown or wrongly typed field
     */
    public function normalize_payload(array $payload): array {
        $fields = $this->fields();
        $unknown = array_diff(array_keys($payload), array_keys($fields));
        if ($unknown) {
            throw new \InvalidArgumentException("Unknown payload field(s) for {$this->value}: " . implode(', ', $unknown));
        }

        $normalized = [];
        foreach ($fields as $name => $kind) {
            if (!array_key_exists($name, $payload)) {
                throw new \InvalidArgumentException("Missing payload field '{$name}' for {$this->value}.");
            }
            $value = $payload[$name];
            if ($kind === 'int') {
                if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
                    $value = (int) $value;
                }
                if (!is_int($value)) {
                    throw new \InvalidArgumentException("Payload field '{$name}' of {$this->value} must be an integer.");
                }
            } else if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("Payload field '{$name}' of {$this->value} must be a non-empty string.");
            }
            $normalized[$name] = $value;
        }
        return $normalized;
    }
}
