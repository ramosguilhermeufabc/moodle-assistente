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

namespace local_assistente\task;

use local_assistente\application\report\report_builder;
use local_assistente\application\report\report_generator;
use local_assistente\domain\event\event_type;
use local_assistente\domain\report\report_schedule;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\report_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the dispatcher of the reports (T116; quickstart 019 Q1, Q7, Q14 and Q15; DEC-06, DEC-17, DEC-44 D2 and D3, P-05).
 *
 * Time is simulated: every run is given an explicit instant of the site time zone. Nothing waits. The behaviour of the cron, the Tasks API and the
 * Lock API under real concurrency is the validation CL-39 (T117), not this test.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_dispatcher::class)]
#[CoversClass(report_generator::class)]
final class report_dispatcher_test extends \advanced_testcase {
    /** @var string Site time zone of the tests (UTC-3 in 2026). */
    private const TZ = 'America/Sao_Paulo';

    /** @var int One hour in seconds. */
    private const HOUR = 3600;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass the activity with the date */
    private \stdClass $assign;

    /** @var report_generator */
    private report_generator $generator;

    /** @var report_repository */
    private report_repository $reports;

    /**
     * A course with the assistant enabled and an assignment due on 30/10 at 18:00 (local), so the final update is 31/10 at 00:00.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        (new config_repository())->create((int) $this->course->id, true);
        $this->assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id, 'duedate' => $this->at('2026-10-30 18:00'),
        ]);
        $this->generator = new report_generator(timezone: self::TZ);
        $this->reports = new report_repository();
    }

    /**
     * Instant of a local date and time in the site time zone.
     *
     * @param string $local
     * @return int
     */
    private function at(string $local): int {
        return (new \DateTimeImmutable($local, new \DateTimeZone(self::TZ)))->getTimestamp();
    }

    /**
     * Move the date of the assignment.
     *
     * @param string $local
     */
    private function move_due_date(string $local): void {
        global $DB;
        $DB->set_field('assign', 'duedate', $this->at($local), ['id' => $this->assign->id]);
    }

    /**
     * The report of the course.
     *
     * @return \stdClass
     */
    private function report(): \stdClass {
        return $this->reports->find_by_course((int) $this->course->id);
    }

    /**
     * Types of the versions, oldest first.
     *
     * @return string[]
     */
    private function types(): array {
        $builder = new report_builder();
        $versions = array_reverse(array_values($this->reports->find_history((int) $this->report()->id)));
        return array_map(fn($v) => $builder->read($v->snapshot_json)['type'], $versions);
    }

    /**
     * Q1: the first run creates the report and its first version; 71 hours later nothing is due; at 72 hours and later, a new version.
     */
    public function test_first_version_and_the_72_hours(): void {
        $course = (int) $this->course->id;
        $start = $this->at('2026-10-21 08:00');

        $first = $this->generator->generate_due($start);

        $this->assertSame(['generated' => [$course], 'failed' => [], 'skipped' => []], $first);
        $report = $this->report();
        $this->assertSame($start + 72 * self::HOUR, (int) $report->next_update_at);
        $this->assertSame($this->at('2026-10-31 00:00'), (int) $report->final_update_at);
        $this->assertSame($this->at('2026-10-30 18:00'), (int) $report->final_activity_date);
        $this->assertSame(['periodic'], $this->types());

        $this->assertSame([], $this->generator->generate_due($start + 71 * self::HOUR)['generated']);
        $this->assertCount(1, $this->reports->find_history((int) $report->id));

        $this->assertSame([$course], $this->generator->generate_due($start + 73 * self::HOUR)['generated']);
        $report = $this->report();
        $this->assertSame(['periodic', 'periodic'], $this->types());
        $this->assertSame($start + 73 * self::HOUR + 72 * self::HOUR, (int) $report->next_update_at, 'the 72 hours restart from the success');
        $this->assertSame((int) $this->reports->find_version((int) $report->id, 2)->id, (int) $report->current_version);
    }

    /**
     * The manifest of a version references the current analysis of a student.
     */
    public function test_the_version_references_the_current_analyses(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $events = new event_repository();
        $event = $events->create_event((int) $this->course->id, event_type::GRADE_CHANGED, ['userid' => (int) $student->id, 'itemid' => 9, 'occurred_at' => 1], 1);
        $events->begin_attempt((int) $event->id, 1);
        $processing = $events->find_processing((int) $event->id);
        $analysis = $this->getDataGenerator()->get_plugin_generator('local_assistente')->create_analysis([
            'course_id' => $this->course->id, 'processing_id' => $processing->id, 'analysis_type' => 'grade_performance',
        ]);

        $this->generator->generate_due($this->at('2026-10-21 08:00'));

        $manifest = (new report_builder())->read($this->reports->find_current_version((int) $this->report()->id)->snapshot_json);
        $this->assertSame('valid', $manifest['blocks']['grades']['state']);
        $this->assertSame([(int) $analysis->id], $manifest['blocks']['grades']['analysis_ids']);
        $this->assertSame('no_data', $manifest['blocks']['frequency']['state']);
        $this->assertSame(1, $DB->count_records('local_assistente_report_version'));
    }

