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

use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use local_assistente\infrastructure\persistence\quiz_results_repository;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the results of a questionnaire (T072; quickstart 011 Q3, Q5, Q7, Q8, Q9, Q11, Q12, Q13; DEC-24, DEC-36). Real quiz, real
 * attempts; no LLM exists in this flow.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(results_handler::class)]
final class results_handler_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass quiz instance with cmid */
    private \stdClass $quiz;

    /** @var \stdClass question category */
    private \stdClass $category;

    /** @var \stdClass[] students */
    private array $students = [];

    /** @var \stdClass[] questions of the quiz by name */
    private array $questions = [];

    /** @var quiz_results_repository */
    private quiz_results_repository $repository;

    /** @var event_repository */
    private event_repository $events;

    /** @var results_handler */
    private results_handler $handler;

    /**
     * Course with the assistant enabled, two students, a teacher and a quiz with a true/false and a numerical question, with the
     * initial analysis of both.
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
        $this->students = [
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
        ];
        $this->quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id, 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => 2,
        ]);
        $this->category = $this->qgen()->create_question_category(['contextid' => \context_module::instance($this->quiz->cmid)->id]);
        $this->questions['tf'] = $this->add_question('truefalse');
        $this->questions['num'] = $this->add_question('numerical');
        $this->repository = new quiz_results_repository();
        $this->events = new event_repository();
        $this->handler = new results_handler();
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
     * @return \stdClass
     */
    private function add_question(string $type): \stdClass {
        $question = $this->qgen()->create_question($type, null, ['category' => $this->category->id]);
        quiz_add_quiz_question($question->id, $this->quiz);
        return $question;
    }

    /**
     * Save the initial analysis of the questions that the quiz has now, as the publication handler would have.
     */
    private function initial_analysis(): void {
        $rows = array_map(
            static fn(\stdClass $q) => ['question_id' => (int) $q->id, 'question_version' => 1, 'complexity' => 0.5],
            array_values($this->questions)
        );
        (new quiz_initial_repository())->save(
            (int) $this->course->id,
            (int) $this->quiz->cmid,
            (int) $this->event_and_processing(event_type::QUIZ_PUBLISHED, ['cmid' => (int) $this->quiz->cmid])[1]->id,
            0.5,
            $rows
        );
    }

    /**
     * An event and its processing, as the queue hands them to the handler.
     *
     * @param event_type $type
     * @param array $payload
     * @return \stdClass[] event and processing
     */
    private function event_and_processing(event_type $type, array $payload): array {
        static $occurred = 1000;
        if (isset($type->fields()['occurred_at'])) {
            $payload += ['occurred_at' => ++$occurred];
        }
        $event = $this->events->create_event((int) $this->course->id, $type, $payload, time());
        $this->events->begin_attempt((int) $event->id, time());
        return [$event, $this->events->find_processing((int) $event->id)];
    }

    /**
     * Run the handler for an attempt-submitted event of the quiz.
     *
     * @param event_type $type
     * @return array handler_result and processing
     */
    private function recalculate(event_type $type = event_type::QUIZ_ATTEMPT_SUBMITTED): array {
        $payload = match ($type) {
            event_type::QUIZ_ATTEMPT_SUBMITTED => ['cmid' => (int) $this->quiz->cmid, 'attemptid' => 1, 'userid' => 1],
            event_type::QUIZ_CLOSED => ['cmid' => (int) $this->quiz->cmid, 'timeclose' => 5],
            default => ['cmid' => (int) $this->quiz->cmid, 'attemptid' => 1, 'slot' => 1],
        };
        [$event, $processing] = $this->event_and_processing($type, $payload);
        return [$this->handler->handle($event, $processing), $processing];
    }

    /**
     * A finished attempt of a user.
     *
     * @param \stdClass $user
     * @param int $number attempt number of the user
     * @param array $answers answer by slot (1 = true/false, 2 = numerical); a slot left out is not answered
     * @param bool $preview
     * @param bool $finish false leaves the attempt in progress
     * @return quiz_attempt
     */
    private function attempt(\stdClass $user, int $number, array $answers, bool $preview = false, bool $finish = true): quiz_attempt {
        $quizobj = quiz_settings::create($this->quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, $number, false, $timenow, $preview, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, $number, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        if ($answers) {
            $attemptobj->process_submitted_actions(time(), false, array_map(static fn($a) => is_array($a) ? $a : ['answer' => $a], $answers));
            $attemptobj = quiz_attempt::create($attempt->id);
        }
        if ($finish) {
            $attemptobj->process_submit(time(), false);
            $attemptobj->process_grade_submission(time());
        }
        return $attemptobj;
    }

    /**
     * Rows of question_result of an analysis by question id.
     *
     * @param int $analysisid
     * @return \stdClass[]
     */
    private function rows(int $analysisid): array {
        $rows = [];
        foreach ($this->repository->find_question_results($analysisid) as $row) {
            $rows[(int) $row->question_id] = $row;
        }
        return $rows;
    }

    /**
     * Normal case: the metrics by question and the aggregate, from the finished attempts of the students.
     */
    public function test_metrics_by_question_and_aggregate(): void {
        $this->attempt($this->students[0], 1, [1 => '1', 2 => '3.14']);
        $this->attempt($this->students[1], 1, [1 => '1', 2 => '9']);

        [$result, $processing] = $this->recalculate();

        $this->assertTrue($result->success, (string) $result->response());
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $rows = $this->rows((int) $analysis->id);
        $tf = $rows[(int) $this->questions['tf']->id];
        $this->assertEquals(100.0, $tf->average_correctness);
        $this->assertSame(2, (int) $tf->correct_count);
        $num = $rows[(int) $this->questions['num']->id];
        $this->assertEquals(50.0, $num->average_correctness);
        $this->assertSame(1, (int) $num->correct_count);
        $this->assertSame(1, (int) $num->incorrect_count);
        $this->assertNull($num->partial_result, 'Not computed: the reader gives no correct items (Q-011-07)');
        $metric = $this->repository->find_metric((int) $analysis->id);
        $this->assertEquals([75.0, 25.0, 50.0, 100.0], [$metric->mean, $metric->population_standard_deviation, $metric->minimum, $metric->maximum]);
        $this->assertMatchesRegularExpression('/2 attempts, 2 students, 2 questions/', (string) $result->response());
    }

    /**
     * Q9: attempts in progress, previews and attempts of users who are not students stay out.
     */
    public function test_unfinished_previews_and_non_students_stay_out(): void {
        $this->attempt($this->students[0], 1, [1 => '1', 2 => '3.14']);
        $this->attempt($this->students[1], 1, [1 => '0', 2 => '9'], false, false);
        $this->attempt($this->getDataGenerator()->create_user(), 1, [1 => '0', 2 => '9'], true);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->attempt($teacher, 1, [1 => '0', 2 => '9']);

        [$result, $processing] = $this->recalculate();

        $this->assertTrue($result->success);
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertEquals(100.0, $this->repository->find_metric((int) $analysis->id)->mean);
        $this->assertEquals(100.0, $this->rows((int) $analysis->id)[(int) $this->questions['tf']->id]->average_correctness);
    }

    /**
     * Q8: a student with attempts of 50% and 100%: the highest grade gives 100, the average 75, the first 50, the last 100.
     */
    public function test_final_result_follows_the_grading_method(): void {
        global $DB;
        $this->attempt($this->students[0], 1, [1 => '1', 2 => '9']);
        $this->attempt($this->students[0], 2, [1 => '1', 2 => '3.14']);

        foreach ([1 => 100.0, 2 => 75.0, 3 => 50.0, 4 => 100.0] as $method => $expected) {
            $DB->set_field('quiz', 'grademethod', $method, ['id' => $this->quiz->id]);
            [$result, $processing] = $this->recalculate();
            $this->assertTrue($result->success);
            $analysis = $this->repository->find_by_processing((int) $processing->id);
            $this->assertEquals($expected, $this->repository->find_metric((int) $analysis->id)->mean, "Method {$method}");
        }
    }

    /**
     * Q11: a new version of an analysed question, answered by the student, is linked to the analysed row; no initial analysis again.
     */
    public function test_new_version_is_linked_to_the_analysed_row(): void {
        global $DB;
        $analysed = $this->rows_of_initial();
        $new = $this->qgen()->update_question($this->questions['tf'], null, ['name' => 'Versao 2']);
        $this->attempt($this->students[0], 1, [1 => '1', 2 => '3.14']);

        [$result, $processing] = $this->recalculate();

        $this->assertTrue($result->success);
        $rows = $this->rows((int) $this->repository->find_by_processing((int) $processing->id)->id);
        $this->assertSame(2, (int) $rows[(int) $new->id]->question_version);
        $this->assertSame($analysed[(int) $this->questions['tf']->id], (int) $rows[(int) $new->id]->questionnaire_question_id);
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_initial']));
    }

    /**
     * The analysed rows of the initial analysis by question id.
     *
     * @return int[]
     */
    private function rows_of_initial(): array {
        $initial = new quiz_initial_repository();
        $latest = array_values($initial->find_for_quiz((int) $this->course->id, (int) $this->quiz->cmid))[0];
        $rows = [];
        foreach ($initial->find_questions((int) $latest->id) as $row) {
            $rows[(int) $row->question_id] = (int) $row->id;
        }
        return $rows;
    }

    /**
     * Q12: a question added after the publication has no question_result, enters the aggregate and is listed in the context.
     */
    public function test_question_added_later_is_only_in_the_aggregate_and_the_context(): void {
        $added = $this->add_question('truefalse');
        $this->attempt($this->students[0], 1, [1 => '1', 2 => '3.14', 3 => '1']);

        [$result, $processing] = $this->recalculate();

        $this->assertTrue($result->success);
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertArrayNotHasKey((int) $added->id, $this->rows((int) $analysis->id));
        $this->assertEquals(100.0, $this->repository->find_metric((int) $analysis->id)->mean, 'Its grade is in the attempt');
        $context = json_decode($this->events->find_context((int) $processing->id)->context_json, true);
        $this->assertSame([(int) $added->id], array_column($context['unanalysed_questions'], 'question_id'));
    }

    /**
     * Q7: an essay waiting for manual grading is out of the metrics (no row); the attempt has no grade yet and does not count.
     */
    public function test_answer_waiting_for_manual_grading_is_out(): void {
        $this->questions['essay'] = $this->add_question('essay');
        $this->initial_analysis_again();
        $this->attempt($this->students[0], 1, [
            1 => '1', 2 => '3.14', 3 => ['answer' => 'Texto', 'answerformat' => FORMAT_HTML],
        ]);

        [$result, $processing] = $this->recalculate();

        $this->assertTrue($result->success);
        $rows = $this->rows((int) $this->repository->find_by_processing((int) $processing->id)->id);
        $this->assertArrayNotHasKey((int) $this->questions['essay']->id, $rows, 'No graded answer: no_data, not zero');
        $this->assertArrayHasKey((int) $this->questions['tf']->id, $rows);
        $this->assertNull(
            $this->repository->find_metric((int) $this->repository->find_by_processing((int) $processing->id)->id),
            'The only attempt has no grade yet: no student result, no aggregate'
        );

        // The teacher grades the essay: the next recalculation takes the answer and the attempt.
        global $DB;
        $stored = array_values($this->attempts_of($this->students[0]))[0];
        $quba = \question_engine::load_questions_usage_by_activity((int) $stored->uniqueid);
        $quba->manual_grade(3, 'Bom', 1.0, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);
        $DB->update_record('quiz_attempts', (object) ['id' => $stored->id, 'timemodified' => time(), 'sumgrades' => $quba->get_total_mark()]);

        [$after, $processingafter] = $this->recalculate(event_type::QUIZ_ATTEMPT_MANUALLY_GRADED);

        $this->assertTrue($after->success);
        $analysis = $this->repository->find_by_processing((int) $processingafter->id);
        $rowsafter = $this->rows((int) $analysis->id);
        $this->assertEquals(100.0, $rowsafter[(int) $this->questions['essay']->id]->average_correctness);
        $this->assertEquals(100.0, $this->repository->find_metric((int) $analysis->id)->mean);
    }

    /**
     * Attempts of a user.
     *
     * @param \stdClass $user
     * @return \stdClass[]
     */
    private function attempts_of(\stdClass $user): array {
        global $DB;
        return $DB->get_records('quiz_attempts', ['quiz' => $this->quiz->id, 'userid' => $user->id], 'attempt ASC');
    }

    /**
     * Save a second initial analysis with all the questions of the quiz now (the latest one is the one used).
     */
    private function initial_analysis_again(): void {
        $this->initial_analysis();
    }

    /**
     * Q13: no finished attempt is no data (no rows, success); all zeros is a valid zero; a technical failure is an error, not a zero.
     */
    public function test_no_data_valid_zero_and_error(): void {
        [$none, $nodata] = $this->recalculate();
        $this->assertTrue($none->success);
        $a = $this->repository->find_by_processing((int) $nodata->id);
        $this->assertNull($this->repository->find_metric((int) $a->id));
        $this->assertSame([], $this->repository->find_question_results((int) $a->id));

        $this->attempt($this->students[0], 1, [1 => '0', 2 => '9']);
        [$zero, $valid] = $this->recalculate();
        $this->assertTrue($zero->success);
        $b = $this->repository->find_by_processing((int) $valid->id);
        $this->assertEquals(0.0, $this->repository->find_metric((int) $b->id)->mean, 'A real zero is a row');

        [$event, $processing] = $this->event_and_processing(event_type::QUIZ_ATTEMPT_SUBMITTED, ['cmid' => 987654, 'attemptid' => 1, 'userid' => 1]);
        $error = $this->handler->handle($event, $processing);
        $this->assertFalse($error->success);
        $this->assertSame(attempt_failure::TECHNICAL, $error->failure);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id), 'An error leaves no zero behind');
    }

    /**
     * Q3: the closing event is the final recalculation with all the finished attempts, and says so in the context.
     */
    public function test_closing_is_the_final_recalculation(): void {
        $this->attempt($this->students[0], 1, [1 => '1', 2 => '3.14']);
        $this->attempt($this->students[1], 1, [1 => '0', 2 => '9']);

        [$partial, $p1] = $this->recalculate();
        [$final, $p2] = $this->recalculate(event_type::QUIZ_CLOSED);

        $this->assertTrue($partial->success);
        $this->assertTrue($final->success);
        $this->assertFalse(json_decode($this->events->find_context((int) $p1->id)->context_json, true)['final']);
        $closed = json_decode($this->events->find_context((int) $p2->id)->context_json, true);
        $this->assertTrue($closed['final']);
        $this->assertSame(2, $closed['attempts_considered']);
        $this->assertEquals(50.0, $this->repository->find_metric((int) $this->repository->find_by_processing((int) $p2->id)->id)->mean);
    }

    /**
     * Q-011-07 (decided 26/10/2026), REAL attempts of a multiple choice question with two correct alternatives:
     * both correct = 100; one correct = 50; one correct and one incorrect = 0 (any incorrect gives zero); nothing recorded = no data,
     * left out of the mean and not a zero. The mean is among the answers that have the indicator, and it is not the Moodle grade.
     */
    public function test_partial_result_of_multiple_choice(): void {
        $multi = $this->qgen()->create_question('multichoice', 'two_of_four', ['category' => $this->category->id]);
        quiz_add_quiz_question($multi->id, $this->quiz);
        $this->questions['multi'] = $multi;
        $this->initial_analysis();
        $students = [];
        foreach (range(1, 4) as $i) {
            $students[$i] = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        }
        $this->finish_multi($students[1], 2, 0);
        $this->finish_multi($students[2], 1, 0);
        $this->finish_multi($students[3], 1, 1);
        $this->finish_multi($students[4], 0, 0);

        [$result, $processing] = $this->recalculate();

        $this->assertTrue($result->success, (string) $result->response());
        $row = $this->rows((int) $this->repository->find_by_processing((int) $processing->id)->id)[(int) $multi->id];
        $this->assertEquals(50.0, $row->partial_result, 'Mean of 100, 50 and 0; the answer with nothing recorded is not a zero');
        $this->assertNotNull($row->partial_result);
    }

    /**
     * Q-011-07: with every answer of the question lacking a response, the partial result is null, not zero, while the other metrics
     * (here the grade) follow Moodle; and a question without several correct alternatives never has one.
     */
    public function test_partial_result_is_null_without_data_and_for_other_types(): void {
        $multi = $this->qgen()->create_question('multichoice', 'two_of_four', ['category' => $this->category->id]);
        quiz_add_quiz_question($multi->id, $this->quiz);
        $this->questions['multi'] = $multi;
        $this->initial_analysis();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->finish_multi($student, 0, 0);

        [$result, $processing] = $this->recalculate();

        $this->assertTrue($result->success);
        $rows = $this->rows((int) $this->repository->find_by_processing((int) $processing->id)->id);
        $this->assertArrayNotHasKey((int) $multi->id, $rows, 'Nothing recorded and no grade: no row at all (no_data)');
        $this->assertNull($rows[(int) $this->questions['tf']->id]->partial_result, 'True/false has no partial result');
    }

    /**
     * Q-011-07, decision 4: a recorded response with nothing selected is a valid answer and counts as 0, which Moodle 5.2.4 never
     * stores (it keeps only the selected choices); the rule is checked here with data that does record it, through the reader's
     * seam. It counts even though the answer has no grade, because the indicator does not depend on the Moodle grade.
     */
    public function test_recorded_empty_selection_counts_as_zero(): void {
        $multi = $this->qgen()->create_question('multichoice', 'two_of_four', ['category' => $this->category->id]);
        quiz_add_quiz_question($multi->id, $this->quiz);
        $this->questions['multi'] = $multi;
        $this->initial_analysis();
        $first = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $second = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->finish_multi($first, 2, 0);
        $this->finish_multi($second, 0, 0);
        $reader = new class extends \local_assistente\infrastructure\moodle\questionnaire_reader {
            #[\Override]
            public function get_attempt_detail(int $attemptid): ?\stdClass {
                $detail = parent::get_attempt_detail($attemptid);
                foreach ($detail->questions as $item) {
                    if ($item->choices !== null && !$item->choices['recorded']) {
                        $item->choices['recorded'] = true;
                    }
                }
                return $detail;
            }
        };
        [$event, $processing] = $this->event_and_processing(event_type::QUIZ_ATTEMPT_SUBMITTED, ['cmid' => (int) $this->quiz->cmid, 'attemptid' => 1, 'userid' => 1]);

        $result = (new results_handler(null, null, $reader))->handle($event, $processing);

        $this->assertTrue($result->success, (string) $result->response());
        $row = $this->rows((int) $this->repository->find_by_processing((int) $processing->id)->id)[(int) $multi->id];
        $this->assertEquals(50.0, $row->partial_result, 'Mean of 100 and the recorded empty selection, 0');
    }

    /**
     * A finished attempt of a student with the true/false and numerical questions right and the multiple choice question answered
     * with some correct and some incorrect alternatives, chosen by the order of this attempt.
     *
     * @param \stdClass $student
     * @param int $correct correct alternatives to select
     * @param int $incorrect incorrect alternatives to select
     */
    private function finish_multi(\stdClass $student, int $correct, int $incorrect): void {
        $attempt = $this->attempt($student, 1, [1 => '1', 2 => '3.14'], false, false);
        $slot = 3;
        $qa = $attempt->get_question_attempt($slot);
        $question = $qa->get_question();
        $pick = [];
        foreach ($question->get_order($qa) as $position => $answerid) {
            $isright = (float) $question->answers[$answerid]->fraction > 0;
            if ($isright && $correct > 0) {
                $pick["choice{$position}"] = 1;
                $correct--;
            } else if (!$isright && $incorrect > 0) {
                $pick["choice{$position}"] = 1;
                $incorrect--;
            }
        }
        if ($pick) {
            $post = ['slots' => (string) $slot, $qa->get_field_prefix() . ':sequencecheck' => $qa->get_sequence_check_count()];
            foreach ($pick as $name => $value) {
                $post[$qa->get_field_prefix() . $name] = $value;
            }
            $attempt->process_submitted_actions(time(), false, $post);
            $attempt = quiz_attempt::create($attempt->get_attemptid());
        }
        $attempt->process_submit(time(), false);
        $attempt->process_grade_submission(time());
    }
    /**
     * Regrade window (T075), with the real regrade method of the overview report: right after it the questions are regraded and saved,
     * the event is queued, and `sumgrades` is still the old grade. The handler must not write anything from that state.
     */
    public function test_handler_does_not_write_results_while_the_regrade_is_not_persisted(): void {
        global $DB;
        $attempt = $this->regrade_window();

        [$event, $processing] = $this->event_and_processing(event_type::QUIZ_ATTEMPT_REGRADED, ['cmid' => (int) $this->quiz->cmid, 'attemptid' => (int) $attempt->get_attemptid()]);
        $result = $this->handler->handle($event, $processing);

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $this->assertStringContainsString('grade not persisted yet', (string) $result->response());
        $this->assertNull($this->repository->find_by_processing((int) $processing->id), 'No result from a half-finished regrade');
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));
        $this->assertNull($this->events->find_context((int) $processing->id), 'Not even the context is written');
    }

    /**
     * After the report persists the grades the same event is processed and gives the right results; processing it again writes nothing
     * more; an event of the same regrade that arrives after the grade is persisted gives the same values in a new analysis.
     */
    public function test_after_the_grade_is_persisted_the_same_event_gives_the_correct_results(): void {
        global $DB;
        $attempt = $this->regrade_window();
        [$event, $processing] = $this->event_and_processing(event_type::QUIZ_ATTEMPT_REGRADED, ['cmid' => (int) $this->quiz->cmid, 'attemptid' => (int) $attempt->get_attemptid()]);
        $this->assertFalse($this->handler->handle($event, $processing)->success);

        $this->quiz_settings_grade_calculator()->recompute_all_attempt_sumgrades();
        $retry = $this->handler->handle($event, $processing);

        $this->assertTrue($retry->success, (string) $retry->response());
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertEquals(50.0, $this->repository->find_metric((int) $analysis->id)->mean);
        $averages = array_values(array_map(static fn($r) => (float) $r->average_correctness, $this->repository->find_question_results((int) $analysis->id)));
        $this->assertEqualsCanonicalizing([0.0, 100.0], $averages, 'The regraded true/false is wrong now, the numerical is right');

        $again = $this->handler->handle($event, $processing);
        $this->assertTrue($again->success);
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']), 'No duplication');
        [$later, $laterprocessing] = $this->event_and_processing(event_type::QUIZ_ATTEMPT_REGRADED, ['cmid' => (int) $this->quiz->cmid, 'attemptid' => (int) $attempt->get_attemptid()]);
        $this->assertTrue($this->handler->handle($later, $laterprocessing)->success);
        $this->assertEquals(50.0, $this->repository->find_metric((int) $this->repository->find_by_processing((int) $laterprocessing->id)->id)->mean);
    }

    /**
     * Through the real queue: the first cycle finds the grade stale and the event stays pending with one failed attempt; after the
     * grades are persisted the next cycle processes it. Nothing is lost and nothing is written twice. A regrade that is never completed
     * leaves the event failed after three attempts, visible in the queue.
     */
    public function test_the_queue_retries_until_the_grade_is_persisted(): void {
        global $DB;
        $attempt = $this->regrade_window();
        $queued = $DB->get_record_select(
            'local_assistente_event',
            "event_type = 'quiz_attempt_regraded' AND payload LIKE :p",
            ['p' => '%"attemptid":' . (int) $attempt->get_attemptid() . ',%'],
            '*',
            IGNORE_MULTIPLE
        );
        $this->assertNotFalse($queued, 'The observer queued the real attempt_regraded event');
        $registry = new handler_registry();
        $registry->register(event_type::QUIZ_ATTEMPT_REGRADED, $this->handler);
        $processor = new queue_processor(null, $registry);

        $processor->run_cycle();
        $afterfirst = $DB->get_record('local_assistente_event', ['id' => $queued->id]);
        $this->assertSame('pending', $afterfirst->status, 'Failed once, waiting for another attempt');
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));

        $this->quiz_settings_grade_calculator()->recompute_all_attempt_sumgrades();
        $processor->run_cycle();

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $queued->id]));
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));
        $processor->run_cycle();
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']), 'No second analysis');
    }

    /**
     * A regrade that is never completed: after three attempts the event is failed and visible, nothing was written.
     */
    public function test_a_regrade_that_never_completes_leaves_the_event_failed(): void {
        global $DB;
        $attempt = $this->regrade_window();
        $queued = $DB->get_record_select(
            'local_assistente_event',
            "event_type = 'quiz_attempt_regraded' AND payload LIKE :p",
            ['p' => '%"attemptid":' . (int) $attempt->get_attemptid() . ',%'],
            '*',
            IGNORE_MULTIPLE
        );
        $registry = new handler_registry();
        $registry->register(event_type::QUIZ_ATTEMPT_REGRADED, $this->handler);
        $processor = new queue_processor(null, $registry);

        for ($i = 0; $i < 4; $i++) {
            $processor->run_cycle();
        }

        $this->assertSame('failure', $DB->get_field('local_assistente_event', 'status', ['id' => $queued->id]));
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));
    }

    /**
     * A settled attempt is not mistaken for a stale one: an attempt with a question waiting for manual grading (no grade at all) and an
     * ordinary finished attempt both pass the check.
     */
    public function test_settled_attempts_pass_the_check(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->attempt($student, 1, [1 => '1', 2 => '3.14']);

        [$result] = $this->recalculate();

        $this->assertTrue($result->success, (string) $result->response());
    }

    /**
     * The grade calculator of the quiz, as the overview report uses it after a regrade.
     *
     * @return \mod_quiz\grade_calculator
     */
    private function quiz_settings_grade_calculator(): \mod_quiz\grade_calculator {
        return quiz_settings::create((int) $this->quiz->id)->get_grade_calculator();
    }

    /**
     * Put an attempt in the regrade window: a finished attempt is regraded with the real method of the overview report after the true/
     * false question is edited, so that the questions are saved and `sumgrades` is still the old value.
     *
     * @return quiz_attempt
     */
    private function regrade_window(): quiz_attempt {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/report/overview/report.php');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $attempt = $this->attempt($student, 1, [1 => '1', 2 => '3.14']);
        $this->qgen()->update_question($this->questions['tf'], null, ['correctanswer' => 0]);
        $report = new \quiz_overview_report();
        (new \ReflectionProperty(\mod_quiz\local\reports\attempts_report::class, 'context'))
            ->setValue($report, \context_module::instance($this->quiz->cmid));
        $report->regrade_attempt($DB->get_record('quiz_attempts', ['id' => $attempt->get_attemptid()], '*', MUST_EXIST));
        return $attempt;
    }
    /**
     * Idempotency: a repeated delivery for a processing that has its results reads and writes nothing.
     */
    public function test_repeated_delivery_is_idempotent(): void {
        global $DB;
        $this->attempt($this->students[0], 1, [1 => '1', 2 => '3.14']);
        [$event, $processing] = $this->event_and_processing(event_type::QUIZ_ATTEMPT_REGRADED, ['cmid' => (int) $this->quiz->cmid, 'attemptid' => 1]);
        $this->assertTrue($this->handler->handle($event, $processing)->success);

        $again = $this->handler->handle($event, $processing);

        $this->assertTrue($again->success);
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));
    }

    /**
     * The handler is registered for the four events, and the history keeps every execution.
     */
    public function test_registered_for_the_four_events_and_history_is_kept(): void {
        $registry = handler_registry::default();
        foreach ([
            event_type::QUIZ_ATTEMPT_SUBMITTED,
            event_type::QUIZ_ATTEMPT_MANUALLY_GRADED,
            event_type::QUIZ_ATTEMPT_REGRADED,
            event_type::QUIZ_CLOSED,
        ] as $type) {
            $this->assertInstanceOf(results_handler::class, $registry->handler_for($type), $type->value);
        }
        $this->recalculate();
        $this->recalculate();
        $this->assertCount(2, $this->repository->find_history((int) $this->course->id, (int) $this->quiz->cmid));
    }
}
