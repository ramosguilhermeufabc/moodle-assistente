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

use local_assistente\application\quiz\quiz_publication_service;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the course module observer, through the real Moodle events (quickstart 006 Q1, Q6, Q7; DEC-31).
 *
 * That Moodle emits these events with the data this code expects, and when, is validated by CL-02 (T047). These tests
 * assume the behaviour read in the MOODLE_502_STABLE sources: course_module_created and course_module_updated carry
 * `other['modulename']` and the cmid as `objectid`, and a visibility change triggers course_module_updated.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_module_observer::class)]
final class course_module_observer_test extends \advanced_testcase {
    /**
     * Real transactions and locks are used by the queue; the observer runs through real events.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Restore the default service.
     */
    protected function tearDown(): void {
        course_module_observer::use_service(null);
        parent::tearDown();
    }

    /**
     * A course with the assistant enabled.
     *
     * @return \stdClass
     */
    private function enabled_course(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($course->id, true);
        return $course;
    }

    /**
     * Number of publication events of a course.
     *
     * @param int $courseid
     * @return int
     */
    private function published_events(int $courseid): int {
        global $DB;
        return $DB->count_records('local_assistente_event', ['course_id' => $courseid, 'event_type' => 'quiz_published']);
    }

    /**
     * The course module events are registered with callbacks that exist (the quiz observers are tested in quiz_observer_test).
     */
    public function test_observers_are_registered_and_callable(): void {
        global $CFG;
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');

        $this->assertContains('\core\event\course_module_created', array_column($observers, 'eventname'));
        $this->assertContains('\core\event\course_module_updated', array_column($observers, 'eventname'));
        foreach ($observers as $observer) {
            $this->assertTrue(is_callable($observer['callback']), $observer['callback']);
            $this->assertFalse($observer['internal'], 'Runs after the commit: the queue takes locks, which must precede a transaction');
        }
    }

    /**
     * Quickstart 006 Q1, through the real event: creating a quiz already visible produces one pending event.
     */
    public function test_creating_a_visible_quiz_produces_one_event(): void {
        global $DB;
        $course = $this->enabled_course();

        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $this->assertSame(1, $this->published_events($course->id));
        $event = $DB->get_record('local_assistente_event', ['course_id' => $course->id], '*', MUST_EXIST);
        $this->assertSame('pending', $event->status);
        $this->assertSame((int) $quiz->cmid, json_decode($event->payload, true)['cmid']);
    }

    /**
     * Quickstart 006 Q6, through the real events: created hidden gives nothing; one event when it is made visible in the edit form.
     */
    public function test_hidden_then_visible_produces_the_event_when_it_becomes_visible(): void {
        $course = $this->enabled_course();

        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'visible' => 0]);
        $this->assertSame(0, $this->published_events($course->id), 'Created hidden: no event');

        // Finding (CL-02, T047): the eye icon of the course page (cmactions::set_visibility, 5.2) emits no course_module_updated.
        set_coursemodule_visible($quiz->cmid, 1);
        $this->assertSame(0, $this->published_events($course->id), 'The eye icon emits no course module event');
        set_coursemodule_visible($quiz->cmid, 0);

        // The edit form (update_moduleinfo) does emit it.
        $cm = get_coursemodule_from_id('quiz', $quiz->cmid, 0, false, MUST_EXIST);
        [$cm, , , $data] = get_moduleinfo_data($cm, $course);
        $data->visible = 1;
        $data->quizpassword = ''; // The quiz form field for the password.
        update_moduleinfo($cm, $data, $course);
        $this->assertSame(1, $this->published_events($course->id));
    }

    /**
     * Quickstart 006 Q7, through the real events: hide, show again and edit after the publication keep one event.
     */
    public function test_hide_show_and_edit_after_publication_keep_one_event(): void {
        $course = $this->enabled_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->assertSame(1, $this->published_events($course->id));

        set_coursemodule_visible($quiz->cmid, 0);
        set_coursemodule_visible($quiz->cmid, 1);
        $cm = get_coursemodule_from_id('quiz', $quiz->cmid, 0, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();

        $this->assertSame(1, $this->published_events($course->id));
    }

    /**
     * Other modules and courses without the assistant enabled produce nothing, and no error.
     */
    public function test_other_modules_and_not_enabled_courses_produce_nothing(): void {
        global $DB;
        $enabled = $this->enabled_course();
        $disabled = $this->getDataGenerator()->create_course();

        // A page is a possible material and queues material_changed (T065, material_observer_test); it is never a publication.
        $this->getDataGenerator()->create_module('page', ['course' => $enabled->id]);
        $this->getDataGenerator()->create_module('forum', ['course' => $enabled->id]);
        $this->getDataGenerator()->create_module('quiz', ['course' => $disabled->id]);

        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => 'quiz_published']));
        $this->assertSame(
            ['material_changed'],
            array_values($DB->get_fieldset_select('local_assistente_event', 'DISTINCT event_type', '1 = 1'))
        );
    }

    /**
     * A failure of the queue is logged and does not break the action that triggered the event.
     */
    public function test_a_failure_is_contained_and_logged(): void {
        $service = new class extends quiz_publication_service {
            /**
             * Constructor, without the real collaborators.
             */
            public function __construct() {
            }

            #[\Override]
            public function handle_module_event(int $courseid, int $cmid): ?\local_assistente\application\enqueue_result {
                throw new \RuntimeException('queue unavailable');
            }
        };
        course_module_observer::use_service($service);
        $course = $this->enabled_course();

        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $this->assertNotEmpty($quiz->cmid, 'The module was created');
        $this->assertDebuggingCalled();
    }
}
