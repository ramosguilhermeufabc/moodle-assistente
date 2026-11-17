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

namespace local_assistente\infrastructure\persistence;

use local_assistente\domain\quiz\aggregate_metrics;
use local_assistente\domain\quiz\question_metrics;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the repository of the results of a questionnaire (T071; spec-011 data-model; quickstart 011 Q11, Q12, Q13 at persistence
 * level).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(quiz_results_repository::class)]
final class quiz_results_repository_test extends \advanced_testcase {
    /** @var quiz_results_repository */
    private quiz_results_repository $repository;

    /** @var \local_assistente_generator */
    private \local_assistente_generator $generator;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var int[] questionnaire_question ids of the initial analysis of cmid 77, by question_id */
    private array $analysed;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback. A course with the initial analysis of the
     * questionnaire 77, with the questions 11 and 12.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new quiz_results_repository();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $this->course = $this->getDataGenerator()->create_course();
        $this->analysed = $this->initial((int) $this->course->id, 77, [11, 12]);
    }

    /**
     * An initial analysis, to have the analysed rows that the results point to.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int[] $questionids
     * @return int[] questionnaire_question id by question id
     */
    private function initial(int $courseid, int $cmid, array $questionids): array {
        $initial = new quiz_initial_repository();
        $id = $initial->save($courseid, $cmid, (int) $this->generator->create_processing()->id, 0.5, array_map(
            static fn(int $q) => ['question_id' => $q, 'question_version' => 1, 'complexity' => 0.5],
            $questionids
        ));
        $rows = [];
        foreach ($initial->find_questions($id) as $row) {
            $rows[(int) $row->question_id] = (int) $row->id;
        }
        return $rows;
    }

    /**
     * A result of a question.
     *
     * @param int $questionid
     * @param int $version version answered
     * @param float $average
     * @return array
     */
    private function question_row(int $questionid, int $version = 1, float $average = 50.0): array {
        return [
            'questionnaire_question_id' => $this->analysed[$questionid],
            'question_id' => $questionid,
            'question_version' => $version,
            'metrics' => new question_metrics($average, 1, 1, 66.666),
        ];
    }

    /**
     * Normal case: the analysis, its results and its aggregate are written and read back.
     */
    public function test_saves_and_reads_the_results_and_the_aggregate(): void {
        $processing = $this->generator->create_processing();

        $id = $this->repository->save(
            (int) $this->course->id,
            77,
            (int) $processing->id,
            new aggregate_metrics(80.0, 16.329, 60.0, 100.0),
            [$this->question_row(11), $this->question_row(12, 1, 100.0)],
            'resumo',
            1000
        );

        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame($id, (int) $analysis->id);
        $this->assertSame('quiz_results', $analysis->analysis_type);
        $this->assertSame(77, (int) $analysis->cmid);
        $this->assertSame(1000, (int) $analysis->created_at);
        $metric = $this->repository->find_metric($id);
        $this->assertEquals([80.0, 16.329, 60.0, 100.0], [$metric->mean, $metric->population_standard_deviation, $metric->minimum, $metric->maximum]);
        $rows = array_values($this->repository->find_question_results($id));
        $this->assertSame([11, 12], array_map(static fn($r) => (int) $r->question_id, $rows));
        $this->assertEquals(50.0, $rows[0]->average_correctness);
        $this->assertSame(1, (int) $rows[0]->correct_count);
        $this->assertSame(1, (int) $rows[0]->incorrect_count);
        $this->assertEquals(66.666, $rows[0]->partial_result);
        $this->assertSame($this->analysed[11], (int) $rows[0]->questionnaire_question_id);
    }

    /**
     * Q13: no_data is the absence of the rows, never a zero; a real zero is a row.
     */
    public function test_no_data_is_the_absence_of_rows_and_a_real_zero_is_a_row(): void {
        $nodata = $this->generator->create_processing();
        $zero = $this->generator->create_processing();

        $a = $this->repository->save((int) $this->course->id, 77, (int) $nodata->id, null, []);
        $b = $this->repository->save((int) $this->course->id, 77, (int) $zero->id, new aggregate_metrics(0.0, 0.0, 0.0, 0.0), [
            ['questionnaire_question_id' => $this->analysed[11], 'question_id' => 11, 'question_version' => 1,
             'metrics' => new question_metrics(0.0, 0, 2, null)],
        ]);

        $this->assertNull($this->repository->find_metric($a), 'No student with a result: no aggregate row');
        $this->assertSame([], $this->repository->find_question_results($a));
        $this->assertEquals(0.0, $this->repository->find_metric($b)->mean, 'A real zero is stored');
        $row = array_values($this->repository->find_question_results($b))[0];
        $this->assertEquals(0.0, $row->average_correctness);
        $this->assertNull($row->partial_result, 'A single-answer question has no partial result');
    }

    /**
     * Q11: the version answered may differ from the analysed one; the row points to the analysed row of the same bank entry.
     */
    public function test_answered_version_may_differ_from_the_analysed_one(): void {
        $processing = $this->generator->create_processing();

        $id = $this->repository->save((int) $this->course->id, 77, (int) $processing->id, null, [$this->question_row(11, 2)]);

        $row = array_values($this->repository->find_question_results($id))[0];
        $this->assertSame(2, (int) $row->question_version);
        $this->assertSame($this->analysed[11], (int) $row->questionnaire_question_id);
    }

    /**
     * Each execution is a new immutable analysis; the current one is the latest, and the history is kept.
     */
    public function test_current_is_the_latest_and_the_history_is_kept(): void {
        $one = $this->repository->save((int) $this->course->id, 77, (int) $this->generator->create_processing()->id, null, [], null, 100);
        $two = $this->repository->save((int) $this->course->id, 77, (int) $this->generator->create_processing()->id, null, [], null, 200);

        $this->assertSame($two, (int) $this->repository->find_current((int) $this->course->id, 77)->id);
        $this->assertSame([$two, $one], array_map(
            static fn($r) => (int) $r->id,
            array_values($this->repository->find_history((int) $this->course->id, 77))
        ));
        $this->assertCount(0, $this->repository->find_question_results($one), 'The first one is untouched');
    }

    /**
     * Idempotency: a second save for the same processing is rejected and the first is untouched.
     */
    public function test_second_save_for_the_same_processing_is_rejected(): void {
        global $DB;
        $processing = $this->generator->create_processing();
        $first = $this->repository->save((int) $this->course->id, 77, (int) $processing->id, null, [$this->question_row(11)]);

        try {
            $this->repository->save((int) $this->course->id, 77, (int) $processing->id, null, [$this->question_row(12)]);
            $this->fail('A second analysis was saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));
            $this->assertSame([11], array_map(
                static fn($r) => (int) $r->question_id,
                array_values($this->repository->find_question_results($first))
            ));
        }
    }

    /**
     * Invalid values and references write nothing.
     *
     * @param \Closure $build builds the arguments from the test case: returns [aggregate, questions]
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_inputs')]
    public function test_invalid_input_writes_nothing(\Closure $build): void {
        global $DB;
        $processing = $this->generator->create_processing();
        [$aggregate, $questions] = $build($this);

        try {
            $this->repository->save((int) $this->course->id, 77, (int) $processing->id, $aggregate, $questions);
            $this->fail('The invalid results were saved');
        } catch (invalid_domain_value_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));
            $this->assertSame(0, $DB->count_records('local_assistente_question_result'));
            $this->assertSame(0, $DB->count_records('local_assistente_questionnaire_metric'));
        }
    }

    /**
     * Invalid inputs.
     *
     * @return array
     */
    public static function invalid_inputs(): array {
        return [
            'aggregate above 100' => [fn($t) => [new aggregate_metrics(101.0, 0.0, 0.0, 0.0), []]],
            'negative minimum' => [fn($t) => [new aggregate_metrics(1.0, 0.0, -1.0, 0.0), []]],
            'average above 100' => [fn($t) => [null, [$t->question_row(11, 1, 100.5)]]],
            'metrics missing' => [fn($t) => [null, [['questionnaire_question_id' => $t->analysed[11], 'question_id' => 11, 'question_version' => 1]]]],
            'question id zero' => [fn($t) => [null, [['questionnaire_question_id' => $t->analysed[11], 'question_id' => 0, 'question_version' => 1,
                'metrics' => new question_metrics(1.0, 0, 0, null)]]]],
            'analysed row does not exist' => [fn($t) => [null, [['questionnaire_question_id' => 987654, 'question_id' => 11,
                'question_version' => 1, 'metrics' => new question_metrics(1.0, 0, 0, null)]]]],
        ];
    }

    /**
     * Isolation: an analysed row of another questionnaire or course is refused, and reads are scoped by course and questionnaire.
     */
    public function test_references_and_reads_are_isolated_by_course_and_questionnaire(): void {
        $other = $this->getDataGenerator()->create_course();
        $foreign = $this->initial((int) $other->id, 77, [11]);
        $sibling = $this->initial((int) $this->course->id, 88, [11]);
        $processing = $this->generator->create_processing();

        foreach ([$foreign, $sibling] as $rows) {
            try {
                $this->repository->save((int) $this->course->id, 77, (int) $processing->id, null, [[
                    'questionnaire_question_id' => $rows[11], 'question_id' => 11, 'question_version' => 1,
                    'metrics' => new question_metrics(1.0, 0, 0, null),
                ]]);
                $this->fail('A row of another questionnaire was accepted');
            } catch (invalid_domain_value_exception) {
                $this->assertNull($this->repository->find_by_processing((int) $processing->id));
            }
        }
        $this->repository->save((int) $this->course->id, 77, (int) $processing->id, null, [$this->question_row(11)]);
        $this->assertNotNull($this->repository->find_current((int) $this->course->id, 77));
        $this->assertNull($this->repository->find_current((int) $other->id, 77), 'Another course sees nothing');
        $this->assertNull($this->repository->find_current((int) $this->course->id, 88), 'Another questionnaire sees nothing');
        $this->assertSame([], $this->repository->find_history((int) $this->course->id, 88));
    }

    /**
     * The same question and version twice is rejected, with nothing written.
     */
    public function test_repeated_question_version_is_rejected(): void {
        global $DB;
        $processing = $this->generator->create_processing();

        try {
            $this->repository->save((int) $this->course->id, 77, (int) $processing->id, null, [$this->question_row(11), $this->question_row(11)]);
            $this->fail('A repeated question was saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));
        }
    }

    /**
     * A failure in the middle of the writes rolls everything back: a database trigger makes the database refuse the second question
     * row, after the analysis, the aggregate and the first row were written.
     */
    public function test_failure_midway_rolls_back_everything(): void {
        global $DB;
        $processing = $this->generator->create_processing();
        $table = $DB->get_prefix() . 'local_assistente_question_result';
        try {
            // One statement only (Moodle refuses several): the row of question 12 gets NULL in a NOT NULL column.
            $DB->execute("CREATE TRIGGER t071_refuse BEFORE INSERT ON {$table} FOR EACH ROW
                SET NEW.question_version = IF(NEW.question_id = 12, NULL, NEW.question_version)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }

        try {
            $this->repository->save(
                (int) $this->course->id,
                77,
                (int) $processing->id,
                new aggregate_metrics(50.0, 0.0, 50.0, 50.0),
                [$this->question_row(11), $this->question_row(12)]
            );
            $saved = true;
        } catch (\dml_exception) {
            $saved = false;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t071_refuse');
        }

        $this->assertFalse($saved, 'The trigger must have refused the second question');
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));
        $this->assertSame(0, $DB->count_records('local_assistente_questionnaire_metric'));
        $this->assertSame(0, $DB->count_records('local_assistente_question_result'));
    }

    /**
     * A processing that does not exist is a missing record, and nothing is written.
     */
    public function test_unknown_processing_is_rejected(): void {
        global $DB;

        $this->expectException(\dml_missing_record_exception::class);
        try {
            $this->repository->save((int) $this->course->id, 77, 987654, null, []);
        } finally {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_results']));
        }
    }

    /**
     * Missing records read as null or empty.
     */
    public function test_missing_records_read_as_null_or_empty(): void {
        $this->assertNull($this->repository->find_by_processing(424242));
        $this->assertNull($this->repository->find_metric(424242));
        $this->assertSame([], $this->repository->find_question_results(424242));
        $this->assertNull($this->repository->find_current(424242, 1));
    }
}
