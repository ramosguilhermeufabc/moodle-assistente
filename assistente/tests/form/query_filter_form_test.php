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

namespace local_assistente\form;

use local_assistente\domain\report\version_filter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the filter form of the query pages (plan 020; DEC-43 D1; DEC-44; quickstart 021 Q10).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(query_filter_form::class)]
final class query_filter_form_test extends \advanced_testcase {
    /**
     * Submit the form.
     *
     * @param array $custom custom data
     * @param array $values submitted fields
     * @return query_filter_form
     */
    private function submitted(array $custom, array $values): query_filter_form {
        query_filter_form::mock_submit($values);
        return new query_filter_form(null, $custom + ['courseid' => 3, 'individual' => false, 'groups' => [], 'sections' => [], 'versions' => []]);
    }

    /**
     * The filters chosen in the lists are returned; an empty choice is null.
     */
    public function test_selected_filters(): void {
        $this->resetAfterTest();
        $custom = ['individual' => true, 'groups' => [11 => 'A', 12 => 'B'], 'sections' => [5 => 'S1', 6 => 'S2'], 'versions' => [1 => 'v1', 2 => 'v2']];

        $form = $this->submitted($custom, ['groupid' => '12', 'sectionid' => '5', 'version' => '2']);
        $this->assertTrue($form->is_validated());
        $this->assertSame(['groupid' => 12, 'sectionid' => 5, 'version' => 2], query_filter_form::selected($form->get_data()));

        $form = $this->submitted($custom, ['groupid' => '', 'sectionid' => '', 'version' => '']);
        $this->assertTrue($form->is_validated());
        $this->assertSame(['groupid' => null, 'sectionid' => null, 'version' => null], query_filter_form::selected($form->get_data()));
        $this->assertSame(['groupid' => null, 'sectionid' => null, 'version' => null], query_filter_form::selected(null));
    }

    /**
     * A value outside the lists given by the page (a group the user may not see, a section of another course) is never applied:
     * the select of the Form API drops it, and the own validation refuses the raw data.
     */
    public function test_values_outside_the_choices_are_not_applied(): void {
        $this->resetAfterTest();
        $custom = ['courseid' => 3, 'individual' => true, 'groups' => [11 => 'A'], 'sections' => [5 => 'S1'], 'versions' => [1 => 'v1']];

        $form = $this->submitted($custom, ['groupid' => '99', 'sectionid' => '77', 'version' => '9']);
        $this->assertSame(['groupid' => null, 'sectionid' => null, 'version' => null], query_filter_form::selected($form->get_data()));

        $errors = $form->validation(['groupid' => '99', 'sectionid' => '5', 'version' => '1'], []);
        $this->assertSame(['groupid'], array_keys($errors));
        $this->assertSame(['sectionid', 'version'], array_keys($form->validation(['groupid' => '11', 'sectionid' => '77', 'version' => '9'], [])));
        $this->assertSame([], $form->validation(['groupid' => '11', 'sectionid' => '5', 'version' => '1'], []));
    }

    /**
     * The aggregate queries have no group filter: a group sent to them is refused, not applied.
     */
    public function test_the_group_exists_only_in_the_individual_queries(): void {
        $this->resetAfterTest();
        $custom = ['individual' => false, 'groups' => [11 => 'A'], 'sections' => [5 => 'S1']];

        $this->assertTrue($this->submitted($custom, ['sectionid' => '5'])->is_validated());
        $form = $this->submitted($custom, ['sectionid' => '5', 'groupid' => '11']);
        $this->assertSame(['groupid' => null, 'sectionid' => 5, 'version' => null], query_filter_form::selected($form->get_data()));
    }
    /**
     * The submission of a period: a date selector sends the day, the month, the year and whether it is on.
     *
     * @param string|null $first "d/m/Y" or null for a selector that is off
     * @param string|null $last
     * @return array
     */
    private static function period(?string $first, ?string $last): array {
        $values = [];
        foreach (['from' => $first, 'to' => $last] as $name => $date) {
            if ($date !== null) {
                [$day, $month, $year] = array_map('intval', explode('/', $date));
                $values[$name] = ['day' => $day, 'month' => $month, 'year' => $year, 'enabled' => 1];
            }
        }
        return $values;
    }

