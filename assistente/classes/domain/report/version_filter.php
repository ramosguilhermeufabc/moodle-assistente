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
 * Filter of the versions of a report by number and by execution date (plan 020 Q-020-02; DEC-44).
 *
 * The date is the execution date of the version (`report_version.created_at`, the instant the version was generated). The limits are
 * calendar days of the site time zone (DEC-44 D2) and both are inclusive: the first day starts at 00:00 and the last day goes up to its
 * last second, so a version generated at any moment of the last day is included. The number of the version and the dates combine.
 *
 * Pure rule: no Moodle API, no database. The caller gives the site time zone.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class version_filter {
    /**
     * Constructor; use none() or of().
     *
     * @param int|null $version number of the version
     * @param int|null $from first instant included (00:00 of the first day)
     * @param int|null $to last instant included (last second of the last day)
     */
    private function __construct(
        /** @var int|null number of the version */
        public readonly ?int $version,
        /** @var int|null first instant included */
        public readonly ?int $from,
        /** @var int|null last instant included */
        public readonly ?int $to
    ) {
    }

    /**
     * The filter that selects nothing: the default behaviour.
     *
     * @return self
     */
    public static function none(): self {
        return new self(null, null, null);
    }

    /**
     * A filter from a version number and two calendar days.
     *
     * @param int|null $version number of the version, 1 or more
     * @param int|null $fromday any instant of the first day, or null/0 for no lower limit
     * @param int|null $today any instant of the last day, or null/0 for no upper limit
     * @param string $timezone IANA identifier of the site time zone
     * @return self
     * @throws \InvalidArgumentException for a number below 1, an invalid time zone, or a first day after the last day
     */
    public static function of(?int $version, ?int $fromday, ?int $today, string $timezone): self {
        if ($version !== null && $version < 1) {
            throw new \InvalidArgumentException('The number of a version is 1 or more.');
        }
        try {
            $zone = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid time zone: ' . $timezone, 0, $e);
        }
        $from = $fromday ? (new \DateTimeImmutable('@' . $fromday))->setTimezone($zone)->setTime(0, 0)->getTimestamp() : null;
        $to = $today
            ? (new \DateTimeImmutable('@' . $today))->setTimezone($zone)->setTime(0, 0)->modify('+1 day')->getTimestamp() - 1
            : null;
        if ($from !== null && $to !== null && $from > $to) {
            throw new \InvalidArgumentException('The first day is after the last day.');
        }
        return new self($version, $from, $to);
    }

    /**
     * Whether the filter selects nothing.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return $this->version === null && $this->from === null && $this->to === null;
    }

    /**
     * Whether a version passes the filter: the number (when given) and the execution date inside the limits (when given).
     *
     * @param int $number number of the version
     * @param int $executedat instant the version was generated
     * @return bool
     */
    public function matches(int $number, int $executedat): bool {
        return ($this->version === null || $this->version === $number)
            && ($this->from === null || $executedat >= $this->from)
            && ($this->to === null || $executedat <= $this->to);
    }
}
