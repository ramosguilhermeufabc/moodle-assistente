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

use core\task\manager;
use core\task\scheduled_task;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the scheduled task of the queue (quickstart 007 Q1 and Q2; DEC-32 D1).
 *
 * The schedule is the native one of Moodle; the plugin has no form of its own for it. How the real cron runs the task is
 * validated in CL-02 (T051).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(process_queue::class)]
final class process_queue_test extends \advanced_testcase {
    /**
     * The task as registered in the installed site.
     *
     * @return scheduled_task
     */
    private function installed_task(): scheduled_task {
        $task = manager::get_scheduled_task(process_queue::class);
        $this->assertNotFalse($task, 'The task must be installed from db/tasks.php');
        return $task;
    }

    /**
     * Q1: the task is installed with an interval of 15 minutes, and has a name.
     */
    public function test_initial_interval_is_15_minutes(): void {
        $this->resetAfterTest();
        $task = $this->installed_task();

        $this->assertSame('*/15', $task->get_minute());
        $this->assertSame('*', $task->get_hour());
        $this->assertSame('*', $task->get_day());
        $this->assertSame('*', $task->get_month());
        $this->assertSame('*', $task->get_day_of_week());
        $this->assertFalse((bool) $task->get_disabled());
        $this->assertNotSame('', $task->get_name());
        $this->assertInstanceOf(scheduled_task::class, $task);
    }

    /**
     * Q1: the queue is one task declared once, with 15 minutes; the other tasks are the detection of closed questionnaires (T074,
     * DEC-36 D4), of the reference dates (T088), the dispatcher of the reports (T116) and the reconciliation of the completed
     * versions of the materials (CL-51); and the plugin has no setting or form of its own for the interval.
     */
    public function test_queue_task_declared_once_and_no_own_interval_setting(): void {
        global $CFG;
        $tasks = [];
        include($CFG->dirroot . '/local/assistente/db/tasks.php');

        $classes = array_column($tasks, 'classname');
        $this->assertSame(
            ['\local_assistente\task\process_queue', '\local_assistente\task\detect_quiz_close', '\local_assistente\task\detect_reference_dates',
                '\local_assistente\task\report_dispatcher',
                '\local_assistente\task\reconcile_material_versions'],
            $classes
        );
        $this->assertSame('*/15', $tasks[0]['minute']);
        $settings = $CFG->dirroot . '/local/assistente/settings.php';
        $this->assertStringNotContainsStringIgnoringCase(
            'interval',
            is_file($settings) ? (string) file_get_contents($settings) : '',
            'The interval is not a plugin setting (DEC-32 D1); settings.php exists only for the yt-dlp path (DEC-67)'
        );
    }

    /**
     * Q2: the administrator changes the interval in the native schedule and the new interval is the one in force: the
     * cycle deadline follows it.
     */
    public function test_interval_changed_in_the_native_schedule_is_in_force(): void {
        $this->resetAfterTest();
        $task = $this->installed_task();
        $starts = [];
        for ($i = 0; $i < 8; $i++) {
            $starts[] = 1900000000 + $i * 7 * MINSECS;
        }
        $waits15 = array_map(static fn(int $t) => $task->get_next_scheduled_time($t) - $t, $starts);
        $this->assertLessThanOrEqual(15 * MINSECS, max($waits15));

        $task->set_minute('*/30');
        manager::configure_scheduled_task($task);
        $changed = $this->installed_task();
        $waits30 = array_map(static fn(int $t) => $changed->get_next_scheduled_time($t) - $t, $starts);

        $this->assertSame('*/30', $changed->get_minute());
        $this->assertLessThanOrEqual(30 * MINSECS, max($waits30));
        $this->assertGreaterThan(15 * MINSECS, max($waits30), 'Some start waits longer than 15 minutes for the next run');
    }

    /**
     * The task runs a cycle and logs one line. Every type of the catalog has a handler now (the last one, synthesis_requested, came with
     * T097), so the case of an event without a handler is covered by queue_processor_test with its own registry; here the queue is empty.
     */
    public function test_execute_runs_a_cycle_and_logs_one_line(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($course->id, true);
        $this->expectOutputRegex('/queue cycle: .*stopped_by=empty/');

        (new process_queue())->execute();

        $this->assertSame(0, $DB->count_records('local_assistente_processing'));
        $this->assertSame(0, $DB->count_records('local_assistente_event'));
    }
}
