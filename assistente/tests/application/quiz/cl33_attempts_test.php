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

namespace local_assistente\application\quiz;

use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\questionnaire_reader;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use local_assistente\infrastructure\persistence\quiz_results_repository;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Technical validation CL-33 (T075), the part on attempts: what Moodle 5.2.4 does with real attempts, and what the results handler
 * makes of it. It records observed behaviour; it decides nothing. Q-011-07 (partial result of multiple choice) was decided on 26/10/2026: the last
 * test records the raw data that Moodle offers and what the reader takes from it.
 *
 * What is real and what is synthetic is said in each test: attempts, submission, grading, manual grading by process_submitted_actions
 * (the call comment.php makes) and the regrade of the overview report are the real code of Moodle; the pages themselves
 * (comment.php, the overview report screen) are not run.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class cl33_attempts_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass quiz instance with cmid */
    private \stdClass $quiz;

    /** @var \stdClass question category */
    private \stdClass $category;

    /** @var \stdClass[] questions by name */
    private array $questions = [];

    /** @var event_repository */
    private event_repository $events;

    /** @var quiz_results_repository */
    private quiz_results_repository $repository;

    /**
     * Course with the assistant enabled and a quiz of a true/false and a numerical question, with the initial analysis saved.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($this->course->id, true);
        $this->quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id, 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => 2,
        ]);
        $this->category = $this->qgen()->create_question_category(['contextid' => \context_module::instance($this->quiz->cmid)->id]);
        $this->questions['tf'] = $this->add_question('truefalse');
        $this->questions['num'] = $this->add_question('numerical');
        $this->events = new event_repository();
        $this->repository = new quiz_results_repository();
        $this->initial_analysis();
    }

    /**
     * The core_question generator.
     *
     * @return \core_question_generator
     */
    private function qgen(): \core_question_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_question');
    }

    /**
     * Add a question to the quiz.
     *
     * @param string $type
     * @param string|null $which
     * @return \stdClass
     */
    private function add_question(string $type, ?string $which = null): \stdClass {
        $question = $this->qgen()->create_question($type, $which, ['category' => $this->category->id]);
        quiz_add_quiz_question($question->id, $this->quiz);
        return $question;
    }

    /**
     * Save the initial analysis of the questions of the quiz.
     */
    private function initial_analysis(): void {
        (new quiz_initial_repository())->save(
            (int) $this->course->id,
            (int) $this->quiz->cmid,
            (int) $this->delivery(event_type::QUIZ_PUBLISHED, ['cmid' => (int) $this->quiz->cmid])[1]->id,
            0.5,
            array_map(
                static fn(\stdClass $q) => ['question_id' => (int) $q->id, 'question_version' => 1, 'complexity' => 0.5],
                array_values($this->questions)
            )
        );
    }

    /**
     * An event and its processing.
     *
     * @param event_type $type
     * @param array $payload
     * @return \stdClass[] event and processing
     */
    private function delivery(event_type $type, array $payload): array {
        static $occurred = 5000;
        if (isset($type->fields()['occurred_at'])) {
            $payload += ['occurred_at' => ++$occurred];
        }
        $event = $this->events->create_event((int) $this->course->id, $type, $payload, time());
        $this->events->begin_attempt((int) $event->id, time());
        return [$event, $this->events->find_processing((int) $event->id)];
    }

    /**
     * Run the results handler and return the stored analysis.
     *
     * @return \stdClass|null analysis, with the metric (`metric`) and the context (`context`) attached
     */
    private function recalculate(): ?\stdClass {
        [$event, $processing] = $this->delivery(event_type::QUIZ_ATTEMPT_REGRADED, ['cmid' => (int) $this->quiz->cmid, 'attemptid' => 1]);
        $result = (new results_handler())->handle($event, $processing);
        $this->assertTrue($result->success, (string) $result->response());
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $analysis->metric = $this->repository->find_metric((int) $analysis->id);
        $analysis->context = json_decode($this->events->find_context((int) $processing->id)->context_json, true);
        return $analysis;
    }

    /**
     * Start an attempt for a user, answer it and leave it in the state asked.
     *
     * @param \stdClass $user
     * @param int $number
     * @param array $answers answer by slot
     * @param bool $preview
     * @return quiz_attempt
     */
    private function start(\stdClass $user, int $number = 1, array $answers = [], bool $preview = false): quiz_attempt {
        $quizobj = quiz_settings::create($this->quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, $number, false, $timenow, $preview, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, $number, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        if ($answers) {
            $attemptobj->process_submitted_actions(time(), false, array_map(
                static fn($a) => is_array($a) ? $a : ['answer' => $a],
                $answers
            ));
            $attemptobj = quiz_attempt::create($attempt->id);
        }
        return $attemptobj;
    }

    /**
     * Submit and grade an attempt, as the quiz does when the student finishes it.
     *
     * @param quiz_attempt $attempt
     * @return quiz_attempt
     */
    private function finish(quiz_attempt $attempt): quiz_attempt {
        $attempt->process_submit(time(), false);
        $attempt->process_grade_submission(time());
        return quiz_attempt::create($attempt->get_attemptid());
    }

    /**
     * State of an attempt in the database.
     *
     * @param quiz_attempt $attempt
     * @return \stdClass
     */
    private function stored(quiz_attempt $attempt): \stdClass {
        global $DB;
        return $DB->get_record('quiz_attempts', ['id' => $attempt->get_attemptid()], '*', MUST_EXIST);
    }

    /**
     * States that Moodle gives an attempt, and which of them count. REAL: every transition is the mod_quiz code.
     */
    public function test_attempt_states_and_which_count(): void {
        $students = [];
        for ($i = 0; $i < 5; $i++) {
            $students[$i] = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        }
        $answers = [1 => '1', 2 => '3.14'];
        $finished = $this->finish($this->start($students[0], 1, $answers));
        $inprogress = $this->start($students[1], 1, $answers);
        $overdue = $this->start($students[2], 1, $answers);
        $overdue->process_going_overdue(time(), false);
        $abandoned = $this->start($students[3], 1, $answers);
        $abandoned->process_abandon(time(), false);
        $submitted = $this->start($students[4], 1, $answers);
        $submitted->process_submit(time(), false);
        $this->start($this->getDataGenerator()->create_user(), 1, $answers, true);

        $states = [];
        foreach ((new questionnaire_reader())->get_attempts((int) $this->quiz->cmid) as $attempt) {
            $states[$attempt->attempt_id] = [$attempt->state, $attempt->preview, $attempt->is_finished];
        }

        $this->assertSame(['finished', false, true], $states[(int) $finished->get_attemptid()]);
        $this->assertSame(['inprogress', false, false], $states[(int) $inprogress->get_attemptid()]);
        $this->assertSame(['overdue', false, false], $states[(int) $overdue->get_attemptid()]);
        $this->assertSame(['abandoned', false, false], $states[(int) $abandoned->get_attemptid()]);
        $this->assertSame(['submitted', false, false], $states[(int) $submitted->get_attemptid()], 'Submitted and not graded yet');
        $this->assertCount(1, array_filter($states, static fn($s) => $s[1]), 'One preview, flagged as such');

        $analysis = $this->recalculate();
        $this->assertSame(1, $analysis->context['attempts_considered'], 'Only the finished attempt of a student counts');
        $this->assertEquals(100.0, $analysis->metric->mean);
    }

    /**
     * Timeline of one attempt. REAL: submission and grading. After the submission the attempt is `submitted` and has no grade; the
     * event is already queued and a recalculation at that moment finds nothing; after the grading the attempt is `finished` with its
     * grade and the same recalculation counts it. This is why the queue running later is safe.
     */
    public function test_timeline_submit_event_then_grade(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $attempt = $this->start($student, 1, [1 => '1', 2 => '3.14']);

        $attempt->process_submit(time(), false);
        $afterSubmit = $this->stored($attempt);
        $queued = $DB->count_records('local_assistente_event', ['event_type' => 'quiz_attempt_submitted']);
        $early = $this->recalculate();
        $attempt->process_grade_submission(time());
        $afterGrade = $this->stored($attempt);
        $late = $this->recalculate();

        $this->assertSame('submitted', $afterSubmit->state);
        $this->assertNull($afterSubmit->sumgrades, 'No grade yet when the submit event is emitted');
        $this->assertSame(1, $queued, 'The event was emitted at submission');
        $this->assertNull($early->metric, 'Nothing to count yet: no_data');
        $this->assertSame('finished', $afterGrade->state);
        $this->assertEquals(2.0, (float) $afterGrade->sumgrades);
        $this->assertEquals(100.0, $late->metric->mean);
    }

    /**
     * Who is a student. REAL: roles and enrolments. A teacher, a user who is not enrolled and a student with a suspended enrolment
     * have finished attempts and none counts.
     */
    public function test_only_students_with_an_active_enrolment_count(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $suspended = $this->getDataGenerator()->create_and_enrol($this->course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $stranger = $this->getDataGenerator()->create_user();
        foreach ([$student, $teacher, $suspended, $stranger] as $user) {
            $this->finish($this->start($user, 1, [1 => '1', 2 => '3.14']));
        }

        $analysis = $this->recalculate();

        $this->assertSame(1, $analysis->context['attempts_considered']);
        $this->assertSame(1, $analysis->context['students_with_result']);
    }

    /**
     * Grading methods against the final grade that Moodle itself stores. REAL: the attempts and the grade calculator of the quiz (recompute_final_grade); one student with
     * attempts of 50% and 100%. The handler's final result, in percent, equals quiz_grades.grade over quiz.grade, for the four methods.
     */
    public function test_grading_methods_match_the_final_grade_of_moodle(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->finish($this->start($student, 1, [1 => '1', 2 => '9']));
        $this->finish($this->start($student, 2, [1 => '1', 2 => '3.14']));
        $methods = [QUIZ_GRADEHIGHEST => 'highest', QUIZ_GRADEAVERAGE => 'average', QUIZ_ATTEMPTFIRST => 'first', QUIZ_ATTEMPTLAST => 'last'];
        $expected = ['highest' => 100.0, 'average' => 75.0, 'first' => 50.0, 'last' => 100.0];

        foreach ($methods as $code => $name) {
            $DB->set_field('quiz', 'grademethod', $code, ['id' => $this->quiz->id]);
            $quiz = $DB->get_record('quiz', ['id' => $this->quiz->id], '*', MUST_EXIST);
            quiz_settings::create((int) $quiz->id)->get_grade_calculator()->recompute_final_grade((int) $student->id);
            $moodle = (float) $DB->get_field('quiz_grades', 'grade', ['quiz' => $quiz->id, 'userid' => $student->id]) / $quiz->grade * 100;

            $ours = (float) $this->recalculate()->metric->mean;

            $this->assertEqualsWithDelta($expected[$name], $moodle, 0.001, "Moodle, {$name}");
            $this->assertEqualsWithDelta($moodle, $ours, 0.001, "Handler against Moodle, {$name}");
        }
    }

    /**
     * Maximum grade 10 (CL-33, T075; DEC-36 D2). REAL: a quiz of grade 10 with three true/false questions of one mark, a student with two
     * attempts of 2/3 and 1/3, the grading method "first attempt" and the grade calculator of the quiz. The attempts, their sums and
     * the final grade are the ones of Moodle (grade 10, final 6.66667), and the percentage kept by the plugin is the truncated one
     * (66.666), not the rounded one (66.667).
     */
    public function test_quiz_with_maximum_grade_ten_matches_moodle_and_the_percentage_is_truncated(): void {
        global $DB;
        $this->quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id, 'questionsperpage' => 0, 'grade' => 10.0, 'sumgrades' => 3, 'grademethod' => QUIZ_ATTEMPTFIRST, 'attempts' => 0,
        ]);
        $this->category = $this->qgen()->create_question_category(['contextid' => \context_module::instance($this->quiz->cmid)->id]);
        $this->questions = ['a' => $this->add_question('truefalse'), 'b' => $this->add_question('truefalse'), 'c' => $this->add_question('truefalse')];
        $this->initial_analysis();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $first = $this->finish($this->start($student, 1, [1 => '1', 2 => '1', 3 => '0']));
        $second = $this->finish($this->start($student, 2, [1 => '1', 2 => '0', 3 => '0']));

        // What Moodle says.
        $quiz = $DB->get_record('quiz', ['id' => $this->quiz->id], '*', MUST_EXIST);
        $this->assertEquals(10.0, (float) $quiz->grade);
        $this->assertEquals(3.0, (float) $quiz->sumgrades);
        $attempts = quiz_get_user_attempts($quiz->id, $student->id, 'finished', true);
        $this->assertCount(2, $attempts, 'Two finished attempts');
        $this->assertEquals(2.0, (float) $this->stored($first)->sumgrades);
        $this->assertEquals(1.0, (float) $this->stored($second)->sumgrades);
        $this->assertEqualsWithDelta(6.66667, quiz_rescale_grade(2.0, $quiz, false), 1e-5, 'Grade of the first attempt out of 10');
        $this->assertEqualsWithDelta(3.33333, quiz_rescale_grade(1.0, $quiz, false), 1e-5, 'Grade of the second attempt out of 10');
        quiz_settings::create((int) $quiz->id)->get_grade_calculator()->recompute_final_grade((int) $student->id);
        $final = (float) $DB->get_field('quiz_grades', 'grade', ['quiz' => $quiz->id, 'userid' => $student->id], MUST_EXIST);
        $this->assertEqualsWithDelta(6.66667, $final, 1e-5, 'Final grade of Moodle (first attempt)');

        // What the plugin keeps: the percentage of the maximum grade, truncated to three decimals (DEC-36 D2).
        $metric = $this->recalculate()->metric;
        $this->assertEquals(66.666, (float) $metric->mean);
        $this->assertEqualsWithDelta($final / 10 * 100, (float) $metric->mean, 0.001, 'Agrees with Moodle within the truncation');
        $this->assertLessThan($final / 10 * 100, (float) $metric->mean, 'Truncated, not rounded up to 66.667');
    }

    /**
     * Manual grading. REAL: the attempt and process_submitted_actions with the manual grade data, the call that comment.php makes.
     * An essay waits (the attempt has no grade, so it does not count); after the grade the attempt has its grade and counts. FINDING:
     * that call emits no event of its own; question_manually_graded is triggered by the script comment.php (and by the grading report),
     * which is not run here.
     */
    public function test_manual_grading_real_call_and_its_events(): void {
        global $DB;
        $essay = $this->add_question('essay');
        (new quiz_initial_repository())->save(
            (int) $this->course->id,
            (int) $this->quiz->cmid,
            (int) $this->delivery(event_type::QUIZ_PUBLISHED, ['cmid' => (int) $this->quiz->cmid])[1]->id,
            0.5,
            array_map(
                static fn(\stdClass $q) => ['question_id' => (int) $q->id, 'question_version' => 1, 'complexity' => 0.5],
                [$this->questions['tf'], $this->questions['num'], $essay]
            )
        );
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $attempt = $this->finish($this->start($student, 1, [
            1 => '1', 2 => '3.14', 3 => ['answer' => 'Texto', 'answerformat' => FORMAT_HTML],
        ]));
        $before = $this->stored($attempt);
        $waiting = $this->recalculate();
        $eventsbefore = $DB->count_records('local_assistente_event', ['event_type' => 'quiz_attempt_manually_graded']);

        // The data of the comment form: the comment is a text with files, so it carries the id of its draft area.
        $quba = \question_engine::load_questions_usage_by_activity((int) $attempt->get_uniqueid());
        $post = $quba->prepare_simulated_post_data([
            3 => ['-comment' => 'Bom', '-commentformat' => FORMAT_HTML, '-mark' => 1.0, '-maxmark' => 1.0],
        ]);
        $post[$quba->get_field_prefix(3) . '-comment:itemid'] = file_get_unused_draft_itemid();
        $attempt->process_submitted_actions(time(), false, $post);
        $after = $this->stored($attempt);
        $graded = $this->recalculate();

        $this->assertNull($before->sumgrades, 'Finished, but the essay is waiting: no grade for the attempt');
        $this->assertNull($waiting->metric, 'So it does not count');
        $this->assertEquals(3.0, (float) $after->sumgrades, 'The manual grade completes the grade of the attempt');
        $this->assertEquals(100.0, $graded->metric->mean);
        $this->assertSame($eventsbefore, $DB->count_records('local_assistente_event', ['event_type' => 'quiz_attempt_manually_graded']),
            'process_submitted_actions emits no event: it comes from the script comment.php');
    }

    /**
     * Regrading. REAL: the regrade of the overview report (regrade_attempt, the method its screen calls), after the true/false question
     * is edited so that the stored answer becomes wrong. The event attempt_regraded is emitted and queued by the real code; the grade
     * of the attempt in the database is updated only afterwards, by the recompute step of the report (a window with the old grade). Repeated processing of one event writes one
     * analysis; every new execution writes another and none mixes the others.
     */
    public function test_regrade_real_method_emits_the_event_and_updates_the_grade(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/report/overview/report.php');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $attempt = $this->finish($this->start($student, 1, [1 => '1', 2 => '3.14']));
        $first = $this->recalculate();
        $this->assertEquals(100.0, $first->metric->mean);
        $known = $DB->get_fieldset_select('local_assistente_event', 'id', "event_type = 'quiz_attempt_regraded'");
        // The true/false question now has a different right answer, in a new version.
        $this->qgen()->update_question($this->questions['tf'], null, ['correctanswer' => 0]);

        $report = new \quiz_overview_report();
        $context = new \ReflectionProperty(\mod_quiz\local\reports\attempts_report::class, 'context');
        $context->setValue($report, \context_module::instance($this->quiz->cmid));
        $messages = $report->regrade_attempt($this->stored($attempt));
        [$notin, $params] = $DB->get_in_or_equal($known ?: [0], SQL_PARAMS_NAMED, 'known', false);
        $queued = array_values($DB->get_records_select('local_assistente_event', "event_type = 'quiz_attempt_regraded' AND id $notin", $params));
        $stored = $this->stored($attempt);

        $this->assertSame([], $messages, 'The regrade had no problem');
        $this->assertCount(1, $queued, 'The real regrade emitted attempt_regraded and the observer queued it');
        $this->assertSame((int) $attempt->get_attemptid(), json_decode($queued[0]->payload, true)['attemptid']);
        $quba = \question_engine::load_questions_usage_by_activity((int) $attempt->get_uniqueid());
        $this->assertEquals(1.0, $quba->get_total_mark(), 'The questions are regraded and saved when the event is emitted');
        $this->assertEquals(2.0, (float) $stored->sumgrades, 'FINDING: the grade of the attempt is still the old one at that moment');
        // A recalculation in that window is refused by the results handler (T075 follow-up): see results_handler_test.
        [$staleevent, $staleprocessing] = $this->delivery(event_type::QUIZ_ATTEMPT_REGRADED, ['cmid' => (int) $this->quiz->cmid, 'attemptid' => (int) $attempt->get_attemptid()]);
        $this->assertFalse((new results_handler())->handle($staleevent, $staleprocessing)->success, 'A recalculation in that window is refused');
        $this->assertNull($this->repository->find_by_processing((int) $staleprocessing->id));        // The overview report recomputes the grades of the attempts after the batch of regrades.
        quiz_settings::create((int) $this->quiz->id)->get_grade_calculator()->recompute_all_attempt_sumgrades();
        $this->assertEquals(1.0, (float) $this->stored($attempt)->sumgrades, 'Updated by the recompute step of the report');
        $second = $this->recalculate();
        $third = $this->recalculate();
        $this->assertEquals(50.0, $second->metric->mean);
        $this->assertNotSame((int) $second->id, (int) $third->id, 'Another execution is another analysis');
        $this->assertEquals($second->metric->mean, $third->metric->mean);
        $this->assertEquals(100.0, $this->repository->find_metric((int) $first->id)->mean, 'The first analysis is not changed');
        $this->assertCount(3, $this->repository->find_history((int) $this->course->id, (int) $this->quiz->cmid));
    }

    /**
     * Q-011-07: the raw data that Moodle offers for a multiple choice question with several right answers, and what the reader takes
     * from it. REAL: an attempt with one right and one wrong choice. The partial result itself is tested in results_handler_test.
     */
    public function test_raw_data_for_multiple_choice_with_several_correct_answers(): void {
        $multi = $this->qgen()->create_question('multichoice', 'two_of_four', ['category' => $this->category->id]);
        quiz_add_quiz_question($multi->id, $this->quiz);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $attempt = $this->start($student);
        $quba = \question_engine::load_questions_usage_by_activity((int) $attempt->get_uniqueid());
        $slot = 3;
        $question = $quba->get_question($slot);
        $order = $question->get_order($quba->get_question_attempt($slot));
        $right = $wrong = null;
        foreach ($order as $position => $answerid) {
            $fraction = (float) $question->answers[$answerid]->fraction;
            $right ??= $fraction > 0 ? $position : null;
            $wrong ??= $fraction <= 0 ? $position : null;
        }
        $attempt->process_submitted_actions(time(), false, [1 => ['answer' => '1'], 2 => ['answer' => '3.14']]);
        $attempt = quiz_attempt::create($attempt->get_attemptid());
        $qa3 = $attempt->get_question_attempt($slot);
        $attempt->process_submitted_actions(time(), false, [
            'slots' => (string) $slot,
            $qa3->get_field_prefix() . ':sequencecheck' => $qa3->get_sequence_check_count(),
            $qa3->get_field_prefix() . "choice{$right}" => 1,
            $qa3->get_field_prefix() . "choice{$wrong}" => 1,
        ]);
        $attempt = quiz_attempt::create($attempt->get_attemptid());
        $this->finish($attempt);
        $quba = \question_engine::load_questions_usage_by_activity((int) $attempt->get_uniqueid());
        $qa = $quba->get_question_attempt($slot);

        $fractions = array_map(static fn($a) => (float) $a->fraction, $quba->get_question($slot)->answers);
        [$numright, $numtotal] = $quba->get_question($slot)->get_num_parts_right($qa->get_last_qt_data());
        $reader = array_values(array_filter(
            (new questionnaire_reader())->get_attempt_questions($attempt->get_attemptid()),
            static fn($i) => $i->slot === $slot
        ))[0];

        $this->assertEqualsCanonicalizing([0.5, 0.5, 0.0, 0.0], array_values($fractions), 'Fractions of the choices: two right (0.5 each) and two wrong');
        $this->assertSame(1, $numright, 'get_num_parts_right: choices selected that are not wrong');
        $this->assertSame(4, $numtotal, 'get_num_parts_right: the total is the number of CHOICES, not of right choices');
        $this->assertEquals(0.5, $reader->fraction, 'One right and one wrong choice: the fraction of the question');
        $this->assertSame(
            ['correct_total' => 2, 'selected_correct' => 1, 'selected_incorrect' => 1, 'recorded' => true],
            $reader->choices,
            'The reader gives the alternatives of the attempt: correct ones, selected correct, selected incorrect (decided in Q-011-07)'
        );        $this->assertTrue($reader->graded);
    }
}
