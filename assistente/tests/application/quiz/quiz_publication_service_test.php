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

namespace local_assistente\application\quiz;

use local_assistente\application\enqueue_status;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the quiz publication rule (quickstart 006 Q1, Q2, Q6, Q7; DEC-31).
 *
 * The fixtures create the course with the assistant not enabled, so that the observer of the module events (T046) does not
 * act while they are built, and enable it before calling the service. Visibility is changed in the database without an
 * event, so the service is exercised alone; the path through the real Moodle events is tested in the observer test. What
 * Moodle actually emits is validated by CL-02 (T047).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(quiz_publication_service::class)]
final class quiz_publication_service_test extends \advanced_testcase {
    /**
     * Real transactions and locks are used by the queue.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Set the visibility of a module without triggering any event.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $visible
     */
    private function set_visibility_silently(int $courseid, int $cmid, int $visible): void {
        global $DB;
        $DB->set_field('course_modules', 'visible', $visible, ['id' => $cmid]);
        rebuild_course_cache($courseid, true);
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
     * A course whose assistant is enabled only after the fixtures were built.
     *
     * @param array $quizoptions options of the quiz module
     * @return array [course, quiz module, enable callable]
     */
    private function course_with_quiz(array $quizoptions = []): array {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id] + $quizoptions);
        $enable = static fn() => (new config_repository())->create($course->id, true);
        return [$course, $quiz, $enable];
    }

    /**
     * Q1: a quiz created already visible produces one pending event.
     */
    public function test_quiz_created_visible_produces_one_pending_event(): void {
        global $DB;
        [$course, $quiz, $enable] = $this->course_with_quiz();
        $enable();

        $result = (new quiz_publication_service())->handle_module_event($course->id, $quiz->cmid);

        $this->assertSame(enqueue_status::CREATED, $result->status);
        $this->assertSame(1, $this->published_events($course->id));
        $event = $DB->get_record('local_assistente_event', ['course_id' => $course->id], '*', MUST_EXIST);
        $this->assertSame('pending', $event->status);
        $this->assertSame((int) $quiz->cmid, json_decode($event->payload, true)['cmid'], 'The payload carries the cmid (R-006-01)');
    }

    /**
     * Q2: the Moodle event arriving again for the same published quiz keeps one event.
     */
    public function test_second_dispatch_keeps_one_event(): void {
        [$course, $quiz, $enable] = $this->course_with_quiz();
        $enable();
        $service = new quiz_publication_service();

        $service->handle_module_event($course->id, $quiz->cmid);
        $second = $service->handle_module_event($course->id, $quiz->cmid);

        $this->assertSame(enqueue_status::REUSED, $second->status);
        $this->assertSame(1, $this->published_events($course->id));
    }

    /**
     * Q6: a quiz created hidden produces no event; one event when it becomes visible.
     */
    public function test_created_hidden_then_made_visible(): void {
        [$course, $quiz, $enable] = $this->course_with_quiz(['visible' => 0]);
        $enable();
        $service = new quiz_publication_service();

        $this->assertNull($service->handle_module_event($course->id, $quiz->cmid), 'Hidden: nothing to publish');
        $this->assertSame(0, $this->published_events($course->id));

        $this->set_visibility_silently($course->id, $quiz->cmid, 1);
        $this->assertSame(enqueue_status::CREATED, $service->handle_module_event($course->id, $quiz->cmid)->status);
        $this->assertSame(1, $this->published_events($course->id));
    }

    /**
     * Q7: hiding and showing again, or editing, after the publication does not produce another event.
     */
    public function test_hide_show_and_edit_after_publication_keep_one_event(): void {
        global $DB;
        [$course, $quiz, $enable] = $this->course_with_quiz();
        $enable();
        $service = new quiz_publication_service();
        $service->handle_module_event($course->id, $quiz->cmid);

        $this->set_visibility_silently($course->id, $quiz->cmid, 0);
        $this->assertNull($service->handle_module_event($course->id, $quiz->cmid), 'Hidden again: nothing new');
        $this->set_visibility_silently($course->id, $quiz->cmid, 1);
        $this->assertSame(enqueue_status::REUSED, $service->handle_module_event($course->id, $quiz->cmid)->status);
        $DB->set_field('quiz', 'name', 'Editado', ['id' => $quiz->id]);
        $this->assertSame(enqueue_status::REUSED, $service->handle_module_event($course->id, $quiz->cmid)->status);

        $this->assertSame(1, $this->published_events($course->id));
    }

    /**
     * The opening time is not part of the definition: a visible quiz that opens in the future is published.
     */
    public function test_timeopen_does_not_matter(): void {
        [$course, $quiz, $enable] = $this->course_with_quiz(['timeopen' => time() + 30 * DAYSECS]);
        $enable();

        $result = (new quiz_publication_service())->handle_module_event($course->id, $quiz->cmid);

        $this->assertSame(enqueue_status::CREATED, $result->status);
    }

    /**
     * Anything that is not a questionnaire of that course is ignored: another module type, another course, a missing cmid, a
     * module being deleted.
     */
    public function test_other_modules_are_ignored(): void {
        global $DB;
        [$course, $quiz, $enable] = $this->course_with_quiz();
        $other = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $enable();
        $service = new quiz_publication_service();

        $this->assertNull($service->handle_module_event($course->id, $page->cmid));
        $this->assertNull($service->handle_module_event($other->id, $quiz->cmid), 'The quiz belongs to another course');
        $this->assertNull($service->handle_module_event($course->id, 987654));

        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $quiz->cmid]);
        rebuild_course_cache($course->id, true);
        $this->assertNull($service->handle_module_event($course->id, $quiz->cmid));
        $this->assertSame(0, $this->published_events($course->id));
    }

    /**
     * While the assistant is not enabled nothing is created; the quiz is published when it is next handled with it enabled.
     */
    public function test_no_event_while_the_assistant_is_not_enabled(): void {
        [$course, $quiz, $enable] = $this->course_with_quiz();
        $service = new quiz_publication_service();

        $skipped = $service->handle_module_event($course->id, $quiz->cmid);
        $this->assertSame(enqueue_status::SKIPPED_NOT_ENABLED, $skipped->status);
        $this->assertSame(0, $this->published_events($course->id));

        $enable();
        $this->assertSame(enqueue_status::CREATED, $service->handle_module_event($course->id, $quiz->cmid)->status);
    }
}
