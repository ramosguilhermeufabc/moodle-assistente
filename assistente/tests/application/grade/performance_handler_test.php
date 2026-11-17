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

namespace local_assistente\application\grade;

use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\handler_registry;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\gradebook_reader;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\performance_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the performance of a student (T078; quickstart 012 Q1, Q3, Q4 to Q10; DEC-24, DEC-37). Real course, real gradebook; no LLM
 * exists in this flow.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(performance_handler::class)]
final class performance_handler_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var event_repository */
    private event_repository $events;

    /** @var performance_repository */
    private performance_repository $repository;

    /** @var performance_handler */
    private performance_handler $handler;

    /**
     * Real transactions and locks are used. A course with the assistant enabled and a student.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($this->course->id, true);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->events = new event_repository();
        $this->repository = new performance_repository();
        $this->handler = new performance_handler();
    }

    /**
     * An assignment whose grade item has a range.
     *
     * @param float $min
     * @param float $max
     * @return \stdClass assignment with cmid
     */
    private function activity(float $min = 0.0, float $max = 100.0): \stdClass {
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $assign->id, 0, null, [
            'itemname' => 'Atividade', 'gradetype' => GRADE_TYPE_VALUE, 'grademin' => $min, 'grademax' => $max,
        ]);
        return $assign;
    }

    /**
     * Give (or remove, with null) the grade of the student in an assignment.
     *
     * @param \stdClass $assign
     * @param float|null $grade
     */
    private function grade(\stdClass $assign, ?float $grade): void {
        $result = grade_update('mod/assign', $this->course->id, 'mod', 'assign', $assign->id, 0, [
            'userid' => $this->student->id, 'rawgrade' => $grade,
        ]);
        $this->assertSame(GRADE_UPDATE_OK, $result);
    }

    /**
     * Run the handler for a grade event of the student.
     *
     * @param \stdClass|null $student
     * @return array handler_result and processing
     */
    private function run_event(?\stdClass $student = null): array {
        static $occurred = 3000;
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::GRADE_CHANGED,
            ['userid' => (int) ($student ?? $this->student)->id, 'itemid' => 1, 'occurred_at' => ++$occurred],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        return [$this->handler->handle($event, $processing), $processing];
    }

    /**
     * Rows of an analysis by activity.
     *
     * @param \stdClass $processing
     * @return float[] grade by cmid
     */
    private function grades_of(\stdClass $processing): array {
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $rows = [];
        foreach ($this->repository->find_rows((int) $analysis->id) as $row) {
            $rows[(int) $row->activity_id] = (float) $row->grade;
        }
        return $rows;
    }

    /**
     * The context of a processing.
     *
     * @param \stdClass $processing
     * @return array
     */
    private function context_of(\stdClass $processing): array {
        return json_decode($this->events->find_context((int) $processing->id)->context_json, true);
    }

    /**
     * Q4 and Q5: the grade is the percentage of the range of the item, 0 to 20 with 13.333 and 5 to 10 with 7.
     */
    public function test_grade_is_the_percentage_of_the_range_of_the_item(): void {
        $a = $this->activity(0, 20);
        $b = $this->activity(5, 10);
        $this->grade($a, 13.333);
        $this->grade($b, 7.0);

        [$result, $processing] = $this->run_event();

        $this->assertTrue($result->success, (string) $result->response());
        $this->assertEquals([(int) $a->cmid => 66.665, (int) $b->cmid => 40.0], $this->grades_of($processing));
    }

    /**
     * Q1 and Q3, weights configured in the gradebook: the course total is the one the gradebook computes with the weights of the
     * teacher, and it goes in the context as the average of the student; the plugin has no weight of its own.
     */
    public function test_course_total_follows_the_weights_configured_in_the_gradebook(): void {
        $a = $this->activity(0, 100);
        $b = $this->activity(0, 100);
        $this->grade($a, 100.0);
        $this->grade($b, 0.0);
        $category = \grade_category::fetch_course_category($this->course->id);
        $category->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
        $category->update();
        foreach ([[$a, 3.0], [$b, 1.0]] as [$assign, $weight]) {
            $item = \grade_item::fetch(['courseid' => $this->course->id, 'itemmodule' => 'assign', 'iteminstance' => $assign->id]);
            $item->aggregationcoef = $weight;
            $item->update();
        }
        grade_regrade_final_grades($this->course->id);
        $total = (new gradebook_reader())->get_course_total((int) $this->course->id, (int) $this->student->id);

        [$result, $processing] = $this->run_event();

        $this->assertTrue($result->success, (string) $result->response());
        $this->assertEqualsWithDelta(75.0, $this->context_of($processing)['course_total_percent'], 0.001, 'Weights 3 and 1 on 100 and 0');
        $this->assertEqualsWithDelta(
            ($total->final_grade - $total->grade_min) / ($total->grade_max - $total->grade_min) * 100,
            $this->context_of($processing)['course_total_percent'],
            0.001,
            'The average is the total of the gradebook'
        );
        $this->assertEquals([(int) $a->cmid => 100.0, (int) $b->cmid => 0.0], $this->grades_of($processing), 'Each grade is the final grade, unweighted');
    }

    /**
     * Q6: an activity with a scale of four levels, level 3 (minimum 1, maximum 4), is 66.666.
     */
    public function test_scale_level_is_converted_with_the_range_of_the_scale(): void {
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Ruim,Regular,Bom,Otimo']);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $assign->id, 0, null, [
            'itemname' => 'Escala', 'gradetype' => GRADE_TYPE_SCALE, 'scaleid' => $scale->id,
        ]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $assign->id, 0, ['userid' => $this->student->id, 'rawgrade' => 3]);

        [$result, $processing] = $this->run_event();

        $this->assertTrue($result->success, (string) $result->response());
        $this->assertEquals([(int) $assign->cmid => 66.666], $this->grades_of($processing));
    }

    /**
     * Q7: an activity with no grade has no row (no_data); a grade of zero is a row; the course total is registered even so.
     */
    public function test_no_grade_is_no_data_and_zero_is_a_row(): void {
        $ungraded = $this->activity();
        $zero = $this->activity();
        $this->grade($zero, 0.0);

        [$result, $processing] = $this->run_event();

        $this->assertTrue($result->success);
        $grades = $this->grades_of($processing);
        $this->assertArrayNotHasKey((int) $ungraded->cmid, $grades);
        $this->assertSame(0.0, $grades[(int) $zero->cmid]);
    }

    /**
     * Q8: a change and then a removal each make a new analysis; the earlier one is kept; after the removal the activity has no row.
     */
    public function test_change_and_removal_keep_the_earlier_analysis(): void {
        $assign = $this->activity();
        $this->grade($assign, 80.0);
        [, $first] = $this->run_event();
        $this->grade($assign, 60.0);
        [, $second] = $this->run_event();
        $this->grade($assign, null);
        [$result, $third] = $this->run_event();

        $this->assertTrue($result->success);
        $this->assertEquals([(int) $assign->cmid => 80.0], $this->grades_of($first));
        $this->assertEquals([(int) $assign->cmid => 60.0], $this->grades_of($second));
        $this->assertSame([], $this->grades_of($third), 'Removed: no row, no zero');
        $this->assertCount(3, $this->repository->find_history_for_student((int) $this->course->id, (int) $this->student->id));
    }

    /**
     * Q9: a manual item, a category total and an item of text type are not activities.
     */
    public function test_manual_category_and_text_items_are_not_activities(): void {
        $assign = $this->activity();
        $this->grade($assign, 50.0);
        $manual = new \grade_item(['courseid' => $this->course->id, 'itemtype' => 'manual', 'itemname' => 'Manual', 'grademax' => 10, 'gradetype' => GRADE_TYPE_VALUE], false);
        $manual->insert();
        $manual->update_final_grade($this->student->id, 5.0);
        $category = new \grade_category(['courseid' => $this->course->id, 'fullname' => 'Categoria'], false);
        $category->insert();
        $text = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $text->id, 0, null, ['itemname' => 'Texto', 'gradetype' => GRADE_TYPE_TEXT]);

        [$result, $processing] = $this->run_event();

        $this->assertTrue($result->success);
        $this->assertEquals([(int) $assign->cmid => 50.0], $this->grades_of($processing));
    }

    /**
     * A second grade item for the same activity module (item number 1), as the forum has.
     *
     * @param \stdClass $assign
     * @param float|null $grade grade of the student in the second item; null leaves it without a grade
     */
    private function second_item(\stdClass $assign, ?float $grade): void {
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $assign->id, 1, null, [
            'itemname' => 'Segundo item', 'gradetype' => GRADE_TYPE_VALUE, 'grademin' => 0, 'grademax' => 100,
        ]);
        if ($grade !== null) {
            grade_update('mod/assign', $this->course->id, 'mod', 'assign', $assign->id, 1, [
                'userid' => $this->student->id, 'rawgrade' => $grade,
            ]);
        }
    }

    /**
     * DEC-53: an activity with several grade items is represented by its main item (item number 0), without combining the grades
     * and without choosing another item: item 0 with 30 and item 1 with 70 gives 30.
     */
    public function test_activity_with_several_items_uses_the_main_item_only(): void {
        $assign = $this->activity();
        $this->grade($assign, 30.0);
        $this->second_item($assign, 70.0);
        $second = \grade_item::fetch(['courseid' => $this->course->id, 'itemmodule' => 'assign', 'iteminstance' => $assign->id, 'itemnumber' => 1]);
        $this->assertEquals(70.0, \grade_grade::fetch(['itemid' => $second->id, 'userid' => $this->student->id])->finalgrade, 'The fixture: the second item has 70');

        [$result, $processing] = $this->run_event();

        $this->assertTrue($result->success, (string) $result->response());
        $grades = $this->grades_of($processing);
        $this->assertSame([(int) $assign->cmid => 30.0], $grades, 'One row for the activity, from item 0; 70 is neither used nor combined');
    }

    /**
     * DEC-53: when the main item has no grade the activity is no_data (no row), even if another item of the activity has one.
     */
    public function test_main_item_without_grade_is_no_data_even_if_another_item_has_one(): void {
        $assign = $this->activity();
        $this->second_item($assign, 70.0);

        [$result, $processing] = $this->run_event();

        $this->assertTrue($result->success, (string) $result->response());
        $this->assertSame([], $this->grades_of($processing), 'No row: the item with a grade is not the main item');
        $this->assertSame(0, $this->context_of($processing)['activities_with_grade']);
    }
    /**
     * Only a student is analysed: a teacher's event is done with nothing written.
     */
    public function test_a_user_who_is_not_a_student_writes_nothing(): void {
        global $DB;
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        [$result, $processing] = $this->run_event($teacher);

        $this->assertTrue($result->success);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id));
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'grade_performance']));
    }

    /**
     * Q7, error: a technical failure fails the attempt and writes nothing; it is not turned into zero.
     */
    public function test_a_technical_failure_writes_nothing(): void {
        global $DB;
        $assign = $this->activity();
        $this->grade($assign, 50.0);
        $broken = new class extends gradebook_reader {
            #[\Override]
            public function get_course_total(int $courseid, int $userid): \stdClass {
                throw new \RuntimeException('gradebook unavailable');
            }
        };
        $handler = new performance_handler(null, $broken);
        $event = $this->events->create_event((int) $this->course->id, event_type::GRADE_CHANGED, ['userid' => (int) $this->student->id, 'itemid' => 1, 'occurred_at' => 1], time());
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);

        $result = $handler->handle($event, $processing);

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'grade_performance']));
    }

    /**
     * Idempotency: a repeated delivery for a processing that has its analysis writes nothing more.
     */
    public function test_repeated_delivery_is_idempotent(): void {
        global $DB;
        $assign = $this->activity();
        $this->grade($assign, 50.0);
        $event = $this->events->create_event((int) $this->course->id, event_type::GRADE_CHANGED, ['userid' => (int) $this->student->id, 'itemid' => 1, 'occurred_at' => 2], time());
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        $this->assertTrue($this->handler->handle($event, $processing)->success);

        $again = $this->handler->handle($event, $processing);

        $this->assertTrue($again->success);
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'grade_performance']));
    }

    /**
     * The handler is registered for the grade event and can be built without arguments, and it can run under the lock of the queue.
     */
    public function test_registered_and_runs_under_the_queue(): void {
        global $DB;
        $assign = $this->activity();
        $this->grade($assign, 50.0);
        $event = $this->events->create_event((int) $this->course->id, event_type::GRADE_CHANGED, ['userid' => (int) $this->student->id, 'itemid' => 1, 'occurred_at' => 3], time());
        $this->assertInstanceOf(performance_handler::class, handler_registry::default()->handler_for(event_type::GRADE_CHANGED));
        $registry = new handler_registry();
        $registry->register(event_type::GRADE_CHANGED, new performance_handler());

        (new \local_assistente\application\queue\queue_processor(null, $registry))->run_cycle();

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $event->id]));
        $processing = $this->events->find_processing((int) $event->id);
        $this->assertEquals([(int) $assign->cmid => 50.0], $this->grades_of($processing));
    }
}
