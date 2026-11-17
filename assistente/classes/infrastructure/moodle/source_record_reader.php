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
 * Looks for the Moodle records that the history points to (T102; spec-017, DEC-42 D3).
 *
 * One question for each kind of record the chain refers to: is it there, is it gone, or is it a record of another course. A failure of the
 * database is an exception: a record that could not be looked at is never reported as gone. It only reads.
 *
 * Moodle API: none finds these records by id alone, so the tables of the Moodle schema are read (course_modules with its
 * `deletioninprogress`, forum_discussions, forum_posts with `deleted`, quiz_attempts through quiz, grade_items, user with `deleted`,
 * course_sections). Verified in the MOODLE_502_STABLE sources; the behaviour of a module being deleted is validated with CL-16 (T099).
 *
 * DML justification: Moodle has no API that finds a discussion, a message, an attempt or a grade item by id (the grade item API fetches by
 * conditions and the gradebook reader leaves out the total of the course and the categories), and a lookup that goes through the course
 * would not tell a removed record from a hidden one. The reader never writes.
 *
 * - `course_module`: gone when there is no record or when it is being deleted (`deletioninprogress`, hypothesis CL-16: a module scheduled
 *   for removal is not a valid source);
 * - `forum_discussion`: gone when there is no record;
 * - `forum_post`: gone when there is no record or when Moodle kept it as deleted (`deleted` = 1, which it does for a message that has replies);
 * - `quiz_attempt`: gone when there is no record;
 * - `grade_item`: any item, including the total of the course and the categories, which the gradebook reader leaves out;
 * - `user`: gone when there is no record or the account is deleted (`deleted` = 1);
 * - `course_section`: gone when there is no record.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_record_reader {
    /** @var string the record is there */
    public const PRESENT = 'present';

    /** @var string the record is not there */
    public const ABSENT = 'absent';

    /** @var string the record is there, but in another course */
    public const OTHER_COURSE = 'other_course';

    /** @var string[] the kinds of record that can be looked for */
    public const TYPES = ['course_module', 'forum_discussion', 'forum_post', 'quiz_attempt', 'grade_item', 'user', 'course_section'];

    /**
     * Look for a record.
     *
     * @param string $type one of TYPES
     * @param int $id id of the record
     * @param int $courseid course the history belongs to
     * @return string PRESENT, ABSENT or OTHER_COURSE (never for a user, who belongs to no course)
     * @throws \InvalidArgumentException for an unknown kind or an id that is not positive
     * @throws \dml_exception when the database cannot be read
     */
    public function locate(string $type, int $id, int $courseid): string {
        global $DB;
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("'{$type}' is not a kind of Moodle record the history refers to.");
        }
        if ($id <= 0 || $courseid <= 0) {
            throw new \InvalidArgumentException('The record and the course must be positive identifiers.');
        }

        $course = null;
        $present = false;
        switch ($type) {
            case 'course_module':
                $row = $DB->get_record('course_modules', ['id' => $id], 'id, course, deletioninprogress');
                $present = $row && !(int) $row->deletioninprogress;
                $course = $row ? (int) $row->course : null;
                break;
            case 'forum_discussion':
                $row = $DB->get_record('forum_discussions', ['id' => $id], 'id, course');
                $present = (bool) $row;
                $course = $row ? (int) $row->course : null;
                break;
            case 'forum_post':
                $row = $DB->get_record_sql(
                    'SELECT p.id, p.deleted, d.course FROM {forum_posts} p JOIN {forum_discussions} d ON d.id = p.discussion WHERE p.id = :id',
                    ['id' => $id]
                );
                $present = $row && !(int) $row->deleted;
                $course = $row ? (int) $row->course : null;
                break;
            case 'quiz_attempt':
                $row = $DB->get_record_sql(
                    'SELECT a.id, q.course FROM {quiz_attempts} a JOIN {quiz} q ON q.id = a.quiz WHERE a.id = :id',
                    ['id' => $id]
                );
                $present = (bool) $row;
                $course = $row ? (int) $row->course : null;
                break;
            case 'grade_item':
                $row = $DB->get_record('grade_items', ['id' => $id], 'id, courseid');
                $present = (bool) $row;
                $course = $row ? (int) $row->courseid : null;
                break;
            case 'user':
                $row = $DB->get_record('user', ['id' => $id], 'id, deleted');
                return $row && !(int) $row->deleted ? self::PRESENT : self::ABSENT;
            case 'course_section':
                $row = $DB->get_record('course_sections', ['id' => $id], 'id, course');
                $present = (bool) $row;
                $course = $row ? (int) $row->course : null;
                break;
        }
        if (!$present) {
            return self::ABSENT;
        }
        return $course === $courseid ? self::PRESENT : self::OTHER_COURSE;
    }
}
