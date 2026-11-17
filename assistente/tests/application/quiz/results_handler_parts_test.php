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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Partial result of matching (`match`) and drag-and-drop words into sentences (`ddwtos`) questions (Q-011-07; DEC-36 D1; CL-33, T075).
 *
 * Real quiz, real attempts, the real handler and the real persistence. The expected counts come from the answer key below, written by
 * text, and not from the Moodle objects that the code under test reads. `multianswer` (Cloze) counts its embedded subquestions graded
 * right, and `multichoice` with several answers keeps its own rule (DEC-52); both are checked here against the same flow.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(results_handler::class)]
final class results_handler_parts_test extends \advanced_testcase {
    /** @var string Cloze with four embedded subquestions of four types, one point each: short answer (a 50% answer), numerical, multiple choice and multiple response */
    private const CLOZE = 'Capital of France: {1:SHORTANSWER:=Paris~%50%France~*}. Two plus two: {1:NUMERICAL:=4:0}. '
        . 'Largest planet: {1:MULTICHOICE:Mars~=Jupiter~Venus}. Primary colours: {1:MULTIRESPONSE:=Red~=Blue~%-50%Green}.';

    /** @var string the first three subquestions of CLOZE */
    private const CLOZE3 = 'Capital of France: {1:SHORTANSWER:=Paris~%50%France~*}. Two plus two: {1:NUMERICAL:=4:0}. '
        . 'Largest planet: {1:MULTICHOICE:Mars~=Jupiter~Venus}.';

    /** @var string Cloze with a single embedded subquestion */
    private const CLOZE1 = 'Capital of France: {1:SHORTANSWER:=Paris~*}.';

    /** @var string Cloze whose subquestions weigh zero */
    private const CLOZE0 = 'Capital of France: {0:SHORTANSWER:=Paris~*}. Two plus two: {0:NUMERICAL:=4:0}.';

    /** @var int[] position of each planet of the multiple choice of CLOZE (not shuffled: the order of the text) */
    private const PLANETS = ['Mars' => 0, 'Jupiter' => 1, 'Venus' => 2];

    /** @var int[] position of each colour of the multiple response of CLOZE (not shuffled: the order of the text) */
    private const COLOURS = ['Red' => 0, 'Blue' => 1, 'Green' => 2];

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $quiz;

    /** @var \stdClass[] questions of the quiz, in slot order */
    private array $questions = [];

    /** @var quiz_results_repository */
    private quiz_results_repository $repository;

    /** @var event_repository */
    private event_repository $events;

