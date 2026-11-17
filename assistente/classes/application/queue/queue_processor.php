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

namespace local_assistente\application\queue;

use local_assistente\application\course_config_service;
use local_assistente\domain\event\event_type;
use local_assistente\domain\event_status;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * One cycle of the logical queue (spec-007; DEC-32 D2 to D6; DEC-08, DEC-09, DEC-16, DEC-22; P-04).
 *
 * The loop
 * - takes the next runnable event in creation order (D6) among those not yet tried in this cycle, so an event gets at most one
 *   attempt per cycle (D3) and events that arrive during the cycle are picked up in the same cycle (D5);
 * - processes one event at a time, under the lock of that event (D2), without a transaction open while the handler runs;
 * - stops when no event is left (D5 a) or when the time of the next scheduled run arrives (D5 b). The event in progress is
 *   always finished first, the rest waits for the next cycle. The deadline is a time given by the caller, computed from the
 *   interval configured in the native schedule; this class invents no number.
 *
 * Per event, under the lock: the attempt is started in a short transaction, the handler runs with no transaction, and the
 * result is saved in another short transaction. A failed attempt sends the event back to pending until the third (D4).
 *
 * What is NOT processed
 * - Events of a course without configuration or with the assistant disabled: they stay as they are (DEC-45 D3).
 * - Events of a type without a handler: they stay pending indefinitely until a delivery registers the handler, with no
 *   attempt consumed, no limit and no alert (DEC-63, CL-43, closed; failing them would burn their attempts before the
 *   handler exists).
 *
 * Interrupted runs: an event left in `processing` is a candidate again. If its lock can be taken, no run is alive for it, and
 * the attempt that did not finish is closed as failed and counted, the same processing continues (DEC-22). What the lock
 * backend does with the lock of a process that died, and whether an active run can be told from an abandoned one without
 * the lock, is not defined (P-04 "ainda não validado"): this class uses only the lock as the signal and adds no heartbeat,
 * timeout or field. Validated in CL-02 (T051).
 *
 * Failure of saving a result (database error) is not caught: hypothesis CL-44; the exception ends the cycle and the task fails visibly; the
 * event stays in `processing` and is recovered by the next cycle. A handler that already persisted its results therefore
 * runs again on recovery, which is why handlers must be idempotent (see event_handler).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_processor {
    /** @var event_repository */
    private event_repository $repository;

    /** @var handler_registry */
    private handler_registry $handlers;

    /** @var course_config_service */
    private course_config_service $config;

    /** @var unit_of_work */
    private unit_of_work $unitofwork;

    /** @var \Closure returns the current time */
    private \Closure $clock;

    /**
     * Constructor.
     *
     * @param event_repository|null $repository
     * @param handler_registry|null $handlers the handlers of the plugin when null
     * @param course_config_service|null $config
     * @param unit_of_work|null $unitofwork its timeout is how long to wait for the lock of an event held by another run
     * @param callable|null $clock returns the current unix time; time() when null (tests pass their own)
     */
    public function __construct(
        ?event_repository $repository = null,
        ?handler_registry $handlers = null,
        ?course_config_service $config = null,
        ?unit_of_work $unitofwork = null,
        ?callable $clock = null
    ) {
        $this->repository = $repository ?? new event_repository();
        $this->handlers = $handlers ?? handler_registry::default();
        $this->config = $config ?? new course_config_service();
        $this->unitofwork = $unitofwork ?? new unit_of_work(1);
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : time(...);
    }

    /**
     * Run one cycle.
     *
     * @param int|null $deadline unix time of the next scheduled run of the task; null for no limit
     * @return cycle_report
     */
    public function run_cycle(?int $deadline = null): cycle_report {
        $report = new cycle_report();
        $tried = [];
        $types = $this->handlers->registered_types();

        while (true) {
            $next = $this->repository->find_runnable_in_enabled_courses($tried, 1, $types);
            if (!$next) {
                $report->stop(cycle_report::STOPPED_EMPTY);
                break;
            }
            if ($deadline !== null && ($this->clock)() >= $deadline) {
                $report->stop(cycle_report::STOPPED_DEADLINE);
                break;
            }

            $eventid = (int) $next[0]->id;
            $tried[] = $eventid;
            $report->record($eventid, $this->process_event($eventid));
        }
        return $report;
    }

    /**
     * Process one event under its lock.
     *
     * @param int $eventid
     * @return cycle_outcome
     */
    private function process_event(int $eventid): cycle_outcome {
        try {
            return $this->unitofwork->run_locked(
                unit_of_work::event_resource($eventid),
                fn(): cycle_outcome => $this->process_locked($eventid)
            );
        } catch (lock_unavailable_exception $e) {
            return cycle_outcome::SKIPPED_LOCKED;
        }
    }

    /**
     * Process an event whose lock is held.
     *
     * @param int $eventid
     * @return cycle_outcome
     */
    private function process_locked(int $eventid): cycle_outcome {
        $event = $this->repository->find($eventid);
        if ($event === null || !in_array($event->status, [event_status::PENDING->value, event_status::PROCESSING->value], true)) {
            return cycle_outcome::SKIPPED_NOT_RUNNABLE;
        }
        $type = event_type::tryFrom($event->event_type);
        $handler = $type === null ? null : $this->handlers->handler_for($type);
        if ($handler === null || !$this->config->is_enabled((int) $event->course_id)) {
            return cycle_outcome::SKIPPED_NOT_RUNNABLE;
        }

        // Short transaction: start the attempt (recovering an interrupted run, or failing an exhausted event).
        $attempt = $this->unitofwork->run(fn() => $this->repository->begin_attempt($eventid, ($this->clock)()));
        if ($attempt === null) {
            return cycle_outcome::EXHAUSTED;
        }

        // No transaction is open from here until the result is saved: the handler may call external services.
        $event = $this->repository->find($eventid);
        $processing = $this->repository->find_processing($eventid);
        try {
            $result = $handler->handle($event, $processing);
        } catch (\Throwable $e) {
            $result = handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }

        if ($result->success) {
            $this->unitofwork->run(
                fn() => $this->repository->complete_attempt((int) $attempt->id, ($this->clock)(), $result->response())
            );
            return cycle_outcome::PROCESSED;
        }

        $state = $this->unitofwork->run(
            fn() => $this->repository->fail_attempt((int) $attempt->id, ($this->clock)(), $result->response())
        );
        return $state === event_status::FAILURE ? cycle_outcome::FAILED : cycle_outcome::RETRY_LATER;
    }
}
