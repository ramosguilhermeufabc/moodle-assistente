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
 * Tests of the questionnaire reader (quickstart 004 Q1; DEC-35, DEC-36).
 *
 * The fixtures follow the patterns of the core mod_quiz tests of MOODLE_502_STABLE (qbank_helper_test,
 * attempt_walkthrough_test). They have not been run: if a fixture fails because of a core API detail, fix the
 * fixture first. Behaviour of random slots, versions and attempts in a running Moodle 5.2 is validated
 * by CL-33 (T033).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(questionnaire_reader::class)]
final class questionnaire_reader_test extends \advanced_testcase {
    /** @var \stdClass course */
    private \stdClass $course;

    /** @var \stdClass quiz instance record (with cmid) */
    private \stdClass $quiz;

    /** @var \stdClass question category in the quiz context */
    private \stdClass $category;

    /**
     * Course, quiz and a question category that belongs to the quiz.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->quiz = $this->create_quiz();
        $context = \context_module::instance($this->quiz->cmid);
        $this->category = $this->question_generator()->create_question_category(['contextid' => $context->id]);
    }

    /**
     * The core_question generator.
     *
     * @return \core_question_generator
     */
    private function question_generator(): \core_question_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_question');
    }

    /**
     * Create a quiz in the course.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function create_quiz(array $overrides = []): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance($overrides + [
            'course' => $this->course->id,
            'questionsperpage' => 0,
            'grade' => 100.0,
            'sumgrades' => 2,
        ]);
    }

    /**
     * Create a question in the quiz category.
     *
     * @param string $qtype
     * @param string|null $which question type template
     * @param array $overrides
     * @return \stdClass
     */
    private function create_question(string $qtype, ?string $which = null, array $overrides = []): \stdClass {
        return $this->question_generator()->create_question($qtype, $which, $overrides + ['category' => $this->category->id]);
    }

    /**
     * Q-questionnaire: fields, time limits and grade method; anything that is not a quiz gives null.
     */
    public function test_questionnaire_data(): void {
        $close = time() + 7 * DAYSECS;
        $quiz = $this->create_quiz(['timeclose' => $close, 'grademethod' => QUIZ_GRADEAVERAGE, 'name' => 'Prova 1']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $reader = new questionnaire_reader();

        $data = $reader->get_questionnaire($quiz->cmid);
        $this->assertSame((int) $quiz->id, $data->quiz_id);
        $this->assertSame((int) $this->course->id, $data->course_id);
        $this->assertSame('Prova 1', $data->name);
        $this->assertSame($close, $data->time_close);
        $this->assertSame('average', $data->grade_method);

        $this->assertNull($reader->get_questionnaire($this->quiz->cmid)->time_close, 'timeclose unset is null');
        $this->assertSame('highest', $reader->get_questionnaire($this->quiz->cmid)->grade_method);
        $this->assertNull($reader->get_questionnaire($page->cmid));
        $this->assertNull($reader->get_questionnaire(987654));
        $this->assertSame([], $reader->get_slots($page->cmid));
        $this->assertSame([], $reader->get_attempts(987654));
    }

    /**
     * Q1: two versions of a question are told apart by question_id + question_version, and the slot returns the
     * version in force.
     */
    public function test_question_versions_are_told_apart_without_the_slot(): void {
        $first = $this->create_question('essay', null, ['name' => 'Versao 1']);
        $second = $this->question_generator()->update_question($first, null, ['name' => 'Versao 2']);
        quiz_add_quiz_question($first->id, $this->quiz);
        $reader = new questionnaire_reader();

        $versionone = $reader->get_question((int) $first->id);
        $versiontwo = $reader->get_question((int) $second->id);

        $this->assertSame(1, $versionone->question_version);
        $this->assertSame(2, $versiontwo->question_version);
        $this->assertNotSame($versionone->question_id, $versiontwo->question_id);
        $this->assertSame($versionone->bank_entry_id, $versiontwo->bank_entry_id, 'Same entry of the question bank');
        $this->assertNull($reader->get_question(987654));

        $slots = $reader->get_slots($this->quiz->cmid);
        $this->assertCount(1, $slots);
        $this->assertFalse($slots[0]->random);
        $this->assertSame($versiontwo->question_id, $slots[0]->question->question_id, 'The latest version is in force');
        $this->assertSame(2, $slots[0]->question->question_version);
    }

    /**
     * Description-type questions are returned with their type, so that the analysis can leave them out (DEC-35 D1).
     */
    public function test_description_question_keeps_its_type(): void {
        $description = $this->create_question('description');
        quiz_add_quiz_question($description->id, $this->quiz);

        $slots = (new questionnaire_reader())->get_slots($this->quiz->cmid);

        $this->assertSame('description', $slots[0]->question->type);
    }

    /**
     * Answers with fractions and the response marked correct, by question type.
     */
    public function test_answers_and_correct_response(): void {
        $choice = $this->create_question('multichoice', 'one_of_four');
        $essay = $this->create_question('essay');
        $reader = new questionnaire_reader();

        $multi = $reader->get_question((int) $choice->id);
        $this->assertSame('multichoice', $multi->type);
        $this->assertCount(4, $multi->answers);
        $this->assertCount(1, array_filter($multi->answers, static fn($a) => $a->fraction > 0.99));
        $this->assertNotNull($multi->correct_response);

        $long = $reader->get_question((int) $essay->id);
        $this->assertSame([], $long->answers);
        $this->assertNull($long->correct_response);

        $light = $reader->get_question((int) $choice->id, false);
        $this->assertSame([], $light->answers);
    }

    /**
     * Random slots: no question, the filter, and the candidates of the category at this moment (DEC-35 D1).
     */
    public function test_random_slot_and_candidates(): void {
        $questions = [];
        for ($i = 1; $i <= 4; $i++) {
            $questions[$i] = $this->create_question('essay', null, ['name' => "Candidata {$i}"]);
        }
        $quizobj = quiz_settings::create($this->quiz->id);
        $quizobj->get_structure()->add_random_questions(1, 1, [
            'filter' => [
                'category' => [
                    'jointype' => \core_question\local\bank\condition::JOINTYPE_DEFAULT,
                    'values' => [$this->category->id],
                    'filteroptions' => ['includesubcategories' => false],
                ],
            ],
        ]);
        // One of the candidates is also placed in a fixed position.
        quiz_add_quiz_question($questions[1]->id, $this->quiz);
        $reader = new questionnaire_reader();

        $slots = $reader->get_slots($this->quiz->cmid);
        $random = array_values(array_filter($slots, static fn($slot) => $slot->random));
        $fixed = array_values(array_filter($slots, static fn($slot) => !$slot->random));
        $this->assertCount(1, $random);
        $this->assertCount(1, $fixed);
        $this->assertNull($random[0]->question);
        $this->assertNotEmpty($random[0]->filter);

        $candidates = $reader->get_random_candidates($random[0]->filter);
        $ids = array_map(static fn($candidate) => $candidate->question_id, $candidates);
        $this->assertCount(4, $candidates, 'One item per question, with no duplicate for the fixed one');
        $this->assertSame(count($ids), count(array_unique($ids)));
        $this->assertContains((int) $questions[1]->id, $ids);
        $this->assertSame(1, $candidates[0]->question_version);
        $this->assertSame([], $reader->get_random_candidates(['filter' => ['category' => [
            'jointype' => \core_question\local\bank\condition::JOINTYPE_DEFAULT,
            'values' => [987654],
            'filteroptions' => ['includesubcategories' => false],
        ]]]));
    }

    /**
     * Start an attempt for a user.
     *
     * @param \stdClass $user
     * @param bool $preview
     * @return quiz_attempt
     */
    private function start_attempt(\stdClass $user, bool $preview = false): quiz_attempt {
        $quizobj = quiz_settings::create($this->quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, $preview, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        return quiz_attempt::create($attempt->id);
    }

    /**
     * Attempts, their states and the fraction per question; an essay waiting for grading has no fraction.
     */
    public function test_attempts_states_and_fractions(): void {
        $numerical = $this->create_question('numerical');
        $essay = $this->create_question('essay');
        quiz_add_quiz_question($numerical->id, $this->quiz);
        quiz_add_quiz_question($essay->id, $this->quiz);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $reader = new questionnaire_reader();

        // A finished attempt: numerical right, essay answered and waiting for manual grading.
        $finished = $this->start_attempt($student);
        $finished->process_submitted_actions(time(), false, [
            1 => ['answer' => '3.14'],
            2 => ['answer' => 'Uma resposta longa', 'answerformat' => FORMAT_HTML],
        ]);
        $finished = quiz_attempt::create($finished->get_attemptid());
        $finished->process_finish(time(), false);
        // An attempt in progress and a teacher preview.
        $inprogress = $this->start_attempt($other);
        $preview = $this->start_attempt($this->getDataGenerator()->create_user(), true);

        $attempts = [];
        foreach ($reader->get_attempts($this->quiz->cmid) as $attempt) {
            $attempts[$attempt->attempt_id] = $attempt;
        }
        $this->assertCount(3, $attempts);
        $this->assertSame('finished', $attempts[$finished->get_attemptid()]->state);
        $this->assertTrue($attempts[$finished->get_attemptid()]->is_finished);
        $this->assertNotNull($attempts[$finished->get_attemptid()]->time_finish);
        $this->assertSame('inprogress', $attempts[$inprogress->get_attemptid()]->state);
        $this->assertFalse($attempts[$inprogress->get_attemptid()]->is_finished);
        $this->assertNull($attempts[$inprogress->get_attemptid()]->time_finish);
        $this->assertTrue($attempts[$preview->get_attemptid()]->preview);
        $this->assertFalse($attempts[$finished->get_attemptid()]->preview);

        $answers = $reader->get_attempt_questions($finished->get_attemptid());
        $this->assertCount(2, $answers);
        $this->assertSame((int) $numerical->id, $answers[0]->question_id);
        $this->assertSame(1, $answers[0]->question_version);
        $this->assertTrue($answers[0]->graded);
        $this->assertEqualsWithDelta(1.0, $answers[0]->fraction, 0.0001);
        $this->assertFalse($answers[1]->graded, 'Essay waits for manual grading');
        $this->assertNull($answers[1]->fraction);
        $this->assertSame([], $reader->get_attempt_questions(987654));
    }
}
