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
 * Schedule rules of the report (quickstart 019 Q1, Q2, Q3, Q5, Q6, Q8, Q13 and Q14, in the domain).
 *
 * Time is simulated: every instant is built explicitly in a named time zone and passed as "now"; nothing waits, reads the
 * clock or depends on the time zone of the machine. This shows the domain rules only, not cron, the Tasks API or locks (CL-39).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_schedule::class)]
final class report_schedule_test extends \basic_testcase {
    /** @var string Site time zone of the tests (UTC-3 in 2026). */
    private const SITE_TZ = 'America/Sao_Paulo';

    /** @var int One hour in seconds. */
    private const HOUR = 3600;

    /**
     * Instant of a local date and time in the site time zone.
     *
     * @param string $local For instance '2026-10-20 12:00'.
     * @return int
     */
    private function at(string $local): int {
        return (new \DateTimeImmutable($local, new \DateTimeZone(self::SITE_TZ)))->getTimestamp();
    }

    /**
     * Schedule with a far final date, so the periodic update is the one that falls due.
     *
     * @param int $last_success_at
     * @return report_schedule
     */
    private function periodic_schedule(int $last_success_at): report_schedule {
        return report_schedule::calculate([$this->at('2026-12-31 18:00')], $last_success_at, self::SITE_TZ, null, $last_success_at);
    }

    /**
     * Q1: 71 hours is not due, 72 absolute hours is, 73 hours still is; a failure moves nothing.
     */
    public function test_periodic_update_is_due_after_72_absolute_hours(): void {
        $last = $this->at('2026-10-20 12:00');
        $schedule = $this->periodic_schedule($last);

        $this->assertSame($last + 72 * self::HOUR, $schedule->next_update_at);
        $this->assertFalse($schedule->is_due($last + 71 * self::HOUR));
        $this->assertNull($schedule->due_type($last + 71 * self::HOUR));
        $this->assertTrue($schedule->is_due($last + 72 * self::HOUR));
        $this->assertSame(report_schedule::TYPE_PERIODIC, $schedule->due_type($last + 72 * self::HOUR));
        $this->assertTrue($schedule->is_due($last + 73 * self::HOUR));
        $this->assertSame(report_schedule::TYPE_PERIODIC, $schedule->due_type($last + 73 * self::HOUR));
    }

    /**
     * Q1: a failed update is not a success, so recalculating with the same last success keeps the due instant.
     */
    public function test_failure_does_not_restart_the_interval(): void {
        $last = $this->at('2026-10-20 12:00');
        $before = $this->periodic_schedule($last);
        // The attempt at 72 h fails: nothing is recorded as success, and the next calculation still uses the last success.
        $after = report_schedule::calculate(
            [$this->at('2026-12-31 18:00')],
            $last,
            self::SITE_TZ,
            null,
            $last + 74 * self::HOUR,
        );

        $this->assertSame($before->next_update_at, $after->next_update_at);
        $this->assertTrue($after->is_due($last + 74 * self::HOUR));
    }

    /**
     * Q1: a success starts 72 new hours from its own instant.
     */
    public function test_success_starts_new_72_hours_from_its_instant(): void {
        $last = $this->at('2026-10-20 12:00');
        $schedule = $this->periodic_schedule($last);
        $success_at = $last + 80 * self::HOUR;

        $next = $schedule->after_success($success_at, report_schedule::TYPE_PERIODIC);

        $this->assertSame($success_at + 72 * self::HOUR, $next->next_update_at);
        $this->assertNull($next->final_completed_at);
    }

    /**
     * Q1: 72 hours are absolute: across a day change the duration is not three calendar days at midnight.
     */
    public function test_72_hours_do_not_align_to_calendar_days(): void {
        $last = $this->at('2026-10-20 23:59');
        $schedule = $this->periodic_schedule($last);

        $this->assertSame($this->at('2026-10-23 23:59'), $schedule->next_update_at);
    }

