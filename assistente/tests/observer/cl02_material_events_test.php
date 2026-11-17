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

namespace local_assistente\observer;

use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Technical validation CL-02 (T066), materials part: which Moodle events each teacher action emits in Moodle 5.2.4, and what
 * reaches the queue of the plugin. It records observed behaviour; it decides nothing.
 *
 * Observed here, by running the actions against the real Moodle (and not by reading code): creation, edition (form), rename,
 * eye icon, move, file replacement, restriction edit and deletion. The matching reading of the sources is in the comments.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class cl02_material_events_test extends \advanced_testcase {
    /** @var string[] short names of the course module events recorded by the current action */
    private static array $recorded = [];

    /**
     * Real transactions and locks are used by the queue.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        foreach ($observers as $i => $observer) {
            $observers[$i] += ['includefile' => null, 'priority' => 0];
        }
        $observers[] = [
            'eventname' => '*',
            'callback' => 'local_assistente\observer\cl02_material_events_test::record',
            'includefile' => null,
            'priority' => 0,
            'internal' => false,
        ];
        \core\event\manager::phpunit_replace_observers($observers);
    }

    /**
     * Classes of the course module events emitted by an action, in order.
     *
     * @param callable $action
     * @return string[] short class names (without the namespace)
     */
    private function emitted(callable $action): array {
        self::$recorded = [];
        $action();
        return self::$recorded;
    }

    /**
     * Observer that records the course module events next to the observers of the plugin (a sink would hide them from the plugin).
     *
     * @param \core\event\base $event
     */
    public static function record(\core\event\base $event): void {
        if (str_starts_with($event::class, 'core\\event\\course_module')) {
            self::$recorded[] = (new \ReflectionClass($event))->getShortName();
        }
    }

    /**
     * Number of `material_changed` events of the queue of a course.
     *
     * @param int $courseid
     * @return int
     */
    private function queued(int $courseid): int {
        global $DB;
        return $DB->count_records('local_assistente_event', ['course_id' => $courseid, 'event_type' => 'material_changed']);
    }

    /**
     * A course with the assistant enabled and a page, a file resource and a URL.
     *
     * @return \stdClass
     */
    private function setup_course(): \stdClass {
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        (new config_repository())->create($course->id, true);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => '<p>a</p>']);
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $url = $this->getDataGenerator()->create_module(
            'url',
            ['course' => $course->id, 'externalurl' => 'https://www.youtube.com/watch?v=abc123']
        );
        return (object) ['course' => $course, 'page' => $page, 'resource' => $resource, 'url' => $url];
    }

    /**
     * Creation emits course_module_created for each of the three types and the queue receives one material event each.
     */
    public function test_creation_emits_course_module_created_and_is_queued(): void {
        $course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($course->id, true);

        foreach (['page', 'resource', 'url'] as $modname) {
            $names = $this->emitted(fn() => $this->getDataGenerator()->create_module($modname, ['course' => $course->id]));
            $this->assertContains('course_module_created', $names, $modname);
        }
        $this->assertSame(3, $this->queued((int) $course->id));
    }

    /**
     * Edition through the form (update_moduleinfo) emits course_module_updated; the content change of a page and the address
     * change of a URL reach the queue.
     */
    public function test_form_edition_emits_course_module_updated(): void {
        $c = $this->setup_course();
        $before = $this->queued((int) $c->course->id);

        foreach (['page' => $c->page, 'url' => $c->url] as $modname => $module) {
            $names = $this->emitted(function () use ($modname, $module, $c) {
                $cm = get_coursemodule_from_id($modname, $module->cmid, 0, false, MUST_EXIST);
                [$cm, , , $data] = get_moduleinfo_data($cm, $c->course);
                $data->name = 'Editado';
                if ($modname === 'page') {
                    $data->page = ['text' => '<p>b</p>', 'format' => FORMAT_HTML, 'itemid' => 0]; // Field of the page form.
                    $data->printintro = 0;
                    $data->printlastmodified = 0;
                }
                update_moduleinfo($cm, $data, $c->course);
            });
            $this->assertContains('course_module_updated', $names, $modname);
        }
        $this->assertSame($before + 2, $this->queued((int) $c->course->id));
    }

    /**
     * Rename emits course_module_updated (cmactions::rename, read in the sources and observed here).
     */
    public function test_rename_emits_course_module_updated(): void {
        $c = $this->setup_course();

        $names = $this->emitted(fn() => set_coursemodule_name($c->page->cmid, 'Outro nome'));

        $this->assertSame(['course_module_updated'], $names);
    }

    /**
     * FINDING: the eye icon (cmactions::set_visibility) emits no course module event, so nothing reaches the queue and an
     * assistant event that depends on "made visible later" is not produced by it.
     */
    public function test_eye_icon_emits_no_course_module_event(): void {
        $c = $this->setup_course();
        $before = $this->queued((int) $c->course->id);

        $hide = $this->emitted(fn() => \core_courseformat\formatactions::cm((int) $c->course->id)->set_visibility($c->page->cmid, 0));
        $show = $this->emitted(fn() => \core_courseformat\formatactions::cm((int) $c->course->id)->set_visibility($c->page->cmid, 1));

        $this->assertSame([], $hide);
        $this->assertSame([], $show);
        $this->assertSame($before, $this->queued((int) $c->course->id));
    }

    /**
     * FINDING: moving a module between sections (cmactions::move_end_section) and before another module (move_before) emits no
     * course module event.
     */
    public function test_move_emits_no_course_module_event(): void {
        $c = $this->setup_course();
        $section = $this->getDataGenerator()->create_course_section(['course' => $c->course->id, 'section' => 2]);
        $actions = new \core_courseformat\local\cmactions(get_course((int) $c->course->id));
        $before = $this->queued((int) $c->course->id);

        $end = $this->emitted(fn() => $actions->move_end_section($c->page->cmid, (int) $section->id));
        $beforecm = $this->emitted(fn() => $actions->move_before($c->page->cmid, $c->resource->cmid));

        $this->assertSame([], $end);
        $this->assertSame([], $beforecm);
        $this->assertSame($before, $this->queued((int) $c->course->id));
    }

    /**
     * FINDING: replacing the file of a resource directly in the file storage emits no course module event. The change reaches the
     * plugin only when the resource is saved through the form (course_module_updated), which this test also observes.
     */
    public function test_file_replacement_emits_event_only_through_the_form(): void {
        $c = $this->setup_course();
        $context = \context_module::instance($c->resource->cmid);
        $fs = get_file_storage();
        $before = $this->queued((int) $c->course->id);

        $direct = $this->emitted(function () use ($fs, $context) {
            $fs->delete_area_files($context->id, 'mod_resource', 'content');
            $fs->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_resource', 'filearea' => 'content',
                'itemid' => 0, 'filepath' => '/', 'filename' => 'novo.pdf',
            ], 'pdf novo');
        });
        $this->assertSame([], $direct);
        $this->assertSame($before, $this->queued((int) $c->course->id));

        $form = $this->emitted(function () use ($c, $context) {
            $cm = get_coursemodule_from_id('resource', $c->resource->cmid, 0, false, MUST_EXIST);
            [$cm, , , $data] = get_moduleinfo_data($cm, $c->course);
            $draftid = 0;
            file_prepare_draft_area($draftid, $context->id, 'mod_resource', 'content', 0);
            $data->files = $draftid;
            update_moduleinfo($cm, $data, $c->course);
        });
        $this->assertContains('course_module_updated', $form);
    }

    /**
     * Editing the access restrictions through the form emits course_module_updated, and the plugin queues it; the handler of
     * `material_changed` then creates no version (material_observer_test, Q8).
     */
    public function test_restriction_edition_through_the_form_emits_course_module_updated(): void {
        $c = $this->setup_course();

        $names = $this->emitted(function () use ($c) {
            $cm = get_coursemodule_from_id('page', $c->page->cmid, 0, false, MUST_EXIST);
            [$cm, , , $data] = get_moduleinfo_data($cm, $c->course);
            $data->page = ['text' => '<p>a</p>', 'format' => FORMAT_HTML, 'itemid' => 0];
            $data->printintro = 0;
            $data->printlastmodified = 0;
            $data->availabilityconditionsjson = json_encode(['op' => '&', 'c' => [], 'showc' => []]);
            update_moduleinfo($cm, $data, $c->course);
        });

        $this->assertContains('course_module_updated', $names);
    }

    /**
     * Deletion emits course_module_deleted. The plugin does not observe it (no observer registered, not part of T046/T065).
     */
    public function test_deletion_emits_course_module_deleted_and_the_plugin_does_not_observe_it(): void {
        global $CFG;
        $c = $this->setup_course();
        $before = $this->queued((int) $c->course->id);

        $names = $this->emitted(
            fn() => (new \core_courseformat\local\cmactions(get_course((int) $c->course->id)))->delete($c->page->cmid)
        );

        $this->assertContains('course_module_deleted', $names);
        $this->assertSame($before, $this->queued((int) $c->course->id));
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        $this->assertNotContains('\core\event\course_module_deleted', array_column($observers, 'eventname'));
    }
}
