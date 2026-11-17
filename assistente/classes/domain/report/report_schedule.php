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

namespace local_assistente\domain\report;

/**
 * Schedule of a report (spec-019; DEC-06, DEC-07, DEC-18, DEC-29, DEC-44 D2 and D3).
 *
 * The object holds the four nullable schedule fields of `local_assistente_report` and only computes them: it does not read
 * Moodle, does not persist, does not run tasks and does not read the clock. Every instant is a Unix timestamp (int, as in the
 * XMLDB fields) and every "now" is passed by the caller.
 *
 * Time rules:
 * - the periodic update is due exactly 72 absolute hours (259200 seconds) after the last successful update; the site time zone
 *   never changes this duration, and a failed update is not a success, so it never moves the interval;
 * - `final_activity_date` is the farthest own date among the eligible activities (dates are instants);
 * - `final_update_at` is 00:00 of the calendar day after `final_activity_date`, read in the site time zone, which is an IANA
 *   identifier (the caller resolves Moodle's "server timezone" setting, for instance with \core_date::normalise_timezone());
 * - when the periodic due instant reaches or passes the final one, a single update is due and it is the final one;
 * - without a dated eligible activity nothing is scheduled: `next_update_at`, `final_activity_date` and `final_update_at` are
 *   null (a missing date is never replaced by a default).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_schedule {
    /** Interval between periodic updates: 72 absolute hours (DEC-06). */
    public const PERIOD_SECONDS = 259200;

    /** The update due is a periodic one. */
    public const TYPE_PERIODIC = 'periodic';

    /** The update due is the final one. */
    public const TYPE_FINAL = 'final';

    /**
     * Constructor.
     *
     * @param int|null $next_update_at Instant of the next update (periodic or final); null if nothing is scheduled.
     * @param int|null $final_activity_date Farthest own date among the eligible activities; null if there is none.
     * @param int|null $final_update_at 00:00 of the day after the final activity date, in the site time zone; null if none.
     * @param int|null $final_completed_at Instant the final update succeeded; null while it has not.
     */
    private function __construct(
        public readonly ?int $next_update_at,
        public readonly ?int $final_activity_date,
        public readonly ?int $final_update_at,
        public readonly ?int $final_completed_at,
    ) {
    }

    /**
     * Calculate the schedule after a successful update (or after any recalculation).
     *
     * @param array $activity_dates Own dates of the eligible activities: int Unix timestamps; null and values <= 0 (Moodle
     *     stores "no date" as 0) mean the activity has no date and are ignored.
     * @param int|null $last_success_at Instant of the last successful update; may be null only when no date applies.
     * @param string $timezone IANA identifier of the site time zone.
     * @param int|null $final_completed_at Current `final_completed_at` of the report, null if the final update did not happen.
     * @param int $now Reference instant, used only to decide whether a moved date reopens a completed report.
     * @return self
     * @throws \InvalidArgumentException If there is a date but no successful update (the first version is generated when the
     *     report is created, plan 019 Q-019-04) or the time zone is invalid.
     */
    public static function calculate(
        array $activity_dates,
        ?int $last_success_at,
        string $timezone,
        ?int $final_completed_at,
        int $now,
    ): self {
        $farthest = self::farthest($activity_dates);
        if ($farthest === null) {
            return new self(null, null, null, $final_completed_at);
        }
        if ($last_success_at === null) {
            throw new \InvalidArgumentException('A schedule with a final date needs the last successful update.');
        }

        $final_update_at = self::final_update_at_of($farthest, $timezone);
        if ($final_completed_at !== null && $final_update_at > $now) {
            $final_completed_at = null; // DEC-44 D3: the deadline moved to the future after the final update.
        }
        if ($final_completed_at !== null) {
            return new self(null, $farthest, $final_update_at, $final_completed_at);
        }

        $next = min($last_success_at + self::PERIOD_SECONDS, $final_update_at);
        return new self($next, $farthest, $final_update_at, null);
    }

    /**
     * The final update instant for a final activity date: 00:00 of the next calendar day in the site time zone.
     *
     * @param int $final_activity_date Instant of the farthest own date.
     * @param string $timezone IANA identifier of the site time zone.
     * @return int
     * @throws \InvalidArgumentException If the time zone is invalid.
     */
    public static function final_update_at_of(int $final_activity_date, string $timezone): int {
        try {
            $zone = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid time zone: ' . $timezone, 0, $e);
        }
        return (new \DateTimeImmutable('@' . $final_activity_date))
            ->setTimezone($zone)
            ->setTime(0, 0)
            ->modify('+1 day')
            ->getTimestamp();
    }

    /**
     * Schedule after a successful update at the given instant.
     *
     * A successful final update completes the report; a periodic one starts a new 72 hours (never past the final update).
     *
     * @param int $at Instant of the successful update.
     * @param string $type {@see self::TYPE_PERIODIC} or {@see self::TYPE_FINAL}, as returned by {@see self::due_type()}.
     * @return self
     */
    public function after_success(int $at, string $type): self {
        if ($type === self::TYPE_FINAL) {
            return new self(null, $this->final_activity_date, $this->final_update_at, $at);
        }
        if ($this->final_update_at === null) {
            return $this;
        }
        return new self(
            min($at + self::PERIOD_SECONDS, $this->final_update_at),
            $this->final_activity_date,
            $this->final_update_at,
            $this->final_completed_at,
        );
    }

    /**
     * Whether an update is due at the given instant.
     *
     * @param int $now
     * @return bool
     */
    public function is_due(int $now): bool {
        return $this->next_update_at !== null && $now >= $this->next_update_at;
    }

    /**
     * The type of the single update due at the given instant (periodic and final due together are one final update).
     *
     * @param int $now
     * @return string|null {@see self::TYPE_PERIODIC}, {@see self::TYPE_FINAL} or null when nothing is due.
     */
    public function due_type(int $now): ?string {
        if (!$this->is_due($now)) {
            return null;
        }
        return $this->next_update_at === $this->final_update_at ? self::TYPE_FINAL : self::TYPE_PERIODIC;
    }

    /**
     * Whether this schedule reopened a report that the previous one had completed (DEC-44 D3).
     *
     * @param self $previous Schedule before the recalculation.
     * @return bool
     */
    public function reopens(self $previous): bool {
        return $previous->final_completed_at !== null && $this->final_completed_at === null;
    }

    /**
     * Farthest own date among the eligible activities.
     *
     * @param array $dates
     * @return int|null Null when no activity has a date.
     */
    private static function farthest(array $dates): ?int {
        $dated = array_filter($dates, static fn($date): bool => $date !== null && $date > 0);
        return $dated === [] ? null : max($dated);
    }
}