    /**
     * Q2: the farthest own date defines the final date and the final update is 00:00 of the next day in the site time zone.
     */
    public function test_farthest_eligible_date_defines_the_final_update(): void {
        $dates = [$this->at('2026-10-10 12:00'), $this->at('2026-10-25 18:00'), null, 0, $this->at('2026-10-15 09:00')];
        $last = $this->at('2026-10-01 08:00');

        $schedule = report_schedule::calculate($dates, $last, self::SITE_TZ, null, $last);

        $this->assertSame($this->at('2026-10-25 18:00'), $schedule->final_activity_date);
        $this->assertSame($this->at('2026-10-26 00:00'), $schedule->final_update_at);
    }

    /**
     * Q2: the periodic updates stop at the final one; the next update never passes it.
     */
    public function test_next_update_never_passes_the_final_update(): void {
        $last = $this->at('2026-10-24 12:00');
        $schedule = report_schedule::calculate([$this->at('2026-10-25 18:00')], $last, self::SITE_TZ, null, $last);

        // 72 h after the last success would be 27/10 12:00, after the final of 26/10 00:00.
        $this->assertSame($this->at('2026-10-26 00:00'), $schedule->next_update_at);
        $this->assertSame(report_schedule::TYPE_FINAL, $schedule->due_type($this->at('2026-10-26 00:00')));
    }

    /**
     * Q3: after the final update succeeds nothing more is scheduled and nothing falls due.
     */
    public function test_nothing_is_scheduled_after_the_final_update(): void {
        $last = $this->at('2026-10-24 12:00');
        $dates = [$this->at('2026-10-25 18:00')];
        $schedule = report_schedule::calculate($dates, $last, self::SITE_TZ, null, $last);
        $final_at = $this->at('2026-10-26 00:05');

        $closed = $schedule->after_success($final_at, report_schedule::TYPE_FINAL);

        $this->assertNull($closed->next_update_at);
        $this->assertSame($final_at, $closed->final_completed_at);
        $this->assertFalse($closed->is_due($final_at + 100 * self::HOUR));
        // A recalculation with the same dates later (for instance after a new grade) does not reopen it.
        $again = report_schedule::calculate($dates, $final_at, self::SITE_TZ, $final_at, $final_at + 200 * self::HOUR);
        $this->assertNull($again->next_update_at);
        $this->assertSame($final_at, $again->final_completed_at);
        $this->assertFalse($again->reopens($closed));
    }

    /**
     * Q5: periodic and final due at the same instant give one update, and it is the final one.
     */
    public function test_coinciding_periodic_and_final_is_one_final_update(): void {
        $final_update = $this->at('2026-10-26 00:00');
        $last = $final_update - 72 * self::HOUR;

        $schedule = report_schedule::calculate([$this->at('2026-10-25 18:00')], $last, self::SITE_TZ, null, $last);

        $this->assertSame($final_update, $schedule->next_update_at);
        $this->assertSame($schedule->next_update_at, $schedule->final_update_at);
        $this->assertNull($schedule->due_type($final_update - 1));
        $this->assertSame(report_schedule::TYPE_FINAL, $schedule->due_type($final_update));
        $this->assertNull($schedule->after_success($final_update, report_schedule::TYPE_FINAL)->next_update_at);
    }

    /**
     * Q6: without a dated eligible activity nothing is scheduled and the fields stay null.
     */
    public function test_without_final_date_nothing_is_scheduled(): void {
        foreach ([[], [null], [0, null], [-1]] as $dates) {
            $schedule = report_schedule::calculate($dates, null, self::SITE_TZ, null, $this->at('2026-10-20 12:00'));

            $this->assertNull($schedule->next_update_at);
            $this->assertNull($schedule->final_activity_date);
            $this->assertNull($schedule->final_update_at);
            $this->assertNull($schedule->final_completed_at);
            $this->assertFalse($schedule->is_due($this->at('2027-01-01 00:00')));
        }
    }

    /**
     * Q6: the schedule is cleared when the dated activities disappear, with a last success on record.
     */
    public function test_removing_every_date_clears_the_schedule(): void {
        $last = $this->at('2026-10-20 12:00');

        $schedule = report_schedule::calculate([null], $last, self::SITE_TZ, null, $last);

        $this->assertNull($schedule->next_update_at);
        $this->assertNull($schedule->final_activity_date);
        $this->assertNull($schedule->final_update_at);
    }

