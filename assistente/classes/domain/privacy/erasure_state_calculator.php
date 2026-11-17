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

/**
 * The decisions about the erasure of a student that need no Moodle (T142; DEC-58): the state, the case that is open, and which stopped
 * retry tasks became obsolete.
 *
 * The state is decided by the residue that exists now and, only then, by the tasks and the events:
 * 1. no residue by identifier: TEXTS_UNVERIFIED when the name is unavailable, NO_RESIDUE_FOUND when it is available and no preserved text
 *    cites it;
 * 2. residue: PENDING when a task with attempts left covers it; STOPPED when a stopped task newer than the last completed erasure covers
 *    it or the latest event is a `stopped`; INCOMPLETE otherwise.
 * A task or an event of courses that only overlap the key counts for the residue they cover, never for the exact case: `closed_by_retry`
 * needs a `completed` event of exactly the courses of the key and of its latest generation.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_state_calculator {
    /**
     * The state of an erasure.
     *
     * @param erasure_facts $facts
     * @return erasure_result
     */
    public function calculate(erasure_facts $facts): erasure_result {
        $key = $facts->key;
        $residue = $facts->idresidue;
        if ($facts->nameavailable) {
            $residue = array_merge($residue, $facts->textresidue);
        }
        $residue = array_values(array_intersect(array_unique(array_map('intval', $residue)), $key->courses()));
        sort($residue);

        if ($residue === []) {
            return $facts->nameavailable ? $this->without_residue($facts) : new erasure_result(erasure_state::TEXTS_UNVERIFIED);
        }

        foreach ($facts->tasks as $task) {
            if ($task->attempts > 0 && array_intersect($task->courses, $residue) !== []) {
                return new erasure_result(erasure_state::PENDING);
            }
        }
        foreach ($facts->tasks as $task) {
            $covered = array_values(array_intersect($task->courses, $residue));
            if ($task->attempts === 0 && $covered !== [] && $task->id > $this->last_completed_task($facts->events, $covered)) {
                return new erasure_result(erasure_state::STOPPED);
            }
        }
        $latest = null;
        foreach ($facts->events as $event) {
            if (array_intersect($event->courses, $residue) !== [] && ($latest === null || $event->id > $latest->id)) {
                $latest = $event;
            }
        }
        if ($latest !== null && $latest->type === erasure_event_fact::STOPPED) {
            return new erasure_result(erasure_state::STOPPED);
        }
        return new erasure_result(erasure_state::INCOMPLETE);
    }

    /**
     * The task of the exact case that is open: its latest generation, when no `completed` event closed it.
     *
     * @param erasure_key $key
     * @param erasure_event_fact[] $events
     * @return int|null the task (generation), or null when there is no case or it is closed
     */
    public function open_generation(erasure_key $key, array $events): ?int {
        $case = $this->exact_case($key, $events);
        return $case === null || $case['completed'] !== null ? null : $case['generation'];
    }

    /**
     * The retry tasks that a completed erasure made obsolete: stopped (no attempts), not running, older than the completed task and with
     * courses that the completed erasure covers. A task with attempts left, a running task and a newer task are never obsolete.
     *
     * @param erasure_task_fact[] $tasks
     * @param int[] $completedcourses the courses of the `completed` event
     * @param int $completedtask the task of the `completed` event
     * @return int[] ids of the tasks
     */
    public function obsolete_tasks(array $tasks, array $completedcourses, int $completedtask): array {
        $obsolete = [];
        foreach ($tasks as $task) {
            if (
                $task->attempts === 0 && !$task->running && $task->id < $completedtask && $task->courses !== []
                && array_diff($task->courses, $completedcourses) === []
            ) {
                $obsolete[] = $task->id;
            }
        }
        return $obsolete;
    }

    /**
     * NO_RESIDUE_FOUND with what the exact case says about how it was closed.
     *
     * @param erasure_facts $facts
     * @return erasure_result
     */
    private function without_residue(erasure_facts $facts): erasure_result {
        $case = $this->exact_case($facts->key, $facts->events);
        if ($case === null || $case['completed'] === null) {
            return new erasure_result(erasure_state::NO_RESIDUE_FOUND);
        }
        $via = $case['completed']->detail;
        return new erasure_result(
            erasure_state::NO_RESIDUE_FOUND,
            $via === erasure_event_fact::VIA_RETRY,
            $via,
            erasure_result::EVIDENCE_DATA_AND_EVENTS
        );
    }

    /**
     * The latest generation of the exact case and the `completed` event that closed it, if any.
     *
     * Only events of exactly the courses of the key count. The generation is the highest task among the events that open a case (`deferred`
     * or the `stopped` of a marker). A `completed` event closes it when it is of that generation, comes after the first event of the
     * generation and no event that opens a case comes after it.
     *
     * @param erasure_key $key
     * @param erasure_event_fact[] $events
     * @return array{generation: int, completed: erasure_event_fact|null}|null null when the key never had a case
     */
    private function exact_case(erasure_key $key, array $events): ?array {
        $exact = array_filter($events, static fn(erasure_event_fact $e): bool => $key->has_courses($e->courses));
        $openings = array_filter($exact, static fn(erasure_event_fact $e): bool => $e->type !== erasure_event_fact::COMPLETED);
        if ($openings === []) {
            return null;
        }
        $generation = max(array_map(static fn(erasure_event_fact $e): int => $e->task, $openings));
        $first = min(array_map(
            static fn(erasure_event_fact $e): int => $e->id,
            array_filter($openings, static fn(erasure_event_fact $e): bool => $e->task === $generation)
        ));
        $lastopening = max(array_map(static fn(erasure_event_fact $e): int => $e->id, $openings));
        $completed = null;
        foreach ($exact as $event) {
            if (
                $event->type === erasure_event_fact::COMPLETED && $event->task === $generation && $event->id > $first
                && $event->id > $lastopening && ($completed === null || $event->id > $completed->id)
            ) {
                $completed = $event;
            }
        }
        return ['generation' => $generation, 'completed' => $completed];
    }

    /**
     * The highest task among the `completed` events that touch some courses.
     *
     * @param erasure_event_fact[] $events
     * @param int[] $courses
     * @return int 0 when there is none
     */
    private function last_completed_task(array $events, array $courses): int {
        $last = 0;
        foreach ($events as $event) {
            if ($event->type === erasure_event_fact::COMPLETED && array_intersect($event->courses, $courses) !== []) {
                $last = max($last, $event->task);
            }
        }
        return $last;
    }
}
