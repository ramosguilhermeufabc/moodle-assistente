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

namespace local_assistente\task;

use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\domain\material\material_availability;
use local_assistente\domain\material_type;
use local_assistente\infrastructure\moodle\material_reader;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;

require_once(__DIR__ . '/../fixtures/lock_observer.php');

/**
 * The reconciliation of the material versions against the real queue, in separate PHP processes (CL-51).
 *
 * Every worker is a PHP process with its own connection to the test database: the real task (reconcile_material_versions), the real queue
 * (queue_processor with the handler and the service of the materials, on the real page of a course, with a scripted fake LLM), the real
 * event_service and the real repository. Nothing here is two calls in a row in one process: the order of arrival is fixed by files and by
 * the lock list of the server, the LLM is stopped inside its call (no transaction open) while the other processes act, and what is
 * asserted is read from the database afterwards.
 *
 * Each scenario runs RUNS times, each with its own material, and the whole class is meant to be run three times in a row. The locks and
 * timeouts are the ones of the project (30 seconds of the repository, 1 second of the event lock of the queue); the only one shortened is
 * the lock wait of the task in the scenario that holds a lock on purpose, and it is said there.
 *
 * What is NOT proved here: MariaDB 11.4.13 with GET_LOCK, same machine; no other backend; the cron of Moodle (CL-02/T051) does not run the
 * task here, the process runs it as the fixture does.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reconcile_material_versions::class)]
final class reconcile_material_versions_concurrency_test extends \advanced_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var int contended runs of each scenario */
    private const RUNS = 6;

    /** @var string prefix of the triggers of this class */
    private const TRIGGER_PREFIX = 'cl51_';

    /** @var \stdClass the course, with the assistant enabled */
    private \stdClass $course;

    /** @var int counter that makes every occurrence of an event distinct */
    private int $occurrence = 0;

    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        unit_of_work::reset_lock_factory();
        $this->clean_tables();
        $this->assert_observable_locks();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create((int) $this->course->id, true);
    }

    protected function tearDown(): void {
        global $DB;
        try {
            $this->end_actors();
        } finally {
            foreach ($this->leftover_triggers() as $name) {
                $DB->execute("DROP TRIGGER IF EXISTS {$name}");
            }
            $this->clean_tables();
            unit_of_work::reset_lock_factory();
            parent::tearDown();
        }
    }

    /**
     * The reset of PHPUnit does not see what other processes write: empty the tables of the plugin that these tests use.
     */
    private function clean_tables(): void {
        global $DB;
        foreach (
            [
                'local_assistente_processing_attempt', 'local_assistente_processing_context', 'local_assistente_processing',
                'local_assistente_event', 'local_assistente_material_version', 'local_assistente_material', 'local_assistente_config',
            ] as $table
        ) {
            $DB->delete_records($table);
        }
    }

    /**
     * @return string[] the triggers of this class that are in the database
     */
    private function leftover_triggers(): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT TRIGGER_NAME AS id FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND LEFT(TRIGGER_NAME, '
            . strlen(self::TRIGGER_PREFIX) . ') = ?',
            [self::TRIGGER_PREFIX]
        );
        return array_map('strval', array_keys($rows));
    }

    /**
     * A page of the course, a real functional material.
     *
     * @return \stdClass course id and cmid
     */
    private function page(): \stdClass {
        global $DB;
        // Creating the module fires the observer of the plugin, which queues an event of its own; the events of these tests are made by the
        // test, so the one the creation made is removed.
        $last = (int) $DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {local_assistente_event}');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id, 'content' => '<p>Texto ' . uniqid('', true) . '</p>',
        ]);
        $DB->delete_records_select('local_assistente_event', 'id > ?', [$last]);
        return (object) ['course' => (int) $this->course->id, 'cmid' => (int) $page->cmid];
    }

    /**
     * A material registered for the page, with a pending version of the content the page has now (what a worker that died after
     * add_version() leaves behind).
     *
     * @param \stdClass $page
     * @return int[] material id, version id
     */
    private function material_with_pending_version(\stdClass $page): array {
        $moodle = (new material_reader())->get_material($page->course, $page->cmid);
        $this->assertNotNull($moodle, 'The page must be a functional material');
        $repository = new material_repository();
        $materialid = $repository->register(
            $page->course,
            (int) $moodle->section_id,
            $page->cmid,
            material_type::from($moodle->material_type),
            (string) $moodle->name,
            material_availability::AVAILABLE
        );
        return [$materialid, $repository->add_version($materialid, $moodle->content_hash, material_availability::AVAILABLE)];
    }

    /**
     * A material with the completed versions 1 to $count, none of them current.
     *
     * @param \stdClass $page
     * @param int $count
     * @return array [material id, version ids by number]
     */
    private function material_with_completed_versions(\stdClass $page, int $count): array {
        $repository = new material_repository();
        $materialid = $repository->register($page->course, 10, $page->cmid, material_type::TEXT, 'Concorrência', material_availability::AVAILABLE);
        $versions = [];
        for ($number = 1; $number <= $count; $number++) {
            $versions[$number] = $repository->add_version($materialid, "hash-{$number}", material_availability::AVAILABLE);
            $repository->complete_summary($versions[$number], "resumo {$number}");
        }
        return [$materialid, $versions];
    }

    /**
     * Version rows of a material, by id.
     *
     * @param int $materialid
     * @return \stdClass[]
     */
    private function versions(int $materialid): array {
        global $DB;
        return $DB->get_records('local_assistente_material_version', ['material_id' => $materialid], 'id ASC');
    }

    /**
     * The current version of a material, as the row of the version (null when there is none).
     *
     * @param int $materialid
     * @return \stdClass|null
     */
    private function current_of(int $materialid): ?\stdClass {
        global $DB;
        $pointer = $DB->get_field('local_assistente_material', 'current_version', ['id' => $materialid], MUST_EXIST);
        return $pointer === null ? null : $DB->get_record('local_assistente_material_version', ['id' => $pointer], '*', MUST_EXIST);
    }

    /**
     * The invariant of DEC-61/62: the pointer is null or points to a completed version of its own material.
     *
     * @param int $materialid
     * @param string $when
     */
    private function assert_pointer_invariant(int $materialid, string $when): void {
        $current = $this->current_of($materialid);
        if ($current !== null) {
            $this->assertSame($materialid, (int) $current->material_id, "{$when}: the pointer is on a version of another material");
            $this->assertSame('completed', $current->summary_status, "{$when}: the pointer is on a version that is not completed");
        }
    }

    /**
     * An event of the page in the queue, made by the test process (the arrival of an event is not what is measured here).
     *
     * @param \stdClass $page
     * @return int the id of the event
     */
    private function enqueue_event(\stdClass $page): int {
        $result = (new event_service())->enqueue($page->course, event_type::MATERIAL_CHANGED, [
            'cmid' => $page->cmid, 'moodle_event' => 'cl51_test', 'occurred_at' => 1000 + ++$this->occurrence,
        ]);
        $this->assertSame('created', $result->status->value);
        return (int) $result->event->id;
    }

    /**
     * An event of the page in the queue, made by a process of its own with the lock timeout of the project; the wait must be far below it.
     *
     * @param \stdClass $page
     * @return int the id of the event
     */
    private function enqueue_by_a_process(\stdClass $page): int {
        $actor = $this->spawn_actor([
            'mode' => 'enqueue', 'course' => $page->course, 'lock_timeout' => unit_of_work::DEFAULT_LOCK_TIMEOUT,
            'items' => ['e' => ['type' => 'material_changed', 'payload' => [
                'cmid' => $page->cmid, 'moodle_event' => 'cl51_test', 'occurred_at' => 1000 + ++$this->occurrence,
            ]]],
        ], 'enqueue-' . $page->cmid);
        $answer = $this->finish_actor($actor);
        $this->assertNull($answer['error'], 'The enqueue must not fail: ' . var_export($answer['error'], true));
        $this->assertSame('created', $answer['results']['e']['status'], 'The enqueue must create the event');
        $this->assertLessThan(
            unit_of_work::DEFAULT_LOCK_TIMEOUT,
            $answer['finished_at'] - $answer['go_at'],
            'The enqueue waited as long as the lock timeout'
        );
        return (int) $answer['results']['e']['event'];
    }

    /**
     * The state of an event: its status and the status of each attempt, in order.
     *
     * @param int $eventid
     * @return array [status, attempt statuses]
     */
    private function event_state(int $eventid): array {
        global $DB;
        $status = $DB->get_field('local_assistente_event', 'status', ['id' => $eventid], MUST_EXIST);
        $attempts = $DB->get_fieldset_sql(
            'SELECT a.status FROM {local_assistente_processing_attempt} a JOIN {local_assistente_processing} p ON p.id = a.processing_id
              WHERE p.event_id = ? ORDER BY a.attempt_number ASC',
            [$eventid]
        );
        return [$status, $attempts];
    }

    /**
     * A signal file of this test.
     *
     * @param string $name
     * @return string
     */
    private function file(string $name): string {
        return $this->signal_file($name);
    }

    /**
     * Start a process that runs one cycle of the real queue.
     *
     * @param string[] $llm the steps of the fake LLM: "ok", "fail" or "block"
     * @param string $label
     * @param array $extra
     * @return array the actor
     */
    private function cycle(array $llm, string $label, array $extra = []): array {
        return $this->spawn_actor(['mode' => 'material_cycle', 'llm' => $llm, 'course' => (int) $this->course->id] + $extra, $label);
    }

    /**
     * Start a process that runs one cycle of the real queue and stops inside the call to the LLM.
     *
     * @param string $label
     * @return array the actor, with the file that releases the LLM ('llm_release_file')
     */
    private function blocked_cycle(string $label): array {
        $entered = $this->file('entered');
        $release = $this->file('llm_release');
        $actor = $this->cycle(['block'], $label, ['llm_entered_file' => $entered, 'llm_release_file' => $release]);
        $this->await(fn() => file_exists($entered), "'{$label}' did not reach the call to the LLM");
        $actor['llm_release_file'] = $release;
        return $actor;
    }

    /**
     * Release the LLM of a blocked cycle and give the answer of the cycle.
     *
     * @param array $actor
     * @return array the first result of the cycle
     */
    private function finish_blocked_cycle(array $actor): array {
        file_put_contents($actor['llm_release_file'], 'release');
        return $this->cycle_result($this->finish_actor($actor));
    }

    /**
     * The result of a cycle; a failure of the process or an error that escaped the queue is a failure of the test unless the caller says
     * it expects one (it reads the answer itself).
     *
     * @param array $answer
     * @return array
     */
    private function cycle_result(array $answer): array {
        $this->assertNull($answer['error'], 'The cycle process failed: ' . var_export($answer['error'], true));
        $this->assertArrayHasKey('outcomes', $answer['results'][0], 'The cycle raised: ' . json_encode($answer['results'][0]));
        return $answer['results'][0];
    }

    /**
     * Start the real task in a process.
     *
     * @param string $label
     * @param array $extra
     * @return array the actor
     */
    private function task(string $label, array $extra = []): array {
        return $this->spawn_actor(['mode' => 'reconcile'] + $extra, $label);
    }

    /**
     * The numbers the task wrote for the second step, and what it raised.
     *
     * @param array $answer
     * @return array pending, marked, active, kept, removed, failed, raised
     */
    private function pending_step(array $answer): array {
        $this->assertNull($answer['error'], 'The task process failed: ' . var_export($answer['error'], true));
        $result = $answer['results'][0];
        $this->assertSame(1, preg_match(
            '/(\d+) pending candidates, (\d+) marked failed, (\d+) with an active event, (\d+) kept, (\d+) removed, (\d+) failed/',
            $result['output'],
            $found
        ), 'The task did not write the summary of the second step: ' . $result['output']);
        return [
            'pending' => (int) $found[1], 'marked' => (int) $found[2], 'active' => (int) $found[3], 'kept' => (int) $found[4],
            'removed' => (int) $found[5], 'failed' => (int) $found[6], 'raised' => $result['raised'],
        ];
    }

    /**
     * Items 1 and 6. The completed version of the greatest number that never became current is promoted by the task while two other
     * processes switch the pointer of the same material to other versions: the pointer ends on the greatest number, nothing technical
     * happens, no version row changes and the pointer is never on a version that is not completed.
     */
    public function test_the_promotion_races_with_other_switches_and_the_pointer_never_goes_back(): void {
        for ($run = 1; $run <= self::RUNS; $run++) {
            [$materialid, $versions] = $this->material_with_completed_versions($this->page(), 3);
            $before = $this->versions($materialid);
            $group = $this->spawn_group([
                ['mode' => 'reconcile'],
                ['mode' => 'switch_ops', 'material_id' => $materialid, 'versions' => [$versions[1], $versions[2]]],
                ['mode' => 'switch_ops', 'material_id' => $materialid, 'versions' => [$versions[3], $versions[1]]],
            ], "promotion-{$run}");
            $this->go_group($group);
            $answers = array_values($this->finish_group($group));

            $task = $answers[0];
            $this->assertNull($task['error'], "Run {$run}: " . var_export($task['error'], true));
            $this->assertNull($task['results'][0]['raised'], "Run {$run}: the task raised an exception: " . $task['results'][0]['output']);
            foreach ([$answers[1], $answers[2]] as $worker) {
                $this->assertNull($worker['error'], "Run {$run}: " . var_export($worker['error'], true));
                foreach ($worker['results'] as $outcome) {
                    $this->assertTrue(!empty($outcome['ok']) || !empty($outcome['refused']), "Run {$run}: technical failure: " . json_encode($outcome));
                }
            }
            $this->assertSame(3, (int) $this->current_of($materialid)->version, "Run {$run}: the pointer must end on the greatest number");
            $this->assertEquals($before, $this->versions($materialid), "Run {$run}: no version row may change");
            $this->assert_pointer_invariant($materialid, "run {$run}");
        }
    }

    /**
     * Items 2 and 9. A pending version with an active event is not classified as failed: the worker is stopped inside the LLM, the task
     * runs in another process and sees the event, a second queue process finds the event locked and leaves it (the live lock is not a
     * dead run), and the worker finishes the version.
     */
    public function test_a_pending_version_with_an_active_event_is_not_classified_as_failed(): void {
        for ($run = 1; $run <= self::RUNS; $run++) {
            $page = $this->page();
            $eventid = $this->enqueue_event($page);
            $worker = $this->blocked_cycle("worker-{$run}");
            [$status] = $this->event_state($eventid);
            $this->assertSame('processing', $status, "Run {$run}: the event is being processed");
            $materialid = (int) $this->material_id($page);
            $this->assertSame(['pending'], array_column($this->versions($materialid), 'summary_status'), "Run {$run}: the version is pending");

            $task = $this->task("task-{$run}");
            $second = $this->cycle(['ok'], "second-{$run}");
            $step = $this->pending_step($this->finish_actor($task));
            $this->assertSame(1, $step['active'], "Run {$run}: the task must see the event");
            $this->assertSame(0, $step['marked'], "Run {$run}: the task must not classify the version");
            $this->assertNull($step['raised'], "Run {$run}: the task raised an exception");
            $secondresult = $this->cycle_result($this->finish_actor($second));
            $this->assertSame([$eventid], $secondresult['outcomes']['skipped_locked'], "Run {$run}: the event is held by the first queue");
            $this->assertSame(0, $secondresult['llm_calls'], "Run {$run}: the second queue must not call the LLM");
            $this->assertSame(['pending'], array_column($this->versions($materialid), 'summary_status'), "Run {$run}: still pending");

            $result = $this->finish_blocked_cycle($worker);
            $this->assertSame([$eventid], $result['outcomes']['processed'], "Run {$run}: the event is processed");
            $this->assertSame(1, $result['llm_calls']);
            $versions = $this->versions($materialid);
            $this->assertCount(1, $versions, "Run {$run}: one version");
            $this->assertSame('completed', reset($versions)->summary_status);
            $this->assertSame((int) reset($versions)->id, (int) $this->current_of($materialid)->id);
            $this->assertSame(['processed', ['processed']], $this->event_state($eventid), "Run {$run}: one attempt");
            $this->assert_pointer_invariant($materialid, "run {$run}");
        }
    }

    /**
     * The id of the material registered for a page.
     *
     * @param \stdClass $page
     * @return int
     */
    private function material_id(\stdClass $page): int {
        global $DB;
        return (int) $DB->get_field('local_assistente_material', 'id', ['course_id' => $page->course, 'cmid' => $page->cmid], MUST_EXIST);
    }

    /**
     * Item 3. An abandoned pending version is classified as failed, with two tasks running at the same time (two scheduled tasks are not
     * serialized): exactly one of them marks it, none fails, and the version is failed afterwards with its summary untouched.
     */
    public function test_an_abandoned_version_is_classified_once_by_two_tasks_at_the_same_time(): void {
        for ($run = 1; $run <= self::RUNS; $run++) {
            [$materialid, $versionid] = $this->material_with_pending_version($this->page());
            $group = $this->spawn_group([['mode' => 'reconcile'], ['mode' => 'reconcile']], "classification-{$run}");
            $this->go_group($group);
            $steps = array_map(fn(array $answer) => $this->pending_step($answer), array_values($this->finish_group($group)));

            $this->assertSame(1, $steps[0]['marked'] + $steps[1]['marked'], "Run {$run}: exactly one task marks the version");
            $this->assertSame(0, $steps[0]['failed'] + $steps[1]['failed'], "Run {$run}: no technical failure");
            $this->assertNull($steps[0]['raised'], "Run {$run}: no exception");
            $this->assertNull($steps[1]['raised'], "Run {$run}: no exception");
            $version = $this->versions($materialid)[$versionid];
            $this->assertSame('failed', $version->summary_status, "Run {$run}: the version is failed");
            $this->assertNull($version->summary, "Run {$run}: the summary is untouched");
            $this->assertNull($this->current_of($materialid), "Run {$run}: the pointer is untouched");
        }
    }

    /**
     * Item 7, the arrival of the event in the gap the task leaves. The task reads the events (none), is stopped, an event arrives by the
     * enqueue of another process, a queue process takes it and stops inside the LLM with the version pending; the task goes on and, asking
     * again inside the lock of the material, sees the event and leaves the version. The event is not lost: it is processed in its first
     * attempt, with the one version and one call to the LLM.
     */
    public function test_an_event_that_arrives_after_the_first_reading_keeps_the_version(): void {
        for ($run = 1; $run <= self::RUNS; $run++) {
            $page = $this->page();
            [$materialid, $versionid] = $this->material_with_pending_version($page);
            $paused = $this->file('paused');
            $release = $this->file('task_release');
            $task = $this->task("task-{$run}", ['pause_file' => $paused, 'release_file' => $release]);
            $this->await(fn() => file_exists($paused), "'task-{$run}' did not stop after its reading of the events");

            $eventid = $this->enqueue_by_a_process($page);
            $worker = $this->blocked_cycle("worker-{$run}");
            $this->assertSame('pending', $this->versions($materialid)[$versionid]->summary_status, "Run {$run}: the worker holds a pending version");
            file_put_contents($release, 'release');
            $step = $this->pending_step($this->finish_actor($task));

            $this->assertSame(0, $step['active'], "Run {$run}: the first reading did not see the event");
            $this->assertSame(0, $step['marked'], "Run {$run}: the version must be kept");
            $this->assertSame(1, $step['kept'], "Run {$run}: the second reading, inside the lock, kept the version");
            $this->assertNull($step['raised']);
            $this->assertSame('pending', $this->versions($materialid)[$versionid]->summary_status);

            $result = $this->finish_blocked_cycle($worker);
            $this->assertSame([$eventid], $result['outcomes']['processed'], "Run {$run}: the event is processed");
            $this->assertSame(1, $result['llm_calls']);
            $this->assertSame(['processed', ['processed']], $this->event_state($eventid), "Run {$run}: first attempt");
            $versions = $this->versions($materialid);
            $this->assertSame([$versionid], array_keys($versions), "Run {$run}: the same version, no duplicate");
            $this->assertSame('completed', $versions[$versionid]->summary_status);
            $this->assertSame($versionid, (int) $this->current_of($materialid)->id);
            $this->assert_pointer_invariant($materialid, "run {$run}");
        }
    }

    /**
     * Item 7, when the classification is made on stale events (the second reading did not exist, or the event arrived between it and the
     * change). A version a worker holds as pending is marked failed from under it, by a process that does not ask; the worker finishes
     * the same attempt: the version goes failed, pending, completed, the event is processed in its first attempt, the LLM was called once
     * and no version is created. Without this, the attempt would fail and one attempt of three would be spent.
     */
    public function test_a_version_failed_under_a_worker_is_finished_by_the_same_attempt(): void {
        for ($run = 1; $run <= self::RUNS; $run++) {
            $page = $this->page();
            [$materialid, $versionid] = $this->material_with_pending_version($page);
            $eventid = $this->enqueue_by_a_process($page);
            $worker = $this->blocked_cycle("worker-{$run}");
            $this->assertSame('pending', $this->versions($materialid)[$versionid]->summary_status);

            $stale = $this->spawn_actor(['mode' => 'fail_pending', 'version_id' => $versionid], "stale-{$run}");
            $answer = $this->finish_actor($stale);
            $this->assertNull($answer['error'], 'fail_pending: ' . var_export($answer['error'], true));
            $this->assertTrue($answer['results'][0]['marked'], "Run {$run}: the version was taken from under the worker");
            $this->assertSame('failed', $this->versions($materialid)[$versionid]->summary_status);

            $result = $this->finish_blocked_cycle($worker);
            $this->assertSame([$eventid], $result['outcomes']['processed'], "Run {$run}: the event is not lost");
            $this->assertSame([], $result['outcomes']['retry_later'], "Run {$run}: the attempt did not fail");
            $this->assertSame(1, $result['llm_calls'], "Run {$run}: the LLM was called once");
            $this->assertSame(['processed', ['processed']], $this->event_state($eventid), "Run {$run}: the attempt was not spent");
            $versions = $this->versions($materialid);
            $this->assertSame([$versionid], array_keys($versions), "Run {$run}: no duplicate version");
            $this->assertSame('completed', $versions[$versionid]->summary_status);
            $this->assertNotNull($versions[$versionid]->summary);
            $this->assertSame($versionid, (int) $this->current_of($materialid)->id);
            $this->assert_pointer_invariant($materialid, "run {$run}");
        }
    }

    /**
     * Items 4 and 5. The real exhaustion of the three attempts, with two queue processes at the same time on the same event: the event
     * ends in failure with exactly three failed attempts (none duplicated, none beyond the limit) and the version ends failed, alone, with
     * no summary and no pointer. The task leaves it as it is. A new event of the same content takes up the same version (no duplicate),
     * completes it and makes it current; the first event stays failed, with its history.
     */
    public function test_the_exhaustion_is_consistent_and_a_new_event_takes_up_the_same_version(): void {
        for ($run = 1; $run <= self::RUNS; $run++) {
            $page = $this->page();
            $first = $this->enqueue_event($page);
            $calls = 0;
            for ($cycle = 1; $cycle <= 3 && $this->event_state($first)[0] !== 'failure'; $cycle++) {
                $group = $this->spawn_group([
                    ['mode' => 'material_cycle', 'llm' => ['fail'], 'course' => (int) $this->course->id],
                    ['mode' => 'material_cycle', 'llm' => ['fail'], 'course' => (int) $this->course->id],
                ], "exhaustion-{$run}-{$cycle}");
                $this->go_group($group);
                foreach ($this->finish_group($group) as $answer) {
                    $calls += $this->cycle_result($answer)['llm_calls'];
                }
            }
            $this->assertSame(['failure', ['failure', 'failure', 'failure']], $this->event_state($first), "Run {$run}: three failed attempts");
            $this->assertSame(3, $calls, "Run {$run}: one call to the LLM per attempt, none duplicated");
            $materialid = $this->material_id($page);
            $versions = $this->versions($materialid);
            $this->assertCount(1, $versions, "Run {$run}: one version");
            $version = reset($versions);
            $this->assertSame('failed', $version->summary_status);
            $this->assertNull($version->summary);
            $this->assertNull($this->current_of($materialid), "Run {$run}: no pointer");

            $task = $this->pending_step($this->finish_actor($this->task("task-{$run}")));
            $this->assertSame(0, $task['pending'], "Run {$run}: a failed version is not a candidate");
            $this->assertNull($task['raised']);
            $this->assertEquals($versions, $this->versions($materialid), "Run {$run}: the task changed nothing");

            $second = $this->enqueue_by_a_process($page);
            $result = $this->cycle_result($this->finish_actor($this->cycle(['ok'], "recovery-{$run}")));
            $this->assertSame([$second], $result['outcomes']['processed'], "Run {$run}: the new event is processed");
            $this->assertSame(1, $result['llm_calls']);
            $after = $this->versions($materialid);
            $this->assertSame(array_keys($versions), array_keys($after), "Run {$run}: the same version, none created");
            $this->assertSame('completed', reset($after)->summary_status);
            $this->assertSame((int) $version->id, (int) $this->current_of($materialid)->id);
            $this->assertSame(['processed', ['processed']], $this->event_state($second));
            $this->assertSame(['failure', ['failure', 'failure', 'failure']], $this->event_state($first), "Run {$run}: the history of the first event");
            $this->assert_pointer_invariant($materialid, "run {$run}");
        }
    }

    /**
     * Item 8 (CL-44). The result of the attempt cannot be saved (a trigger fails the write): the cycle ends with an error that nobody
     * hides, the event stays in processing with its attempt open, the lock of the event is free in the server although the process is
     * gone, and the version is already completed and current. With the failure gone, the next cycle of another process takes the event
     * up: the open attempt is closed as interrupted and counted, the handler runs again, finds nothing to do (idempotent), and the event
     * is processed with no second call to the LLM and no second version.
     */
    public function test_a_failure_to_save_the_result_is_recovered_by_the_next_cycle(): void {
        global $DB;
        for ($run = 1; $run <= self::RUNS; $run++) {
            $page = $this->page();
            $eventid = $this->enqueue_event($page);
            $name = self::TRIGGER_PREFIX . 'save_' . $run;
            $this->assertFalse($DB->is_transaction_started(), 'DDL commits implicitly: no transaction may be open');
            $DB->execute(
                "CREATE TRIGGER {$name} BEFORE UPDATE ON " . $DB->get_prefix() . "local_assistente_processing_attempt FOR EACH ROW "
                . "SET NEW.status = IF(NEW.status = 'processed', (SELECT 1 UNION ALL SELECT 2), NEW.status)"
            );
            try {
                $answer = $this->finish_actor($this->cycle(['ok'], "save-{$run}"));
            } finally {
                $DB->execute("DROP TRIGGER IF EXISTS {$name}");
            }
            $this->assertNull($answer['error']);
            $this->assertArrayHasKey('cycle_error', $answer['results'][0], "Run {$run}: the failure must not be hidden: " . json_encode($answer['results'][0]));
            $this->assertSame(1, $answer['results'][0]['llm_calls']);
            $this->assertSame(['processing', ['processing']], $this->event_state($eventid), "Run {$run}: the event and the attempt stay open");
            $this->await(fn() => $this->lock_owner(unit_of_work::event_resource($eventid)) === null, 'the lock of the event was not freed');
            $materialid = $this->material_id($page);
            $versions = $this->versions($materialid);
            $this->assertCount(1, $versions);
            $this->assertSame('completed', reset($versions)->summary_status, "Run {$run}: the handler had persisted its result");
            $this->assertSame((int) reset($versions)->id, (int) $this->current_of($materialid)->id);

            $result = $this->cycle_result($this->finish_actor($this->cycle(['ok'], "recover-{$run}")));
            $this->assertSame([$eventid], $result['outcomes']['processed'], "Run {$run}: the next cycle recovers the event");
            $this->assertSame(0, $result['llm_calls'], "Run {$run}: no second call to the LLM");
            $this->assertSame(['processed', ['failure', 'processed']], $this->event_state($eventid), "Run {$run}: the open attempt was closed and counted");
            $this->assertEquals($versions, $this->versions($materialid), "Run {$run}: no version row changed");
            $this->assertSame([], $this->leftover_triggers());
            $this->assert_pointer_invariant($materialid, "run {$run}");
        }
    }

    /**
     * Item 9, the locks and timeouts. (a) A lock of a material held by another process: the task waits for the timeout it was given (2
     * seconds here, the only shortened one), counts the material as a failure, goes on with the others, raises an exception at the end
     * and takes the material up in the next run. (b) The lock of an event held by another process: the queue leaves the event after
     * the 1 second of its event lock, without calling the LLM, and processes it when the lock is free.
     */
    public function test_the_task_and_the_queue_respect_the_locks_and_the_timeouts(): void {
        for ($run = 1; $run <= self::RUNS; $run++) {
            [$blockedid, $blockedversions] = $this->material_with_completed_versions($this->page(), 1);
            [$freeid, $freeversions] = $this->material_with_completed_versions($this->page(), 1);
            $holder = $this->hold_resource(unit_of_work::material_resource($blockedid), "holder-{$run}");
            $answer = $this->finish_actor($this->task("task-{$run}", ['lock_timeout' => 2]));
            $this->assertNull($answer['error']);
            $this->assertSame('RuntimeException', $answer['results'][0]['raised'], "Run {$run}: the failure is raised at the end");
            $this->assertStringContainsString('1 promoted', $answer['results'][0]['output']);
            $this->assertStringContainsString('1 failed', $answer['results'][0]['output']);
            $waited = $answer['ended_at'] - $answer['started_at'];
            $this->assertGreaterThan(1.5, $waited, "Run {$run}: the task did not wait for the lock");
            $this->assertLessThan(unit_of_work::DEFAULT_LOCK_TIMEOUT, $waited, "Run {$run}: the task waited past the timeout");
            $this->assertNull($this->current_of($blockedid), "Run {$run}: the blocked material is untouched");
            $this->assertSame((int) $freeversions[1], (int) $this->current_of($freeid)->id, "Run {$run}: the other material is promoted");
            $this->release_actor($holder);
            $this->finish_actor($holder);
            $again = $this->finish_actor($this->task("again-{$run}"));
            $this->assertNull($again['results'][0]['raised'], "Run {$run}: the next run is clean");
            $this->assertSame((int) $blockedversions[1], (int) $this->current_of($blockedid)->id, "Run {$run}: taken up by the next run");

            $page = $this->page();
            $eventid = $this->enqueue_event($page);
            $eventholder = $this->hold_resource(unit_of_work::event_resource($eventid), "event-holder-{$run}");
            $skipped = $this->finish_actor($this->cycle(['ok'], "skipped-{$run}"));
            $result = $this->cycle_result($skipped);
            $this->assertSame([$eventid], $result['outcomes']['skipped_locked'], "Run {$run}: the held event is left");
            $this->assertSame(0, $result['llm_calls']);
            $this->assertLessThan(unit_of_work::DEFAULT_LOCK_TIMEOUT, $skipped['ended_at'] - $skipped['started_at']);
            $this->assertSame(['pending', []], $this->event_state($eventid));
            $this->release_actor($eventholder);
            $this->finish_actor($eventholder);
            $done = $this->cycle_result($this->finish_actor($this->cycle(['ok'], "done-{$run}")));
            $this->assertSame([$eventid], $done['outcomes']['processed'], "Run {$run}: processed when the lock is free");
        }
    }
}
