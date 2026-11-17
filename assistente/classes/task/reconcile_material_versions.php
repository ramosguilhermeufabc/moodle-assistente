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

use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\version_not_newer_exception;

/**
 * Scheduled task that reconciles the versions of the materials that a failure left behind (CL-51), in two steps and nothing more.
 *
 * 1. A `completed` version that never became current. A version can be completed and not current when the switch of the pointer failed on
 *    every attempt of its event, and nothing but a new event of the same material would take it up. The task takes up the completed version
 *    of the greatest number of each material of a course with the assistant enabled
 *    (material_repository::find_unpromoted_completed_versions()) and calls material_repository::switch_current() for it: the rule of DEC-62
 *    (the number must be greater than the current one), the lock of the material and the transaction are those of switch_current(), so a
 *    version overtaken meanwhile, by the normal processing or by a run of this task, is refused and left as it is.
 * 2. A `pending` version that nobody is working on. It is marked `failed` (material_repository::fail_pending_version()) when the course has
 *    the assistant enabled, no completed version of a greater number exists (a superseded pending version is never changed: CL-50) and no
 *    `material_changed` event of the same course and module is `pending` or `processing`. Every handler that works on a version runs for one
 *    such event, and an event leaves those two states only after its handler returned, so without one nobody is working on the version.
 *    The events are read once, before the first change of this step and outside any transaction. A failed version is not touched, never
 *    taken up again by the task (no retry_summary, no service, no LLM): a new event for the same content takes it up as it always did (CL-52).
 *
 * A new event can arrive after the events were read. Two things deal with it, and neither takes a lock of the module: the events are read
 * again inside the lock of the material and the transaction of the change (`$abandoned` of fail_pending_version()), so an event committed
 * before that reading keeps the version; and a worker that read the version as pending before it was marked failed finds it failed when it
 * persists the summary, and material_service takes it up again in the same attempt (failed to pending to completed, the transitions that
 * exist) with the summary it already has. The lock of the material is not held while a worker waits for the LLM, so no lock can exclude the
 * two; what is guaranteed is that the event is not lost, the attempt is not spent and the LLM is not called again.
 *
 * Neither step calls the LLM, creates, changes the content of or removes a version, looks at the content of the material, or touches
 * `current_version` outside switch_current(). Running it again changes nothing that was already done.
 *
 * For each candidate of either step:
 * - Done: the pointer moved, or the version was marked failed.
 * - Refused as not newer (version_not_newer_exception), step 1: a normal outcome, counted, not a failure.
 * - Removed: the operation found no record (dml_missing_record_exception) and the material itself no longer exists, as after the removal of
 *   the course between the reading of the candidates and the operation. Counted apart and ignored, not a failure. The same exception with
 *   the material still there (a missing version, a pointer that points to nothing) is not a removal and is a failure.
 * - Any other exception (database, lock, a programming error, a TypeError included): a failure. One line with the identifiers, the kind
 *   of exception, its code and where it was raised, never the content; the candidates after it are still tried. When the run ends, after
 *   the summaries, the task raises an exception if there was any failure, so that Moodle shows the run as failed; nothing is hidden as a
 *   normal outcome. The candidate is tried again by the next run.
 *
 * No limit per run: the candidates leave the set when they are done, and no number is invented without evidence of the need. How the real
 * cron runs it in Moodle 5.2 is validated with the other tasks in CL-02 (T051).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_material_versions extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task_reconcile_material_versions', 'local_assistente');
    }

    #[\Override]
    public function execute(): void {
        $repository = $this->repository();
        $failed = 0;
        $total = 0;

        $promoted = 0;
        $overtaken = 0;
        $removed = 0;
        $candidates = $this->candidates($repository);
        $total += count($candidates);
        foreach ($candidates as $materialid => $versionid) {
            try {
                $repository->switch_current($materialid, $versionid);
                $promoted++;
            } catch (version_not_newer_exception) {
                $overtaken++;
            } catch (\dml_missing_record_exception $e) {
                if ($this->is_removed($repository, $materialid)) {
                    $removed++;
                } else {
                    $failed++;
                    $this->report($materialid, $versionid, $e);
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->report($materialid, $versionid, $e);
            }
        }
        mtrace(sprintf(
            'material version reconciliation: %d candidates, %d promoted, %d overtaken, %d removed, %d failed',
            count($candidates),
            $promoted,
            $overtaken,
            $removed,
            $failed
        ));

        $pending = $this->pending_candidates($repository);
        $total += count($pending);
        $busy = [];
        $unreadable = false;
        if ($pending) {
            try {
                $busy = $this->modules_with_active_events();
            } catch (\Throwable $e) {
                // Nobody can say whether a version is being worked on: nothing is changed, and the run is signalled as failed.
                $unreadable = true;
                $failed++;
                mtrace(sprintf(
                    'material version reconciliation: the events could not be read, no pending version was changed: %s (code %s) at %s:%d',
                    get_class($e),
                    (string) $e->getCode(),
                    basename($e->getFile()),
                    $e->getLine()
                ));
            }
        }
        $marked = 0;
        $active = 0;
        $kept = 0;
        $pendingremoved = 0;
        $pendingfailed = 0;
        foreach ($unreadable ? [] : $pending as $versionid => $row) {
            $materialid = (int) $row->materialid;
            if (isset($busy[$row->courseid . ':' . $row->cmid])) {
                $active++;
                continue;
            }
            try {
                // Asked again inside the lock of the material and the transaction: an event that arrived after the first reading is seen.
                $stillabandoned = fn(): bool => !isset($this->modules_with_active_events()[$row->courseid . ':' . $row->cmid]);
                if ($repository->fail_pending_version((int) $versionid, $stillabandoned)) {
                    $marked++;
                } else {
                    $kept++;
                }
            } catch (\dml_missing_record_exception $e) {
                if ($this->is_removed($repository, $materialid)) {
                    $pendingremoved++;
                } else {
                    $pendingfailed++;
                    $this->report($materialid, (int) $versionid, $e);
                }
            } catch (\Throwable $e) {
                $pendingfailed++;
                $this->report($materialid, (int) $versionid, $e);
            }
        }
        $failed += $pendingfailed;
        mtrace(sprintf(
            'material version reconciliation: %d pending candidates, %d marked failed, %d with an active event, '
            . '%d kept, %d removed, %d failed',
            count($pending),
            $marked,
            $active,
            $kept,
            $pendingremoved,
            $pendingfailed
        ));

        if ($failed > 0) {
            throw new \RuntimeException(
                "local_assistente: the reconciliation of material versions failed for {$failed} of {$total} candidates."
            );
        }
    }

    /**
     * The modules that still have a `material_changed` event `pending` or `processing`, as "course:cmid". Read outside any transaction
     * (a seam for tests).
     *
     * @return bool[] true by "course:cmid"
     */
    protected function modules_with_active_events(): array {
        $modules = [];
        foreach ((new event_repository())->find_runnable_in_enabled_courses([], 0, [event_type::MATERIAL_CHANGED]) as $event) {
            $payload = json_decode((string) $event->payload, true);
            $cmid = is_array($payload) ? ($payload['cmid'] ?? null) : null;
            if (is_int($cmid)) {
                $modules[(int) $event->course_id . ':' . $cmid] = true;
            }
        }
        return $modules;
    }

    /**
     * Whether the material no longer exists. A failure of the check itself is not a removal.
     *
     * @param material_repository $repository
     * @param int $materialid
     * @return bool
     */
    private function is_removed(material_repository $repository, int $materialid): bool {
        try {
            return $repository->find($materialid) === null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Write the line of a failure: the material and the version, and the kind, code and place of the exception. Never the content.
     *
     * @param int $materialid
     * @param int $versionid
     * @param \Throwable $e
     */
    private function report(int $materialid, int $versionid, \Throwable $e): void {
        mtrace(sprintf(
            'material version reconciliation: material %d, version %d: %s (code %s) at %s:%d',
            $materialid,
            $versionid,
            get_class($e),
            (string) $e->getCode(),
            basename($e->getFile()),
            $e->getLine()
        ));
    }

    /**
     * The repository the run uses: the default one, which waits 30 seconds for a lock (a seam for tests).
     *
     * @return material_repository
     */
    protected function repository(): material_repository {
        return new material_repository();
    }

    /**
     * The completed versions to make current in this run (a seam for tests).
     *
     * @param material_repository $repository
     * @return int[] id of the version, by id of the material
     */
    protected function candidates(material_repository $repository): array {
        return $repository->find_unpromoted_completed_versions();
    }

    /**
     * The pending versions to look at in this run (a seam for tests).
     *
     * @param material_repository $repository
     * @return \stdClass[] by id of the version, each with `materialid`, `courseid` and `cmid`
     */
    protected function pending_candidates(material_repository $repository): array {
        return $repository->find_pending_without_later_completed();
    }
}
