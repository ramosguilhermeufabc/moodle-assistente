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

use mod_quiz\quiz_settings;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Technical validation CL-33 (T069; quickstart 010 Q12): reading a questionnaire in Moodle 5.2.4 with fixed and random positions and
 * several versions, and extracting statement, answers and the response marked by the teacher by question type. It records what
 * Moodle returns; the expectations below are observations, not decisions.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(questionnaire_reader::class)]
final class cl33_quiz_reading_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass quiz instance with cmid */
    private \stdClass $quiz;

    /** @var \stdClass question category of the quiz */
    private \stdClass $category;

    /** @var questionnaire_reader */
    private questionnaire_reader $reader;

    /**
     * A course with a quiz and a question category in the quiz context.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id, 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => 2,
        ]);
        $this->category = $this->qgen()->create_question_category(['contextid' => \context_module::instance($this->quiz->cmid)->id]);
        $this->reader = new questionnaire_reader();
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
     * Create a question of a type in the quiz category.
     *
     * @param string $type
     * @param array $overrides
     * @return \stdClass
     */
    private function question(string $type, array $overrides = []): \stdClass {
        return $this->qgen()->create_question($type, null, $overrides + ['category' => $this->category->id]);
    }

    /**
     * Statement, answers and teacher response by type, as Moodle returns them (OBSERVED, 5.2.4).
     */
    public function test_extraction_by_question_type(): void {
        $truefalse = $this->reader->get_question((int) $this->question('truefalse')->id);
        $this->assertSame('truefalse', $truefalse->type);
        $this->assertSame('The answer is true.', $truefalse->text);
        $this->assertSame([], $truefalse->answers, 'True/false has no answers');
        $this->assertEquals(['answer' => 1], $truefalse->correct_response, 'The teacher response is given by the question');

        $short = $this->reader->get_question((int) $this->question('shortanswer')->id);
        $this->assertSame(['frog', 'toad', '*'], array_map(static fn($a) => $a->text, $short->answers));
        $this->assertEquals([1.0, 0.8, 0.0], array_map(static fn($a) => $a->fraction, $short->answers));
        $this->assertEquals(['answer' => 'frog'], $short->correct_response);

        $numerical = $this->reader->get_question((int) $this->question('numerical')->id);
        $this->assertSame('3.14', $numerical->answers[0]->text);
        $this->assertEquals(['answer' => '3.14'], $numerical->correct_response);

        $essay = $this->reader->get_question((int) $this->question('essay')->id);
        $this->assertSame([], $essay->answers);
        $this->assertNull($essay->correct_response, 'An essay has no response marked by the teacher');

        $description = $this->reader->get_question((int) $this->question('description')->id);
        $this->assertSame('description', $description->type);
    }

    /**
     * LIMITATION observed: for multiple choice and matching the answers and their fractions are read, but the correct response
     * comes back empty, because Moodle builds it from the order of the answers of an attempt, which a question loaded outside an
     * attempt does not have. The right choices are in the fractions of the answers.
     */
    public function test_multichoice_and_match_have_answers_but_no_correct_response_outside_an_attempt(): void {
        $multi = $this->reader->get_question((int) $this->question('multichoice')->id);
        $this->assertCount(4, $multi->answers);
        $this->assertEquals([0.5, 0.0, 0.5, 0.0], array_map(static fn($a) => $a->fraction, $multi->answers));
        $this->assertEmpty($multi->correct_response);

        $match = $this->reader->get_question((int) $this->question('match')->id);
        $this->assertSame([], $match->answers, 'Matching keeps its pairs in subquestions, not in answers');
        $this->assertEmpty($match->correct_response);
    }

    /**
     * DEFECT found and fixed: a type that needs data of an attempt to load (calculated) made get_question() throw, which would
     * fail the whole analysis of a questionnaire that merely contains one. The reader now returns the question with the answers it
     * could read and no response, as its documented hypothesis says for types that cannot supply them.
     */
    public function test_a_type_that_cannot_load_options_outside_an_attempt_does_not_break_the_reader(): void {
        $calculated = $this->question('calculated');

        $read = $this->reader->get_question((int) $calculated->id);

        $this->assertSame('calculated', $read->type);
        $this->assertSame('{a} + {b}', $read->answers[0]->text, 'The formula is read; the answers are not evaluated');
        $this->assertNull($read->correct_response, 'The response needs the dataset of an attempt');
    }

    /**
     * Versions: the fixed slot returns the version in force, a new version replaces it for the next reading, and both versions
     * keep distinct question ids with the same bank entry.
     */
    public function test_fixed_slot_follows_the_version_in_force(): void {
        $first = $this->question('truefalse', ['name' => 'V1']);
        quiz_add_quiz_question($first->id, $this->quiz);
        $before = $this->reader->get_slots($this->quiz->cmid);
        $second = $this->qgen()->update_question($first, null, ['name' => 'V2']);
        $after = $this->reader->get_slots($this->quiz->cmid);

        $this->assertSame([1], array_map(static fn($s) => $s->question->question_version, $before));
        $this->assertSame([1], array_map(static fn($s) => $s->question->question_version, $before));
        $this->assertSame([2], array_map(static fn($s) => $s->question->question_version, $after));
        $this->assertSame((int) $second->id, $after[0]->question->question_id);
        $this->assertNotSame((int) $first->id, (int) $second->id);
        $this->assertSame($before[0]->question->bank_entry_id, $after[0]->question->bank_entry_id);
        $this->assertFalse($after[0]->random);
        $this->assertSame('ready', $after[0]->status);
    }

    /**
     * Random slot with a filter: the slot reports the filter and no question, and the candidates are the questions in force of the
     * filter at that moment, one per bank entry; a question both candidate and fixed appears in both readings.
     */
    public function test_random_slot_candidates_are_the_questions_in_force_of_the_filter(): void {
        $questions = [];
        for ($i = 1; $i <= 4; $i++) {
            $questions[$i] = $this->question('essay', ['name' => "Candidata {$i}"]);
        }
        quiz_settings::create($this->quiz->id)->get_structure()->add_random_questions(1, 1, [
            'filter' => [
                'category' => [
                    'jointype' => \core_question\local\bank\condition::JOINTYPE_DEFAULT,
                    'values' => [$this->category->id],
                    'filteroptions' => ['includesubcategories' => false],
                ],
            ],
        ]);
        quiz_add_quiz_question($questions[1]->id, $this->quiz);
        $newer = $this->qgen()->update_question($questions[2], null, ['name' => 'Candidata 2 v2']);

        $slots = $this->reader->get_slots($this->quiz->cmid);
        $random = array_values(array_filter($slots, static fn($s) => $s->random));
        $candidates = $this->reader->get_random_candidates($random[0]->filter);

        $this->assertCount(2, $slots, 'One random and one fixed position');
        $this->assertNull($random[0]->question);
        $this->assertNotEmpty($random[0]->filter);
        $this->assertCount(4, $candidates, 'One candidate per question of the filter');
        $byid = array_column(array_map(static fn($c) => (array) $c, $candidates), 'question_version', 'question_id');
        $this->assertSame(2, $byid[(int) $newer->id], 'The version in force of the second question is a candidate');
        $this->assertArrayNotHasKey((int) $questions[2]->id, $byid, 'Its older version is not');
        $this->assertArrayHasKey((int) $questions[1]->id, $byid, 'The fixed question is also a candidate');
    }
}
