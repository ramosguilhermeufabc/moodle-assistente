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

namespace local_assistente\application\report;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the collector of the report (T116; DEC-39 D1 for the activities that fix the final date, contract "Agenda" of spec-019).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_collector::class)]
final class report_collector_test extends \advanced_testcase {
    /**
     * Only the eligible activities with a date of their own give the dates of the report: an assignment (submission), a questionnaire (grade) and a
     * forum count; an activity with no category does not, unless it has completion enabled; a hidden activity, a resource and the end of the course
     * do not.
     */
    public function test_only_eligible_activities_with_their_own_date_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser(); // A forum with a due date writes a calendar event.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1, 'enddate' => 5000000000]);
        $id = (int) $course->id;
        $generator->create_module('assign', ['course' => $id, 'duedate' => 1000]);
        $generator->create_module('quiz', ['course' => $id, 'timeclose' => 2000]);
        $generator->create_module('forum', ['course' => $id, 'duedate' => 3000]);
        $generator->create_module('choice', ['course' => $id, 'timeclose' => 4000]);               // No category: out.
        $generator->create_module('assign', ['course' => $id, 'duedate' => 6000, 'visible' => 0]); // Hidden: out.
        $generator->create_module('page', ['course' => $id, 'completion' => COMPLETION_TRACKING_MANUAL]); // Resource: out, whatever it has.

        $dates = (new report_collector())->activity_dates($id);
        sort($dates);

        $this->assertSame([1000, 2000, 3000], $dates);

        $generator->create_module('choice', ['course' => $id, 'timeclose' => 7000, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $dates = (new report_collector())->activity_dates($id);
        $this->assertSame(7000, max($dates), 'A choice with completion enabled is an eligible activity');
    }

    /**
     * A course whose only date is the end of the course has no date for the report, and a course with nothing has all blocks without data.
     */
    public function test_no_date_and_no_data(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enddate' => 5000000000]);
        $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'duedate' => 0]);

        $collector = new report_collector();
        $blocks = $collector->blocks((int) $course->id);

        $this->assertSame([], $collector->activity_dates((int) $course->id));
        $this->assertSame(report_builder::BLOCKS, array_keys($blocks));
        $this->assertSame(['no_data'], array_unique(array_column($blocks, 'state')));
    }
}
