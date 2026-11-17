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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the filter of the versions of a report by number and execution date (plan 020 Q-020-02; DEC-44).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(version_filter::class)]
final class version_filter_test extends \basic_testcase {
    /** @var string site time zone of the tests */
    private const ZONE = 'America/Sao_Paulo';

    /**
     * An instant from a local date and time in the site time zone.
     *
     * @param string $local such as "2026-11-10 23:59:59"
     * @param string $zone
     * @return int
     */
    private static function at(string $local, string $zone = self::ZONE): int {
        return (new \DateTimeImmutable($local, new \DateTimeZone($zone)))->getTimestamp();
    }

    /**
     * Without a number and without dates the filter selects nothing and lets everything pass.
     */
    public function test_the_empty_filter_is_the_default(): void {
        $none = version_filter::none();
        $this->assertTrue($none->is_empty());
        $this->assertTrue($none->matches(1, 1));
        $this->assertTrue(version_filter::of(null, null, null, self::ZONE)->is_empty());
        $this->assertTrue(version_filter::of(null, 0, 0, self::ZONE)->is_empty(), 'A date selector that is off gives 0');
    }

    /**
     * Item 3: both limits are inclusive, to the second, in the days of the site time zone.
     */
    public function test_the_limits_are_inclusive(): void {
        $filter = version_filter::of(null, self::at('2026-11-10 15:30:00'), self::at('2026-11-12 08:00:00'), self::ZONE);

        $this->assertSame(self::at('2026-11-10 00:00:00'), $filter->from);
        $this->assertSame(self::at('2026-11-12 23:59:59'), $filter->to);
        $this->assertTrue($filter->matches(1, self::at('2026-11-10 00:00:00')), 'First second of the first day');
        $this->assertTrue($filter->matches(1, self::at('2026-11-12 23:59:59')), 'Last second of the last day');
        $this->assertTrue($filter->matches(1, self::at('2026-11-11 12:00:00')));
    }

    /**
     * Item 4: an execution outside the interval does not pass, by one second.
     */
    public function test_outside_the_interval_does_not_pass(): void {
        $filter = version_filter::of(null, self::at('2026-11-10 12:00:00'), self::at('2026-11-12 12:00:00'), self::ZONE);

        $this->assertFalse($filter->matches(1, self::at('2026-11-09 23:59:59')));
        $this->assertFalse($filter->matches(1, self::at('2026-11-13 00:00:00')));
    }

    /**
     * Only one limit: an open interval on the other side.
     */
    public function test_one_limit_alone(): void {
        $from = version_filter::of(null, self::at('2026-11-10 12:00:00'), null, self::ZONE);
        $to = version_filter::of(null, null, self::at('2026-11-10 12:00:00'), self::ZONE);

        $this->assertTrue($from->matches(1, self::at('2030-01-01 00:00:00')));
        $this->assertFalse($from->matches(1, self::at('2026-11-09 23:59:59')));
        $this->assertTrue($to->matches(1, self::at('2020-01-01 00:00:00')));
        $this->assertTrue($to->matches(1, self::at('2026-11-10 23:59:59')));
        $this->assertFalse($to->matches(1, self::at('2026-11-11 00:00:00')));
    }

    /**
     * Item 5: the number and the dates combine; both must pass.
     */
    public function test_the_number_and_the_dates_combine(): void {
        $filter = version_filter::of(2, self::at('2026-11-10 12:00:00'), self::at('2026-11-12 12:00:00'), self::ZONE);

        $this->assertTrue($filter->matches(2, self::at('2026-11-11 10:00:00')));
        $this->assertFalse($filter->matches(3, self::at('2026-11-11 10:00:00')), 'Other number');
        $this->assertFalse($filter->matches(2, self::at('2026-11-13 10:00:00')), 'Other date');
        $this->assertTrue(version_filter::of(2, null, null, self::ZONE)->matches(2, 1));
        $this->assertFalse(version_filter::of(2, null, null, self::ZONE)->matches(1, 1));
    }

    /**
     * The days are those of the site time zone, not of UTC: the same instant is in different days in two zones.
     */
    public function test_the_day_is_the_one_of_the_site_time_zone(): void {
        $instant = self::at('2026-11-10 22:00:00'); // 01:00 of the 11th in UTC.
        $saopaulo = version_filter::of(null, $instant, $instant, self::ZONE);
        $utc = version_filter::of(null, $instant, $instant, 'UTC');

        $this->assertTrue($saopaulo->matches(1, self::at('2026-11-10 03:00:00')));
        $this->assertFalse($utc->matches(1, self::at('2026-11-10 03:00:00')));
        $this->assertTrue($utc->matches(1, self::at('2026-11-11 03:00:00', 'UTC')));
    }

    /**
     * A day of 25 hours (end of daylight saving time) is included up to its last second, and the next day starts after it.
     */
    public function test_a_day_of_25_hours_is_included_to_the_end(): void {
        $zone = 'America/New_York';
        $filter = version_filter::of(null, null, self::at('2026-11-01 12:00:00', $zone), $zone);

        $this->assertSame(self::at('2026-11-01 23:59:59', $zone), $filter->to);
        $this->assertSame(25 * 3600, self::at('2026-11-02 00:00:00', $zone) - self::at('2026-11-01 00:00:00', $zone));
        $this->assertTrue($filter->matches(1, self::at('2026-11-01 23:59:59', $zone)));
        $this->assertFalse($filter->matches(1, self::at('2026-11-02 00:00:00', $zone)));
    }

    /**
     * Item 10: an inverted interval, a number below 1 and an invalid time zone are refused, not turned into a result.
     */
    public function test_invalid_input_is_refused(): void {
        $first = self::at('2026-11-12 12:00:00');
        $last = self::at('2026-11-10 12:00:00');
        foreach ([
            fn() => version_filter::of(null, $first, $last, self::ZONE),
            fn() => version_filter::of(0, null, null, self::ZONE),
            fn() => version_filter::of(-1, null, null, self::ZONE),
            fn() => version_filter::of(null, $first, $first, 'Not/AZone'),
        ] as $call) {
            try {
                $call();
                $this->fail('The input must be refused');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $sameday = version_filter::of(null, $first, $first, self::ZONE);
        $this->assertTrue($sameday->matches(1, $first), 'The same day as first and last day is valid');
    }
}
