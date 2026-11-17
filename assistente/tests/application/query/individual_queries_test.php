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


namespace local_assistente\application\query;

use core\exception\required_capability_exception;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\course_erasure_repository;
use local_assistente\infrastructure\persistence\event_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the individual queries (T109; quickstart 018 Q1 to Q3, Q5 and Q7, Q10, Q12; DEC-37, DEC-39, DEC-40, DEC-42 D3, DEC-43,
 * DEC-45 D3). Synthetic data only: a course made here, with the rows that the handlers would have written.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(grades_query::class)]
#[CoversClass(frequency_query::class)]
#[CoversClass(below_criteria_query::class)]
final class individual_queries_test extends \advanced_testcase {
    /** @var \stdClass course with the assistant enabled and separate groups */
    private \stdClass $course;

    /** @var \stdClass[] students by name */
    private array $students = [];

    /** @var \stdClass[] groups A and B */
    private array $groups = [];

    /** @var \stdClass editing teacher of the course */
    private \stdClass $teacher;

    /** @var \stdClass[] page modules: kept and gone */
    private array $pages = [];

    /** @var int tick for the creation time of the analyses */
    private int $tick = 0;

    /** @var presentation */
    private presentation $presentation;

    /**
     * Ana (group A), Bruno (group B), Carla (A and B) and Eva (no group, nothing computed yet); an editing teacher; two pages, one of which
     * is removed from the course later.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        (new config_repository())->create((int) $this->course->id, true);
        foreach (['A', 'B'] as $name) {
            $this->groups[$name] = $generator->create_group(['courseid' => $this->course->id, 'name' => $name]);
        }
        foreach (['Ana' => ['A'], 'Bruno' => ['B'], 'Carla' => ['A', 'B'], 'Eva' => []] as $name => $in) {
            $this->students[$name] = $generator->create_and_enrol($this->course, 'student', ['firstname' => $name, 'lastname' => 'Teste']);
            foreach ($in as $group) {
                $generator->create_group_member(['groupid' => $this->groups[$group]->id, 'userid' => $this->students[$name]->id]);
            }
        }
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->pages = [
            'kept' => $generator->create_module('page', ['course' => $this->course->id, 'name' => 'Atividade mantida']),
            'gone' => $generator->create_module('page', ['course' => $this->course->id, 'name' => 'Atividade removida']),
        ];
        $strings = [];
        $string = [];
        include(__DIR__ . '/../../../lang/pt_br/local_assistente.php');
        $strings = $string;
        $this->presentation = new presentation(',', fn(string $key): string => $strings[$key], fn(int $time): string => gmdate('d/m/Y', $time));
    }

    /**
     * Insert a row filling the required columns the test does not care about.
     *
     * @param string $table
     * @param array $values
     * @return int
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
                    $column->meta_type === 'I' => 1,
                    in_array($column->meta_type, ['N', 'F'], true) => 0,
                    default => 'x',
                };
            }
        }
        return (int) $DB->insert_record($table, (object) ($values + $record));
    }

    /**
     * An analysis with its event, processing and snapshot.
     *
     * @param event_type $type
     * @param array $payload
     * @param string $analysistype
     * @param array|null $snapshot
     * @return int the id of the analysis
     */
    private function analysis(event_type $type, array $payload, string $analysistype, ?array $snapshot): int {
        $events = new event_repository();
        $event = $events->create_event((int) $this->course->id, $type, $payload, time());
        $attempt = $events->begin_attempt((int) $event->id, time());
        $processing = $events->find_processing((int) $event->id);
        if ($snapshot !== null) {
            $events->save_context((int) $processing->id, json_encode($snapshot + ['course_id' => (int) $this->course->id]), time());
        }
        $id = $this->row('local_assistente_analysis', [
            'course_id' => $this->course->id, 'processing_id' => $processing->id, 'analysis_type' => $analysistype, 'cmid' => null,
            'summary' => null, 'created_at' => 1700000000 + (++$this->tick),
        ]);
        $events->complete_attempt((int) $attempt->id, time(), 'ok');
        return $id;
    }

