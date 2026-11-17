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

namespace local_assistente\infrastructure\llm;

use local_assistente\application\llm\llm_result_adapter;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\cycle_outcome;
use local_assistente\application\queue\event_handler;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\handler_result;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the simulated LLM adapter and of how its failures reach the queue (quickstart 009 Q1, Q3, Q6 to Q8; DEC-32 D3 and D4).
 *
 * No external service is called. The queue tests need real transactions and locks, so they run with the data reset by
 * deletion, not by rollback.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(fake_adapter::class)]
#[CoversClass(\local_assistente\application\llm\llm_result_adapter::class)]
final class fake_adapter_test extends \advanced_testcase {
    /**
     * Real transactions and locks are used by some tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
    }

    /**
     * Quickstart 009 Q1: with nothing scripted, every use gets a valid simulated answer, marked as simulated.
     */
    public function test_default_answers_are_valid_and_marked_as_simulated(): void {
        $adapter = new fake_adapter();
        $summary = $adapter->evaluate(new llm_context(llm_use_case::MATERIAL_SUMMARY, ['text' => 't']), 'Summarise');
        $coverage = $adapter->evaluate(new llm_context(llm_use_case::QUIZ_COVERAGE), 'Cover');
        $complexity = $adapter->evaluate(new llm_context(llm_use_case::QUESTION_COMPLEXITY), 'Complex');
        $forum = $adapter->evaluate(new llm_context(llm_use_case::FORUM_ANALYSIS, ['discussion_ids' => [1]]), 'Forum');
        $synthesis = $adapter->evaluate(new llm_context(llm_use_case::PEDAGOGICAL_SYNTHESIS), 'Synth');

        foreach ([$summary, $coverage, $complexity, $forum, $synthesis] as $result) {
            $this->assertTrue($result->is_valid(), $result->usecase->value);
        }
        $this->assertStringStartsWith('[SIMULADO]', $summary->data['summary']);
        $this->assertStringStartsWith('[SIMULADO]', $forum->data['forum_summary']);
        $this->assertStringStartsWith('[SIMULADO]', $synthesis->data['content']);
        $this->assertEqualsWithDelta(0.5, $coverage->data['coverage'], 1e-9);
        $this->assertSame(5, $adapter->calls());
        $this->assertSame('Summarise', $adapter->requests[0]['instruction']);
    }

    /**
     * Scripted behaviours are consumed in order, and the default comes back when the script ends.
     */
    public function test_script_is_consumed_in_order(): void {
        $adapter = (new fake_adapter())
            ->respond_valid(['coverage' => 0.9])
            ->fail_with('timeout')
            ->respond_valid(['coverage' => 0.1]);
        $context = new llm_context(llm_use_case::QUIZ_COVERAGE);

        $first = $adapter->evaluate($context, 'i');
        $second = $adapter->evaluate($context, 'i');
        $third = $adapter->evaluate($context, 'i');
        $default = $adapter->evaluate($context, 'i');

        $this->assertEqualsWithDelta(0.9, $first->data['coverage'], 1e-9);
        $this->assertSame('timeout', $second->errorcode);
        $this->assertEqualsWithDelta(0.1, $third->data['coverage'], 1e-9);
        $this->assertEqualsWithDelta(0.5, $default->data['coverage'], 1e-9);
    }

    /**
     * Quickstart 009 Q6 and Q7 through the adapter: bad answers are rejected by the validation and nothing is handed over.
     */
    public function test_scripted_bad_answers_are_rejected_by_the_validation(): void {
        $context = new llm_context(llm_use_case::QUIZ_COVERAGE);
        $adapter = new fake_adapter();

        foreach ([1.1, -0.1, 0.35] as $bad) {
            $adapter->respond_valid(['coverage' => $bad]);
            $result = $adapter->evaluate($context, 'i');
            $this->assertSame('invalid_response', $result->errorcode, (string) $bad);
            $this->assertNull($result->data);
        }

        $adapter->respond_with(['use_case' => 'question_complexity', 'status' => 'valid', 'data' => ['coverage' => 0.5]]);
        $this->assertSame('invalid_response', $adapter->evaluate($context, 'i')->errorcode, 'Another use than the requested');
        $adapter->respond_with(['use_case' => 'quiz_coverage', 'status' => 'valid', 'data' => []]);
        $this->assertSame('invalid_response', $adapter->evaluate($context, 'i')->errorcode, 'Missing field');
        $adapter->respond_with('not an object');
        $this->assertSame('invalid_response', $adapter->evaluate($context, 'i')->errorcode, 'Does not decode');
    }

