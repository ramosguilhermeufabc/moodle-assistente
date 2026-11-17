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

use local_assistente\application\material\material_event_handler;
use local_assistente\application\material\material_event_service;
use local_assistente\application\material\material_service;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\material_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the reaction to material events (T065; quickstart 008 Q8, Q9, Q10; DEC-33, DEC-51), through the real events of Moodle,
 * the real queue and the real handler of `material_changed`. The LLM is the simulated adapter (DEC-48).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_module_observer::class)]
#[CoversClass(material_event_service::class)]
final class material_observer_test extends \advanced_testcase {
    /** @var material_repository */
    private material_repository $repository;

    /** @var fake_adapter */
    private fake_adapter $llm;

    /** @var material_event_handler */
    private material_event_handler $handler;

    /**
     * Real transactions and locks are used by the queue; the observer runs through real events.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/course/lib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->repository = new material_repository();
        $this->llm = new fake_adapter();
        $this->handler = new material_event_handler(new material_service($this->repository, null, $this->llm));
    }

    /**
     * A course with the assistant enabled.
     *
     * @return \stdClass
     */
    private function enabled_course(): \stdClass {
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        (new config_repository())->create($course->id, true);
        return $course;
    }

    /**
     * Pending `material_changed` events of a course, oldest first.
     *
     * @param int $courseid
     * @return \stdClass[]
     */
    private function events(int $courseid): array {
        global $DB;
        return array_values($DB->get_records(
            'local_assistente_event',
            ['course_id' => $courseid, 'event_type' => 'material_changed', 'status' => 'pending'],
            'id ASC'
        ));
    }

    /**
     * Run the handler for the pending events and mark them done, as the queue would.
     *
     * @param int $courseid
     * @return int number of events handled
     */
    private function drain(int $courseid): int {
        global $DB;
        $events = $this->events($courseid);
        foreach ($events as $event) {
            $this->assertTrue($this->handler->handle($event, new \stdClass())->success);
            $DB->set_field('local_assistente_event', 'status', 'processed', ['id' => $event->id]);
        }
        return count($events);
    }

    /**
     * Versions of the material of a module.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass[]
     */
    private function versions(int $courseid, int $cmid): array {
        $material = $this->repository->find_by_cmid($courseid, $cmid);
        return $material === null ? [] : array_values($this->repository->find_versions((int) $material->id));
    }

