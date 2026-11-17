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

use core\task\manager;
use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\application\delivery\observer_event_service;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use local_assistente\task\redeliver_event;
use PHPUnit\Framework\Attributes\CoversClass;

require_once(__DIR__ . '/../fixtures/lock_holder.php');

/**
 * Tests of the observers when the lock of a student is busy (exception to DEC-32 D2; T046, DEC-57).
 *
 * The observers wait three seconds for a lock, not thirty. The lock is held by another process, and what an observer cannot create now
 * is handed to a retry task: the observer does not throw, the action of the teacher goes on, and nothing is lost without a line saying so.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(participation_observer::class)]
#[CoversClass(quiz_observer::class)]
#[CoversClass(observer_event_service::class)]
final class redelivery_observers_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_holder;

    /** @var \stdClass course with the assistant enabled */
    private \stdClass $course;

    /**
     * Real transactions, locks and tasks are used; the observers start with no memory of failed locks.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($this->course->id, true);
        observer_event_service::reset();
    }

    /**
     * The holders end and the memory of the observers is dropped.
     */
    protected function tearDown(): void {
        $this->release_locks();
        observer_event_service::reset();
        parent::tearDown();
    }

    /**
     * The completion of an activity by a student, as Moodle reports it.
     *
     * @param int $userid
     * @return array{0: \core\event\course_module_completion_updated, 1: int} the event and the course module
     */
    private function completion(int $userid): array {
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $event = \core\event\course_module_completion_updated::create([
            'objectid' => 1, 'context' => \context_module::instance($assign->cmid), 'relateduserid' => $userid,
            'other' => ['relateduserid' => $userid, 'overrideby' => null, 'completionstate' => 1],
        ]);
        return [$event, (int) $assign->cmid];
    }

    /**
     * Run an observer and give back what it left in the log of PHP and how long it took.
     *
     * @param callable $observer
     * @return array{0: string, 1: float}
     */
    private function observed(callable $observer): array {
        $logfile = tempnam(sys_get_temp_dir(), 'observer_log_');
        $previous = ini_set('error_log', $logfile);
        $start = microtime(true);
        try {
            $observer();
        } finally {
            $seconds = microtime(true) - $start;
            ini_set('error_log', (string) $previous);
        }
        $log = (string) file_get_contents($logfile);
        unlink($logfile);
        return [$log, $seconds];
    }

    /**
     * The retry tasks that exist, with their data.
     *
     * @return array[]
     */
    private function deliveries(): array {
        return array_values(array_map(
            fn($task) => event_redelivery_scheduler::decode($task->get_custom_data()),
            manager::get_adhoc_tasks(redeliver_event::class)
        ));
    }

    /**
     * With the lock free the observer creates the event, as always, and schedules nothing.
     */
    public function test_a_free_lock_creates_the_event_and_no_task(): void {
        global $DB;
        [$event] = $this->completion(801);

        $this->observed(fn() => participation_observer::completion_updated($event));

        $this->assertSame(1, $DB->count_records('local_assistente_event', ['course_id' => $this->course->id, 'event_type' => 'participation_changed']));
        $this->assertSame([], $this->deliveries());
    }

    /**
     * With the lock of the student busy the observer waits about three seconds, not thirty, does not throw, hands the delivery to a task,
     * and leaves no line about a loss. A second delivery for the same student does not wait again.
     */
    public function test_a_busy_lock_is_handed_to_a_task_after_about_three_seconds(): void {
        global $DB;
        $this->hold_lock(unit_of_work::privacy_user_resource(802), (int) $this->course->id);
        [$event, $cmid] = $this->completion(802);

        [$log, $seconds] = $this->observed(fn() => participation_observer::completion_updated($event));

        $this->assertGreaterThan(2.5, $seconds);
        $this->assertLessThan(8.0, $seconds, 'Three seconds, not the thirty of the queue');
        $this->assertSame('', $log, 'The action goes on and nothing is reported as lost');
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['course_id' => $this->course->id]));
        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        $this->assertSame('participation_changed', $deliveries[0]['event_type']);
        $this->assertSame($cmid, $deliveries[0]['payload']['cmid']);
        $this->assertSame(802, $deliveries[0]['payload']['userid']);
        $this->assertSame('completion', $deliveries[0]['payload']['source']);

        [$unusedlog, $again] = $this->observed(fn() => participation_observer::completion_updated($event));
        $this->assertLessThan(1.0, $again, 'The lock that has just failed is not waited for again');
    }

    /**
     * The same for the observer of the questionnaire: the task names the module and the attempt, which is what the administrator needs.
     */
    public function test_a_questionnaire_attempt_is_handed_to_a_task_with_its_module_and_attempt(): void {
        $this->hold_lock(unit_of_work::privacy_user_resource(803), (int) $this->course->id);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $event = \mod_quiz\event\attempt_submitted::create([
            'objectid' => 4321, 'relateduserid' => 803, 'courseid' => $this->course->id, 'context' => \context_module::instance($quiz->cmid),
            'other' => ['quizid' => $quiz->id, 'submitterid' => 803],
        ]);

        [$log] = $this->observed(fn() => quiz_observer::attempt_submitted($event));

        $this->assertSame('', $log);
        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        $this->assertSame('quiz_attempt_submitted', $deliveries[0]['event_type']);
        $this->assertSame([(int) $quiz->cmid, 4321, 803], [$deliveries[0]['payload']['cmid'], $deliveries[0]['payload']['attemptid'], $deliveries[0]['payload']['userid']]);
        $this->assertSame(
            "course {$this->course->id}, type quiz_attempt_submitted, cmid {$quiz->cmid}, attemptid 4321",
            event_redelivery_scheduler::case_label((int) $this->course->id, $deliveries[0]['event_type'], $deliveries[0]['payload'])
        );
    }
}
