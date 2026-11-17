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

use local_assistente\domain\event_status;
use local_assistente\infrastructure\persistence\student_data_repository;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * The part of the erasure of a student that concerns the events: it keeps the queue, the creation of events and the erasure from stepping on
 * each other (T104; spec-017, DEC-42 D1, DEC-57). It is not the privacy provider, which does not exist yet; the provider will call it.
 *
 * What it does, for one student, in this order:
 * 1. reads the events of the student that are not final (`pending` or `processing`), without a lock;
 * 2. asks the unit of work for the lock of the student (`privacy_user_<id>`) and the lock of each of those events, all at once, before any
 *    transaction. The queue holds the lock of an event for the whole of its handler, so waiting for it is waiting for the handler to end (up
 *    to the timeout of the unit of work, 30 s); if the lock does not come the request fails, with nothing changed, and can be repeated;
 * 3. opens the transaction and reads the events again. An event that was born between the reading and the locks is not locked: the attempt is
 *    undone (nothing had been written) and made again with it in the set. Once the lock of the student is held no event of the student can
 *    be created (event_service takes the same lock), and an event that exists can only change state under its own lock, so the set is stable
 *    after a few rounds; MAX_ATTEMPTS bounds them, and the request fails with student_event_stabilisation_exception if they do not end;
 * 4. in the same transaction, with every handler of those events finished or never started: deletes the individual results of the student
 *    (the ones a handler wrote a moment ago included), neutralizes the payload of every event of the student, final or not (DEC-57), and
 *    runs what the caller passed for the other steps of the erasure.
 *
 * The events that were not final are afterwards handled by the queue as events of an erased subject: the handlers finish them with success
 * and write nothing. An event interrupted in `processing` is recovered by the queue as before. The final events are only neutralized.
 *
 * Idempotent: a student with nothing left finds nothing. Order of the locks: see unit_of_work::privacy_user_resource().
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_event_eraser {
    /** @var int rounds of reading, locking and checking before the erasure gives up */
    public const MAX_ATTEMPTS = 5;

    /** @var student_data_repository */
    private student_data_repository $data;

    /** @var unit_of_work */
    private unit_of_work $unitofwork;

    /** @var \Closure|null called after each reading of the events and before the locks, with the number of the round; for tests */
    private ?\Closure $afterreading;

    /**
     * Constructor.
     *
     * @param student_data_repository|null $data
     * @param unit_of_work|null $unitofwork
     * @param \Closure|null $afterreading seam for tests: runs between the reading and the locks
     */
    public function __construct(
        ?student_data_repository $data = null,
        ?unit_of_work $unitofwork = null,
        ?\Closure $afterreading = null
    ) {
        $this->data = $data ?? new student_data_repository();
        $this->unitofwork = $unitofwork ?? new unit_of_work();
        $this->afterreading = $afterreading;
    }

    /**
     * Erase the events and the individual results of a student.
     *
     * @param int $userid
     * @param \Closure|null $within the other steps of the erasure, run in the same transaction after the ones above, with the student
     * @param int[]|null $courseids only the events and results of these courses; null for every course
     * @param string[] $extraresources locks of the caller that go with the others, taken before the transaction (the sections of the syntheses it will redact)
     * @return array{rounds: int, events: int, not_final: int, rows: array<string, int>}
     * @throws \InvalidArgumentException when the student is not a positive identifier
     * @throws \local_assistente\infrastructure\persistence\lock_unavailable_exception when a lock does not come; nothing was changed
     * @throws student_event_stabilisation_exception when the events kept changing; nothing was changed
     * @throws \Throwable what $within throws; the whole transaction is undone
     */
    public function erase(int $userid, ?\Closure $within = null, ?array $courseids = null, array $extraresources = []): array {
        if ($userid <= 0) {
            throw new \InvalidArgumentException('The student must be a positive identifier.');
        }
        $known = [];
        for ($round = 1; $round <= self::MAX_ATTEMPTS; $round++) {
            $known = array_values(array_unique(array_merge($known, $this->not_final_ids($userid, $courseids))));
            if ($this->afterreading !== null) {
                ($this->afterreading)($round);
            }
            $resources = array_map(unit_of_work::event_resource(...), $known);
            $resources[] = unit_of_work::privacy_user_resource($userid);
            $resources = array_merge($resources, $extraresources);

            try {
                $result = $this->unitofwork->run(function () use ($userid, $known, $within, $courseids): array {
                    $events = $this->data->find_events($userid, $courseids);
                    $notfinal = $this->ids_not_final($events);
                    $unlocked = array_values(array_diff($notfinal, $known));
                    if ($unlocked) {
                        throw new student_event_set_changed($unlocked);
                    }
                    $rows = $this->data->delete_result_rows($userid, $courseids);
                    $neutralized = 0;
                    foreach ($events as $event) {
                        $neutralized += $this->data->neutralize_event($event) ? 1 : 0;
                    }
                    if ($within !== null) {
                        $within($userid);
                    }
                    return ['events' => $neutralized, 'not_final' => count($notfinal), 'rows' => $rows];
                }, $resources);
                return ['rounds' => $round] + $result;
            } catch (student_event_set_changed $changed) {
                $known = array_values(array_unique(array_merge($known, $changed->ids)));
            }
        }
        throw new student_event_stabilisation_exception(
            'The events of the student kept changing during ' . self::MAX_ATTEMPTS . ' rounds; nothing was changed.'
        );
    }

    /**
     * Ids of the events of the student that are not final, read without a lock.
     *
     * @param int $userid
     * @param int[]|null $courseids
     * @return int[]
     */
    private function not_final_ids(int $userid, ?array $courseids): array {
        return $this->ids_not_final($this->data->find_events($userid, $courseids));
    }

    /**
     * Ids of the events that are `pending` or `processing`.
     *
     * @param \stdClass[] $events
     * @return int[]
     */
    private function ids_not_final(array $events): array {
        $ids = [];
        foreach ($events as $event) {
            if (in_array($event->status, [event_status::PENDING->value, event_status::PROCESSING->value], true)) {
                $ids[] = (int) $event->id;
            }
        }
        return $ids;
    }
}
