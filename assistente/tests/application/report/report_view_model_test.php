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

namespace local_assistente\application\report;

use core\exception\required_capability_exception;
use local_assistente\application\query\presentation;
use local_assistente\domain\event\event_type;
use local_assistente\domain\report\report_schedule;
use local_assistente\domain\report\version_filter;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\report_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the reading of a report (T115; quickstart 019 Q4, Q10, Q11 and Q12; DEC-42 D1, DEC-43 D2, DEC-44 D1) and of its filters by
 * version and execution date (plan 020 Q-020-02). Synthetic data only.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_view_model::class)]
final class report_view_model_test extends \advanced_testcase {
    /** @var \stdClass course with the assistant enabled and separate groups */
    private \stdClass $course;

    /** @var \stdClass[] students by name */
    private array $students = [];

    /** @var \stdClass[] groups A and B */
    private array $groups = [];

    /** @var \stdClass editing teacher */
    private \stdClass $teacher;

    /** @var \stdClass the page the grades are about */
    private \stdClass $page;

    /** @var report_view_model */
    private report_view_model $view;

    /** @var report_repository */
    private report_repository $reports;

    /** @var report_builder */
    private report_builder $builder;

    /** @var int */
    private int $reportid;

    /** @var int tick for the instants of the versions */
    private int $tick = 1790000000;

    /**
     * Ana (group A), Bruno (group B), Carla (A and B); an editing teacher; the report of the course with no version.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        (new config_repository())->create((int) $this->course->id, true);
        foreach (['A', 'B'] as $name) {
            $this->groups[$name] = $generator->create_group(['courseid' => $this->course->id, 'name' => $name]);
        }
        foreach (['Ana' => ['A'], 'Bruno' => ['B'], 'Carla' => ['A', 'B']] as $name => $in) {
            $this->students[$name] = $generator->create_and_enrol($this->course, 'student', ['firstname' => $name, 'lastname' => 'Teste']);
            foreach ($in as $group) {
                $generator->create_group_member(['groupid' => $this->groups[$group]->id, 'userid' => $this->students[$name]->id]);
            }
        }
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->page = $generator->create_module('page', ['course' => $this->course->id]);
        $string = [];
        include(__DIR__ . '/../../../lang/pt_br/local_assistente.php');
        $strings = $string;
        $this->view = new report_view_model(
            presentation: new presentation(',', fn(string $key): string => $strings[$key], fn(int $time): string => gmdate('d/m/Y', $time))
        );
        $this->reports = new report_repository();
        $this->builder = new report_builder();
        $this->reportid = (int) $this->reports->create((int) $this->course->id, report_schedule::calculate([], null, 'UTC', null, 0))->id;
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
     * An analysis with its event and processing; the payload names the student, as the grade handler's event does.
     *
     * @param string $type
     * @param array $payload
     * @param int|null $courseid
     * @return int
     */
    private function analysis(string $type, array $payload = [], ?int $courseid = null): int {
        $events = new event_repository();
        $courseid ??= (int) $this->course->id;
        $event = $events->create_event($courseid, event_type::GRADE_CHANGED, $payload + ['userid' => 1, 'itemid' => $this->tick, 'occurred_at' => $this->tick], time());
        $attempt = $events->begin_attempt((int) $event->id, time());
        $processing = $events->find_processing((int) $event->id);
        $id = $this->row('local_assistente_analysis', [
            'course_id' => $courseid, 'processing_id' => $processing->id, 'analysis_type' => $type, 'cmid' => null, 'summary' => null,
            'created_at' => $this->tick,
        ]);
        $events->complete_attempt((int) $attempt->id, time(), 'ok');
        return $id;
    }

    /**
     * The grade of a student on the page.
     *
     * @param string $student
     * @param float $grade
     * @param int|null $courseid
     * @return int the analysis
     */
    private function grade(string $student, float $grade, ?int $courseid = null): int {
        $id = (int) $this->students[$student]->id;
        $analysis = $this->analysis('grade_performance', ['userid' => $id], $courseid);
        $this->row('local_assistente_performance', ['analysis_id' => $analysis, 'student_id' => $id, 'activity_id' => $this->page->cmid, 'grade' => $grade]);
        return $analysis;
    }