    /**
     * Q8: moving the farthest date recalculates the final date and the final update.
     */
    public function test_changing_the_farthest_date_recalculates_the_final_update(): void {
        $last = $this->at('2026-10-20 12:00');
        $before = report_schedule::calculate(
            [$this->at('2026-10-22 12:00'), $this->at('2026-10-30 12:00')],
            $last,
            self::SITE_TZ,
            null,
            $last,
        );
        $this->assertSame($this->at('2026-10-31 00:00'), $before->final_update_at);

        $after = report_schedule::calculate(
            [$this->at('2026-10-22 12:00'), $this->at('2026-10-23 12:00')],
            $last,
            self::SITE_TZ,
            null,
            $last,
        );

        $this->assertSame($this->at('2026-10-23 12:00'), $after->final_activity_date);
        $this->assertSame($this->at('2026-10-24 00:00'), $after->final_update_at);
        $this->assertSame($this->at('2026-10-23 12:00') + 12 * self::HOUR, $after->final_update_at);
        $this->assertSame($this->at('2026-10-23 12:00'), $after->next_update_at); // 72 h after 20/10 12:00, before the final.
        $this->assertFalse($after->reopens($before));
    }

    /**
     * Q13: UTC-3 site, last activity ends 25/10 at 23:30 local: the final update is 26/10 at 00:00 local, and 72 h stay absolute.
     */
    public function test_site_time_zone_utc_minus_3(): void {
        $end = (new \DateTimeImmutable('2026-10-25 23:30:00', new \DateTimeZone(self::SITE_TZ)));
        $this->assertSame(-3 * self::HOUR, $end->getOffset());
        $last = $end->getTimestamp() - 100 * self::HOUR;

        $schedule = report_schedule::calculate([$end->getTimestamp()], $last, self::SITE_TZ, null, $last);

        $expected_final = (new \DateTimeImmutable('2026-10-26 00:00:00', new \DateTimeZone(self::SITE_TZ)))->getTimestamp();
        $this->assertSame($expected_final, $schedule->final_update_at);
        // 26/10 00:00 at UTC-3 is 26/10 03:00 UTC: the same instant read in UTC, 30 minutes after the activity end.
        $this->assertSame('2026-10-26 03:00', gmdate('Y-m-d H:i', $schedule->final_update_at));
        $this->assertSame($end->getTimestamp() + 30 * 60, $schedule->final_update_at);
        // 72 h are absolute: from a last success 10 hours before the final, the periodic is 72 h later, the final is earlier.
        $near = report_schedule::calculate([$end->getTimestamp()], $expected_final - 10 * self::HOUR, self::SITE_TZ, null, 0);
        $this->assertSame($expected_final, $near->next_update_at);
        // And with a distant final date the 72 h are the same number of seconds in any site time zone.
        foreach (['UTC', 'Asia/Tokyo', self::SITE_TZ] as $zone) {
            $far = report_schedule::calculate([$end->getTimestamp() + 400 * self::HOUR], $last, $zone, null, $last);
            $this->assertSame($last + 72 * self::HOUR, $far->next_update_at);
        }
    }

    /**
     * Q13: the local date, not the UTC date, decides the day: 23:30 local on the 25th is already the 26th in UTC.
     */
    public function test_local_date_is_not_read_as_utc(): void {
        $end = $this->at('2026-10-25 23:30');
        $this->assertSame('2026-10-26 02:30', gmdate('Y-m-d H:i', $end));

        $utc_site = report_schedule::final_update_at_of($end, 'UTC');
        $local_site = report_schedule::final_update_at_of($end, self::SITE_TZ);

        $this->assertSame('2026-10-27 00:00', gmdate('Y-m-d H:i', $utc_site));
        $this->assertSame('2026-10-26 03:00', gmdate('Y-m-d H:i', $local_site));
    }

    /**
     * The result does not depend on the default time zone of the PHP process.
     */
    public function test_result_does_not_depend_on_the_machine_time_zone(): void {
        $dates = [$this->at('2026-10-25 23:30')];
        $last = $this->at('2026-10-24 12:00');
        $original = date_default_timezone_get();
        $results = [];
        try {
            foreach (['UTC', 'Pacific/Auckland', 'America/Los_Angeles'] as $machine) {
                date_default_timezone_set($machine);
                $s = report_schedule::calculate($dates, $last, self::SITE_TZ, null, $last);
                $results[] = [$s->next_update_at, $s->final_activity_date, $s->final_update_at];
            }
        } finally {
            date_default_timezone_set($original);
        }

        $this->assertSame($results[0], $results[1]);
        $this->assertSame($results[0], $results[2]);
        $this->assertSame($this->at('2026-10-26 00:00'), $results[0][2]);
    }

