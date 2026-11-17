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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the repository of the performance of a student (T077; spec-012 data-model; quickstart 012 Q7, Q8 at persistence level).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(performance_repository::class)]
final class performance_repository_test extends \advanced_testcase {
    /** @var performance_repository */
    private performance_repository $repository;

    /** @var event_repository */
    private event_repository $events;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass[] two students */
    private array $students;

    /** @var int[] cmids of three activities of the course */
    private array $activities;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback. A course with two students and three activities.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new performance_repository();
        $this->events = new event_repository();
        $this->course = $this->getDataGenerator()->create_course();
        $this->students = [
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
        ];
        $this->activities = [];
        foreach (range(1, 3) as $i) {
            $this->activities[] = (int) $this->getDataGenerator()->create_module('page', ['course' => $this->course->id])->cmid;
        }
    }

    /**
     * A grade event of a student and its processing.
     *
     * @param \stdClass $student
     * @return \stdClass the processing
     */
    private function processing(\stdClass $student): \stdClass {
        static $occurred = 1000;
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::GRADE_CHANGED,
            ['userid' => (int) $student->id, 'itemid' => 1, 'occurred_at' => ++$occurred],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());
        return $this->events->find_processing((int) $event->id);
    }

    /**
     * Rows for some activities.
     *
     * @param array $grades activity index => grade
     * @return array
     */
    private function rows(array $grades): array {
        $rows = [];
        foreach ($grades as $index => $grade) {
            $rows[] = ['activity_id' => $this->activities[$index], 'grade' => $grade];
        }
        return $rows;
    }

    /**
     * Normal case: the analysis and its rows are written and read back, with the student in each row.
     */
    public function test_saves_and_reads_the_analysis_and_its_rows(): void {
        $processing = $this->processing($this->students[0]);

        $id = $this->repository->save(
            (int) $this->course->id,
            (int) $processing->id,
            (int) $this->students[0]->id,
            $this->rows([0 => 66.665, 1 => 0.0, 2 => 100.0]),
            'resumo',
            1000
        );

        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame($id, (int) $analysis->id);
        $this->assertSame('grade_performance', $analysis->analysis_type);
        $this->assertNull($analysis->cmid);
        $this->assertSame(1000, (int) $analysis->created_at);
        $rows = array_values($this->repository->find_rows($id));
        $this->assertSame($this->activities, array_map(static fn($r) => (int) $r->activity_id, $rows));
        $this->assertEquals([66.665, 0.0, 100.0], array_map(static fn($r) => (float) $r->grade, $rows));
        $this->assertSame([(int) $this->students[0]->id], array_values(array_unique(array_map(static fn($r) => (int) $r->student_id, $rows))));
    }

    /**
     * Q7: an activity with no grade is not passed, so it has no row (no_data); a real zero is a row; a student with nothing graded
     * has a valid analysis with no row.
     */
    public function test_no_data_is_the_absence_of_a_row_and_zero_is_a_row(): void {
        $none = $this->processing($this->students[0]);
        $zero = $this->processing($this->students[1]);

        $a = $this->repository->save((int) $this->course->id, (int) $none->id, (int) $this->students[0]->id, []);
        $b = $this->repository->save((int) $this->course->id, (int) $zero->id, (int) $this->students[1]->id, $this->rows([0 => 0.0]));

        $this->assertSame([], $this->repository->find_rows($a));
        $rows = array_values($this->repository->find_rows($b));
        $this->assertCount(1, $rows);
        $this->assertEquals(0.0, $rows[0]->grade);
    }

    /**
     * Q8: each execution is a new immutable analysis; the current one of a student is the latest, the history is kept, and another
     * student's analyses are not mixed in. An analysis with no row still belongs to its student.
     */
    public function test_current_is_the_latest_per_student_and_the_history_is_kept(): void {
        $courseid = (int) $this->course->id;
        $first = $this->repository->save($courseid, (int) $this->processing($this->students[0])->id, (int) $this->students[0]->id, $this->rows([0 => 50.0]), null, 100);
        $other = $this->repository->save($courseid, (int) $this->processing($this->students[1])->id, (int) $this->students[1]->id, $this->rows([0 => 10.0]), null, 150);
        $removed = $this->repository->save($courseid, (int) $this->processing($this->students[0])->id, (int) $this->students[0]->id, [], null, 200);

        $current = $this->repository->find_current_for_student($courseid, (int) $this->students[0]->id);
        $history = array_map(static fn($a) => (int) $a->id, array_values($this->repository->find_history_for_student($courseid, (int) $this->students[0]->id)));

        $this->assertSame($removed, (int) $current->id, 'After the removal of the grade the activity has no row');
        $this->assertSame([$removed, $first], $history);
        $this->assertCount(1, $this->repository->find_rows($first), 'The earlier analysis is untouched');
        $this->assertSame([$other], array_map(static fn($a) => (int) $a->id, array_values(
            $this->repository->find_history_for_student($courseid, (int) $this->students[1]->id)
        )));
    }

    /**
     * Idempotency: a second save for the same processing is rejected and the first is untouched.
     */
    public function test_second_save_for_the_same_processing_is_rejected(): void {
        global $DB;
        $processing = $this->processing($this->students[0]);
        $first = $this->repository->save((int) $this->course->id, (int) $processing->id, (int) $this->students[0]->id, $this->rows([0 => 50.0]));

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, (int) $this->students[0]->id, $this->rows([1 => 99.0]));
            $this->fail('A second analysis was saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'grade_performance']));
            $this->assertCount(1, $this->repository->find_rows($first));
        }
    }

    /**
     * Invalid values and references write nothing.
     *
     * @param \Closure $build builds [student id, rows] from the test case
     */
    #[DataProvider('invalid_inputs')]
    public function test_invalid_input_writes_nothing(\Closure $build): void {
        global $DB;
        $processing = $this->processing($this->students[0]);
        [$student, $rows] = $build($this);

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, $student, $rows);
            $this->fail('The invalid performance was saved');
        } catch (invalid_domain_value_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'grade_performance']));
            $this->assertSame(0, $DB->count_records('local_assistente_performance'));
        }
    }

    /**
     * Invalid inputs.
     *
     * @return array
     */
    public static function invalid_inputs(): array {
        return [
            'unknown student' => [fn($t) => [987654, $t->rows([0 => 50.0])]],
            'activity of no module' => [fn($t) => [(int) $t->students[0]->id, [['activity_id' => 987654, 'grade' => 50.0]]]],
            'activity id zero' => [fn($t) => [(int) $t->students[0]->id, [['activity_id' => 0, 'grade' => 50.0]]]],
            'grade missing' => [fn($t) => [(int) $t->students[0]->id, [['activity_id' => $t->activities[0]]]]],
            'grade not finite' => [fn($t) => [(int) $t->students[0]->id, $t->rows([0 => NAN])]],
            'grade too large for the column' => [fn($t) => [(int) $t->students[0]->id, $t->rows([0 => 1.0e9])]],
        ];
    }

    /**
     * An activity of another course is refused.
     */
    public function test_activity_of_another_course_is_refused(): void {
        $other = $this->getDataGenerator()->create_course();
        $foreign = (int) $this->getDataGenerator()->create_module('page', ['course' => $other->id])->cmid;
        $processing = $this->processing($this->students[0]);

        $this->expectException(invalid_domain_value_exception::class);
        $this->repository->save((int) $this->course->id, (int) $processing->id, (int) $this->students[0]->id, [['activity_id' => $foreign, 'grade' => 10.0]]);
    }

    /**
     * The same activity twice is rejected, with nothing written.
     */
    public function test_repeated_activity_is_rejected(): void {
        global $DB;
        $processing = $this->processing($this->students[0]);

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, (int) $this->students[0]->id, array_merge($this->rows([0 => 10.0, 1 => 20.0]), [['activity_id' => $this->activities[0], 'grade' => 30.0]]));
            $this->fail('A repeated activity was saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'grade_performance']));
        }
    }

    /**
     * A failure in the middle of the writes rolls everything back: a database trigger makes the database refuse the second row.
     */
    public function test_failure_midway_rolls_back_everything(): void {
        global $DB;
        $processing = $this->processing($this->students[0]);
        $table = $DB->get_prefix() . 'local_assistente_performance';
        try {
            // One statement only (Moodle refuses several): the row of the second activity gets NULL in a NOT NULL column.
            $DB->execute("CREATE TRIGGER t077_refuse BEFORE INSERT ON {$table} FOR EACH ROW
                SET NEW.grade = IF(NEW.activity_id = {$this->activities[1]}, NULL, NEW.grade)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, (int) $this->students[0]->id, $this->rows([0 => 10.0, 1 => 20.0]));
            $saved = true;
        } catch (\dml_exception) {
            $saved = false;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t077_refuse');
        }

        $this->assertFalse($saved, 'The trigger must have refused the second row');
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'grade_performance']));
        $this->assertSame(0, $DB->count_records('local_assistente_performance'));
    }

    /**
     * A processing that does not exist is a missing record, and nothing is written.
     */
    public function test_unknown_processing_is_rejected(): void {
        $this->expectException(\dml_missing_record_exception::class);

        $this->repository->save((int) $this->course->id, 987654, (int) $this->students[0]->id, []);
    }

    /**
     * Missing records read as null or empty; another course sees nothing.
     */
    public function test_missing_records_read_as_null_or_empty(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->repository->save((int) $this->course->id, (int) $this->processing($this->students[0])->id, (int) $this->students[0]->id, []);

        $this->assertNull($this->repository->find_by_processing(424242));
        $this->assertSame([], $this->repository->find_rows(424242));
        $this->assertNull($this->repository->find_current_for_student((int) $other->id, (int) $this->students[0]->id));
        $this->assertSame([], $this->repository->find_history_for_student(424242, 1));
    }
}
