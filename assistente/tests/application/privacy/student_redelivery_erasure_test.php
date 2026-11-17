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

namespace local_assistente\application\privacy;

use core\task\manager;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\domain\event\erased_subject;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\privacy\provider;
use local_assistente\task\redeliver_event;
use PHPUnit\Framework\Attributes\CoversClass;

require_once(__DIR__ . '/../../fixtures/lock_observer.php');

/**
 * Tests of the erasure of a student and the deliveries waiting for a retry (DEC-57, DEC-58; exception to DEC-32 D2).
 *
 * A delivery that waits for a retry holds the identifiers of a student. The erasure removes those tasks inside its transaction, and a
 * task that Moodle had already handed to a run creates nothing afterwards (the guard, inside the critical section of the queue).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(student_data_eraser::class)]
#[CoversClass(event_redelivery_scheduler::class)]
final class student_redelivery_erasure_test extends \core_privacy\tests\provider_testcase {
    use \local_assistente\tests\fixtures\lock_observer;

    /** @var \stdClass[] two courses with the assistant enabled */
    private array $courses;

    /** @var \stdClass[] two students, enrolled in both courses */
    private array $students;

    /** @var event_redelivery_scheduler */
    private event_redelivery_scheduler $scheduler;

    /**
     * Real transactions, locks and tasks are used.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->courses = [$generator->create_course(), $generator->create_course()];
        $this->students = [
            $generator->create_user(['firstname' => 'Ana', 'lastname' => 'Souza']),
            $generator->create_user(['firstname' => 'Bruno', 'lastname' => 'Lima']),
        ];
        foreach ($this->courses as $course) {
            (new config_repository())->create($course->id, true);
            foreach ($this->students as $student) {
                $generator->enrol_user($student->id, $course->id, 'student');
            }
        }
        $this->scheduler = new event_redelivery_scheduler();
        // The provider writes a line of counts for the log of PHP; the tests read the effects, not that line.
        provider::use_tracer(static function (string $line): void {
        });
    }

    /**
     * The tracer of the provider is the default again.
     */
    public function tearDown(): void {
        $this->stop_recording_locks();
        $this->end_actors();
        provider::use_tracer(null);
        parent::tearDown();
    }

    /**
     * Schedule the delivery of a grade of a student.
     *
     * @param \stdClass $student
     * @param \stdClass $course
     * @param int $item
     * @return int the task
     */
    private function defer(\stdClass $student, \stdClass $course, int $item = 7): int {
        return $this->scheduler->defer((int) $course->id, event_type::GRADE_CHANGED, ['userid' => (int) $student->id, 'itemid' => $item, 'occurred_at' => 1790000000]);
    }

    /**
     * Run a task and give back what it printed.
     *
     * @param int $id
     * @return string
     */
    private function run_task(int $id): string {
        ob_start();
        try {
            manager::get_adhoc_tasks(redeliver_event::class)[$id]->execute();
        } finally {
            $output = ob_get_clean();
        }
        return (string) $output;
    }

    /**
     * The erasure removes the deliveries of the student in every course, also in a course where it holds nothing else of the student,
     * and nobody else's.
     */
    public function test_the_erasure_removes_the_deliveries_of_the_student_and_only_those(): void {
        [$ana, $bruno] = $this->students;
        $this->defer($ana, $this->courses[0]);
        $this->defer($ana, $this->courses[1]);
        $brunotask = $this->defer($bruno, $this->courses[0]);

        (new student_data_eraser())->erase((int) $ana->id);

        $this->assertSame([], $this->scheduler->tasks_of((int) $ana->id));
        $this->assertSame([$brunotask], $this->scheduler->tasks_of((int) $bruno->id));
    }

    /**
     * Asked for one course only, the erasure leaves the deliveries of the other courses.
     */
    public function test_an_erasure_of_one_course_leaves_the_other_courses(): void {
        [$ana] = $this->students;
        $this->defer($ana, $this->courses[0]);
        $other = $this->defer($ana, $this->courses[1]);

        (new student_data_eraser())->erase((int) $ana->id, [(int) $this->courses[0]->id]);

        $this->assertSame([$other], $this->scheduler->tasks_of((int) $ana->id));
    }

    /**
     * The erasure is all or nothing: if a step after the removal fails, the deliveries are still there.
     */
    public function test_a_failed_erasure_keeps_the_deliveries(): void {
        [$ana] = $this->students;
        $this->defer($ana, $this->courses[0]);
        $this->defer($ana, $this->courses[1]);

        try {
            (new student_data_eraser())->erase((int) $ana->id, null, function () {
                throw new \RuntimeException('a later step failed');
            });
            $this->fail('The failure must come out');
        } catch (\RuntimeException $e) {
            $this->assertSame('a later step failed', $e->getMessage());
        }

        $this->assertCount(2, $this->scheduler->tasks_of((int) $ana->id), 'The removal was undone with the rest');
    }

    /**
     * A task that Moodle had already handed to a run, when the erasure removes its record, creates no event afterwards.
     */
    public function test_a_task_already_handed_to_a_run_creates_nothing_after_the_erasure(): void {
        global $DB;
        [$ana] = $this->students;
        $id = $this->defer($ana, $this->courses[0]);
        $task = manager::get_adhoc_tasks(redeliver_event::class)[$id];

        (new student_data_eraser())->erase((int) $ana->id);
        ob_start();
        try {
            $task->execute();
        } finally {
            $output = (string) ob_get_clean();
        }

        $this->assertStringContainsString('skipped_revoked', $output);
        $this->assertSame(0, $DB->count_records('local_assistente_event'));
    }

    /**
     * A task that delivered before the erasure leaves an event, and the erasure neutralises it as it does any other event of the student.
     */
    public function test_an_event_delivered_before_the_erasure_is_neutralised(): void {
        global $DB;
        [$ana] = $this->students;
        $id = $this->defer($ana, $this->courses[0]);
        $this->assertStringContainsString('created', $this->run_task($id));
        $this->assertSame(1, $DB->count_records('local_assistente_event'));

        (new student_data_eraser())->erase((int) $ana->id);

        $payload = json_decode($DB->get_field('local_assistente_event', 'payload', []), true);
        $this->assertSame(0, $payload['userid']);
        $this->assertSame(erased_subject::IDENTITY, $payload['identity']);
        $this->assertSame([], $this->scheduler->tasks_of((int) $ana->id));
    }

    /**
     * The contexts the Privacy API finds for a student, as integers.
     *
     * @param int $userid
     * @return int[]
     */
    private function found(int $userid): array {
        return array_map('intval', provider::get_contexts_for_userid($userid)->get_contextids());
    }

    /**
     * Take the attempts of a task, as Moodle does after the last one: it stays in the table and is no longer run.
     *
     * @param int $taskid
     */
    private function exhaust(int $taskid): void {
        global $DB;
        $DB->set_field('task_adhoc', 'attemptsavailable', 0, ['id' => $taskid]);
        $DB->set_field('task_adhoc', 'faildelay', 7680, ['id' => $taskid]);
    }

    /**
     * The identifiers of the contexts of the courses.
     *
     * @param \stdClass[] $courses
     * @return int[]
     */
    private function contexts(array $courses): array {
        return array_map(fn($course) => (int) \context_course::instance($course->id)->id, $courses);
    }

    /**
     * Risk 1: a student who has only a delivery waiting, and nothing else in the plugin, is found by the Privacy API (the contexts and the
     * users) and erased by it: the task goes, and what belongs to other students and other courses stays.
     */
    public function test_a_student_with_only_a_delivery_is_found_and_erased_by_the_privacy_api(): void {
        [$ana, $bruno] = $this->students;
        $this->defer($ana, $this->courses[0]);
        $brunoone = $this->defer($bruno, $this->courses[0]);
        $brunotwo = $this->defer($bruno, $this->courses[1]);

        $this->assertSame($this->contexts([$this->courses[0]]), $this->found((int) $ana->id));
        $userlist = new userlist(\context_course::instance($this->courses[0]->id), 'local_assistente');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([(int) $ana->id, (int) $bruno->id], $userlist->get_userids());

        provider::delete_data_for_user(new approved_contextlist($ana, 'local_assistente', $this->contexts([$this->courses[0]])));

        $this->assertSame([], $this->scheduler->tasks_of((int) $ana->id));
        $this->assertSame([], $this->found((int) $ana->id), 'Nothing left to find');
        $this->assertEqualsCanonicalizing([$brunoone, $brunotwo], $this->scheduler->tasks_of((int) $bruno->id), 'The other student keeps both');
    }

    /**
     * The same for a delivery that has no attempts left: it does not go away by itself, and it is found and removed all the same.
     */
    public function test_a_student_with_only_an_exhausted_delivery_is_found_and_erased(): void {
        [$ana, $bruno] = $this->students;
        $this->exhaust($this->defer($ana, $this->courses[1]));
        $bruno1 = $this->defer($bruno, $this->courses[1]);
        $this->exhaust($bruno1);

        $this->assertSame($this->contexts([$this->courses[1]]), $this->found((int) $ana->id));

        provider::delete_data_for_user(new approved_contextlist($ana, 'local_assistente', $this->contexts([$this->courses[1]])));

        $this->assertSame([], $this->scheduler->tasks_of((int) $ana->id));
        $this->assertSame([$bruno1], $this->scheduler->tasks_of((int) $bruno->id), 'An exhausted task of another student is not touched');
    }

    /**
     * The erasure of a course context reaches the students who only have a delivery waiting there, and no other course.
     */
    public function test_erasing_a_course_context_reaches_the_students_with_only_a_delivery(): void {
        [$ana, $bruno] = $this->students;
        $this->defer($ana, $this->courses[0]);
        $this->defer($bruno, $this->courses[0]);
        $anaother = $this->defer($ana, $this->courses[1]);

        provider::delete_data_for_all_users_in_context(\context_course::instance($this->courses[0]->id));

        $this->assertSame([$anaother], $this->scheduler->tasks_of((int) $ana->id));
        $this->assertSame([], $this->scheduler->tasks_of((int) $bruno->id));
    }

    /**
     * Exporting the data of a student who only has a delivery waiting does not fail and exports nothing: the task is not declared data of the
     * plugin (D8).
     */
    public function test_exporting_a_student_with_only_a_delivery_does_not_fail(): void {
        [$ana] = $this->students;
        $this->defer($ana, $this->courses[0]);
        $context = \context_course::instance($this->courses[0]->id);

        $this->export_context_data_for_user((int) $ana->id, $context, 'local_assistente');

        $this->assertFalse(writer::with_context($context)->has_any_data());
        $this->assertCount(1, $this->scheduler->tasks_of((int) $ana->id), 'Exporting removes nothing');
    }

    /**
     * Risk 2: a course that Moodle deletes loses the deliveries it had waiting, of students and of no student, and the other course keeps its.
     */
    public function test_a_deleted_course_with_a_pending_delivery_loses_it(): void {
        [$ana, $bruno] = $this->students;
        $this->defer($ana, $this->courses[0]);
        $this->defer($bruno, $this->courses[0]);
        $this->scheduler->defer((int) $this->courses[0]->id, event_type::FORUM_POST_CREATED, ['cmid' => 4, 'discussionid' => 5, 'postid' => 6]);
        $anaother = $this->defer($ana, $this->courses[1]);
        $forumother = $this->scheduler->defer((int) $this->courses[1]->id, event_type::FORUM_POST_CREATED, ['cmid' => 4, 'discussionid' => 5, 'postid' => 6]);

        delete_course($this->courses[0], false);

        $this->assertEqualsCanonicalizing([$anaother, $forumother], array_keys((new \local_assistente\infrastructure\persistence\redelivery_task_repository())->deliveries()));
        $this->assertSame([], $this->scheduler->tasks_of((int) $bruno->id));
    }

    /**
     * The same when the tasks of the course have no attempts left: they would otherwise stay in the table for good.
     */
    public function test_a_deleted_course_with_an_exhausted_delivery_loses_it(): void {
        [$ana, $bruno] = $this->students;
        $this->exhaust($this->defer($ana, $this->courses[0]));
        $this->exhaust($this->scheduler->defer((int) $this->courses[0]->id, event_type::FORUM_POST_CREATED, ['cmid' => 4, 'discussionid' => 5, 'postid' => 6]));
        $brunoother = $this->defer($bruno, $this->courses[1]);
        $this->exhaust($brunoother);

        delete_course($this->courses[0], false);

        $this->assertSame([$brunoother], array_keys((new \local_assistente\infrastructure\persistence\redelivery_task_repository())->deliveries()));
    }

    /**
     * All or nothing, first point: if the erasure of the course fails before the tasks are removed (they go last), they are all still there.
     */
    public function test_a_failed_course_erasure_keeps_the_deliveries(): void {
        global $DB;
        [$ana] = $this->students;
        $pending = $this->defer($ana, $this->courses[0]);
        $exhausted = $this->scheduler->defer((int) $this->courses[0]->id, event_type::FORUM_POST_CREATED, ['cmid' => 4, 'discussionid' => 5, 'postid' => 6]);
        $this->exhaust($exhausted);
        (new event_repository())->create_event((int) $this->courses[0]->id, event_type::GRADE_CHANGED, ['userid' => (int) $ana->id, 'itemid' => 9, 'occurred_at' => 1790000001], time());
        $table = $DB->get_prefix() . 'local_assistente_event';
        try {
            $DB->execute("CREATE TRIGGER t_redelivery_refuse BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused'");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }
        $failed = false;
        try {
            (new course_data_eraser())->erase((int) $this->courses[0]->id);
        } catch (\dml_exception) {
            $failed = true;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t_redelivery_refuse');
        }

        $this->assertTrue($failed, 'The trigger must have refused the deletion of the events');
        $this->assertEqualsCanonicalizing([$pending, $exhausted], array_keys((new \local_assistente\infrastructure\persistence\redelivery_task_repository())->deliveries()));

        (new course_data_eraser())->erase((int) $this->courses[0]->id);
        $this->assertSame([], (new \local_assistente\infrastructure\persistence\redelivery_task_repository())->deliveries());
    }

    /**
     * All or nothing, second point: if the removal of the tasks itself fails (they go last, after the rows of the plugin), everything the erasure
     * of the course had already deleted comes back: the events and the configuration are as they were, and the tasks too. After the cause is
     * gone the erasure works.
     */
    public function test_a_failure_in_the_removal_of_the_tasks_undoes_the_erasure_of_the_course(): void {
        global $DB;
        [$ana] = $this->students;
        $courseid = (int) $this->courses[0]->id;
        $pending = $this->defer($ana, $this->courses[0]);
        $exhausted = $this->scheduler->defer($courseid, event_type::FORUM_POST_CREATED, ['cmid' => 4, 'discussionid' => 5, 'postid' => 6]);
        $this->exhaust($exhausted);
        (new event_repository())->create_event($courseid, event_type::GRADE_CHANGED, ['userid' => (int) $ana->id, 'itemid' => 9, 'occurred_at' => 1790000001], time());
        $before = [
            $DB->count_records('local_assistente_event', ['course_id' => $courseid]),
            $DB->record_exists('local_assistente_config', ['course_id' => $courseid]),
        ];
        $this->assertSame([1, true], $before);
        $table = $DB->get_prefix() . 'task_adhoc';
        try {
            $DB->execute("CREATE TRIGGER t_redelivery_refuse_tasks BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused'");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage() . ' The atomicity of this point was not verified.');
        }
        $failed = false;
        try {
            (new course_data_eraser())->erase($courseid);
        } catch (\dml_exception) {
            $failed = true;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t_redelivery_refuse_tasks');
        }

        $this->assertTrue($failed, 'The trigger must have refused the removal of the tasks');
        $this->assertSame($before, [
            $DB->count_records('local_assistente_event', ['course_id' => $courseid]),
            $DB->record_exists('local_assistente_config', ['course_id' => $courseid]),
        ], 'What had been deleted before the tasks came back');
        $this->assertEqualsCanonicalizing([$pending, $exhausted], array_keys((new \local_assistente\infrastructure\persistence\redelivery_task_repository())->deliveries()));

        (new course_data_eraser())->erase($courseid);
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['course_id' => $courseid]));
        $this->assertSame([], (new \local_assistente\infrastructure\persistence\redelivery_task_repository())->deliveries());
    }

    /**
     * S1: the erasure of the data of the plugin does not mean that no event of that student can ever appear again. With the account active, a
     * delivery that comes after the erasure makes its event, the one of the retry task and the one of an observer alike (what the code base
     * always did). Only a deleted account (the privacy request deletes it right after the plugins) revokes it.
     */
    public function test_after_the_erasure_a_delivery_for_an_active_student_still_makes_an_event(): void {
        global $DB;
        [$ana] = $this->students;
        (new student_data_eraser())->erase((int) $ana->id);
        $payload = ['userid' => (int) $ana->id, 'itemid' => 7, 'occurred_at' => 1790000000];

        $direct = (new \local_assistente\application\event_service())->enqueue((int) $this->courses[0]->id, event_type::GRADE_CHANGED, $payload);
        $this->assertSame(\local_assistente\application\enqueue_status::CREATED, $direct->status, 'The delivery of an observer after the erasure');

        $id = $this->defer($ana, $this->courses[1]);
        $this->assertStringContainsString('created', $this->run_task($id), 'The retry task after the erasure, for an active account');
        $this->assertSame(2, $DB->count_records('local_assistente_event'));
    }

    /**
     * The erasure of a student takes no lock of the course (it takes the lock of the student, as always), and the erasure of a course takes
     * only the lock of the course. The order is recorded on the real paths.
     */
    public function test_the_erasure_of_a_student_takes_no_lock_of_the_course_and_the_erasure_of_the_course_takes_only_that(): void {
        [$ana] = $this->students;
        $this->defer($ana, $this->courses[0]);
        $factory = $this->record_locks();
        try {
            (new student_data_eraser())->erase((int) $ana->id);
            $student = $factory->sequence();
            $factory->log = [];
            (new course_data_eraser())->erase((int) $this->courses[0]->id);
            $course = $factory->sequence();
        } finally {
            $this->stop_recording_locks();
        }

        $this->assertContains(\local_assistente\infrastructure\persistence\unit_of_work::privacy_user_resource((int) $ana->id), $student);
        foreach ($student as $resource) {
            $this->assertStringStartsNotWith('course_erasure_', $resource, 'The erasure of a student never takes the lock of a course');
        }
        $this->assertSame([\local_assistente\infrastructure\persistence\unit_of_work::course_erasure_resource((int) $this->courses[0]->id)], $course);
    }

    /**
     * The order of the removals: the tasks go after the rows of the plugin, as in the erasure of a student, so that two erasures lock rows in the
     * same order. Seen through the database: the events cannot be deleted (a trigger refuses), and another trigger notes, in a variable of the
     * session, that a task was deleted. The note is not rolled back with the transaction. If the erasure reached the tasks before the events
     * the note would be set; it must not be. (The atomicity tests cannot tell the two orders apart, since both are undone.)
     */
    public function test_the_tasks_are_removed_after_the_rows_of_the_plugin(): void {
        global $DB;
        [$ana] = $this->students;
        $courseid = (int) $this->courses[0]->id;
        $this->defer($ana, $this->courses[0]);
        (new event_repository())->create_event($courseid, event_type::GRADE_CHANGED, ['userid' => (int) $ana->id, 'itemid' => 9, 'occurred_at' => 1790000001], time());
        $events = $DB->get_prefix() . 'local_assistente_event';
        $tasks = $DB->get_prefix() . 'task_adhoc';
        try {
            $DB->execute("CREATE TRIGGER t_order_refuse_events BEFORE DELETE ON {$events} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused'");
            $DB->execute("CREATE TRIGGER t_order_note_tasks AFTER DELETE ON {$tasks} FOR EACH ROW SET @t_order_tasks_deleted = 1");
        } catch (\dml_exception $e) {
            $DB->execute('DROP TRIGGER IF EXISTS t_order_refuse_events');
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage() . ' The order of the removals was not verified.');
        }
        $DB->execute('SET @t_order_tasks_deleted = 0');
        $failed = false;
        try {
            (new course_data_eraser())->erase($courseid);
        } catch (\dml_exception) {
            $failed = true;
        }
        $noted = (int) $DB->get_field_sql('SELECT @t_order_tasks_deleted');
        $DB->execute('DROP TRIGGER IF EXISTS t_order_refuse_events');
        $DB->execute('DROP TRIGGER IF EXISTS t_order_note_tasks');

        $this->assertTrue($failed, 'The trigger must have refused the deletion of the events');
        $this->assertSame(0, $noted, 'The erasure did not reach the tasks before the rows of the plugin');
    }
}
