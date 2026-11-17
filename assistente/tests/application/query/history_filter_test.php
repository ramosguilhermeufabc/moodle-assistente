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

namespace local_assistente\application\query;

use core\exception\required_capability_exception;
use local_assistente\application\report\report_builder;
use local_assistente\domain\report\report_schedule;
use local_assistente\domain\report\version_filter;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\report_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the filters of the history of the report, by version number and execution date (plan 020 Q-020-02; DEC-44).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(history_query::class)]
final class history_filter_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $teacher;

    /** @var int[] version number => execution instant, in the order they were stored */
    private array $executed = [];

    /**
     * A course with the assistant enabled, an editing teacher and a report with four versions at known instants (Brazilian days).
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        (new config_repository())->create((int) $this->course->id, true);
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');

        $reports = new report_repository();
        $builder = new report_builder();
        $schedule = report_schedule::calculate([], null, 'UTC', null, 0);
        $reportid = (int) $reports->create((int) $this->course->id, $schedule)->id;
        $blocks = array_fill_keys(report_builder::BLOCKS, ['state' => 'no_data']);
        foreach (['2026-11-09 23:59:59', '2026-11-10 00:00:00', '2026-11-12 23:59:59', '2026-11-13 00:00:00'] as $local) {
            $instant = self::at($local);
            $version = $reports->add_version($reportid, $builder->build('periodic', $instant, $blocks), $schedule, $instant);
            $this->executed[(int) $version->version] = $instant;
        }
    }

    /**
     * An instant from a local date and time of the site time zone used by the filters of these tests.
     *
     * @param string $local
     * @return int
     */
    private static function at(string $local): int {
        return (new \DateTimeImmutable($local, new \DateTimeZone('America/Sao_Paulo')))->getTimestamp();
    }

    /**
     * The history of the report with a filter, the version numbers listed.
     *
     * @param version_filter|null $filter
     * @param int|null $user
     * @return int[]
     */
    private function listed(?version_filter $filter, ?int $user = null): array {
        $result = (new history_query())->query((int) $this->course->id, $user ?? (int) $this->teacher->id, history_kind::REPORT, 0, null, $filter);
        return array_column($result['rows'], 'version');
    }

    /**
     * A filter in the tests: number and first and last day.
     *
     * @param int|null $number
     * @param string|null $first
     * @param string|null $last
     * @return version_filter
     */
    private static function filter(?int $number, ?string $first = null, ?string $last = null): version_filter {
        return version_filter::of(
            $number,
            $first === null ? null : self::at("$first 12:00:00"),
            $last === null ? null : self::at("$last 12:00:00"),
            'America/Sao_Paulo'
        );
    }

    /**
     * Item 6: without a filter every version is listed, newest first, and the newest is the current one, as before the filters existed.
     */
    public function test_without_a_filter_the_history_is_what_it_was(): void {
        $query = new history_query();
        $plain = $query->query((int) $this->course->id, (int) $this->teacher->id, history_kind::REPORT);

        $this->assertSame([4, 3, 2, 1], array_column($plain['rows'], 'version'));
        $this->assertSame([true, false, false, false], array_column($plain['rows'], 'current'));
        $this->assertSame([$this->executed[4], $this->executed[3], $this->executed[2], $this->executed[1]], array_column($plain['rows'], 'created_at'));
        $this->assertEquals($plain, $query->query((int) $this->course->id, (int) $this->teacher->id, history_kind::REPORT, 0, null, version_filter::none()));
        $this->assertEquals($plain, $query->query((int) $this->course->id, (int) $this->teacher->id, history_kind::REPORT, 0, null, null));
    }

    /**
     * Item 7: the number of the version.
     */
    public function test_filter_by_version(): void {
        $this->assertSame([2], $this->listed(self::filter(2)));
        $this->assertSame([4], $this->listed(self::filter(4)));
        $this->assertSame([], $this->listed(self::filter(9)));
    }

    /**
     * Item 8, with items 3 and 4: the execution date, inclusive on both limits.
     */
    public function test_filter_by_dates(): void {
        $this->assertSame([3, 2], $this->listed(self::filter(null, '2026-11-10', '2026-11-12')), 'Both limits are included');
        $this->assertSame([2], $this->listed(self::filter(null, '2026-11-10', '2026-11-10')), 'First second of the day');
        $this->assertSame([3], $this->listed(self::filter(null, '2026-11-12', '2026-11-12')), 'Last second of the day');
        $this->assertSame([4, 3], $this->listed(self::filter(null, '2026-11-11')), 'Only the first day');
        $this->assertSame([2, 1], $this->listed(self::filter(null, null, '2026-11-11')), 'Only the last day');
        $this->assertSame([], $this->listed(self::filter(null, '2026-11-11', '2026-11-11')), 'A day with no execution');
    }

    /**
     * Item 9: the number and the dates together.
     */
    public function test_filter_by_version_and_dates(): void {
        $this->assertSame([3], $this->listed(self::filter(3, '2026-11-10', '2026-11-12')));
        $this->assertSame([], $this->listed(self::filter(1, '2026-11-10', '2026-11-12')), 'Right number, date outside');
        $this->assertSame([], $this->listed(self::filter(3, '2026-11-13', '2026-11-13')), 'Right date, other number');
    }

    /**
     * The mark of the current version is the one of the newest version of the report, whether the filter keeps it or not.
     */
    public function test_the_filter_does_not_change_which_version_is_current(): void {
        $result = (new history_query())->query(
            (int) $this->course->id,
            (int) $this->teacher->id,
            history_kind::REPORT,
            0,
            null,
            self::filter(null, '2026-11-10', '2026-11-12')
        );

        $this->assertSame([3, 2], array_column($result['rows'], 'version'));
        $this->assertSame([false, false], array_column($result['rows'], 'current'), 'Version 4 is the current one and is not listed');
    }

    /**
     * Item 10: an inverted interval does not reach the query as a result; the filter is refused when it is built.
     */
    public function test_an_inverted_interval_is_refused(): void {
        $this->expectException(\InvalidArgumentException::class);
        self::filter(null, '2026-11-12', '2026-11-10');
    }

    /**
     * The filter is for the history of the report: the dates of the other histories are those of analyses, and a filter there is refused
     * instead of ignored. An empty filter is no filter.
     */
    public function test_the_filter_applies_only_to_the_history_of_the_report(): void {
        $query = new history_query();
        $course = (int) $this->course->id;
        $teacher = (int) $this->teacher->id;

        $this->assertTrue($query->query($course, $teacher, history_kind::SYNTHESIS, 1, null, version_filter::none())['available']);
        $this->expectException(\InvalidArgumentException::class);
        $query->query($course, $teacher, history_kind::SYNTHESIS, 1, null, self::filter(1));
    }

    /**
     * The filters do not open the history to who has no access, and the course with the assistant off says so.
     */
    public function test_access_and_the_assistant_off(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $outsider = $this->getDataGenerator()->create_user();
        foreach ([(int) $student->id, (int) $outsider->id] as $user) {
            try {
                $this->listed(self::filter(1), $user);
                $this->fail('Access must be denied');
            } catch (required_capability_exception) {
                $this->addToAssertionCount(1);
            }
        }

        $config = new config_repository();
        $config->update((object) ['id' => $config->find_by(['course_id' => $this->course->id])->id, 'enabled' => 0]);
        $off = (new history_query())->query((int) $this->course->id, (int) $this->teacher->id, history_kind::REPORT, 0, null, self::filter(1));
        $this->assertFalse($off['available']);
        $this->assertSame([], $off['rows']);
        $this->assertNotNull($off['message']);
    }

    /**
     * Reading the history with a filter writes nothing.
     */
    public function test_reading_with_a_filter_writes_nothing(): void {
        global $DB;
        $tables = ['local_assistente_report', 'local_assistente_report_version'];
        $fingerprint = fn(): array => array_map(fn(string $t): string => md5(json_encode($DB->get_records($t, null, 'id'))), $tables);
        $before = $fingerprint();

        $this->listed(self::filter(null, '2026-11-10', '2026-11-12'));

        $this->assertSame($before, $fingerprint());
    }
}