    /**
     * Frequency of students in one analysis.
     *
     * @param string[] $names
     * @return int the analysis
     */
    private function frequency(array $names): int {
        $analysis = $this->analysis('frequency');
        foreach ($names as $name) {
            $this->row('local_assistente_frequency', [
                'analysis_id' => $analysis, 'student_id' => $this->students[$name]->id, 'section_id' => 7, 'total_activities' => 2,
                'participating_activities' => 1, 'percentage' => 50,
            ]);
        }
        return $analysis;
    }

    /**
     * Criteria results (all three below) of students in one analysis.
     *
     * @param string[] $names
     * @return int the analysis
     */
    private function criteria(array $names): int {
        $analysis = $this->analysis('criteria_check');
        foreach ($names as $name) {
            $this->row('local_assistente_criteria_result', [
                'analysis_id' => $analysis, 'student_id' => $this->students[$name]->id, 'activity_status' => 0, 'forum_status' => 1,
                'frequency_status' => 2,
            ]);
        }
        return $analysis;
    }

    /**
     * Store a version whose manifest references the given blocks.
     *
     * @param array $blocks block name => block input, the others no_data
     * @param string $type
     * @return \stdClass the version
     */
    private function version(array $blocks, string $type = 'periodic'): \stdClass {
        $all = $blocks + array_fill_keys(report_builder::BLOCKS, ['state' => 'no_data']);
        $schedule = report_schedule::calculate([], null, 'UTC', null, 0);
        return $this->reports->add_version($this->reportid, $this->builder->build($type, ++$this->tick, $all), $schedule, $this->tick);
    }

    /**
     * Names of the rows of a block of the first rendered version.
     *
     * @param array $result
     * @param string $block
     * @return string[]
     */
    private function names(array $result, string $block): array {
        return array_column($result['rows'][0]['blocks'][$block]['rows'], 'name');
    }

