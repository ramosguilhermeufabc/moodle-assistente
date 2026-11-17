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


namespace local_assistente\application\privacy;

use local_assistente\infrastructure\persistence\course_erasure_repository;
use local_assistente\observer\course_observer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the erasure of the data of a deleted course (T103; quickstart 017 Q10; DEC-42 D2). Real courses and tables; the deletion goes
 * through the real delete_course() of Moodle, which triggers the event that the observer listens to. Behaviour on a running site is CL-37 (T106).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_data_eraser::class)]
#[CoversClass(course_erasure_repository::class)]
#[CoversClass(course_observer::class)]
final class course_data_eraser_test extends \advanced_testcase {
    /**
     * Real transactions are used by the eraser, and the observers of the plugin are active.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    protected function tearDown(): void {
        course_observer::use_eraser(null);
        parent::tearDown();
    }

    /**
     * Insert a row, filling every required column that the test does not care about.
     *
     * @param string $table
     * @param array $values
     * @return int the id of the row
     */
    private function row(string $table, array $values): int {
        global $DB;
        $record = [];
        foreach ($DB->get_columns($table) as $name => $column) {
            if ($name === 'id' || array_key_exists($name, $values)) {
                continue;
            }
            if ($column->not_null && $column->default_value === null && !$column->auto_increment) {
                $record[$name] = match (true) {
                    in_array($column->meta_type, ['I'], true) => 1,
                    in_array($column->meta_type, ['N', 'F'], true) => 0,
                    default => 'x',
                };
            }
        }
        return (int) $DB->insert_record($table, (object) ($values + $record));
    }

    /**
     * Fill every table of the plugin with rows of a course, reaching each by its own key.
     *
     * @param \stdClass $course
     * @return array<string, int[]> ids created, by table
     */
    private function populate(\stdClass $course): array {
        global $DB;
        $c = (int) $course->id;
        $before = [];
        foreach (course_erasure_repository::TABLES as $table) {
            $before[$table] = $DB->get_fieldset_select($table, 'id', '1 = 1');
        }
        $config = $this->row('local_assistente_config', ['course_id' => $c]);
        $this->row('local_assistente_criteria', ['config_id' => $config]);
        $event = $this->row('local_assistente_event', ['course_id' => $c]);
        $processing = $this->row('local_assistente_processing', ['event_id' => $event]);
        $this->row('local_assistente_processing_attempt', ['processing_id' => $processing]);
        $this->row('local_assistente_processing_context', ['processing_id' => $processing]);
        $analysis = $this->row('local_assistente_analysis', ['course_id' => $c, 'processing_id' => $processing]);
        $qa = $this->row('local_assistente_questionnaire_analysis', ['analysis_id' => $analysis]);
        $qq = $this->row('local_assistente_questionnaire_question', ['questionnaire_analysis_id' => $qa]);
        $this->row('local_assistente_question_result', ['analysis_id' => $analysis, 'questionnaire_question_id' => $qq]);
        $this->row('local_assistente_questionnaire_metric', ['analysis_id' => $analysis]);
        $this->row('local_assistente_forum_difficulty', ['analysis_id' => $analysis]);
        $this->row('local_assistente_performance', ['analysis_id' => $analysis]);
        $frequency = $this->row('local_assistente_frequency', ['analysis_id' => $analysis]);
        $this->row('local_assistente_frequency_activity', ['frequency_id' => $frequency]);
        $this->row('local_assistente_criteria_result', ['analysis_id' => $analysis]);
        $this->row('local_assistente_pedagogical_synthesis', ['analysis_id' => $analysis, 'course_id' => $c]);
        $material = $this->row('local_assistente_material', ['course_id' => $c]);
        $this->row('local_assistente_material_version', ['material_id' => $material]);
        $report = $this->row('local_assistente_report', ['course_id' => $c]);
        $this->row('local_assistente_report_version', ['report_id' => $report]);
        $created = [];
        foreach (course_erasure_repository::TABLES as $table) {
            $created[$table] = array_map('intval', array_values(array_diff($DB->get_fieldset_select($table, 'id', '1 = 1'), $before[$table])));
        }
        return $created;
    }

