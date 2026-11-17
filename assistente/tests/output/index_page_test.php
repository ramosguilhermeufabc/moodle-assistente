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

namespace local_assistente\output;

use local_assistente\application\report\report_builder;
use local_assistente\domain\report\report_schedule;
use local_assistente\domain\report\version_filter;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\report_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of what the entry page shows (T124; quickstart 021 Q1, Q4, Q5, Q6, Q10; quickstart 005 Q1; DEC-45 D1 and D3; DEC-43).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(page_controller::class)]
final class index_page_test extends \advanced_testcase {
    /**
     * The service uses real transactions, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
    }

    /**
     * Q1: a user without the capability gets nothing: no tab and no renderable.
     */
    public function test_user_without_the_capability_is_denied(): void {
        $course = $this->getDataGenerator()->create_course();
        foreach (['student', 'teacher'] as $role) {
            $user = $this->getDataGenerator()->create_and_enrol($course, $role);
            try {
                (new page_controller())->tabs($course->id, $user->id);
                $this->fail("{$role} must be denied");
            } catch (\core\exception\required_capability_exception $e) {
                $this->assertNotNull($e);
            }
            try {
                (new page_controller())->renderable($course->id, 'grades', $user->id);
                $this->fail("{$role} must be denied");
            } catch (\core\exception\required_capability_exception $e) {
                $this->assertNotNull($e);
            }
        }
    }

    /**
     * Q5: with `view` only, the query and report tabs are there and "Configuração" is not; with `manage` it is the last tab.
     */
    public function test_configuration_tab_only_for_manage(): void {
        $course = $this->getDataGenerator()->create_course();
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_course::instance($course->id);
        assign_capability('local/assistente:view', CAP_ALLOW, $roleid, $context->id, true);
        $this->getDataGenerator()->role_assign($roleid, $viewer->id, $context->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $viewtabs = (new page_controller())->tabs($course->id, $viewer->id);
        $this->assertSame(
            ['grades', 'frequency', 'below_criteria', 'quiz_metrics', 'forums', 'materials', 'synthesis', 'report', 'history'],
            $viewtabs
        );
        $managetabs = (new page_controller())->tabs($course->id, $teacher->id);
        $this->assertSame([...$viewtabs, 'config'], $managetabs);
    }

    /**
     * Q6: a course that is not enabled answers "assistente não habilitado" in every tab.
     */
    public function test_every_tab_says_not_enabled_for_a_disabled_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $controller = new page_controller();
        $renderer = $this->getDataGenerator() ? $GLOBALS['PAGE']->get_renderer('local_assistente') : null;

        foreach (array_diff($controller->tabs($course->id, $teacher->id), [page_controller::TAB_CONFIG]) as $tab) {
            $html = $renderer->render($controller->renderable($course->id, $tab, $teacher->id));
            $this->assertStringContainsString(get_string('query:not_enabled', 'local_assistente'), $html, "tab {$tab}");
            $this->assertStringNotContainsString('<table', $html, "tab {$tab}");
        }
    }

