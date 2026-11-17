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

namespace local_assistente\domain\privacy;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The state of an erasure and the case that is open (T142; DEC-58). Pure: the facts are given, nothing is read.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(erasure_state_calculator::class)]
#[CoversClass(erasure_result::class)]
#[CoversClass(erasure_state::class)]
final class erasure_state_calculator_test extends \basic_testcase {
    /** @var erasure_state_calculator */
    private erasure_state_calculator $calculator;

    protected function setUp(): void {
        parent::setUp();
        $this->calculator = new erasure_state_calculator();
    }

    /**
     * The facts of a key.
     *
     * @param int[] $courses the courses of the key
     * @param int[] $ids courses with residue by identifier
     * @param bool $name whether the name is available
     * @param int[] $texts courses with a text that cites the name
     * @param erasure_task_fact[] $tasks
     * @param erasure_event_fact[] $events
     * @return erasure_result
     */
    private function outcome(array $courses, array $ids, bool $name, array $texts = [], array $tasks = [], array $events = []): erasure_result {
        return $this->calculator->calculate(new erasure_facts(new erasure_key(5, $courses), $ids, $name, $texts, $tasks, $events));
    }

    private function task(int $id, array $courses, int $attempts, bool $running = false): erasure_task_fact {
        return new erasure_task_fact($id, $courses, $attempts, $running);
    }

    private function event(int $id, string $type, int $task, array $courses, string $detail = ''): erasure_event_fact {
        return new erasure_event_fact($id, $type, $task, $courses, $detail);
    }

    /**
     * There is no state called "completed": the enum holds the five states and nothing that claims more.
     */
    public function test_the_five_states_and_no_completed(): void {
        $this->assertEqualsCanonicalizing(
            ['no_residue_found', 'texts_unverified', 'incomplete', 'pending', 'stopped'],
            array_map(fn($case) => $case->value, erasure_state::cases())
        );
    }

    /**
     * With no residue by identifier: the name unavailable is TEXTS_UNVERIFIED, whatever the tasks and the log say; the name available and
     * no text that cites it is NO_RESIDUE_FOUND, with no claim about what was done.
     */
    public function test_without_residue(): void {
        $this->assertSame(erasure_state::TEXTS_UNVERIFIED, $this->outcome([7], [], false)->state);
        $this->assertSame(erasure_state::TEXTS_UNVERIFIED, $this->outcome([7], [], false, [], [$this->task(10, [7], 5)])->state, 'A task does not verify texts');
        $this->assertSame(
            erasure_state::TEXTS_UNVERIFIED,
            $this->outcome([7], [], false, [], [], [$this->event(3, 'completed', 2, [7], 'retry_task')])->state,
            'A completed event does not verify texts either'
        );
        $clean = $this->outcome([7], [], true);
        $this->assertSame(erasure_state::NO_RESIDUE_FOUND, $clean->state);
        $this->assertFalse($clean->closedbyretry);
        $this->assertNull($clean->closedby);
        $this->assertSame(erasure_result::EVIDENCE_DATA, $clean->evidence);
    }

    /**
     * Residue in the texts counts only when the name is available; with the name unavailable the texts are not looked at.
     */
    public function test_a_text_that_cites_the_name_is_residue(): void {
        $this->assertSame(erasure_state::INCOMPLETE, $this->outcome([7], [], true, [7])->state);
        $this->assertSame(erasure_state::TEXTS_UNVERIFIED, $this->outcome([7], [], false, [7])->state, 'Unavailable name: nothing to say about texts');
        $this->assertSame(erasure_state::PENDING, $this->outcome([7], [], true, [7], [$this->task(10, [7], 3)])->state);
    }

    /**
     * Residue: PENDING with a task that has attempts left and covers it; a task of other courses does not count.
     */
    public function test_pending(): void {
        $this->assertSame(erasure_state::PENDING, $this->outcome([7], [7], true, [], [$this->task(10, [7], 8)])->state);
        $this->assertSame(erasure_state::PENDING, $this->outcome([7], [7], true, [], [$this->task(10, [7], 1, true)])->state, 'Running counts');
        $this->assertSame(erasure_state::INCOMPLETE, $this->outcome([7], [7], true, [], [$this->task(10, [9], 8)])->state, 'Other courses');
    }

    /**
     * Residue: STOPPED by a row with no attempts left, or by a latest `stopped` event, even when the row is gone; INCOMPLETE when nothing
     * says why the residue is still there. A stopped row older than a completed erasure is stale.
     */
    public function test_stopped_and_incomplete(): void {
        $this->assertSame(erasure_state::STOPPED, $this->outcome([7], [7], true, [], [$this->task(10, [7], 0)])->state, 'The row alone');
        $this->assertSame(
            erasure_state::STOPPED,
            $this->outcome([7], [7], true, [], [], [$this->event(4, 'deferred', 10, [7], 'lock_unavailable'), $this->event(9, 'stopped', 10, [7], 'attempts_exhausted')])->state,
            'The event alone, with the row expired or deleted'
        );
        $this->assertSame(erasure_state::INCOMPLETE, $this->outcome([7], [7], true)->state, 'No task, no record');
        $this->assertSame(
            erasure_state::INCOMPLETE,
            $this->outcome([7], [7], true, [], [$this->task(10, [7], 0)], [$this->event(20, 'completed', 12, [7], 'new_request')])->state,
            'A stopped row older than a completed erasure is stale: the residue came back'
        );
        $this->assertSame(
            erasure_state::INCOMPLETE,
            $this->outcome([7], [7], true, [], [], [$this->event(4, 'deferred', 10, [7]), $this->event(9, 'stopped', 10, [7]), $this->event(15, 'completed', 10, [7], 'new_request')])->state,
            'The latest event is a completed one'
        );
    }