    /**
     * A failure class that is not one of the contract cannot be scripted.
     */
    public function test_unknown_failure_class_cannot_be_scripted(): void {
        $this->expectException(\InvalidArgumentException::class);
        (new fake_adapter())->fail_with('technical');
    }

    /**
     * A crash is an exception, to test that the caller survives it.
     */
    public function test_crash(): void {
        $adapter = (new fake_adapter())->crash_with(new \RuntimeException('boom'));
        $this->expectException(\RuntimeException::class);
        $adapter->evaluate(new llm_context(llm_use_case::QUIZ_COVERAGE), 'i');
    }

    /**
     * The port must not be called with a transaction open (spec-007): the adapter refuses.
     */
    public function test_call_with_an_open_transaction_is_refused(): void {
        global $DB;
        $adapter = new fake_adapter();
        $transaction = $DB->start_delegated_transaction();

        try {
            $adapter->evaluate(new llm_context(llm_use_case::QUIZ_COVERAGE), 'i');
            $this->fail('The call must be refused while a transaction is open');
        } catch (\coding_exception $e) {
            $this->assertSame(0, $adapter->calls(), 'Nothing was recorded or sent');
        } finally {
            $transaction->allow_commit();
        }
        $this->assertTrue($adapter->evaluate(new llm_context(llm_use_case::QUIZ_COVERAGE), 'i')->is_valid(), 'Allowed again without the transaction');
    }

    /**
     * A failed result becomes the failed attempt of the queue with the same class; a valid result is not a failure.
     */
    public function test_failed_result_maps_to_an_attempt_failure(): void {
        foreach (structured_result::ERROR_CODES as $code) {
            $result = structured_result::error(llm_use_case::MATERIAL_SUMMARY, $code, 'why');
            $mapped = llm_result_adapter::failure_of($result);

            $this->assertFalse($mapped->success);
            $this->assertSame(attempt_failure::from($code), $mapped->failure);
            $this->assertSame("{$code}: why", $mapped->response());
        }
        $this->expectException(\InvalidArgumentException::class);
        llm_result_adapter::failure_of(structured_result::valid(llm_use_case::MATERIAL_SUMMARY, ['summary' => 'x']));
    }

    /**
     * A handler that asks the simulated LLM, for the queue tests.
     *
     * @param fake_adapter $adapter
     * @return event_handler
     */
    private function llm_handler(fake_adapter $adapter): event_handler {
        return new class($adapter) implements event_handler {
            /**
             * Constructor.
             *
             * @param fake_adapter $adapter
             */
            public function __construct(
                /** @var fake_adapter the simulated LLM */
                private readonly fake_adapter $adapter
            ) {
            }

            #[\Override]
            public function handle(\stdClass $event, \stdClass $processing): handler_result {
                $result = $this->adapter->evaluate(new llm_context(llm_use_case::MATERIAL_SUMMARY, ['text' => 'conteudo']), 'Summarise');
                return $result->is_valid() ? handler_result::success('summary saved') : llm_result_adapter::failure_of($result);
            }
        };
    }

    /**
     * An enabled course with one pending event, and a processor whose only handler asks the simulated LLM.
     *
     * @param fake_adapter $adapter
     * @return array [event, processor]
     */
    private function queue_with(fake_adapter $adapter): array {
        $course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($course->id, true);
        $event = (new event_repository())->create_event($course->id, event_type::QUIZ_PUBLISHED, ['cmid' => 1], 100);
        $registry = new handler_registry();
        $registry->register(event_type::QUIZ_PUBLISHED, $this->llm_handler($adapter));
        return [$event, new queue_processor(null, $registry, null, new unit_of_work(1), fn() => 2000)];
    }

