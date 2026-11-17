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

namespace local_assistente\tests\fixtures;

use local_assistente\infrastructure\persistence\unit_of_work;

require_once(__DIR__ . '/recording_lock_factory.php');

/**
 * Start actors as independent PHP processes and observe their locks from the test, for the tests of the disputes between the retry task and the
 * erasures (redelivery of an event).
 *
 * What is observed, and what is not. Two signals of the server, together and correlated by key and by connection:
 * - the owner: IS_USED_LOCK(key) is the connection of the process that was asked to hold the lock;
 * - the wait: the connection of the other process is in the list of the server in the state "User lock", running GET_LOCK of that same key.
 * Both were checked on MariaDB 11.4.13. The state text is not assumed for any other server: the first test of a run starts a known holder and a
 * known waiter and, if the two signals are not seen, every test that needs them is skipped with the reason. A skipped test is no proof.
 * Once the signals are known to work, a signal that a test waits for and does not see is a failure ("SINAL NÃO OBSERVADO"), never a pass.
 * The waiting is a poll with a limit; the limit is not a way of sequencing the actors: each actor is started only after the previous signal
 * was seen, and the actors that keep a lock are released by the test, by a file, only after the signals were seen.
 * Not observed, and never claimed: that a process is blocked by a row of the database (the list of the server only says "Updating"), and the
 * internal state of any transaction.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait lock_observer {
    /** Longest wait, in seconds, of finish_actor() when the test gives no limit. */
    private const ACTOR_FINISH_SECONDS = 120.0;

    /** Longest wait, in seconds, for a worker that is alive to say it is ready (marker), or the environment variable of the same name. A worker that dies is detected at once. */
    private const ACTOR_START_SECONDS = 120.0;

    /** Seconds a process gets to end after SIGTERM, and then after SIGKILL, before it is reported as stuck. */
    private const ACTOR_TERM_SECONDS = 3.0;
    private const ACTOR_KILL_SECONDS = 5.0;

    /** Longest wait, in seconds, for the connections of ended actors to leave the list of the server. */
    private const ACTOR_CONNECTION_SECONDS = 10.0;

    /** @var array[] actors started by the test */
    private array $actors = [];

    /** @var string[] signal files made by signal_file() */
    private array $signalfiles = [];

    /** @var string[] what end_actors() found that makes the cleanup unsound; raised by assert_actor_cleanup_was_sound() */
    private array $cleanupfailures = [];

    /** @var string|null null when not checked yet; '' when the signals work; a reason when they do not (kept for the whole run) */
    private static ?string $signalsproblem = null;

    /**
     * Skip the test, with the reason, unless the signals work on this server.
     */
    protected function require_observable_locks(): void {
        if (self::$signalsproblem === null) {
            self::$signalsproblem = $this->check_signals();
        }
        if (self::$signalsproblem !== '') {
            $this->markTestSkipped(self::$signalsproblem . ' This test does not count as proof of the dispute.');
        }
    }

    /**
     * Start a known holder and a known waiter and see whether the two signals show.
     *
     * @return string '' when they do, the reason when they do not
     */
    private function check_signals(): string {
        global $DB;
        if ($DB->get_dbfamily() !== 'mysql') {
            return 'The database is not MySQL or MariaDB: GET_LOCK cannot be observed.';
        }
        $version = (string) $DB->get_field_sql('SELECT VERSION()');
        $resource = 'selfcheck_' . getmypid();
        $problem = '';
        $holder = null;
        $waiter = null;
        try {
            $holder = $this->hold_resource($resource, 'selfcheck holder');
            $waiter = $this->spawn_actor(['mode' => 'acquire', 'resource' => $resource, 'timeout' => 30], 'selfcheck waiter');
            $deadline = microtime(true) + 60;
            $seen = false;
            while (microtime(true) < $deadline && !$seen) {
                $seen = $this->lock_owner($resource) === $holder['connection'] && $this->waiting_row($waiter['connection'], $resource) !== null;
                $seen || usleep(100000);
            }
            if (!$seen) {
                $problem = "The signals of GET_LOCK (IS_USED_LOCK and the state \"User lock\" in the list of the server) were not seen on {$version} with a "
                    . 'known holder and a known waiter.';
            }
        } catch (\Throwable $e) {
            $problem = "The signals of GET_LOCK could not be read on {$version}: " . $e->getMessage() . '.';
        } finally {
            $holder && $this->release_actor($holder);
            $waiter && $this->finish_actor($waiter);
            $holder && $this->finish_actor($holder);
        }
        return $problem;
    }

    /**
     * The name the server knows a lock by.
     *
     * @param string $resource
     * @return string
     */
    protected function lock_key(string $resource): string {
        global $CFG;
        return sha1($CFG->dbname . ':' . ($CFG->prefix ?? '') . '_' . unit_of_work::LOCK_TYPE . '_' . $resource);
    }

    /**
     * The connection that holds the lock of a resource.
     *
     * @param string $resource
     * @return int|null null when nobody holds it
     */
    protected function lock_owner(string $resource): ?int {
        global $DB;
        $owner = $DB->get_field_sql('SELECT IS_USED_LOCK(?)', [$this->lock_key($resource)]);
        return ($owner === null || $owner === false) ? null : (int) $owner;
    }

    /**
     * The row of the list of the server of a connection that is waiting for the lock of a resource.
     *
     * @param int $connection
     * @param string $resource
     * @return \stdClass|null null when the connection is not waiting for that lock
     */
    protected function waiting_row(int $connection, string $resource): ?\stdClass {
        global $DB;
        $rows = $DB->get_records_sql('SELECT ID AS id, STATE AS state, INFO AS info FROM information_schema.PROCESSLIST WHERE ID = ?', [$connection]);
        $row = reset($rows);
        if (!$row) {
            return null;
        }
        $waiting = $row->state === 'User lock' && str_contains((string) $row->info, "GET_LOCK('" . $this->lock_key($resource) . "'");
        return $waiting ? $row : null;
    }

    /**
     * Wait for a signal. A signal that does not come is a failure.
     *
     * @param callable $seen
     * @param string $signal what was waited for
     * @param float $seconds the longest wait
     */
    protected function await(callable $seen, string $signal, float $seconds = 90.0): void {
        // A run that expects to fail (a mutation) can shorten the wait: LOCK_SIGNAL_SECONDS. It changes how long the test waits, never what it accepts.
        $seconds = (float) (getenv('LOCK_SIGNAL_SECONDS') ?: $seconds);
        $deadline = microtime(true) + $seconds;
        while (microtime(true) < $deadline) {
            if ($seen()) {
                return;
            }
            usleep(100000);
        }
        $this->fail('SINAL NÃO OBSERVADO: ' . $signal);
    }

    /**
     * Say what was seen, for the report of the run.
     *
     * @param string $line
     */
    protected function note(string $line): void {
        fwrite(STDERR, "\n[sinal] {$line}");
    }

    /**
     * Start an actor as a PHP process and wait for it to say which connection it has.
     *
     * @param array $spec specification of the worker (mode and what the mode needs)
     * @param string $label
     * @return array the actor
     */
    protected function spawn_actor(array $spec, string $label): array {
        global $CFG;
        $marker = tempnam(sys_get_temp_dir(), 'actor_marker_');
        unlink($marker);
        $spec += [
            'autoload' => realpath($CFG->dirroot . '/../vendor/autoload.php'), 'bootstrap' => $CFG->dirroot . '/lib/phpunit/bootstrap.php',
            'start' => microtime(true), 'label' => $label, 'course' => 0, 'marker' => $marker,
        ];
        $script = $CFG->dirroot . '/local/assistente/tests/fixtures/queue_worker.php';
        $process = proc_open(
            [PHP_BINARY, '-d', 'xdebug.mode=off', $script, json_encode($spec)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            $this->fail(sprintf(
                "INICIALIZAÇÃO FALHOU: o worker '%s' não foi iniciado (etapa proc_open). php: %s; script: %s (%s); mode: %s.",
                $label,
                PHP_BINARY,
                $script,
                file_exists($script) ? 'exists' : 'MISSING',
                $spec['mode'] ?? '?'
            ));
        }
        foreach ([1, 2] as $pipe) {
            stream_set_blocking($pipes[$pipe], false);
        }
        $this->actors[] = [
            'process' => $process, 'pipes' => $pipes, 'label' => $label, 'files' => [$marker], 'connection' => 0, 'ended' => false,
            'early_out' => '', 'early_err' => '', 'mode' => $spec['mode'] ?? '?',
        ];
        $index = array_key_last($this->actors);

        // Ready: the marker. The wait ends at once when the process is gone (it ended before it could write the marker), and it keeps
        // the limit for a process that stays alive without writing it. What the process wrote meanwhile is kept for finish_actor().
        $limit = (float) (getenv('ACTOR_START_SECONDS') ?: self::ACTOR_START_SECONDS);
        $started = microtime(true);
        $deadline = $started + $limit;
        while (!file_exists($marker)) {
            $this->actors[$index]['early_out'] .= (string) stream_get_contents($pipes[1]);
            $this->actors[$index]['early_err'] .= (string) stream_get_contents($pipes[2]);
            if (!proc_get_status($process)['running'] && !file_exists($marker)) {
                $this->abort_spawn($index, 'the process ended before it wrote its marker (Moodle did not start, or the connection failed)', $started);
            }
            if (microtime(true) >= $deadline) {
                $this->abort_spawn($index, 'the process is alive but wrote no marker within ' . $limit . 's', $started);
            }
            $read = [$pipes[1], $pipes[2]];
            $write = $except = null;
            @stream_select($read, $write, $except, 0, 20000);
        }
        $marked = json_decode((string) file_get_contents($marker), true);
        if (!is_array($marked) || !isset($marked['connection'])) {
            $this->abort_spawn($index, 'the marker is not valid: ' . substr((string) @file_get_contents($marker), 0, 200), $started);
        }
        $this->actors[$index]['connection'] = (int) $marked['connection'];
        return $this->actors[$index];
    }

    /**
     * A worker failed to start: collect what it wrote, clean it up, and fail with the diagnosis. The process, the pipes and the marker are
     * gone before the failure is raised.
     *
     * @param int $index index in $this->actors
     * @param string $stage what failed
     * @param float $started when the start began
     */
    private function abort_spawn(int $index, string $stage, float $started): void {
        $actor = $this->actors[$index];
        $status = proc_get_status($actor['process']);
        $out = $actor['early_out'] . (string) stream_get_contents($actor['pipes'][1]);
        $err = $actor['early_err'] . (string) stream_get_contents($actor['pipes'][2]);
        $exit = $status['running'] ? 'still running' : var_export($status['exitcode'], true);
        $text = strtolower($out . ' ' . $err);
        $hint = 'no hint in the output';
        if (preg_match('/access denied|connection refused|unknown database|could not connect|server has gone away|dml_connection/', $text)) {
            $hint = 'looks like a failure of the connection to the database';
        } else if (preg_match('/bootstrap|phpunit|config\.php|require\(|failed opening|no such file|parse error|fatal error/', $text)) {
            $hint = 'looks like a failure of the PHP or Moodle bootstrap';
        } else if ($err === '' && $out === '') {
            $hint = 'the process wrote nothing';
        }
        $diagnosis = sprintf(
            "INICIALIZAÇÃO FALHOU: worker '%s' (mode %s, pid %s), stage: %s. Elapsed %.1fs; exit code: %s; hint: %s. stderr (tail): %s. stdout (tail): %s",
            $actor['label'],
            $actor['mode'],
            $status['pid'] ?? '?',
            $stage,
            microtime(true) - $started,
            $exit,
            $hint,
            substr($err, -800),
            substr($out, -400)
        );
        [$notes, $state] = $this->stop_process($actor);
        if ($state === 'ended') {
            foreach ($actor['files'] as $file) {
                file_exists($file) && unlink($file);
            }
            unset($this->actors[$index]);
        } else {
            // Not confirmed: the record, the pipes and the marker stay, so that end_actors() tries again and reports it.
            $diagnosis .= " The process is NOT confirmed as ended (state: {$state}); its record was kept for a new attempt of cleanup.";
        }
        $this->fail($diagnosis . ($notes ? ' Cleanup: ' . implode('; ', $notes) : ''));
    }

    /**
     * Start an actor that holds the lock of a resource until the test releases it.
     *
     * @param string $resource
     * @param string $label
     * @return array the actor, ready: it holds the lock
     */
    protected function hold_resource(string $resource, string $label): array {
        $ready = tempnam(sys_get_temp_dir(), 'actor_ready_');
        unlink($ready);
        $release = tempnam(sys_get_temp_dir(), 'actor_release_');
        unlink($release);
        $actor = $this->spawn_actor([
            'mode' => 'hold', 'resources' => [$resource], 'seconds' => 120, 'ready_file' => $ready, 'release_file' => $release,
        ], $label);
        $this->await(fn() => file_exists($ready), "'{$label}' did not take the lock of {$resource}");
        $actor['release_file'] = $release;
        $actor['files'] = array_merge($actor['files'], [$ready, $release]);
        $this->register_actor($actor);
        return $actor;
    }

    /**
     * Start an actor that locks the row of an event in a transaction until the test releases it.
     *
     * @param int $eventid
     * @param string $label
     * @return array the actor, ready: it holds the row
     */
    protected function hold_event_row(int $eventid, string $label): array {
        $ready = tempnam(sys_get_temp_dir(), 'actor_ready_');
        unlink($ready);
        $release = tempnam(sys_get_temp_dir(), 'actor_release_');
        unlink($release);
        $actor = $this->spawn_actor([
            'mode' => 'hold_row', 'event_id' => $eventid, 'seconds' => 120, 'ready_file' => $ready, 'release_file' => $release,
        ], $label);
        $this->await(fn() => file_exists($ready), "'{$label}' did not lock the row of the event {$eventid}");
        $actor['release_file'] = $release;
        $actor['files'] = array_merge($actor['files'], [$ready, $release]);
        $this->register_actor($actor);
        return $actor;
    }

    /**
     * Keep the last version of an actor, the one that has the files.
     *
     * @param array $actor
     */
    private function register_actor(array $actor): void {
        foreach ($this->actors as $index => $known) {
            if ($known['process'] === $actor['process']) {
                $this->actors[$index] = $actor;
            }
        }
    }

    /**
     * Tell an actor that holds something to let go.
     *
     * @param array $actor
     */
    protected function release_actor(array $actor): void {
        if (!empty($actor['release_file'])) {
            file_put_contents($actor['release_file'], 'release');
        }
    }

    /**
     * Wait for an actor to end and give back its answer, within a time limit.
     *
     * The wait is bounded: a process that does not end within the limit is diagnosed, ended (SIGTERM and then SIGKILL), its pipes are
     * closed and the test fails with the diagnosis. The limit is ACTOR_FINISH_SECONDS, or the environment variable of the same name.
     *
     * @param array $actor
     * @param float|null $timeout seconds; null for the default
     * @return array the answer of the worker: results, error, finished_at and what the mode adds
     */
    protected function finish_actor(array $actor, ?float $timeout = null): array {
        $timeout = (float) (getenv('ACTOR_FINISH_SECONDS') ?: ($timeout ?? self::ACTOR_FINISH_SECONDS));
        $pipes = $actor['pipes'];
        foreach ([1, 2] as $pipe) {
            stream_set_blocking($pipes[$pipe], false);
        }
        $output = (string) ($actor['early_out'] ?? '');
        $errors = (string) ($actor['early_err'] ?? '');
        $status = ['running' => true];
        $exitcode = null;
        $started = microtime(true);
        $deadline = $started + $timeout;
        $overdue = false;
        while (true) {
            $status = proc_get_status($actor['process']);
            if (!$status['running'] && $exitcode === null) {
                $exitcode = $status['exitcode'];
            }
            $output .= (string) stream_get_contents($pipes[1]);
            $errors .= (string) stream_get_contents($pipes[2]);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) >= $deadline) {
                $overdue = true;
                break;
            }
            $read = [$pipes[1], $pipes[2]];
            $write = $except = null;
            @stream_select($read, $write, $except, 0, 200000);
        }
        $output .= (string) stream_get_contents($pipes[1]);
        $errors .= (string) stream_get_contents($pipes[2]);

        $diagnosis = '';
        if ($overdue) {
            try {
                $diagnosis = $this->diagnose_actor($actor, $status, $timeout, microtime(true) - $started, $output, $errors);
            } catch (\Throwable $e) {
                // Whatever fails in the diagnosis, the timeout is reported and the process is still ended below.
                $diagnosis = sprintf(
                    "PROCESSO EXCEDEU O PRAZO: '%s' (pid %s) did not end within %.1fs; the rest of the diagnosis could NOT be built (%s: %s).",
                    $actor['label'],
                    $status['pid'] ?? '?',
                    $timeout,
                    get_class($e),
                    substr($e->getMessage(), 0, 120)
                );
            }
            [$notes, $state] = $this->stop_process($actor);
            $diagnosis .= $notes ? ' Cleanup: ' . implode('; ', $notes) : '';
            if ($state === 'ended') {
                $this->mark_actor_ended($actor);
            } else {
                // Not confirmed: the actor stays registered, with its pipes and files, so that end_actors() tries again and reports it.
                $diagnosis .= " The process is NOT confirmed as ended (state: {$state}); its record was kept for a new attempt of cleanup.";
            }
        } else {
            // The process has ended, so proc_close() returns at once.
            foreach ([1, 2] as $pipe) {
                is_resource($pipes[$pipe]) && fclose($pipes[$pipe]);
            }
            proc_close($actor['process']);
            $this->mark_actor_ended($actor);
        }

        if ($overdue) {
            $this->fail($diagnosis);
        }
        if (!str_contains($output, '@@RESULT@@')) {
            $this->fail("The process '{$actor['label']}' did not answer (exit code " . var_export($exitcode, true)
                . "). stderr: {$errors} stdout: {$output}");
        }
        $answer = json_decode(substr($output, strpos($output, '@@RESULT@@') + 10), true);
        $this->assertIsArray($answer, "The answer of '{$actor['label']}' is not valid. stdout: {$output}");
        return $answer;
    }

    /**
     * What is known about a process that did not end in time, for the message of the failure.
     *
     * @param array $actor
     * @param array $status result of proc_get_status()
     * @param float $timeout the limit that was exceeded
     * @param float $elapsed seconds waited
     * @param string $output what the process wrote to stdout
     * @param string $errors what the process wrote to stderr
     * @return string
     */
    private function diagnose_actor(array $actor, array $status, float $timeout, float $elapsed, string $output, string $errors): string {
        global $DB;
        $server = 'no row in the list of the server';
        if (!empty($actor['connection'])) {
            // The query is only a help for the message. If it fails, the message says so and keeps the rest: it must never replace the
            // report of the timeout, nor stop finish_actor() from going on to end the process.
            try {
                $rows = $DB->get_records_sql(
                    'SELECT ID AS id, STATE AS state, TIME AS seconds, INFO AS info FROM information_schema.PROCESSLIST WHERE ID = ?',
                    [(int) $actor['connection']]
                );
                if ($row = reset($rows)) {
                    $server = sprintf('state "%s" for %ss, statement "%s"', $row->state, $row->seconds, substr((string) $row->info, 0, 160));
                }
            } catch (\Throwable $e) {
                $server = 'the state of the connection could NOT be queried (' . get_class($e) . ': ' . substr($e->getMessage(), 0, 120) . ')';
            }
        }
        return sprintf(
            "PROCESSO EXCEDEU O PRAZO: '%s' (pid %s, connection %s) did not end within %.1fs (waited %.1fs). Server: %s. "
            . "Command: %s. stdout (tail): %s. stderr (tail): %s",
            $actor['label'],
            $status['pid'] ?? '?',
            $actor['connection'] ?: '?',
            $timeout,
            $elapsed,
            $server,
            substr((string) ($status['command'] ?? ''), 0, 300),
            substr($output, -600),
            substr($errors, -600)
        );
    }

    /**
     * What is known about a process: 'running', 'ended' (seen not running), or 'unknown' (the resource is not valid or its state cannot be
     * read). Only 'ended' is a confirmation; 'unknown' is never taken as one.
     *
     * @param mixed $process the resource of proc_open()
     * @return string 'running', 'ended' or 'unknown'
     */
    private function process_state($process): string {
        if (!is_resource($process)) {
            return 'unknown';
        }
        try {
            $status = proc_get_status($process);
        } catch (\Throwable $e) {
            return 'unknown';
        }
        if (!is_array($status)) {
            return 'unknown';
        }
        return $status['running'] ? 'running' : 'ended';
    }

    /**
     * The pid of an actor, for the messages: from the status when it can be read.
     *
     * @param array $actor
     * @return string
     */
    private function actor_pid(array $actor): string {
        try {
            $status = is_resource($actor['process']) ? proc_get_status($actor['process']) : false;
        } catch (\Throwable $e) {
            $status = false;
        }
        return is_array($status) ? (string) $status['pid'] : 'unknown';
    }

    /**
     * End a process with a bound on every wait: SIGTERM, a limited wait that checks that the process is really gone, then SIGKILL, another
     * limited wait. Only when the process was seen to have ended are the pipes and the process resource closed (proc_close() waits for the
     * process to end, so it must never be called for one that is alive). When the process is still running, or its state cannot be read,
     * nothing is closed: the pipes and the resource stay open so that a new attempt, and the diagnosis, remain possible. Closing a pipe or
     * dropping a reference does not end a process, and this method does not treat it as if it did.
     *
     * PHP offers no way to guarantee the end of a process that survives SIGKILL (one in an uninterruptible state); it is reported, not hidden.
     *
     * @param array $actor
     * @return array [notes about what did not go as expected, state: 'ended', 'running' or 'unknown']
     */
    private function stop_process(array $actor): array {
        $notes = [];
        $process = $actor['process'];
        $state = $this->process_state($process);
        if ($state === 'unknown') {
            return [['the state of the process cannot be read (the resource is not valid), so its end is not confirmed'], 'unknown'];
        }
        if ($state === 'running') {
            proc_terminate($process);
            if (!$this->wait_not_running($process, self::ACTOR_TERM_SECONDS)) {
                $notes[] = 'did not end ' . self::ACTOR_TERM_SECONDS . 's after SIGTERM, so SIGKILL was sent';
                proc_terminate($process, 9);
                if (!$this->wait_not_running($process, self::ACTOR_KILL_SECONDS)) {
                    $notes[] = 'STILL RUNNING ' . self::ACTOR_KILL_SECONDS . 's after SIGKILL';
                }
            }
            $state = $this->process_state($process);
        }
        if ($state === 'ended') {
            foreach ([1, 2] as $pipe) {
                is_resource($actor['pipes'][$pipe]) && fclose($actor['pipes'][$pipe]);
            }
            proc_close($process);
        } else {
            $notes[] = 'its end is NOT confirmed: the pipes and the process resource were kept open and proc_close() was not called';
        }
        return [$notes, $state];
    }

    /**
     * Wait, within a limit, until a process is seen not running.
     *
     * @param mixed $process
     * @param float $seconds
     * @return bool true only when the process was seen ended; false when it was still running at the limit or its state cannot be read
     */
    private function wait_not_running($process, float $seconds): bool {
        $deadline = microtime(true) + $seconds;
        while (true) {
            $state = $this->process_state($process);
            if ($state === 'ended') {
                return true;
            }
            if ($state === 'unknown' || microtime(true) >= $deadline) {
                return false;
            }
            usleep(10000);
        }
    }

    /**
     * Record that an actor has ended, so that end_actors() does not end it again.
     *
     * @param array $actor
     */
    private function mark_actor_ended(array $actor): void {
        foreach ($this->actors as $index => $known) {
            if ($known['process'] === $actor['process']) {
                $this->actors[$index]['ended'] = true;
            }
        }
    }

    /**
     * End an actor with SIGKILL, as a process that dies with no chance of cleaning anything (no shutdown function of PHP runs).
     *
     * @param array $actor
     * @return array how it ended: signaled, termsig and exitcode of proc_get_status()
     */
    protected function kill_actor(array $actor): array {
        proc_terminate($actor['process'], 9);
        $deadline = microtime(true) + 15.0;
        $status = proc_get_status($actor['process']);
        $result = ['signaled' => $status['signaled'], 'termsig' => $status['termsig'], 'exitcode' => $status['exitcode']];
        while ($status['running'] && microtime(true) < $deadline) {
            $read = [$actor['pipes'][1], $actor['pipes'][2]];
            $write = $except = null;
            @stream_select($read, $write, $except, 0, 100000);
            $status = proc_get_status($actor['process']);
            $result = ['signaled' => $status['signaled'], 'termsig' => $status['termsig'], 'exitcode' => $status['exitcode']];
        }
        if ($status['running']) {
            // Not confirmed: the actor stays registered, with its pipes and files (end_actors() will try again and report it), and
            // proc_close() is not called because it would wait for a process that does not end.
            $this->fail("The process '{$actor['label']}' (pid {$status['pid']}, connection {$actor['connection']}) was still running 15s after SIGKILL; "
                . 'its record was kept for a new attempt of cleanup.');
        }
        $this->mark_actor_ended($actor);
        foreach ([1, 2] as $pipe) {
            is_resource($actor['pipes'][$pipe]) && fclose($actor['pipes'][$pipe]);
        }
        proc_close($actor['process']);
        return $result;
    }

    /**
     * Ask the server to end the connection of an actor (KILL CONNECTION), from the connection of the test.
     *
     * @param int $connection
     * @throws \dml_exception when the server refuses (see kill_was_denied())
     */
    protected function kill_connection(int $connection): void {
        global $DB;
        $DB->execute('KILL CONNECTION ' . $connection);
    }

    /**
     * Whether a failure of KILL CONNECTION is the server refusing the privilege, and not another problem.
     *
     * @param \Throwable $e
     * @return bool
     */
    protected function kill_was_denied(\Throwable $e): bool {
        $text = strtolower($e->getMessage() . ' ' . ($e->debuginfo ?? '') . ' ' . ($e->error ?? ''));
        return str_contains($text, 'not owner of thread') || str_contains($text, 'access denied') || str_contains($text, 'you need');
    }

    /**
     * Wait for a lock to be free. The lock of a process that ended is released when the server notices that the connection is gone.
     *
     * @param string $resource
     * @param float $seconds
     */
    protected function await_lock_free(string $resource, float $seconds = 30.0): void {
        $this->await(fn() => $this->lock_owner($resource) === null, "the lock of {$resource} was not released", $seconds);
    }

    /**
     * Wait for a connection to disappear from the list of the server.
     *
     * @param int $connection
     * @param float $seconds
     */
    protected function await_connection_gone(int $connection, float $seconds = 30.0): void {
        global $DB;
        $this->await(
            fn() => $DB->count_records_sql('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID = ?', [$connection]) === 0,
            "the connection {$connection} is still in the list of the server",
            $seconds
        );
    }

    /**
     * A path for a signal file between the test and a process; the file does not exist yet and is removed by end_actors().
     *
     * @param string $name
     * @return string
     */
    protected function signal_file(string $name): string {
        $file = tempnam(sys_get_temp_dir(), 'sig_' . $name . '_');
        unlink($file);
        $this->signalfiles[] = $file;
        return $file;
    }

    /**
     * Start a group of workers that will begin together (ready/go). Each is started, initializes Moodle and writes its marker (ready); this
     * returns when all are ready, and go_group() lets them begin.
     *
     * @param array[] $specs one specification per worker (mode and what it needs)
     * @param string $prefix prefix of the labels
     * @return array the group: actors by label, and the go file
     */
    protected function spawn_group(array $specs, string $prefix): array {
        $go = $this->signal_file('go');
        $actors = [];
        try {
            foreach ($specs as $index => $spec) {
                $label = "{$prefix}-{$index}";
                $actors[$label] = $this->spawn_actor($spec + ['go_file' => $go], $label);
            }
        } catch (\Throwable $e) {
            // A later worker failed to start: do not leave the ones already started for the tearDown() to find. The failure of the start
            // is the one that is raised; what the cleanup finds is only written to the report of the run.
            $this->abandon_group($actors, $go);
            throw $e;
        }
        return ['actors' => $actors, 'go_file' => $go];
    }

    /**
     * End the workers of a group that was only partly started, and remove its go file. None of them was told to go, so none has written
     * anything, none is at a barrier, and each one is waiting for the go file: that file is NOT written (it would make them work), and
     * SIGTERM ends them where they wait. A worker whose end is not confirmed stays registered, so that end_actors() tries again and fails the
     * test on it. This never raises: the failure that is being handled must not be masked.
     *
     * @param array[] $started the actors of the group that were started, by label
     * @param string $go the go file of the group
     */
    private function abandon_group(array $started, string $go): void {
        try {
            $unconfirmed = [];
            foreach ($started as $label => $actor) {
                [$notes, $state] = $this->stop_process($actor);
                if ($state === 'ended') {
                    $this->forget_actor($actor);
                } else {
                    $unconfirmed[] = sprintf(
                        "'%s' (pid %s, connection %s) state %s: %s",
                        $label,
                        $this->actor_pid($actor),
                        $actor['connection'] ?: '?',
                        $state,
                        implode('; ', $notes)
                    );
                }
            }
            if (!$unconfirmed) {
                $this->signalfiles = array_values(array_diff($this->signalfiles, [$go]));
                file_exists($go) && unlink($go);
                file_exists($go . '.tmp') && unlink($go . '.tmp');
            } else {
                $this->note('spawn_group: the end of these started workers is NOT confirmed (kept registered): ' . implode(' | ', $unconfirmed));
            }
        } catch (\Throwable $cleanup) {
            $this->note('spawn_group: the cleanup of the started workers failed: ' . get_class($cleanup) . ': ' . $cleanup->getMessage());
        }
    }

    /**
     * Remove an actor whose end is confirmed from the registry, with its files.
     *
     * @param array $actor
     */
    private function forget_actor(array $actor): void {
        foreach ($this->actors as $index => $known) {
            if ($known['process'] === $actor['process']) {
                foreach ($known['files'] as $file) {
                    file_exists($file) && unlink($file);
                }
                unset($this->actors[$index]);
            }
        }
    }

    /**
     * Let a group begin. The file is written whole and moved into place, so that no worker reads it half written; it holds the instant at
     * which they begin, a few milliseconds ahead, only to cover the time the file takes to be seen by all of them.
     *
     * @param array $group
     * @return float the instant at which the workers begin
     */
    protected function go_group(array $group): float {
        $at = microtime(true) + 0.05;
        $temporary = $group['go_file'] . '.tmp';
        file_put_contents($temporary, sprintf('%.6F', $at));
        rename($temporary, $group['go_file']);
        return $at;
    }

    /**
     * Wait for every worker of a group and give back the answers.
     *
     * @param array $group
     * @param float|null $timeout seconds for each worker; null for the default
     * @return array[] the answers by label
     */
    protected function finish_group(array $group, ?float $timeout = null): array {
        $answers = [];
        foreach ($group['actors'] as $label => $actor) {
            $answers[$label] = $this->finish_actor($actor, $timeout);
        }
        return $answers;
    }

    /**
     * Skip nothing: fail when the signals of GET_LOCK cannot be observed on this server. For the tests whose result is required, a missing
     * prerequisite is a failure, never a skip (see require_observable_locks() for the tests that may skip).
     */
    protected function assert_observable_locks(): void {
        if (self::$signalsproblem === null) {
            self::$signalsproblem = $this->check_signals();
        }
        if (self::$signalsproblem !== '') {
            $this->fail('PRÉ-REQUISITO AUSENTE: ' . self::$signalsproblem);
        }
    }

    /**
     * End every actor that is still running, remove their files, and confirm the cleanup.
     *
     * The registry keeps every actor whose end is not confirmed ('running' or 'unknown'), with its pipes and files, so that nothing that may
     * still be alive is forgotten and a later call tries again. A cleanup is sound only if every process was seen to have ended, the list of
     * the server could be read, and no connection of an ended actor is still in it. When it is not, the problem is written at once to the
     * report of the run (note(), stderr) and is raised as a failure by assert_actor_cleanup_was_sound(), after the tearDown() of the test and
     * after the hooks of Moodle: an exception thrown from here would skip the #[After] hook of advanced_testcase that restores $CFG and $DB
     * and resets the database. PHPUnit reports that failure in addition to the failure of the test itself, never instead of it.
     */
    protected function end_actors(): void {
        $informative = [];
        $failures = [];
        $survivors = [];
        $connections = [];
        foreach ($this->actors as $key => $actor) {
            if (!$actor['ended']) {
                [$notes, $state] = $this->stop_process($actor);
                if ($state !== 'ended') {
                    $failures[] = sprintf(
                        "process '%s' (pid %s, connection %s) is NOT confirmed as ended (state: %s): %s",
                        $actor['label'],
                        $this->actor_pid($actor),
                        $actor['connection'] ?: '?',
                        $state,
                        implode('; ', $notes)
                    );
                    $survivors[$key] = $actor;
                    continue;
                }
                $actor['ended'] = true;
                if ($notes) {
                    $informative[] = "'{$actor['label']}' (connection {$actor['connection']}): " . implode('; ', $notes);
                }
            }
            if ($actor['connection']) {
                $connections[] = (int) $actor['connection'];
            }
            foreach ($actor['files'] as $file) {
                file_exists($file) && unlink($file);
            }
        }
        $this->actors = $survivors;
        if (!$survivors) {
            foreach ($this->signalfiles as $file) {
                file_exists($file) && unlink($file);
                file_exists($file . '.tmp') && unlink($file . '.tmp');
                file_exists($file . '.tmp.tmp') && unlink($file . '.tmp.tmp');
            }
            $this->signalfiles = [];
        }
        // The locks of the connection of a process that was ended are gone once the server notices that the connection is gone: wait for
        // that signal, with a limit, instead of a fixed pause. A list that cannot be read is not a confirmation.
        if ($connections) {
            $check = $this->check_connections($connections, self::ACTOR_CONNECTION_SECONDS);
            if ($check['state'] === 'residual') {
                $failures[] = 'connections of ended actors still in the list of the server: ' . implode(', ', $check['listed']);
            } else if ($check['state'] === 'unverifiable') {
                $failures[] = 'the list of the server could not be read, so the end of the connections ' . implode(', ', $connections)
                    . ' is NOT confirmed: ' . $check['error'];
            }
        }
        if ($informative) {
            $this->note('end_actors: ' . implode(' | ', $informative));
        }
        if ($failures) {
            $diagnosis = 'LIMPEZA INCOMPLETA: ' . implode(' | ', $failures);
            $this->note($diagnosis);
            $this->cleanupfailures[] = $diagnosis;
        }
    }

    /**
     * Raise, as a failure of the test, what end_actors() found wrong. It runs last (priority below every hook of the test, of the trait user
     * and of Moodle), so that it does not skip the reset that Moodle does after each test. If the test had already failed, PHPUnit reports this
     * failure as well; the diagnosis was also written to stderr by end_actors().
     */
    #[\PHPUnit\Framework\Attributes\After(-1000)]
    public function assert_actor_cleanup_was_sound(): void {
        if ($this->cleanupfailures) {
            $diagnosis = implode("\n", $this->cleanupfailures);
            $this->cleanupfailures = [];
            $this->fail($diagnosis);
        }
    }

    /**
     * Wait, within a limit, for connections to leave the list of the server, and say whether that was confirmed.
     *
     * @param int[] $connections
     * @param float $seconds
     * @return array state: 'clear' (read, none listed), 'residual' (read, some still listed at the limit: 'listed') or 'unverifiable'
     *               (the list could not be read: 'error'); also 'listed' (int[]) and 'error' (string)
     */
    private function check_connections(array $connections, float $seconds): array {
        global $DB;
        $deadline = microtime(true) + $seconds;
        try {
            [$insql, $params] = $DB->get_in_or_equal($connections);
            while (true) {
                $listed = $DB->get_fieldset_sql("SELECT ID FROM information_schema.PROCESSLIST WHERE ID {$insql}", $params);
                if (!$listed) {
                    return ['state' => 'clear', 'listed' => [], 'error' => ''];
                }
                if (microtime(true) >= $deadline) {
                    return ['state' => 'residual', 'listed' => array_map('intval', $listed), 'error' => ''];
                }
                usleep(20000);
            }
        } catch (\Throwable $e) {
            return ['state' => 'unverifiable', 'listed' => [], 'error' => get_class($e) . ': ' . substr($e->getMessage(), 0, 200)];
        }
    }

    /**
     * Wait for every worker of a group to reach a barrier, and fail at once if one of them is gone before it arrives. The signals are files
     * that the workers write; the wait is a poll of those files with a limit, and a worker that ended without writing its file is
     * detected on the next poll, not at the limit.
     *
     * @param array $group
     * @param string[] $files the file each worker writes when it arrives, by label
     * @param string $what name of the barrier, for the messages
     * @param float $seconds the longest wait
     */
    protected function await_group_barrier(array $group, array $files, string $what, float $seconds = 60.0): void {
        $deadline = microtime(true) + $seconds;
        while (true) {
            $missing = array_filter($files, fn($file) => !file_exists($file));
            if (!$missing) {
                return;
            }
            foreach (array_keys($missing) as $label) {
                $actor = $group['actors'][$label];
                if (!proc_get_status($actor['process'])['running'] && !file_exists($files[$label])) {
                    $err = (string) stream_get_contents($actor['pipes'][2]);
                    $out = (string) stream_get_contents($actor['pipes'][1]);
                    $this->fail(sprintf(
                        "BARREIRA '%s': o worker '%s' terminou antes de chegar. Chegaram: [%s]. stderr (tail): %s. stdout (tail): %s",
                        $what,
                        $label,
                        implode(', ', array_keys(array_diff_key($files, $missing))),
                        substr($err, -600),
                        substr($out, -400)
                    ));
                }
            }
            if (microtime(true) >= $deadline) {
                $this->fail(sprintf(
                    "BARREIRA '%s': nem todos chegaram em %.0fs. Chegaram: [%s]. Faltam: [%s].",
                    $what,
                    $seconds,
                    implode(', ', array_keys(array_diff_key($files, $missing))),
                    implode(', ', array_keys($missing))
                ));
            }
            usleep(1000);
        }
    }

    /**
     * Record the order in which the locks are taken in this process, until stop_recording_locks().
     *
     * @return recording_lock_factory
     */
    protected function record_locks(): recording_lock_factory {
        $factory = new recording_lock_factory(unit_of_work::LOCK_TYPE);
        (new \ReflectionProperty(unit_of_work::class, 'factory'))->setValue(null, $factory);
        return $factory;
    }

    /**
     * Go back to the lock factory of the plugin.
     */
    protected function stop_recording_locks(): void {
        unit_of_work::reset_lock_factory();
    }
}