    /**
     * The order of the evaluation: a pending task wins over a stop record, because the work is still going to be done.
     */
    public function test_pending_wins_over_a_stop(): void {
        $facts = $this->outcome([7], [7], true, [], [$this->task(10, [7], 0), $this->task(11, [7], 4)], [$this->event(9, 'stopped', 10, [7])]);
        $this->assertSame(erasure_state::PENDING, $facts->state);
    }

    /**
     * `closed_by_retry` needs a `completed` event of exactly the courses of the key, of its latest generation, made by the retry task.
     */
    public function test_closed_by_retry_is_strict(): void {
        $deferred = $this->event(4, 'deferred', 10, [7, 8], 'lock_unavailable');
        $ok = $this->outcome([7, 8], [], true, [], [], [$deferred, $this->event(8, 'completed', 10, [7, 8], 'retry_task')]);
        $this->assertTrue($ok->closedbyretry);
        $this->assertSame('retry_task', $ok->closedby);
        $this->assertSame(erasure_result::EVIDENCE_DATA_AND_EVENTS, $ok->evidence);

        $byrequest = $this->outcome([7, 8], [], true, [], [], [$deferred, $this->event(8, 'completed', 10, [7, 8], 'new_request')]);
        $this->assertFalse($byrequest->closedbyretry, 'Closed by a new request, not by the retry');
        $this->assertSame('new_request', $byrequest->closedby);

        $superset = $this->outcome([8], [], true, [], [], [$this->event(4, 'deferred', 11, [8]), $this->event(8, 'completed', 10, [7, 8], 'retry_task')]);
        $this->assertFalse($superset->closedbyretry, 'A completed event of other courses never closes this case');
        $this->assertNull($superset->closedby);

        $other = $this->outcome([7], [], true, [], [], [$this->event(8, 'completed', 10, [7, 8], 'retry_task')]);
        $this->assertFalse($other->closedbyretry, 'A key that never had a case');

        $older = $this->outcome([7, 8], [], true, [], [], [
            $deferred, $this->event(8, 'completed', 10, [7, 8], 'retry_task'), $this->event(12, 'deferred', 14, [7, 8], 'lock_unavailable'),
        ]);
        $this->assertFalse($older->closedbyretry, 'The completed event is of an older generation: the case was opened again');

        $wrongtask = $this->outcome([7, 8], [], true, [], [], [$deferred, $this->event(8, 'completed', 99, [7, 8], 'retry_task')]);
        $this->assertFalse($wrongtask->closedbyretry, 'Another generation');

        $before = $this->outcome([7, 8], [], true, [], [], [$this->event(2, 'completed', 10, [7, 8], 'retry_task'), $deferred]);
        $this->assertFalse($before->closedbyretry, 'A completed event that came before the case opened');

        $residue = $this->outcome([7, 8], [7], true, [], [], [$deferred, $this->event(8, 'completed', 10, [7, 8], 'retry_task')]);
        $this->assertFalse($residue->closedbyretry, 'With residue the flag is false');
    }

    /**
     * Overlapping cases, [7,8] and [8], never mix: each key keeps its own flag, and a pending task counts only while there is residue in
     * the courses it shares.
     */
    public function test_overlapping_cases_do_not_mix(): void {
        $t10 = $this->task(10, [7, 8], 8);
        $t11 = $this->task(11, [8], 8);
        $d10 = $this->event(1, 'deferred', 10, [7, 8], 'lock_unavailable');
        $d11 = $this->event(2, 'deferred', 11, [8], 'lock_unavailable');

        // t1/t2: both pending, residue in 7 and 8.
        $this->assertSame(erasure_state::PENDING, $this->outcome([7, 8], [7, 8], true, [], [$t10, $t11], [$d10, $d11])->state);
        $this->assertSame(erasure_state::PENDING, $this->outcome([8], [8], true, [], [$t10, $t11], [$d10, $d11])->state);
        $this->assertSame(erasure_state::PENDING, $this->outcome([7], [7], true, [], [$t10, $t11], [$d10, $d11])->state);

        // t3: T11 completed course 8. The key [8] is clean and closed by the retry; T10 is pending but there is no residue in 8.
        $c11 = $this->event(3, 'completed', 11, [8], 'retry_task');
        $key8 = $this->outcome([8], [], true, [], [$t10], [$d10, $d11, $c11]);
        $this->assertSame(erasure_state::NO_RESIDUE_FOUND, $key8->state);
        $this->assertTrue($key8->closedbyretry);
        $this->assertSame(erasure_state::PENDING, $this->outcome([7, 8], [7], true, [], [$t10], [$d10, $d11, $c11])->state, 'Residue left in course 7');
        $this->assertSame(erasure_state::PENDING, $this->outcome([7], [7], true, [], [$t10], [$d10, $d11, $c11])->state);

        // t4: T10 completed [7,8]: the superset is closed by the retry, [8] keeps its own flag, [7] never had a case of its own.
        $c10 = $this->event(4, 'completed', 10, [7, 8], 'retry_task');
        $all = [$d10, $d11, $c11, $c10];
        $this->assertTrue($this->outcome([7, 8], [], true, [], [], $all)->closedbyretry);
        $this->assertTrue($this->outcome([8], [], true, [], [], $all)->closedbyretry);
        $key7 = $this->outcome([7], [], true, [], [], $all);
        $this->assertSame(erasure_state::NO_RESIDUE_FOUND, $key7->state);
        $this->assertFalse($key7->closedbyretry);
    }

