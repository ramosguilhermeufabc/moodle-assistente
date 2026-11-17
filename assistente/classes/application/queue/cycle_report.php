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

/**
 * Report of one cycle of the queue: what happened to each event it touched, in order, and why it stopped.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cycle_report {
    /** The queue had no event left to try in this cycle (DEC-32 D5 a). */
    public const STOPPED_EMPTY = 'empty';

    /** The time of the next scheduled run arrived, with events left for the next cycle (DEC-32 D5 b). */
    public const STOPPED_DEADLINE = 'deadline';

    /** @var array<int, cycle_outcome> outcome by event id, in the order the events were tried */
    private array $outcomes = [];

    /** @var string why the cycle stopped */
    private string $stoppedby = self::STOPPED_EMPTY;

    /**
     * Record what happened to an event.
     *
     * @param int $eventid
     * @param cycle_outcome $outcome
     */
    public function record(int $eventid, cycle_outcome $outcome): void {
        $this->outcomes[$eventid] = $outcome;
    }

    /**
     * Record why the cycle stopped.
     *
     * @param string $reason one of the STOPPED_ constants
     */
    public function stop(string $reason): void {
        $this->stoppedby = $reason;
    }

    /**
     * Why the cycle stopped.
     *
     * @return string
     */
    public function stopped_by(): string {
        return $this->stoppedby;
    }

    /**
     * Events with an outcome, in the order they were tried.
     *
     * @param cycle_outcome|null $outcome only this outcome; null for all
     * @return int[] event ids
     */
    public function events(?cycle_outcome $outcome = null): array {
        $ids = [];
        foreach ($this->outcomes as $id => $recorded) {
            if ($outcome === null || $recorded === $outcome) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Number of events with an outcome.
     *
     * @param cycle_outcome $outcome
     * @return int
     */
    public function count(cycle_outcome $outcome): int {
        return count($this->events($outcome));
    }

    /**
     * One line for the log of the task.
     *
     * @return string
     */
    public function summary(): string {
        $parts = [];
        foreach (cycle_outcome::cases() as $outcome) {
            $parts[] = $outcome->value . '=' . $this->count($outcome);
        }
        return 'queue cycle: ' . implode(' ', $parts) . ' stopped_by=' . $this->stoppedby;
    }
}