    /**
     * Midnight of a day in the time zone of the site, which is the zone of the date selectors of the form (DEC-44 D2).
     *
     * @param string $date "Y-m-d"
     * @return int
     */
    private static function midnight(string $date): int {
        return (new \DateTimeImmutable($date . ' 00:00:00', new \DateTimeZone(\core_date::get_server_timezone())))->getTimestamp();
    }

    /**
     * Q-020-02: the dates are offered only when the page asks for them (the report and its history).
     */
    public function test_the_dates_exist_only_when_asked(): void {
        $this->resetAfterTest();
        $with = $this->submitted(['dates' => true], self::period('10/11/2026', '12/11/2026'));
        $without = $this->submitted(['dates' => false], self::period('10/11/2026', '12/11/2026'));

        $this->assertSame(['from' => self::midnight('2026-11-10'), 'to' => self::midnight('2026-11-12')], query_filter_form::selected_period($with->get_data()));
        $this->assertSame(['from' => null, 'to' => null], query_filter_form::selected_period($without->get_data()), 'No field, nothing taken');
        $this->assertSame(['from' => null, 'to' => null], query_filter_form::selected_period(null));
    }

    /**
     * Item 3: the first and the last day are valid together, alone, and equal.
     */
    public function test_valid_periods(): void {
        $this->resetAfterTest();
        foreach ([['10/11/2026', '12/11/2026'], ['10/11/2026', '10/11/2026'], ['10/11/2026', null], [null, '12/11/2026'], [null, null]] as [$first, $last]) {
            $form = $this->submitted(['dates' => true], self::period($first, $last));
            $this->assertTrue($form->is_validated(), (string) $first . ' - ' . (string) $last);
        }
        $this->assertSame(
            ['from' => null, 'to' => null],
            query_filter_form::selected_period($this->submitted(['dates' => true], self::period(null, null))->get_data()),
            'Selectors that are off'
        );
    }

    /**
     * Item 10: a last day before the first day is a validation error of the form, on the last day, and gives no data to build a filter.
     */
    public function test_an_inverted_period_is_refused_by_the_form(): void {
        $this->resetAfterTest();
        $form = $this->submitted(['dates' => true], self::period('12/11/2026', '10/11/2026'));

        $this->assertFalse($form->is_validated());
        $this->assertNull($form->get_data());
        $errors = $form->validation(['from' => self::midnight('2026-11-12'), 'to' => self::midnight('2026-11-10')], []);
        $this->assertSame(['to'], array_keys($errors));
        $this->assertSame([], $form->validation(['from' => self::midnight('2026-11-10'), 'to' => self::midnight('2026-11-10')], []), 'The same day is valid');
        $this->assertSame([], $form->validation(['from' => self::midnight('2026-11-10'), 'to' => 0], []), 'A selector that is off is not a limit');
    }

    /**
     * Items 3 and 5: the chain of the page: the form gives the days, version_filter makes them inclusive limits in the site time zone,
     * and the version number of the list combines with them.
     */
    public function test_the_form_feeds_the_filter(): void {
        $this->resetAfterTest();
        $custom = ['dates' => true, 'versions' => [3 => '3 (x)', 4 => '4 (y)']];
        $form = $this->submitted($custom, ['version' => '3'] + self::period('10/11/2026', '12/11/2026'));
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $selected = query_filter_form::selected($data);
        $period = query_filter_form::selected_period($data);

        $filter = version_filter::of($selected['version'], $period['from'], $period['to'], \core_date::get_server_timezone());

        $this->assertSame(3, $filter->version);
        $this->assertSame(self::midnight('2026-11-10'), $filter->from);
        $this->assertSame(self::midnight('2026-11-13') - 1, $filter->to, 'The whole last day is included');
        $this->assertTrue($filter->matches(3, self::midnight('2026-11-13') - 1));
        $this->assertFalse($filter->matches(3, self::midnight('2026-11-13')));
        $this->assertFalse($filter->matches(4, self::midnight('2026-11-11')));
    }
}