    /**
     * The performance of a student: a total and a grade for each page given.
     *
     * @param string $student
     * @param float|null $total the course total of the snapshot, null for a snapshot without it
     * @param array $grades page key => grade
     * @return int the analysis
     */
    private function performance(string $student, ?float $total, array $grades): int {
        $id = (int) $this->students[$student]->id;
        $snapshot = ['format' => 1, 'kind' => 'grade_performance', 'student_id' => $id, 'activities_with_grade' => count($grades)];
        if ($total !== null) {
            $snapshot['course_total_percent'] = $total;
        }
        $analysis = $this->analysis(event_type::GRADE_CHANGED, ['userid' => $id, 'itemid' => 9 + $this->tick, 'occurred_at' => $this->tick], 'grade_performance', $snapshot);
        foreach ($grades as $page => $grade) {
            $this->row('local_assistente_performance', ['analysis_id' => $analysis, 'student_id' => $id, 'activity_id' => $this->pages[$page]->cmid, 'grade' => $grade]);
        }
        return $analysis;
    }

    /**
     * The frequency of some students in one analysis.
     *
     * @param array $students name => [total, participating, percentage, accessed, [page => participated]]
     * @return int the analysis
     */
    private function frequency(array $students): int {
        $access = [];
        foreach ($students as $name => $data) {
            $access[] = ['student_id' => (int) $this->students[$name]->id, 'accessed' => $data[3], 'last_access' => $data[3] ? 1700000000 : null];
        }
        $analysis = $this->analysis(event_type::REFERENCE_DATE_REACHED, ['cmid' => (int) $this->pages['kept']->cmid, 'reference_date' => $this->tick], 'frequency',
            ['format' => 1, 'kind' => 'frequency', 'course_access' => $access, 'activities_without_date' => []]);
        foreach ($students as $name => [$total, $participating, $percentage, , $activities]) {
            $frequency = $this->row('local_assistente_frequency', [
                'analysis_id' => $analysis, 'student_id' => $this->students[$name]->id, 'section_id' => 7, 'total_activities' => $total,
                'participating_activities' => $participating, 'percentage' => $percentage,
            ]);
            foreach ($activities as $page => $participated) {
                $this->row('local_assistente_frequency_activity', [
                    'frequency_id' => $frequency, 'activity_id' => $this->pages[$page]->cmid, 'participated' => $participated ? 1 : 0, 'reference_date' => 1000,
                ]);
            }
        }
        return $analysis;
    }

    /**
     * The criteria results of some students in one analysis.
     *
     * @param array $students name => [activity, forum, frequency] statuses
     * @return int the analysis
     */
    private function criteria(array $students): int {
        $analysis = $this->analysis(event_type::COURSE_CRITERIA_CHECK_REQUESTED, ['course_id' => (int) $this->course->id], 'criteria_check',
            ['format' => 1, 'kind' => 'criteria_check', 'scope' => 'course', 'students' => []]);
        foreach ($students as $name => [$activity, $forum, $frequency]) {
            $this->row('local_assistente_criteria_result', [
                'analysis_id' => $analysis, 'student_id' => $this->students[$name]->id, 'activity_status' => $activity,
                'forum_status' => $forum, 'frequency_status' => $frequency,
            ]);
        }
        return $analysis;
    }

    /**
     * The rows of a query by student name.
     *
     * @param array $result
     * @return array[]
     */
    private function by_name(array $result): array {
        return array_column($result['rows'], null, 'name');
    }

    /**
     * Build a query with the Portuguese presentation.
     *
     * @param string $class
     * @return object
     */
    private function make(string $class): object {
        return new $class(null, null, null, $this->presentation);
    }

    /**
     * Digest of every table of the plugin, to tell that a query wrote nothing.
     *
     * @return string
     */
    private function digest(): string {
        global $DB;
        $all = [];
        foreach (course_erasure_repository::TABLES as $table) {
            $all[$table] = array_values($DB->get_records($table, null, 'id'));
        }
        return md5(json_encode($all));
    }

