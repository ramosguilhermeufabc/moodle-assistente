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

use local_assistente\domain\event\event_type;
use local_assistente\domain\frequency\frequency_calculator;
use local_assistente\domain\frequency\frequency_result;
use local_assistente\domain\result_state;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the repository of the frequency (T086; spec-014 data-model; quickstart 014 Q4, Q13 at persistence level).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(frequency_repository::class)]
final class frequency_repository_test extends \advanced_testcase {
    /** @var frequency_repository */
    private frequency_repository $repository;

    /** @var event_repository */
    private event_repository $events;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass[] two students */
    private array $students;

    /** @var int[] section ids of the course (1 and 2) */
    private array $sections;

    /** @var int[] cmids of three activities */
    private array $activities;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback. A course with two sections, two students and three
     * activities.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new frequency_repository();
        $this->events = new event_repository();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $this->students = [
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
        ];
        $this->sections = array_map('intval', array_values($DB->get_fieldset_select('course_sections', 'id', 'course = ? AND section > 0', [$this->course->id], 'section')));
        $this->activities = [];
        foreach (range(1, 3) as $i) {
            $this->activities[] = (int) $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id])->cmid;
        }
    }

    /**
     * An event of participation and its processing.
     *
     * @return \stdClass the processing
     */
    private function processing(): \stdClass {
        static $occurred = 2000;
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::PARTICIPATION_CHANGED,
            ['cmid' => $this->activities[0], 'userid' => (int) $this->students[0]->id, 'source' => 'submission', 'occurred_at' => ++$occurred],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());
        return $this->events->find_processing((int) $event->id);
    }

    /**
     * A frequency result from the calculator: the listed activities, with the given ones taken part in; all of them past due.
     *
     * @param int[] $indexes indexes of the activities in the denominator
     * @param int[] $taken indexes of those in which the student took part
     * @return frequency_result
     */
    private function calculated(array $indexes, array $taken = []): frequency_result {
        $facts = array_map(fn($i) => [
            'activity_id' => $this->activities[$i], 'is_resource' => false, 'categories' => ['submission'], 'available' => true,
            'due_date' => 1000, 'participation' => ['submitted' => in_array($i, $taken, true)],
        ], $indexes);
        return (new frequency_calculator())->calculate($facts, null, 5000);
    }

    /**
     * A row of the frequency.
     *
     * @param int $student index of the student
     * @param int $section index of the section
     * @param frequency_result $result
     * @return array
     */
    private function row(int $student, int $section, frequency_result $result): array {
        return ['student_id' => (int) $this->students[$student]->id, 'section_id' => $this->sections[$section], 'result' => $result];
    }

    /**
     * Normal case: the rows and their activities are written and read back (Q1: 1 of 3 is 33.333).
     */
    public function test_saves_and_reads_the_rows_and_their_activities(): void {
        $processing = $this->processing();

        $id = $this->repository->save((int) $this->course->id, (int) $processing->id, [
            $this->row(0, 0, $this->calculated([0, 1, 2], [0])),
            $this->row(1, 0, $this->calculated([0, 1], [0, 1])),
            $this->row(0, 1, $this->calculated([])),
        ], null, 1000);

        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame($id, (int) $analysis->id);
        $this->assertSame('frequency', $analysis->analysis_type);
        $this->assertNull($analysis->cmid);
        $rows = array_values($this->repository->find_frequencies($id));
        $this->assertCount(3, $rows);
        $first = $this->repository->find_frequencies($id, (int) $this->students[0]->id);
        $one = array_values(array_filter($first, fn($r) => (int) $r->section_id === $this->sections[0]))[0];
        $this->assertSame([3, 1], [(int) $one->total_activities, (int) $one->participating_activities]);
        $this->assertEquals(33.333, $one->percentage);
        $activities = array_values($this->repository->find_activities((int) $one->id));
        $this->assertSame($this->activities, array_map(static fn($a) => (int) $a->activity_id, $activities));
        $this->assertSame([1, 0, 0], array_map(static fn($a) => (int) $a->participated, $activities));
        $this->assertSame([1000, 1000, 1000], array_map(static fn($a) => (int) $a->reference_date, $activities));
    }

    /**
     * Q13: a student with no activity in the denominator has a row with total zero and percentage 0 (no_data is derived, there is no
     * column for it), and an activity row exists only for the activities of the denominator; a valid zero keeps its activity rows.
     */
    public function test_no_data_row_has_no_activities_and_a_valid_zero_keeps_them(): void {
        $processing = $this->processing();

        $id = $this->repository->save((int) $this->course->id, (int) $processing->id, [
            $this->row(0, 0, $this->calculated([])),
            $this->row(1, 0, $this->calculated([0, 1])),
        ]);

        $nodata = array_values($this->repository->find_frequencies($id, (int) $this->students[0]->id))[0];
        $zero = array_values($this->repository->find_frequencies($id, (int) $this->students[1]->id))[0];
        $this->assertSame([0, 0], [(int) $nodata->total_activities, (int) $nodata->participating_activities]);
        $this->assertEquals(0.0, $nodata->percentage);
        $this->assertSame([], $this->repository->find_activities((int) $nodata->id));
        $this->assertSame([2, 0], [(int) $zero->total_activities, (int) $zero->participating_activities]);
        $this->assertEquals(0.0, $zero->percentage);
        $this->assertCount(2, $this->repository->find_activities((int) $zero->id));
    }

    /**
     * Q4: only derived results are stored: the columns of the three tables are the aggregate, the counts, the percentage and, for each
     * activity, whether the student took part and the reference date; nothing of the interactions or of the content.
     */
    public function test_only_derived_results_are_stored(): void {
        global $DB;
        $columns = fn(string $table) => array_keys($DB->get_columns($table));

        $this->assertSame(
            ['id', 'analysis_id', 'student_id', 'section_id', 'total_activities', 'participating_activities', 'percentage'],
            $columns('local_assistente_frequency')
        );
        $this->assertSame(
            ['id', 'frequency_id', 'activity_id', 'participated', 'reference_date'],
            $columns('local_assistente_frequency_activity')
        );
        $this->assertSame(
            ['id', 'course_id', 'processing_id', 'analysis_type', 'cmid', 'summary', 'created_at'],
            $columns('local_assistente_analysis')
        );
    }

    /**
     * Each execution is a new immutable analysis; the current one of a student is the latest that has the student, and the history is
     * kept; another student's analyses are not mixed in.
     */
    public function test_current_is_the_latest_per_student_and_the_history_is_kept(): void {
        $courseid = (int) $this->course->id;
        $first = $this->repository->save($courseid, (int) $this->processing()->id, [$this->row(0, 0, $this->calculated([0], [0]))], null, 100);
        $other = $this->repository->save($courseid, (int) $this->processing()->id, [$this->row(1, 0, $this->calculated([0]))], null, 150);
        $second = $this->repository->save($courseid, (int) $this->processing()->id, [$this->row(0, 0, $this->calculated([0, 1], [0]))], null, 200);

        $student = (int) $this->students[0]->id;
        $this->assertSame($second, (int) $this->repository->find_current_for_student($courseid, $student)->id);
        $this->assertSame([$second, $first], array_map(
            static fn($a) => (int) $a->id,
            array_values($this->repository->find_history_for_student($courseid, $student))
        ));
        $this->assertSame([$other], array_map(
            static fn($a) => (int) $a->id,
            array_values($this->repository->find_history_for_student($courseid, (int) $this->students[1]->id))
        ));
        $oldrow = array_values($this->repository->find_frequencies($first))[0];
        $this->assertSame(1, (int) $oldrow->total_activities, 'The first analysis is untouched');
    }

    /**
     * A student with a row in each of two sections is found in the history without a warning (found by the handler of T087).
     */
    public function test_a_student_with_several_sections_is_found_in_the_history(): void {
        $courseid = (int) $this->course->id;
        $id = $this->repository->save($courseid, (int) $this->processing()->id, [
            $this->row(0, 0, $this->calculated([0], [0])),
            $this->row(0, 1, $this->calculated([1])),
        ], null, 100);

        $history = array_values($this->repository->find_history_for_student($courseid, (int) $this->students[0]->id));

        $this->assertSame([$id], array_map(static fn($a) => (int) $a->id, $history));
        $this->assertSame($id, (int) $this->repository->find_current_for_student($courseid, (int) $this->students[0]->id)->id);
    }
    /**
     * Idempotency: a second save for the same processing is rejected and the first is untouched.
     */
    public function test_second_save_for_the_same_processing_is_rejected(): void {
        global $DB;
        $processing = $this->processing();
        $first = $this->repository->save((int) $this->course->id, (int) $processing->id, [$this->row(0, 0, $this->calculated([0], [0]))]);

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, [$this->row(1, 1, $this->calculated([1]))]);
            $this->fail('A second analysis was saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'frequency']));
            $this->assertCount(1, $this->repository->find_frequencies($first));
        }
    }

    /**
     * Invalid values and references write nothing.
     *
     * @param \Closure $build builds the rows from the test case
     */
    #[DataProvider('invalid_inputs')]
    public function test_invalid_input_writes_nothing(\Closure $build): void {
        global $DB;
        $processing = $this->processing();

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, $build($this));
            $this->fail('The invalid frequency was saved');
        } catch (invalid_domain_value_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'frequency']));
            $this->assertSame(0, $DB->count_records('local_assistente_frequency'));
            $this->assertSame(0, $DB->count_records('local_assistente_frequency_activity'));
        }
    }

    /**
     * Invalid inputs.
     *
     * @return array
     */
    public static function invalid_inputs(): array {
        return [
            'unknown student' => [fn($t) => [['student_id' => 987654, 'section_id' => $t->sections[0], 'result' => $t->calculated([0])]]],
            'section of no course' => [fn($t) => [['student_id' => (int) $t->students[0]->id, 'section_id' => 987654, 'result' => $t->calculated([0])]]],
            'result missing' => [fn($t) => [['student_id' => (int) $t->students[0]->id, 'section_id' => $t->sections[0]]]],
            'total that does not match the activities' => [fn($t) => [$t->row(0, 0, new frequency_result(
                50.0,
                result_state::VALID,
                5,
                1,
                $t->calculated([0, 1], [0])->activities,
                []
            ))]],
            'percentage that does not match the totals' => [fn($t) => [$t->row(0, 0, new frequency_result(
                99.0,
                result_state::VALID,
                2,
                1,
                $t->calculated([0, 1], [0])->activities,
                []
            ))]],
            'no_data state with activities' => [fn($t) => [$t->row(0, 0, new frequency_result(
                50.0,
                result_state::NO_DATA,
                2,
                1,
                $t->calculated([0, 1], [0])->activities,
                []
            ))]],
            'participated that is not 0 or 1' => [fn($t) => [$t->row(0, 0, new frequency_result(
                100.0,
                result_state::VALID,
                1,
                2,
                [['activity_id' => $t->activities[0], 'participated' => 2, 'reference_date' => 1000]],
                []
            ))]],
        ];
    }

    /**
     * An activity of another course is refused, and so is a repeated student and section (nothing written).
     */
    public function test_activity_of_another_course_and_repeated_rows_are_refused(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_course();
        $foreign = (int) $this->getDataGenerator()->create_module('assign', ['course' => $other->id])->cmid;
        $processing = $this->processing();
        $facts = [[
            'activity_id' => $foreign, 'is_resource' => false, 'categories' => ['submission'], 'available' => true,
            'due_date' => 1000, 'participation' => [],
        ]];

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, [
                $this->row(0, 0, (new frequency_calculator())->calculate($facts, null, 5000)),
            ]);
            $this->fail('An activity of another course was accepted');
        } catch (invalid_domain_value_exception) {
            $this->assertNull($this->repository->find_by_processing((int) $processing->id));
        }

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, [
                $this->row(0, 0, $this->calculated([0])),
                $this->row(0, 0, $this->calculated([1])),
            ]);
            $this->fail('A repeated student and section were saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'frequency']));
        }
    }

    /**
     * A failure in the middle of the writes rolls everything back: a database trigger makes the database refuse the second activity row,
     * after the analysis, the frequency row and the first activity row were written.
     */
    public function test_failure_midway_rolls_back_everything(): void {
        global $DB;
        $processing = $this->processing();
        $table = $DB->get_prefix() . 'local_assistente_frequency_activity';
        try {
            // One statement only (Moodle refuses several): the row of the second activity gets NULL in a NOT NULL column.
            $DB->execute("CREATE TRIGGER t086_refuse BEFORE INSERT ON {$table} FOR EACH ROW
                SET NEW.participated = IF(NEW.activity_id = {$this->activities[1]}, NULL, NEW.participated)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, [$this->row(0, 0, $this->calculated([0, 1], [0]))]);
            $saved = true;
        } catch (\dml_exception) {
            $saved = false;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t086_refuse');
        }

        $this->assertFalse($saved, 'The trigger must have refused the second activity');
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'frequency']));
        $this->assertSame(0, $DB->count_records('local_assistente_frequency'));
        $this->assertSame(0, $DB->count_records('local_assistente_frequency_activity'));
    }

    /**
     * A processing that does not exist is a missing record, and nothing is written.
     */
    public function test_unknown_processing_is_rejected(): void {
        $this->expectException(\dml_missing_record_exception::class);

        $this->repository->save((int) $this->course->id, 987654, []);
    }

    /**
     * Missing records read as null or empty; another course sees nothing.
     */
    public function test_missing_records_read_as_null_or_empty(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->repository->save((int) $this->course->id, (int) $this->processing()->id, [$this->row(0, 0, $this->calculated([0]))]);

        $this->assertNull($this->repository->find_by_processing(424242));
        $this->assertSame([], $this->repository->find_frequencies(424242));
        $this->assertSame([], $this->repository->find_activities(424242));
        $this->assertNull($this->repository->find_current_for_student((int) $other->id, (int) $this->students[0]->id));
    }
}
