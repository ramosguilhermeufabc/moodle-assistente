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

use local_assistente\application\material\material_service;
use local_assistente\application\queue\attempt_failure;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the initial analysis of a questionnaire (T068; quickstart 010 Q1 to Q11; DEC-31, DEC-34, DEC-35). The LLM is the simulated
 * adapter (DEC-48). Q12 is the technical validation T069.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(initial_analysis_handler::class)]
final class initial_analysis_handler_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass quiz instance with cmid */
    private \stdClass $quiz;

    /** @var \stdClass question category of the quiz */
    private \stdClass $category;

    /** @var fake_adapter */
    private fake_adapter $llm;

    /** @var event_repository */
    private event_repository $events;

    /** @var quiz_initial_repository */
    private quiz_initial_repository $repository;

    /** @var initial_analysis_handler */
    private initial_analysis_handler $handler;

    /**
     * Course with the assistant enabled, a student and a quiz in section 1.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        (new config_repository())->create($this->course->id, true);
        $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id, 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => 2, 'section' => 1,
        ]);
        $this->category = $this->qgen()->create_question_category(['contextid' => \context_module::instance($this->quiz->cmid)->id]);
        $this->llm = new fake_adapter();
        $this->events = new event_repository();
        $this->repository = new quiz_initial_repository();
        $this->handler = new initial_analysis_handler($this->llm);
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
     * @param string $qtype
     * @param string|null $which
     * @param array $overrides
     * @return \stdClass
     */
    private function add_question(string $qtype, ?string $which = null, array $overrides = []): \stdClass {
        $question = $this->qgen()->create_question($qtype, $which, $overrides + ['category' => $this->category->id]);
        quiz_add_quiz_question($question->id, $this->quiz);
        return $question;
    }

    /**
     * A processed page in the section of the quiz (an eligible material).
     *
     * @param int $section
     * @return int cmid
     */
    private function material(int $section = 1): int {
        $page = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $this->course->id, 'content' => '<p>Conteudo</p>'],
            ['section' => $section]
        );
        $this->assertTrue((new material_service(new material_repository(), null, new fake_adapter()))
            ->process((int) $this->course->id, (int) $page->cmid)->success);
        return (int) $page->cmid;
    }

    /**
     * An event and its processing, as the queue hands them to the handler.
     *
     * @param int|null $cmid
     * @return \stdClass[] event and processing
     */
    private function delivery(?int $cmid = null): array {
        $event = $this->events->create_event(
            (int) $this->course->id,
            \local_assistente\domain\event\event_type::QUIZ_PUBLISHED,
            ['cmid' => $cmid ?? (int) $this->quiz->cmid],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());
        return [$event, $this->events->find_processing((int) $event->id)];
    }

    /**
     * Run the handler for a new delivery.
     *
     * @return array handler_result and the processing
     */
    private function deliver_and_handle(): array {
        [$event, $processing] = $this->delivery();
        return [$this->handler->handle($event, $processing), $processing];
    }

    /**
     * Q1: a questionnaire with 3 questions gives one analysis: a coverage and 3 complexities; the duration is recorded.
     */
    public function test_three_questions_give_one_analysis(): void {
        $this->material();
        foreach (['truefalse', 'essay', 'shortanswer'] as $type) {
            $this->add_question($type);
        }

        [$result, $processing] = $this->deliver_and_handle();

        $this->assertTrue($result->success, (string) $result->response());
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame('quiz_initial', $analysis->analysis_type);
        $this->assertSame((int) $this->quiz->cmid, (int) $analysis->cmid);
        $this->assertEquals(0.5, $this->repository->find_questionnaire_analysis((int) $analysis->id)->coverage);
        $this->assertCount(3, $this->repository->find_questions((int) $analysis->id));
        $this->assertSame(4, $this->llm->calls(), '3 complexities and 1 coverage');
        $this->assertMatchesRegularExpression('/4 LLM calls, [0-9.]+ s/', (string) $result->response());
    }

    /**
     * Q2: a value the mock returns outside the domain (0.35, 1.2) is rejected and nothing is persisted.
     */
    public function test_invalid_values_are_rejected_and_nothing_is_persisted(): void {
        global $DB;
        $this->material();
        $this->add_question('truefalse');
        foreach ([0.35, 1.2] as $bad) {
            $this->llm->respond_valid(['complexity' => $bad]);

            [$result] = $this->deliver_and_handle();

            $this->assertFalse($result->success);
            $this->assertSame(attempt_failure::INVALID_RESPONSE, $result->failure);
        }
        $this->assertSame(0, $DB->count_records('local_assistente_analysis'));
    }

    /**
     * Q3: a material of another section does not reach the context.
     */
    public function test_material_of_another_section_is_not_in_the_context(): void {
        $this->material(1);
        $this->material(2);
        $this->add_question('truefalse');

        [$result] = $this->deliver_and_handle();

        $this->assertTrue($result->success);
        $context = $this->llm->requests[0]['context']->data;
        $this->assertCount(1, $context['materials']);
    }

    /**
     * Q4: the position follows the version in force when the questionnaire is published (DEC-35 D1), so a new version of the same
     * question, published later, gives a distinct row by question version in the next analysis; the older analysis is untouched.
     */
    public function test_two_versions_of_a_question_give_distinct_rows(): void {
        $first = $this->add_question('essay', null, ['name' => 'Versao 1']);
        [$one, $processingone] = $this->deliver_and_handle();
        $this->qgen()->update_question($first, null, ['name' => 'Versao 2']);

        [$two, $processingtwo] = $this->deliver_and_handle();

        $this->assertTrue($one->success);
        $this->assertTrue($two->success);
        $rowsone = array_values($this->repository->find_questions((int) $this->repository->find_by_processing((int) $processingone->id)->id));
        $rowstwo = array_values($this->repository->find_questions((int) $this->repository->find_by_processing((int) $processingtwo->id)->id));
        $this->assertSame([1], array_map(static fn($r) => (int) $r->question_version, $rowsone));
        $this->assertSame([2], array_map(static fn($r) => (int) $r->question_version, $rowstwo));
        $this->assertNotSame((int) $rowsone[0]->question_id, (int) $rowstwo[0]->question_id, 'Distinct question rows by version');
    }

    /**
     * Q5: a description question is left out.
     */
    public function test_description_question_is_left_out(): void {
        $this->add_question('description');
        $this->add_question('truefalse');
        $this->add_question('essay');

        [$result, $processing] = $this->deliver_and_handle();

        $this->assertTrue($result->success);
        $this->assertCount(2, $this->repository->find_questions((int) $this->repository->find_by_processing((int) $processing->id)->id));
    }

    /**
     * Q6: a random position contributes the candidates of the filter, with no duplicate of a question that is also fixed.
     */
    public function test_random_position_contributes_candidates_without_duplicates(): void {
        $questions = [];
        for ($i = 1; $i <= 4; $i++) {
            $questions[$i] = $this->qgen()->create_question('essay', null, ['category' => $this->category->id, 'name' => "Candidata {$i}"]);
        }
        \mod_quiz\quiz_settings::create($this->quiz->id)->get_structure()->add_random_questions(1, 1, [
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
        $fixed = $questions[1];

        [$result, $processing] = $this->deliver_and_handle();

        $this->assertTrue($result->success, (string) $result->response());
        $rows = $this->repository->find_questions((int) $this->repository->find_by_processing((int) $processing->id)->id);
        $ids = array_map(static fn($r) => (int) $r->question_id, array_values($rows));
        $this->assertSame(count($ids), count(array_unique($ids)), 'One row per question and version');
        $this->assertContains((int) $fixed->id, $ids);
        $this->assertCount(4, $ids, 'The pool of 4 candidates, the fixed one counted once');
    }

    /**
     * Q7: a question without answers or teacher response (essay) is analysed with its statement only.
     */
    public function test_essay_is_analysed_with_the_statement_only(): void {
        $this->add_question('essay', null, ['questiontext' => ['text' => 'Explique a fotossintese', 'format' => FORMAT_HTML]]);

        [$result] = $this->deliver_and_handle();

        $this->assertTrue($result->success);
        $question = $this->llm->requests[0]['context']->data['question'];
        $this->assertSame('Explique a fotossintese', $question['statement']);
        $this->assertSame([], $question['answers']);
    }

    /**
     * Q8: a section with no eligible material gives coverage 0.0, no quiz_coverage call, and a snapshot with no eligible material.
     */
    public function test_no_eligible_material_gives_zero_coverage_without_a_call(): void {
        $this->add_question('truefalse');
        $this->add_question('essay');

        [$result, $processing] = $this->deliver_and_handle();

        $this->assertTrue($result->success);
        $this->assertEquals(0.0, $this->repository->find_questionnaire_analysis(
            (int) $this->repository->find_by_processing((int) $processing->id)->id
        )->coverage);
        $uses = array_map(static fn($r) => $r['context']->usecase->value, $this->llm->requests);
        $this->assertSame(['question_complexity', 'question_complexity'], $uses);
        $snapshot = json_decode($this->events->find_context((int) $processing->id)->context_json, true);
        $this->assertSame([], $snapshot['used_versions']);
    }

    /**
     * Q9: a failure in the third of four complexity calls writes nothing; the retry of the same processing redoes everything.
     */
    public function test_failure_midway_writes_nothing_and_the_retry_redoes_it(): void {
        global $DB;
        for ($i = 0; $i < 4; $i++) {
            $this->add_question('truefalse');
        }
        $this->llm->respond_valid(['complexity' => 0.1])->respond_valid(['complexity' => 0.2])->fail_with('timeout');
        [$event, $processing] = $this->delivery();

        $first = $this->handler->handle($event, $processing);

        $this->assertFalse($first->success);
        $this->assertSame(attempt_failure::TIMEOUT, $first->failure);
        $this->assertSame(0, $DB->count_records('local_assistente_analysis'));
        $second = $this->handler->handle($event, $processing);
        $this->assertTrue($second->success);
        $this->assertCount(4, $this->repository->find_questions((int) $this->repository->find_by_processing((int) $processing->id)->id));
        $this->assertSame(1, $DB->count_records('local_assistente_processing_context'), 'One snapshot for the processing');
    }

    /**
     * Q10: a section that cannot be resolved is a technical error, never coverage 0.0.
     */
    public function test_unresolved_section_is_a_technical_error(): void {
        global $DB;
        $this->add_question('truefalse');
        [$event, $processing] = $this->delivery(987654);

        $result = $this->handler->handle($event, $processing);

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $this->assertSame(0, $DB->count_records('local_assistente_analysis'));
        $this->assertSame(0, $this->llm->calls());
    }

    /**
     * Q11: the context holds no attempt, answer of a student or identifier of a student.
     */
    public function test_context_has_no_student_data(): void {
        $this->material();
        $this->add_question('truefalse');

        [$result] = $this->deliver_and_handle();

        $this->assertTrue($result->success);
        foreach ($this->llm->requests as $request) {
            $json = json_encode($request['context']->data);
            $this->assertDoesNotMatchRegularExpression('/user_?id|attempt|student|email/i', $json);
        }
    }

    /**
     * A delivery repeated for a processing that has its analysis calls nothing and writes nothing.
     */
    public function test_repeated_delivery_is_idempotent(): void {
        global $DB;
        $this->add_question('truefalse');
        [$event, $processing] = $this->delivery();
        $this->assertTrue($this->handler->handle($event, $processing)->success);
        $calls = $this->llm->calls();

        $again = $this->handler->handle($event, $processing);

        $this->assertTrue($again->success);
        $this->assertSame($calls, $this->llm->calls());
        $this->assertSame(1, $DB->count_records('local_assistente_analysis'));
    }

    /**
     * Through the real queue: the queue holds the lock of the event while the handler runs, and the repository, which locks the same
     * event for its transaction, shares it instead of being refused. The analysis is saved and the event is processed.
     */
    public function test_the_analysis_is_saved_when_run_by_the_queue(): void {
        global $DB;
        $this->add_question('truefalse');
        $event = $this->events->create_event(
            (int) $this->course->id,
            \local_assistente\domain\event\event_type::QUIZ_PUBLISHED,
            ['cmid' => (int) $this->quiz->cmid],
            time()
        );
        $registry = new \local_assistente\application\queue\handler_registry();
        $registry->register(\local_assistente\domain\event\event_type::QUIZ_PUBLISHED, $this->handler);

        (new \local_assistente\application\queue\queue_processor(null, $registry))->run_cycle();

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $event->id]));
        $processing = $this->events->find_processing((int) $event->id);
        $this->assertNotNull($this->repository->find_by_processing((int) $processing->id), 'The analysis of this event was saved');
    }

    /**
     * The handler is registered for the publication event and can be built without arguments.
     */
    public function test_registered_for_the_publication_event(): void {
        $registry = \local_assistente\application\queue\handler_registry::default();

        $this->assertInstanceOf(
            initial_analysis_handler::class,
            $registry->handler_for(\local_assistente\domain\event\event_type::QUIZ_PUBLISHED)
        );
    }
}
