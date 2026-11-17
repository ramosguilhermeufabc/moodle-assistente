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

use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\frequency_repository;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * CL-36 (T090; DEC-39): automatic completion through the real completion API of Moodle reaches the frequency.
 *
 * - completion by view: `completion_info::set_module_viewed()`, which the page of the activity calls;
 * - completion by grade to pass: a quiz with a passing grade and automatic completion, attempts submitted and graded by the quiz code;
 *   Moodle sets COMPLETION_COMPLETE_PASS or COMPLETION_COMPLETE_FAIL through the grade.
 * Each change emits `course_module_completion_updated`; the observer queues `participation_changed` with source `completion`, and the
 * real queue and frequency handler count the activity as participated, as DEC-39 D1 says (complete, passed or failed).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(participation_observer::class)]
final class completion_real_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $assign;

    /** @var \stdClass */
    private \stdClass $quiz;

    /**
     * Course with completion and the assistant, an assignment completed by view and a quiz completed by the passing grade.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enablecompletion = 1;
        $this->course = $this->getDataGenerator()->create_course([
            'enablecompletion' => 1, 'numsections' => 2, 'startdate' => time() - 2 * DAYSECS, 'enddate' => time() - 500,
        ]);
        (new config_repository())->create($this->course->id, true);
        $this->assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        ]);
        $this->quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id, 'questionsperpage' => 0, 'grade' => 10.0, 'sumgrades' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionusegrade' => 1, 'completionpassgrade' => 1,
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => \context_module::instance($this->quiz->cmid)->id]);
        quiz_add_quiz_question($generator->create_question('truefalse', null, ['category' => $category->id])->id, $this->quiz);
        $this->set_passing_grade(5.0);
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        foreach ($observers as $i => $observer) {
            $observers[$i] += ['includefile' => null, 'priority' => 0];
        }
        \core\event\manager::phpunit_replace_observers($observers);
    }

    /**
     * Set the passing grade of the quiz grade item.
     *
     * @param float $gradepass
     */
    private function set_passing_grade(float $gradepass): void {
        $item = \grade_item::fetch([
            'courseid' => $this->course->id, 'itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $this->quiz->id, 'itemnumber' => 0,
        ]);
        $item->gradepass = $gradepass;
        $item->update();
    }

    /**
     * Queued `participation_changed` events with the source `completion`, as [cmid, userid] pairs.
     *
     * @return array[]
     */
    private function queued(): array {
        global $DB;
        $rows = $DB->get_records('local_assistente_event', ['event_type' => event_type::PARTICIPATION_CHANGED->value], 'id ASC');
        $pairs = [];
        foreach ($rows as $row) {
            $payload = json_decode($row->payload, true);
            if ($payload['source'] === 'completion') {
                $pairs[] = [$payload['cmid'], $payload['userid']];
            }
        }
        return $pairs;
    }

    /**
     * Completion state that Moodle stores for the user in the module.
     *
     * @param \stdClass $module
     * @param string $modname
     * @param \stdClass $user
     * @return int
     */
    private function state(\stdClass $module, string $modname, \stdClass $user): int {
        $cm = get_coursemodule_from_id($modname, $module->cmid, $this->course->id, false, MUST_EXIST);
        return (int) (new \completion_info(get_course($this->course->id)))->get_data($cm, false, $user->id)->completionstate;
    }

    /**
     * The student answers the quiz and finishes it.
     *
     * @param \stdClass $student
     * @param string $answer '1' is the right answer of the true/false question
     */
    private function attempt(\stdClass $student, string $answer): void {
        $quizobj = quiz_settings::create($this->quiz->id, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions(time(), false, [1 => ['answer' => $answer]]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submit(time(), false);
        $attemptobj->process_grade_submission(time());
    }

    /**
     * The frequency of the student after the real queue ran: [cmid => participated].
     *
     * @param \stdClass $student
     * @return int[]
     */
    private function participated(\stdClass $student): array {
        (new queue_processor(null, handler_registry::default()))->run_cycle();
        $repository = new frequency_repository();
        $current = $repository->find_current_for_student((int) $this->course->id, (int) $student->id);
        $this->assertNotNull($current, 'The frequency of the student was calculated');
        $participated = [];
        foreach ($repository->find_frequencies((int) $current->id, (int) $student->id) as $frequency) {
            foreach ($repository->find_activities((int) $frequency->id) as $activity) {
                $participated[(int) $activity->activity_id] = (int) $activity->participated;
            }
        }
        return $participated;
    }

    /**
     * Automatic completion by view: Moodle completes the activity, emits the event, and the frequency counts the participation.
     */
    public function test_completion_by_view_reaches_the_frequency(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $cm = get_coursemodule_from_id('assign', $this->assign->cmid, $this->course->id, false, MUST_EXIST);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($this->assign, 'assign', $student));
        $this->assertSame([], $this->queued());

        (new \completion_info(get_course($this->course->id)))->set_module_viewed($cm, $student->id);

        $this->assertSame(COMPLETION_COMPLETE, $this->state($this->assign, 'assign', $student));
        $this->assertSame([[(int) $this->assign->cmid, (int) $student->id]], $this->queued());
        $this->assertSame(1, $this->participated($student)[(int) $this->assign->cmid] ?? null);
    }

    /**
     * Automatic completion by the passing grade: a passing attempt gives COMPLETE_PASS and a failing one COMPLETE_FAIL; both are
     * participation (DEC-39 D1).
     */
    public function test_passed_and_failed_completion_reach_the_frequency(): void {
        $passer = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $failer = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->attempt($passer, '1');
        $this->attempt($failer, '0');

        $this->assertSame(COMPLETION_COMPLETE_PASS, $this->state($this->quiz, 'quiz', $passer));
        $this->assertSame(COMPLETION_COMPLETE_FAIL, $this->state($this->quiz, 'quiz', $failer));
        $queued = $this->queued();
        $this->assertContains([(int) $this->quiz->cmid, (int) $passer->id], $queued);
        $this->assertContains([(int) $this->quiz->cmid, (int) $failer->id], $queued);
        $this->assertSame(1, $this->participated($passer)[(int) $this->quiz->cmid] ?? null, 'Passed counts');
        $this->assertSame(1, $this->participated($failer)[(int) $this->quiz->cmid] ?? null, 'Failed counts');
    }
}