    /**
     * Ids of a table that are still there.
     *
     * @param string $table
     * @param int[] $ids
     * @return int[]
     */
    private function remaining(string $table, array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$sql, $params] = $DB->get_in_or_equal($ids);
        return array_map('intval', $DB->get_fieldset_select($table, 'id', "id {$sql}", $params));
    }

    /**
     * The list of the eraser is every table of the schema: a table added to the plugin without being added to the erasure fails here.
     */
    public function test_every_table_of_the_schema_is_in_the_erasure(): void {
        global $DB;
        $tables = [];
        foreach ($DB->get_manager()->get_install_xml_schema()->getTables() as $table) {
            if (str_starts_with($table->getName(), 'local_assistente_')) {
                $tables[] = $table->getName();
            }
        }
        $this->assertCount(21, $tables);
        $listed = course_erasure_repository::TABLES;
        sort($tables);
        sort($listed);
        $this->assertSame($tables, $listed);
        $this->assertSame(array_unique($listed), $listed);
    }

    /**
     * Q10: a course with rows in every table loses all of them, direct and indirect, and the count of each table says how many.
     */
    public function test_a_course_with_data_in_every_table_loses_all_of_it(): void {
        $course = $this->getDataGenerator()->create_course();
        $created = $this->populate($course);
        foreach ($created as $table => $ids) {
            $this->assertCount(1, $ids, "The test made one row in {$table}");
        }

        $deleted = (new course_data_eraser())->erase((int) $course->id);

        foreach ($created as $table => $ids) {
            $this->assertSame([], $this->remaining($table, $ids), "{$table} still has rows of the course");
            $this->assertSame(1, $deleted[$table], "{$table}: rows deleted");
        }
        $this->assertSame(array_keys($created), array_keys($deleted));
    }

    /**
     * The rows of another course are not touched, in any table, by the erasure of the first.
     */
    public function test_another_course_keeps_everything(): void {
        $erased = $this->getDataGenerator()->create_course();
        $kept = $this->getDataGenerator()->create_course();
        $this->populate($erased);
        $createdkept = $this->populate($kept);

        (new course_data_eraser())->erase((int) $erased->id);

        foreach ($createdkept as $table => $ids) {
            $this->assertSame($ids, $this->remaining($table, $ids), "{$table}: the other course lost rows");
        }
    }

    /**
     * A course with no data of the plugin, and the same course erased twice, lose nothing and are not an error.
     */
    public function test_no_data_and_repetition_are_harmless(): void {
        $empty = $this->getDataGenerator()->create_course();
        $course = $this->getDataGenerator()->create_course();
        $this->populate($course);
        $eraser = new course_data_eraser();

        $none = $eraser->erase((int) $empty->id);
        $first = $eraser->erase((int) $course->id);
        $again = $eraser->erase((int) $course->id);

        $this->assertSame(0, array_sum($none));
        $this->assertSame(21, array_sum($first));
        $this->assertSame(0, array_sum($again));
    }

    /**
     * Invalid courses are refused and nothing is touched.
     */
    public function test_an_invalid_course_is_refused(): void {
        $course = $this->getDataGenerator()->create_course();
        $created = $this->populate($course);

        foreach ([0, -3] as $id) {
            try {
                (new course_data_eraser())->erase($id);
                $this->fail("{$id} was accepted");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        foreach ($created as $table => $ids) {
            $this->assertSame($ids, $this->remaining($table, $ids));
        }
    }

    /**
     * All or nothing: a refusal of the database in the middle (a trigger on the events, which are emptied after their children) undoes what
     * had already been deleted, and the data of the course are exactly as they were; after the cause is gone the erasure works.
     */
    public function test_a_failure_in_the_middle_leaves_the_data_as_they_were(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $created = $this->populate($course);
        $table = $DB->get_prefix() . 'local_assistente_event';
        try {
            // One statement only (Moodle refuses several).
            $DB->execute("CREATE TRIGGER t103_refuse BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused'");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }
        try {
            (new course_data_eraser())->erase((int) $course->id);
            $failed = false;
        } catch (\dml_exception) {
            $failed = true;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t103_refuse');
        }

        $this->assertTrue($failed, 'The trigger must have refused the deletion of the events');
        foreach ($created as $name => $ids) {
            $this->assertSame($ids, $this->remaining($name, $ids), "{$name}: partial erasure");
        }

        $deleted = (new course_data_eraser())->erase((int) $course->id);

        $this->assertSame(21, array_sum($deleted));
    }

    /**
     * Through the real mechanism: Moodle deletes the course, triggers course_deleted, and the observer erases its data; the data of another
     * course stay, and a course that never had the assistant is deleted without a problem.
     */
    public function test_the_deletion_of_the_course_in_moodle_erases_its_data(): void {
        $gone = $this->getDataGenerator()->create_course();
        $kept = $this->getDataGenerator()->create_course();
        $plain = $this->getDataGenerator()->create_course();
        $createdgone = $this->populate($gone);
        $createdkept = $this->populate($kept);

        $this->assertTrue(delete_course($gone, false));
        $this->assertTrue(delete_course($plain, false));

        foreach ($createdgone as $table => $ids) {
            $this->assertSame([], $this->remaining($table, $ids), "{$table}: rows of the deleted course remain");
        }
        foreach ($createdkept as $table => $ids) {
            $this->assertSame($ids, $this->remaining($table, $ids), "{$table}: the other course lost rows");
        }
    }

    /**
     * The event is the one triggered after Moodle finished: when the observer runs the course row is already gone, and the erasure does not
     * need it.
     */
    public function test_the_course_is_already_gone_when_the_observer_runs(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->populate($course);
        $seen = [];
        course_observer::use_eraser(new class ($seen) extends course_data_eraser {
            /**
             * Constructor.
             *
             * @param array $seen receives what the observer saw
             */
            public function __construct(private array &$seen) {
                parent::__construct();
            }

            #[\Override]
            public function erase(int $courseid): array {
                global $DB;
                $this->seen[] = [$courseid, $DB->record_exists('course', ['id' => $courseid])];
                return parent::erase($courseid);
            }
        });

        delete_course($course, false);

        $this->assertSame([[(int) $course->id, false]], $seen);
        $this->assertSame(0, $DB->count_records('local_assistente_config', ['course_id' => $course->id]));
    }

    /**
     * A failure of the erasure is logged and not rethrown: the course is already deleted, and the same course can be erased again.
     */
    public function test_a_failure_is_logged_and_not_rethrown(): void {
        $course = $this->getDataGenerator()->create_course();
        $created = $this->populate($course);
        course_observer::use_eraser(new class extends course_data_eraser {
            /**
             * Constructor, without the real collaborators.
             */
            public function __construct() {
            }

            #[\Override]
            public function erase(int $courseid): array {
                throw new \RuntimeException('database unavailable');
            }
        });

        $this->assertTrue(delete_course($course, false), 'The deletion of the course goes on');

        $this->assertDebuggingCalled('local_assistente: could not erase the data of the deleted course ' . $course->id . ': database unavailable');
        course_observer::use_eraser(null);
        (new course_data_eraser())->erase((int) $course->id);
        foreach ($created as $table => $ids) {
            $this->assertSame([], $this->remaining($table, $ids));
        }
    }

    /**
     * The registration in db/events.php: the event that is triggered after the deletion, outside the transaction.
     */
    public function test_the_event_is_registered_outside_the_transaction(): void {
        global $CFG;
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        $byevent = array_column($observers, null, 'eventname');

        $this->assertArrayHasKey('\core\event\course_deleted', $byevent);
        $this->assertSame('\local_assistente\observer\course_observer::course_deleted', $byevent['\core\event\course_deleted']['callback']);
        $this->assertFalse($byevent['\core\event\course_deleted']['internal']);
        $this->assertTrue(class_exists(\core\event\course_deleted::class));
    }
}
