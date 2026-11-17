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
 * Reads the signals of participation of a student in an activity (spec-004; DEC-25, DEC-39 D1 and D3).
 *
 * Participation is any of: a submitted assignment, a finished attempt (not a preview), a grade, a completion
 * state complete, passed or failed, or a forum posting. This reader only reports the signals; which activities
 * enter the denominator and when is a rule of the frequency calculation (spec-014). The last access to the
 * course is evidence only, outside the denominator (DEC-25).
 *
 * Moodle API: completion_info::is_enabled() and get_data() (completion states COMPLETION_COMPLETE,
 * COMPLETION_COMPLETE_PASS and COMPLETION_COMPLETE_FAIL); the questionnaire, gradebook and forum readers of this
 * layer. Verified by reading the MOODLE_502_STABLE sources; behaviour in a running Moodle 5.2 is validated in
 * CL-36 (T032).
 *
 * DML justification: (1) assign_submission, to know whether a submission was sent (status "submitted", latest
 * attempt) — assignment has no light API for it; (2) user_lastaccess, the table where Moodle records the last access
 * of a user to each course.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participation_reader {
    /** @var string[] completion state code => name */
    private const COMPLETION_STATES = [0 => 'incomplete', 1 => 'complete', 2 => 'complete_pass', 3 => 'complete_fail'];

    /** @var gradebook_reader */
    private gradebook_reader $gradebook;

    /** @var forum_reader */
    private forum_reader $forums;

    /** @var questionnaire_reader */
    private questionnaire_reader $questionnaires;

    /** @var array<int, array<int, int>>|null message counts of a forum by course module while a memo is open (see with_run_memo()); null otherwise */
    private ?array $postcounts = null;

    /** @var array<int, array<int, true>>|null users with a finished attempt that is not a preview, by quiz course module, while a memo is open */
    private ?array $finishedusers = null;

    /**
     * Constructor.
     *
     * @param gradebook_reader|null $gradebook
     * @param forum_reader|null $forums
     * @param questionnaire_reader|null $questionnaires
     */
    public function __construct(
        ?gradebook_reader $gradebook = null,
        ?forum_reader $forums = null,
        ?questionnaire_reader $questionnaires = null
    ) {
        $this->gradebook = $gradebook ?? new gradebook_reader();
        $this->forums = $forums ?? new forum_reader();
        $this->questionnaires = $questionnaires ?? new questionnaire_reader();
    }

    /**
     * Run work that asks for the participation of many students, reading each forum's message counts and each quiz's attempts once.
     *
     * Without this, every call of get_participation() for a forum reads the counts of the whole forum and every call for a quiz reads all
     * the attempts of the quiz, so reading S students costs S times the size of the module (A2). While the work runs, the first call for a
     * module reads what it needs and the following calls for the same module reuse it. The answers are the ones the reads would give at that
     * first moment: a message or attempt that appears while the work runs is not seen by the students asked after it.
     *
     * The memory exists only inside this call and is dropped when it ends, also when the work throws: nothing is kept between executions,
     * between requests or in a cache of the site. A call inside another one reuses the memory of the outer one.
     *
     * @param \Closure $work
     * @return mixed what the work returns
     */
    public function with_run_memo(\Closure $work): mixed {
        if ($this->postcounts !== null) {
            return $work();
        }
        $this->postcounts = [];
        $this->finishedusers = [];
        try {
            return $work();
        } finally {
            $this->postcounts = null;
            $this->finishedusers = null;
        }
    }

    /**
     * Signals of participation of a student in an activity.
     *
     * Result fields: cmid, user_id, completion_enabled (bool), completion_state (incomplete, complete,
     * complete_pass, complete_fail; null when completion is not enabled), has_submission, has_finished_attempt,
     * has_grade, has_post (bools), participated (bool: any signal that counts) and signals (the names of the
     * signals that counted: submission, attempt, grade, completion, post).
     *
     * @param int $courseid
     * @param int $userid
     * @param int $cmid
     * @param \stdClass|false|null $finalgrade the final grade of the student in the main grade item, when the caller has already read it
     *        (what get_final_grade() returned, or null when there is none): it is used instead of reading it again. false reads it here.
     * @return \stdClass|null null when the module does not exist in the course
     */
    public function get_participation(int $courseid, int $userid, int $cmid, \stdClass|false|null $finalgrade = false): ?\stdClass {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $cms = get_fast_modinfo($courseid)->get_cms();
        if (!isset($cms[$cmid])) {
            return null;
        }
        $cm = $cms[$cmid];

        $completionenabled = false;
        $completionstate = null;
        $completion = new \completion_info(get_course($courseid));
        if ($completion->is_enabled($cm)) {
            $completionenabled = true;
            $data = $completion->get_data($cm, false, $userid);
            $completionstate = self::COMPLETION_STATES[(int) $data->completionstate] ?? 'incomplete';
        }

        $submission = $cm->modname === 'assign' && $this->has_submission((int) $cm->instance, $userid);
        $attempt = $cm->modname === 'quiz' && $this->has_finished_attempt($cmid, $userid);
        $grade = $finalgrade === false ? $this->gradebook->get_final_grade($courseid, $cmid, $userid) : $finalgrade;
        $hasgrade = $grade !== null && $grade->final_grade !== null;
        $post = $cm->modname === 'forum' && $this->has_posted($cmid, $userid);

        $signals = [];
        $submission && $signals[] = 'submission';
        $attempt && $signals[] = 'attempt';
        $hasgrade && $signals[] = 'grade';
        ($completionstate !== null && $completionstate !== 'incomplete') && $signals[] = 'completion';
        $post && $signals[] = 'post';

        return (object) [
            'cmid' => $cmid,
            'user_id' => $userid,
            'completion_enabled' => $completionenabled,
            'completion_state' => $completionstate,
            'has_submission' => $submission,
            'has_finished_attempt' => $attempt,
            'has_grade' => $hasgrade,
            'has_post' => $post,
            'participated' => (bool) $signals,
            'signals' => $signals,
        ];
    }

    /**
     * Last access of a user to a course (DEC-25, DEC-39 D3): evidence only, outside the denominator.
     *
     * @param int $courseid
     * @param int $userid
     * @return int|null unix time; null when the user never accessed the course
     */
    public function get_last_course_access(int $courseid, int $userid): ?int {
        global $DB;
        $time = $DB->get_field('user_lastaccess', 'timeaccess', ['courseid' => $courseid, 'userid' => $userid], IGNORE_MISSING);
        return ($time === false || (int) $time === 0) ? null : (int) $time;
    }

    /**
     * Whether the student has a submitted assignment (the latest attempt).
     *
     * @param int $assignid assign instance id
     * @param int $userid
     * @return bool
     */
    private function has_submission(int $assignid, int $userid): bool {
        global $DB;
        return $DB->record_exists('assign_submission', [
            'assignment' => $assignid, 'userid' => $userid, 'status' => 'submitted', 'latest' => 1,
        ]);
    }

    /**
     * Whether the student has a finished attempt that is not a preview.
     *
     * @param int $cmid
     * @param int $userid
     * @return bool
     */
    private function has_finished_attempt(int $cmid, int $userid): bool {
        if ($this->finishedusers === null) {
            return isset($this->finished_attempt_users($cmid)[$userid]);
        }
        $this->finishedusers[$cmid] ??= $this->finished_attempt_users($cmid);
        return isset($this->finishedusers[$cmid][$userid]);
    }

    /**
     * The users who have a finished attempt that is not a preview in a quiz.
     *
     * @param int $cmid
     * @return array<int, true> user id => true
     */
    private function finished_attempt_users(int $cmid): array {
        $users = [];
        foreach ($this->questionnaires->get_attempts($cmid) as $attempt) {
            if ($attempt->is_finished && !$attempt->preview) {
                $users[$attempt->user_id] = true;
            }
        }
        return $users;
    }

    /**
     * Whether the user has at least one existing message in the forum.
     *
     * @param int $cmid
     * @param int $userid
     * @return bool
     */
    private function has_posted(int $cmid, int $userid): bool {
        if ($this->postcounts === null) {
            return $this->forums->has_posted($cmid, $userid);
        }
        $this->postcounts[$cmid] ??= $this->forums->get_post_counts($cmid);
        return ($this->postcounts[$cmid][$userid] ?? 0) > 0;
    }
}