    /**
     * An enabled course shows the tables; the configuration is not a query and is refused as a renderable.
     */
    public function test_enabled_course_renders_and_config_is_not_a_query(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_and_enrol($course, 'student');
        (new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
        $this->setUser($teacher);

        $page = (new page_controller())->renderable($course->id, 'grades', $teacher->id);
        $this->assertInstanceOf(query_page::class, $page);
        $this->assertInstanceOf(report_page::class, (new page_controller())->renderable($course->id, 'report', $teacher->id));
        $this->assertInstanceOf(history_page::class, (new page_controller())->renderable($course->id, 'history', $teacher->id));
        $this->expectException(\InvalidArgumentException::class);
        (new page_controller())->renderable($course->id, 'config', $teacher->id);
    }

    /**
     * Q10: in separate groups the group filter offers only the groups of the teacher, and only in the individual tabs.
     */
    public function test_group_filter_offers_only_the_visible_groups(): void {
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $editing = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $own = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Meu']);
        $other = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Outro']);
        $this->getDataGenerator()->create_group_member(['groupid' => $own->id, 'userid' => $teacher->id]);
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_course::instance($course->id);
        assign_capability('local/assistente:view', CAP_ALLOW, $roleid, $context->id, true);
        $this->getDataGenerator()->role_assign($roleid, $teacher->id, $context->id);
        $this->setUser($teacher);
        $this->assertTrue(has_capability('moodle/site:accessallgroups', $context, $editing), 'The editing teacher sees every group');

        $controller = new page_controller();
        $individual = $controller->filter_data($course->id, 'grades', $teacher->id);
        $this->assertTrue($individual['individual']);
        $this->assertArrayHasKey((int) $own->id, $individual['groups']);
        $this->assertArrayNotHasKey((int) $other->id, $individual['groups'], 'A group the teacher is not in is not offered');
        $this->assertSame([], $controller->filter_data($course->id, 'forums', $teacher->id)['groups'], 'No group filter in the aggregate tabs');
    }
    /**
     * An enabled course with an editing teacher and a report with versions executed at the given instants.
     *
     * @param int[] $instants execution instants, one version each
     * @return \stdClass[] course and teacher
     */
    private function course_with_report(array $instants): array {
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        (new config_repository())->create($course->id, true);
        $reports = new report_repository();
        $builder = new report_builder();
        $schedule = report_schedule::calculate([], null, 'UTC', null, 0);
        $reportid = (int) $reports->create((int) $course->id, $schedule)->id;
        $blocks = array_fill_keys(report_builder::BLOCKS, ['state' => 'no_data']);
        foreach ($instants as $instant) {
            $reports->add_version($reportid, $builder->build('periodic', $instant, $blocks), $schedule, $instant);
        }
        return [$course, $teacher];
    }

    /**
     * The context a renderable gives its template.
     *
     * @param \renderable $page
     * @return array
     */
    private function exported(\renderable $page): array {
        return $page->export_for_template($GLOBALS['PAGE']->get_renderer('local_assistente'));
    }

    /**
     * Q-020-02: the report offers the group (for its individual blocks), the version and the dates; the history offers the version and the
     * dates and no group; the individual queries offer the group only; the aggregate queries offer no version, no date and no group.
     */
    public function test_each_tab_offers_its_own_filters(): void {
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1, 'numsections' => 3]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        (new config_repository())->create($course->id, true);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'A']);
        $reports = new report_repository();
        $schedule = report_schedule::calculate([], null, 'UTC', null, 0);
        $reportid = (int) $reports->create((int) $course->id, $schedule)->id;
        $blocks = array_fill_keys(report_builder::BLOCKS, ['state' => 'no_data']);
        foreach ([1790000000, 1790100000] as $instant) {
            $reports->add_version($reportid, (new report_builder())->build('periodic', $instant, $blocks), $schedule, $instant);
        }
        $controller = new page_controller();
        $data = fn(string $tab): array => $controller->filter_data($course->id, $tab, $teacher->id);

        $report = $data('report');
        $this->assertSame([(int) $group->id => 'A'], $report['groups']);
        $this->assertTrue($report['individual'] && $report['dates']);
        $this->assertSame([2, 1], array_keys($report['versions']), 'Newest first');
        $this->assertStringStartsWith('2 (', $report['versions'][2]);
        $this->assertSame([], $report['sections'], 'The report is not data of one section');

        $history = $data('history');
        $this->assertSame([], $history['groups']);
        $this->assertFalse($history['individual']);
        $this->assertTrue($history['dates']);
        $this->assertSame([2, 1], array_keys($history['versions']));
        $this->assertSame([], $history['sections']);

        $grades = $data('grades');
        $this->assertSame([(int) $group->id => 'A'], $grades['groups']);
        $this->assertFalse($grades['dates']);
        $this->assertSame([], $grades['versions']);
        $this->assertSame([], $grades['sections'], 'The individual queries are not data of one section');

        $synthesis = $data('synthesis');
        $this->assertSame([], $synthesis['groups']);
        $this->assertFalse($synthesis['individual'] || $synthesis['dates']);
        $this->assertCount(3, $synthesis['sections'], 'The aggregate queries are per section');
    }

    /**
     * Items 1, 2, 7, 8, 11: the page gives the filter to the report and to the history and says so when nothing matches.
     */
    public function test_the_report_and_the_history_use_the_filter(): void {
        [$course, $teacher] = $this->course_with_report([1790000000, 1790100000, 1790200000]);
        $controller = new page_controller();
        $zone = 'UTC';

        $default = $this->exported($controller->renderable($course->id, 'report', $teacher->id));
        $this->assertStringContainsString('3', $default['versiontext']);

        $second = $this->exported($controller->renderable($course->id, 'report', $teacher->id, null, null, version_filter::of(2, null, null, $zone)));
        $this->assertStringContainsString('2', $second['versiontext']);
        $this->assertTrue($second['hasreport']);

        $none = $this->exported($controller->renderable($course->id, 'report', $teacher->id, null, null, version_filter::of(9, null, null, $zone)));
        $this->assertFalse($none['hasreport']);
        $this->assertTrue($none['noresults']);
        $this->assertFalse($none['noreport']);

        $listed = $this->exported($controller->renderable($course->id, 'history', $teacher->id, null, null, version_filter::of(2, null, null, $zone)));
        $this->assertCount(1, $listed['rows']);
        $all = $this->exported($controller->renderable($course->id, 'history', $teacher->id));
        $this->assertCount(3, $all['rows']);
        $this->assertFalse($all['noresults']);

        $nothing = $this->exported($controller->renderable($course->id, 'history', $teacher->id, null, null, version_filter::of(9, null, null, $zone)));
        $this->assertSame([], $nothing['rows']);
        $this->assertTrue($nothing['noresults']);
        $this->assertFalse($nothing['norows']);
    }

    /**
     * Item 14: the filter of the section keeps the rows of one section where the data is per section, and does nothing to the tabs that
     * are not; the filter of the version does nothing to the tabs that are not the report or its history.
     */
    public function test_the_section_filter_only_affects_data_of_a_section(): void {
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_and_enrol($course, 'student');
        (new config_repository())->create($course->id, true);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $sections = [];
        foreach ([1, 2] as $number) {
            $id = (int) $GLOBALS['DB']->get_field('course_sections', 'id', ['course' => $course->id, 'section' => $number]);
            $generator->create_pedagogical_synthesis(['course_id' => $course->id, 'section_id' => $id]);
            $sections[$number] = $id;
        }
        $controller = new page_controller();

        $all = $this->exported($controller->renderable($course->id, 'synthesis', $teacher->id));
        $one = $this->exported($controller->renderable($course->id, 'synthesis', $teacher->id, null, $sections[1]));
        $this->assertCount(3, $all['rows'], 'The query lists every section of the course: the 0 and the two synthesized');
        $this->assertCount(1, $one['rows'], 'The filter keeps the rows of the section chosen');

        foreach (['grades', 'frequency', 'report'] as $tab) {
            $plain = $this->exported($controller->renderable($course->id, $tab, $teacher->id));
            $with = $this->exported($controller->renderable($course->id, $tab, $teacher->id, null, $sections[1]));
            $this->assertEquals($plain, $with, "$tab is not data of one section");
        }
        $filtered = $this->exported($controller->renderable($course->id, 'grades', $teacher->id, null, null, version_filter::of(1, null, null, 'UTC')));
        $this->assertEquals($this->exported($controller->renderable($course->id, 'grades', $teacher->id)), $filtered, 'No version in the queries');
    }
}
