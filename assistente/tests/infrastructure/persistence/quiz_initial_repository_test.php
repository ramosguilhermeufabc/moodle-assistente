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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the repository of the initial analysis of a questionnaire (T067; spec-010 data-model; quickstart 010 at persistence
 * level).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(quiz_initial_repository::class)]
final class quiz_initial_repository_test extends \advanced_testcase {
    /** @var quiz_initial_repository */
    private quiz_initial_repository $repository;

    /** @var \local_assistente_generator */
    private \local_assistente_generator $generator;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new quiz_initial_repository();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
    }

    /**
     * Questions of an analysis.
     *
     * @return array
     */
    private function questions(): array {
        return [
            ['question_id' => 11, 'question_version' => 1, 'complexity' => 0.3],
            ['question_id' => 12, 'question_version' => 2, 'complexity' => 1.0],
            ['question_id' => 12, 'question_version' => 1, 'complexity' => 0.0],
        ];
    }

    /**
     * Normal case: the three tables are written and read back.
     */
    public function test_saves_and_reads_the_whole_analysis(): void {
        $course = $this->getDataGenerator()->create_course();
        $processing = $this->generator->create_processing();

        $id = $this->repository->save((int) $course->id, 77, (int) $processing->id, 0.7, $this->questions(), 'resumo', 1000);

        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame($id, (int) $analysis->id);
        $this->assertSame('quiz_initial', $analysis->analysis_type);
        $this->assertSame(77, (int) $analysis->cmid);
        $this->assertSame((int) $course->id, (int) $analysis->course_id);
        $this->assertSame('resumo', $analysis->summary);
        $this->assertSame(1000, (int) $analysis->created_at);
        $this->assertEquals(0.7, $this->repository->find_questionnaire_analysis($id)->coverage);
        $rows = array_values($this->repository->find_questions($id));
        $this->assertCount(3, $rows);
        $this->assertSame([11, 12, 12], array_map(static fn($r) => (int) $r->question_id, $rows));
        $this->assertSame([1, 1, 2], array_map(static fn($r) => (int) $r->question_version, $rows));
        $this->assertEquals(0.0, $rows[1]->complexity);
        $this->assertEquals(1.0, $rows[2]->complexity);
    }

    /**
     * Coverage 0.0 with no question (no eligible material) is a valid analysis.
     */
    public function test_coverage_zero_without_questions(): void {
        $course = $this->getDataGenerator()->create_course();
        $processing = $this->generator->create_processing();

        $id = $this->repository->save((int) $course->id, 5, (int) $processing->id, 0.0, []);

        $this->assertEquals(0.0, $this->repository->find_questionnaire_analysis($id)->coverage);
        $this->assertSame([], $this->repository->find_questions($id));
    }

    /**
     * An invalid value writes nothing, in any of the three tables.
     *
     * @dataProvider invalid_values
     * @param float $coverage
     * @param array $questions
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_values')]
    public function test_invalid_values_write_nothing(float $coverage, array $questions): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $processing = $this->generator->create_processing();

        try {
            $this->repository->save((int) $course->id, 5, (int) $processing->id, $coverage, $questions);
            $this->fail('The invalid analysis was saved');
        } catch (invalid_domain_value_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis'));
            $this->assertSame(0, $DB->count_records('local_assistente_questionnaire_analysis'));
            $this->assertSame(0, $DB->count_records('local_assistente_questionnaire_question'));
        }
    }

    /**
     * Invalid coverage and complexity, and invalid question keys.
     *
     * @return array
     */
    public static function invalid_values(): array {
        $ok = ['question_id' => 1, 'question_version' => 1, 'complexity' => 0.5];
        return [
            'coverage above 1' => [1.1, []],
            'coverage negative' => [-0.1, []],
            'coverage not a tenth' => [0.55, []],
            'complexity above 1' => [0.5, [$ok, ['question_id' => 2, 'question_version' => 1, 'complexity' => 1.5]]],
            'complexity not a tenth' => [0.5, [['question_id' => 2, 'question_version' => 1, 'complexity' => 0.25]]],
            'complexity missing' => [0.5, [['question_id' => 2, 'question_version' => 1]]],
            'question id zero' => [0.5, [['question_id' => 0, 'question_version' => 1, 'complexity' => 0.5]]],
            'version zero' => [0.5, [['question_id' => 2, 'question_version' => 0, 'complexity' => 0.5]]],
        ];
    }

    /**
     * The same question and version twice is rejected, with nothing written.
     */
    public function test_repeated_question_version_is_rejected(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $processing = $this->generator->create_processing();
        $questions = [
            ['question_id' => 9, 'question_version' => 1, 'complexity' => 0.2],
            ['question_id' => 9, 'question_version' => 1, 'complexity' => 0.4],
        ];

        $this->expectException(duplicate_record_exception::class);
        try {
            $this->repository->save((int) $course->id, 5, (int) $processing->id, 0.5, $questions);
        } finally {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis'));
        }
    }

    /**
     * Idempotency: a second save for the same processing is rejected and the first analysis is untouched.
     */
    public function test_second_save_for_the_same_processing_is_rejected(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $processing = $this->generator->create_processing();
        $first = $this->repository->save((int) $course->id, 5, (int) $processing->id, 0.4, $this->questions());

        try {
            $this->repository->save((int) $course->id, 5, (int) $processing->id, 0.9, [
                ['question_id' => 99, 'question_version' => 1, 'complexity' => 0.9],
            ]);
            $this->fail('A second analysis was saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(1, $DB->count_records('local_assistente_analysis'));
            $this->assertEquals(0.4, $this->repository->find_questionnaire_analysis($first)->coverage);
            $this->assertCount(3, $this->repository->find_questions($first));
        }
    }

    /**
     * A failure in the middle of the writes rolls everything back: a database trigger makes the database refuse the second question row, after the
     * analysis, the coverage and the first question row were written.
     */
    public function test_failure_midway_rolls_back_everything(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $processing = $this->generator->create_processing();
        $table = $DB->get_prefix() . 'local_assistente_questionnaire_question';
        try {
            // One statement only (Moodle refuses several): the row of question 2 gets NULL in a NOT NULL column.
            $DB->execute("CREATE TRIGGER t067_refuse BEFORE INSERT ON {$table} FOR EACH ROW
                SET NEW.question_version = IF(NEW.question_id = 2, NULL, NEW.question_version)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }
        $questions = [
            ['question_id' => 1, 'question_version' => 1, 'complexity' => 0.2],
            ['question_id' => 2, 'question_version' => 1, 'complexity' => 0.2],
        ];

        try {
            $this->repository->save((int) $course->id, 5, (int) $processing->id, 0.5, $questions);
            $saved = true;
        } catch (\dml_exception) {
            $saved = false;
        } finally {
            $DB->execute("DROP TRIGGER IF EXISTS t067_refuse");
        }

        $this->assertFalse($saved, 'The trigger must have refused the second question');
        $this->assertSame(0, $DB->count_records('local_assistente_analysis'));
        $this->assertSame(0, $DB->count_records('local_assistente_questionnaire_analysis'));
        $this->assertSame(0, $DB->count_records('local_assistente_questionnaire_question'));
    }

    /**
     * A processing that does not exist is a missing record, and nothing is written.
     */
    public function test_unknown_processing_is_rejected(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\dml_missing_record_exception::class);
        try {
            $this->repository->save((int) $course->id, 5, 987654, 0.5, []);
        } finally {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis'));
        }
    }

    /**
     * Reads are scoped by course and questionnaire; missing records read as null or empty.
     */
    public function test_reads_are_isolated_by_course_and_questionnaire(): void {
        $a = $this->getDataGenerator()->create_course();
        $b = $this->getDataGenerator()->create_course();
        $p1 = $this->generator->create_processing();
        $p2 = $this->generator->create_processing();
        $p3 = $this->generator->create_processing();
        $one = $this->repository->save((int) $a->id, 10, (int) $p1->id, 0.1, [], null, 100);
        $two = $this->repository->save((int) $a->id, 10, (int) $p2->id, 0.2, [], null, 200);
        $this->repository->save((int) $a->id, 11, (int) $p3->id, 0.3, [], null, 300);

        $this->assertSame([$two, $one], array_map(static fn($r) => (int) $r->id, array_values(
            $this->repository->find_for_quiz((int) $a->id, 10)
        )), 'Newest first, only that questionnaire');
        $this->assertSame([], $this->repository->find_for_quiz((int) $b->id, 10), 'Another course sees nothing');
        $this->assertNull($this->repository->find_by_processing(424242));
        $this->assertNull($this->repository->find_questionnaire_analysis(424242));
        $this->assertSame([], $this->repository->find_questions(424242));
    }
}
