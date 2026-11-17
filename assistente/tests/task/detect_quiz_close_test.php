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
use local_assistente\application\quiz\quiz_close_detector;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the detection of closed questionnaires (T074; quickstart 011 Q10; DEC-36 D4).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(detect_quiz_close::class)]
#[CoversClass(quiz_close_detector::class)]
final class detect_quiz_close_test extends \advanced_testcase {
    /**
     * Real transactions and locks are used by the queue.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
    }

    /**
     * A course with the assistant enabled or not.
     *
     * @param bool $enabled
     * @return \stdClass
     */
    private function course(bool $enabled = true): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        if ($enabled) {
            (new config_repository())->create($course->id, true);
        }
        return $course;
    }

    /**
     * A quiz of a course.
     *
     * @param \stdClass $course
     * @param int $timeclose
     * @return \stdClass
     */
    private function quiz(\stdClass $course, int $timeclose): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id, 'timeclose' => $timeclose,
        ]);
    }

    /**
     * Events `quiz_closed` of the queue.
     *
     * @return \stdClass[]
     */
    private function queued(): array {
        global $DB;
        return array_values($DB->get_records('local_assistente_event', ['event_type' => 'quiz_closed'], 'id ASC'));
    }

    /**
     * The task is installed from db/tasks.php, every 15 minutes, with a name.
     */
    public function test_task_is_installed_every_15_minutes(): void {
        $task = manager::get_scheduled_task(detect_quiz_close::class);

        $this->assertNotFalse($task, 'The task must be installed from db/tasks.php');
        $this->assertSame('*/15', $task->get_minute());
        $this->assertSame('Detect closed questionnaires', $task->get_name());
    }

    /**
     * Q10: a questionnaire whose closing time passed, with no event from Moodle, queues one final recalculation.
     */
    public function test_closed_questionnaire_queues_one_final_recalculation(): void {
        $course = $this->course();
        $now = time();
        $quiz = $this->quiz($course, $now - 100);

        (new quiz_close_detector())->detect($now);

        $events = $this->queued();
        $this->assertCount(1, $events);
        $payload = json_decode($events[0]->payload, true);
        $this->assertSame((int) $quiz->cmid, $payload['cmid']);
        $this->assertSame($now - 100, $payload['timeclose']);
        $this->assertSame((int) $course->id, (int) $events[0]->course_id);
        $this->assertSame('pending', $events[0]->status);
    }

    /**
     * The same closing queues once however many times the task runs; a new closing time is a new closing.
     */
    public function test_the_same_closing_queues_once_and_a_new_closing_time_queues_again(): void {
        global $DB;
        $course = $this->course();
        $now = time();
        $quiz = $this->quiz($course, $now - 100);
        $detector = new quiz_close_detector();

        $detector->detect($now);
        $detector->detect($now + 60);
        $detector->detect($now + 120);
        $this->assertCount(1, $this->queued());

        $DB->set_field('quiz', 'timeclose', $now - 50, ['id' => $quiz->id]);
        $detector->detect($now + 180);
        $this->assertCount(2, $this->queued(), 'Another closing time is another closing');
    }

    /**
     * Not closed yet, without a closing time (partial aggregates), a course without the assistant and a module being deleted queue
     * nothing.
     */
    public function test_not_closed_without_timeclose_not_enabled_or_deleting_queue_nothing(): void {
        global $DB;
        $course = $this->course();
        $now = time();
        $this->quiz($course, $now + 3600);
        $this->quiz($course, 0);
        $this->quiz($this->course(false), $now - 100);
        $deleting = $this->quiz($course, $now - 100);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $deleting->cmid]);

        $results = (new quiz_close_detector())->detect($now);

        $this->assertSame([], $this->queued());
        $this->assertSame([], $results);
    }

    /**
     * The task runs the detection and logs one line.
     */
    public function test_execute_queues_and_logs_one_line(): void {
        $course = $this->course();
        $this->quiz($course, time() - 100);
        $this->expectOutputRegex('/quiz close detection: 1 closed questionnaires, 1 queued/');

        (new detect_quiz_close())->execute();

        $this->assertCount(1, $this->queued());
    }

    /**
     * CL-33 (T075): the task is run by the real executor of the scheduled tasks of Moodle, with a questionnaire whose `timeclose` has
     * passed. The Tasks API hands out the due task (`manager::get_next_scheduled_task()`) and `\core\cron::run_inner_scheduled_task()`,
     * the path of the cron, locks it, runs it, logs it and schedules the next run. This is not the CLI script on the shared site, which
     * would process every enabled course there (admin/cli/scheduled_task.php uses the same method).
     */
    public function test_the_real_scheduled_task_executor_queues_the_closed_questionnaire(): void {
        global $DB;
        $course = $this->course();
        $quiz = $this->quiz($course, time() - 100);
        $classname = '\\' . detect_quiz_close::class;
        // Only this task is due: the other scheduled tasks of Moodle are disabled in the test database.
        $DB->execute('UPDATE {task_scheduled} SET disabled = 1 WHERE classname <> ?', [$classname]);
        $DB->set_field('task_scheduled', 'disabled', 0, ['classname' => $classname]);
        $DB->set_field('task_scheduled', 'nextruntime', time() - 60, ['classname' => $classname]);

        $due = manager::get_next_scheduled_task(time());
        $this->assertNotNull($due, 'The Tasks API must hand out the due task');
        $this->assertSame(detect_quiz_close::class, get_class($due));
        $this->expectOutputRegex('/quiz close detection: 1 closed questionnaires, 1 queued/');
        $before = time();
        \core\cron::run_inner_scheduled_task($due);

        $queued = $this->queued();
        $this->assertCount(1, $queued);
        $this->assertSame((int) $quiz->cmid, json_decode($queued[0]->payload, true)['cmid']);
        $stored = $DB->get_record('task_scheduled', ['classname' => $classname]);
        $this->assertGreaterThanOrEqual($before, (int) $stored->lastruntime);
        $this->assertGreaterThan((int) $stored->lastruntime, (int) $stored->nextruntime);
        $this->assertSame(1, $DB->count_records('task_log', ['classname' => detect_quiz_close::class, 'result' => 0]));
        $this->assertNull(manager::get_next_scheduled_task(time()), 'Nothing else is due until the next run');
    }
}