    /**
     * Quickstart 009 Q3: a timeout is recorded as a failed attempt of the queue, with its class, and the event goes back to
     * pending.
     */
    public function test_timeout_is_a_failed_attempt(): void {
        global $DB;
        $adapter = (new fake_adapter())->fail_with('timeout', 'no answer in time');
        [$event, $processor] = $this->queue_with($adapter);

        $report = $processor->run_cycle();

        $this->assertSame(1, $report->count(cycle_outcome::RETRY_LATER));
        $attempt = $DB->get_record('local_assistente_processing_attempt', [], '*', MUST_EXIST);
        $this->assertSame('failure', $attempt->status);
        $this->assertSame('timeout: no answer in time', $attempt->response);
        $this->assertSame(attempt_failure::TIMEOUT, attempt_failure::from_response($attempt->response));
        $this->assertSame('pending', $DB->get_field('local_assistente_event', 'status', ['id' => $event->id]));
    }

    /**
     * Quickstart 009 Q8: each class of failure is recorded in its attempt, with no state created beyond the four.
     */
    public function test_each_failure_class_is_recorded(): void {
        global $DB;
        foreach (structured_result::ERROR_CODES as $code) {
            $DB->delete_records('local_assistente_processing_attempt');
            $DB->delete_records('local_assistente_processing');
            $DB->delete_records('local_assistente_event');
            [$event, $processor] = $this->queue_with((new fake_adapter())->fail_with($code, 'simulated'));

            $processor->run_cycle();

            $attempt = $DB->get_record('local_assistente_processing_attempt', [], '*', MUST_EXIST);
            $this->assertSame(attempt_failure::from($code), attempt_failure::from_response($attempt->response), $code);
            $this->assertContains($DB->get_field('local_assistente_event', 'status', ['id' => $event->id]), ['pending', 'processing', 'processed', 'failure']);
        }
    }

    /**
     * Quickstart 009 Q8: after the third failed attempt the event is in failure, whatever the classes of the failures.
     */
    public function test_event_fails_after_the_third_failed_attempt(): void {
        global $DB;
        $adapter = (new fake_adapter())->fail_with('timeout')->fail_with('rate_limit')->fail_with('auth');
        [$event, $processor] = $this->queue_with($adapter);

        $processor->run_cycle();
        $processor->run_cycle();
        $last = $processor->run_cycle();
        $after = $processor->run_cycle();

        $this->assertSame(1, $last->count(cycle_outcome::FAILED));
        $this->assertSame([], $after->events(), 'A failed event is not tried again');
        $this->assertSame('failure', $DB->get_field('local_assistente_event', 'status', ['id' => $event->id]));
        $classes = array_map(
            static fn($a) => attempt_failure::from_response($a->response),
            array_values($DB->get_records('local_assistente_processing_attempt', [], 'attempt_number ASC'))
        );
        $this->assertSame([attempt_failure::TIMEOUT, attempt_failure::RATE_LIMIT, attempt_failure::AUTH], $classes);
        $this->assertSame(3, $adapter->calls());
    }

    /**
     * The LLM is called outside any transaction when it is the queue that calls it (the guard of the adapter would have refused).
     */
    public function test_the_queue_calls_the_llm_outside_a_transaction_and_succeeds(): void {
        global $DB;
        $adapter = new fake_adapter();
        [$event, $processor] = $this->queue_with($adapter);

        $report = $processor->run_cycle();

        $this->assertSame(1, $report->count(cycle_outcome::PROCESSED));
        $this->assertSame(1, $adapter->calls());
        $this->assertSame('summary saved', $DB->get_field('local_assistente_event', 'response', ['id' => $event->id]));
    }
}