    /**
     * Q4: a user without `local/assistente:view` is denied, whether or not there is a report.
     */
    public function test_a_user_without_access_is_denied(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $student = $this->students['Ana'];
        $course = (int) $this->course->id;
        foreach ([(int) $outsider->id, (int) $student->id] as $user) {
            try {
                $this->view->current($course, $user);
                $this->fail('Access must be denied');
            } catch (required_capability_exception) {
                $this->assertTrue(true);
            }
        }
        $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 80)]]]);
        $this->expectException(required_capability_exception::class);
        $this->view->history($course, (int) $outsider->id);
    }

    /**
     * A course without version gives no rows; a course where the assistant is not enabled gives the message and no rows.
     */
    public function test_no_version_and_assistant_not_enabled(): void {
        $course = (int) $this->course->id;
        $teacher = (int) $this->teacher->id;
        $this->assertSame(['available' => true, 'message' => null, 'rows' => []], $this->view->current($course, $teacher));

        $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 80)]]]);
        (new config_repository())->update((object) ['id' => (new config_repository())->find_by(['course_id' => $course])->id, 'enabled' => 0]);

        $result = $this->view->current($course, $teacher);
        $this->assertFalse($result['available']);
        $this->assertSame([], $result['rows']);
        $this->assertNotNull($result['message']);
    }

    /**
     * Q10: an old version shows the values of the analyses it references, the current one the values it references.
     */
    public function test_an_old_version_shows_its_own_values(): void {
        $course = (int) $this->course->id;
        $teacher = (int) $this->teacher->id;
        $first = $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 50)]]]);
        $second = $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 90)]]], 'final');

        $history = $this->view->history($course, $teacher);
        $current = $this->view->current($course, $teacher);

        $this->assertSame([(int) $second->version, (int) $first->version], array_column($history['rows'], 'version'));
        $this->assertSame('final', $history['rows'][0]['type']);
        $text = fn(array $row): string => $row['blocks']['grades']['rows'][0]['activities'][0]['grade']['text'];
        $this->assertSame('90,00%', $text($history['rows'][0]));
        $this->assertSame('50,00%', $text($history['rows'][1]));
        $this->assertSame($history['rows'][0], $current['rows'][0]);
        $this->assertSame('no_data', $current['rows'][0]['blocks']['frequency']['state']);
        $this->assertSame([], $current['rows'][0]['blocks']['frequency']['rows']);
    }

    /**
     * Q11: separate groups. The individual blocks follow the groups the user can see and the filter; the aggregate blocks do not.
     */
    public function test_individual_blocks_follow_the_groups_and_aggregate_blocks_do_not(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $assistant = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->groups['A']->id, 'userid' => $assistant->id]);
        assign_capability('local/assistente:view', CAP_ALLOW, $DB->get_field('role', 'id', ['shortname' => 'teacher']),
            \context_course::instance($this->course->id)->id, true);
        $synthesis = $generator->get_plugin_generator('local_assistente')->create_pedagogical_synthesis(['course_id' => $this->course->id]);
        $forum = $this->analysis('forum_analysis');
        $this->version([
            'grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 10), $this->grade('Bruno', 20), $this->grade('Carla', 30)]],
            'frequency' => ['state' => 'valid', 'analysis_ids' => [$this->frequency(['Ana', 'Bruno', 'Carla'])]],
            'below_criteria' => ['state' => 'valid', 'analysis_ids' => [$this->criteria(['Ana', 'Bruno', 'Carla'])]],
            'forums' => ['state' => 'valid', 'analysis_ids' => [$forum]],
            'section_synthesis' => ['state' => 'valid', 'synthesis_versions' => [(int) $synthesis->section_id => (int) $synthesis->version]],
        ]);
        $course = (int) $this->course->id;
        $individual = ['grades', 'frequency', 'below_criteria'];

        $all = $this->view->current($course, (int) $this->teacher->id);
        $restricted = $this->view->current($course, (int) $assistant->id);
        $group_b = $this->view->current($course, (int) $this->teacher->id, (int) $this->groups['B']->id);
        $hidden = $this->view->current($course, (int) $assistant->id, (int) $this->groups['B']->id);

        foreach ($individual as $block) {
            $this->assertSame(['Ana Teste', 'Bruno Teste', 'Carla Teste'], $this->names($all, $block), $block);
            $this->assertSame(['Ana Teste', 'Carla Teste'], $this->names($restricted, $block), "$block restricted");
            $this->assertSame(['Bruno Teste', 'Carla Teste'], $this->names($group_b, $block), "$block filter");
            $this->assertSame([], $this->names($hidden, $block), "$block group not visible");
        }
        foreach ([$all, $restricted, $hidden] as $result) {
            $blocks = $result['rows'][0]['blocks'];
            $this->assertCount(1, $blocks['forums']['rows']);
            $this->assertCount(1, $blocks['section_synthesis']['rows']);
            $row = $blocks['section_synthesis']['rows'][0];
            $this->assertSame([(int) $synthesis->section_id, (int) $synthesis->version, $synthesis->content], [$row['section_id'], $row['version'], $row['content']]);
            $this->assertArrayHasKey('section', $row);
        }
    }

    /**
     * Q12: a student who was deleted does not appear, even though the manifest still references their analyses.
     */
    public function test_a_deleted_student_does_not_appear(): void {
        $this->version([
            'grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 10), $this->grade('Bruno', 20)]],
            'frequency' => ['state' => 'valid', 'analysis_ids' => [$this->frequency(['Ana', 'Bruno'])]],
            'below_criteria' => ['state' => 'valid', 'analysis_ids' => [$this->criteria(['Ana', 'Bruno'])]],
        ]);
        delete_user($this->students['Bruno']);

        $result = $this->view->current((int) $this->course->id, (int) $this->teacher->id);

        foreach (['grades', 'frequency', 'below_criteria'] as $block) {
            $this->assertSame(['Ana Teste'], $this->names($result, $block), $block);
        }
        $this->assertStringNotContainsString('Bruno', json_encode($result));
    }

    /**
     * An analysis of another course that a reference points to is ignored.
     */
    public function test_an_analysis_of_another_course_is_ignored(): void {
        $other = $this->getDataGenerator()->create_course();
        $foreign = $this->grade('Ana', 99, (int) $other->id);
        $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$foreign, $this->grade('Carla', 70)]]]);

        $result = $this->view->current((int) $this->course->id, (int) $this->teacher->id);

        $this->assertSame(['Carla Teste'], $this->names($result, 'grades'));
    }

    /**
     * Reading writes nothing.
     */
    public function test_reading_writes_nothing(): void {
        global $DB;
        $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 10)]]]);
        $tables = ['local_assistente_report', 'local_assistente_report_version', 'local_assistente_analysis', 'local_assistente_event'];
        $count = fn() => array_map(fn($t) => $DB->count_records($t), $tables);
        $before = $count();

        $this->view->current((int) $this->course->id, (int) $this->teacher->id);
        $this->view->history((int) $this->course->id, (int) $this->teacher->id);

        $this->assertSame($before, $count());
    }
    /**
     * Store a version that was generated at a given instant (the execution date the filter reads).
     *
     * @param int $instant
     * @param array $blocks
     * @return \stdClass
     */
    private function version_at(int $instant, array $blocks = []): \stdClass {
        $this->tick = $instant - 1;
        return $this->version($blocks);
    }

    /**
     * An instant from a local date and time of the site time zone used by the filters of these tests.
     *
     * @param string $local
     * @return int
     */
    private static function at(string $local): int {
        return (new \DateTimeImmutable($local, new \DateTimeZone('America/Sao_Paulo')))->getTimestamp();
    }

    /**
     * A filter of the tests: number and first and last day, in the time zone of the site.
     *
     * @param int|null $number
     * @param string|null $first local date, such as "2026-11-10"
     * @param string|null $last
     * @return version_filter
     */
    private static function filter(?int $number, ?string $first = null, ?string $last = null): version_filter {
        return version_filter::of(
            $number,
            $first === null ? null : self::at("$first 12:00:00"),
            $last === null ? null : self::at("$last 12:00:00"),
            'America/Sao_Paulo'
        );
    }

    /**
     * The version number of the first rendered version, or null when none is rendered.
     *
     * @param array $result
     * @return int|null
     */
    private function shown(array $result): ?int {
        return $result['rows'][0]['version'] ?? null;
    }

    /**
     * Three versions with different grades of Ana.
     *
     * @return \stdClass[] the versions, in order
     */
    private function three_versions(): array {
        $versions = [];
        foreach ([50, 70, 90] as $grade) {
            $versions[] = $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', $grade)]]]);
        }
        return $versions;
    }

    /**
     * Q-020-02, item 1: without a filter, and with the filter that selects nothing, the current version is shown.
     */
    public function test_without_a_filter_the_current_version_is_shown(): void {
        $course = (int) $this->course->id;
        $teacher = (int) $this->teacher->id;
        [, , $third] = $this->three_versions();

        $plain = $this->view->current($course, $teacher);
        $this->assertSame((int) $third->version, $this->shown($plain));
        $this->assertSame($plain, $this->view->current($course, $teacher, null, null));
        $this->assertSame($plain, $this->view->current($course, $teacher, null, version_filter::none()));
    }

    /**
     * Item 2: a version, the current or an old one, is selected by its number and shows the values of its own references.
     */
    public function test_a_version_is_selected_by_its_number(): void {
        $course = (int) $this->course->id;
        $teacher = (int) $this->teacher->id;
        [$first, $second] = $this->three_versions();
        $text = fn(array $result): string => $result['rows'][0]['blocks']['grades']['rows'][0]['activities'][0]['grade']['text'];

        $one = $this->view->current($course, $teacher, null, self::filter((int) $first->version));
        $two = $this->view->current($course, $teacher, null, self::filter((int) $second->version));

        $this->assertSame([(int) $first->version, '50,00%'], [$this->shown($one), $text($one)]);
        $this->assertSame([(int) $second->version, '70,00%'], [$this->shown($two), $text($two)]);
        $this->assertSame([], $this->view->current($course, $teacher, null, self::filter(99))['rows'], 'A number that does not exist');
    }

    /**
     * Items 3, 4 and 5: the execution date is an interval of days, inclusive on both sides, and combines with the number.
     */
    public function test_the_execution_date_interval_is_inclusive_and_combines_with_the_number(): void {
        $course = (int) $this->course->id;
        $teacher = (int) $this->teacher->id;
        $before = $this->version_at(self::at('2026-11-09 23:59:59'));
        $start = $this->version_at(self::at('2026-11-10 00:00:00'));
        $end = $this->version_at(self::at('2026-11-12 23:59:59'));
        $after = $this->version_at(self::at('2026-11-13 00:00:00'));
        $number = fn(\stdClass $version): int => (int) $version->version;

        $this->assertSame($number($end), $this->shown($this->view->current($course, $teacher, null, self::filter(null, '2026-11-10', '2026-11-12'))));
        $this->assertSame($number($start), $this->shown($this->view->current($course, $teacher, null, self::filter(null, '2026-11-10', '2026-11-10'))), 'First day, first second');
        $this->assertSame($number($end), $this->shown($this->view->current($course, $teacher, null, self::filter(null, '2026-11-12', '2026-11-12'))), 'Last day, last second');
        $this->assertSame($number($before), $this->shown($this->view->current($course, $teacher, null, self::filter(null, '2026-11-09', '2026-11-09'))));
        $this->assertSame($number($after), $this->shown($this->view->current($course, $teacher, null, self::filter(null, '2026-11-13', '2026-11-13'))));
        $this->assertSame([], $this->view->current($course, $teacher, null, self::filter(null, '2026-11-11', '2026-11-11'))['rows'], 'A day with no execution');
        $this->assertSame($number($after), $this->shown($this->view->current($course, $teacher, null, self::filter(null, '2026-11-11'))), 'Only the first day');
        $this->assertSame($number($end), $this->shown($this->view->current($course, $teacher, null, self::filter(null, null, '2026-11-12'))), 'Only the last day');

        $this->assertSame($number($start), $this->shown($this->view->current($course, $teacher, null, self::filter($number($start), '2026-11-10', '2026-11-12'))));
        $this->assertSame([], $this->view->current($course, $teacher, null, self::filter($number($before), '2026-11-10', '2026-11-12'))['rows'], 'Right number, date outside');
        $this->assertSame([], $this->view->current($course, $teacher, null, self::filter($number($start), '2026-11-11', '2026-11-12'))['rows'], 'Right date, other number');
    }

    /**
     * Item 12: the filter of the group narrows the individual blocks of the version chosen and leaves the aggregate blocks as they are.
     * Item 13: a user who cannot see the group gets no student, with or without the filter of the version.
     */
    public function test_group_and_version_filters_together_follow_the_permissions(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $assistant = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->groups['A']->id, 'userid' => $assistant->id]);
        assign_capability('local/assistente:view', CAP_ALLOW, $DB->get_field('role', 'id', ['shortname' => 'teacher']),
            \context_course::instance($this->course->id)->id, true);
        $synthesis = $generator->get_plugin_generator('local_assistente')->create_pedagogical_synthesis(['course_id' => $this->course->id]);
        $first = $this->version([
            'grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 10), $this->grade('Bruno', 20), $this->grade('Carla', 30)]],
            'forums' => ['state' => 'valid', 'analysis_ids' => [$this->analysis('forum_analysis')]],
            'section_synthesis' => ['state' => 'valid', 'synthesis_versions' => [(int) $synthesis->section_id => (int) $synthesis->version]],
        ]);
        $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 99)]]]);
        $course = (int) $this->course->id;
        $chosen = self::filter((int) $first->version);
        $group_b = (int) $this->groups['B']->id;

        $all = $this->view->current($course, (int) $this->teacher->id, null, $chosen);
        $only_b = $this->view->current($course, (int) $this->teacher->id, $group_b, $chosen);
        $hidden = $this->view->current($course, (int) $assistant->id, $group_b, $chosen);
        $restricted = $this->view->current($course, (int) $assistant->id, null, $chosen);

        $this->assertSame(['Ana Teste', 'Bruno Teste', 'Carla Teste'], $this->names($all, 'grades'));
        $this->assertSame(['Bruno Teste', 'Carla Teste'], $this->names($only_b, 'grades'));
        $this->assertSame(['Ana Teste', 'Carla Teste'], $this->names($restricted, 'grades'), 'The groups of the user, as without the filter');
        $this->assertSame([], $this->names($hidden, 'grades'), 'A group the user cannot see gives no student');
        foreach ([$all, $only_b, $hidden, $restricted] as $result) {
            $this->assertSame((int) $first->version, $this->shown($result));
            $this->assertCount(1, $result['rows'][0]['blocks']['forums']['rows'], 'Aggregate block, never filtered by group');
            $this->assertCount(1, $result['rows'][0]['blocks']['section_synthesis']['rows'], 'Aggregate block, never filtered by group');
        }
    }

    /**
     * Item 13: a student and a user with no role are refused with the filters, and so is the list of versions.
     */
    public function test_the_filters_do_not_open_the_report_to_who_has_no_access(): void {
        $this->three_versions();
        $outsider = (int) $this->getDataGenerator()->create_user()->id;
        $student = (int) $this->students['Ana']->id;
        $course = (int) $this->course->id;
        foreach ([$outsider, $student] as $user) {
            foreach ([
                fn() => $this->view->current($course, $user, null, self::filter(1)),
                fn() => $this->view->current($course, $user, (int) $this->groups['A']->id, self::filter(null, '2020-01-01', '2035-01-01')),
                fn() => $this->view->versions($course, $user),
            ] as $call) {
                try {
                    $call();
                    $this->fail('Access must be denied');
                } catch (required_capability_exception) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    /**
     * Item 15: reading an old version through the filters changes nothing: not the manifest, not the pointer to the current version, not
     * the analyses it references; and it shows the values it referenced even though newer analyses of the same student exist.
     */
    public function test_reading_an_old_version_through_the_filters_changes_nothing(): void {
        global $DB;
        $course = (int) $this->course->id;
        $teacher = (int) $this->teacher->id;
        $first = $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 50)]]]);
        $this->version(['grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 90)]]]);
        $tables = ['local_assistente_report', 'local_assistente_report_version', 'local_assistente_analysis', 'local_assistente_performance',
            'local_assistente_event', 'local_assistente_processing'];
        $fingerprint = fn(): array => array_map(
            fn(string $table): string => md5(json_encode($DB->get_records($table, null, 'id'))),
            $tables
        );
        $before = $fingerprint();
        $manifest = $DB->get_field('local_assistente_report_version', 'snapshot_json', ['id' => $first->id]);

        $result = $this->view->current($course, $teacher, null, self::filter((int) $first->version));

        $this->assertSame('50,00%', $result['rows'][0]['blocks']['grades']['rows'][0]['activities'][0]['grade']['text']);
        $this->assertSame($manifest, $DB->get_field('local_assistente_report_version', 'snapshot_json', ['id' => $first->id]));
        $this->assertSame($before, $fingerprint(), 'Nothing is written or recalculated');
        $this->assertSame(2, $this->shown($this->view->current($course, $teacher)), 'The current version is still the second');
    }

    /**
     * The numbers the page offers: newest first, with their execution date; nothing when the assistant is not enabled.
     */
    public function test_the_versions_offered_to_the_filter(): void {
        $course = (int) $this->course->id;
        $teacher = (int) $this->teacher->id;
        $this->assertSame([], $this->view->versions($course, $teacher), 'No version yet');
        $first = $this->version_at(self::at('2026-11-10 08:00:00'));
        $second = $this->version_at(self::at('2026-11-11 08:00:00'));

        $this->assertSame(
            [(int) $second->version => self::at('2026-11-11 08:00:00'), (int) $first->version => self::at('2026-11-10 08:00:00')],
            $this->view->versions($course, $teacher)
        );
        $config = new config_repository();
        $config->update((object) ['id' => $config->find_by(['course_id' => $course])->id, 'enabled' => 0]);
        $this->assertSame([], $this->view->versions($course, $teacher));
    }
    /**
     * Item 12 (Q-020-02), the blocks the other tests do not look at: the questionnaires and the material summaries are aggregate blocks, so
     * the group of the filter and the groups of the user do not change them, while the individual block of the same version does change.
     */
    public function test_questionnaire_and_material_blocks_are_never_filtered_by_group(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $assistant = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->groups['A']->id, 'userid' => $assistant->id]);
        assign_capability('local/assistente:view', CAP_ALLOW, $DB->get_field('role', 'id', ['shortname' => 'teacher']),
            \context_course::instance($this->course->id)->id, true);

        $results = $this->analysis('quiz_results');
        $this->row('local_assistente_questionnaire_metric', [
            'analysis_id' => $results, 'mean' => 70, 'population_standard_deviation' => 5, 'minimum' => 50, 'maximum' => 90,
        ]);
        $material = $this->row('local_assistente_material', [
            'course_id' => $this->course->id, 'cmid' => $this->page->cmid, 'name' => 'Apostila', 'material_type' => 'text',
        ]);
        $materialversion = $this->row('local_assistente_material_version', [
            'material_id' => $material, 'version' => 1, 'summary' => 'Resumo da apostila', 'summary_status' => 'completed',
            'availability' => 'available',
        ]);
        $first = $this->version([
            'grades' => ['state' => 'valid', 'analysis_ids' => [$this->grade('Ana', 10), $this->grade('Bruno', 20), $this->grade('Carla', 30)]],
            'quiz_metrics' => ['state' => 'valid', 'analysis_ids' => [$results]],
            'material_summaries' => ['state' => 'valid', 'material_versions' => [$materialversion]],
        ]);
        $course = (int) $this->course->id;
        $chosen = self::filter((int) $first->version);
        $group_b = (int) $this->groups['B']->id;

        $all = $this->view->current($course, (int) $this->teacher->id, null, $chosen);
        $others = [
            'filter by group B' => $this->view->current($course, (int) $this->teacher->id, $group_b, $chosen),
            'user with only group A' => $this->view->current($course, (int) $assistant->id, null, $chosen),
            'user with only group A, group B asked' => $this->view->current($course, (int) $assistant->id, $group_b, $chosen),
        ];

        $blocks = $all['rows'][0]['blocks'];
        $this->assertCount(1, $blocks['quiz_metrics']['rows']);
        $this->assertSame('70,00%', $blocks['quiz_metrics']['rows'][0]['metrics']['mean']['text']);
        $this->assertSame(
            [['cmid' => (int) $this->page->cmid, 'material' => 'Apostila', 'version' => 1, 'summary' => 'Resumo da apostila']],
            $blocks['material_summaries']['rows']
        );
        foreach ($others as $label => $result) {
            $this->assertSame($blocks['quiz_metrics'], $result['rows'][0]['blocks']['quiz_metrics'], "quiz_metrics, $label");
            $this->assertSame($blocks['material_summaries'], $result['rows'][0]['blocks']['material_summaries'], "material_summaries, $label");
        }
        $this->assertSame(['Ana Teste', 'Bruno Teste', 'Carla Teste'], $this->names($all, 'grades'));
        $this->assertSame(['Bruno Teste', 'Carla Teste'], $this->names($others['filter by group B'], 'grades'), 'The individual block does change');
        $this->assertSame([], $this->names($others['user with only group A, group B asked'], 'grades'));
    }
}
