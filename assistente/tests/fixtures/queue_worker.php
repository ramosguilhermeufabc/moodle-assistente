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

/**
 * Worker process of the concurrency tests of the queue (event_service_concurrency_test).
 *
 * It is not a test and not a page of the plugin. The test starts it as a separate PHP process, so it has its own connection to the
 * database and its own locks, which is what two requests of a real site have. It starts Moodle in the environment of PHPUnit (the
 * database and data root of the tests, never the ones of the site), waits for a start instant so that the processes begin together,
 * and does one of these things:
 *
 * - "enqueue": enqueues occurrences, one by one or in batches, and prints what the queue answered for each;
 * - "hold": takes the given locks, says it holds them (the ready file holds the connection of the process), and keeps them until the
 *   release file appears, or for the given seconds at most, and releases them;
 * - "acquire": waits for the lock of a resource and says whether it got it;
 * - "hold_row": opens a transaction, locks the row of an event with SELECT ... FOR UPDATE, says so, and rolls back when the release file appears;
 * - "task": runs the redelivery task with the given identifier, as Moodle would (it takes the lock of the task first), and prints its output;
 * - "erase_course": the real erasure of the data of a course; "erase_student": the real erasure of the data of a student;
 * - "material_ops": rounds of operations of the material repository ("add_version", "cycle", or "add_version_nolock", a control with no lock;
 *   the control can have a barrier in its first round, between the reading of the maximum and the insert: "barrier_arrive_file" (written
 *   when the worker has calculated its number), "barrier_release_file" (the test writes it when every worker has arrived) and
 *   "barrier_timeout");
 * - "switch_ops": switch_current() of the material repository for the given versions, in order, with an optional wait for a signal file (T144; M-12, M-13);
 * - "material_cycle": one cycle of the real queue with the real handler and service of the materials and a scripted fake LLM ("ok", "fail", or
 *   "block", which waits inside the call for a release file) (CL-51);
 * - "reconcile": the real reconciliation task of the material versions, optionally stopped after its first reading of the events (CL-51);
 * - "fail_pending": fail_pending_version() with no guard, as a classification on stale events would do (CL-51);
 * - "report_ops": rounds of add_version of the report repository, with a valid schedule;
 * - "run_unit": a unit of work under the lock of a resource, committed or rolled back (CL-07, M-06); it can wait inside the unit and stays
 *   alive afterwards, until the release file, so that the test sees the lock while the connection that held it still exists;
 * - "write_and_hold": a write of the material repository inside a unit of work that is not confirmed, then a wait inside the unit (CL-07,
 *   M-09 and M-10); the test may end the process or its connection meanwhile.
 *
 * A group of workers starts together with the ready/go protocol: each worker writes its marker when Moodle has started and the connection is
 * known (ready), and waits for the "go_file"; the test writes it when every worker is ready (see lock_observer::spawn_group()). A worker
 * with a "done_file" writes its answer there when the work is done, so that the test knows without waiting for the process to end.
 *
 * When the specification has a "marker" file, the process writes its database connection there before it waits for the start instant, so
 * that the test that started it can tell the connection apart from the others in the lists of the server.
 *
 * The specification comes as the one argument, a JSON object. The answer is printed after the line "@@RESULT@@".
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$spec = json_decode($argv[1] ?? '', true);
if (!is_array($spec)) {
    fwrite(STDERR, "Missing specification.\n");
    exit(2);
}
require($spec['autoload']);
// The bootstrap of PHPUnit takes the lock of the test run and resets the database, which would take the data of the test that started
// this process. As a utility run (PHPUNIT_UTIL) it does neither, and still points at the database and data root of the tests.
define('PHPUNIT_UTIL', true);
require($spec['bootstrap']);

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\application\material\material_event_handler;
use local_assistente\application\material\material_service;
use local_assistente\application\queue\cycle_outcome;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\domain\llm\llm_context;
use local_assistente\domain\material\material_availability;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\domain\report\report_schedule;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\report_repository;
use local_assistente\infrastructure\persistence\table_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use local_assistente\infrastructure\persistence\version_not_newer_exception;
use local_assistente\task\reconcile_material_versions;

/**
 * Wait for a file to appear, at most some seconds. A safety limit for the helpers, never a way of sequencing the test.
 *
 * @param string $file
 * @param float $seconds
 */
function wait_for_file(string $file, float $seconds): void {
    $deadline = microtime(true) + $seconds;
    while (!file_exists($file) && microtime(true) < $deadline) {
        usleep(50000);
    }
}

