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

use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the participation reader (DEC-25, DEC-39 D1 and D3).
 *
 * The fixtures for the quiz attempt and the assignment submission have not been run; completion states,
 * submission status and last access in a running Moodle 5.2 are validated by CL-36 (T032).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(participation_reader::class)]
final class participation_reader_test extends \advanced_testcase {
    /**
     * A student with no signal has not participated, and every flag is false.
     */
    public function test_no_signal_means_no_participation(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);

        $result = (new participation_reader())->get_participation($course->id, (int) $student->id, $assign->cmid);

        $this->assertFalse($result->participated);
        $this->assertSame([], $result->signals);
        $this->assertFalse($result->has_submission);
        $this->assertFalse($result->has_grade);
        $this->assertFalse($result->completion_enabled);
        $this->assertNull($result->completion_state);
        $this->assertNull((new participation_reader())->get_participation($course->id, (int) $student->id, 987654));
    }

    /**
     * A submitted assignment counts; a draft does not.
     */
    public function test_submitted_assignment_counts_and_draft_does_not(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $submitter = $generator->create_and_enrol($course, 'student');
        $drafter = $generator->create_and_enrol($course, 'student');
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        foreach ([[$submitter, 'submitted'], [$drafter, 'draft']] as [$user, $status]) {
            $DB->insert_record('assign_submission', (object) [
                'assignment' => $assign->id, 'userid' => $user->id, 'timecreated' => time(), 'timemodified' => time(),
                'status' => $status, 'groupid' => 0, 'attemptnumber' => 0, 'latest' => 1,
            ]);
        }
        $reader = new participation_reader();

        $yes = $reader->get_participation($course->id, (int) $submitter->id, $assign->cmid);
        $no = $reader->get_participation($course->id, (int) $drafter->id, $assign->cmid);

        $this->assertTrue($yes->participated);
        $this->assertSame(['submission'], $yes->signals);
        $this->assertFalse($no->participated);
    }

    /**
     * A grade counts as participation.
     */
    public function test_grade_counts(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->id, 'itemnumber' => 0]);
        $item->update_final_grade($student->id, 55.0, 'test');

        $result = (new participation_reader())->get_participation($course->id, (int) $student->id, $assign->cmid);

        $this->assertTrue($result->has_grade);
        $this->assertContains('grade', $result->signals);
    }

    /**
     * Forum postings count.
     */
    public function test_forum_post_counts(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $poster = $generator->create_and_enrol($course, 'student');
        $silent = $generator->create_and_enrol($course, 'student');
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $poster->id,
        ]);
        $reader = new participation_reader();

        $this->assertTrue($reader->get_participation($course->id, (int) $poster->id, $forum->cmid)->has_post);
        $this->assertSame(['post'], $reader->get_participation($course->id, (int) $poster->id, $forum->cmid)->signals);
        $this->assertFalse($reader->get_participation($course->id, (int) $silent->id, $forum->cmid)->participated);
    }

    /**
     * Completion states: complete, passed and failed count; incomplete does not.
     */
    public function test_completion_states(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $done = $generator->create_and_enrol($course, 'student');
        $notdone = $generator->create_and_enrol($course, 'student');
        $page = $generator->create_module('page', [
            'course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $cm = get_fast_modinfo($course->id)->get_cm($page->cmid);
        (new \completion_info($course))->update_state($cm, COMPLETION_COMPLETE, $done->id);
        $reader = new participation_reader();

        $yes = $reader->get_participation($course->id, (int) $done->id, $page->cmid);
        $no = $reader->get_participation($course->id, (int) $notdone->id, $page->cmid);

        $this->assertTrue($yes->completion_enabled);
        $this->assertSame('complete', $yes->completion_state);
        $this->assertTrue($yes->participated);
        $this->assertSame('incomplete', $no->completion_state);
        $this->assertFalse($no->participated);
    }

    /**
     * A finished attempt counts; an attempt in progress does not.
     */
    public function test_finished_attempt_counts(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $finisher = $generator->create_and_enrol($course, 'student');
        $starter = $generator->create_and_enrol($course, 'student');
        $quiz = $generator->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 1]);
        $context = \context_module::instance($quiz->cmid);
        $category = $generator->get_plugin_generator('core_question')->create_question_category(['contextid' => $context->id]);
        $question = $generator->get_plugin_generator('core_question')->create_question('numerical', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $attempts = [];
        foreach ([$finisher, $starter] as $user) {
            $quizobj = quiz_settings::create($quiz->id, $user->id);
            $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
            $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
            $timenow = time();
            $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
            quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
            quiz_attempt_save_started($quizobj, $quba, $attempt);
            $attempts[$user->id] = quiz_attempt::create($attempt->id);
        }
        $attempts[$finisher->id]->process_submitted_actions(time(), false, [1 => ['answer' => '3.14']]);
        quiz_attempt::create($attempts[$finisher->id]->get_attemptid())->process_finish(time(), false);
        $reader = new participation_reader();

        $this->assertTrue($reader->get_participation($course->id, (int) $finisher->id, $quiz->cmid)->has_finished_attempt);
        $this->assertFalse($reader->get_participation($course->id, (int) $starter->id, $quiz->cmid)->has_finished_attempt);
    }

    /**
     * Last access to the course, as recorded by Moodle; never accessed gives null.
     */
    public function test_last_course_access(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $visited = $generator->create_and_enrol($course, 'student');
        $never = $generator->create_and_enrol($course, 'student');
        $DB->insert_record('user_lastaccess', (object) ['userid' => $visited->id, 'courseid' => $course->id, 'timeaccess' => 1900000000]);
        $reader = new participation_reader();

        $this->assertSame(1900000000, $reader->get_last_course_access($course->id, (int) $visited->id));
        $this->assertNull($reader->get_last_course_access($course->id, (int) $never->id));
    }

    /**
     * The grade can be handed in already read (A2): the same answer as when the reader reads it, with a grade, with none, and when the
     * grade is not handed in at all.
     */
    public function test_a_grade_handed_in_gives_the_same_participation(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $graded = $generator->create_and_enrol($course, 'student');
        $ungraded = $generator->create_and_enrol($course, 'student');
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assign->id, 'itemnumber' => 0]);
        $item->update_final_grade($graded->id, 80.0, 'test');
        $gradebook = new gradebook_reader();
        $reader = new participation_reader($gradebook);

        foreach ([$graded, $ungraded] as $student) {
            $read = $reader->get_participation($course->id, (int) $student->id, $assign->cmid);
            $known = $gradebook->get_final_grades($course->id, $assign->cmid, [(int) $student->id])[(int) $student->id];
            $this->assertEquals($read, $reader->get_participation($course->id, (int) $student->id, $assign->cmid, $known));
        }
        $this->assertTrue($reader->get_participation($course->id, (int) $graded->id, $assign->cmid)->has_grade);
        $this->assertFalse($reader->get_participation($course->id, (int) $ungraded->id, $assign->cmid, null)->has_grade);
    }}
