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


namespace local_assistente\application\synthesis;

use local_assistente\application\frequency\frequency_handler;
use local_assistente\application\grade\performance_handler;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\domain\llm\llm_context;
use local_assistente\domain\synthesis\fingerprint;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\llm\llm_port;
use local_assistente\infrastructure\llm\structured_result;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\forum_analysis_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use local_assistente\infrastructure\persistence\synthesis_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the synthesis handler (T097; quickstart 016 Q1 to Q6, Q8, Q11 to Q14; DEC-04, DEC-13, DEC-19, DEC-41, DEC-46). Real course,
 * readers, repositories and queue; the LLM is the simulated adapter behind a spy that keeps what was sent.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(synthesis_handler::class)]
final class synthesis_handler_test extends \advanced_testcase {
    /** @var int counter that keeps the occurrences distinct */
    private static int $occurred = 9000;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass[] two students */
    private array $students;

    /** @var int[] ids of the sections 1 and 2 */
    private array $sections;

    /** @var \stdClass assignment past due with a grade of 100, section 1 */
    private \stdClass $assign;

    /** @var \stdClass forum, section 1 */
    private \stdClass $forum;

    /** @var event_repository */
    private event_repository $events;

    /** @var synthesis_repository */
    private synthesis_repository $repository;

    /** @var fake_adapter the simulated adapter behind the spy */
    private fake_adapter $adapter;

    /** @var llm_context[] what the port received */
    private array $sent = [];

    /** @var string[] instructions the port received */
    private array $instructions = [];

    /** @var synthesis_handler */
    private synthesis_handler $handler;

    /**
     * A course with the assistant, two students, an assignment and a forum in the first section and a second section.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2, 'startdate' => $now - 500 - DAYSECS, 'enddate' => $now - 500, 'lang' => 'pt_br']);
        (new config_repository())->create($this->course->id, true);
        $this->students = [
            $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Maria', 'lastname' => 'Souza', 'email' => 'maria.souza@example.com']),
            $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Joao', 'lastname' => 'Lima', 'email' => 'joao.lima@example.com']),
        ];
        $this->sections = array_map('intval', array_values(
            $DB->get_fieldset_select('course_sections', 'id', 'course = ? AND section > 0', [$this->course->id], 'section')
        ));
        $this->assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id, 'duedate' => $now - 1000, 'grade' => 100,
        ], ['section' => 1]);
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id], ['section' => 1]);
        $this->events = new event_repository();
        $this->repository = new synthesis_repository();
        $this->adapter = new fake_adapter();
        $this->handler = $this->handler_with($this->adapter);
    }

    /**
     * A handler whose port keeps what it receives and delegates to an adapter.
     *
     * @param llm_port $inner
     * @return synthesis_handler
     */
    private function handler_with(llm_port $inner): synthesis_handler {
        $spy = new class ($inner, $this->sent, $this->instructions) implements llm_port {
            /**
             * Constructor.
             *
             * @param llm_port $inner
             * @param array $sent receives the contexts
             * @param array $instructions receives the instructions
             */
            public function __construct(private llm_port $inner, private array &$sent, private array &$instructions) {
            }

            #[\Override]
            public function evaluate(llm_context $context, string $instruction): structured_result {
                $this->sent[] = $context;
                $this->instructions[] = $instruction;
                return $this->inner->evaluate($context, $instruction);
            }
        };
        return new synthesis_handler($spy);
    }

    /**
     * Create an event, begin its attempt and hand it to a handler.
     *
     * @param event_type $type
     * @param array $payload
     * @param \local_assistente\application\queue\event_handler|null $handler the synthesis handler of the test by default
     * @param int|null $courseid
     * @return array handler_result, processing and event
     */
    private function deliver(event_type $type, array $payload, $handler = null, ?int $courseid = null): array {
        $event = $this->events->create_event($courseid ?? (int) $this->course->id, $type, $payload, time());
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        return [($handler ?? $this->handler)->handle($event, $processing), $processing, $event];
    }

    /**
     * Ask for the synthesis of a section.
     *
     * @param int $section index of the section (0 or 1)
     * @param synthesis_handler|null $handler
     * @return array handler_result, processing and event
     */
    private function synthesize(int $section = 0, ?synthesis_handler $handler = null): array {
        return $this->deliver(event_type::SYNTHESIS_REQUESTED, ['section_id' => $this->sections[$section]], $handler);
    }

