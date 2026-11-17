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
use local_assistente\domain\result_state;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the students that a query may show (T108; quickstart 018 Q5 to Q8, spec-022 Q6 and Q7; DEC-43, DEC-47). Synthetic data only.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(student_scope::class)]
final class student_scope_test extends \advanced_testcase {
    /** @var \stdClass course with separate groups */
    private \stdClass $course;

    /** @var \stdClass[] groups A and B */
    private array $groups = [];

    /** @var \stdClass[] students by name */
    private array $students = [];

    /** @var \stdClass non-editing teacher, member of group A, with the view capability granted and without access to all groups */
    private \stdClass $teacher;

    /** @var \stdClass editing teacher (the archetype has the view capability and access to all groups) */
    private \stdClass $editing;

    /**
     * A course with separate groups: Ana in A, Bruno in B, Carla in A and B, Davi in no group, a suspended student, a teacher in group A.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $this->groups = [
            'A' => $generator->create_group(['courseid' => $this->course->id, 'name' => 'A']),
            'B' => $generator->create_group(['courseid' => $this->course->id, 'name' => 'B']),
        ];
        foreach (['Ana' => ['A'], 'Bruno' => ['B'], 'Carla' => ['A', 'B'], 'Davi' => []] as $name => $in) {
            $this->students[$name] = $generator->create_and_enrol($this->course, 'student', ['firstname' => $name, 'lastname' => 'Teste']);
            foreach ($in as $group) {
                $generator->create_group_member(['groupid' => $this->groups[$group]->id, 'userid' => $this->students[$name]->id]);
            }
        }
        $this->students['Suspensa'] = $generator->create_and_enrol(
            $this->course, 'student', ['firstname' => 'Suspensa', 'lastname' => 'Teste'], 'manual', 0, 0, ENROL_USER_SUSPENDED
        );
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->groups['A']->id, 'userid' => $this->teacher->id]);
        $context = \context_course::instance($this->course->id);
        assign_capability('local/assistente:view', CAP_ALLOW, $DB->get_field('role', 'id', ['shortname' => 'teacher']), $context->id, true);
        $this->editing = $generator->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * The ids of the rows.
     *
     * @param array[] $rows
     * @return int[]
     */
    private function ids(array $rows): array {
        return array_column($rows, 'id');
    }

    /**
     * The ids of some of the students.
     *
     * @param string ...$names
     * @return int[] sorted
     */
    private function expected(string ...$names): array {
        $ids = array_map(fn($name) => (int) $this->students[$name]->id, $names);
        sort($ids);
        return $ids;
    }

    /**
     * Only active enrolments with a role that the gradebook evaluates: no suspended student, no teacher, no one not enrolled; with names.
     */
    public function test_students_are_active_enrolments_with_an_evaluated_role(): void {
        $stranger = $this->getDataGenerator()->create_user();

        $rows = (new student_scope())->individual($this->course->id, (int) $this->editing->id);

        $this->assertSame($this->expected('Ana', 'Bruno', 'Carla', 'Davi'), $this->ids($rows));
        $this->assertNotContains((int) $this->students['Suspensa']->id, $this->ids($rows));
        $this->assertNotContains((int) $this->teacher->id, $this->ids($rows));
        $this->assertNotContains((int) $stranger->id, $this->ids($rows));
        $this->assertSame(['Ana Teste', 'Bruno Teste', 'Carla Teste', 'Davi Teste'], $this->sorted_names($rows));
    }

    /**
     * The names of the rows, sorted.
     *
     * @param array[] $rows
     * @return string[]
     */
    private function sorted_names(array $rows): array {
        $names = array_column($rows, 'name');
        sort($names);
        return $names;
    }

    /**
     * Q5 and DEC-43 D1: the filter by group A gives the students of A; a student in two groups is listed once without a filter.
     */
    public function test_the_filter_by_group_in_an_individual_query(): void {
        $scope = new student_scope();
        $editing = (int) $this->editing->id;

        $this->assertSame($this->expected('Ana', 'Carla'), $this->ids($scope->individual($this->course->id, $editing, (int) $this->groups['A']->id)));
        $this->assertSame($this->expected('Bruno', 'Carla'), $this->ids($scope->individual($this->course->id, $editing, (int) $this->groups['B']->id)));
        $all = $this->ids($scope->individual($this->course->id, $editing));
        $this->assertSame(count($all), count(array_unique($all)), 'Carla is listed once');
        $this->assertSame([], $scope->individual($this->course->id, $editing, 987654), 'A group that does not exist has no students');
    }

