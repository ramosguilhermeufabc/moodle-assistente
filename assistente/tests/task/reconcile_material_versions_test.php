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

use core\task\manager;
use local_assistente\domain\event\event_type;
use local_assistente\domain\material\material_availability;
use local_assistente\domain\material_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the task that makes current the completed versions that are not (CL-51, completed versions only).
 *
 * The rule of the switch (DEC-62), the lock and the transaction are those of material_repository::switch_current(), already proved by
 * material_repository_test (Q16) and material_monotonic_concurrency_test (M-12, M-13); what is tested here is what the task adds: it takes
 * up the candidates, changes nothing but the pointer, repeats without harm, treats a version overtaken meanwhile as a normal outcome and does
 * not stop at a material that fails. The candidates that the task would have found are replaced by a stale list through its seam.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reconcile_material_versions::class)]
final class reconcile_material_versions_test extends \advanced_testcase {
    /** @var material_repository */
    private material_repository $repository;

    /**
     * Real transactions and locks are used by the repository.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new material_repository();
        $config = new config_repository();
        $config->create(2, true);
        $config->create(3, false);
    }

    /**
     * A material with its versions, all completed, and the one given as current (none when null).
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $versions how many completed versions
     * @param int|null $current number of the version that is current
     * @return int[] the id of the material and then the ids of the versions, in the order of their numbers
     */
    private function material(int $courseid, int $cmid, int $versions, ?int $current = null): array {
        $id = $this->repository->register($courseid, 10, $cmid, material_type::PDF, 'Apostila', material_availability::AVAILABLE, 1000);
        $ids = [];
        for ($n = 1; $n <= $versions; $n++) {
            $ids[$n] = $this->repository->add_version($id, "hash-{$cmid}-{$n}", material_availability::AVAILABLE);
            $this->repository->complete_summary($ids[$n], "resumo {$cmid}-{$n}");
            if ($n === $current) {
                $this->repository->switch_current($id, $ids[$n]);
            }
        }
        return [$id, ...array_values($ids)];
    }

    /**
     * The task with the candidates given, instead of the ones found in the database, and with the repository given (the default when null).
     *
     * @param int[] $candidates id of the version, by id of the material
     * @param material_repository|null $repository
     * @param \stdClass[]|null $pending the pending versions read earlier (by id of the version); the ones found now when null
     * @param \Closure|null $events replaces the reading of the active events (to make it fail); the real reading when null
     * @return reconcile_material_versions
     */
    private function task_with(
        array $candidates,
        ?material_repository $repository = null,
        ?array $pending = null,
        ?\Closure $events = null
    ): reconcile_material_versions {
        return new class ($candidates, $repository, $pending, $events) extends reconcile_material_versions {
            /**
             * @param int[] $candidates
             * @param material_repository|null $custom
             * @param \stdClass[]|null $pending
             * @param \Closure|null $events
             */
            public function __construct(
                private readonly array $candidates,
                private readonly ?material_repository $custom,
                private readonly ?array $pending,
                private readonly ?\Closure $events
            ) {
            }

            #[\Override]
            protected function modules_with_active_events(): array {
                return $this->events !== null ? ($this->events)() : parent::modules_with_active_events();
            }

            #[\Override]
            protected function pending_candidates(material_repository $repository): array {
                return $this->pending ?? parent::pending_candidates($repository);
            }

            #[\Override]
            protected function repository(): material_repository {
                return $this->custom ?? parent::repository();
            }

            #[\Override]
            protected function candidates(material_repository $repository): array {
                return $this->candidates;
            }
        };
    }

    /**
     * The id of the current version of a material.
     *
     * @param int $materialid
     * @return int|null
     */
    private function current_of(int $materialid): ?int {
        $current = $this->repository->find($materialid)->current_version;
        return $current === null ? null : (int) $current;
    }

    /**
     * Every row of the versions and of the materials, the pointer aside.
     *
     * @return array
     */
    private function snapshot(): array {
        global $DB;
        $materials = array_map(static function (\stdClass $row): \stdClass {
            unset($row->current_version);
            return $row;
        }, $DB->get_records('local_assistente_material', [], 'id ASC'));
        return [$materials, $DB->get_records('local_assistente_material_version', [], 'id ASC')];
    }

