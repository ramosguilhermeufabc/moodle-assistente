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

namespace local_assistente\application;

use core\task\manager;
use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\domain\event\event_type;
use local_assistente\domain\event\occurrence_identity;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\infrastructure\persistence\unit_of_work;
use local_assistente\task\redeliver_event;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The uniqueness of an event under concurrent deliveries, with real processes (plan 006; DEC-16, DEC-22; A2).
 *
 * Two requests of a site are two processes with two connections to the database. The lock of the database is re-entrant inside one
 * process, so exclusion cannot be shown there; this test starts separate PHP processes (tests/fixtures/queue_worker.php) that run in
 * the database and the data root of PHPUnit, begin at the same instant, and enqueue the same occurrences one by one, in batches, or
 * both. The events they leave are counted in the database, and the answer each process got is checked: for one occurrence exactly one
 * process may have created the event and all the others must have reused it.
 *
 * Scope: a MariaDB server, the lock factory of its GET_LOCK, processes of the same machine. Other lock backends and servers are not
 * covered (CL-02, CL-07).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../fixtures/lock_observer.php');

#[CoversClass(event_service::class)]
final class event_service_concurrency_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var \stdClass course with the assistant enabled */
    private \stdClass $course;

    /**
     * The workers write in the database, and the test reads what they wrote: nothing may be wrapped in a transaction.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->clear_events();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($this->course->id, true);
    }

    /**
     * The reset of PHPUnit does not see what other processes insert, so the events are removed here, before and after each test.
     */
    protected function tearDown(): void {
        $this->end_actors();
        $this->clear_events();
        parent::tearDown();
    }

    /**
     * Remove all the events of the queue.
     */
    private function clear_events(): void {
        global $DB;
        $DB->delete_records('local_assistente_event');
    }

    /**
     * Grade occurrences of many students in the same item and the same second.
     *
     * @param int $count
     * @param int $firstuser
     * @return array[] type and payload each
     */
    private static function grades(int $count, int $firstuser = 1000): array {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = ['type' => 'grade_changed', 'payload' => ['userid' => $firstuser + $i, 'itemid' => 7, 'occurred_at' => 1790000000]];
        }
        return $items;
    }

    /**
     * The specification of a worker.
     *
     * @param array $extra
     * @param float $start
     * @param string $label
     * @return array
     */
    private function spec(array $extra, float $start, string $label): array {
        global $CFG;
        return $extra + [
            'autoload' => realpath($CFG->dirroot . '/../vendor/autoload.php'),
            'bootstrap' => $CFG->dirroot . '/lib/phpunit/bootstrap.php',
            'start' => $start, 'label' => $label, 'course' => (int) $this->course->id,
        ];
    }

    /**
     * Start a worker process.
     *
     * @param array $spec
     * @return array process and pipes
     */
    private function start(array $spec): array {
        global $CFG;
        $worker = $CFG->dirroot . '/local/assistente/tests/fixtures/queue_worker.php';
        $process = proc_open(
            [PHP_BINARY, '-d', 'xdebug.mode=off', $worker, json_encode($spec)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process, 'The worker process could not start');
        return [$process, $pipes];
    }

    /**
     * Wait for a worker and read its answer.
     *
     * @param array $started process and pipes
     * @return array the answer of the worker
     */
    private function finish(array $started): array {
        [$process, $pipes] = $started;
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $this->assertStringContainsString('@@RESULT@@', $output, "The worker did not answer. stderr: {$errors} stdout: {$output}");
        $answer = json_decode(substr($output, strpos($output, '@@RESULT@@') + 10), true);
        $this->assertIsArray($answer);
        return $answer;
    }

    /**
     * Run workers that begin together and return their answers.
     *
     * @param array[] $extras specification of each worker
     * @return array[] answers
     */
    private function run_together(array $extras): array {
        $start = microtime(true) + 3.0;
        $started = [];
        foreach ($extras as $index => $extra) {
            $started[] = $this->start($this->spec($extra, $start, 'w' . $index));
        }
        return array_map(fn(array $one) => $this->finish($one), $started);
    }

    /**
     * Number of grade events of the course.
     *
     * @return int
     */
    private function count_grade_events(): int {
        global $DB;
        return $DB->count_records('local_assistente_event', ['course_id' => $this->course->id, 'event_type' => 'grade_changed']);
    }

    /**
     * Item 1: the same occurrence delivered at the same time by four processes: one event, created by one process, reused by the others.
     */
    public function test_the_same_occurrence_from_four_processes_is_one_event(): void {
        $item = self::grades(1);
        $answers = $this->run_together(array_fill(0, 4, ['mode' => 'enqueue', 'items' => $item]));

        $this->assertSame(1, $this->count_grade_events());
        $statuses = array_map(fn(array $a) => $a['results'][0]['status'], $answers);
        $this->assertEquals(['created' => 1, 'reused' => 3], array_count_values($statuses) + ['created' => 0, 'reused' => 0]);
        $this->assertSame([null], array_unique(array_column($answers, 'error')));
        $this->assertCount(1, array_unique(array_map(fn(array $a) => $a['results'][0]['event'], $answers)), 'All of them got the same event');
    }

    /**
     * Item 2 (already there) and item 3 (new): batches of four processes over the same 40 occurrences: each is created by exactly one
     * process; run again, every occurrence already exists and nothing is created.
     */
    public function test_the_same_batch_from_four_processes_creates_each_event_once(): void {
        $items = self::grades(40);
        $spec = ['mode' => 'enqueue', 'items' => $items, 'batch' => true, 'chunk' => 10];
        $answers = $this->run_together(array_fill(0, 4, $spec));

        $this->assertSame(40, $this->count_grade_events());
        $this->assertSame([null], array_unique(array_column($answers, 'error')));
        for ($i = 0; $i < 40; $i++) {
            $statuses = array_map(fn(array $a) => $a['results'][$i]['status'], $answers);
            $this->assertEquals(['created' => 1, 'reused' => 3], array_count_values($statuses) + ['created' => 0, 'reused' => 0], "occurrence $i");
        }

        $again = $this->run_together([$spec]);
        $this->assertSame(40, $this->count_grade_events(), 'Run again: nothing new');
        $this->assertSame(['reused'], array_unique(array_column($again[0]['results'], 'status')));
    }

    /**
     * One by one and in batches at the same time, over the same occurrences: the two paths recognise each other's events.
     */
    public function test_single_and_batch_deliveries_recognise_each_other(): void {
        $items = self::grades(30);
        $answers = $this->run_together([
            ['mode' => 'enqueue', 'items' => $items],
            ['mode' => 'enqueue', 'items' => $items, 'batch' => true, 'chunk' => 7],
            ['mode' => 'enqueue', 'items' => $items],
            ['mode' => 'enqueue', 'items' => $items, 'batch' => true, 'chunk' => 11],
        ]);

        $this->assertSame(30, $this->count_grade_events());
        $this->assertSame([null], array_unique(array_column($answers, 'error')));
        for ($i = 0; $i < 30; $i++) {
            $created = count(array_filter($answers, fn(array $a) => $a['results'][$i]['status'] === 'created'));
            $this->assertSame(1, $created, "occurrence $i was created by $created processes");
        }
    }

    /**
     * Item 4: different occurrences at the same time are all created; the identity decides, not the moment.
     */
    public function test_different_occurrences_from_four_processes_are_all_created(): void {
        $specs = [];
        foreach ([0, 1, 2, 3] as $n) {
            $specs[] = ['mode' => 'enqueue', 'items' => self::grades(25, 2000 + $n * 100), 'batch' => $n % 2 === 0, 'chunk' => 8];
        }
        $answers = $this->run_together($specs);

        $this->assertSame(100, $this->count_grade_events());
        $this->assertSame([null], array_unique(array_column($answers, 'error')));
        foreach ($answers as $answer) {
            $this->assertSame(['created'], array_unique(array_column($answer['results'], 'status')));
        }
    }

    /**
     * A batch takes the lock of the identity of each occurrence: while another process holds one of them the batch does not get through,
     * writes nothing of that chunk, and goes through when the lock is released.
     */
    public function test_a_batch_waits_for_the_lock_of_an_identity_held_elsewhere(): void {
        $items = self::grades(4);
        $held = occurrence_identity::lock_resource(
            (int) $this->course->id,
            event_type::GRADE_CHANGED,
            occurrence_identity::of(event_type::GRADE_CHANGED, event_type::GRADE_CHANGED->normalize_payload($items[2]['payload']))
        );
        $this->assert_blocked_then_free($items, $held);
    }

    /**
     * The same for the lock of the student: an event of a student also takes the lock that the erasure of the data of that student
     * holds while it runs (DEC-57), as one event at a time does.
     */
    public function test_a_batch_waits_for_the_lock_of_a_student_held_elsewhere(): void {
        $items = self::grades(4);
        $this->assert_blocked_then_free($items, unit_of_work::privacy_user_resource($items[1]['payload']['userid']));
    }

    /**
     * Another process holds a lock; a batch with a short timeout fails and leaves no event; after the process lets go it works.
     *
     * @param array[] $items
     * @param string $resource
     */
    private function assert_blocked_then_free(array $items, string $resource): void {
        $ready = tempnam(sys_get_temp_dir(), 'queue_ready_');
        unlink($ready);
        $holder = $this->start($this->spec(['mode' => 'hold', 'resources' => [$resource], 'seconds' => 6, 'ready_file' => $ready], microtime(true), 'holder'));
        $deadline = microtime(true) + 20;
        while (!file_exists($ready) && microtime(true) < $deadline) {
            usleep(100000);
        }
        $this->assertFileExists($ready, 'The holder did not take the lock');

        $service = new event_service(null, null, new unit_of_work(1));
        $occurrences = [];
        foreach ($items as $item) {
            $occurrences[] = ['type' => event_type::GRADE_CHANGED, 'payload' => $item['payload']];
        }
        try {
            $service->enqueue_many((int) $this->course->id, $occurrences);
            $this->fail('The batch must not get through while the lock is held by another process');
        } catch (lock_unavailable_exception $e) {
            $this->assertSame(0, $this->count_grade_events(), 'Nothing of the blocked chunk is written');
        }

        $answer = $this->finish($holder);
        $this->assertNull($answer['error']);
        unlink($ready);
        $results = $service->enqueue_many((int) $this->course->id, $occurrences);
        $this->assertSame(['created'], array_unique(array_map(fn(enqueue_result $r) => $r->status->value, $results)));
        $this->assertSame(4, $this->count_grade_events());
    }

    /**
     * The retry tasks and the delivery of always, all at once over the same occurrence: two equal tasks (Moodle refuses a duplicate only
     * when it is asked to look, and two processes can both pass) and a direct delivery end in one event, created by exactly one of them.
     */
    public function test_equal_retry_tasks_and_a_direct_delivery_create_one_event(): void {
        // A real, active student: the guard of the retry task asks for the account.
        $student = $this->getDataGenerator()->create_user();
        $payload = ['userid' => (int) $student->id, 'itemid' => 7, 'occurred_at' => 1790000000];
        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $task = new redeliver_event();
            $task->set_custom_data(['course_id' => (int) $this->course->id, 'event_type' => 'grade_changed', 'payload' => $payload]);
            $task->set_attempts_available(8);
            $ids[] = (int) manager::queue_adhoc_task($task);
        }
        $this->assertNotSame($ids[0], $ids[1]);

        $answers = $this->run_together([
            ['mode' => 'task', 'task_id' => $ids[0]],
            ['mode' => 'task', 'task_id' => $ids[1]],
            ['mode' => 'enqueue', 'items' => [['type' => 'grade_changed', 'payload' => $payload]]],
        ]);

        $this->assertSame([null], array_unique(array_column($answers, 'error')));
        $this->assertSame(1, $this->count_grade_events());
        $created = 0;
        foreach ([0, 1] as $i) {
            $this->assertSame('executed', $answers[$i]['results'][0]['status']);
            $created += substr_count($answers[$i]['results'][0]['output'], 'redelivery created');
        }
        $created += $answers[2]['results'][0]['status'] === 'created' ? 1 : 0;
        $this->assertSame(1, $created, 'Exactly one of the three created the event');
    }

    /**
     * The erasure of a student and a retry task that is waiting for the lock of that student, with independent processes and the signals of
     * the server: the task holds the lock of the course and waits for the lock of the student, which this test holds as the erasure does.
     * Seen together, correlated by key and by connection, before the record of the task is removed (inside the transaction of the erasure),
     * the lock is let go, and the task, now with the lock, finds its record gone and creates nothing.
     * What is shown: the task was waiting for the lock of the student when its record was removed. Not shown: anything about the internal
     * state of the transaction of the erasure.
     */
    public function test_a_retry_task_waiting_for_the_lock_creates_nothing_when_the_erasure_removed_it(): void {
        $this->require_observable_locks();
        // A real, active student: what makes the guard refuse here is the record of the task, not the account.
        $student = $this->getDataGenerator()->create_user();
        $userid = (int) $student->id;
        $payload = ['userid' => $userid, 'itemid' => 7, 'occurred_at' => 1790000000];
        $id = (new event_redelivery_scheduler())->defer((int) $this->course->id, event_type::GRADE_CHANGED, $payload);
        $courselock = unit_of_work::course_erasure_resource((int) $this->course->id);
        $studentlock = unit_of_work::privacy_user_resource($userid);
        $actor = null;

        (new unit_of_work(5))->run(function () use (&$actor, $id, $courselock, $studentlock) {
            $actor = $this->spawn_actor(['mode' => 'task', 'task_id' => $id, 'course' => (int) $this->course->id], 'task');
            $this->await(
                fn() => $this->lock_owner($courselock) === $actor['connection'] && $this->waiting_row($actor['connection'], $studentlock) !== null
                    && $this->lock_owner($courselock) === $actor['connection'],
                'the task holds the lock of the course and waits for the lock of the student'
            );
            $this->note("task connection {$actor['connection']} owns {$this->lock_key($courselock)} and waits for {$this->lock_key($studentlock)}");
            manager::delete_adhoc_task($id);
        }, [$studentlock]);
        $answer = $this->finish_actor($actor);

        $this->assertNull($answer['error']);
        $this->assertSame('executed', $answer['results'][0]['status']);
        $this->assertStringContainsString('skipped_revoked', $answer['results'][0]['output']);
        $this->assertSame(0, $this->count_grade_events());
    }

    /**
     * Number of requests of rebuild of a forum in the course, and their statuses, oldest first.
     *
     * @return string[] status of each request
     */
    private function rebuild_statuses(): array {
        global $DB;
        return array_values($DB->get_fieldset_select(
            'local_assistente_event',
            'status',
            'course_id = ? AND event_type = ? ORDER BY id ASC',
            [(int) $this->course->id, event_type::FORUM_REBUILD_REQUESTED->value]
        ));
    }

    /**
     * Workers that all ask for the rebuild of the same forum, half of them one by one and half in batches (CL-47).
     *
     * @param int $count
     * @param int $cmid
     * @return array[] answers
     */
    private function rebuild_requests_together(int $count, int $cmid): array {
        $items = [['type' => 'forum_rebuild_requested', 'payload' => ['cmid' => $cmid]]];
        $specs = [];
        for ($i = 0; $i < $count; $i++) {
            $specs[] = ['mode' => 'enqueue', 'items' => $items, 'batch' => $i % 2 === 1];
        }
        return $this->run_together($specs);
    }

    /**
     * CL-47: the same forum asked for at the same time by four processes, while no request is running, is one pending request: created by
     * one process and reused by the others (the pending-only identity, shared by the one-by-one path and the batch path).
     */
    public function test_rebuild_requests_from_four_processes_coalesce_into_one_pending_event(): void {
        $answers = $this->rebuild_requests_together(4, 40);

        $this->assertSame([null], array_unique(array_column($answers, 'error')));
        $this->assertSame(['pending'], $this->rebuild_statuses());
        $statuses = array_map(fn(array $a) => $a['results'][0]['status'], $answers);
        $this->assertEquals(['created' => 1, 'reused' => 3], array_count_values($statuses) + ['created' => 0, 'reused' => 0]);
        $this->assertCount(1, array_unique(array_map(fn(array $a) => $a['results'][0]['event'], $answers)));
    }

    /**
     * CL-47: a request that arrives while the first one is processing is not lost. The state is made by the real repository (the attempt
     * begins, the event is `processing`, committed), then four real processes ask for the same forum at the same time: exactly one new
     * event is created, the others reuse it, and the one being processed is left alone. After the first finishes, the new one is still
     * pending and still takes more requests; after that one finishes too, the next request creates another event.
     */
    public function test_a_request_during_the_processing_is_kept_for_a_later_processing(): void {
        global $DB;
        $repository = new event_repository();
        $first = (new event_service())->enqueue((int) $this->course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 40])->event;
        $attempt = $repository->begin_attempt((int) $first->id, 2000);
        $this->assertSame(['processing'], $this->rebuild_statuses());

        $answers = $this->rebuild_requests_together(4, 40);

        $this->assertSame([null], array_unique(array_column($answers, 'error')));
        $statuses = array_map(fn(array $a) => $a['results'][0]['status'], $answers);
        $this->assertEquals(['created' => 1, 'reused' => 3], array_count_values($statuses) + ['created' => 0, 'reused' => 0]);
        $ids = array_unique(array_map(fn(array $a) => $a['results'][0]['event'], $answers));
        $this->assertCount(1, $ids, 'All the requests of the moment ended in one new event');
        $this->assertNotContains((int) $first->id, $ids, 'The event being processed does not absorb the request');
        $this->assertSame(['processing', 'pending'], $this->rebuild_statuses());

        $repository->complete_attempt((int) $attempt->id, 2001);
        $more = $this->rebuild_requests_together(2, 40);
        $this->assertSame(['reused'], array_unique(array_map(fn(array $a) => $a['results'][0]['status'], $more)));
        $this->assertSame(['processed', 'pending'], $this->rebuild_statuses(), 'The second request waits for its own processing');
        $second = (int) reset($ids);
        $this->assertSame('pending', $DB->get_field('local_assistente_event', 'status', ['id' => $second]));

        $repository->complete_attempt((int) $repository->begin_attempt($second, 2002)->id, 2003);
        $last = $this->rebuild_requests_together(4, 40);
        $statuses = array_map(fn(array $a) => $a['results'][0]['status'], $last);
        $this->assertEquals(['created' => 1, 'reused' => 3], array_count_values($statuses) + ['created' => 0, 'reused' => 0]);
        $this->assertSame(['processed', 'processed', 'pending'], $this->rebuild_statuses());
    }

    /**
     * CL-47: requests for different forums at the same time do not merge: one pending event for each forum.
     */
    public function test_rebuild_requests_of_different_forums_stay_separate(): void {
        $specs = [];
        foreach ([40, 41, 42, 43] as $n => $cmid) {
            $specs[] = ['mode' => 'enqueue', 'items' => [['type' => 'forum_rebuild_requested', 'payload' => ['cmid' => $cmid]]], 'batch' => $n % 2 === 1];
        }
        $answers = $this->run_together($specs);

        $this->assertSame([null], array_unique(array_column($answers, 'error')));
        $this->assertSame(['created'], array_unique(array_map(fn(array $a) => $a['results'][0]['status'], $answers)));
        $this->assertSame(['pending', 'pending', 'pending', 'pending'], $this->rebuild_statuses());
    }
}