    /**
     * Give the student a grade in the assignment and let the real handlers analyse performance and frequency.
     *
     * @param \stdClass $student
     * @param float $grade
     */
    private function grade(\stdClass $student, float $grade): void {
        global $DB;
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assign->id, 0, ['userid' => $student->id, 'rawgrade' => $grade]);
        $itemid = (int) $DB->get_field('grade_items', 'id', [
            'courseid' => $this->course->id, 'itemmodule' => 'assign', 'iteminstance' => $this->assign->id, 'itemnumber' => 0,
        ]);
        $this->deliver(
            event_type::GRADE_CHANGED,
            ['userid' => (int) $student->id, 'itemid' => $itemid, 'occurred_at' => ++self::$occurred],
            new performance_handler()
        );
        $this->deliver(event_type::PARTICIPATION_CHANGED, [
            'cmid' => (int) $this->assign->cmid, 'userid' => (int) $student->id, 'source' => 'grade', 'occurred_at' => ++self::$occurred,
        ], new frequency_handler(null, null, null, null, null, null, null, null, fn() => time()));
    }

    /**
     * A snapshot of the forum, as the forum analysis would save it.
     *
     * @param string $summary
     * @param \stdClass|null $forum
     * @param array $difficulties
     */
    private function forum_snapshot(string $summary, ?\stdClass $forum = null, array $difficulties = []): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        (new forum_analysis_repository())->save(
            (int) $this->course->id,
            (int) ($forum ?? $this->forum)->cmid,
            (int) $generator->create_processing()->id,
            $summary,
            $difficulties
        );
    }

    /**
     * Versions of a section, newest first, as numbers.
     *
     * @param int $section
     * @return int[]
     */
    private function versions(int $section = 0): array {
        return array_map(
            fn($row) => (int) $row->version,
            array_values($this->repository->find_history((int) $this->course->id, $this->sections[$section]))
        );
    }

    /**
     * Q1 and Q4: new information makes the first version, version 1, linked to the processing that made it; the port is called once, with
     * the aggregates only, and the language is recorded with the context of the processing.
     */
    public function test_the_first_synthesis_is_version_one_linked_to_its_processing(): void {
        $this->grade($this->students[0], 80.0);
        $this->grade($this->students[1], 60.0);
        $this->forum_snapshot('Resumo do fórum');

        [$result, $processing] = $this->synthesize();

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertSame([1], $this->versions());
        $row = $this->repository->find_current((int) $this->course->id, $this->sections[0]);
        $this->assertSame('[SIMULADO] Síntese pedagógica.', $row->content);
        $this->assertSame((int) $this->repository->find_by_processing((int) $processing->id)->id, (int) $row->analysis_id);
        $this->assertCount(1, $this->sent);
        $this->assertSame('pedagogical_synthesis', $this->sent[0]->usecase->value);
        $this->assertSame(['comparable_set'], array_keys($this->sent[0]->data));
        $this->assertStringContainsString("'pt_br'", $this->instructions[0], 'The instruction is in the generation language (DEC-46)');
        $context = json_decode($this->events->find_context((int) $processing->id)->context_json, true);
        $this->assertSame('pt_br', $context['language']);
        $this->assertSame($this->sections[0], $context['section_id']);
    }

    /**
     * Q6: what is sent holds only aggregates and texts already produced: no identifier, name or e-mail of a student, no individual grade
     * and nothing of the criteria.
     */
    public function test_what_is_sent_has_only_aggregates_and_texts(): void {
        $this->grade($this->students[0], 80.0);
        $this->grade($this->students[1], 60.0);
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $this->students[0]->id,
        ]);
        $this->forum_snapshot('Resumo do fórum', null, [['discussion_id' => (int) $discussion->id, 'description' => 'Dúvida sobre prazos']]);

        $this->synthesize();

        $json = json_encode($this->sent[0]->data, JSON_UNESCAPED_UNICODE);
        foreach (['Maria', 'Souza', 'Joao', 'Lima', 'example.com', 'userid', 'student', 'criteria', 'firstname', 'email'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $json);
        }
        $set = $this->sent[0]->data['comparable_set'];
        $this->assertEquals(70.0, $set['activities'][0]['mean']);
        $this->assertEquals([80.0, 60.0], [$set['activities'][0]['maximum'], $set['activities'][0]['minimum']]);
        $this->assertSame([2, 0], [$set['activities'][0]['valid'], $set['activities'][0]['no_data']]);
        $this->assertSame('Resumo do fórum', $set['forums'][0]['summary']);
        $this->assertSame('Dúvida sobre prazos', $set['forums'][0]['difficulties'][0]['description']);
        $this->assertSame([], array_diff(array_keys($set), ['activities', 'frequency', 'questionnaires', 'forums', 'materials']));
        $this->assertStringNotContainsString('"grades"', $json);
    }

    /**
     * Q2 and Q5: the same information again makes no call to the LLM and no version, even when the grades are written again with the same
     * values and the analyses are made anew.
     */
    public function test_the_same_information_makes_no_call_and_no_version(): void {
        global $DB;
        $this->grade($this->students[0], 80.0);
        $this->synthesize();
        $analyses = $DB->count_records('local_assistente_analysis', ['analysis_type' => 'section_synthesis']);

        $this->grade($this->students[0], 80.0);
        [$result, $processing] = $this->synthesize();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('unchanged', (string) $result->message);
        $this->assertCount(1, $this->sent, 'No second call');
        $this->assertSame([1], $this->versions());
        $this->assertSame($analyses, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'section_synthesis']));
        $this->assertNull($this->events->find_context((int) $processing->id), 'A processing with no version leaves no context');
    }

    /**
     * Q8 and the history: a change in the information (a new summary of the forum) makes a new version; the older one stays as it was.
     */
    public function test_a_change_makes_a_new_version_and_keeps_the_history(): void {
        $this->grade($this->students[0], 80.0);
        $this->forum_snapshot('Resumo do fórum');
        $this->synthesize();

        $this->forum_snapshot('Resumo novo, depois de uma postagem');
        [$result] = $this->synthesize();

        $this->assertTrue($result->success);
        $this->assertSame([2, 1], $this->versions());
        $this->assertCount(2, $this->sent);
        $first = $this->repository->find_version((int) $this->course->id, $this->sections[0], 1);
        $second = $this->repository->find_version((int) $this->course->id, $this->sections[0], 2);
        $this->assertNotSame($first->input_fingerprint, $second->input_fingerprint);
        $this->assertSame('[SIMULADO] Síntese pedagógica.', $first->content);
    }

    /**
     * The comparison is with the current version only: information that goes back to the value of an older version is a change.
     */
    public function test_going_back_to_an_older_value_is_a_new_version(): void {
        $this->grade($this->students[0], 80.0);
        $this->synthesize();
        $this->grade($this->students[0], 60.0);
        $this->synthesize();
        $this->grade($this->students[0], 80.0);

        [$result] = $this->synthesize();

        $this->assertTrue($result->success);
        $this->assertSame([3, 2, 1], $this->versions());
        $first = $this->repository->find_version((int) $this->course->id, $this->sections[0], 1);
        $third = $this->repository->find_version((int) $this->course->id, $this->sections[0], 3);
        $this->assertSame($first->input_fingerprint, $third->input_fingerprint);
    }

    /**
     * Q3: a failure of the LLM (timeout, or an answer that does not fit the contract) makes no version and no context, and the current
     * version stays.
     */
    public function test_a_failure_of_the_llm_makes_no_version_and_keeps_the_current_one(): void {
        global $DB;
        $this->grade($this->students[0], 80.0);
        $this->synthesize();
        $this->grade($this->students[0], 50.0);

        foreach ([
            [(new fake_adapter())->fail_with('timeout', 'simulated timeout'), attempt_failure::TIMEOUT],
            [(new fake_adapter())->respond_with(['use_case' => 'pedagogical_synthesis', 'status' => 'valid', 'data' => ['wrong' => 1]]), attempt_failure::INVALID_RESPONSE],
        ] as [$adapter, $class]) {
            [$result, $processing] = $this->synthesize(0, $this->handler_with($adapter));

            $this->assertFalse($result->success);
            $this->assertSame($class, $result->failure, 'The class of the failure reaches the attempt');
            $this->assertSame([1], $this->versions());
            $this->assertNull($this->events->find_context((int) $processing->id));
        }
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'section_synthesis']));
        $this->assertSame('[SIMULADO] Síntese pedagógica.', $this->repository->find_current((int) $this->course->id, $this->sections[0])->content);
    }

    /**
     * Q11: a section with no pedagogical result gets no synthesis and no call, and the response records that it waits; when a result
     * appears the synthesis is made, with the frequency as no data where there is none.
     */
    public function test_a_section_without_a_result_waits_and_is_made_when_one_appears(): void {
        [$result, $processing] = $this->synthesize();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('pending', (string) $result->message);
        $this->assertSame([], $this->versions());
        $this->assertSame([], $this->sent);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id));

        $this->forum_snapshot('Resumo do fórum');
        [$later] = $this->synthesize();

        $this->assertTrue($later->success);
        $this->assertSame([1], $this->versions());
        $set = $this->sent[0]->data['comparable_set'];
        $this->assertSame('no_data', $set['frequency']['state']);
        $this->assertSame('no_data', $set['activities'][0]['state']);
    }

    /**
     * Q12: a section that is not of the course, a section that does not exist and an event without a section make no synthesis and no
     * call; the reason is recorded in the failed attempt.
     */
    public function test_an_invalid_section_makes_no_synthesis(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $foreign = (int) $DB->get_field('course_sections', 'id', ['course' => $other->id, 'section' => 1]);

        foreach ([$foreign, 987654] as $sectionid) {
            [$result] = $this->deliver(event_type::SYNTHESIS_REQUESTED, ['section_id' => $sectionid]);
            $this->assertFalse($result->success);
            $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
            $this->assertStringContainsString('section_invalid', (string) $result->message);
        }
        [$missing] = $this->deliver(event_type::SYNTHESIS_REQUESTED, ['section_id' => 0]);

        $this->assertFalse($missing->success);
        $this->assertSame([], $this->sent);
        $this->assertSame(0, $DB->count_records('local_assistente_pedagogical_synthesis'));
    }

    /**
     * Q13: a change in one section never changes another: the synthesis of the second section keeps its version while the first one
     * gets a new one.
     */
    public function test_sections_are_independent(): void {
        $second = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id], ['section' => 2]);
        $this->grade($this->students[0], 80.0);
        $this->forum_snapshot('Resumo do segundo fórum', $second);
        $this->synthesize(0);
        $this->synthesize(1);
        $before = $this->repository->find_current((int) $this->course->id, $this->sections[1]);

        $this->grade($this->students[0], 40.0);
        $this->synthesize(0);
        [$again] = $this->synthesize(1);

        $this->assertSame([2, 1], $this->versions(0));
        $this->assertSame([1], $this->versions(1));
        $this->assertStringContainsString('unchanged', (string) $again->message);
        $after = $this->repository->find_current((int) $this->course->id, $this->sections[1]);
        $this->assertSame([(int) $before->id, $before->input_fingerprint], [(int) $after->id, $after->input_fingerprint]);
        $setb = $this->sent[1]->data['comparable_set'];
        $this->assertSame('Resumo do segundo fórum', $setb['forums'][0]['summary']);
        $this->assertSame('no_data', $setb['activities'], 'The grades of the first section are not in the second');
    }

    /**
     * Q14: the answer is kept as text, exactly as it came; HTML is not interpreted or cleaned here.
     */
    public function test_the_answer_is_kept_as_text(): void {
        $this->grade($this->students[0], 80.0);
        $html = '<b>Síntese</b> <script>alert(1)</script> & mais';
        $adapter = (new fake_adapter())->respond_valid(['content' => $html]);

        $this->synthesize(0, $this->handler_with($adapter));

        $this->assertSame($html, $this->repository->find_current((int) $this->course->id, $this->sections[0])->content);
    }

    /**
     * Idempotent: the same processing handled twice has one version.
     */
    public function test_the_same_processing_is_idempotent(): void {
        $this->grade($this->students[0], 80.0);
        [, $processing, $event] = $this->synthesize();

        $again = $this->handler->handle($event, $processing);

        $this->assertTrue($again->success);
        $this->assertSame([1], $this->versions());
        $this->assertCount(1, $this->sent);
    }

    /**
     * Concurrency: if another processing saves a version with the same information while this one waits for the answer of the LLM, the
     * comparison under the lock finds it, and this one makes no redundant version and leaves no context.
     */
    public function test_a_concurrent_writer_of_the_same_information_makes_no_redundant_version(): void {
        $this->grade($this->students[0], 80.0);
        $repository = $this->repository;
        $courseid = (int) $this->course->id;
        $sectionid = $this->sections[0];
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $racing = new class ($repository, $courseid, $sectionid, $generator) implements llm_port {
            /**
             * Constructor.
             *
             * @param synthesis_repository $repository
             * @param int $courseid
             * @param int $sectionid
             * @param object $generator
             */
            public function __construct(
                private synthesis_repository $repository,
                private int $courseid,
                private int $sectionid,
                private object $generator
            ) {
            }

            #[\Override]
            public function evaluate(llm_context $context, string $instruction): structured_result {
                // Another processing of the same section finishes first, with the same information.
                $this->repository->save(
                    $this->courseid,
                    $this->sectionid,
                    (int) $this->generator->create_processing()->id,
                    'Escrita pelo outro processamento',
                    hash('sha256', fingerprint::canonical_json($context->data['comparable_set']))
                );
                return (new fake_adapter())->evaluate($context, $instruction);
            }
        };

        [$result, $processing] = $this->synthesize(0, new synthesis_handler($racing));

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertStringContainsString('unchanged', (string) $result->message);
        $this->assertSame([1], $this->versions());
        $this->assertSame('Escrita pelo outro processamento', $this->repository->find_current($courseid, $sectionid)->content);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id));
        $this->assertNull($this->events->find_context((int) $processing->id));
    }

    /**
     * A failure of the persistence in the middle (a trigger refuses the version after its context was written) rolls back the analysis, the
     * version and the context together, and the retry makes the version.
     */
    public function test_a_persistence_failure_rolls_back_everything_and_the_retry_succeeds(): void {
        global $DB;
        $this->grade($this->students[0], 80.0);
        $table = $DB->get_prefix() . 'local_assistente_pedagogical_synthesis';
        try {
            // One statement only (Moodle refuses several): the content becomes NULL, which the column does not accept.
            $DB->execute("CREATE TRIGGER t097_refuse BEFORE INSERT ON {$table} FOR EACH ROW SET NEW.content = NULL");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }
        try {
            [$result, $processing, $event] = $this->synthesize();
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t097_refuse');
        }

        $this->assertFalse($result->success);
        $this->assertSame([], $this->versions());
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'section_synthesis']));
        $this->assertNull($this->events->find_context((int) $processing->id));

        $retry = $this->handler->handle($event, $processing);

        $this->assertTrue($retry->success, (string) $retry->message);
        $this->assertSame([1], $this->versions());
        $this->assertNotNull($this->events->find_context((int) $processing->id));
    }

    /**
     * The questionnaires of the section enter the set with the coverage of the current analysis.
     */
    public function test_the_questionnaires_of_the_section_enter_the_set(): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id], ['section' => 1]);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        (new quiz_initial_repository())->save(
            (int) $this->course->id,
            (int) $quiz->cmid,
            (int) $generator->create_processing()->id,
            0.7,
            [['question_id' => 11, 'question_version' => 1, 'complexity' => 0.3]]
        );

        $this->synthesize();

        $set = $this->sent[0]->data['comparable_set'];
        $this->assertSame((int) $quiz->cmid, $set['questionnaires'][0]['cmid']);
        $this->assertEquals(0.7, $set['questionnaires'][0]['coverage']);
        $this->assertEquals(0.3, $set['questionnaires'][0]['questions'][0]['complexity']);
        $this->assertSame([1], $this->versions());
    }

    /**
     * Through the real queue: the request is processed by the default registry, and the section gets its first version.
     */
    public function test_the_real_queue_makes_the_synthesis(): void {
        global $DB;
        $this->grade($this->students[0], 80.0);
        (new \local_assistente\application\event_service())->enqueue(
            (int) $this->course->id,
            event_type::SYNTHESIS_REQUESTED,
            ['section_id' => $this->sections[0]]
        );

        (new queue_processor(null, handler_registry::default()))->run_cycle();

        $this->assertSame([1], $this->versions());
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => 'synthesis_requested', 'status' => 'pending']));
    }
}