    /**
     * The task is installed from db/tasks.php, every 15 minutes, with a name in each language of the plugin.
     */
    public function test_task_is_installed_with_a_name_in_both_languages(): void {
        global $CFG;
        $task = manager::get_scheduled_task(reconcile_material_versions::class);

        $this->assertNotFalse($task, 'The task must be installed from db/tasks.php');
        $this->assertSame('*/15', $task->get_minute());
        $this->assertSame('Reconcile the material versions left behind by a failure (completed to current, abandoned pending to failed)', $task->get_name());
        foreach (['en', 'pt_br'] as $language) {
            $string = [];
            include($CFG->dirroot . '/local/assistente/lang/' . $language . '/local_assistente.php');
            $this->assertNotEmpty($string['task_reconcile_material_versions'] ?? '', "Missing the name of the task in {$language}");
        }
    }

    /**
     * The candidates are promoted, and nothing else changes: no version is created, changed or removed (the summary, the status and the
     * availability included), the material keeps everything but the pointer, a course with the assistant disabled is left alone, and a
     * second run does nothing and does not fail.
     */
    public function test_it_promotes_the_candidates_changes_only_the_pointer_and_is_repeatable(): void {
        [$none, , $nonetop] = $this->material(2, 21, 2);
        [$above, , , $abovetop] = $this->material(2, 22, 3, 1);
        [$done, , $donetop] = $this->material(2, 23, 2, 2);
        [$disabled] = $this->material(3, 24, 1);
        $before = $this->snapshot();
        $this->expectOutputRegex(
            '/2 candidates, 2 promoted, 0 overtaken, 0 removed, 0 failed.*0 candidates, 0 promoted, 0 overtaken, 0 removed, 0 failed/s'
        );

        (new reconcile_material_versions())->execute();
        $this->assertSame($nonetop, $this->current_of($none), 'No current version: the greatest completed');
        $this->assertSame($abovetop, $this->current_of($above), 'Above the current one: the greatest completed');
        $this->assertSame($donetop, $this->current_of($done), 'Already current: left as it is');
        $this->assertNull($this->current_of($disabled), 'A course with the assistant disabled is not reconciled');
        $this->assertEquals($before, $this->snapshot(), 'Only the pointer of the materials may change');

        (new reconcile_material_versions())->execute();
        $this->assertSame($nonetop, $this->current_of($none));
        $this->assertSame($abovetop, $this->current_of($above));
        $this->assertEquals($before, $this->snapshot());
    }

    /**
     * A candidate overtaken meanwhile (a lower number than the current one, or the current one itself) is a normal outcome: counted, not a
     * failure; the pointer does not go back, nothing is written, and the other candidates are promoted.
     */
    public function test_a_candidate_overtaken_meanwhile_is_a_normal_outcome(): void {
        [$lower, $lowerfirst, $lowersecond] = $this->material(2, 31, 2, 2);
        [$equal, , $equalsecond] = $this->material(2, 32, 2, 2);
        [$next, , $nexttop] = $this->material(2, 33, 2);
        $before = $this->snapshot();
        $this->expectOutputRegex('/3 candidates, 1 promoted, 2 overtaken, 0 removed, 0 failed/');

        $this->task_with([$lower => $lowerfirst, $equal => $equalsecond, $next => $nexttop])->execute();

        $this->assertSame($lowersecond, $this->current_of($lower), 'A lower number never replaces the current version');
        $this->assertSame($equalsecond, $this->current_of($equal));
        $this->assertSame($nexttop, $this->current_of($next));
        $this->assertEquals($before, $this->snapshot());
    }


    /**
     * A material removed after the candidates were read is not a failure: it is counted apart, it is ignored, the task ends without raising
     * anything and the materials after it are promoted. The candidates are the ones really found before the removal.
     */
    public function test_a_material_removed_after_the_candidates_were_read_is_ignored(): void {
        global $DB;
        [$gone] = $this->material(2, 51, 2);
        [$next, , $nexttop] = $this->material(2, 52, 2);
        $candidates = $this->repository->find_unpromoted_completed_versions();
        $this->assertCount(2, $candidates);
        $this->assertArrayHasKey($gone, $candidates);
        $DB->delete_records('local_assistente_material_version', ['material_id' => $gone]);
        $DB->delete_records('local_assistente_material', ['id' => $gone]);
        $this->expectOutputRegex('/\A(?!.*material \d+, version \d+:).*2 candidates, 1 promoted, 0 overtaken, 1 removed, 0 failed/s');

        $this->task_with($candidates)->execute();

        $this->assertNull($this->repository->find($gone));
        $this->assertSame($nexttop, $this->current_of($next), 'The material after the removed one is reconciled');
    }

