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

namespace local_assistente\application;

use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\domain\event\event_type;
use local_assistente\domain\event\identity_strategy;
use local_assistente\domain\event\occurrence_identity;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * The logical queue's entry point: turns an occurrence into an event, once (spec-006; DEC-08, DEC-16, DEC-21, DEC-45 D3).
 *
 * - A course without configuration, or with the assistant disabled, gets no event (isolation by course).
 * - The same occurrence received again reuses the event that exists, whatever its state; a new legitimate occurrence creates
 *   a new one. What "the same occurrence" means depends on the type (event_type::strategy()); for COALESCE_PENDING only an
 *   event still pending is reused.
 * - Receipt of one occurrence is serialised by a lock named after its identity, taken before the short transaction that looks
 *   for the existing event and creates the new one. Two concurrent deliveries end in one event.
 * - Nothing external runs here: no LLM, no Moodle read beyond the enabled check. Observers call this and return.
 *
 * Limits, documented in event_type::limitation(): the identity of an OCCURRENCE type has second precision.
 *
 * Redelivery (exception to DEC-32 D2). A queue built with an event_redelivery_scheduler (the one of the observers, which wait only a few
 * seconds for a lock) does not lose an occurrence whose lock is busy: it hands the delivery to a retry task and answers DEFERRED, and only
 * after the task is confirmed. If the task cannot be confirmed it throws; it never answers DEFERRED for an event that is not preserved. A
 * queue without a scheduler (the default, used by the queue, the handlers and the synchronization) lets the exception go up, as always.
 * The retry task calls enqueue() with a guard, which runs inside the critical section, under the same locks, before anything is read or
 * created.
 *
 * Many occurrences at once (enqueue_many(), used by the initial synchronization) keep the same guarantees. The cost of looking for an
 * existing event is a pass over the events of the course and type, because the identity is only a key of the JSON payload and has no
 * index (the schema is not changed for this); doing that pass once for each event makes creating E events quadratic. enqueue_many()
 * does it once for a chunk of occurrences, and stays exact because the chunk takes the lock of every identity (and of every student)
 * before its transaction opens, in the same global order as enqueue(): whoever else creates one of those identities waits for the
 * chunk, and what it created before is committed and visible to the pass.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_service {
    /** @var event_repository */
    private event_repository $repository;

    /** @var course_config_service */
    private course_config_service $config;

    /** @var unit_of_work */
    private unit_of_work $unitofwork;

    /** @var event_redelivery_scheduler|null when set, a delivery whose lock is busy is handed to a retry */
    private ?event_redelivery_scheduler $redelivery;

    /** Occurrences handled under one set of locks and one transaction by enqueue_many(). */
    public const BATCH_CHUNK = 250;

    /**
     * Constructor.
     *
     * @param event_repository|null $repository
     * @param course_config_service|null $config
     * @param unit_of_work|null $unitofwork
     * @param event_redelivery_scheduler|null $redelivery null for no redelivery: a busy lock throws
     */
    public function __construct(
        ?event_repository $repository = null,
        ?course_config_service $config = null,
        ?unit_of_work $unitofwork = null,
        ?event_redelivery_scheduler $redelivery = null
    ) {
        $this->repository = $repository ?? new event_repository();
        $this->config = $config ?? new course_config_service();
        $this->unitofwork = $unitofwork ?? new unit_of_work();
        $this->redelivery = $redelivery;
    }

    /**
     * Hand an occurrence to the queue.
     *
     * @param int $courseid
     * @param event_type $type
     * @param array $payload fields of the type (see event_type::fields())
     * @param int|null $now creation time of a new event; the current time when null
     * @param \Closure|null $guard runs inside the critical section, under the locks, before the event is looked for; when it returns
     *        false nothing is created and the answer is SKIPPED_REVOKED (the retry task asks whether it still exists)
     * @return enqueue_result
     * @throws \InvalidArgumentException when the payload does not fit the type
     * @throws lock_unavailable_exception when a lock does not come and there is no redelivery scheduler
     * @throws \RuntimeException when a lock does not come and the redelivery could not be scheduled
     */
    public function enqueue(int $courseid, event_type $type, array $payload, ?int $now = null, ?\Closure $guard = null): enqueue_result {
        // The payload is checked first, so that a malformed call fails the same way in every course.
        $payload = $type->normalize_payload($payload);

        if (!$this->config->is_enabled($courseid)) {
            return new enqueue_result(enqueue_status::SKIPPED_NOT_ENABLED);
        }

        $identity = occurrence_identity::of($type, $payload);
        $onlypending = $type->strategy() === identity_strategy::COALESCE_PENDING;
        $locks = [occurrence_identity::lock_resource($courseid, $type, $identity)];
        // An event of a student takes the lock of the student, which the erasure of the data of that student holds while it runs (T104, DEC-57).
        if (isset($payload['userid']) && $payload['userid'] > 0) {
            $locks[] = unit_of_work::privacy_user_resource((int) $payload['userid']);
        }

        // A lock that failed a moment ago in this process: do not wait for it again, the retry has it.
        if ($this->redelivery !== null && $this->redelivery->is_blocked($locks)) {
            return $this->defer($courseid, $type, $payload);
        }

        try {
            return $this->unitofwork->run(function () use ($courseid, $type, $payload, $identity, $onlypending, $now, $guard): enqueue_result {
                if ($guard !== null && !$guard()) {
                    return new enqueue_result(enqueue_status::SKIPPED_REVOKED);
                }
                $existing = $this->repository->find_by_identity($courseid, $type, $identity, $onlypending);
                if ($existing !== null) {
                    return new enqueue_result(enqueue_status::REUSED, $existing);
                }
                return new enqueue_result(
                    enqueue_status::CREATED,
                    $this->repository->create_event($courseid, $type, $payload, $now ?? time())
                );
            }, $locks);
        } catch (lock_unavailable_exception $e) {
            if ($this->redelivery === null) {
                throw $e;
            }
            $this->redelivery->record_failure($e);
            return $this->defer($courseid, $type, $payload);
        }
    }

    /**
     * Hand a delivery to the retry, and answer DEFERRED only once the task is confirmed.
     *
     * @param int $courseid
     * @param event_type $type
     * @param array $payload normalized
     * @return enqueue_result
     * @throws \RuntimeException when the task could not be confirmed
     */
    private function defer(int $courseid, event_type $type, array $payload): enqueue_result {
        $this->redelivery->defer($courseid, $type, $payload);
        return new enqueue_result(enqueue_status::DEFERRED);
    }
    /**
     * Turn many occurrences of one course into events, with the guarantees of enqueue() for each of them.
     *
     * - Every payload is validated and normalised first: a malformed occurrence fails the whole call before anything is written.
     * - A course without configuration or with the assistant disabled gets no event (SKIPPED_NOT_ENABLED), checked before each chunk.
     * - An occurrence that already has an event reuses it, whatever its state; one repeated inside the call is created once and the
     *   repetition reuses it. The identity is the one of enqueue(), so an event created by either path is recognised by the other.
     * - The occurrences are handled in chunks. A chunk takes, before its transaction, the lock of the identity of each occurrence and
     *   the lock of each student (the same resources and order as enqueue()), reads the identities of the course and type once, and
     *   creates the new events in the one transaction. Concurrent deliveries of the same occurrence, by enqueue() or by another
     *   enqueue_many(), end in one event. A chunk is atomic: if it fails nothing of it is written, and the chunks before it stay.
     * - A type whose identity coalesces pending events (COALESCE_PENDING) depends on the state of the event at the moment, which a
     *   chunk cannot hold still: those occurrences go one by one through enqueue().
     *
     * @param int $courseid
     * @param array<int|string, array{type: event_type, payload: array}> $occurrences
     * @param int|null $now creation time of the events; the clock when null
     * @param int $chunk occurrences per chunk
     * @return array<int|string, enqueue_result> one result for each occurrence, with the keys of the input
     * @throws \InvalidArgumentException when an occurrence does not fit its type or the chunk is smaller than 1
     * @throws \local_assistente\infrastructure\persistence\lock_unavailable_exception when a lock cannot be acquired
     */
    public function enqueue_many(int $courseid, array $occurrences, ?int $now = null, int $chunk = self::BATCH_CHUNK): array {
        if ($chunk < 1) {
            throw new \InvalidArgumentException('A chunk has at least one occurrence.');
        }
        // 1. Validate and normalise everything before touching the database.
        $prepared = [];
        foreach ($occurrences as $key => $occurrence) {
            $type = $occurrence['type'];
            $payload = $type->normalize_payload($occurrence['payload']);
            $prepared[$key] = [
                'type' => $type,
                'payload' => $payload,
                'identity' => occurrence_identity::of($type, $payload),
            ];
        }

        $results = [];
        $owners = [];
        $unique = [];
        $repeated = [];
        $coalescing = [];
        foreach ($prepared as $key => $item) {
            if ($item['type']->strategy() === identity_strategy::COALESCE_PENDING) {
                $coalescing[] = $key;
                continue;
            }
            $signature = $item['type']->value . '|' . $item['identity'];
            if (isset($owners[$signature])) {
                $repeated[$key] = $owners[$signature]; // The same occurrence twice in the call: created once.
                continue;
            }
            $owners[$signature] = $key;
            $unique[$key] = $item;
        }

        // 2. The chunks.
        foreach (array_chunk($unique, $chunk, true) as $items) {
            if (!$this->config->is_enabled($courseid)) {
                foreach ($items as $key => $item) {
                    $results[$key] = new enqueue_result(enqueue_status::SKIPPED_NOT_ENABLED);
                }
                continue;
            }
            $locks = [];
            foreach ($items as $item) {
                $locks[] = occurrence_identity::lock_resource($courseid, $item['type'], $item['identity']);
                if (isset($item['payload']['userid']) && $item['payload']['userid'] > 0) {
                    $locks[] = unit_of_work::privacy_user_resource((int) $item['payload']['userid']);
                }
            }
            $created = $now ?? time();
            $done = $this->unitofwork->run(function () use ($courseid, $items, $created): array {
                $known = [];
                $done = [];
                foreach ($items as $key => $item) {
                    $type = $item['type'];
                    $known[$type->value] ??= $this->repository->identities_of($courseid, $type);
                    $existing = $known[$type->value][$item['identity']] ?? null;
                    if ($existing !== null) {
                        $done[$key] = new enqueue_result(enqueue_status::REUSED, $this->repository->find($existing));
                        continue;
                    }
                    $event = $this->repository->create_event($courseid, $type, $item['payload'], $created);
                    $known[$type->value][$item['identity']] = (int) $event->id;
                    $done[$key] = new enqueue_result(enqueue_status::CREATED, $event);
                }
                return $done;
            }, $locks);
            $results += $done;
        }

        // 3. What repeated an occurrence of the same call, and what coalesces.
        foreach ($repeated as $key => $owner) {
            $first = $results[$owner];
            $results[$key] = $first->status === enqueue_status::SKIPPED_NOT_ENABLED
                ? $first
                : new enqueue_result(enqueue_status::REUSED, $first->event);
        }
        foreach ($coalescing as $key) {
            $results[$key] = $this->enqueue($courseid, $prepared[$key]['type'], $prepared[$key]['payload'], $now);
        }

        $ordered = [];
        foreach (array_keys($occurrences) as $key) {
            $ordered[$key] = $results[$key];
        }
        return $ordered;
    }
}
