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

namespace local_assistente\application\delivery;

/**
 * Which locks failed recently in this process, kept in memory only (redelivery of an event; DEC-32 D2 exception).
 *
 * When a lock did not come, the next deliveries of the process that need that same lock do not wait for it again: they go straight to the
 * retry, so a burst of events of one student costs one wait, not one for each event. Only the exact resource that failed is remembered, so
 * a busy lock of one student or one occurrence never changes the delivery of another.
 *
 * The memory belongs to the instance, lives as long as the process (the observers share one instance, see observer_event_service) and is
 * never written anywhere: no table, no cache of the site, no state between requests. An entry stops counting after VALIDITY seconds, so a
 * long process (a cron that keeps running) tries the lock again once it has had time to be released.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lock_failure_budget {
    /** Seconds a failed lock keeps the deliveries of the process from waiting for it again. */
    public const VALIDITY = 30;

    /** @var array<string, int> resource => time until which it counts as busy */
    private array $until = [];

    /**
     * Constructor.
     *
     * @param \Closure|null $clock returns the current time; time() when null (a seam for tests)
     */
    public function __construct(private readonly ?\Closure $clock = null) {
    }

    /**
     * Remember that the lock of a resource did not come.
     *
     * @param string $resource empty is ignored
     */
    public function record(string $resource): void {
        if ($resource !== '') {
            $this->until[$resource] = $this->now() + self::VALIDITY;
        }
    }

    /**
     * Whether a delivery that needs these locks should not wait for them: one of them failed within the last VALIDITY seconds.
     *
     * @param string[] $resources
     * @return bool
     */
    public function is_blocked(array $resources): bool {
        $now = $this->now();
        foreach ($resources as $resource) {
            if (isset($this->until[$resource])) {
                if ($this->until[$resource] > $now) {
                    return true;
                }
                unset($this->until[$resource]);
            }
        }
        return false;
    }

    /**
     * Forget everything.
     */
    public function clear(): void {
        $this->until = [];
    }

    /**
     * The current time.
     *
     * @return int
     */
    private function now(): int {
        return $this->clock === null ? time() : (int) ($this->clock)();
    }
}