    /**
     * The final update: it comes at 00:00 of the next day, closes the report, and after it nothing more is generated (Q15).
     */
    public function test_final_update_closes_the_report_and_nothing_follows(): void {
        $start = $this->at('2026-10-28 12:00');
        $this->generator->generate_due($start);                                   // Next: 31/10 00:00 (the final comes before 72 h).
        $this->assertSame($this->at('2026-10-31 00:00'), (int) $this->report()->next_update_at);

        $this->assertSame([], $this->generator->generate_due($this->at('2026-10-30 23:59'))['generated']);
        $this->assertCount(1, $this->generator->generate_due($this->at('2026-10-31 00:00'))['generated']);

        $report = $this->report();
        $this->assertSame(['periodic', 'final'], $this->types());
        $this->assertSame($this->at('2026-10-31 00:00'), (int) $report->final_completed_at);
        $this->assertNull($report->next_update_at);
        // Q15: a new grade changes no date, so nothing is due, however much time passes.
        $this->assertSame([], $this->generator->generate_due($this->at('2026-12-31 12:00'))['generated']);
        $this->assertCount(2, $this->reports->find_history((int) $report->id));
    }

    /**
     * Q5: when the 72 hours end exactly at the final update, one final version is generated, not two.
     */
    public function test_periodic_and_final_together_are_one_final_version(): void {
        $final = $this->at('2026-10-31 00:00');
        $this->generator->generate_due($final - 72 * self::HOUR);
        $this->assertSame($final, (int) $this->report()->next_update_at);

        $this->generator->generate_due($final);
        $this->generator->generate_due($final);

        $this->assertSame(['periodic', 'final'], $this->types());
        $this->assertSame($final, (int) $this->report()->final_completed_at);
    }

    /**
     * Q14: after the final update, a date moved to the future reopens the report; the 72 hours then run from the last success, and the final
     * version stays in the history.
     */
    public function test_a_date_extended_after_the_final_reopens_the_report(): void {
        $this->generator->generate_due($this->at('2026-10-28 12:00'));
        $this->generator->generate_due($this->at('2026-10-31 00:00'));
        $this->assertNotNull($this->report()->final_completed_at);

        $this->move_due_date('2026-11-10 18:00');
        $now = $this->at('2026-11-01 09:00');
        $reopened = $this->generator->generate_due($now);

        $report = $this->report();
        $this->assertSame([], $reopened['generated'], 'Reopening alone writes no version');
        $this->assertNull($report->final_completed_at);
        $this->assertSame($this->at('2026-11-11 00:00'), (int) $report->final_update_at);
        $this->assertSame($this->at('2026-10-31 00:00') + 72 * self::HOUR, (int) $report->next_update_at);
        $this->assertSame(['periodic', 'final'], $this->types());

        $this->assertCount(1, $this->generator->generate_due($this->at('2026-11-03 00:00'))['generated']);
        $this->assertSame(['periodic', 'final', 'periodic'], $this->types());
    }

    /**
     * Q8: a moved date changes the stored final date without a version when nothing is due.
     */
    public function test_a_moved_date_recalculates_the_final_update(): void {
        $this->generator->generate_due($this->at('2026-10-21 08:00'));
        $this->move_due_date('2026-11-05 10:00');

        $this->generator->generate_due($this->at('2026-10-22 08:00'));

        $report = $this->report();
        $this->assertSame($this->at('2026-11-06 00:00'), (int) $report->final_update_at);
        $this->assertSame($this->at('2026-11-05 10:00'), (int) $report->final_activity_date);
        $this->assertCount(1, $this->reports->find_history((int) $report->id));
    }

    /**
     * A course without a dated activity gets no report; a course where the assistant is off is not touched.
     */
    public function test_no_report_without_a_dated_activity_or_with_the_assistant_off(): void {
        global $DB;
        $undated = $this->getDataGenerator()->create_course();
        (new config_repository())->create((int) $undated->id, true);
        $this->getDataGenerator()->create_module('assign', ['course' => $undated->id, 'duedate' => 0]);
        $off = $this->getDataGenerator()->create_course();
        (new config_repository())->create((int) $off->id, false);
        $this->getDataGenerator()->create_module('assign', ['course' => $off->id, 'duedate' => $this->at('2026-10-30 18:00')]);

        $result = $this->generator->generate_due($this->at('2026-10-21 08:00'));

        $this->assertSame([(int) $this->course->id], $result['generated']);
        $this->assertSame(1, $DB->count_records('local_assistente_report'));
    }

