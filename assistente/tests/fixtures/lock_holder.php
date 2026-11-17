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

/**
 * Hold the lock of a resource from another PHP process, for tests of what happens while a lock is busy (redelivery of an event).
 *
 * The lock of the database is re-entrant inside one process, so a busy lock can only be shown by a second process: this starts
 * tests/fixtures/queue_worker.php in its "hold" mode, waits until it says it holds the lock, and ends it with the test. It never makes
 * a test wait for a long time: the holder is ended by the test as soon as it has seen what it needs.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait lock_holder {
    /** @var array[] holders started by the test: process and pipes */
    private array $holders = [];

    /**
     * Start a process that holds the lock of a resource, and wait until it does.
     *
     * @param string $resource
     * @param int $courseid any course (the worker needs one in its specification)
     * @param int $seconds longest the holder lives if the test does not end it
     */
    protected function hold_lock(string $resource, int $courseid, int $seconds = 20): void {
        global $CFG;
        $ready = tempnam(sys_get_temp_dir(), 'lock_ready_');
        unlink($ready);
        $spec = [
            'autoload' => realpath($CFG->dirroot . '/../vendor/autoload.php'), 'bootstrap' => $CFG->dirroot . '/lib/phpunit/bootstrap.php',
            'start' => microtime(true), 'label' => 'holder', 'course' => $courseid, 'mode' => 'hold',
            'resources' => [$resource], 'seconds' => $seconds, 'ready_file' => $ready,
        ];
        $process = proc_open(
            [PHP_BINARY, '-d', 'xdebug.mode=off', $CFG->dirroot . '/local/assistente/tests/fixtures/queue_worker.php', json_encode($spec)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->holders[] = [$process, $pipes, $ready];
        $deadline = microtime(true) + 30;
        while (!file_exists($ready) && microtime(true) < $deadline) {
            usleep(100000);
        }
        $this->assertFileExists($ready, 'The holder did not take the lock');
    }

    /**
     * End the holders: their locks are released with their connections.
     */
    protected function release_locks(): void {
        foreach ($this->holders as [$process, $pipes, $ready]) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
            if (file_exists($ready)) {
                unlink($ready);
            }
        }
        $this->holders = [];
        // The lock of the connection of a terminated process is gone once the server notices; give it a moment.
        usleep(300000);
    }
}
