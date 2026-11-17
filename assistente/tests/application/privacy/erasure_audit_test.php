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

use local_assistente\domain\privacy\erasure_event_fact;
use local_assistente\domain\privacy\erasure_key;
use local_assistente\infrastructure\moodle\erasure_audit_reader;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The audit events of the erasures and their reader (T142; DEC-58): one event per type, task and case; only codes in the data; a log that
 * cannot be written or read never changes the outcome of an erasure.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(erasure_audit::class)]
#[CoversClass(erasure_audit_reader::class)]
#[CoversClass(\local_assistente\event\student_erasure_event::class)]
final class erasure_audit_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $user;

    /** @var erasure_audit */
    private erasure_audit $audit;

    /** @var erasure_audit_reader */
    private erasure_audit_reader $reader;

    /**
     * The standard log store is enabled and writes at once, as the tests of the log store of Moodle do.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
        $this->user = $this->getDataGenerator()->create_user(['firstname' => 'Nome', 'lastname' => 'Reservado']);
        $this->reader = new erasure_audit_reader();
        $this->audit = new erasure_audit($this->reader);
    }

    /**
     * Each type is written once for a task and a case, however many times it is asked, and the three types are independent.
     */
    public function test_each_event_is_written_once(): void {
        $key = new erasure_key((int) $this->user->id, [7, 8]);

        $this->assertTrue($this->audit->deferred_once($key, 10, 'lock_unavailable', 8));
        $this->assertFalse($this->audit->deferred_once($key, 10, 'lock_unavailable', 8));
        $this->assertTrue($this->audit->stopped_once($key, 10, 'attempts_exhausted'));
        $this->assertFalse($this->audit->stopped_once($key, 10, 'attempts_exhausted'));
        $this->assertFalse($this->audit->stopped_once($key, 10, 'names_unavailable'), 'The reason does not make another event');
        $this->assertTrue($this->audit->completed_once($key, 10, erasure_event_fact::VIA_RETRY));
        $this->assertFalse($this->audit->completed_once($key, 10, erasure_event_fact::VIA_REQUEST));

        $facts = $this->reader->events_of((int) $this->user->id);
        $this->assertSame(['deferred', 'stopped', 'completed'], array_column(array_map(fn($f) => (array) $f, $facts), 'type'));
        $this->assertSame([10, 10, 10], array_column(array_map(fn($f) => (array) $f, $facts), 'task'));
    }

    /**
     * The correlation is by type, task and the exact courses: another task, other courses or another student are other events.
     */
    public function test_events_are_correlated_by_task_and_exact_courses(): void {
        $other = $this->getDataGenerator()->create_user();
        $key = new erasure_key((int) $this->user->id, [7, 8]);

        $this->assertTrue($this->audit->stopped_once($key, 10, 'attempts_exhausted'));
        $this->assertTrue($this->audit->stopped_once($key, 11, 'attempts_exhausted'), 'Another task');
        $this->assertTrue($this->audit->stopped_once(new erasure_key((int) $this->user->id, [8]), 10, 'attempts_exhausted'), 'Other courses');
        $this->assertTrue($this->audit->stopped_once(new erasure_key((int) $other->id, [7, 8]), 10, 'attempts_exhausted'), 'Another student');

        $this->assertCount(3, $this->reader->events_of((int) $this->user->id));
        $this->assertCount(1, $this->reader->events_of((int) $other->id));
        $this->assertTrue($this->reader->has('stopped', 10, new erasure_key((int) $this->user->id, [8, 7])), 'The order of the courses does not matter');
        $this->assertFalse($this->reader->has('deferred', 10, $key));
    }

    /**
     * The log row has only the code, the courses, the task and the number of attempts: no name, no text, no message of an exception, no
     * name of a lock; the student is in `relateduserid` and the context is the system.
     */
    public function test_the_event_carries_no_personal_data(): void {
        global $DB;
        $key = new erasure_key((int) $this->user->id, [7]);
        $this->audit->deferred_once($key, 10, 'lock_unavailable', 8);
        $this->audit->completed_once($key, 10, erasure_event_fact::VIA_RETRY);
        $this->audit->stopped_once($key, 11, 'names_unavailable');

        $rows = $DB->get_records_select('logstore_standard_log', "eventname LIKE '%student_erasure%'", [], 'id');
        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame((int) $this->user->id, (int) $row->relateduserid);
            $this->assertSame(\context_system::instance()->id, (int) $row->contextid);
            $other = json_decode($row->other, true);
            $this->assertSame(['courseids', 'task', 'detail', 'remaining'], array_keys($other));
            $this->assertSame([7], $other['courseids']);
            $text = json_encode($row);
            foreach (['Nome', 'Reservado', 'event_', 'privacy_user', 'Could not'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $text);
            }
        }
    }

    /**
     * An event with an unknown reason, a student or an empty case is refused when it is built, not written half.
     */
    public function test_an_incomplete_event_is_refused(): void {
        $this->expectException(\coding_exception::class);
        \local_assistente\event\student_erasure_stopped::create([
            'context' => \context_system::instance(), 'relateduserid' => (int) $this->user->id, 'other' => ['courseids' => [7]],
        ]);
    }

    /**
     * A log that cannot be read or written does not change an erasure: no exception comes out, only a fixed line in the PHP log.
     */
    public function test_a_failing_log_never_changes_the_outcome(): void {
        $reader = new class extends erasure_audit_reader {
            #[\Override]
            public function has(string $type, int $task, erasure_key $key): bool {
                throw new \RuntimeException('the log is down: a secret name');
            }
        };
        $logfile = tempnam(sys_get_temp_dir(), 't142');
        $previous = ini_set('error_log', $logfile);
        try {
            $written = (new erasure_audit($reader))->stopped_once(new erasure_key((int) $this->user->id, [7]), 10, 'attempts_exhausted');
        } finally {
            ini_set('error_log', (string) $previous);
        }

        $this->assertFalse($written);
        $line = (string) file_get_contents($logfile);
        unlink($logfile);
        $this->assertStringContainsString("the audit event 'stopped' of an erasure could not be recorded", $line);
        $this->assertStringNotContainsString('secret', $line);
        $this->assertCount(0, $this->reader->events_of((int) $this->user->id));
    }

    /**
     * With the default buffer of the log store (50 events) an event would stay in memory until the end of the process: the audit flushes it,
     * so it is in the log when the call returns and the next events are still written.
     */
    public function test_each_event_is_in_the_log_when_the_call_returns(): void {
        set_config('buffersize', 50, 'logstore_standard');
        get_log_manager(true);
        $reader = new erasure_audit_reader();
        $audit = new erasure_audit($reader);
        $key = new erasure_key((int) $this->user->id, [7]);

        $this->assertTrue($audit->deferred_once($key, 10, 'lock_unavailable', 8));
        $this->assertCount(1, $reader->events_of((int) $this->user->id), 'Visible at once, not at the end of the process');
        $this->assertTrue($audit->stopped_once($key, 10, 'attempts_exhausted'));
        $this->assertTrue($audit->completed_once($key, 10, erasure_event_fact::VIA_RETRY));
        $this->assertCount(3, $reader->events_of((int) $this->user->id));
    }

    /**
     * Without a log store the reader answers nothing and says so; the audit does not fail and the state falls back to the data.
     */
    public function test_without_a_log_store_the_reader_is_unavailable(): void {
        $this->assertTrue($this->reader->available());
        set_config('enabled_stores', '', 'tool_log');
        get_log_manager(true);
        $reader = new erasure_audit_reader();

        $this->assertFalse($reader->available());
        $this->assertSame([], $reader->events_of((int) $this->user->id));
        $this->assertFalse($reader->has('stopped', 10, new erasure_key((int) $this->user->id, [7])));
        $this->assertTrue((new erasure_audit($reader))->stopped_once(new erasure_key((int) $this->user->id, [7]), 10, 'attempts_exhausted'));
    }
}
