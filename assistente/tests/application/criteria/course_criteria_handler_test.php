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


namespace local_assistente\application\criteria;

use local_assistente\application\frequency\frequency_handler;
use local_assistente\application\grade\performance_handler;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\forum_reader;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\criteria_result_repository;
use local_assistente\infrastructure\persistence\event_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the request for the criteria of a whole course and of its handler (T141; quickstart 015 Q14; DEC-55 D2 to D4). Real course,
 * readers, repositories and queue; no LLM exists in this flow. The moment is fixed by the clock of the handler.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(criteria_handler::class)]
#[CoversClass(criteria_request_service::class)]
final class course_criteria_handler_test extends \advanced_testcase {
    /** @var int counter that keeps the occurrences distinct */
    private static int $occurred = 8000;

    /** @var int fixed moment of the evaluation */
    private int $now;

    /** @var \stdClass course that ended 500 seconds before the moment */
    private \stdClass $course;

    /** @var \stdClass[] two students */
    private array $students;

    /** @var \stdClass assignment past due with a grade of 100 */
    private \stdClass $assign;

    /** @var \stdClass forum without a date */
    private \stdClass $forum;

    /** @var event_repository */
    private event_repository $events;

    /** @var criteria_result_repository */
    private criteria_result_repository $repository;

    /** @var criteria_handler */
    private criteria_handler $handler;

    /**
     * A course with the assistant and the criteria 60, forum required, 40; two students, an assignment and a forum, both past their date.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->now = time();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2, 'startdate' => $this->now - 500 - DAYSECS, 'enddate' => $this->now - 500]);
        $configs = new config_repository();
        $config = $configs->create($this->course->id, true);
        $configs->insert_criteria((int) $config->id, 60, 1, 40);
        $this->students = [
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
            $this->getDataGenerator()->create_and_enrol($this->course, 'student'),
        ];
        $this->assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id, 'duedate' => $this->now - 1000, 'grade' => 100,
        ], ['section' => 1]);
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id], ['section' => 1]);
        $this->events = new event_repository();
        $this->repository = new criteria_result_repository();
        $this->handler = $this->handler_at(fn() => $this->now);
    }

    /**
     * A criteria handler with its clock.
     *
     * @param callable $clock
     * @param forum_reader|null $forums
     * @return criteria_handler
     */
    private function handler_at(callable $clock, ?forum_reader $forums = null): criteria_handler {
        return new criteria_handler(null, null, null, null, null, null, $forums, null, null, null, $clock);
    }

