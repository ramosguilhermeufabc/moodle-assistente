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

use local_assistente\application\quiz\quiz_attempt_event_service;
use local_assistente\infrastructure\persistence\config_repository;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the quiz attempt observer (T073; DEC-36 D4), through the events of Moodle 5.2.4 and the real queue.
 *
 * Emission validated here by running the real action: attempt submitted (a real attempt is submitted), including its absence for a
 * preview, and the order of the submit and grade events. Emission reproduced with the parameters Moodle itself passes, but NOT by
 * running its page (pending, T075): question_manually_graded (comment.php and the grading report are scripts) and attempt_regraded
 * (the overview report, here called directly).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(quiz_observer::class)]
#[CoversClass(quiz_attempt_event_service::class)]
final class quiz_observer_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass quiz instance with cmid */
    private \stdClass $quiz;

    /** @var \stdClass */
    private \stdClass $student;

    /**
     * Real transactions and locks are used by the queue; the observers run through real events.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($this->course->id, true);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id, 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => 1,
        ]);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => \context_module::instance($this->quiz->cmid)->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $this->quiz);
    }

    /**
     * Start an attempt for a user.
     *
     * @param \stdClass $user
     * @param bool $preview
     * @return quiz_attempt
     */
    private function start_attempt(\stdClass $user, bool $preview = false): quiz_attempt {
        $quizobj = quiz_settings::create($this->quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, $preview, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        return quiz_attempt::create($attempt->id);
    }

    /**
     * Events of the queue of a type.
     *
     * @param string $type
     * @return \stdClass[]
     */
    private function queued(string $type): array {
        global $DB;
        return array_values($DB->get_records('local_assistente_event', ['event_type' => $type], 'id ASC'));
    }

    /**
     * The three observers are registered after the commit, with callbacks that exist.
     */
    public function test_observers_are_registered_after_the_commit(): void {
        global $CFG;
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        $byname = array_column($observers, null, 'eventname');

        foreach ([
            '\mod_quiz\event\attempt_submitted',
            '\mod_quiz\event\question_manually_graded',
            '\mod_quiz\event\attempt_regraded',
        ] as $name) {
            $this->assertArrayHasKey($name, $byname);
            $this->assertTrue(is_callable($byname[$name]['callback']), $name);
            $this->assertFalse($byname[$name]['internal'], $name);
            $this->assertTrue(class_exists(ltrim($name, '\\')), 'The event class exists in this Moodle');
        }
    }

    /**
     * VALIDATED with the real action: submitting a real attempt emits attempt_submitted, the queue gets one quiz_attempt_submitted
     * with the course, the questionnaire, the attempt and the student taken from the event.
     */
    public function test_submitting_an_attempt_enqueues_it(): void {
        $attempt = $this->start_attempt($this->student);

        $attempt->process_submit(time(), false);

        $events = $this->queued('quiz_attempt_submitted');
        $this->assertCount(1, $events);
        $payload = json_decode($events[0]->payload, true);
        $this->assertSame((int) $this->quiz->cmid, $payload['cmid']);
        $this->assertSame((int) $attempt->get_attemptid(), $payload['attemptid']);
        $this->assertSame((int) $this->student->id, $payload['userid']);
        $this->assertSame((int) $this->course->id, (int) $events[0]->course_id);
        $this->assertSame('pending', $events[0]->status);
    }

    /**
     * VALIDATED: the event comes before the automatic grading (the attempt is `submitted`, not yet `finished`); attempt_graded follows
     * and is not observed, because the queue runs later and results_handler reads only finished attempts.
     */
    public function test_the_submit_event_comes_before_the_grading(): void {
        global $DB;
        $attempt = $this->start_attempt($this->student);
        $statesseen = [];
        $spy = static function (\mod_quiz\event\attempt_submitted $event) use (&$statesseen): void {
            global $DB;
            $statesseen[] = $DB->get_field('quiz_attempts', 'state', ['id' => $event->objectid]);
        };
        \core\event\manager::phpunit_replace_observers([[
            'eventname' => '\mod_quiz\event\attempt_submitted',
            'callback' => $spy,
            'internal' => false,
            'includefile' => null,
            'priority' => 0,
        ]]);

        $attempt->process_submit(time(), false);
        $afterevent = $DB->get_field('quiz_attempts', 'state', ['id' => $attempt->get_attemptid()]);
        $attempt->process_grade_submission(time());

        $this->assertSame(['submitted'], $statesseen, 'Observers run with the attempt submitted, not graded');
        $this->assertSame('submitted', $afterevent);
        $this->assertSame('finished', $DB->get_field('quiz_attempts', 'state', ['id' => $attempt->get_attemptid()]));
    }

    /**
     * VALIDATED: a preview does not emit attempt_submitted, so nothing is queued.
     */
    public function test_a_preview_enqueues_nothing(): void {
        $preview = $this->start_attempt($this->getDataGenerator()->create_user(), true);

        $preview->process_submit(time(), false);

        $this->assertSame([], $this->queued('quiz_attempt_submitted'));
    }

    /**
     * A submitted event delivered again for the same attempt keeps one queue event (once per attempt).
     */
    public function test_a_repeated_submit_event_keeps_one_queue_event(): void {
        $attempt = $this->start_attempt($this->student);
        $attempt->process_submit(time(), false);
        $stored = quiz_attempt::create($attempt->get_attemptid());

        \mod_quiz\event\attempt_submitted::create([
            'objectid' => $stored->get_attemptid(),
            'relateduserid' => $this->student->id,
            'courseid' => $this->course->id,
            'context' => $stored->get_quizobj()->get_context(),
            'other' => ['quizid' => $this->quiz->id, 'submitterid' => $this->student->id, 'studentisonline' => false],
        ])->trigger();

        $this->assertCount(1, $this->queued('quiz_attempt_submitted'));
    }

    /**
     * Manual grading, with the parameters Moodle passes in comment.php: one queue event for each question saved; the same question
     * saved again in the same second is the same occurrence. The page itself is not run here (pending, T075).
     */
    public function test_manual_grading_enqueues_one_event_per_question_saved(): void {
        $attempt = $this->start_attempt($this->student);
        $trigger = function (int $slot) use ($attempt): void {
            \mod_quiz\event\question_manually_graded::create([
                'objectid' => $attempt->get_question_attempt(1)->get_question_id(),
                'courseid' => $attempt->get_courseid(),
                'context' => $attempt->get_quizobj()->get_context(),
                'other' => ['quizid' => $attempt->get_quizid(), 'attemptid' => $attempt->get_attemptid(), 'slot' => $slot],
            ])->trigger();
        };

        $trigger(1);
        $trigger(2);
        $trigger(2);

        $events = $this->queued('quiz_attempt_manually_graded');
        $this->assertCount(2, $events, 'Slots 1 and 2; slot 2 twice in one second is one occurrence');
        $payload = json_decode($events[0]->payload, true);
        $this->assertSame((int) $this->quiz->cmid, $payload['cmid']);
        $this->assertSame((int) $attempt->get_attemptid(), $payload['attemptid']);
        $this->assertSame(1, $payload['slot']);
        $this->assertGreaterThan(0, $payload['occurred_at']);
    }

    /**
     * Regrading, with the parameters of the overview report; one queue event per attempt regraded. The report is not run here
     * (pending, T075).
     */
    public function test_regrading_enqueues_the_attempt(): void {
        $attempt = $this->start_attempt($this->student);

        \mod_quiz\event\attempt_regraded::create([
            'objectid' => $attempt->get_attemptid(),
            'relateduserid' => $this->student->id,
            'context' => $attempt->get_quizobj()->get_context(),
            'other' => ['quizid' => $this->quiz->id],
        ])->trigger();

        $events = $this->queued('quiz_attempt_regraded');
        $this->assertCount(1, $events);
        $this->assertSame((int) $attempt->get_attemptid(), json_decode($events[0]->payload, true)['attemptid']);
        $this->assertSame((int) $this->course->id, (int) $events[0]->course_id);
    }

    /**
     * A course without the assistant enabled gets nothing.
     */
    public function test_course_without_the_assistant_enqueues_nothing(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $before = $DB->count_records('local_assistente_event');

        \mod_quiz\event\attempt_regraded::create([
            'objectid' => 1,
            'relateduserid' => $user->id,
            'context' => \context_module::instance($quiz->cmid),
            'other' => ['quizid' => $quiz->id],
        ])->trigger();

        $this->assertSame($before, $DB->count_records('local_assistente_event'));
    }

    /**
     * Incomplete data is refused: nothing is queued and the failure is logged, not thrown to the action that triggered the event.
     */
    public function test_incomplete_event_data_is_refused_safely(): void {
        $attempt = $this->start_attempt($this->student);

        \mod_quiz\event\question_manually_graded::create([
            'objectid' => 1,
            'courseid' => $attempt->get_courseid(),
            'context' => $attempt->get_quizobj()->get_context(),
            'other' => ['quizid' => $attempt->get_quizid(), 'attemptid' => 0, 'slot' => 0],
        ])->trigger();

        $this->assertSame([], $this->queued('quiz_attempt_manually_graded'));
        $this->assertDebuggingCalled();
    }

    /**
     * A failure of the queue is logged and does not break the action that triggered the event.
     */
    public function test_a_failure_is_contained_and_logged(): void {
        $service = new class extends quiz_attempt_event_service {
            /**
             * Constructor, without the real collaborators.
             */
            public function __construct() {
            }

            #[\Override]
            public function attempt_regraded(int $courseid, int $cmid, int $attemptid, int $occurredat): \local_assistente\application\enqueue_result {
                throw new \RuntimeException('queue unavailable');
            }
        };
        quiz_observer::use_service($service);
        $attempt = $this->start_attempt($this->student);

        \mod_quiz\event\attempt_regraded::create([
            'objectid' => $attempt->get_attemptid(),
            'relateduserid' => $this->student->id,
            'context' => $attempt->get_quizobj()->get_context(),
            'other' => ['quizid' => $this->quiz->id],
        ])->trigger();

        $this->assertDebuggingCalled();
        quiz_observer::use_service(null);
    }
}
