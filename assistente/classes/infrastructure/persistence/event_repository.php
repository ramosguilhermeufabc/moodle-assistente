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

namespace local_assistente\infrastructure\persistence;

use local_assistente\domain\attempt_status;
use local_assistente\domain\event\event_transitions;
use local_assistente\domain\event\event_type;
use local_assistente\domain\event\occurrence_identity;
use local_assistente\domain\event_status;

/**
 * Repository of the event aggregate: `event`, its 1:1 `processing`, the `processing_attempt` rows and the
 * `processing_context` snapshot (plan 003, "Agregados"; spec-006; spec-007).
 *
 * Contract of the writes
 * - Every state change goes through the transitions of event_transitions; a forbidden one throws \DomainException.
 * - The methods that change state touch more than one table. The caller must run them inside unit_of_work with the lock of
 *   the event (event resource), so that one processing exists per event and a concurrent run does not restart it (DEC-16,
 *   DEC-22). They never call anything external.
 * - The vocabulary of `processing.status` is the one of the event (DEC-32 D4); it always mirrors the state of the event.
 *
 * What is stored in the free fields (Q-006-06)
 * - `event.processed_at`: the moment the event reached a final state (`processed` or `failure`); null while it is not final.
 * - `event.response`: the response of the latest attempt, successful or failed (a short text; no state is inferred from it).
 * - `processing_attempt.response`: the result or the error of that attempt.
 * - `processing.finished_at`: set together with `event.processed_at`.
 *
 * Attempts: the persisted rows are the counter (P-04). At most three attempts exist per processing (DEC-09); the schema does
 * not enforce it, this repository does.
 *
 * The identity of an occurrence has no column: it is stored inside the payload (P-01), so finding an event by identity reads
 * the events of one course and type. That cost is linear in them; measure it before any field or index is proposed.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_repository extends repository {
    /** @var table_repository */
    private readonly table_repository $processings;

    /** @var table_repository */
    private readonly table_repository $attempts;

    /** @var table_repository */
    private readonly table_repository $contexts;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->processings = new table_repository('local_assistente_processing');
        $this->attempts = new table_repository('local_assistente_processing_attempt');
        $this->contexts = new table_repository('local_assistente_processing_context');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_event';
    }

    /**
     * Create an event in the `pending` state.
     *
     * The payload is validated against the schema of the type, and the identity of the occurrence is stored in it.
     * Deduplication is the job of the service (find_by_identity under the identity lock).
     *
     * @param int $courseid
     * @param event_type $type
     * @param array $payload fields of the type
     * @param int $now creation time
     * @return \stdClass the stored event
     * @throws \InvalidArgumentException when the payload does not fit the type
     */
    public function create_event(int $courseid, event_type $type, array $payload, int $now): \stdClass {
        $normalized = $type->normalize_payload($payload);
        $normalized[occurrence_identity::PAYLOAD_KEY] = occurrence_identity::of($type, $normalized);

        $id = $this->insert((object) [
            'course_id' => $courseid,
            'event_type' => $type->value,
            'payload' => json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'status' => event_status::PENDING->value,
            'processed_at' => null,
            'response' => null,
        ]);
        return $this->find($id);
    }

    /**
     * Find the event of an occurrence.
     *
     * @param int $courseid
     * @param event_type $type
     * @param string $identity see occurrence_identity::of()
     * @param bool $onlypending look only at events that are still pending (strategy COALESCE_PENDING)
     * @return \stdClass|null the oldest match
     */
    public function find_by_identity(int $courseid, event_type $type, string $identity, bool $onlypending = false): ?\stdClass {
        global $DB;
        $select = 'course_id = :courseid AND event_type = :type';
        $params = ['courseid' => $courseid, 'type' => $type->value];
        if ($onlypending) {
            $select .= ' AND status = :status';
            $params['status'] = event_status::PENDING->value;
        }

        $found = null;
        $events = $DB->get_recordset_select($this->table(), $select, $params, 'id ASC');
        foreach ($events as $event) {
            $payload = json_decode((string) $event->payload, true);
            if (is_array($payload) && ($payload[occurrence_identity::PAYLOAD_KEY] ?? null) === $identity) {
                $found = $event;
                break;
            }
        }
        $events->close();
        return $found;
    }

    /**
     * The identities of the events of a course and a type, each with the id of the oldest event that has it, in one pass.
     *
     * It is the same reading as find_by_identity() (all the events of the course and type, oldest first, the identity compared as a string),
     * made once for many identities. The caller must hold the lock of every identity it is going to look up before it calls this, and read
     * inside a transaction that was opened after the locks (event_service::enqueue_many()): that is what makes the answer exact for them.
     *
     * @param int $courseid
     * @param event_type $type
     * @return array<string, int> identity => id of the oldest event with that identity
     */
    public function identities_of(int $courseid, event_type $type): array {
        global $DB;
        $found = [];
        $events = $DB->get_recordset_select(
            $this->table(),
            'course_id = :courseid AND event_type = :type',
            ['courseid' => $courseid, 'type' => $type->value],
            'id ASC',
            'id, payload'
        );
        foreach ($events as $event) {
            $payload = json_decode((string) $event->payload, true);
            $identity = is_array($payload) ? ($payload[occurrence_identity::PAYLOAD_KEY] ?? null) : null;
            if (is_string($identity) && !isset($found[$identity])) {
                $found[$identity] = (int) $event->id;
            }
        }
        $events->close();
        return $found;
    }

    /**
     * Pending events of courses where the assistant is enabled, in the order of processing (DEC-32 D6).
     *
     * Events of a course without configuration or with the assistant disabled are not returned: they stay pending and are
     * processed normally if the assistant is enabled again (DEC-45 D3). The order is creation time, then id.
     *
     * @param int[] $excludeids events already tried in this cycle (DEC-32 D3)
     * @param int $limit 0 for no limit
     * @return \stdClass[] events, in order
     */
    public function find_pending_in_enabled_courses(array $excludeids = [], int $limit = 0): array {
        global $DB;
        $params = ['status' => event_status::PENDING->value];
        $exclude = '';
        if ($excludeids) {
            [$notin, $inparams] = $DB->get_in_or_equal($excludeids, SQL_PARAMS_NAMED, 'ex', false);
            $exclude = " AND e.id {$notin}";
            $params += $inparams;
        }
        $sql = "SELECT e.*
                  FROM {local_assistente_event} e
                  JOIN {local_assistente_config} c ON c.course_id = e.course_id AND c.enabled = 1
                 WHERE e.status = :status{$exclude}
              ORDER BY e.created_at ASC, e.id ASC";
        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    /**
     * Events the queue may run now, in the order of processing: pending ones, and ones left in `processing` by a run that
     * was interrupted, of courses where the assistant is enabled (DEC-32 D6, DEC-22, DEC-45 D3).
     *
     * An event in `processing` is only a candidate: whether another run is still working on it is told by its lock, which
     * the caller tries to take. This repository does not decide whether a run is alive (open point of P-04, validated in CL-02).
     *
     * @param int[] $excludeids events already tried in this cycle (DEC-32 D3)
     * @param int $limit 0 for no limit
     * @param event_type[]|null $types only these types; null for any; an empty list matches nothing
     * @return \stdClass[] events, in order
     */
    public function find_runnable_in_enabled_courses(array $excludeids = [], int $limit = 0, ?array $types = null): array {
        global $DB;
        if ($types !== null && !$types) {
            return [];
        }

        [$instatus, $params] = $DB->get_in_or_equal(
            [event_status::PENDING->value, event_status::PROCESSING->value],
            SQL_PARAMS_NAMED,
            'st'
        );
        $where = "e.status {$instatus}";
        if ($excludeids) {
            [$notin, $inparams] = $DB->get_in_or_equal($excludeids, SQL_PARAMS_NAMED, 'ex', false);
            $where .= " AND e.id {$notin}";
            $params += $inparams;
        }
        if ($types !== null) {
            [$intypes, $typeparams] = $DB->get_in_or_equal(
                array_map(static fn(event_type $type) => $type->value, $types),
                SQL_PARAMS_NAMED,
                'ty'
            );
            $where .= " AND e.event_type {$intypes}";
            $params += $typeparams;
        }

        $sql = "SELECT e.*
                  FROM {local_assistente_event} e
                  JOIN {local_assistente_config} c ON c.course_id = e.course_id AND c.enabled = 1
                 WHERE {$where}
              ORDER BY e.created_at ASC, e.id ASC";
        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    /**
     * Processing of an event.
     *
     * @param int $eventid
     * @return \stdClass|null null while no attempt has started
     */
    public function find_processing(int $eventid): ?\stdClass {
        return $this->processings->find_by(['event_id' => $eventid]);
    }

    /**
     * Attempts of a processing, in order.
     *
     * @param int $processingid
     * @return \stdClass[] by attempt number
     */
    public function find_attempts(int $processingid): array {
        return array_values($this->attempts->find_all_by(['processing_id' => $processingid], 'attempt_number ASC'));
    }

    /**
     * Start an attempt for an event, under the event lock.
     *
     * - A pending event moves to `processing`, creating its processing the first time.
     * - An event already in `processing` means that a previous run was interrupted (the caller holds the lock, so nobody
     *   else is running it): its open attempt is closed as failed and counts as an attempt (P-04). Hypothesis pending CL-02:
     *   an attempt that did not finish counts toward the limit.
     * - When the three attempts are used, the event goes to `failure` and null is returned.
     * - A processed or failed event cannot start an attempt.
     *
     * @param int $eventid
     * @param int $now
     * @return \stdClass|null the new attempt; null when the attempts are exhausted
     * @throws \DomainException when the event does not exist or is in a final state
     */
    public function begin_attempt(int $eventid, int $now): ?\stdClass {
        $event = $this->require_event($eventid);
        $status = event_status::from($event->status);
        if ($status !== event_status::PENDING && $status !== event_status::PROCESSING) {
            throw new \DomainException("Event {$eventid} is '{$status->value}' and cannot start an attempt.");
        }

        $processing = $this->find_processing($eventid);
        if ($processing !== null && $status === event_status::PROCESSING) {
            foreach ($this->find_attempts((int) $processing->id) as $open) {
                if ($open->status === attempt_status::PROCESSING->value) {
                    $this->attempts->update((object) [
                        'id' => $open->id,
                        'status' => attempt_status::FAILURE->value,
                        'finished_at' => $now,
                        'response' => 'interrupted',
                    ]);
                }
            }
        }

        $used = $processing === null ? 0 : count($this->find_attempts((int) $processing->id));
        if ($used >= event_transitions::MAX_ATTEMPTS) {
            $this->finish($event, $processing, event_status::FAILURE, $now, 'attempts exhausted');
            return null;
        }

        if ($status === event_status::PENDING) {
            event_transitions::assert_allowed($status, event_status::PROCESSING);
            $this->update((object) ['id' => $eventid, 'status' => event_status::PROCESSING->value]);
        }
        if ($processing === null) {
            $processingid = $this->processings->insert((object) [
                'event_id' => $eventid, 'status' => event_status::PROCESSING->value, 'created_at' => $now, 'finished_at' => null,
            ]);
        } else {
            $processingid = (int) $processing->id;
            $this->processings->update((object) ['id' => $processingid, 'status' => event_status::PROCESSING->value]);
        }

        $attemptid = $this->attempts->insert((object) [
            'processing_id' => $processingid,
            'attempt_number' => $used + 1,
            'started_at' => $now,
            'finished_at' => null,
            'status' => attempt_status::PROCESSING->value,
            'response' => null,
        ]);
        return $this->attempts->find($attemptid);
    }

    /**
     * Finish an attempt with success: the attempt, its processing and the event become `processed`.
     *
     * @param int $attemptid
     * @param int $now
     * @param string|null $response the result of the attempt
     * @throws \DomainException when the attempt is not in progress
     */
    public function complete_attempt(int $attemptid, int $now, ?string $response = null): void {
        [$attempt, $processing, $event] = $this->load_open_attempt($attemptid);
        $this->attempts->update((object) [
            'id' => $attempt->id, 'status' => attempt_status::PROCESSED->value, 'finished_at' => $now, 'response' => $response,
        ]);
        $this->finish($event, $processing, event_status::PROCESSED, $now, $response);
    }

    /**
     * Finish an attempt with failure. The event goes back to `pending` while attempts are left, and to `failure` after the
     * third.
     *
     * @param int $attemptid
     * @param int $now
     * @param string|null $response the error of the attempt
     * @return event_status the new state of the event
     * @throws \DomainException when the attempt is not in progress
     */
    public function fail_attempt(int $attemptid, int $now, ?string $response = null): event_status {
        [$attempt, $processing, $event] = $this->load_open_attempt($attemptid);
        $this->attempts->update((object) [
            'id' => $attempt->id, 'status' => attempt_status::FAILURE->value, 'finished_at' => $now, 'response' => $response,
        ]);

        $next = event_transitions::after_failure((int) $attempt->attempt_number);
        if ($next === event_status::FAILURE) {
            $this->finish($event, $processing, event_status::FAILURE, $now, $response);
            return $next;
        }

        event_transitions::assert_allowed(event_status::PROCESSING, $next);
        $this->update((object) ['id' => $event->id, 'status' => $next->value, 'response' => $response]);
        $this->processings->update((object) ['id' => $processing->id, 'status' => $next->value]);
        return $next;
    }

    /**
     * Save the context snapshot of a processing. It is immutable: a second save for the same processing is rejected.
     *
     * @param int $processingid
     * @param string $contextjson
     * @param int $now
     * @return \stdClass the stored snapshot
     * @throws duplicate_record_exception when the processing already has a snapshot
     */
    public function save_context(int $processingid, string $contextjson, int $now): \stdClass {
        $id = $this->contexts->insert((object) [
            'processing_id' => $processingid, 'context_json' => $contextjson, 'created_at' => $now,
        ]);
        return $this->contexts->find($id);
    }

    /**
     * Context snapshot of a processing.
     *
     * @param int $processingid
     * @return \stdClass|null
     */
    public function find_context(int $processingid): ?\stdClass {
        return $this->contexts->find_by(['processing_id' => $processingid]);
    }

    /**
     * Put the event and its processing in a final state.
     *
     * @param \stdClass $event
     * @param \stdClass|null $processing
     * @param event_status $final processed or failure
     * @param int $now
     * @param string|null $response
     */
    private function finish(\stdClass $event, ?\stdClass $processing, event_status $final, int $now, ?string $response): void {
        event_transitions::assert_allowed(event_status::from($event->status), $final);
        $this->update((object) [
            'id' => $event->id, 'status' => $final->value, 'processed_at' => $now, 'response' => $response,
        ]);
        if ($processing !== null) {
            $this->processings->update((object) ['id' => $processing->id, 'status' => $final->value, 'finished_at' => $now]);
        }
    }

    /**
     * The event, or an error when it does not exist.
     *
     * @param int $eventid
     * @return \stdClass
     * @throws \DomainException
     */
    private function require_event(int $eventid): \stdClass {
        $event = $this->find($eventid);
        if ($event === null) {
            throw new \DomainException("Event {$eventid} does not exist.");
        }
        return $event;
    }

    /**
     * Load an attempt that is in progress with its processing and event.
     *
     * @param int $attemptid
     * @return array [attempt, processing, event]
     * @throws \DomainException when the attempt does not exist or is not in progress
     */
    private function load_open_attempt(int $attemptid): array {
        $attempt = $this->attempts->find($attemptid);
        if ($attempt === null || $attempt->status !== attempt_status::PROCESSING->value) {
            throw new \DomainException("Attempt {$attemptid} is not in progress.");
        }
        $processing = $this->processings->find((int) $attempt->processing_id);
        $event = $this->require_event((int) $processing->event_id);
        return [$attempt, $processing, $event];
    }
}