/**
 * The "go" of a group of workers: wait for the file to appear, then for the instant it holds. The workers of a group are all ready (Moodle
 * started, connection known, marker written) before the test writes the file, so the instant only covers the time the file takes to be
 * seen by all of them, a few milliseconds; it is not a wait for the workers to be ready.
 *
 * @param string $file
 * @param float $seconds the longest wait for the file
 * @return bool false when the file did not appear
 */
function wait_for_go(string $file, float $seconds): bool {
    $deadline = microtime(true) + $seconds;
    while (!file_exists($file)) {
        if (microtime(true) >= $deadline) {
            return false;
        }
        usleep(1000);
    }
    $at = (float) @file_get_contents($file);
    while ($at > 0 && microtime(true) < $at) {
        usleep(100);
    }
    return true;
}

/**
 * Wait for a signal file, at most some seconds, and say whether it came. The barrier of a group uses it: a signal that does not come is
 * reported, never presumed.
 *
 * @param string $file
 * @param float $seconds
 * @return bool false when the file did not appear in time
 */
function wait_for_signal(string $file, float $seconds): bool {
    $deadline = microtime(true) + $seconds;
    while (!file_exists($file)) {
        if (microtime(true) >= $deadline) {
            return false;
        }
        usleep(1000);
    }
    return true;
}

$connection = (int) $DB->get_field_sql('SELECT CONNECTION_ID()');
if (!empty($spec['marker'])) {
    // The marker is also the "ready" of the ready/go protocol: it is written after Moodle started and the connection is known.
    file_put_contents($spec['marker'], json_encode(['connection' => $connection]));
}

while (microtime(true) < (float) $spec['start']) {
    usleep(500);
}
$goreceived = empty($spec['go_file']) || wait_for_go($spec['go_file'], (float) ($spec['go_timeout'] ?? 120));

