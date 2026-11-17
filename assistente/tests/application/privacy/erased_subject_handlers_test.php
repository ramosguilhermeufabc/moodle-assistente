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

use local_assistente\application\criteria\criteria_handler;
use local_assistente\application\frequency\frequency_handler;
use local_assistente\application\grade\performance_handler;
use local_assistente\application\quiz\results_handler;
use local_assistente\domain\event\erased_subject;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The handlers read `userid` 0 as the subject of an event whose data were erased (T104; DEC-57): they finish with success, say so, and
 * write nothing individual, even when the student is still enrolled and has activity. A `userid` that is missing or negative stays an error.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(performance_handler::class)]
#[CoversClass(frequency_handler::class)]
#[CoversClass(criteria_handler::class)]
#[CoversClass(erased_subject::class)]
final class erased_subject_handlers_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var event_repository */
    private event_repository $events;

    /**
     * A course with the assistant and criteria, a student who is still enrolled and an assignment with a grade.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course(['startdate' => time() - 500 - DAYSECS, 'enddate' => time() - 500]);
        $configs = new config_repository();
        $config = $configs->create($this->course->id, true);
        $configs->insert_criteria((int) $config->id, 60, 1, 40);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id, 'duedate' => time() - 1000, 'grade' => 100]);
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $assign->id, 0, ['userid' => $this->student->id, 'rawgrade' => 80]);
        $this->events = new event_repository();
    }

    /**
     * Hand an event to a handler.
     *
     * @param event_type $type
     * @param array $payload
     * @param \local_assistente\application\queue\event_handler $handler
     * @return \local_assistente\application\queue\handler_result
     */
    private function handle(event_type $type, array $payload, $handler) {
        $event = $this->events->create_event((int) $this->course->id, $type, $payload, time());
        $this->events->begin_attempt((int) $event->id, time());
        return $handler->handle($event, $this->events->find_processing((int) $event->id));
    }

    /**
     * Rows that a handler could write about the student.
     *
     * @return int[]
     */
    private function written(): array {
        global $DB;
        return [
            $DB->count_records('local_assistente_analysis'),
            $DB->count_records('local_assistente_performance'),
            $DB->count_records('local_assistente_frequency'),
            $DB->count_records('local_assistente_criteria_result'),
            $DB->count_records('local_assistente_processing_context'),
            $DB->count_records('local_assistente_event'),
        ];
    }

    /**
     * The three handlers finish an event of an erased subject with success and the response of the erasure, and write nothing: no analysis, no
     * individual result, no context, and no request of criteria or of synthesis.
     */
    public function test_the_handlers_finish_an_erased_subject_without_results(): void {
        $before = $this->written();
        $before[5] += 3; // The three events the test itself makes; a handler would add requests of criteria or of synthesis to them.
        $clock = fn() => time();
        $cases = [
            [event_type::GRADE_CHANGED, ['userid' => 0, 'itemid' => 9, 'occurred_at' => 5], new performance_handler()],
            [event_type::PARTICIPATION_CHANGED, ['cmid' => 1, 'userid' => 0, 'source' => 'grade', 'occurred_at' => 5],
                new frequency_handler(null, null, null, null, null, null, null, null, $clock)],
            [event_type::CRITERIA_CHECK_REQUESTED, ['userid' => 0], new criteria_handler(null, null, null, null, null, null, null, null, null, null, $clock)],
        ];
        foreach ($cases as [$type, $payload, $handler]) {
            $result = $this->handle($type, $payload, $handler);

            $this->assertTrue($result->success, $type->value . ': ' . $result->message);
            $this->assertSame(erased_subject::RESPONSE, $result->message);
        }
        $this->assertSame($before, $this->written());
    }

    /**
     * A userid that is missing, negative or not a number is still a failure, not an erased subject; only 0 is the marker.
     */
    public function test_only_zero_is_the_marker(): void {
        $clock = fn() => time();
        foreach ([-1, 'x', null] as $bad) {
            foreach ([
                [event_type::GRADE_CHANGED, ['userid' => $bad, 'itemid' => 9, 'occurred_at' => 5], new performance_handler()],
                [event_type::CRITERIA_CHECK_REQUESTED, ['userid' => $bad], new criteria_handler(null, null, null, null, null, null, null, null, null, null, $clock)],
            ] as [$type, $payload, $handler]) {
                $event = (object) ['id' => 1, 'course_id' => $this->course->id, 'event_type' => $type->value, 'payload' => json_encode($payload)];
                $result = $handler->handle($event, (object) ['id' => 1]);
                $this->assertFalse($result->success, $type->value . ' with ' . var_export($bad, true));
                $this->assertStringContainsString('no valid userid', (string) $result->message);
            }
        }
    }

    /**
     * The events that are not of one student keep their way: a reference date still recalculates the course, and the quiz event, which
     * does not read the userid, is not affected by the marker.
     */
    public function test_events_without_a_student_and_the_quiz_event_are_not_affected(): void {
        $clock = fn() => time();
        $reference = $this->handle(
            event_type::REFERENCE_DATE_REACHED,
            ['cmid' => 1, 'reference_date' => time() - 100],
            new frequency_handler(null, null, null, null, null, null, null, null, $clock)
        );
        $this->assertTrue($reference->success, (string) $reference->message);
        $this->assertNotSame(erased_subject::RESPONSE, $reference->message);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);

        $result = $this->handle(
            event_type::QUIZ_ATTEMPT_SUBMITTED,
            ['cmid' => (int) $quiz->cmid, 'attemptid' => 5, 'userid' => 0],
            new results_handler()
        );

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertNotSame(erased_subject::RESPONSE, $result->message, 'The quiz handler does not read the userid');
    }
}