    /**
     * Observers are registered after the commit and the material service is wired.
     */
    public function test_creating_a_page_enqueues_a_material_event_and_creates_version_one(): void {
        $course = $this->enabled_course();

        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => '<p>Texto</p>']);

        $events = $this->events((int) $course->id);
        $this->assertCount(1, $events);
        $payload = json_decode($events[0]->payload, true);
        $this->assertSame((int) $page->cmid, $payload['cmid']);
        $this->assertSame('\core\event\course_module_created', $payload['moodle_event']);
        $this->drain((int) $course->id);
        $versions = $this->versions((int) $course->id, (int) $page->cmid);
        $this->assertCount(1, $versions);
        $this->assertSame('pending', $this->repository->find_by_cmid((int) $course->id, (int) $page->cmid)->availability);
    }

    /**
     * Q10: a change in the processable content creates a new version; the old one is preserved.
     */
    public function test_content_change_creates_a_new_version(): void {
        global $DB;
        $course = $this->enabled_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => '<p>v1</p>']);
        $this->drain((int) $course->id);
        $before = $this->versions((int) $course->id, (int) $page->cmid);

        $DB->set_field('page', 'content', '<p>v2 diferente</p>', ['id' => $page->id]);
        \core\event\course_module_updated::create_from_cm(get_coursemodule_from_id('page', $page->cmid, 0, false, MUST_EXIST))
            ->trigger();
        $this->assertCount(1, $this->events((int) $course->id));
        $this->drain((int) $course->id);

        $after = $this->versions((int) $course->id, (int) $page->cmid);
        $this->assertCount(2, $after);
        $this->assertEquals($before[0], $after[0], 'The first version is preserved');
        $material = $this->repository->find_by_cmid((int) $course->id, (int) $page->cmid);
        $this->assertSame((int) $after[1]->id, (int) $material->current_version);
    }

    /**
     * Q8: renaming, hiding and changing the access restrictions create no version.
     */
    public function test_rename_visibility_and_restrictions_create_no_version(): void {
        global $DB;
        $course = $this->enabled_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => '<p>v1</p>']);
        $this->drain((int) $course->id);

        set_coursemodule_name($page->cmid, 'Novo nome');
        $this->assertSame(1, $this->drain((int) $course->id), 'A rename emits course_module_updated');
        set_coursemodule_visible($page->cmid, 0);
        $DB->set_field('course_modules', 'availability', json_encode(['op' => '&', 'c' => [], 'showc' => []]), ['id' => $page->cmid]);
        \core\event\course_module_updated::create_from_cm(get_coursemodule_from_id('page', $page->cmid, 0, false, MUST_EXIST))
            ->trigger();
        $this->drain((int) $course->id);

        $this->assertCount(1, $this->versions((int) $course->id, (int) $page->cmid));
        $this->assertSame('Novo nome', $this->repository->find_by_cmid((int) $course->id, (int) $page->cmid)->name);
        $this->assertSame(1, $this->llm->calls(), 'The LLM was called once, for version 1');
    }

    /**
     * Q9, the real behaviour of Moodle 5.2: moving a module to another section emits NO course_module_updated, so there is
     * nothing for the observer to react to. When the material is processed later, the section is updated and no version is created.
     */
    public function test_moving_a_module_emits_no_event_and_the_section_follows_the_next_processing(): void {
        $course = $this->enabled_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => '<p>v1</p>']);
        $this->drain((int) $course->id);
        $section = $this->getDataGenerator()->create_course_section(['course' => $course->id, 'section' => 2]);

        $sink = $this->redirectEvents();
        (new \core_courseformat\local\cmactions(get_course((int) $course->id)))->move_end_section($page->cmid, (int) $section->id);
        $updated = array_filter($sink->get_events(), static fn($e) => $e instanceof \core\event\course_module_updated);
        $sink->close();
        $this->assertSame([], $updated, 'Evidence for Q9: the move emits no course_module_updated');

        $this->assertSame(0, count($this->events((int) $course->id)), 'So nothing reached the queue');
        $stale = $this->repository->find_by_cmid((int) $course->id, (int) $page->cmid);
        $this->assertNotSame((int) $section->id, (int) $stale->section_id, 'The stored section is stale until the next processing');

        $this->assertTrue($this->handler->handle(
            (object) ['course_id' => $course->id, 'payload' => json_encode(['cmid' => (int) $page->cmid])],
            new \stdClass()
        )->success);
        $material = $this->repository->find_by_cmid((int) $course->id, (int) $page->cmid);
        $this->assertSame((int) $section->id, (int) $material->section_id);
        $this->assertCount(1, $this->versions((int) $course->id, (int) $page->cmid));
    }

    /**
     * Modules that cannot be materials, and courses without the assistant, produce no material event.
     */
    public function test_other_modules_and_not_enabled_courses_produce_no_material_event(): void {
        global $DB;
        $enabled = $this->enabled_course();
        $disabled = $this->getDataGenerator()->create_course();

        $this->getDataGenerator()->create_module('forum', ['course' => $enabled->id]);
        $this->getDataGenerator()->create_module('label', ['course' => $enabled->id]);
        $this->getDataGenerator()->create_module('page', ['course' => $disabled->id]);

        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => 'material_changed']));
    }

    /**
     * A module that is a candidate type but not a functional material (a URL that is not YouTube) is queued and then ignored
     * by the handler, without a material or a version.
     */
    public function test_candidate_that_is_not_a_material_creates_nothing(): void {
        global $DB;
        $course = $this->enabled_course();
        $url = $this->getDataGenerator()->create_module('url', ['course' => $course->id, 'externalurl' => 'https://example.org/x']);

        $this->assertCount(1, $this->events((int) $course->id));
        $this->drain((int) $course->id);

        $this->assertNull($this->repository->find_by_cmid((int) $course->id, (int) $url->cmid));
        $this->assertSame(0, $DB->count_records('local_assistente_material_version'));
    }

    /**
     * The questionnaire flow of T046 is not affected, and a questionnaire is never a material event.
     */
    public function test_quiz_flow_is_preserved(): void {
        global $DB;
        $course = $this->enabled_course();

        $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $this->assertSame(1, $DB->count_records('local_assistente_event', ['event_type' => 'quiz_published']));
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => 'material_changed']));
    }

    /**
     * A failure of the queue is logged and does not break the teacher's action.
     */
    public function test_a_failure_is_contained_and_logged(): void {
        $service = new class extends material_event_service {
            /**
             * Constructor, without the real collaborators.
             */
            public function __construct() {
            }

            #[\Override]
            public function handle_module_event(
                int $courseid,
                int $cmid,
                string $modulename,
                string $moodleevent,
                int $occurredat
            ): ?\local_assistente\application\enqueue_result {
                throw new \RuntimeException('queue unavailable');
            }
        };
        course_module_observer::use_material_service($service);
        $course = $this->enabled_course();

        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->assertNotEmpty($page->cmid, 'The module was created');
        $this->assertDebuggingCalled();
        course_module_observer::use_material_service(null);
    }
}
