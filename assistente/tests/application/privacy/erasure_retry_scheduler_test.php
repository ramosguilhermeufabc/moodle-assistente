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

use core\task\manager;
use local_assistente\domain\privacy\erasure_key;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\task\retry_student_erasure;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The retry tasks of the erasures (T142; DEC-58): one task per case, deduplicated by Moodle on the canonical key, 8 attempts, no user on
 * the task, and a clean-up that never touches a task that is active, running or newer.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(erasure_retry_scheduler::class)]
#[CoversClass(retry_student_erasure::class)]
final class erasure_retry_scheduler_test extends \advanced_testcase {
    /** @var erasure_retry_scheduler */
    private erasure_retry_scheduler $scheduler;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->scheduler = new erasure_retry_scheduler();
    }

    /**
     * The rows of the retry task in the queue of Moodle.
     *
     * @return \stdClass[]
     */
    private function rows(): array {
        global $DB;
        return $DB->get_records('task_adhoc', ['classname' => '\\' . retry_student_erasure::class], 'id');
    }

    /**
     * The same case in any order of the courses is one task, with 8 attempts, the canonical custom data and no user.
     */
    public function test_one_task_per_case_with_eight_attempts_and_no_user(): void {
        $user = $this->getDataGenerator()->create_user();

        $first = $this->scheduler->schedule(new erasure_key((int) $user->id, [8, 7]), 'lock_unavailable');
        $again = $this->scheduler->schedule(new erasure_key((int) $user->id, [7, 8, 7]), 'lock_unavailable');

        $this->assertSame($first, $again, 'Moodle refused the duplicate and the existing task is returned');
        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame(erasure_retry_scheduler::ATTEMPTS, (int) $row->attemptsavailable);
        $this->assertSame(8, erasure_retry_scheduler::ATTEMPTS);
        $this->assertSame('{"userid":' . $user->id . ',"courseids":[7,8]}', $row->customdata);
        $this->assertEmpty($row->userid, 'A task of a user is refused by Moodle when the account is deleted');
        $this->assertSame(0, (int) $row->faildelay);
    }

    /**
     * Different sets of courses, and different students, are different tasks.
     */
    public function test_other_sets_and_students_are_other_tasks(): void {
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();

        $ids = [
            $this->scheduler->schedule(new erasure_key((int) $one->id, [7]), 'lock_unavailable'),
            $this->scheduler->schedule(new erasure_key((int) $one->id, [8]), 'lock_unavailable'),
            $this->scheduler->schedule(new erasure_key((int) $one->id, [7, 8]), 'lock_unavailable'),
            $this->scheduler->schedule(new erasure_key((int) $two->id, [7]), 'lock_unavailable'),
        ];

        $this->assertCount(4, array_unique($ids));
        $this->assertCount(4, $this->rows());
    }

    /**
     * The account of the student can already be deleted or suspended: the retry is still scheduled (it would not be with a user on the task).
     */
    public function test_a_deleted_account_can_be_scheduled(): void {
        $deleted = $this->getDataGenerator()->create_user();
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        delete_user($deleted);

        $a = $this->scheduler->schedule(new erasure_key((int) $deleted->id, [7]), 'lock_unavailable');
        $b = $this->scheduler->schedule(new erasure_key((int) $suspended->id, [7]), 'lock_unavailable');

        $this->assertGreaterThan(0, $a);
        $this->assertGreaterThan(0, $b);
    }

    /**
     * A task that stopped (no attempts left) does not block a new one for the same case: Moodle does not count it as scheduled.
     */
    public function test_a_stopped_task_does_not_block_a_new_request(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $key = new erasure_key((int) $user->id, [7]);
        $old = $this->scheduler->schedule($key, 'lock_unavailable');
        $DB->set_field('task_adhoc', 'attemptsavailable', 0, ['id' => $old]);

        $new = $this->scheduler->schedule($key, 'lock_unavailable');

        $this->assertNotSame($old, $new);
        $this->assertCount(2, $this->rows());
    }

    /**
     * The mark of an erasure that needs an action has no attempts, is never offered to the cron and is not duplicated.
     */
    public function test_the_mark_is_visible_and_never_runs(): void {
        $user = $this->getDataGenerator()->create_user();
        $key = new erasure_key((int) $user->id, [7]);

        $mark = $this->scheduler->mark($key);
        $this->assertSame($mark, $this->scheduler->mark($key), 'The existing mark is reused');

        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertSame(0, (int) $rows[$mark]->attemptsavailable);
        manager::clear_static_caches();
        $this->assertNull(manager::get_next_adhoc_task(time() + 86400, true, '\\' . retry_student_erasure::class));
    }

    /**
     * The tasks of a student come as facts: the courses, the attempts left and whether they run; other students and strange data are left out.
     */
    public function test_the_tasks_of_a_student(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $a = $this->scheduler->schedule(new erasure_key((int) $user->id, [7, 8]), 'lock_unavailable');
        $b = $this->scheduler->schedule(new erasure_key((int) $user->id, [9]), 'lock_unavailable');
        $this->scheduler->schedule(new erasure_key((int) $other->id, [7]), 'lock_unavailable');
        $DB->set_field('task_adhoc', 'attemptsavailable', 3, ['id' => $b]);
        $DB->set_field('task_adhoc', 'timestarted', time(), ['id' => $b]);
        $strange = new retry_student_erasure();
        $strange->set_custom_data(['nothing' => 'here']);
        manager::queue_adhoc_task($strange);

        $facts = $this->scheduler->tasks_of((int) $user->id);

        $this->assertCount(2, $facts);
        $byid = array_column(array_map(fn($f) => (array) $f, $facts), null, 'id');
        $this->assertSame([[7, 8], 8, false], [$byid[$a]['courses'], $byid[$a]['attempts'], $byid[$a]['running']]);
        $this->assertSame([[9], 3, true], [$byid[$b]['courses'], $byid[$b]['attempts'], $byid[$b]['running']]);
    }

    /**
     * The clean-up deletes only the stopped, not running, older tasks whose courses the completed erasure covers; it keeps a task with
     * attempts left (even if it covers more), a running one, a newer one and one of another student.
     */
    public function test_the_cleanup_keeps_every_task_that_is_active(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $key = fn(array $courses, ?\stdClass $who = null) => new erasure_key((int) ($who ?? $user)->id, $courses);
        $obsolete = $this->scheduler->schedule($key([8]), 'lock_unavailable');
        $DB->set_field('task_adhoc', 'attemptsavailable', 0, ['id' => $obsolete]);               // Stopped, older, covered: obsolete.
        $sameactive = $this->scheduler->schedule($key([8]), 'lock_unavailable');                 // Same case, with attempts left, older: kept.
        $active = $this->scheduler->schedule($key([7, 8, 9]), 'lock_unavailable');               // Attempts left, covers more: kept.
        $running = $this->scheduler->schedule($key([7]), 'lock_unavailable');
        $DB->set_field('task_adhoc', 'attemptsavailable', 0, ['id' => $running]);
        $DB->set_field('task_adhoc', 'timestarted', time(), ['id' => $running]);                 // Stopped but running: kept.
        $foreign = $this->scheduler->schedule($key([8], $other), 'lock_unavailable');
        $DB->set_field('task_adhoc', 'attemptsavailable', 0, ['id' => $foreign]);                // Another student, stopped: kept.
        $completed = $this->scheduler->schedule($key([7, 8]), 'lock_unavailable');               // The completed one.
        $newer = $this->scheduler->schedule($key([7]), 'lock_unavailable');
        $DB->set_field('task_adhoc', 'attemptsavailable', 0, ['id' => $newer]);                  // Covered and stopped, but newer: kept.

        $deleted = $this->scheduler->remove_obsolete($key([7, 8]), $completed);

        $this->assertSame(1, $deleted);
        $left = array_keys($this->rows());
        $this->assertEqualsCanonicalizing([$sameactive, $active, $running, $foreign, $completed, $newer], $left);
        $this->assertNotContains($obsolete, $left);
    }

    /**
     * Only a lock that did not come and events that kept changing are worth a retry; nothing else, not a database failure.
     */
    public function test_which_failures_are_transient(): void {
        $this->assertSame('lock_unavailable', erasure_retry_scheduler::reason_of(new lock_unavailable_exception('x')));
        $this->assertSame('event_set_unstable', erasure_retry_scheduler::reason_of(new student_event_stabilisation_exception('x')));
        $this->assertNull(erasure_retry_scheduler::reason_of(new \dml_write_exception('x')));
        $this->assertNull(erasure_retry_scheduler::reason_of(new \dml_exception('x')));
        $this->assertNull(erasure_retry_scheduler::reason_of(new \RuntimeException('x')));
        $this->assertNull(erasure_retry_scheduler::reason_of(new \InvalidArgumentException('x')));
    }
}