    /** @var int students created, to give each its own user */
    private int $students = 0;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($this->course->id, true);
        $this->repository = new quiz_results_repository();
        $this->events = new event_repository();
    }

    /**
     * Answers by text, for the questions of the generator. A stem or gap left out, or null, is the empty choice.
     *
     * @param string $type match or ddwtos
     * @return array[] name => answer by stem (frog, cat, newt) or by gap (1, 2, 3)
     */
    private static function answers(string $type): array {
        if ($type === 'match') {
            return [
                'all' => ['frog' => 'amphibian', 'cat' => 'mammal', 'newt' => 'amphibian'],
                'two' => ['frog' => 'amphibian', 'cat' => 'insect', 'newt' => 'amphibian'],
                'one' => ['frog' => 'amphibian', 'cat' => 'amphibian', 'newt' => 'insect'],
                'none' => ['frog' => 'mammal', 'cat' => 'insect', 'newt' => 'mammal'],
                'one_rest_blank' => ['frog' => 'amphibian', 'cat' => null, 'newt' => null],
                'blank' => ['frog' => null, 'cat' => null, 'newt' => null],
            ];
        }
        return [
            'all' => [1 => 'quick', 2 => 'fox', 3 => 'lazy'],
            'two' => [1 => 'quick', 2 => 'dog', 3 => 'lazy'],
            'one' => [1 => 'quick', 2 => 'dog', 3 => 'assiduous'],
            'none' => [1 => 'slow', 2 => 'dog', 3 => 'assiduous'],
            'one_rest_blank' => [1 => 'quick', 2 => null, 3 => null],
            'blank' => [1 => null, 2 => null, 3 => null],
        ];
    }

    /**
     * The two types and their fixtures of the generator.
     *
     * @return array
     */
    public static function types(): array {
        return ['match' => ['match', 'foursubq'], 'ddwtos' => ['ddwtos', 'fox']];
    }

    /**
     * A quiz with the questions, and their initial analysis, as the publication handler would have saved it.
     *
     * @param array $types list of [type, fixture or null] or [type, fixture or null, overrides of the generator]
     * @param string $behaviour
     */
    private function make_quiz(array $types, string $behaviour = 'deferredfeedback'): void {
        $this->quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id, 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => count($types),
            'preferredbehaviour' => $behaviour,
        ]);
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => \context_module::instance($this->quiz->cmid)->id]);
        $this->questions = [];
        foreach ($types as $entry) {
            [$type, $which] = $entry;
            $question = $this->new_question($type, $which, $category, $entry[2] ?? []);
            quiz_add_quiz_question($question->id, $this->quiz);
            $this->questions[] = $question;
        }
        [, $processing] = $this->event_and_processing(event_type::QUIZ_PUBLISHED, ['cmid' => (int) $this->quiz->cmid]);
        (new quiz_initial_repository())->save(
            (int) $this->course->id,
            (int) $this->quiz->cmid,
            (int) $processing->id,
            0.5,
            array_map(
                static fn(\stdClass $q) => ['question_id' => (int) $q->id, 'question_version' => 1, 'complexity' => 0.5],
                $this->questions
            )
        );
    }

    /**
     * A question of the generator. `randomsamatch` has no form data fixture in the generator (its only example is an in-memory
     * definition), so it is saved by its own question type with the form that the editing page sends, over two short answer questions of
     * the same category, which are the ones it draws its stems from; they are not in the quiz.
     *
     * @param string $type
     * @param string|null $which fixture of the generator
     * @param \stdClass $category
     * @param array $overrides of the generator
     * @return \stdClass
     */
    private function new_question(string $type, ?string $which, \stdClass $category, array $overrides = []): \stdClass {
        global $CFG;
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        if ($type !== 'randomsamatch') {
            return $qgen->create_question($type, $which, ['category' => $category->id] + $overrides);
        }
        require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
        $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);
        $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);
        $form = (object) [
            'category' => (string) $category->id,
            'name' => 'Random matching',
            'questiontext' => ['text' => 'Classify the animals.', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'choose' => 2,
            'subcats' => 0,
            'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        ];
        \test_question_maker::set_standard_combined_feedback_form_data($form);
        $question = (object) ['qtype' => 'randomsamatch', 'createdby' => 0, 'idnumber' => null, 'status' => $form->status];
        return \question_bank::get_qtype('randomsamatch')->save_question($question, $form);
    }

    /**
     * An event and its processing, as the queue hands them to the handler.
     *
     * @param event_type $type
     * @param array $payload
     * @return \stdClass[] event and processing
     */
    private function event_and_processing(event_type $type, array $payload): array {
        static $occurred = 5000;
        if (isset($type->fields()['occurred_at'])) {
            $payload += ['occurred_at' => ++$occurred];
        }
        $event = $this->events->create_event((int) $this->course->id, $type, $payload, time());
        $this->events->begin_attempt((int) $event->id, time());
        return [$event, $this->events->find_processing((int) $event->id)];
    }

    /**
     * Run the handler for the quiz, with the event of a real attempt of it.
     *
     * @param int $attemptid attempt that the event is about; its user is read from the attempt
     * @param event_type $type
     * @param int $slot slot that was graded, for the manual grading event
     * @return array handler_result and the analysis it saved (null when it saved none)
     */
    private function recalculate(int $attemptid, event_type $type = event_type::QUIZ_ATTEMPT_REGRADED, int $slot = 1): array {
        global $DB;
        $cmid = (int) $this->quiz->cmid;
        $userid = (int) $DB->get_field('quiz_attempts', 'userid', ['id' => $attemptid], MUST_EXIST);
        $payload = match ($type) {
            event_type::QUIZ_ATTEMPT_SUBMITTED => ['cmid' => $cmid, 'attemptid' => $attemptid, 'userid' => $userid],
            event_type::QUIZ_ATTEMPT_MANUALLY_GRADED => ['cmid' => $cmid, 'attemptid' => $attemptid, 'slot' => $slot],
            default => ['cmid' => $cmid, 'attemptid' => $attemptid],
        };
        [$event, $processing] = $this->event_and_processing($type, $payload);
        $result = (new results_handler())->handle($event, $processing);
        $this->assertTrue($result->success, (string) $result->response());
        return [$result, $this->repository->find_by_processing((int) $processing->id)];
    }

    /**
     * The rows of question_result of an analysis by question id.
     *
     * @param \stdClass|null $analysis
     * @return \stdClass[]
     */
    private function rows(?\stdClass $analysis): array {
        $rows = [];
        foreach ($analysis ? $this->repository->find_question_results((int) $analysis->id) : [] as $row) {
            $rows[(int) $row->question_id] = $row;
        }
        return $rows;
    }

    /**
     * Start an attempt of a new student.
     *
     * @return quiz_attempt
     */
    private function start(): quiz_attempt {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['username' => 'parts_student_' . ++$this->students]);
        $quizobj = quiz_settings::create($this->quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        return quiz_attempt::create($attempt->id);
    }

    /**
     * Submit the data of one slot, as processattempt.php does.
     *
     * @param int $attemptid
     * @param int $slot
     * @param array $data question type data by name (the prefix is added)
     */
    private function submit(int $attemptid, int $slot, array $data): void {
        $attempt = quiz_attempt::create($attemptid);
        $qa = $attempt->get_question_attempt($slot);
        $post = ['slots' => (string) $slot, $qa->get_field_prefix() . ':sequencecheck' => $qa->get_sequence_check_count()];
        foreach ($data as $name => $value) {
            $post[$qa->get_field_prefix() . $name] = $value;
        }
        $attempt->process_submitted_actions(time(), false, $post);
    }

    /**
     * Finish and grade an attempt.
     *
     * @param int $attemptid
     */
    private function finish(int $attemptid): void {
        $attempt = quiz_attempt::create($attemptid);
        $attempt->process_submit(time(), false);
        $attempt->process_grade_submission(time());
    }

    /**
     * The form data of a matching or drag-and-drop words question for an answer by text. The position of each choice belongs to the
     * attempt (it is shuffled), so it is found by its text.
     *
     * @param int $attemptid
     * @param int $slot
     * @param string $type match or ddwtos
     * @param array $answer stem text or gap number => choice text, null for the empty choice
     * @return array
     */
    private function data(int $attemptid, int $slot, string $type, array $answer): array {
        $question = quiz_attempt::create($attemptid)->get_question_attempt($slot)->get_question();
        $data = [];
        if ($type === 'match') {
            foreach ($question->get_stem_order() as $key => $stemid) {
                $data['sub' . $key] = 0;
                $wanted = $answer[trim(strip_tags($question->stems[$stemid]))] ?? null;
                foreach ($question->get_choice_order() as $position => $choiceid) {
                    if ($wanted !== null && $question->choices[$choiceid] === $wanted) {
                        $data['sub' . $key] = $position;
                    }
                }
            }
            return $data;
        }
        foreach ($question->places as $place => $group) {
            $data['p' . $place] = 0;
            foreach ($question->get_ordered_choices($group) as $position => $choice) {
                if (($answer[$place] ?? null) !== null && $choice->text === $answer[$place]) {
                    $data['p' . $place] = $position;
                }
            }
        }
        return $data;
    }

    /**
     * A finished attempt of a new student whose first question is answered with the given answer.
     *
     * @param string $type
     * @param array|null $answer null leaves the question without any submission
     * @return int attempt id
     */
    private function attempt_with(string $type, ?array $answer): int {
        $attemptid = (int) $this->start()->get_attemptid();
        if ($answer !== null) {
            $this->submit($attemptid, 1, $this->data($attemptid, 1, $type, $answer));
        }
        $this->finish($attemptid);
        return $attemptid;
    }

    /**
     * Every scenario of the type with a real attempt: the indicator is the mean of parts right over parts of the answers that have a
     * grade. Wrong and empty parts only lower the numerator; an answer never given, and one submitted all empty (Moodle keeps neither,
     * the question ends `gaveup` with no fraction), are left out and never taken as zero.
     *
     * @param string $type
     * @param string $which
     */
    #[DataProvider('types')]
    public function test_partial_result_is_the_mean_of_parts_right(string $type, string $which): void {
        $this->make_quiz([[$type, $which]]);
        $answers = self::answers($type);
        foreach (['all', 'two', 'one', 'none', 'one_rest_blank', 'blank'] as $name) {
            $this->attempt_with($type, $answers[$name]);
        }
        $attemptid = $this->attempt_with($type, null);

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $row = $this->rows($analysis)[(int) $this->questions[0]->id];
        // (3 + 2 + 1 + 0 + 1) / 3 over the five answers that have a grade: 46,666...%, truncated to three places (DEC-36 D2).
        $this->assertEquals(46.666, $row->partial_result);
        $this->assertEquals(1, $row->correct_count, 'Only the answer with every part right has the full fraction');
        $this->assertEquals(1, $row->incorrect_count, 'Only the answer with no part right has a zero fraction');
        // If the two answers without a grade were zeros the mean would be 33,333: they are not in the mean.
        $this->assertNotEquals(33.333, $row->partial_result);
    }

    /**
     * An answer with some parts wrong is not zeroed (unlike multiple choice, DEC-52), and the percentage is truncated, not rounded.
     *
     * @param string $type
     * @param string $which
     */
    #[DataProvider('types')]
    public function test_a_wrong_part_does_not_zero_the_answer_and_the_value_is_truncated(string $type, string $which): void {
        $this->make_quiz([[$type, $which]]);
        $attemptid = $this->attempt_with($type, self::answers($type)['two']);

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $this->assertEquals(66.666, $this->rows($analysis)[(int) $this->questions[0]->id]->partial_result, '2 of 3 truncated, not 66.667');
    }

    /**
     * No answer has a grade (never answered, or submitted empty): the question has no row at all, not a zero.
     *
     * @param string $type
     * @param string $which
     */
    #[DataProvider('types')]
    public function test_questions_never_answered_are_no_data(string $type, string $which): void {
        $this->make_quiz([[$type, $which]]);
        $this->attempt_with($type, null);
        $attemptid = $this->attempt_with($type, self::answers($type)['blank']);

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $this->assertSame([], $this->rows($analysis), 'No graded answer: no_data, never a zero');
    }

    /**
     * The indicator is the count of parts, not the fraction of the question. With the interactive behaviour a student that sends a wrong
     * answer, tries again and gets everything right has every part right, but the fraction carries the penalty of the first try.
     */
    public function test_the_count_of_parts_is_used_not_the_fraction(): void {
        $this->make_quiz([['match', 'foursubq']], 'interactive');
        $attemptid = (int) $this->start()->get_attemptid();
        $answers = self::answers('match');
        $this->submit($attemptid, 1, $this->data($attemptid, 1, 'match', $answers['two']) + ['-submit' => 1]);
        $this->submit($attemptid, 1, ['-tryagain' => 1]);
        $this->submit($attemptid, 1, $this->data($attemptid, 1, 'match', $answers['all']) + ['-submit' => 1]);
        $this->finish($attemptid);

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $row = $this->rows($analysis)[(int) $this->questions[0]->id];
        $this->assertEquals(100.0, $row->partial_result);
        $this->assertLessThan(100.0, (float) $row->average_correctness, 'The fraction of Moodle carries the penalty of the first try');
    }

    /**
     * Regrade with another answer key: the question is edited into a new version and the attempts are regraded with the real method of the
     * overview report. The next recalculation counts the parts against the current key and links the row to the analysed question.
     *
     * @param string $type
     * @param string $which
     */
    #[DataProvider('types')]
    public function test_regrade_with_another_key_is_recounted(string $type, string $which): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/report/overview/report.php');
        $this->make_quiz([[$type, $which]]);
        $answers = self::answers($type);
        $attempts = [$this->attempt_with($type, $answers['all']), $this->attempt_with($type, $answers['two'])];
        [, $before] = $this->recalculate($attempts[1], event_type::QUIZ_ATTEMPT_SUBMITTED);
        $this->assertEquals(83.333, $this->rows($before)[(int) $this->questions[0]->id]->partial_result, '(3/3 + 2/3) / 2');

        // The new version changes the key of one part: newt is a mammal, or the first gap takes "slow".
        $changes = $type === 'match'
            ? ['subanswers' => [0 => 'amphibian', 1 => 'mammal', 2 => 'mammal', 3 => 'insect']]
            : ['questiontext' => ['text' => 'The [[4]] brown [[2]] jumped over the [[3]] dog.', 'format' => FORMAT_HTML]];
        $this->getDataGenerator()->get_plugin_generator('core_question')->update_question($this->questions[0], $which, $changes);
        $report = new \quiz_overview_report();
        (new \ReflectionProperty(\mod_quiz\local\reports\attempts_report::class, 'context'))
            ->setValue($report, \context_module::instance($this->quiz->cmid));
        foreach ($attempts as $id) {
            $this->assertSame([], $report->regrade_attempt($DB->get_record('quiz_attempts', ['id' => $id], '*', MUST_EXIST)));
        }
        quiz_settings::create((int) $this->quiz->id)->get_grade_calculator()->recompute_all_attempt_sumgrades();

        [, $after] = $this->recalculate($attempts[0]);

        $rows = $this->rows($after);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertNotEquals((int) $this->questions[0]->id, (int) $row->question_id, 'The answers are now of the new version');
        $this->assertEquals(50.0, $row->partial_result, '(2/3 + 1/3) / 2 with the new key');
        $this->assertEquals(2, (int) $row->question_version);
    }

    /**
     * A question already graded by Moodle enters the metrics of the question even though another question of the same attempt waits for
     * manual grading, which stays out; the attempt has no grade yet, so there is no aggregate until the teacher grades the essay.
     *
     * @param string $type
     * @param string $which
     */
    #[DataProvider('types')]
    public function test_graded_question_enters_while_another_waits_for_manual_grading(string $type, string $which): void {
        global $DB;
        $this->make_quiz([[$type, $which], ['essay', null]]);
        $attemptid = (int) $this->start()->get_attemptid();
        $this->submit($attemptid, 1, $this->data($attemptid, 1, $type, self::answers($type)['two']));
        $this->submit($attemptid, 2, ['answer' => 'Texto', 'answerformat' => FORMAT_HTML]);
        $this->finish($attemptid);

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $rows = $this->rows($analysis);
        $this->assertEquals(66.666, $rows[(int) $this->questions[0]->id]->partial_result);
        $this->assertArrayNotHasKey((int) $this->questions[1]->id, $rows, 'The essay has no grade: it stays out');
        $this->assertNull($this->repository->find_metric((int) $analysis->id), 'sumgrades is null: no student result, no aggregate');

        // The teacher grades the essay: the attempt gets its grade and enters the aggregate.
        $stored = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $quba = \question_engine::load_questions_usage_by_activity((int) $stored->uniqueid);
        $quba->manual_grade(2, 'Bom', 1.0, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);
        $DB->update_record('quiz_attempts', (object) ['id' => $stored->id, 'timemodified' => time(), 'sumgrades' => $quba->get_total_mark()]);

        [, $graded] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_MANUALLY_GRADED, 2);

        $this->assertEquals(66.666, $this->rows($graded)[(int) $this->questions[0]->id]->partial_result);
        $this->assertEqualsWithDelta(83.333, (float) $this->repository->find_metric((int) $graded->id)->mean, 0.001);
    }

    /**
     * Regression: multiple choice with several answers keeps DEC-52 (one wrong choice zeroes it) in the same quiz where a wrong part of
     * matching only lowers the count; a type without several correct items has no indicator; `multianswer` (Cloze) counts its two
     * embedded subquestions graded right (the scenarios of the Cloze are in the tests below).
     */
    public function test_other_types_keep_their_rules(): void {
        $this->make_quiz([['multichoice', 'two_of_four'], ['match', 'foursubq'], ['truefalse', null], ['multianswer', 'twosubq']]);
        $attemptid = (int) $this->start()->get_attemptid();

        $qa = quiz_attempt::create($attemptid)->get_question_attempt(1);
        $multi = $qa->get_question();
        $pick = [];
        $correct = $incorrect = 1;
        foreach ($multi->get_order($qa) as $position => $answerid) {
            $isright = (float) $multi->answers[$answerid]->fraction > 0;
            if (($isright && $correct-- > 0) || (!$isright && $incorrect-- > 0)) {
                $pick["choice{$position}"] = 1;
            }
        }
        $this->submit($attemptid, 1, $pick);
        $this->submit($attemptid, 2, $this->data($attemptid, 2, 'match', self::answers('match')['two']));
        $this->submit($attemptid, 3, ['answer' => '1']);
        $this->submit($attemptid, 4, quiz_attempt::create($attemptid)->get_question_attempt(4)->get_question()->get_correct_response());
        $this->finish($attemptid);

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $rows = $this->rows($analysis);
        $this->assertEquals(0.0, $rows[(int) $this->questions[0]->id]->partial_result, 'DEC-52: any incorrect choice gives 0');
        $this->assertNotNull($rows[(int) $this->questions[0]->id]->partial_result);
        $this->assertEquals(66.666, $rows[(int) $this->questions[1]->id]->partial_result, 'A wrong part of matching does not zero it');
        $this->assertNull($rows[(int) $this->questions[2]->id]->partial_result, 'True/false has no indicator');
        $this->assertArrayHasKey((int) $this->questions[3]->id, $rows, 'The Cloze answer has a grade and a row');
        $this->assertEquals(100.0, $rows[(int) $this->questions[3]->id]->partial_result, 'multianswer: both embedded subquestions are right');
    }

    /**
     * `randomsamatch` extends the class of `match` in Moodle, so a check by class would take it in; it is out of this indicator. A real
     * attempt with a graded answer has a row and a grade, but the reader gives no parts and the partial result stays null.
     */
    public function test_randomsamatch_is_out_of_the_indicator(): void {
        $this->make_quiz([['randomsamatch', null]]);
        $attemptid = (int) $this->start()->get_attemptid();
        $question = quiz_attempt::create($attemptid)->get_question_attempt(1)->get_question();
        $this->assertSame('randomsamatch', $question->qtype->name());
        $this->assertInstanceOf(\qtype_match_question::class, $question, 'The reason for comparing by name and not by class');
        $this->submit($attemptid, 1, $question->get_correct_response());
        $this->finish($attemptid);

        $item = (new questionnaire_reader())->get_attempt_detail($attemptid)->questions[0];
        $this->assertEqualsWithDelta(1.0, $item->fraction, 0.0001, 'The answer is graded: it would have parts if it were taken in');
        $this->assertNull($item->parts);

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $row = $this->rows($analysis)[(int) $this->questions[0]->id];
        $this->assertEquals(100.0, $row->average_correctness);
        $this->assertNull($row->partial_result);
    }
    /**
     * A question of the quiz as an attempt holds it.
     *
     * @param int $attemptid
     * @param int $slot
     * @return \question_attempt
     */
    private function question_attempt(int $attemptid, int $slot = 1): \question_attempt {
        return quiz_attempt::create($attemptid)->get_question_attempt($slot);
    }

    /**
     * The overrides of the generator that give a multianswer (Cloze) question its own text.
     *
     * @param string $text Cloze text
     * @return array
     */
    private static function cloze(string $text): array {
        return ['questiontext' => ['text' => $text, 'format' => FORMAT_HTML]];
    }

    /**
     * The form data of the Cloze of CLOZE for an answer by text. The position of a choice in an embedded question that is not shuffled is the
     * order in which it is written in the Cloze text; a key left out is a subquestion left unanswered.
     *
     * @param array $answer sa (text), num (text), mc (planet) and mr (list of colours); null or a key left out is unanswered
     * @return array
     */
    private function cloze_data(array $answer): array {
        $data = [];
        if (($answer['sa'] ?? null) !== null) {
            $data['sub1_answer'] = $answer['sa'];
        }
        if (($answer['num'] ?? null) !== null) {
            $data['sub2_answer'] = $answer['num'];
        }
        if (($answer['mc'] ?? null) !== null) {
            $data['sub3_answer'] = self::PLANETS[$answer['mc']];
        }
        foreach ($answer['mr'] ?? [] as $colour) {
            $data['sub4_choice' . self::COLOURS[$colour]] = 1;
        }
        return $data;
    }

    /**
     * A finished attempt of a new student on a quiz whose first question is the Cloze of CLOZE.
     *
     * @param array|null $answer null leaves the question without any submission
     * @return int attempt id
     */
    private function attempt_with_cloze(?array $answer): int {
        $attemptid = (int) $this->start()->get_attemptid();
        if ($answer !== null) {
            $this->submit($attemptid, 1, $this->cloze_data($answer));
        }
        $this->finish($attemptid);
        return $attemptid;
    }

    /**
     * Scenarios of the Cloze of CLOZE (four embedded subquestions: short answer with a 50% answer, numerical, multiple choice and multiple
     * response with a 50% penalty). The expected value is the number of subquestions that the scenario answers right, over four, as a
     * percentage: it comes from the scenario, and the state is the premise that Moodle itself grades the scenario as designed.
     *
     * @return array name => [answer, native state of the question, expected partial_result]
     */
    public static function cloze_scenarios(): array {
        return [
            'all four right' => [['sa' => 'Paris', 'num' => '4', 'mc' => 'Jupiter', 'mr' => ['Red', 'Blue']], 'gradedright', 100.0],
            'two right, two wrong' => [['sa' => 'Paris', 'num' => '5', 'mc' => 'Mars', 'mr' => ['Red', 'Blue']], 'gradedpartial', 50.0],
            'text answer with 50% credit' => [['sa' => 'France', 'num' => '4', 'mc' => 'Jupiter', 'mr' => ['Red', 'Blue']], 'gradedpartial', 75.0],
            'multiple response with half of the right ones' => [['sa' => 'Paris', 'num' => '4', 'mc' => 'Jupiter', 'mr' => ['Red']], 'gradedpartial', 75.0],
            'multiple choice and multiple response unanswered' => [['sa' => 'Paris', 'num' => '4'], 'gradedpartial', 50.0],
            'numerical unanswered' => [['sa' => 'Paris', 'mc' => 'Jupiter', 'mr' => ['Red', 'Blue']], 'gradedpartial', 75.0],
            'all four answered wrong' => [['sa' => 'Rome', 'num' => '5', 'mc' => 'Mars', 'mr' => ['Green']], 'gradedwrong', 0.0],
        ];
    }

    /**
     * Cloze (`multianswer`): the parts are the embedded subquestions graded right, over all of them; a subquestion that is partially right,
     * wrong or unanswered is out of the numerator and in the denominator. Covers the four embedded types and a real attempt each.
     *
     * @param array $answer
     * @param string $state
     * @param float $expected
     */
    #[DataProvider('cloze_scenarios')]
    public function test_cloze_counts_the_subquestions_graded_right(array $answer, string $state, float $expected): void {
        $this->make_quiz([['multianswer', 'twosubq', self::cloze(self::CLOZE)]]);
        $attemptid = $this->attempt_with_cloze($answer);
        $this->assertSame($state, (string) $this->question_attempt($attemptid)->get_state(), 'Premise: Moodle grades the scenario as designed');

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $row = $this->rows($analysis)[(int) $this->questions[0]->id];
        $this->assertEquals($expected, $row->partial_result);
        $this->assertNotNull($row->partial_result, 'A graded answer with no subquestion right is a valid zero');
    }

    /**
     * Cloze never answered: the question ends `gaveup` with no fraction, has no parts and is left out; it is not a zero.
     */
    public function test_cloze_never_answered_is_no_data(): void {
        $this->make_quiz([['multianswer', 'twosubq', self::cloze(self::CLOZE)]]);
        $attemptid = $this->attempt_with_cloze(null);
        $this->assertSame('gaveup', (string) $this->question_attempt($attemptid)->get_state());
        $this->assertNull($this->question_attempt($attemptid)->get_fraction());

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $this->assertSame([], $this->rows($analysis), 'No graded answer: no_data, never a zero');
    }

    /**
     * The mean of the question is among the answers that have the indicator: four answers (100, 50, 75 and 0) and one never answered, which
     * does not enter as a zero (it would give 45).
     */
    public function test_cloze_mean_leaves_the_unanswered_out(): void {
        $this->make_quiz([['multianswer', 'twosubq', self::cloze(self::CLOZE)]]);
        $scenarios = self::cloze_scenarios();
        foreach (['all four right', 'two right, two wrong', 'text answer with 50% credit', 'all four answered wrong'] as $name) {
            $this->attempt_with_cloze($scenarios[$name][0]);
        }
        $attemptid = $this->attempt_with_cloze(null);

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $row = $this->rows($analysis)[(int) $this->questions[0]->id];
        $this->assertEquals(56.25, $row->partial_result, '(4 + 2 + 3 + 0) / 4 subquestions, over the four answers');
        $this->assertEquals(1, $row->correct_count, 'Only the answer with every subquestion right has the full fraction');
        $this->assertEquals(1, $row->incorrect_count, 'Only the answer with every subquestion wrong has a zero fraction');
    }

    /**
     * Scale: 2 of 3 subquestions is truncated to three places (66.666), not rounded.
     */
    public function test_cloze_percentage_is_truncated(): void {
        $this->make_quiz([['multianswer', 'twosubq', self::cloze(self::CLOZE3)]]);
        $attemptid = $this->attempt_with_cloze(['sa' => 'Paris', 'num' => '4', 'mc' => 'Mars']);

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $this->assertEquals(66.666, $this->rows($analysis)[(int) $this->questions[0]->id]->partial_result);
    }

    /**
     * A Cloze with a single embedded subquestion does not have several items: the indicator does not apply (DEC-36 D1) and stays null,
     * even with the answer graded right.
     */
    public function test_cloze_with_one_subquestion_has_no_indicator(): void {
        $this->make_quiz([['multianswer', 'twosubq', self::cloze(self::CLOZE1)]]);
        $attemptid = $this->attempt_with_cloze(['sa' => 'Paris']);
        $this->assertSame('gradedright', (string) $this->question_attempt($attemptid)->get_state());

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $row = $this->rows($analysis)[(int) $this->questions[0]->id];
        $this->assertEquals(100.0, $row->average_correctness);
        $this->assertNull($row->partial_result);
    }

    /**
     * A Cloze whose subquestions all weigh zero has no fraction even when answered: Moodle gives it no grade, so it has no parts and no
     * row, while the other question of the attempt keeps its own.
     */
    public function test_cloze_without_a_fraction_is_out(): void {
        $this->make_quiz([['truefalse', null], ['multianswer', 'twosubq', self::cloze(self::CLOZE0)]]);
        $attemptid = (int) $this->start()->get_attemptid();
        $this->submit($attemptid, 1, ['answer' => '1']);
        $this->submit($attemptid, 2, ['sub1_answer' => 'Paris', 'sub2_answer' => '4']);
        $this->finish($attemptid);
        $this->assertNull($this->question_attempt($attemptid, 2)->get_fraction(), 'Premise: weight zero gives no fraction');

        [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);

        $rows = $this->rows($analysis);
        $this->assertArrayHasKey((int) $this->questions[0]->id, $rows);
        $this->assertArrayNotHasKey((int) $this->questions[1]->id, $rows, 'No fraction: no row');
    }
    /**
     * The warning that Moodle gives for a Cloze with an unanswered numerical subquestion (a request without the field) is expected and
     * does not reach the handler in place; the count is still right (3 of 4: the numerical is graded wrong).
     */
    public function test_cloze_with_a_numerical_left_out_gives_no_php_warning(): void {
        $this->make_quiz([['multianswer', 'twosubq', self::cloze(self::CLOZE)]]);
        $attemptid = $this->attempt_with_cloze(['sa' => 'Paris', 'mc' => 'Jupiter', 'mr' => ['Red', 'Blue']]);

        $seen = [];
        set_error_handler(static function (int $no, string $message) use (&$seen): bool {
            $seen[] = $message;
            return true;
        });
        try {
            [, $analysis] = $this->recalculate($attemptid, event_type::QUIZ_ATTEMPT_SUBMITTED);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $seen, 'No PHP warning or notice');
        $this->assertEquals(75.0, $this->rows($analysis)[(int) $this->questions[0]->id]->partial_result);
    }

    /**
     * Only that warning is silenced: any other warning or notice from the count goes on to the handler in place, the same warning from
     * another file too, and the handler is put back afterwards.
     */
    public function test_other_warnings_of_the_count_stay_visible(): void {
        $question = new class {
            public object $qtype;

            public function __construct() {
                $this->qtype = new class {
                    public function name(): string {
                        return 'multianswer';
                    }
                };
            }

            public function get_num_parts_right(array $response): array {
                trigger_error('another warning', E_USER_WARNING);
                $empty = [];
                $unused = $empty['answer'];
                trigger_error('a notice', E_USER_NOTICE);
                return [1, 2];
            }
        };
        $state = $this->createStub(\question_state::class);
        $state->method('is_graded')->willReturn(true);
        $attempt = $this->createStub(\question_attempt::class);
        $attempt->method('get_question')->willReturn($question);
        $attempt->method('get_state')->willReturn($state);
        $attempt->method('get_fraction')->willReturn(0.5);
        $attempt->method('get_last_qt_data')->willReturn([]);

        $seen = [];
        set_error_handler(static function (int $no, string $message) use (&$seen): bool {
            $seen[] = $message;
            return true;
        });
        try {
            $parts = (new \ReflectionMethod(questionnaire_reader::class, 'parts'))->invoke(new questionnaire_reader(), $attempt);
            trigger_error('after the count', E_USER_WARNING);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['right' => 1, 'total' => 2], $parts);
        $this->assertSame(
            ['another warning', 'Undefined array key "answer"', 'a notice', 'after the count'],
            $seen,
            'The same message from a file that is not the numerical question is not silenced, and the handler is restored'
        );
    }
}
