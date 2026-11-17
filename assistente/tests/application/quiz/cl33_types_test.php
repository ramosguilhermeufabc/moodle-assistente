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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Technical validation CL-33 (T075), question types: for each type, a REAL attempt answered with the response that the question itself
 * gives as correct, read by the reader and processed by the results handler. It records what Moodle 5.2.4 returns for each type; it is a
 * selection (the types below), not all the types of Moodle, and it decides nothing.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class cl33_types_test extends \advanced_testcase {
    /**
     * Types of the selection, with the template of the core generator when one is needed.
     *
     * @return array
     */
    public static function types(): array {
        return [
            'match' => ['match', null],
            'multianswer (cloze)' => ['multianswer', null],
            'gapselect' => ['gapselect', null],
            'ddwtos' => ['ddwtos', null],
            'ddimageortext' => ['ddimageortext', null],
            'calculated' => ['calculated', null],
            'calculatedmulti' => ['calculatedmulti', null],
            'calculatedsimple' => ['calculatedsimple', null],
        ];
    }

    /**
     * A real attempt with the correct response of the type, and one with no response.
     *
     * @param string $type
     * @param string|null $which
     */
    #[DataProvider('types')]
    public function test_type_in_a_real_attempt(string $type, ?string $which): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($course->id, true);
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id, 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => 1,
        ]);
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        try {
            $question = $qgen->create_question($type, $which, ['category' => $category->id]);
        } catch (\Throwable $e) {
            $this->markTestSkipped("NOT EXECUTED: the generator has no fixture for {$type}: " . $e->getMessage());
        }
        quiz_add_quiz_question($question->id, $quiz);

        $reader = new questionnaire_reader();
        $read = $reader->get_question((int) $question->id);
        $this->assertSame($type, $read->type);

        $events = new event_repository();
        $made = $events->create_event((int) $course->id, event_type::QUIZ_PUBLISHED, ['cmid' => (int) $quiz->cmid], time());
        $events->begin_attempt((int) $made->id, time());
        (new quiz_initial_repository())->save(
            (int) $course->id,
            (int) $quiz->cmid,
            (int) $events->find_processing((int) $made->id)->id,
            0.5,
            [['question_id' => (int) $question->id, 'question_version' => 1, 'complexity' => 0.5]]
        );

        $right = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $silent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        try {
            $attempt = $this->start($quiz, $right);
        } catch (\Throwable $e) {
            $this->markTestSkipped("NOT EXECUTED: the attempt of {$type} cannot start with the fixture of the generator (no dataset items): " . $e->getMessage());
        }
        $correct = $attempt->get_question_attempt(1)->get_question()->get_correct_response();
        $qa = $attempt->get_question_attempt(1);
        $post = ['slots' => '1', $qa->get_field_prefix() . ':sequencecheck' => $qa->get_sequence_check_count()];
        foreach ($correct as $name => $value) {
            $post[$qa->get_field_prefix() . $name] = $value;
        }
        $attempt->process_submitted_actions(time(), false, $post);
        $attempt = quiz_attempt::create($attempt->get_attemptid());
        $attempt->process_submit(time(), false);
        $attempt->process_grade_submission(time());
        $other = $this->start($quiz, $silent);
        $other->process_submit(time(), false);
        $other->process_grade_submission(time());

        $detail = $reader->get_attempt_detail((int) $attempt->get_attemptid());
        $item = $detail->questions[0];
        [$event, $processing] = $this->delivery($events, $course, $quiz);
        $result = (new results_handler())->handle($event, $processing);
        $repository = new quiz_results_repository();
        $analysis = $repository->find_by_processing((int) $processing->id);
        $rows = array_values($repository->find_question_results((int) $analysis->id));

        fwrite(STDERR, sprintf(
            "\nCL33 %s: reader answers=%d correct_response=%s | attempt state=%s fraction=%s total_mark=%s | handler %s rows=%d avg=%s partial=%s\n",
            $type,
            count($read->answers),
            $read->correct_response === null ? 'null' : (empty($read->correct_response) ? 'empty' : 'given'),
            $item->state,
            var_export($item->fraction, true),
            var_export($detail->total_mark, true),
            $result->success ? 'ok' : 'FAIL ' . $result->response(),
            count($rows),
            $rows ? $rows[0]->average_correctness : '-',
            $rows ? var_export($rows[0]->partial_result, true) : '-'
        ));
        $this->assertTrue($result->success, (string) $result->response());
        $this->assertNull($item->choices, 'Only multiple choice with several answers has the alternatives of the attempt');
        // Matching, drag-and-drop words and Cloze give the parts right of the attempt (get_num_parts_right: 3 stems or gaps in the fixtures
        // of match and ddwtos, 2 embedded subquestions in the one of multianswer); the silent attempt has no grade and no parts. No other
        // type of this selection has them.
        $parts = ['match' => 3, 'ddwtos' => 3, 'multianswer' => 2];
        $withparts = isset($parts[$type]);
        $this->assertSame($withparts ? ['right' => $parts[$type], 'total' => $parts[$type]] : null, $item->parts, "Parts of {$type}");
        $silentitem = $reader->get_attempt_detail((int) $other->get_attemptid())->questions[0];
        $this->assertNull($silentitem->parts, 'An answer never given has no parts: it stays out of the indicator, never a zero');
        if ($item->fraction !== null) {
            $this->assertEqualsWithDelta(1.0, $item->fraction, 0.0001, 'The correct response of the question is graded right');
            $this->assertCount(1, $rows);
            $this->assertEquals(100.0, $rows[0]->average_correctness, 'The silent attempt is not graded and stays out');
            $withparts ? $this->assertEquals(100.0, $rows[0]->partial_result) : $this->assertNull($rows[0]->partial_result);
        }
    }

    /**
     * An event and its processing for the results of the quiz.
     *
     * @param event_repository $events
     * @param \stdClass $course
     * @param \stdClass $quiz
     * @return \stdClass[]
     */
    private function delivery(event_repository $events, \stdClass $course, \stdClass $quiz): array {
        $event = $events->create_event((int) $course->id, event_type::QUIZ_ATTEMPT_REGRADED, ['cmid' => (int) $quiz->cmid, 'attemptid' => 1, 'occurred_at' => time()], time());
        $events->begin_attempt((int) $event->id, time());
        return [$event, $events->find_processing((int) $event->id)];
    }

    /**
     * Start an attempt for a user.
     *
     * @param \stdClass $quiz
     * @param \stdClass $user
     * @return quiz_attempt
     */
    private function start(\stdClass $quiz, \stdClass $user): quiz_attempt {
        $quizobj = quiz_settings::create($quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        return quiz_attempt::create($attempt->id);
    }
}