    /**
     * The same exception with the material still there is not a removal: a candidate that points to a version that does not exist is a
     * failure of integrity, counted as such, written with its place, and signalled to Moodle at the end.
     */
    public function test_a_missing_version_of_an_existing_material_is_a_failure_not_a_removal(): void {
        [$broken] = $this->material(2, 61, 1);
        [$next, , $nexttop] = $this->material(2, 62, 2);
        $this->expectOutputRegex(
            '/material ' . $broken . ', version 999999: dml_missing_record_exception \(code \S*\) at material_repository\.php:\d+.*'
            . '2 candidates, 1 promoted, 0 overtaken, 0 removed, 1 failed/s'
        );

        try {
            $this->task_with([$broken => 999999, $next => $nexttop])->execute();
            $this->fail('The run must signal the failure to Moodle');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('failed for 1 of 2 candidates', $e->getMessage());
        }

        $this->assertNull($this->current_of($broken));
        $this->assertSame($nexttop, $this->current_of($next), 'The material after the failure is reconciled');
    }

    /**
     * A real technical failure: the lock of the material is held, so the switch cannot start. The candidate is counted as a failure and
     * written with the kind of exception and its place; the candidate after it is still promoted; and the run ends by raising an exception, so
     * that Moodle shows it as failed. The lock is held in the same process, which tests how the task treats lock_unavailable_exception; it is
     * not evidence of the exclusion between processes (M-07, M-12, M-13).
     */
    public function test_a_failure_by_the_lock_is_counted_the_others_continue_and_the_run_signals_failure(): void {
        [$locked, , $lockedtop] = $this->material(2, 71, 2);
        [$next, , $nexttop] = $this->material(2, 72, 2);
        $repository = new material_repository(new unit_of_work(1));
        $this->expectOutputRegex(
            '/material ' . $locked . ', version ' . $lockedtop . ': .*lock_unavailable_exception \(code \S*\) at unit_of_work\.php:\d+.*'
            . '2 candidates, 1 promoted, 0 overtaken, 0 removed, 1 failed/s'
        );

        $lock = unit_of_work::lock_factory()->get_lock(unit_of_work::material_resource($locked), 1);
        $this->assertNotFalse($lock);
        try {
            $this->task_with([$locked => $lockedtop, $next => $nexttop], $repository)->execute();
            $this->fail('The run must signal the failure to Moodle');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('failed for 1 of 2 candidates', $e->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertNull($this->current_of($locked), 'Nothing was written to the material whose lock was held');
        $this->assertSame($nexttop, $this->current_of($next), 'The candidate after the failure is promoted');
    }

    /**
     * A material with `$below` completed versions and, above them, one pending version.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $below
     * @return int[] the id of the material and the id of the pending version
     */
    private function material_with_pending(int $courseid, int $cmid, int $below = 0): array {
        $id = $this->repository->register($courseid, 10, $cmid, material_type::PDF, 'Apostila', material_availability::AVAILABLE, 1000);
        for ($n = 1; $n <= $below; $n++) {
            $this->repository->complete_summary(
                $this->repository->add_version($id, "hash-{$cmid}-{$n}", material_availability::AVAILABLE),
                "resumo {$cmid}-{$n}"
            );
        }
        return [$id, $this->repository->add_version($id, "hash-{$cmid}-pending", material_availability::AVAILABLE)];
    }

    /**
     * An event of the course in the state given.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string $state pending, processing, processed or failure
     * @param event_type $type
     * @return \stdClass
     */
    private function event(int $courseid, int $cmid, string $state, event_type $type = event_type::MATERIAL_CHANGED): \stdClass {
        static $occurred = 1000;
        $events = new event_repository();
        $payload = $type === event_type::MATERIAL_CHANGED
            ? ['cmid' => $cmid, 'moodle_event' => 'course_module_updated', 'occurred_at' => ++$occurred]
            : ['cmid' => $cmid];
        $event = $events->create_event($courseid, $type, $payload, 100);
        if ($state === 'processing') {
            $events->begin_attempt((int) $event->id, 200);
        } else if ($state === 'processed') {
            $events->complete_attempt((int) $events->begin_attempt((int) $event->id, 200)->id, 210);
        } else if ($state === 'failure') {
            for ($n = 0; $n < 3; $n++) {
                $events->fail_attempt((int) $events->begin_attempt((int) $event->id, 200 + $n)->id, 210 + $n);
            }
            $this->assertSame('failure', $events->find((int) $event->id)->status);
        }
        return $events->find((int) $event->id);
    }

    /**
     * The status of a version.
     *
     * @param int $versionid
     * @return string
     */
    private function status_of(int $versionid): string {
        return $this->repository->find_version_by_id($versionid)->summary_status;
    }

    /**
     * An abandoned pending version is marked failed, and nothing else of the second step changes: the summaries stay as they were and the
     * pointer of its material is not touched (the only pointer that moves is the one of the completed version promoted by the first step). A pending
     * version that a completed one supersedes, one of a course with the assistant disabled, a version already failed and one completed
     * are not touched, and a second run does nothing and does not fail.
     */
    public function test_an_abandoned_pending_version_is_marked_failed_and_the_run_is_repeatable(): void {
        global $DB;
        [$abandoned, $abandonedversion] = $this->material_with_pending(2, 81);
        [$superseded, $supersededversion] = $this->material_with_pending(2, 82);
        $laterid = $this->repository->add_version($superseded, 'hash-82-later', material_availability::AVAILABLE);
        $this->repository->complete_summary($laterid, 'resumo 82-later');
        [, $disabledversion] = $this->material_with_pending(3, 83);
        [$failed, $failedversion] = $this->material_with_pending(2, 84);
        $this->repository->fail_summary($failedversion);
        [$current, , $currentversion] = $this->material(2, 85, 2, 2);
        $summaries = $DB->get_records_menu('local_assistente_material_version', [], 'id ASC', 'id, summary');
        $this->expectOutputRegex(
            '/1 pending candidates, 1 marked failed, 0 with an active event, 0 kept, 0 removed, 0 failed.*'
            . '0 pending candidates, 0 marked failed, 0 with an active event, 0 kept, 0 removed, 0 failed/s'
        );

        (new reconcile_material_versions())->execute();
        $this->assertSame('failed', $this->status_of($abandonedversion));
        $this->assertSame('pending', $this->status_of($supersededversion), 'A superseded pending version is never changed');
        $this->assertSame('pending', $this->status_of($disabledversion), 'A course with the assistant disabled is not touched');
        $this->assertSame('failed', $this->status_of($failedversion));
        $this->assertSame('completed', $this->status_of($currentversion));
        $this->assertNull($this->current_of($abandoned), 'The pointer of the material is not touched');
        $this->assertSame($laterid, $this->current_of($superseded), 'Only the promotion of the completed version moved a pointer');
        $this->assertSame($currentversion, $this->current_of($current));
        $this->assertEquals($summaries, $DB->get_records_menu('local_assistente_material_version', [], 'id ASC', 'id, summary'));

        (new reconcile_material_versions())->execute();
        $this->assertSame('failed', $this->status_of($abandonedversion));
        $this->assertSame('pending', $this->status_of($supersededversion));
        $this->assertSame('failed', $this->status_of($failedversion), 'A failed version is not taken up again');
        $this->assertNull($this->current_of($failed));
        $this->assertSame($currentversion, $this->current_of($current));
    }

    /**
     * An event `material_changed` of the same course and module, pending or processing, stops the classification. An event that is over
     * (processed or failure), one of another module, one of another type and one of the same module number in another course do not.
     */
    public function test_an_active_event_of_the_same_module_prevents_the_classification(): void {
        (new config_repository())->create(4, true);
        [, $pending] = $this->material_with_pending(2, 91);
        $this->event(2, 91, 'pending');
        [, $processing] = $this->material_with_pending(2, 92);
        $this->event(2, 92, 'processing');
        [, $processed] = $this->material_with_pending(2, 93);
        $this->event(2, 93, 'processed');
        [, $failure] = $this->material_with_pending(2, 94);
        $this->event(2, 94, 'failure');
        [, $othermodule] = $this->material_with_pending(2, 95);
        $this->event(2, 96, 'pending');
        [, $othertype] = $this->material_with_pending(2, 97);
        $this->event(2, 97, 'pending', event_type::QUIZ_PUBLISHED);
        [, $othercourse] = $this->material_with_pending(2, 98);
        $this->event(4, 98, 'pending');
        $this->expectOutputRegex('/7 pending candidates, 5 marked failed, 2 with an active event, 0 kept, 0 removed, 0 failed/');

        (new reconcile_material_versions())->execute();

        $this->assertSame('pending', $this->status_of($pending), 'An event pending for the module');
        $this->assertSame('pending', $this->status_of($processing), 'An event processing for the module');
        $this->assertSame('failed', $this->status_of($processed));
        $this->assertSame('failed', $this->status_of($failure));
        $this->assertSame('failed', $this->status_of($othermodule));
        $this->assertSame('failed', $this->status_of($othertype));
        $this->assertSame('failed', $this->status_of($othercourse));
    }

    /**
     * A version that stopped being pending, or that a completed version superseded, between the reading of the candidates and the change is
     * kept as it is: it is read again inside the lock.
     */
    public function test_a_version_that_changed_after_the_candidates_were_read_is_kept(): void {
        [, $completed] = $this->material_with_pending(2, 101);
        [$superseded, $supersededversion] = $this->material_with_pending(2, 102);
        $pending = $this->repository->find_pending_without_later_completed();
        $this->assertCount(2, $pending);
        $this->repository->complete_summary($completed, 'resumo 101');
        $laterid = $this->repository->add_version($superseded, 'hash-102-later', material_availability::AVAILABLE);
        $this->repository->complete_summary($laterid, 'resumo 102-later');
        $this->expectOutputRegex('/2 pending candidates, 0 marked failed, 0 with an active event, 2 kept, 0 removed, 0 failed/');

        $this->task_with([], null, $pending)->execute();

        $this->assertSame('completed', $this->status_of($completed));
        $this->assertSame('resumo 101', $this->repository->find_version_by_id($completed)->summary);
        $this->assertSame('pending', $this->status_of($supersededversion));
    }

    /**
     * A material removed after the candidates were read is not a failure: it is counted apart and ignored, the task ends without raising
     * anything and the candidates after it are marked.
     */
    public function test_a_material_removed_after_the_pending_candidates_were_read_is_ignored(): void {
        global $DB;
        [$gone] = $this->material_with_pending(2, 111);
        [, $next] = $this->material_with_pending(2, 112);
        $pending = $this->repository->find_pending_without_later_completed();
        $this->assertCount(2, $pending);
        $DB->delete_records('local_assistente_material_version', ['material_id' => $gone]);
        $DB->delete_records('local_assistente_material', ['id' => $gone]);
        $this->expectOutputRegex(
            '/\A(?!.*material \d+, version \d+:).*2 pending candidates, 1 marked failed, 0 with an active event, 0 kept, 1 removed, 0 failed/s'
        );

        $this->task_with([], null, $pending)->execute();

        $this->assertNull($this->repository->find($gone));
        $this->assertSame('failed', $this->status_of($next), 'The candidate after the removed one is marked');
    }

    /**
     * A real technical failure: the lock of the material is held, so the transition cannot start. The candidate is counted as a failure and
     * written with the kind of exception and its place, it stays pending, the candidate after it is still marked and the run ends by raising
     * an exception. The lock is held in the same process, which tests how the task treats lock_unavailable_exception; it is not evidence of
     * the exclusion between processes (M-07, M-12, M-13).
     */
    public function test_a_failure_by_the_lock_on_a_pending_version_is_counted_and_the_run_signals_failure(): void {
        [$locked, $lockedversion] = $this->material_with_pending(2, 121);
        [, $next] = $this->material_with_pending(2, 122);
        $repository = new material_repository(new unit_of_work(1));
        $this->expectOutputRegex(
            '/material ' . $locked . ', version ' . $lockedversion . ': .*lock_unavailable_exception \(code \S*\) at unit_of_work\.php:\d+.*'
            . '2 pending candidates, 1 marked failed, 0 with an active event, 0 kept, 0 removed, 1 failed/s'
        );

        $lock = unit_of_work::lock_factory()->get_lock(unit_of_work::material_resource($locked), 1);
        $this->assertNotFalse($lock);
        try {
            $this->task_with([], $repository)->execute();
            $this->fail('The run must signal the failure to Moodle');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('failed for 1 of 2 candidates', $e->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertSame('pending', $this->status_of($lockedversion), 'Nothing was written to the version whose lock was held');
        $this->assertSame('failed', $this->status_of($next), 'The candidate after the failure is marked');
    }

    /**
     * When the events cannot be read nobody can say whether a version is being worked on, so nothing is classified: no pending version is
     * marked failed, the summaries and the pointers stay as they were, the failure is written with its place, and the run ends by raising an
     * exception so that Moodle shows it as failed. The failure of the reading is made by the seam of the task; the candidates are the ones
     * the repository really finds.
     */
    public function test_a_failure_to_read_the_events_classifies_nothing_and_the_run_signals_failure(): void {
        global $DB;
        [$first, $firstversion] = $this->material_with_pending(2, 141);
        [$second, $secondversion] = $this->material_with_pending(2, 142);
        $this->assertCount(2, $this->repository->find_pending_without_later_completed());
        $summaries = $DB->get_records_menu('local_assistente_material_version', [], 'id ASC', 'id, summary');
        $statuses = $DB->get_records_menu('local_assistente_material_version', [], 'id ASC', 'id, summary_status');
        $pointers = $DB->get_records_menu('local_assistente_material', [], 'id ASC', 'id, current_version');
        $this->expectOutputRegex(
            '/the events could not be read, no pending version was changed: RuntimeException \(code 0\) at '
            . 'reconcile_material_versions_test\.php:\d+.*'
            . '2 pending candidates, 0 marked failed, 0 with an active event, 0 kept, 0 removed, 0 failed/s'
        );

        try {
            $this->task_with([], null, null, static function (): array {
                throw new \RuntimeException('the events table cannot be read');
            })->execute();
            $this->fail('The run must signal the failure to Moodle');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('failed for 1 of 2 candidates', $e->getMessage());
        }

        $this->assertSame('pending', $this->status_of($firstversion));
        $this->assertSame('pending', $this->status_of($secondversion));
        $this->assertEquals($statuses, $DB->get_records_menu('local_assistente_material_version', [], 'id ASC', 'id, summary_status'));
        $this->assertEquals($summaries, $DB->get_records_menu('local_assistente_material_version', [], 'id ASC', 'id, summary'));
        $this->assertEquals($pointers, $DB->get_records_menu('local_assistente_material', [], 'id ASC', 'id, current_version'));
        $this->assertNull($this->current_of($first));
        $this->assertNull($this->current_of($second));
    }

    /**
     * A pending candidate whose version does not exist but whose material does is not a removal: it is a failure of integrity, counted as
     * such and written with its place, the candidates after it are still marked, and the run ends by raising an exception. The candidate is
     * built from a real one, with the id of the version replaced by an id that does not exist.
     */
    public function test_a_missing_pending_version_of_an_existing_material_is_a_failure_not_a_removal(): void {
        [$broken, $brokenversion] = $this->material_with_pending(2, 151);
        [, $next] = $this->material_with_pending(2, 152);
        $found = $this->repository->find_pending_without_later_completed();
        $this->assertCount(2, $found);
        $missing = 999999;
        $this->assertNull($this->repository->find_version_by_id($missing));
        $pending = [$missing => $found[$brokenversion]] + [$next => $found[$next]];
        $this->expectOutputRegex(
            '/material ' . $broken . ', version ' . $missing . ': dml_missing_record_exception \(code \S*\) at material_repository\.php:\d+.*'
            . '2 pending candidates, 1 marked failed, 0 with an active event, 0 kept, 0 removed, 1 failed/s'
        );

        try {
            $this->task_with([], null, $pending)->execute();
            $this->fail('The run must signal the failure to Moodle');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('failed for 1 of 2 candidates', $e->getMessage());
        }

        $this->assertNotNull($this->repository->find($broken), 'The material still exists: it is not a removal');
        $this->assertSame('pending', $this->status_of($brokenversion), 'The real version of that material was not touched');
        $this->assertSame('failed', $this->status_of($next), 'The candidate after the failure is marked');
    }
}