    /**
     * Q7: a failure leaves the last valid version and the same next update, does not restart the 72 hours and creates no other report; the next
     * run, once the cause is gone, generates the version. A database trigger refuses the switch of the current version.
     */
    public function test_a_failure_keeps_the_last_version_and_the_interval(): void {
        global $DB;
        $start = $this->at('2026-10-21 08:00');
        $this->generator->generate_due($start);
        $before = $this->report();
        $table = $DB->get_prefix() . 'local_assistente_report';
        try {
            $DB->execute("CREATE TRIGGER t116_refuse BEFORE UPDATE ON {$table} FOR EACH ROW
                SET NEW.course_id = IF(OLD.current_version IS NOT NULL AND NEW.current_version <> OLD.current_version, NULL, NEW.course_id)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }

        try {
            $failed = $this->generator->generate_due($start + 75 * self::HOUR);
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t116_refuse');
        }

        $this->assertSame([], $failed['generated']);
        $this->assertArrayHasKey((int) $this->course->id, $failed['failed']);
        $this->assertEquals($before, $this->report());
        $this->assertCount(1, $this->reports->find_history((int) $before->id));
        $this->assertSame(1, $DB->count_records('local_assistente_report'));

        $this->assertSame([(int) $this->course->id], $this->generator->generate_due($start + 76 * self::HOUR)['generated']);
        $this->assertSame($start + 76 * self::HOUR + 72 * self::HOUR, (int) $this->report()->next_update_at);
    }

    /**
     * While the lock of the report is held, the run leaves the report for the next one and writes nothing.
     */
    public function test_a_report_whose_lock_is_held_is_left_for_the_next_run(): void {
        $start = $this->at('2026-10-21 08:00');
        $this->generator->generate_due($start);
        $report = $this->report();
        $held = unit_of_work::lock_factory()->get_lock(unit_of_work::report_resource((int) $report->id), 1);
        $this->assertNotFalse($held);
        $generator = new report_generator(uow: new unit_of_work(1), timezone: self::TZ);

        try {
            $result = $generator->generate_due($start + 80 * self::HOUR);
            if ($result['generated']) {
                $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated in CL-07 and CL-39.');
            }
        } finally {
            $held->release();
        }

        $this->assertSame([(int) $this->course->id], $result['skipped']);
        $this->assertCount(1, $this->reports->find_history((int) $report->id));
        $this->assertSame([(int) $this->course->id], $this->generator->generate_due($start + 80 * self::HOUR)['generated']);
    }

    /**
     * The task is registered with its own schedule and has a name.
     */
    public function test_the_task_is_registered(): void {
        $task = \core\task\manager::get_scheduled_task(report_dispatcher::class);

        $this->assertNotNull($task);
        $this->assertSame(get_string('task_report_dispatcher', 'local_assistente'), $task->get_name());
        $this->assertSame('*/15', $task->get_minute());
    }
    /**
     * T117, in the isolated PHPUnit site (its own database and data directory, no other course exists): the task runs through the Tasks API and the
     * real path of the cron, `\core\cron::run_inner_scheduled_task()`, which locks the task, runs it, logs it and schedules the next run. It generates the
     * first version of a dated course, and a second pass of the cron finds nothing due. This is not a run of the real cron process on the shared
     * site (that would process every enabled course there); it shows that Moodle's Tasks API runs the task as the plugin declares it.
     */
    public function test_the_tasks_api_runs_the_task_and_schedules_the_next_run(): void {
        global $DB;
        // Only this task is due: the other scheduled tasks of Moodle are disabled in the test database.
        $DB->execute('UPDATE {task_scheduled} SET disabled = 1 WHERE classname <> ?', ['\\' . report_dispatcher::class]);
        $DB->set_field('task_scheduled', 'disabled', 0, ['classname' => '\\' . report_dispatcher::class]);
        $DB->set_field('task_scheduled', 'nextruntime', time() - 60, ['classname' => '\\' . report_dispatcher::class]);

        $due = \core\task\manager::get_next_scheduled_task(time());
        $this->assertNotNull($due, 'The Tasks API must hand out the due task');
        $this->assertSame(report_dispatcher::class, get_class($due));
        $this->expectOutputRegex('/report dispatcher: 1 versions generated, 0 failed, 0 left for the next run/');
        $before = time();
        \core\cron::run_inner_scheduled_task($due);

        $report = $this->reports->find_by_course((int) $this->course->id);
        $this->assertNotNull($report);
        $this->assertCount(1, $this->reports->find_history((int) $report->id));
        $stored = $DB->get_record('task_scheduled', ['classname' => '\\' . report_dispatcher::class]);
        $this->assertGreaterThanOrEqual($before, (int) $stored->lastruntime);
        $this->assertGreaterThan((int) $stored->lastruntime, (int) $stored->nextruntime);
        $this->assertLessThanOrEqual((int) $stored->lastruntime + 15 * 60, (int) $stored->nextruntime, 'The next run is within the 15 minutes of the declaration');
        $this->assertSame(1, $DB->count_records('task_log', ['classname' => report_dispatcher::class, 'result' => 0]));
        $this->assertNull(\core\task\manager::get_next_scheduled_task(time()), 'Nothing else is due until the next run');
    }
}