$answer = ['label' => $spec['label'], 'results' => [], 'error' => null, 'connection' => $connection, 'go_at' => microtime(true)];
try {
    if (!$goreceived) {
        throw new RuntimeException('The go signal did not arrive.');
    }
    if ($spec['mode'] === 'hold') {
        $factory = unit_of_work::lock_factory();
        $locks = [];
        foreach ($spec['resources'] as $resource) {
            $lock = $factory->get_lock($resource, 5);
            if (!$lock) {
                throw new RuntimeException("Could not take the lock {$resource}.");
            }
            $locks[] = $lock;
        }
        file_put_contents($spec['ready_file'], json_encode(['connection' => $connection]));
        if (!empty($spec['release_file'])) {
            wait_for_file($spec['release_file'], (float) $spec['seconds']);
        } else {
            usleep((int) ($spec['seconds'] * 1000000));
        }
        foreach ($locks as $lock) {
            $lock->release();
        }
    } else if ($spec['mode'] === 'acquire') {
        $lock = unit_of_work::lock_factory()->get_lock($spec['resource'], (int) $spec['timeout']);
        $answer['results'][] = ['locked' => (bool) $lock];
        $lock && $lock->release();
    } else if ($spec['mode'] === 'hold_row') {
        $transaction = $DB->start_delegated_transaction();
        $DB->get_record_sql('SELECT id FROM {local_assistente_event} WHERE id = ? FOR UPDATE', [(int) $spec['event_id']]);
        file_put_contents($spec['ready_file'], json_encode(['connection' => $connection]));
        wait_for_file($spec['release_file'], (float) $spec['seconds']);
        try {
            $transaction->rollback(new \Exception('end of the hold'));
        } catch (\Exception) {
            // The rollback rethrows what it is given.
        }
    } else if ($spec['mode'] === 'erase_course') {
        $deleted = (new \local_assistente\application\privacy\course_data_eraser())->erase((int) $spec['course']);
        $answer['results'][] = ['rows' => array_sum($deleted)];
    } else if ($spec['mode'] === 'erase_student') {
        $erased = (new \local_assistente\application\privacy\student_data_eraser())->erase((int) $spec['userid']);
        $answer['results'][] = ['events' => $erased['events']];
    } else if ($spec['mode'] === 'material_ops') {
        // Operations of the material repository, round after round: "add_version" (lock and transaction), "cycle" (add_version,
        // complete_summary and switch_current) and "add_version_nolock" (max + 1 read and inserted with no lock, a control: only the
        // unique index protects it).
        $repository = new material_repository(new unit_of_work((int) ($spec['lock_timeout'] ?? 30)));
        $materialid = (int) $spec['material_id'];
        $answer['started_at'] = microtime(true);
        for ($round = 1; $round <= (int) $spec['rounds']; $round++) {
            $hash = substr($spec['prefix'] . '_' . $round, 0, 64);
            $outcome = ['round' => $round, 'hash' => $hash];
            try {
                if ($spec['op'] === 'add_version_nolock') {
                    $next = 1 + (int) $DB->get_field_select('local_assistente_material_version', 'MAX(version)', 'material_id = ?', [$materialid]);
                    $outcome['next'] = $next;
                    if ($round === 1 && !empty($spec['barrier_arrive_file'])) {
                        // Barrier between the reading of the maximum and the insert (first round only): say that the number was
                        // calculated, and insert only when the test, which waits for every worker of the group, releases the barrier.
                        // So every worker has read the same state before any of them inserts.
                        $arrived = $spec['barrier_arrive_file'] . '.tmp';
                        file_put_contents($arrived, json_encode(['label' => $spec['label'], 'next' => $next, 'connection' => $connection]));
                        rename($arrived, $spec['barrier_arrive_file']);
                        if (!wait_for_signal($spec['barrier_release_file'], (float) ($spec['barrier_timeout'] ?? 90))) {
                            throw new RuntimeException('The barrier was not released within ' . ($spec['barrier_timeout'] ?? 90) . 's.');
                        }
                    }
                    $id = (new table_repository('local_assistente_material_version'))->insert((object) [
                        'material_id' => $materialid, 'version' => $next, 'content_hash' => $hash, 'summary' => null,
                        'summary_status' => 'pending', 'availability' => 'available', 'created_at' => time(),
                    ]);
                } else {
                    $id = $repository->add_version($materialid, $hash, material_availability::AVAILABLE);
                    if ($spec['op'] === 'cycle') {
                        $repository->complete_summary($id, 'resumo ' . $hash);
                        try {
                            $repository->switch_current($materialid, $id);
                        } catch (version_not_newer_exception) {
                            // DEC-62: another worker made a greater version current meanwhile. A valid outcome, not a failure.
                            $outcome['switch_refused'] = true;
                        }
                    }
                }
                $outcome += ['ok' => true, 'version_id' => $id];
            } catch (Throwable $e) {
                $outcome += ['ok' => false, 'class' => get_class($e), 'message' => substr($e->getMessage(), 0, 200)];
            }
            $answer['results'][] = $outcome;
        }
        $answer['ended_at'] = microtime(true);
    } else if ($spec['mode'] === 'report_ops') {
        // add_version of the report repository, round after round, with a valid schedule.
        $repository = new report_repository(new unit_of_work((int) ($spec['lock_timeout'] ?? 30)));
        $answer['started_at'] = microtime(true);
        for ($round = 1; $round <= (int) $spec['rounds']; $round++) {
            $outcome = ['round' => $round];
            try {
                $now = time();
                $schedule = report_schedule::calculate([$now + 5 * 86400], $now, 'America/Sao_Paulo', null, $now);
                $manifest = json_encode(['worker' => $spec['prefix'], 'round' => $round]);
                $version = $repository->add_version((int) $spec['report_id'], $manifest, $schedule, $now);
                $outcome += ['ok' => true, 'version_id' => (int) $version->id, 'version' => (int) $version->version];
            } catch (Throwable $e) {
                $outcome += ['ok' => false, 'class' => get_class($e), 'message' => substr($e->getMessage(), 0, 200)];
            }
            $answer['results'][] = $outcome;
        }
        $answer['ended_at'] = microtime(true);
    } else if ($spec['mode'] === 'run_unit') {
        // A unit of work under the lock of a resource, committed or rolled back. With an "inside_file" the work waits, inside the unit, for
        // the "inside_release_file"; the process then stays alive (its connection too) until the "release_file", so that the test can see
        // whether the lock is free while the connection that held it still exists.
        $uow = new unit_of_work((int) ($spec['lock_timeout'] ?? 30));
        try {
            $uow->run(function () use ($spec, $connection, $DB) {
                if (isset($spec['cmid'])) {
                    $DB->insert_record('local_assistente_material', (object) [
                        'course_id' => 2, 'section_id' => 10, 'cmid' => (int) $spec['cmid'], 'material_type' => 'pdf', 'name' => 'run_unit',
                        'availability' => 'available', 'current_version' => null, 'created_at' => time(),
                    ]);
                }
                if (!empty($spec['inside_file'])) {
                    file_put_contents($spec['inside_file'], json_encode(['connection' => $connection]));
                    wait_for_file($spec['inside_release_file'], (float) $spec['seconds']);
                }
                if (($spec['outcome'] ?? 'commit') === 'rollback') {
                    throw new RuntimeException('forced rollback');
                }
            }, [$spec['resource']]);
            $answer['results'][] = ['unit' => 'committed'];
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'forced rollback') {
                throw $e;
            }
            $answer['results'][] = ['unit' => 'rolled_back'];
        }
        file_put_contents($spec['ready_file'], json_encode(['connection' => $connection]));
        if (!empty($spec['release_file'])) {
            wait_for_file($spec['release_file'], (float) $spec['seconds']);
        }
    } else if ($spec['mode'] === 'write_and_hold') {
        // A write inside a unit of work (lock and transaction of the material) that is not confirmed: the process says so, with the version
        // it wrote, and waits for the "release_file" inside the unit. With "after" = "touch_db" it then reads from the database, which fails
        // when the connection was ended by the server meanwhile. The test may also end the process while it waits.
        $uow = new unit_of_work((int) ($spec['lock_timeout'] ?? 30));
        $repository = new material_repository($uow);
        $materialid = (int) $spec['material_id'];
        $uow->run(function () use ($spec, $connection, $DB, $repository, $materialid) {
            $id = $repository->add_version($materialid, $spec['hash'], material_availability::AVAILABLE);
            $version = (int) $DB->get_field('local_assistente_material_version', 'version', ['id' => $id]);
            file_put_contents($spec['ready_file'], json_encode(['connection' => $connection, 'version_id' => $id, 'version' => $version]));
            wait_for_file($spec['release_file'], (float) $spec['seconds']);
            if (($spec['after'] ?? 'stop') === 'touch_db') {
                $DB->get_field_sql('SELECT 1');
            }
        }, [unit_of_work::material_resource($materialid)]);
        $answer['results'][] = ['unit' => 'committed'];
    } else if ($spec['mode'] === 'switch_ops') {
        // switch_current() of the material repository for each of the given versions, in order (T144; M-12, M-13). With a "wait_file" the
        // worker waits for it after the go, so that the test fixes the order of arrival; the refusal by the monotonicity rule is told apart
        // from any other failure.
        if (!empty($spec['wait_file']) && !wait_for_signal($spec['wait_file'], (float) ($spec['wait_timeout'] ?? 90))) {
            throw new RuntimeException('The wait signal did not arrive.');
        }
        $repository = new material_repository(new unit_of_work((int) ($spec['lock_timeout'] ?? 30)));
        $answer['started_at'] = microtime(true);
        foreach ($spec['versions'] as $versionid) {
            $outcome = ['version_id' => (int) $versionid];
            try {
                $repository->switch_current((int) $spec['material_id'], (int) $versionid);
                $outcome += ['ok' => true];
            } catch (version_not_newer_exception $e) {
                $outcome += ['ok' => false, 'refused' => true, 'is_current' => $e->is_current(), 'current_id' => $e->currentid];
            } catch (Throwable $e) {
                $outcome += ['ok' => false, 'refused' => false, 'class' => get_class($e), 'message' => substr($e->getMessage(), 0, 200)];
            }
            $answer['results'][] = $outcome;
        }
        $answer['ended_at'] = microtime(true);
    } else if ($spec['mode'] === 'material_cycle') {
        // One cycle of the real queue (queue_processor) with the real handler and service of the materials, on the real page of the course and
        // with the fake LLM scripted by "llm": "ok", "fail" (a timeout of the port) or "block" (the process writes "llm_entered_file" and waits
        // for "llm_release_file" inside the call to the LLM, with no transaction open). Cycle errors that escape the queue (a failure to save the
        // result, CL-44) are told apart in the answer. The timeouts are the defaults of the project.
        $llm = new fake_adapter();
        foreach ($spec['llm'] ?? [] as $step) {
            if ($step === 'fail') {
                $llm->fail_with('timeout');
            } else if ($step === 'block') {
                $llm->respond_with_callback(static function (llm_context $context) use ($spec): array {
                    file_put_contents($spec['llm_entered_file'], json_encode(['at' => microtime(true)]));
                    if (!wait_for_signal($spec['llm_release_file'], (float) ($spec['llm_timeout'] ?? 90))) {
                        throw new RuntimeException('The release of the LLM did not arrive.');
                    }
                    return ['use_case' => $context->usecase->value, 'status' => 'valid', 'data' => ['summary' => 'Resumo do processo']];
                });
            } else {
                $llm->respond_valid(['summary' => 'Resumo do processo']);
            }
        }
        $registry = new handler_registry();
        $registry->register(event_type::MATERIAL_CHANGED, new material_event_handler(new material_service(null, null, $llm)));
        $answer['started_at'] = microtime(true);
        try {
            $report = (new queue_processor(null, $registry))->run_cycle();
            $outcomes = [];
            foreach (cycle_outcome::cases() as $outcome) {
                $outcomes[$outcome->value] = $report->events($outcome);
            }
            $answer['results'][] = ['outcomes' => $outcomes, 'llm_calls' => $llm->calls()];
        } catch (Throwable $e) {
            $answer['results'][] = ['cycle_error' => get_class($e), 'message' => substr($e->getMessage(), 0, 200), 'llm_calls' => $llm->calls()];
        }
        $answer['ended_at'] = microtime(true);
    } else if ($spec['mode'] === 'reconcile') {
        // The real scheduled task of the reconciliation of the versions. With "pause_file" it stops after the first reading of the events
        // (the one that decides which candidates are looked at) and waits for "release_file", so the test puts an event in that gap. The
        // output of the task and the exception it raises are returned.
        $task = new class ($spec) extends reconcile_material_versions {
            private int $reads = 0;

            public function __construct(private array $options) {
            }

            protected function repository(): material_repository {
                return new material_repository(new unit_of_work((int) ($this->options['lock_timeout'] ?? unit_of_work::DEFAULT_LOCK_TIMEOUT)));
            }

            protected function modules_with_active_events(): array {
                $modules = parent::modules_with_active_events();
                if ($this->reads++ === 0 && !empty($this->options['pause_file'])) {
                    file_put_contents($this->options['pause_file'], json_encode(['at' => microtime(true)]));
                    if (!wait_for_signal($this->options['release_file'], (float) ($this->options['pause_timeout'] ?? 90))) {
                        throw new RuntimeException('The release of the task did not arrive.');
                    }
                }
                return $modules;
            }
        };
        $answer['started_at'] = microtime(true);
        $raised = null;
        ob_start();
        try {
            $task->execute();
        } catch (Throwable $e) {
            $raised = get_class($e);
        } finally {
            $output = ob_get_clean();
        }
        $answer['results'][] = ['output' => $output, 'raised' => $raised];
        $answer['ended_at'] = microtime(true);
    } else if ($spec['mode'] === 'fail_pending') {
        // fail_pending_version() with no guard, as the classification does when the events it read are stale: the version is marked failed
        // whatever the events say, so the test sees what the worker does with a version taken from under it.
        $repository = new material_repository(new unit_of_work((int) ($spec['lock_timeout'] ?? 30)));
        $answer['results'][] = ['marked' => $repository->fail_pending_version((int) $spec['version_id'])];
    } else if ($spec['mode'] === 'task') {
        $task = \core\task\manager::get_adhoc_task((int) $spec['task_id']);
        if ($task === null) {
            $answer['results'][] = ['status' => 'busy'];
        } else {
            $answer['started_at'] = microtime(true);
            ob_start();
            try {
                $task->execute();
            } finally {
                $output = ob_get_clean();
                $task->get_lock()->release();
            }
            $answer['results'][] = ['status' => 'executed', 'output' => $output];
        }
    } else {
        $service = new event_service(null, null, new unit_of_work((int) ($spec['lock_timeout'] ?? 30)));
        $occurrences = [];
        foreach ($spec['items'] as $key => $item) {
            $occurrences[$key] = ['type' => event_type::from($item['type']), 'payload' => $item['payload']];
        }
        $describe = static fn(enqueue_result $r): array => ['status' => $r->status->value, 'event' => $r->event?->id === null ? null : (int) $r->event->id];
        if (!empty($spec['batch'])) {
            foreach ($service->enqueue_many((int) $spec['course'], $occurrences, null, (int) ($spec['chunk'] ?? 250)) as $key => $result) {
                $answer['results'][$key] = $describe($result);
            }
        } else {
            foreach ($occurrences as $key => $occurrence) {
                $answer['results'][$key] = $describe($service->enqueue((int) $spec['course'], $occurrence['type'], $occurrence['payload']));
            }
        }
    }
} catch (Throwable $e) {
    $answer['error'] = get_class($e) . ': ' . $e->getMessage();
}
$answer['finished_at'] = microtime(true);
if (!empty($spec['done_file'])) {
    // The test may need to know that the work is done without waiting for the process to end.
    file_put_contents($spec['done_file'], json_encode($answer));
}
echo "\n@@RESULT@@" . json_encode($answer) . "\n";
