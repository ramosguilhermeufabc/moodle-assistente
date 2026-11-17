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

namespace local_assistente\application\privacy;

use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\course_erasure_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\redelivery_task_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use local_assistente\observer\course_observer;
use PHPUnit\Framework\Attributes\CoversClass;

require_once(__DIR__ . '/../../fixtures/lock_observer.php');

/**
 * The disputes between the retry task of a delivery and the erasures, with independent processes (redelivery of an event; exception to DEC-32 D2).
 *
 * Every actor (the erasure of a course, the retry task, the erasure of a student, whoever holds a lock or a row) is its own PHP process with its
 * own connection. This test only starts them, looks at the server and releases them. An actor is started only after the signal of the previous
 * one was seen; the signals are the owner of a lock (IS_USED_LOCK) and the wait for it (the state "User lock" running GET_LOCK of the same key),
 * correlated by key and by connection. See the trait lock_observer for what is and is not shown. A signal that does not show is a failure
 * ("SINAL NÃO OBSERVADO"); a server whose signals were not seen with a known holder and waiter skips the test, with the reason.
 *
 * What these tests do not show: that the erasure is blocked by a row of the database in scenario A (the row is only the way to keep it busy; the
 * server list says "Updating", which is a hint and not a proof), the internal state of any transaction, the text of the states on another
 * server, and the order between the task and the erasure of a student in the rounds (it is recorded, and it is not forced).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_data_eraser::class)]
#[CoversClass(student_data_eraser::class)]
#[CoversClass(course_erasure_repository::class)]
final class redelivery_erasure_race_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var event_redelivery_scheduler */
    private event_redelivery_scheduler $scheduler;

    /**
     * Real transactions, locks and processes are used. The processes write in the database, which the reset of PHPUnit does not see, so what
     * they leave is removed here, before and after each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->require_observable_locks();
        $this->scheduler = new event_redelivery_scheduler();
        $this->clean();
    }

    /**
     * The processes end and the database is left clean.
     */
    protected function tearDown(): void {
        $this->end_actors();
        $this->clean();
        course_observer::use_eraser(null);
        parent::tearDown();
    }

    /**
     * Remove the events and the retry tasks that the tests and their processes made.
     */
    private function clean(): void {
        global $DB;
        $DB->delete_records('local_assistente_event');
        $DB->delete_records('task_adhoc', ['classname' => '\\local_assistente\\task\\redeliver_event']);
    }

    /**
     * A course with the assistant enabled and a student of it.
     *
     * @return array{0: \stdClass, 1: \stdClass}
     */
    private function world(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        (new config_repository())->create($course->id, true);
        $student = $generator->create_and_enrol($course, 'student');
        return [$course, $student];
    }

    /**
     * The delivery of a grade of a student, scheduled.
     *
     * @param \stdClass $course
     * @param \stdClass $student
     * @return int the task
     */
    private function delivery(\stdClass $course, \stdClass $student): int {
        return $this->scheduler->defer((int) $course->id, event_type::GRADE_CHANGED, ['userid' => (int) $student->id, 'itemid' => 7, 'occurred_at' => 1790000000]);
    }

    /**
     * What is left of a course in the plugin: its events, its retry tasks and its configuration.
     *
     * @param \stdClass $course
     * @return array{events: int, tasks: int, config: bool}
     */
    private function left_of(\stdClass $course): array {
        global $DB;
        $tasks = array_filter((new redelivery_task_repository())->deliveries(), fn($d) => $d['course_id'] === (int) $course->id);
        return [
            'events' => $DB->count_records('local_assistente_event', ['course_id' => $course->id]),
            'tasks' => count($tasks),
            'config' => $DB->record_exists('local_assistente_config', ['course_id' => $course->id]),
        ];
    }

    /**
     * Scenario A, the erasure of the course first: the erasure holds the lock of the course (seen), and the retry task arrives and waits for
     * that same lock (seen, with the erasure still the owner). Only then the row that keeps the erasure busy is let go. The task, with the
     * lock, finds the course gone and makes no event.
     */
    public function test_the_erasure_of_the_course_first_and_the_task_waits_for_the_lock_of_the_course(): void {
        [$course, $student] = $this->world();
        $courselock = unit_of_work::course_erasure_resource((int) $course->id);
        $event = (new event_repository())->create_event((int) $course->id, event_type::GRADE_CHANGED, ['userid' => (int) $student->id, 'itemid' => 9, 'occurred_at' => 1790000001], time());
        $taskid = $this->delivery($course, $student);

        $row = $this->hold_event_row((int) $event->id, 'holder of the row of the event');
        $erasure = $this->spawn_actor(['mode' => 'erase_course', 'course' => (int) $course->id], 'erasure of the course');
        $this->await(
            fn() => $this->lock_owner($courselock) === $erasure['connection'],
            'the erasure of the course holds the lock of the course'
        );
        $task = $this->spawn_actor(['mode' => 'task', 'task_id' => $taskid, 'course' => (int) $course->id], 'retry task');
        $this->await(
            fn() => $this->lock_owner($courselock) === $erasure['connection']
                && $this->waiting_row($task['connection'], $courselock) !== null
                && $this->lock_owner($courselock) === $erasure['connection'],
            'the retry task waits for the lock of the course while the erasure of the course owns it'
        );
        $this->note("A: erasure connection {$erasure['connection']} owns {$this->lock_key($courselock)}; task connection {$task['connection']} waits for it");
        $this->release_actor($row);
        $erased = $this->finish_actor($erasure);
        $tasked = $this->finish_actor($task);
        $this->finish_actor($row);

        $this->assertNull($erased['error']);
        $this->assertNull($tasked['error']);
        $this->assertSame('executed', $tasked['results'][0]['status']);
        $output = $tasked['results'][0]['output'];
        $this->assertTrue(
            str_contains($output, 'skipped_not_enabled') || str_contains($output, 'skipped_revoked'),
            'The task found the course gone and made nothing: ' . $output
        );
        $this->assertSame(['events' => 0, 'tasks' => 0, 'config' => false], $this->left_of($course));
    }

    /**
     * Scenario B, the retry task first: the task holds the lock of the course and waits for the lock of the student, which another process holds
     * (both seen). Then the erasure of the course arrives and waits for the lock of the course (seen, with the task still the owner). Only then
     * the lock of the student is let go. The task makes its event, ends, and the erasure removes the event, the task and the configuration.
     */
    public function test_the_task_first_and_the_erasure_of_the_course_waits_for_the_lock_of_the_course(): void {
        [$course, $student] = $this->world();
        $courselock = unit_of_work::course_erasure_resource((int) $course->id);
        $studentlock = unit_of_work::privacy_user_resource((int) $student->id);
        $taskid = $this->delivery($course, $student);

        $holder = $this->hold_resource($studentlock, 'holder of the lock of the student');
        $task = $this->spawn_actor(['mode' => 'task', 'task_id' => $taskid, 'course' => (int) $course->id], 'retry task');
        $this->await(
            fn() => $this->lock_owner($courselock) === $task['connection']
                && $this->waiting_row($task['connection'], $studentlock) !== null
                && $this->lock_owner($courselock) === $task['connection'],
            'the retry task holds the lock of the course and waits for the lock of the student'
        );
        $erasure = $this->spawn_actor(['mode' => 'erase_course', 'course' => (int) $course->id], 'erasure of the course');
        $this->await(
            fn() => $this->lock_owner($courselock) === $task['connection']
                && $this->waiting_row($erasure['connection'], $courselock) !== null
                && $this->lock_owner($courselock) === $task['connection'],
            'the erasure of the course waits for the lock of the course while the retry task owns it'
        );
        $this->note("B: task connection {$task['connection']} owns {$this->lock_key($courselock)} and waits for the student; erasure connection {$erasure['connection']} waits for the course");
        $this->release_actor($holder);
        $tasked = $this->finish_actor($task);
        $erased = $this->finish_actor($erasure);
        $this->finish_actor($holder);

        $this->assertNull($tasked['error']);
        $this->assertNull($erased['error']);
        $this->assertStringContainsString('redelivery created', $tasked['results'][0]['output'], 'The task went first and made its event');
        $this->assertSame(['events' => 0, 'tasks' => 0, 'config' => false], $this->left_of($course), 'The erasure removed what the task made');
    }

    /**
     * Five rounds of the triangle with the three real actors, in independent processes: the task holds the lock of the course and waits for the
     * lock of the student, the erasure of the student waits for the lock of the student, and the erasure of the course waits for the lock of the
     * course. Every wait is seen before the next actor starts and before the lock of the student is let go. Then the two resolutions are
     * possible (the task wins the lock of the student, or the erasure of the student does); which one happens is recorded, not forced, so the
     * five rounds do not show that both happened. What is checked each round: everyone ends, with no error and no deadlock of the database, the
     * course keeps nothing, and a second course, with its own student, keeps everything.
     */
    public function test_five_rounds_of_the_triangle_end_without_deadlock(): void {
        $outcomes = [];
        for ($round = 1; $round <= 5; $round++) {
            [$course, $student] = $this->world();
            [$sentinel, $other] = $this->world();
            (new event_repository())->create_event((int) $sentinel->id, event_type::GRADE_CHANGED, ['userid' => (int) $other->id, 'itemid' => 1, 'occurred_at' => 1790000005], time());
            $this->delivery($sentinel, $other);
            $courselock = unit_of_work::course_erasure_resource((int) $course->id);
            $studentlock = unit_of_work::privacy_user_resource((int) $student->id);
            $taskid = $this->delivery($course, $student);

            $holder = $this->hold_resource($studentlock, "round {$round} holder of the lock of the student");
            $task = $this->spawn_actor(['mode' => 'task', 'task_id' => $taskid, 'course' => (int) $course->id], "round {$round} retry task");
            $this->await(
                fn() => $this->lock_owner($courselock) === $task['connection'] && $this->waiting_row($task['connection'], $studentlock) !== null,
                "round {$round}: the retry task holds the lock of the course and waits for the lock of the student"
            );
            $pupil = $this->spawn_actor(['mode' => 'erase_student', 'userid' => (int) $student->id, 'course' => (int) $course->id], "round {$round} erasure of the student");
            $this->await(
                fn() => $this->waiting_row($pupil['connection'], $studentlock) !== null,
                "round {$round}: the erasure of the student waits for the lock of the student"
            );
            $erasure = $this->spawn_actor(['mode' => 'erase_course', 'course' => (int) $course->id], "round {$round} erasure of the course");
            $this->await(
                fn() => $this->lock_owner($courselock) === $task['connection'] && $this->waiting_row($erasure['connection'], $courselock) !== null
                    && $this->lock_owner($courselock) === $task['connection'],
                "round {$round}: the erasure of the course waits for the lock of the course while the retry task owns it"
            );
            $this->release_actor($holder);
            $answers = [
                'task' => $this->finish_actor($task),
                'student' => $this->finish_actor($pupil),
                'course' => $this->finish_actor($erasure),
            ];
            $this->finish_actor($holder);

            foreach ($answers as $who => $answer) {
                $this->assertNull($answer['error'], "round {$round}: {$who}: " . json_encode($answer['error']));
                $this->assertStringNotContainsStringIgnoringCase('deadlock', (string) json_encode($answer), "round {$round}: {$who}");
            }
            $this->assertSame(['events' => 0, 'tasks' => 0, 'config' => false], $this->left_of($course), "round {$round}");
            $this->assertSame(['events' => 1, 'tasks' => 1, 'config' => true], $this->left_of($sentinel), "round {$round}: the other course is intact");
            $outcomes[] = str_contains($answers['task']['results'][0]['output'], 'redelivery created') ? 'task first' : 'erasure of the student first';
            $this->note("round {$round}: order of the task and the erasure of the student = " . end($outcomes));
            $this->end_actors();
        }
        $this->assertCount(5, $outcomes);
    }

    /**
     * The erasure of a course that fails for the lock: the observer catches it, writes it down and does not throw; the data of the course stay.
     * Nothing tries again by itself: the erasure is repeated only if somebody asks for it. The lock is held by an independent process, seen as
     * the owner before the observer is called.
     */
    public function test_a_busy_lock_of_the_erasure_of_the_course_is_logged_by_the_observer_and_the_data_stay(): void {
        [$course, $student] = $this->world();
        $courselock = unit_of_work::course_erasure_resource((int) $course->id);
        (new event_repository())->create_event((int) $course->id, event_type::GRADE_CHANGED, ['userid' => (int) $student->id, 'itemid' => 9, 'occurred_at' => 1790000001], time());
        $this->delivery($course, $student);
        $holder = $this->hold_resource($courselock, 'holder of the lock of the course');
        $this->await(fn() => $this->lock_owner($courselock) === $holder['connection'], 'the holder owns the lock of the course');
        course_observer::use_eraser(new course_data_eraser(new course_erasure_repository(new unit_of_work(1))));
        $event = \core\event\course_deleted::create([
            'objectid' => $course->id, 'context' => \context_course::instance($course->id),
            'other' => ['shortname' => $course->shortname, 'fullname' => $course->fullname, 'idnumber' => '', 'format' => $course->format],
        ]);

        $logfile = tempnam(sys_get_temp_dir(), 'course_observer_log_');
        $previous = ini_set('error_log', $logfile);
        try {
            course_observer::course_deleted($event);
        } finally {
            ini_set('error_log', (string) $previous);
        }
        $log = (string) file_get_contents($logfile);
        unlink($logfile);
        $messages = array_map(static fn($m) => $m->message, $this->getDebuggingMessages());
        $this->resetDebugging();
        $this->release_actor($holder);
        $this->finish_actor($holder);

        $this->assertCount(1, $messages, 'The failure is written down once');
        $this->assertStringContainsString("could not erase the data of the deleted course {$course->id}", $messages[0]);
        $this->assertStringContainsString("Could not acquire the lock '{$courselock}'", $messages[0]);
        $this->assertStringContainsString("could not erase the data of the deleted course {$course->id}", $log);
        $this->assertSame(['events' => 1, 'tasks' => 1, 'config' => true], $this->left_of($course), 'The data of the course stay');
    }
}