    /**
     * The open case: the latest generation of the exact key that no `completed` event closed.
     */
    public function test_the_open_case(): void {
        $key = new erasure_key(5, [7]);
        $this->assertNull($this->calculator->open_generation($key, []));
        $this->assertSame(10, $this->calculator->open_generation($key, [$this->event(1, 'deferred', 10, [7])]));
        $this->assertNull($this->calculator->open_generation($key, [$this->event(1, 'deferred', 10, [7]), $this->event(2, 'completed', 10, [7], 'retry_task')]));
        $this->assertSame(
            14,
            $this->calculator->open_generation($key, [$this->event(1, 'deferred', 10, [7]), $this->event(2, 'completed', 10, [7], 'retry_task'), $this->event(3, 'deferred', 14, [7])]),
            'Opened again'
        );
        $this->assertNull($this->calculator->open_generation($key, [$this->event(1, 'deferred', 10, [7, 8])]), 'Other courses are another case');
        $this->assertSame(
            20,
            $this->calculator->open_generation($key, [$this->event(1, 'stopped', 20, [7], 'names_unavailable')]),
            'A mark for an unavailable name opens a case too'
        );
    }

    /**
     * Which stopped tasks a completed erasure makes obsolete: only a stopped task, not running, older than the completed one and with
     * courses that it covers. Never a task with attempts left, a running one, a newer one or one with other courses.
     */
    public function test_obsolete_tasks(): void {
        $tasks = [
            $this->task(5, [8], 0),             // Stopped, older, covered: obsolete.
            $this->task(6, [7, 8], 0),          // Stopped, older, covered: obsolete.
            $this->task(7, [8], 3),             // Attempts left: kept.
            $this->task(8, [8], 0, true),       // Running: kept.
            $this->task(12, [8], 0),            // Newer than the completed one: kept.
            $this->task(9, [8, 9], 0),          // Has a course the completed erasure does not cover: kept.
            $this->task(10, [7, 8], 8),         // The completed one itself, with attempts: kept.
            $this->task(4, [7], 0),             // Stopped, older, covered: obsolete.
        ];

        $this->assertSame([5, 6, 4], $this->calculator->obsolete_tasks($tasks, [7, 8], 10));
        $this->assertSame([], $this->calculator->obsolete_tasks($tasks, [7, 8], 4), 'Nothing is older than the oldest');
        $this->assertSame([], $this->calculator->obsolete_tasks([], [7], 10));
    }

    /**
     * Every combination of the facts gives exactly one state, and a success needs no residue, the name and no pending task is ever needed
     * to say so: the invariant of the table.
     */
    public function test_every_combination_has_one_state_and_no_false_success(): void {
        $tasks = [[], [$this->task(10, [7], 8)], [$this->task(10, [7], 0)]];
        $events = [[], [$this->event(1, 'deferred', 10, [7])], [$this->event(1, 'stopped', 10, [7])], [$this->event(1, 'completed', 10, [7], 'retry_task')]];
        $combinations = 0;
        foreach ([[], [7]] as $ids) {
            foreach ([false, true] as $name) {
                foreach ([[], [7]] as $texts) {
                    foreach ($tasks as $t) {
                        foreach ($events as $e) {
                            $state = $this->outcome([7], $ids, $name, $texts, $t, $e)->state;
                            $combinations++;
                            $hasresidue = $ids !== [] || ($name && $texts !== []);
                            if ($state === erasure_state::NO_RESIDUE_FOUND) {
                                $this->assertTrue($name && !$hasresidue, 'A success needs the name and no residue');
                            }
                            if ($hasresidue) {
                                $this->assertContains($state, [erasure_state::PENDING, erasure_state::STOPPED, erasure_state::INCOMPLETE]);
                            }
                            if (!$name && !$hasresidue) {
                                $this->assertSame(erasure_state::TEXTS_UNVERIFIED, $state);
                            }
                        }
                    }
                }
            }
        }
        $this->assertSame(96, $combinations);
    }
}