    /**
     * A group of another course gives no students, not the students of that group.
     */
    public function test_a_group_of_another_course_gives_nothing(): void {
        $other = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $other->id]);
        $outsider = $this->getDataGenerator()->create_and_enrol($other, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $outsider->id]);

        $this->assertSame([], (new student_scope())->individual($this->course->id, (int) $this->editing->id, (int) $group->id));
    }

    /**
     * Q7 and DEC-43 D2: in separate groups, a teacher without access to all groups sees only the students of their groups (and Carla,
     * who is in A and B, once), the group B is not offered and a filter by B gives nothing.
     */
    public function test_separate_groups_restrict_a_teacher_without_the_capability(): void {
        $scope = new student_scope();
        $teacher = (int) $this->teacher->id;
        $course = $this->course->id;

        $this->assertSame($this->expected('Ana', 'Carla'), $this->ids($scope->individual($course, $teacher)));
        $this->assertSame($this->expected('Ana', 'Carla'), $this->ids($scope->individual($course, $teacher, (int) $this->groups['A']->id)));
        $this->assertSame([], $scope->individual($course, $teacher, (int) $this->groups['B']->id), 'A group the teacher cannot see');
        $this->assertSame([(int) $this->groups['A']->id => 'A'], $scope->groups($course, $teacher));
        $this->assertNotContains((int) $this->students['Davi']->id, $this->ids($scope->individual($course, $teacher)), 'Davi has no group');
    }

    /**
     * Q8 and DEC-43 D2: with the capability to access all groups every student is listed and every group is offered; the capability is
     * read when the query runs.
     */
    public function test_the_capability_to_access_all_groups_lifts_the_restriction(): void {
        global $DB;
        $scope = new student_scope();
        $course = $this->course->id;
        $this->assertCount(2, $scope->individual($course, (int) $this->teacher->id));

        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $DB->get_field('role', 'id', ['shortname' => 'teacher']),
            \context_course::instance($course)->id, true);

        $this->assertSame($this->expected('Ana', 'Bruno', 'Carla', 'Davi'), $this->ids($scope->individual($course, (int) $this->teacher->id)));
        $this->assertCount(2, $scope->groups($course, (int) $this->teacher->id));
        $this->assertSame($this->expected('Ana', 'Bruno', 'Carla', 'Davi'), $this->ids($scope->individual($course, (int) $this->editing->id)));
        $this->assertCount(2, $scope->groups($course, (int) $this->editing->id));
    }

    /**
     * Without separate groups nobody is restricted, whatever the memberships.
     */
    public function test_visible_or_no_groups_do_not_restrict(): void {
        global $DB;
        $DB->set_field('course', 'groupmode', NOGROUPS, ['id' => $this->course->id]);
        $DB->set_field('course', 'groupmodeforce', 0, ['id' => $this->course->id]);
        $this->assertSame(
            $this->expected('Ana', 'Bruno', 'Carla', 'Davi'),
            $this->ids((new student_scope())->individual($this->course->id, (int) $this->teacher->id))
        );
    }

    /**
     * Q6 and DEC-43 D1/D2: an aggregate query has every student of the course, even for a restricted teacher, and refuses a group filter.
     */
    public function test_aggregate_queries_are_not_filtered_by_group(): void {
        $scope = new student_scope();
        $all = $this->expected('Ana', 'Bruno', 'Carla', 'Davi');

        $this->assertSame($all, $this->ids($scope->aggregate($this->course->id, (int) $this->teacher->id)), 'Restricted teacher');
        $this->assertSame($all, $this->ids($scope->aggregate($this->course->id, (int) $this->editing->id)));
        foreach ([(int) $this->groups['A']->id, 987654, 0] as $group) {
            try {
                $scope->aggregate($this->course->id, (int) $this->editing->id, $group);
                $this->fail('The filter by group was accepted');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * The backend authorizes: a student, a teacher without the capability, a teacher of another course and a user with no role are
     * refused in every method, and nothing is returned.
     */
    public function test_the_backend_requires_the_view_capability(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $scope = new student_scope();
        $course = $this->course->id;
        $noncapable = $generator->create_and_enrol($this->course, 'teacher');
        $otherteacher = $generator->create_and_enrol($generator->create_course(), 'editingteacher');
        $denied = [$this->students['Ana'], $noncapable, $otherteacher, $generator->create_user()];
        $DB->delete_records('role_capabilities', ['capability' => 'local/assistente:view', 'roleid' => $DB->get_field('role', 'id', ['shortname' => 'teacher'])]);

        foreach ($denied as $user) {
            foreach ([
                fn() => $scope->individual($course, (int) $user->id),
                fn() => $scope->individual($course, (int) $user->id, (int) $this->groups['A']->id),
                fn() => $scope->aggregate($course, (int) $user->id),
                fn() => $scope->groups($course, (int) $user->id),
            ] as $call) {
                try {
                    $call();
                    $this->fail('User ' . $user->id . ' was not refused');
                } catch (required_capability_exception) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    /**
     * Spec-022 Q6: a student enrolled now is in the list at once and, with no result yet, shows "no data", never zero; the others keep
     * their results.
     */
    public function test_a_new_student_is_listed_with_no_data(): void {
        $scope = new student_scope();
        $editing = (int) $this->editing->id;
        $ana = (int) $this->students['Ana']->id;
        $results = [$ana => ['state' => result_state::VALID, 'value' => 66.666]];
        $new = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Eva', 'lastname' => 'Teste']);

        $rows = $scope->with_results($scope->individual($this->course->id, $editing), $results);

        $byid = array_column($rows, null, 'id');
        $this->assertArrayHasKey((int) $new->id, $byid, 'Listed as soon as enrolled');
        $this->assertSame(result_state::NO_DATA, $byid[(int) $new->id]['state']);
        $this->assertNull($byid[(int) $new->id]['value']);
        $this->assertSame(result_state::VALID, $byid[$ana]['state']);
        $this->assertSame(66.666, $byid[$ana]['value']);
        $this->assertSame(4, count(array_filter($rows, fn($r) => $r['state'] === result_state::NO_DATA)), 'Everyone without a result, the new one included');
        $this->assertSame('sem dados', (new presentation(',', fn($key) => ['query:no_data' => 'sem dados'][$key]))->value($byid[(int) $new->id]['state'], 0));
    }

    /**
     * Spec-022 Q7 and DEC-42: a student removed from the course leaves the lists, including the result that came with them, and what the
     * plugin kept of them is still there.
     */
    public function test_a_removed_student_leaves_the_lists_and_the_history_stays(): void {
        global $DB;
        $scope = new student_scope();
        $editing = (int) $this->editing->id;
        $bruno = (int) $this->students['Bruno']->id;
        $analysis = $DB->insert_record('local_assistente_analysis', (object) [
            'course_id' => $this->course->id, 'processing_id' => 1, 'analysis_type' => 'grade_performance', 'cmid' => null, 'summary' => null, 'created_at' => time(),
        ]);
        $performance = $DB->insert_record('local_assistente_performance', (object) [
            'analysis_id' => $analysis, 'student_id' => $bruno, 'activity_id' => 1, 'grade' => 80, 'created_at' => time(),
        ]);
        $results = [$bruno => ['state' => result_state::VALID, 'value' => 80.0]];
        $this->assertContains($bruno, $this->ids($scope->with_results($scope->individual($this->course->id, $editing), $results)));
        $before = $DB->get_records('local_assistente_performance');

        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($instance, $bruno);

        foreach ([$scope->individual($this->course->id, $editing), $scope->aggregate($this->course->id, $editing),
                  $scope->with_results($scope->individual($this->course->id, $editing), $results)] as $list) {
            $this->assertNotContains($bruno, $this->ids($list));
        }
        $this->assertNotContains($bruno, $this->ids($scope->individual($this->course->id, $editing, (int) $this->groups['B']->id)));
        $this->assertEquals($before, $DB->get_records('local_assistente_performance'), 'The history of the student is untouched');
        $this->assertTrue($DB->record_exists('local_assistente_performance', ['id' => $performance]));
    }

    /**
     * An empty course gives empty lists, and results of nobody are not invented.
     */
    public function test_a_course_without_students(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $scope = new student_scope();

        $this->assertSame([], $scope->individual($course->id, (int) $teacher->id));
        $this->assertSame([], $scope->aggregate($course->id, (int) $teacher->id));
        $this->assertSame([], $scope->with_results([], [99 => ['state' => result_state::VALID, 'value' => 1.0]]));
    }
}