    /**
     * Q14: after a completed final update, a date moved to the future reopens the cycle.
     */
    public function test_extending_the_deadline_after_the_final_reopens_the_cycle(): void {
        $last = $this->at('2026-10-24 12:00');
        $closed = report_schedule::calculate([$this->at('2026-10-25 18:00')], $last, self::SITE_TZ, null, $last)
            ->after_success($this->at('2026-10-26 00:05'), report_schedule::TYPE_FINAL);
        $now = $this->at('2026-10-27 10:00');

        $reopened = report_schedule::calculate(
            [$this->at('2026-10-25 18:00'), $this->at('2026-11-05 18:00')],
            $this->at('2026-10-26 00:05'),
            self::SITE_TZ,
            $closed->final_completed_at,
            $now,
        );

        $this->assertNull($reopened->final_completed_at);
        $this->assertTrue($reopened->reopens($closed));
        $this->assertSame($this->at('2026-11-05 18:00'), $reopened->final_activity_date);
        $this->assertSame($this->at('2026-11-06 00:00'), $reopened->final_update_at);
        // The 72 h resume from the last success (26/10 00:05), so the next update is periodic, on 29/10 00:05.
        $this->assertSame($this->at('2026-10-29 00:05'), $reopened->next_update_at);
        $this->assertSame(report_schedule::TYPE_PERIODIC, $reopened->due_type($this->at('2026-10-29 00:05')));
    }

    /**
     * Q14: a date moved but still in the past does not reopen the cycle, and neither does an unchanged date.
     */
    public function test_a_past_or_unchanged_date_does_not_reopen(): void {
        $completed = $this->at('2026-10-26 00:05');
        $now = $this->at('2026-11-20 10:00');

        $unchanged = report_schedule::calculate([$this->at('2026-10-25 18:00')], $completed, self::SITE_TZ, $completed, $now);
        $moved_in_past = report_schedule::calculate([$this->at('2026-11-01 18:00')], $completed, self::SITE_TZ, $completed, $now);

        $this->assertSame($completed, $unchanged->final_completed_at);
        $this->assertNull($unchanged->next_update_at);
        $this->assertSame($completed, $moved_in_past->final_completed_at);
        $this->assertNull($moved_in_past->next_update_at);
        $this->assertSame($this->at('2026-11-02 00:00'), $moved_in_past->final_update_at);
    }

    /**
     * Whether a deadline moved earlier before the final update brings the final update forward.
     */
    public function test_moving_the_date_earlier_before_the_final_brings_it_forward(): void {
        $last = $this->at('2026-10-20 12:00');

        $schedule = report_schedule::calculate([$this->at('2026-10-21 10:00')], $last, self::SITE_TZ, null, $last);

        $this->assertSame($this->at('2026-10-22 00:00'), $schedule->next_update_at);
        $this->assertSame(report_schedule::TYPE_FINAL, $schedule->due_type($this->at('2026-10-22 00:00')));
    }

    /**
     * A final update that was not run in time is still due later, and as the final one.
     */
    public function test_a_missed_final_update_stays_due(): void {
        $last = $this->at('2026-10-24 12:00');
        $schedule = report_schedule::calculate([$this->at('2026-10-25 18:00')], $last, self::SITE_TZ, null, $last);

        $this->assertSame(report_schedule::TYPE_FINAL, $schedule->due_type($this->at('2026-11-30 00:00')));
    }

    /**
     * Invalid input is refused, not guessed.
     */
    public function test_invalid_input_is_refused(): void {
        try {
            report_schedule::calculate([$this->at('2026-10-25 18:00')], null, self::SITE_TZ, null, 0);
            $this->fail('A final date without a last successful update must be refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('last successful update', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        report_schedule::final_update_at_of($this->at('2026-10-25 18:00'), 'Not/AZone');
    }
}