    /**
     * The student posts in the forum and is graded in the assignment, and both analyses are made by the real handlers.
     *
     * @param \stdClass $student
     * @param float $grade
     */
    private function analyse(\stdClass $student, float $grade): void {
        global $DB;
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $student->id,
        ]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assign->id, 0, ['userid' => $student->id, 'rawgrade' => $grade]);
        $itemid = (int) $DB->get_field('grade_items', 'id', [
            'courseid' => $this->course->id, 'itemmodule' => 'assign', 'iteminstance' => $this->assign->id, 'itemnumber' => 0,
        ]);
        $this->deliver(
            event_type::GRADE_CHANGED,
            ['userid' => (int) $student->id, 'itemid' => $itemid, 'occurred_at' => ++self::$occurred],
            new performance_handler()
        );
        $this->deliver(event_type::PARTICIPATION_CHANGED, [
            'cmid' => (int) $this->forum->cmid, 'userid' => (int) $student->id, 'source' => 'post', 'occurred_at' => ++self::$occurred,
        ], new frequency_handler(null, null, null, null, null, null, null, null, fn() => $this->now));
    }

    /**
     * Create an event, begin its attempt and hand it to a handler.
     *
     * @param event_type $type
     * @param array $payload
     * @param \local_assistente\application\queue\event_handler|null $handler the criteria handler of the test by default
     * @param int|null $courseid
     * @return array handler_result, processing and event
     */
    private function deliver(event_type $type, array $payload, $handler = null, ?int $courseid = null): array {
        $event = $this->events->create_event($courseid ?? (int) $this->course->id, $type, $payload, time());
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        return [($handler ?? $this->handler)->handle($event, $processing), $processing, $event];
    }

    /**
     * Apply the criteria of the whole course.
     *
     * @param int|null $courseid
     * @param criteria_handler|null $handler
     * @return array handler_result, processing and event
     */
    private function check_course(?int $courseid = null, ?criteria_handler $handler = null): array {
        $courseid ??= (int) $this->course->id;
        return $this->deliver(event_type::COURSE_CRITERIA_CHECK_REQUESTED, ['course_id' => $courseid], $handler, $courseid);
    }

    /**
     * Counts of what a course check writes: analyses, results and contexts.
     *
     * @return int[]
     */
    private function written(): array {
        global $DB;
        return [
            $DB->count_records('local_assistente_analysis', ['analysis_type' => 'criteria_check']),
            $DB->count_records('local_assistente_criteria_result'),
            $DB->count_records_select('local_assistente_processing_context', "context_json LIKE '%\"criteria_check\"%'"),
        ];
    }

    /**
     * The statuses saved for a student in a processing.
     *
     * @param \stdClass $processing
     * @param \stdClass $student
     * @return int[] activity, forum, frequency
     */
    private function statuses(\stdClass $processing, \stdClass $student): array {
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $row = $this->repository->find_result((int) $analysis->id, (int) $student->id);
        return [(int) $row->activity_status, (int) $row->forum_status, (int) $row->frequency_status];
    }

    /**
     * Q14: every eligible student is evaluated in one processing: one analysis, one row for each student, one context with the limits once
     * and the values of each student.
     */
    public function test_every_eligible_student_is_evaluated_in_one_processing(): void {
        $this->analyse($this->students[0], 80.0);

        [$result, $processing] = $this->check_course();

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertSame([1, 2, 1], $this->written());
        $this->assertSame([1, 1, 1], $this->statuses($processing, $this->students[0]));
        $this->assertSame([2, 0, 2], $this->statuses($processing, $this->students[1]), 'No analysis: no data; a due forum without a post: below');
        $context = json_decode($this->events->find_context((int) $processing->id)->context_json, true);
        $this->assertSame(['criteria_check', 'course', (int) $this->course->id, $this->now], [
            $context['kind'], $context['scope'], $context['course_id'], $context['evaluated_at'],
        ]);
        $this->assertEquals(
            ['activity_criterion' => 60.0, 'forum_criterion' => 1, 'frequency_criterion' => 40.0],
            $context['limits']
        );
        $this->assertCount(2, $context['students']);
        $byid = array_column($context['students'], null, 'student_id');
        $this->assertEquals(80.0, $byid[(int) $this->students[0]->id]['activity_value']);
        $this->assertNotNull($byid[(int) $this->students[0]->id]['performance_analysis_id']);
        $this->assertNull($byid[(int) $this->students[1]->id]['activity_value']);
        $this->assertSame([(int) $this->forum->cmid], $byid[(int) $this->students[1]->id]['forums_missing']);
    }

    /**
     * Users who are not students of the course are not in the set: a teacher, a suspended enrolment and a user who is not enrolled.
     */
    public function test_ineligible_users_are_not_evaluated(): void {
        $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $suspended = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($suspended->id, $this->course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $stranger = $this->getDataGenerator()->create_user();

        [, $processing] = $this->check_course();

        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $ids = array_map(fn($r) => (int) $r->student_id, array_values($this->repository->find_results((int) $analysis->id)));
        $this->assertSame(array_map(fn($s) => (int) $s->id, $this->students), $ids);
        $this->assertNotContains((int) $suspended->id, $ids);
        $this->assertNotContains((int) $stranger->id, $ids);
    }

    /**
     * A course without criteria, a course that is not enabled and a course without eligible students write nothing: no analysis, no
     * context, no result.
     */
    public function test_nothing_is_written_without_criteria_without_the_assistant_or_without_students(): void {
        $configs = new config_repository();
        $bare = $this->getDataGenerator()->create_course();
        $configs->create($bare->id, true);
        $this->getDataGenerator()->create_and_enrol($bare, 'student');
        $off = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_and_enrol($off, 'student');
        $disabled = $configs->create($off->id, false);
        $configs->insert_criteria((int) $disabled->id, 60, 1, 40);
        $empty = $this->getDataGenerator()->create_course();
        $emptyconfig = $configs->create($empty->id, true);
        $configs->insert_criteria((int) $emptyconfig->id, 60, 1, 40);
        $this->getDataGenerator()->create_and_enrol($empty, 'editingteacher');

        foreach ([$bare, $off, $empty] as $course) {
            [$result, $processing] = $this->check_course((int) $course->id);
            $this->assertTrue($result->success, (string) $result->message);
            $this->assertNull($this->events->find_context((int) $processing->id));
        }

        $this->assertSame([0, 0, 0], $this->written());
    }

    /**
     * The request for the course is coalesced while it is pending, and a new one is made after it was processed.
     */
    public function test_the_course_request_is_coalesced_while_pending_and_new_after_it_ran(): void {
        global $DB;
        $service = new criteria_request_service();
        $first = $service->request_course((int) $this->course->id);
        $second = $service->request_course((int) $this->course->id);

        $this->assertSame((int) $first->event->id, (int) $second->event->id);
        $this->assertSame(1, $DB->count_records('local_assistente_event', ['event_type' => 'course_criteria_check_requested']));

        (new queue_processor(null, handler_registry::default()))->run_cycle();
        $third = $service->request_course((int) $this->course->id);

        $this->assertNotSame((int) $first->event->id, (int) $third->event->id);
        $this->assertSame(2, $DB->count_records('local_assistente_event', ['event_type' => 'course_criteria_check_requested']));
        $this->assertSame(1, $DB->count_records('local_assistente_criteria_result', ['student_id' => $this->students[0]->id]));
        $this->expectException(\InvalidArgumentException::class);
        $service->request_course(0);
    }

    /**
     * A course without the assistant queues no request, and an event for another course than the one of its payload fails.
     */
    public function test_a_disabled_course_queues_nothing_and_a_mismatched_payload_fails(): void {
        global $DB;
        $off = $this->getDataGenerator()->create_course();

        $result = (new criteria_request_service())->request_course((int) $off->id);
        [$mismatch] = $this->deliver(event_type::COURSE_CRITERIA_CHECK_REQUESTED, ['course_id' => (int) $off->id]);

        $this->assertSame(\local_assistente\application\enqueue_status::SKIPPED_NOT_ENABLED, $result->status);
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['course_id' => $off->id]));
        $this->assertFalse($mismatch->success);
        $this->assertSame(attempt_failure::TECHNICAL, $mismatch->failure);
        $this->assertSame([0, 0, 0], $this->written());
    }

    /**
     * Idempotent: the same processing handled twice has one analysis, one set of rows and one context.
     */
    public function test_the_same_processing_is_idempotent(): void {
        [, $processing, $event] = $this->check_course();

        $again = $this->handler->handle($event, $processing);

        $this->assertTrue($again->success);
        $this->assertSame([1, 2, 1], $this->written());
    }

    /**
     * A reference date asks once for the course; a participation event still asks for its student; the retry of an analysis that already
     * exists asks again for the course and the request is reused.
     */
    public function test_the_chain_asks_once_for_the_course_and_still_asks_for_each_student(): void {
        global $DB;
        $frequency = new frequency_handler(null, null, null, null, null, null, null, null, fn() => $this->now);
        [, $processing, $event] = $this->deliver(
            event_type::REFERENCE_DATE_REACHED,
            ['cmid' => (int) $this->assign->cmid, 'reference_date' => $this->now - 1000],
            $frequency
        );
        $count = fn(string $type) => $DB->count_records('local_assistente_event', ['event_type' => $type, 'status' => 'pending']);
        $this->assertSame([1, 0], [$count('course_criteria_check_requested'), $count('criteria_check_requested')]);

        $frequency->handle($event, $processing);
        $this->assertSame(1, $count('course_criteria_check_requested'), 'The retry reuses the pending request');

        $this->deliver(event_type::PARTICIPATION_CHANGED, [
            'cmid' => (int) $this->forum->cmid, 'userid' => (int) $this->students[0]->id, 'source' => 'post', 'occurred_at' => ++self::$occurred,
        ], $frequency);
        $this->assertSame([1, 1], [$count('course_criteria_check_requested'), $count('criteria_check_requested')]);
    }

    /**
     * A failure of a reader for one student fails the attempt and writes nothing for any student.
     */
    public function test_a_read_failure_for_one_student_writes_nothing(): void {
        $second = (int) $this->students[1]->id;
        $failing = new class ($second) extends forum_reader {
            /**
             * Constructor.
             *
             * @param int $failfor the user whose reading fails
             */
            public function __construct(private int $failfor) {
            }

            #[\Override]
            public function has_posted(int $cmid, int $userid): bool {
                if ($userid === $this->failfor) {
                    throw new \RuntimeException('forum unavailable');
                }
                return true;
            }
        };

        [$result, $processing] = $this->check_course(null, $this->handler_at(fn() => $this->now, $failing));

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $this->assertStringContainsString('forum unavailable', (string) $result->message);
        $this->assertSame([0, 0, 0], $this->written());
        $this->assertNull($this->events->find_context((int) $processing->id));
    }

    /**
     * A failure of the evaluation (a course total outside 0 to 100 in the context of an analysis) writes nothing, not even for the
     * students evaluated before it.
     */
    public function test_an_evaluation_failure_writes_nothing(): void {
        global $DB;
        $this->analyse($this->students[0], 80.0);
        $this->analyse($this->students[1], 70.0);
        $analysis = $this->repository;
        $performance = new \local_assistente\infrastructure\persistence\performance_repository();
        $current = $performance->find_current_for_student((int) $this->course->id, (int) $this->students[1]->id);
        $context = $this->events->find_context((int) $current->processing_id);
        $DB->set_field('local_assistente_processing_context', 'context_json', json_encode(['course_total_percent' => 150.0]), ['id' => $context->id]);

        [$result, $processing] = $this->check_course();

        $this->assertFalse($result->success);
        $this->assertSame([0, 0, 0], $this->written());
        $this->assertNull($this->events->find_context((int) $processing->id));
        $this->assertNull($analysis->find_by_processing((int) $processing->id));
    }

    /**
     * A failure of the persistence in the middle (a trigger refuses the row of the second student) rolls back the analysis, the rows and
     * the context together, and the retry writes everything with the data of the retry, not an old context.
     */
    public function test_a_persistence_failure_rolls_back_everything_and_the_retry_has_no_old_context(): void {
        global $DB;
        $table = $DB->get_prefix() . 'local_assistente_criteria_result';
        [$result, $processing, $event] = [null, null, null];
        try {
            // One statement only (Moodle refuses several): the row of the second student gets NULL in a NOT NULL column.
            $DB->execute("CREATE TRIGGER t141_refuse BEFORE INSERT ON {$table} FOR EACH ROW
                SET NEW.forum_status = IF(NEW.student_id = {$this->students[1]->id}, NULL, NEW.forum_status)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }
        try {
            [$result, $processing, $event] = $this->check_course();
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t141_refuse');
        }

        $this->assertFalse($result->success);
        $this->assertSame([0, 0, 0], $this->written());
        $this->assertNull($this->events->find_context((int) $processing->id), 'The context was rolled back with the analysis');

        $later = $this->now + 50;
        $retry = $this->handler_at(fn() => $later)->handle($event, $processing);

        $this->assertTrue($retry->success, (string) $retry->message);
        $this->assertSame([1, 2, 1], $this->written());
        $context = json_decode($this->events->find_context((int) $processing->id)->context_json, true);
        $this->assertSame($later, $context['evaluated_at'], 'The context is the one of the retry');
    }

    /**
     * A context without an analysis, a state the atomic write does not produce, is not attached to a new attempt: the attempt fails and
     * the context stays as it was.
     */
    public function test_a_context_without_an_analysis_is_never_reused(): void {
        [, $processing, $event] = $this->deliver(event_type::COURSE_CRITERIA_CHECK_REQUESTED, ['course_id' => (int) $this->course->id], new class implements \local_assistente\application\queue\event_handler {
            #[\Override]
            public function handle(\stdClass $event, \stdClass $processing): \local_assistente\application\queue\handler_result {
                return \local_assistente\application\queue\handler_result::success('nothing');
            }
        });
        $this->events->save_context((int) $processing->id, '{"kind":"old"}', $this->now - 100);

        $result = $this->handler->handle($event, $processing);

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $this->assertStringContainsString('old context', (string) $result->message, 'Refused on purpose, not by chance of the index');
        $this->assertSame('{"kind":"old"}', $this->events->find_context((int) $processing->id)->context_json);
        $this->assertSame([0, 0, 0], $this->written());
    }

    /**
     * Through the real queue: the request queued by a reference date is processed by the default registry, and the course gets its
     * results.
     */
    public function test_the_real_queue_applies_the_criteria_to_the_course(): void {
        global $DB;
        $this->deliver(
            event_type::REFERENCE_DATE_REACHED,
            ['cmid' => (int) $this->assign->cmid, 'reference_date' => $this->now - 1000],
            new frequency_handler(null, null, null, null, null, null, null, null, fn() => $this->now)
        );

        (new queue_processor(null, handler_registry::default()))->run_cycle();

        $this->assertSame(0, $DB->count_records('local_assistente_event', ['event_type' => 'course_criteria_check_requested', 'status' => 'pending']));
        foreach ($this->students as $student) {
            $this->assertNotNull($this->repository->find_current_for_student((int) $this->course->id, (int) $student->id));
        }
    }
}