    /**
     * Q1 and Q3: the teacher of the course gets the grades with only the fields of the contract, the activity grade and the final total of the
     * course truncated to two decimals, a student with no execution as "sem dados", and the names from A to Z.
     */
    public function test_grades_of_the_own_course_with_only_the_contract_fields(): void {
        $this->performance('Ana', 66.666, ['kept' => 80.5, 'gone' => 66.669]);
        $this->performance('Carla', 0.0, ['kept' => 0.0]);
        $this->performance('Bruno', null, []);

        $result = $this->make(grades_query::class)->query((int) $this->course->id, (int) $this->teacher->id);

        $this->assertTrue($result['available']);
        $this->assertNull($result['message']);
        $this->assertSame(['Ana Teste', 'Bruno Teste', 'Carla Teste', 'Eva Teste'], array_column($result['rows'], 'name'));
        foreach ($result['rows'] as $row) {
            $this->assertSame(['id', 'name', 'course_total', 'activities'], array_keys($row));
            $this->assertSame(['state', 'text'], array_keys($row['course_total']));
            foreach ($row['activities'] as $activity) {
                $this->assertSame(['activity_id', 'activity', 'grade', 'source'], array_keys($activity));
            }
        }
        $rows = $this->by_name($result);
        $this->assertSame(['state' => 'valid', 'text' => '66,66%'], $rows['Ana Teste']['course_total']);
        $this->assertSame(['80,50%', '66,66%'], array_map(fn($a) => $a['grade']['text'], $rows['Ana Teste']['activities']), 'Truncated, not rounded');
        $this->assertSame('Atividade mantida', $rows['Ana Teste']['activities'][0]['activity']);
        $this->assertSame(['state' => 'valid', 'text' => '0,00%'], $rows['Carla Teste']['course_total'], 'A real zero is zero');
        $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $rows['Bruno Teste']['course_total'], 'No total in the snapshot');
        $this->assertSame([], $rows['Bruno Teste']['activities']);
        $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $rows['Eva Teste']['course_total'], 'Never zero');
        $this->assertSame([], $rows['Eva Teste']['activities']);
    }

    /**
     * The frequency with only the fields of the contract: total, participated and percentage by section, the participation by activity, the
     * access to the course as complementary evidence; a section without activities and a student without execution are "sem dados".
     */
    public function test_frequency_with_only_the_contract_fields(): void {
        $this->frequency([
            'Ana' => [2, 1, 50, true, ['kept' => true, 'gone' => false]],
            'Bruno' => [0, 0, 0, false, []],
            'Carla' => [3, 2, 66.666, true, []],
        ]);

        $result = $this->make(frequency_query::class)->query((int) $this->course->id, (int) $this->teacher->id);

        $rows = $this->by_name($result);
        foreach ($result['rows'] as $row) {
            $this->assertSame(['id', 'name', 'status', 'course_access', 'sections'], array_keys($row));
            $this->assertSame(['state', 'text'], array_keys($row['status']));
            foreach ($row['sections'] as $section) {
                $this->assertSame(['section_id', 'total', 'participating', 'percentage', 'activities'], array_keys($section));
                foreach ($section['activities'] as $activity) {
                    $this->assertSame(['activity_id', 'activity', 'participated', 'source'], array_keys($activity));
                }
            }
        }
        $ana = $rows['Ana Teste']['sections'][0];
        $this->assertSame([2, 1, 'valid', '50,00%'], [$ana['total'], $ana['participating'], $ana['percentage']['state'], $ana['percentage']['text']]);
        $this->assertSame([true, false], array_column($ana['activities'], 'participated'));
        $this->assertSame('acessou o curso (último acesso: 14/11/2023)', $rows['Ana Teste']['course_access']);
        $this->assertSame(['state' => 'valid', 'text' => null], $rows['Ana Teste']['status']);
        $this->assertSame('66,66%', $rows['Carla Teste']['sections'][0]['percentage']['text']);
        $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $rows['Bruno Teste']['sections'][0]['percentage'], 'Total zero is not 0%');
        $this->assertSame('não acessou o curso', $rows['Bruno Teste']['course_access'], 'Evidence, beside the main state');
        $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $rows['Eva Teste']['status']);
        $this->assertSame([], $rows['Eva Teste']['sections']);
        $this->assertNull($rows['Eva Teste']['course_access'], 'Nothing is invented');
    }

    /**
     * The students below the criteria, with the status of each criterion: "atende", "abaixo" and "sem dados"; one with only "atende" and
     * "sem dados", and one with no result, are not listed.
     */
    public function test_students_below_the_criteria(): void {
        $this->criteria(['Ana' => [0, 1, 2], 'Bruno' => [1, 2, 2], 'Carla' => [2, 0, 1]]);

        $result = $this->make(below_criteria_query::class)->query((int) $this->course->id, (int) $this->teacher->id);

        $this->assertSame(['Ana Teste', 'Carla Teste'], array_column($result['rows'], 'name'));
        foreach ($result['rows'] as $row) {
            $this->assertSame(['id', 'name', 'criteria'], array_keys($row));
            $this->assertSame(['activity', 'forum', 'frequency'], array_keys($row['criteria']));
            foreach ($row['criteria'] as $criterion) {
                $this->assertSame(['status', 'text'], array_keys($criterion));
            }
        }
        $rows = $this->by_name($result);
        $this->assertSame(['abaixo', 'atende', 'sem dados'], array_column($rows['Ana Teste']['criteria'], 'text'));
        $this->assertSame([0, 1, 2], array_column($rows['Ana Teste']['criteria'], 'status'));
        $this->assertSame(['sem dados', 'abaixo', 'atende'], array_column($rows['Carla Teste']['criteria'], 'text'));
    }

    /**
     * The execution shown is the current one, the last successful: an older analysis is kept in the history and not shown.
     */
    public function test_the_current_execution_is_the_latest(): void {
        $this->performance('Ana', 50.0, ['kept' => 50.0]);
        $this->frequency(['Ana' => [2, 0, 0, false, []]]);
        $this->criteria(['Ana' => [0, 0, 0]]);
        $this->performance('Ana', 90.0, ['kept' => 90.0]);
        $this->frequency(['Ana' => [2, 2, 100, true, []]]);
        $this->criteria(['Ana' => [1, 1, 1]]);
        $scope = (int) $this->course->id;

        $grades = $this->by_name($this->make(grades_query::class)->query($scope, (int) $this->teacher->id));
        $frequency = $this->by_name($this->make(frequency_query::class)->query($scope, (int) $this->teacher->id));
        $below = $this->by_name($this->make(below_criteria_query::class)->query($scope, (int) $this->teacher->id));

        $this->assertSame('90,00%', $grades['Ana Teste']['course_total']['text']);
        $this->assertSame('100,00%', $frequency['Ana Teste']['sections'][0]['percentage']['text']);
        $this->assertArrayNotHasKey('Ana Teste', $below, 'The old result below the criteria is history');
    }

    /**
     * Q12 and DEC-42 D3: an activity removed in Moodle keeps its grade and its participation and says "fonte removida"; the one that exists
     * has its name and no indication.
     */
    public function test_a_removed_activity_keeps_the_result_and_says_so(): void {
        $this->performance('Ana', 70.0, ['kept' => 80.0, 'gone' => 60.0]);
        $this->frequency(['Ana' => [2, 1, 50, true, ['kept' => true, 'gone' => false]]]);
        (new \core_courseformat\local\cmactions(get_course($this->course->id)))->delete((int) $this->pages['gone']->cmid);
        $before = $this->digest();

        $grades = $this->by_name($this->make(grades_query::class)->query((int) $this->course->id, (int) $this->teacher->id))['Ana Teste']['activities'];
        $frequency = $this->by_name($this->make(frequency_query::class)->query((int) $this->course->id, (int) $this->teacher->id))['Ana Teste']['sections'][0]['activities'];

        foreach ([$grades, $frequency] as $activities) {
            $this->assertSame([null, 'fonte removida'], array_column($activities, 'source'));
            $this->assertSame(['Atividade mantida', null], array_column($activities, 'activity'));
        }
        $this->assertSame(['80,00%', '60,00%'], array_map(fn($a) => $a['grade']['text'], $grades), 'The grade stays');
        $this->assertSame([true, false], array_column($frequency, 'participated'));
        $this->assertSame($before, $this->digest(), 'The history was not changed');
    }

    /**
     * Q2: a teacher of another course, a student and a user with no role are refused by the backend, in every query and with or without a
     * group filter, whether or not the assistant is enabled; nothing is returned.
     */
    public function test_a_course_that_is_not_theirs_is_refused(): void {
        $generator = $this->getDataGenerator();
        $other = $generator->create_course();
        $strangers = [$generator->create_and_enrol($other, 'editingteacher'), $this->students['Ana'], $generator->create_user()];
        $disabled = $generator->create_course();
        (new config_repository())->create((int) $disabled->id, false);
        $queries = [grades_query::class, frequency_query::class, below_criteria_query::class];

        foreach ($strangers as $user) {
            foreach ($queries as $class) {
                foreach ([[(int) $this->course->id, null], [(int) $this->course->id, (int) $this->groups['A']->id], [(int) $disabled->id, null]] as [$course, $group]) {
                    try {
                        $this->make($class)->query($course, (int) $user->id, $group);
                        $this->fail("$class gave an answer to user {$user->id}");
                    } catch (required_capability_exception) {
                        $this->addToAssertionCount(1);
                    }
                }
            }
        }
    }

    /**
     * DEC-45 D3: a course with the assistant disabled, or with no configuration, shows no results and says "assistente não habilitado" to
     * whoever may view it; the same for all the queries.
     */
    public function test_a_course_without_the_assistant_enabled_shows_no_results(): void {
        $generator = $this->getDataGenerator();
        $off = $generator->create_course();
        (new config_repository())->create((int) $off->id, false);
        $none = $generator->create_course();
        foreach ([$off, $none] as $course) {
            $teacher = $generator->create_and_enrol($course, 'editingteacher');
            $generator->create_and_enrol($course, 'student');
            foreach ([grades_query::class, frequency_query::class, below_criteria_query::class] as $class) {
                $this->assertSame(
                    ['available' => false, 'message' => 'assistente não habilitado', 'rows' => []],
                    $this->make($class)->query((int) $course->id, (int) $teacher->id)
                );
            }
        }
    }

    /**
     * Q5 and Q7 (DEC-43): the filter by group is applied to the three queries, and in separate groups a teacher without access to all groups
     * only gets the students of the groups they can see.
     */
    public function test_the_filter_by_group_and_the_visible_groups(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $this->performance('Ana', 10.0, []);
        $this->performance('Bruno', 20.0, []);
        $this->performance('Carla', 30.0, []);
        $this->frequency(['Ana' => [1, 1, 100, true, []], 'Bruno' => [1, 0, 0, true, []], 'Carla' => [1, 1, 100, true, []]]);
        $this->criteria(['Ana' => [0, 1, 1], 'Bruno' => [0, 1, 1], 'Carla' => [0, 1, 1]]);
        $assistant = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->groups['A']->id, 'userid' => $assistant->id]);
        assign_capability('local/assistente:view', CAP_ALLOW, $DB->get_field('role', 'id', ['shortname' => 'teacher']),
            \context_course::instance($this->course->id)->id, true);
        $course = (int) $this->course->id;

        foreach ([grades_query::class, frequency_query::class, below_criteria_query::class] as $class) {
            $query = $this->make($class);
            $names = fn(array $result) => array_column($result['rows'], 'name');
            $this->assertSame(['Ana Teste', 'Carla Teste'], $names($query->query($course, (int) $this->teacher->id, (int) $this->groups['A']->id)), $class);
            $this->assertSame(['Bruno Teste', 'Carla Teste'], $names($query->query($course, (int) $this->teacher->id, (int) $this->groups['B']->id)), $class);
            $this->assertSame(['Ana Teste', 'Carla Teste'], $names($query->query($course, (int) $assistant->id)), "$class: restricted teacher");
            $this->assertSame([], $names($query->query($course, (int) $assistant->id, (int) $this->groups['B']->id)), "$class: group not visible");
            $this->assertSame([], $names($query->query($course, (int) $this->teacher->id, 987654)), "$class: group that does not exist");
        }
    }

    /**
     * The queries read and write nothing: the tables of the plugin are the same after them.
     */
    public function test_queries_write_nothing(): void {
        $this->performance('Ana', 70.0, ['kept' => 80.0]);
        $this->frequency(['Ana' => [1, 1, 100, true, ['kept' => true]]]);
        $this->criteria(['Ana' => [0, 1, 2]]);
        $before = $this->digest();

        foreach ([grades_query::class, frequency_query::class, below_criteria_query::class] as $class) {
            $this->make($class)->query((int) $this->course->id, (int) $this->teacher->id);
        }

        $this->assertSame($before, $this->digest());
    }
}
