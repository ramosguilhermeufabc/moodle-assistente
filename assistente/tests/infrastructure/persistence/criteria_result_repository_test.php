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

use local_assistente\domain\criteria\criteria_evaluation;
use local_assistente\domain\criteria\criteria_evaluator;
use local_assistente\domain\criteria_status;
use local_assistente\domain\event\event_type;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the repository of the criteria applied (T092; spec-015 data-model; quickstart 015 Q13 at persistence level).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(criteria_result_repository::class)]
final class criteria_result_repository_test extends \advanced_testcase {
    /** @var criteria_result_repository */
    private criteria_result_repository $repository;

    /** @var event_repository */
    private event_repository $events;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass[] two students */
    private array $students;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback. A course and two students.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new criteria_result_repository();
        $this->events = new event_repository();
        $this->course = $this->getDataGenerator()->create_course();
        $this->students = [
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
        ];
    }

    /**
     * An event of a changed reference date and its processing.
     *
     * @return \stdClass the processing
     */
    private function processing(): \stdClass {
        static $date = 3000;
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::REFERENCE_DATE_REACHED,
            ['cmid' => 1, 'reference_date' => ++$date],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());
        return $this->events->find_processing((int) $event->id);
    }

    /**
     * An evaluation made by the real evaluator.
     *
     * @param float|null $total course total of the student
     * @param bool $posted whether the student posted in the only forum, which is due
     * @param int[] $frequency [participating, total] of the only section; [0, 0] for no data
     * @return criteria_evaluation
     */
    private function evaluation(?float $total, bool $posted = true, array $frequency = [2, 3]): criteria_evaluation {
        return (new criteria_evaluator())->evaluate(
            ['activity_criterion' => 60.0, 'forum_criterion' => 1, 'frequency_criterion' => 40.0],
            $total,
            [['cmid' => 5, 'eligible' => true, 'available' => true, 'reference_date' => 100, 'posted' => $posted]],
            [['participating_activities' => $frequency[0], 'total_activities' => $frequency[1]]],
            1000
        );
    }

    /**
     * A row of the criteria.
     *
     * @param int $student index of the student
     * @param criteria_evaluation $evaluation
     * @return array
     */
    private function row(int $student, criteria_evaluation $evaluation): array {
        return ['student_id' => (int) $this->students[$student]->id, 'evaluation' => $evaluation];
    }

    /**
     * The three statuses of each student are written and read back, one row for each student.
     */
    public function test_saves_and_reads_the_statuses_of_each_student(): void {
        $processing = $this->processing();

        $id = $this->repository->save((int) $this->course->id, (int) $processing->id, [
            $this->row(0, $this->evaluation(59.999, false, [1, 3])),
            $this->row(1, $this->evaluation(60.0, true, [2, 3])),
        ], null, 1000);

        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame($id, (int) $analysis->id);
        $this->assertSame('criteria_check', $analysis->analysis_type);
        $this->assertNull($analysis->cmid);
        $this->assertSame((int) $this->course->id, (int) $analysis->course_id);
        $this->assertCount(2, $this->repository->find_results($id));
        $first = $this->repository->find_result($id, (int) $this->students[0]->id);
        $second = $this->repository->find_result($id, (int) $this->students[1]->id);
        $this->assertSame([0, 0, 0], [(int) $first->activity_status, (int) $first->forum_status, (int) $first->frequency_status]);
        $this->assertSame([1, 1, 1], [(int) $second->activity_status, (int) $second->forum_status, (int) $second->frequency_status]);
        $this->assertCount(1, $this->repository->find_results($id, (int) $this->students[1]->id));
    }

    /**
     * Absence is not zero: a student without a total or without activities is stored as 2, and the rest keeps its own statuses.
     */
    public function test_no_data_is_stored_as_two_and_never_as_zero(): void {
        $id = $this->repository->save((int) $this->course->id, (int) $this->processing()->id, [
            $this->row(0, $this->evaluation(null, true, [0, 0])),
        ]);

        $row = $this->repository->find_result($id, (int) $this->students[0]->id);

        $this->assertSame(
            [criteria_status::NO_DATA->value, criteria_status::MEETS->value, criteria_status::NO_DATA->value],
            [(int) $row->activity_status, (int) $row->forum_status, (int) $row->frequency_status]
        );
    }

    /**
     * Only statuses are stored: no value, limit, forum, post or content of a student.
     */
    public function test_only_the_statuses_are_stored(): void {
        global $DB;

        $this->assertSame(
            ['id', 'analysis_id', 'student_id', 'activity_status', 'forum_status', 'frequency_status'],
            array_keys($DB->get_columns('local_assistente_criteria_result'))
        );
        $this->assertSame(
            ['id', 'course_id', 'processing_id', 'analysis_type', 'cmid', 'summary', 'created_at'],
            array_keys($DB->get_columns('local_assistente_analysis'))
        );
    }

    /**
     * Each execution is a new immutable analysis; the current one of a student is the latest that has the student, and the history is
     * kept; another student's analyses are not mixed in.
     */
    public function test_current_is_the_latest_per_student_and_the_history_is_kept(): void {
        $courseid = (int) $this->course->id;
        $first = $this->repository->save($courseid, (int) $this->processing()->id, [$this->row(0, $this->evaluation(10.0))], null, 100);
        $other = $this->repository->save($courseid, (int) $this->processing()->id, [$this->row(1, $this->evaluation(90.0))], null, 150);
        $second = $this->repository->save($courseid, (int) $this->processing()->id, [$this->row(0, $this->evaluation(80.0))], null, 200);

        $current = $this->repository->find_current_for_student($courseid, (int) $this->students[0]->id);
        $history = $this->repository->find_history_for_student($courseid, (int) $this->students[0]->id);

        $this->assertSame($second, (int) $current->id);
        $this->assertSame([$second, $first], array_map(fn($a) => (int) $a->id, $history));
        $this->assertSame($other, (int) $this->repository->find_current_for_student($courseid, (int) $this->students[1]->id)->id);
        $old = $this->repository->find_result($first, (int) $this->students[0]->id);
        $this->assertSame(0, (int) $old->activity_status, 'The previous analysis is intact');
    }

    /**
     * A course without criteria has no row at all: an empty list writes only the analysis, with no row.
     */
    public function test_an_empty_list_writes_no_row(): void {
        global $DB;

        $id = $this->repository->save((int) $this->course->id, (int) $this->processing()->id, []);

        $this->assertNotNull($this->repository->find_by_processing(
            (int) $DB->get_field('local_assistente_analysis', 'processing_id', ['id' => $id])
        ));
        $this->assertSame(0, $DB->count_records('local_assistente_criteria_result'));
    }

    /**
     * A second save for the same processing is rejected and leaves the first untouched.
     */
    public function test_second_save_for_the_same_processing_is_rejected(): void {
        global $DB;
        $processing = $this->processing();
        $first = $this->repository->save((int) $this->course->id, (int) $processing->id, [$this->row(0, $this->evaluation(80.0))]);

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, [$this->row(1, $this->evaluation(10.0))]);
            $this->fail('A second analysis was saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'criteria_check']));
            $this->assertCount(1, $this->repository->find_results($first));
        }
    }

    /**
     * Invalid input writes nothing: no evaluation, a student that does not exist, a repeated student and an evaluation that does not agree
     * with itself (a status of no data with a value, or a value missing with a status that is not no data).
     */
    public function test_invalid_input_writes_nothing(): void {
        global $DB;
        $valid = $this->evaluation(80.0);
        $limits = $valid->limits;
        $zeroedabsence = new criteria_evaluation(
            criteria_status::BELOW,
            criteria_status::MEETS,
            criteria_status::MEETS,
            null,
            40.0,
            [],
            [],
            $limits
        );
        $hiddenvalue = new criteria_evaluation(
            criteria_status::NO_DATA,
            criteria_status::MEETS,
            criteria_status::MEETS,
            80.0,
            40.0,
            [],
            [],
            $limits
        );
        $zeroedfrequency = new criteria_evaluation(
            criteria_status::MEETS,
            criteria_status::MEETS,
            criteria_status::BELOW,
            80.0,
            null,
            [],
            [],
            $limits
        );
        $cases = [
            'no evaluation' => [['student_id' => (int) $this->students[0]->id]],
            'a status instead of an evaluation' => [['student_id' => (int) $this->students[0]->id, 'evaluation' => 1]],
            'unknown student' => [['student_id' => 987654, 'evaluation' => $valid]],
            'no student' => [['student_id' => 0, 'evaluation' => $valid]],
            'deleted student' => 'deleted',
            'one valid and one unknown' => [$this->row(0, $valid), ['student_id' => 987654, 'evaluation' => $valid]],
            'absence stored as a result' => [$this->row(0, $zeroedabsence)],
            'value hidden as no data' => [$this->row(0, $hiddenvalue)],
            'frequency absence stored as below' => [$this->row(0, $zeroedfrequency)],
        ];
        foreach ($cases as $name => $students) {
            if ($students === 'deleted') {
                $gone = $this->getDataGenerator()->create_user();
                delete_user($gone);
                $students = [['student_id' => (int) $gone->id, 'evaluation' => $valid]];
            }
            try {
                $this->repository->save((int) $this->course->id, (int) $this->processing()->id, $students);
                $this->fail("Saved: {$name}");
            } catch (invalid_domain_value_exception) {
                $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'criteria_check']), $name);
                $this->assertSame(0, $DB->count_records('local_assistente_criteria_result'), $name);
            }
        }
    }

    /**
     * The same student twice in one execution is refused before anything is written.
     */
    public function test_a_repeated_student_is_refused(): void {
        global $DB;

        try {
            $this->repository->save((int) $this->course->id, (int) $this->processing()->id, [
                $this->row(0, $this->evaluation(80.0)),
                $this->row(0, $this->evaluation(10.0)),
            ]);
            $this->fail('A repeated student was saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'criteria_check']));
            $this->assertSame(0, $DB->count_records('local_assistente_criteria_result'));
        }
    }

    /**
     * A failure in the middle of the writes rolls everything back: a database trigger makes the database refuse the row of the second
     * student, after the analysis and the row of the first were written.
     */
    public function test_failure_midway_rolls_back_everything(): void {
        global $DB;
        $processing = $this->processing();
        $table = $DB->get_prefix() . 'local_assistente_criteria_result';
        try {
            // One statement only (Moodle refuses several): the row of the second student gets NULL in a NOT NULL column.
            $DB->execute("CREATE TRIGGER t092_refuse BEFORE INSERT ON {$table} FOR EACH ROW
                SET NEW.forum_status = IF(NEW.student_id = {$this->students[1]->id}, NULL, NEW.forum_status)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }

        try {
            $this->repository->save((int) $this->course->id, (int) $processing->id, [
                $this->row(0, $this->evaluation(80.0)),
                $this->row(1, $this->evaluation(10.0)),
            ]);
            $saved = true;
        } catch (\dml_exception) {
            $saved = false;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t092_refuse');
        }

        $this->assertFalse($saved, 'The trigger must have refused the second student');
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'criteria_check']));
        $this->assertSame(0, $DB->count_records('local_assistente_criteria_result'));
    }

    /**
     * A processing that does not exist is a missing record, and nothing is written.
     */
    public function test_unknown_processing_is_rejected(): void {
        $this->expectException(\dml_missing_record_exception::class);

        $this->repository->save((int) $this->course->id, 987654, []);
    }

    /**
     * Missing records read as null or empty; another course sees nothing; the unique index admits one row for each student.
     */
    public function test_missing_records_read_as_null_or_empty(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_course();
        $id = $this->repository->save((int) $this->course->id, (int) $this->processing()->id, [$this->row(0, $this->evaluation(80.0))]);

        $this->assertNull($this->repository->find_by_processing(424242));
        $this->assertSame([], $this->repository->find_results(424242));
        $this->assertNull($this->repository->find_result($id, (int) $this->students[1]->id));
        $this->assertNull($this->repository->find_current_for_student((int) $other->id, (int) $this->students[0]->id));
        $this->expectException(duplicate_record_exception::class);
        (new table_repository('local_assistente_criteria_result'))->insert((object) [
            'analysis_id' => $id, 'student_id' => (int) $this->students[0]->id,
            'activity_status' => 1, 'forum_status' => 1, 'frequency_status' => 1,
        ]);
    }
}
