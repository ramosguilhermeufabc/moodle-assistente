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
use local_assistente\domain\criteria_status;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\forum_reader;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\criteria_result_repository;
use local_assistente\infrastructure\persistence\event_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the criteria handler and of its chaining after the performance and frequency analyses (T093; quickstart 015 Q13, quickstart 020
 * Q8 at processing level; DEC-40, DEC-45 D2, DEC-55). Real course, real readers, repositories and queue; no LLM exists in this flow. The
 * moment is fixed by the clock of the handler.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(criteria_handler::class)]
#[CoversClass(criteria_request_service::class)]
final class criteria_handler_test extends \advanced_testcase {
    /** @var int fixed moment of the evaluation */
    private int $now;

    /** @var \stdClass course that ended 500 seconds before the moment */
    private \stdClass $course;

    /** @var \stdClass[] two students */
    private array $students;

    /** @var \stdClass assignment past due with a grade of 100, section 1 */
    private \stdClass $assign;

    /** @var \stdClass forum without a date, section 1 */
    private \stdClass $forum;

    /** @var \stdClass the configuration of the course */
    private \stdClass $config;

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
        $this->config = $configs->create($this->course->id, true);
        $configs->insert_criteria((int) $this->config->id, 60, 1, 40);
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
        $this->handler = new criteria_handler(null, null, null, null, null, null, null, null, null, null, fn() => $this->now);
    }

    /**
     * The student posts in the forum and is graded in the assignment, and both analyses are made by the real handlers.
     *
     * @param \stdClass $student
     * @param float $grade
     * @return array the event and the processing of the performance analysis
     */
    private function analyse(\stdClass $student, float $grade): array {
        global $DB;
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $student->id,
        ]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assign->id, 0, ['userid' => $student->id, 'rawgrade' => $grade]);
        $itemid = (int) $DB->get_field('grade_items', 'id', [
            'courseid' => $this->course->id, 'itemmodule' => 'assign', 'iteminstance' => $this->assign->id, 'itemnumber' => 0,
        ]);
        [, $processing, $event] = $this->deliver(
            event_type::GRADE_CHANGED,
            ['userid' => (int) $student->id, 'itemid' => $itemid, 'occurred_at' => ++self::$occurred],
            new performance_handler()
        );
        $this->deliver(event_type::PARTICIPATION_CHANGED, [
            'cmid' => (int) $this->forum->cmid, 'userid' => (int) $student->id, 'source' => 'post', 'occurred_at' => ++self::$occurred,
        ], new frequency_handler(null, null, null, null, null, null, null, null, fn() => $this->now));
        return [$event, $processing];
    }

    /** @var int counter that keeps the occurrences distinct */
    private static int $occurred = 6000;

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
     * Apply the criteria of a student.
     *
     * @param \stdClass $student
     * @param int|null $courseid
     * @return array handler_result, processing and event
     */
    private function check(\stdClass $student, ?int $courseid = null): array {
        return $this->deliver(event_type::CRITERIA_CHECK_REQUESTED, ['userid' => (int) $student->id], null, $courseid);
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
     * The context saved for a processing, as an array.
     *
     * @param \stdClass $processing
     * @return array
     */
    private function context(\stdClass $processing): array {
        return json_decode($this->events->find_context((int) $processing->id)->context_json, true);
    }

    /**
     * How many criteria requests exist in the course.
     *
     * @param string|null $status
     * @return int
     */
    private function requests(?string $status = null): int {
        global $DB;
        return $DB->count_records('local_assistente_event', array_filter([
            'course_id' => $this->course->id, 'event_type' => 'criteria_check_requested', 'status' => $status,
        ]));
    }

    /**
     * The current analyses of a student are read and applied: 80 meets 60, a post in the due forum meets, 2 of 2 meets 40; the context
     * keeps the limits, the values, the forums and the analyses.
     */
    public function test_applies_the_current_analyses_and_keeps_the_context(): void {
        $this->analyse($this->students[0], 80.0);

        [$result, $processing] = $this->check($this->students[0]);

        $this->assertTrue($result->success);
        $this->assertSame([1, 1, 1], $this->statuses($processing, $this->students[0]));
        $context = $this->context($processing);
        $this->assertSame('criteria_check', $context['kind']);
        $this->assertEquals(
            ['activity_criterion' => 60.0, 'forum_criterion' => 1, 'frequency_criterion' => 40.0],
            $context['limits']
        );
        $this->assertEquals(80.0, $context['activity_value']);
        $this->assertEquals(100.0, $context['frequency_value']);
        $this->assertSame([(int) $this->forum->cmid], $context['forums_due']);
        $this->assertSame([], $context['forums_missing']);
        $this->assertNotNull($context['performance_analysis_id']);
        $this->assertNotNull($context['frequency_analysis_id']);
    }

    /**
     * Q13 in the data: a student with no analyses has no data where there is nothing to read, below where a due forum has no post, and
     * nothing becomes a zero.
     */
    public function test_a_student_without_analyses_has_no_data_and_a_missing_post_is_below(): void {
        [$result, $processing] = $this->check($this->students[1]);

        $this->assertTrue($result->success);
        $this->assertSame(
            [criteria_status::NO_DATA->value, criteria_status::BELOW->value, criteria_status::NO_DATA->value],
            $this->statuses($processing, $this->students[1])
        );
        $context = $this->context($processing);
        $this->assertNull($context['activity_value']);
        $this->assertNull($context['frequency_value']);
        $this->assertSame([(int) $this->forum->cmid], $context['forums_missing']);
        $this->assertNull($context['performance_analysis_id']);
    }

    /**
     * Q13: a course without criteria writes no analysis and no criteria_result; the same for a course where the assistant is not enabled.
     */
    public function test_a_course_without_criteria_or_not_enabled_writes_nothing(): void {
        global $DB;
        $bare = $this->getDataGenerator()->create_course();
        (new config_repository())->create($bare->id, true);
        $barestudent = $this->getDataGenerator()->create_and_enrol($bare, 'student');
        $off = $this->getDataGenerator()->create_course();
        $offstudent = $this->getDataGenerator()->create_and_enrol($off, 'student');
        $configs = new config_repository();
        $disabled = $configs->create($off->id, false);
        $configs->insert_criteria((int) $disabled->id, 60, 1, 40);

        [$nocriteria] = $this->check($barestudent, (int) $bare->id);
        [$notenabled] = $this->check($offstudent, (int) $off->id);

        $this->assertTrue($nocriteria->success);
        $this->assertTrue($notenabled->success);
        $this->assertSame(0, $DB->count_records('local_assistente_criteria_result'));
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'criteria_check']));
    }

    /**
     * A user who is not a student of the course is done with nothing written; an event without a valid user fails.
     */
    public function test_a_teacher_is_not_evaluated_and_an_invalid_event_fails(): void {
        global $DB;
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        [$result] = $this->check($teacher);
        [$invalid] = $this->deliver(event_type::CRITERIA_CHECK_REQUESTED, ['userid' => -1]);

        $this->assertTrue($result->success);
        $this->assertSame(0, $DB->count_records('local_assistente_criteria_result'));
        $this->assertFalse($invalid->success);
        $this->assertSame(attempt_failure::TECHNICAL, $invalid->failure);
    }

    /**
     * A failure of a reader fails the attempt and writes nothing: no analysis, no row, no status read as zero.
     */
    public function test_a_failure_of_a_reader_writes_nothing(): void {
        global $DB;
        $failing = new class extends forum_reader {
            #[\Override]
            public function has_posted(int $cmid, int $userid): bool {
                throw new \RuntimeException('forum unavailable');
            }
        };
        $handler = new criteria_handler(null, null, null, null, null, null, $failing, null, null, null, fn() => $this->now);

        [$result, $processing] = $this->deliver(event_type::CRITERIA_CHECK_REQUESTED, ['userid' => (int) $this->students[0]->id], $handler);

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $this->assertStringContainsString('forum unavailable', (string) $result->message);
        $this->assertSame(0, $DB->count_records('local_assistente_criteria_result'));
        $this->assertNull($this->events->find_context((int) $processing->id));
    }

    /**
     * Idempotent: the same processing handled twice has one analysis and one row.
     */
    public function test_the_same_processing_is_idempotent(): void {
        global $DB;
        $this->analyse($this->students[0], 80.0);
        [, $processing, $event] = $this->check($this->students[0]);

        $again = $this->handler->handle($event, $processing);

        $this->assertTrue($again->success);
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'criteria_check']));
        $this->assertSame(1, $DB->count_records('local_assistente_criteria_result'));
    }

    /**
     * Quickstart 020 Q8: after the administrator corrects 60 to 50 the new check uses 50, the previous one keeps 60 in its context and its
     * statuses, and nothing is recalculated by itself.
     */
    public function test_a_correction_reaches_only_the_new_checks(): void {
        $this->analyse($this->students[0], 55.0);
        [, $first] = $this->check($this->students[0]);
        (new config_repository())->update_criteria((int) $this->config->id, 50, 1, 40);

        [, $second] = $this->check($this->students[0]);

        $this->assertEquals(60.0, $this->context($first)['limits']['activity_criterion']);
        $this->assertSame(0, $this->statuses($first, $this->students[0])[0], '55 is below 60 in the first check');
        $this->assertEquals(50.0, $this->context($second)['limits']['activity_criterion']);
        $this->assertSame(1, $this->statuses($second, $this->students[0])[0], '55 meets 50 in the new check');
        $this->assertSame(0, $this->statuses($first, $this->students[0])[0], 'The first check was not recalculated');
    }

    /**
     * Each analysis of performance and of frequency asks for the criteria of its student, and a request that is still pending is reused.
     */
    public function test_the_analyses_queue_one_coalesced_request_for_the_student(): void {
        $this->analyse($this->students[0], 80.0);

        $this->assertSame(1, $this->requests('pending'), 'Performance and frequency asked for the same student');

        $this->analyse($this->students[0], 70.0);
        $this->assertSame(1, $this->requests('pending'), 'A new analysis reuses the pending request');
        $this->analyse($this->students[1], 60.0);
        $this->assertSame(2, $this->requests('pending'), 'Another student has her own request');
    }

    /**
     * The whole course is recalculated on a reference date: one request for the course, none for each student (DEC-55 D2, T141).
     */
    public function test_a_reference_date_asks_once_for_the_course(): void {
        $this->deliver(
            event_type::REFERENCE_DATE_REACHED,
            ['cmid' => (int) $this->assign->cmid, 'reference_date' => $this->now - 1000],
            new frequency_handler(null, null, null, null, null, null, null, null, fn() => $this->now)
        );

        $this->assertSame(0, $this->requests('pending'), 'No request for a single student');
        $this->assertSame(1, $this->course_requests('pending'));
    }

    /**
     * How many requests for the whole course exist.
     *
     * @param string $status
     * @return int
     */
    private function course_requests(string $status): int {
        global $DB;
        return $DB->count_records('local_assistente_event', [
            'course_id' => $this->course->id, 'event_type' => 'course_criteria_check_requested', 'status' => $status,
        ]);
    }

    /**
     * A retry of an analysis that already exists asks again, so a request lost by a failure is not lost for good.
     */
    public function test_handling_an_existing_analysis_asks_again(): void {
        global $DB;
        [$event, $processing] = $this->analyse($this->students[0], 80.0);
        $DB->delete_records('local_assistente_event', ['event_type' => 'criteria_check_requested']);

        (new performance_handler())->handle($event, $processing);

        $this->assertSame(1, $this->requests());
    }

    /**
     * A course without the assistant queues no request.
     */
    public function test_a_course_without_the_assistant_queues_no_request(): void {
        global $DB;
        $off = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($off, 'student');

        $result = (new criteria_request_service())->request((int) $off->id, (int) $student->id);

        $this->assertSame(\local_assistente\application\enqueue_status::SKIPPED_NOT_ENABLED, $result->status);
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['course_id' => $off->id]));
        $this->expectException(\InvalidArgumentException::class);
        (new criteria_request_service())->request(0, 5);
    }

    /**
     * From the analysis to the criteria through the real queue: the request queued by the analyses is processed by the default registry.
     */
    public function test_the_real_queue_applies_the_criteria_after_the_analyses(): void {
        global $DB;
        $this->analyse($this->students[0], 80.0);
        $this->assertSame(1, $this->requests('pending'));

        // The observers of the plugin are active here, so the queue also holds the events of the grade and of the post; the cycle handles
        // them all, and each analysis asks again for the criteria of the student.
        (new queue_processor(null, handler_registry::default()))->run_cycle();

        $this->assertGreaterThanOrEqual(1, $DB->count_records('local_assistente_criteria_result', ['student_id' => $this->students[0]->id]));
        $analysis = $this->repository->find_current_for_student((int) $this->course->id, (int) $this->students[0]->id);
        $this->assertSame(
            [1, 1, 1],
            array_values(array_map('intval', array_intersect_key(
                (array) $this->repository->find_result((int) $analysis->id, (int) $this->students[0]->id),
                array_flip(['activity_status', 'forum_status', 'frequency_status'])
            )))
        );
    }
}
