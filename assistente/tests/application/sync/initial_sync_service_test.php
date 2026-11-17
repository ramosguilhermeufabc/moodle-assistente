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

namespace local_assistente\application\sync;

use local_assistente\application\course_config_service;
use local_assistente\application\forum\forum_analysis_handler;
use local_assistente\application\forum\forum_event_service;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\forum_analysis_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the initial synchronization (quickstart 020 Q9 to Q13; DEC-45 D3; DEC-31; DEC-33 D3).
 *
 * The real services and the real queue are used, with the simulated LLM. The complete rebuild of the forums is requested by the
 * internal event `forum_rebuild_requested` (CL-47), one for each forum of the course, and processed by the forum handler.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(initial_sync_service::class)]
final class initial_sync_service_test extends \advanced_testcase {
    /** @var int clock of the synchronization, moved by the tests */
    private int $now = 1900000000;

    /**
     * The service uses real transactions, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The configuration service with the real initial synchronization.
     *
     * @return course_config_service
     */
    private function config(): course_config_service {
        $sync = new initial_sync_service(null, null, null, null, null, null, null, null, null, fn() => $this->now);
        return new course_config_service(null, null, null, null, $sync);
    }

    /**
     * Count the events of a type in the course.
     *
     * @param int $courseid
     * @param string $type
     * @return int
     */
    private function events(int $courseid, string $type): int {
        global $DB;
        return $DB->count_records('local_assistente_event', ['course_id' => $courseid, 'event_type' => $type]);
    }

    /**
     * A course with 2 visible questionnaires, 1 hidden, 3 materials (PDF, page, YouTube), a forum (one discussion of a teacher), a graded
     * assignment and a student.
     *
     * @return \stdClass course, student and cmids
     */
    private function course(): \stdClass {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $quizzes = [
            $generator->create_module('quiz', ['course' => $course->id])->cmid,
            $generator->create_module('quiz', ['course' => $course->id])->cmid,
        ];
        $generator->create_module('quiz', ['course' => $course->id, 'visible' => 0]);
        $pdf = $generator->create_module('resource', ['course' => $course->id]);
        $fs = get_file_storage();
        $fs->delete_area_files(\context_module::instance($pdf->cmid)->id, 'mod_resource', 'content');
        $fs->create_file_from_string([
            'contextid' => \context_module::instance($pdf->cmid)->id, 'component' => 'mod_resource', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'a.pdf',
        ], '%PDF-1.4 x');
        $generator->create_module('page', ['course' => $course->id, 'content' => '<p>Texto</p>']);
        $generator->create_module('url', ['course' => $course->id, 'externalurl' => 'https://www.youtube.com/watch?v=abc123']);
        $generator->create_module('url', ['course' => $course->id, 'externalurl' => 'https://example.com']);
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemmodule' => 'assign', 'iteminstance' => $assign->id, 'itemnumber' => 0]);
        $item->update_final_grade($student->id, 80, 'test');
        $forum = $generator->create_module('forum', ['course' => $course->id], ['section' => 1]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $teacher->id,
        ]);
        return (object) [
            'course' => $course, 'student' => $student, 'quizzes' => array_map('intval', $quizzes), 'forum' => (int) $forum->cmid,
        ];
    }

    /**
     * Q9: a course with no configuration is not synchronized and creates no event.
     */
    public function test_new_course_creates_no_event(): void {
        global $DB;
        $data = $this->course();
        $this->assertSame(0, $DB->count_records('local_assistente_event', ['course_id' => $data->course->id]));
    }

    /**
     * Q10: enabling queues one event per eligible item, forum included, and only those.
     */
    public function test_enabling_queues_the_events_of_the_current_state(): void {
        $data = $this->course();
        $this->assertTrue($this->config()->enable($data->course->id));
        $courseid = $data->course->id;

        $this->assertSame(2, $this->events($courseid, 'quiz_published'), 'Two visible questionnaires; the hidden one has none');
        $this->assertSame(3, $this->events($courseid, 'material_changed'), 'PDF, page and YouTube; the ordinary URL is not a material');
        $this->assertSame(1, $this->events($courseid, 'grade_changed'), 'One graded item of the one student');
        $this->assertSame(1, $this->events($courseid, 'participation_changed'), 'The student has a signal in the assignment only');
        $this->assertSame(0, $this->events($courseid, 'criteria_check_requested'), 'No criteria are applied by the synchronization');
        $this->assertSame(1, $this->events($courseid, 'forum_rebuild_requested'), 'One rebuild request for the one forum');
        $this->assertSame(0, $this->events($courseid, 'forum_discussion_deleted'), 'The rebuild does not use the deletion events');
        $this->assertSame(0, $this->events($courseid, 'forum_post_deleted'), 'The rebuild does not use the deletion events');
        $this->assertSame(0, $this->events($courseid, 'forum_post_created'), 'No historical message event is simulated');
    }

    /**
     * Q10 from the save of the configuration (T122, Q14 (d)): the page that the form calls (configure_page::save(), which calls
     * course_config_form::submit() and then the service) enables the course and the initial synchronization runs in the same request,
     * after the flag is saved, with one event per eligible item. The mechanism is a direct call of the service, not a Moodle event and
     * not a scheduled task.
     */
    public function test_saving_the_configuration_with_the_course_enabled_queues_one_event_per_item(): void {
        $data = $this->course();
        $courseid = $data->course->id;
        $page = new \local_assistente\output\configure_page(
            new course_config_service(null, null, null, null, new initial_sync_service())
        );

        $page->save((object) [
            'id' => $courseid, 'enabled' => 1, 'activity_criterion' => '60', 'forum_criterion' => 1, 'frequency_criterion' => '75',
        ]);

        $this->assertTrue((new course_config_service())->is_enabled($courseid));
        $this->assertSame(2, $this->events($courseid, 'quiz_published'));
        $this->assertSame(3, $this->events($courseid, 'material_changed'));
        $this->assertSame(1, $this->events($courseid, 'grade_changed'));
        $this->assertSame(1, $this->events($courseid, 'participation_changed'));
        $this->assertSame(1, $this->events($courseid, 'forum_rebuild_requested'));
        $this->assertSame(0, $this->events($courseid, 'criteria_check_requested'));
    }

    /**
     * Q13: enabling without criteria enables the assistant and applies no criteria.
     */
    public function test_enabling_without_criteria(): void {
        global $DB;
        $data = $this->course();
        $this->config()->enable($data->course->id);

        $this->assertTrue($this->config()->is_enabled($data->course->id));
        $this->assertSame(0, $DB->count_records('local_assistente_criteria_result'));
    }

    /**
     * The report of the last run counts the events by kind (R-020-01).
     */
    public function test_the_report_counts_the_events_by_kind(): void {
        $data = $this->course();
        $sync = new initial_sync_service(null, null, null, null, null, null, null, null, null, fn() => $this->now);
        (new \local_assistente\infrastructure\persistence\config_repository())->create($data->course->id, true);
        $sync->run($data->course->id);

        $this->assertSame(['material' => 3, 'quiz' => 2, 'grade' => 1, 'participation' => 1, 'forum' => 1], $sync->last_report());
    }

    /**
     * Q11: disabling creates no more events and keeps the pending ones pending.
     */
    public function test_disabled_course_creates_no_event(): void {
        $data = $this->course();
        $courseid = $data->course->id;
        $this->config()->enable($courseid);
        $before = $this->events($courseid, 'material_changed');
        $this->assertTrue($this->config()->disable($courseid));

        $generator = $this->getDataGenerator();
        $generator->create_module('page', ['course' => $courseid, 'content' => '<p>Novo</p>']);
        $generator->create_module('quiz', ['course' => $courseid]);

        $this->assertSame($before, $this->events($courseid, 'material_changed'));
        $this->assertSame(2, $this->events($courseid, 'quiz_published'));
        $this->assertSame('pending', $this->statuses($courseid)[0], 'Pending events stay pending while disabled');
        $this->assertSame(['pending'], array_values(array_unique($this->statuses($courseid))));
    }

    /**
     * Statuses of the events of a course.
     *
     * @param int $courseid
     * @return string[]
     */
    private function statuses(int $courseid): array {
        global $DB;
        return array_values($DB->get_fieldset_select('local_assistente_event', 'status', 'course_id = ?', [$courseid]));
    }

    /**
     * Q12: enabling again synchronizes again but creates no second initial analysis and no redundant version of a material.
     */
    public function test_enabling_again_creates_no_second_analysis_and_no_redundant_version(): void {
        global $DB;
        $data = $this->course();
        $courseid = $data->course->id;
        $this->config()->enable($courseid);
        (new queue_processor())->run_cycle();
        $versions = $DB->count_records('local_assistente_material_version');
        $analyses = $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_initial']);
        $this->assertSame(3, $versions, 'One version for each of the 3 materials');
        $this->assertSame(2, $analyses, 'One initial analysis for each of the 2 questionnaires');

        $this->config()->disable($courseid);
        $this->now += 3600;
        $this->assertTrue($this->config()->enable($courseid));
        $this->assertSame(2, $this->events($courseid, 'quiz_published'), 'The publication of a questionnaire is queued once in its life');
        $this->assertSame(6, $this->events($courseid, 'material_changed'), 'The materials are synchronized again');
        (new queue_processor())->run_cycle();

        $this->assertSame($versions, $DB->count_records('local_assistente_material_version'), 'The content did not change: no new version');
        $this->assertSame($analyses, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'quiz_initial']));
    }

    /**
     * The memory of the run in the participation step (A2) changes how the data is read, not what is queued: with it and without it a course
     * with forums, quizzes with finished attempts and an assignment queues the same events, with the same identities.
     */
    public function test_the_memory_of_the_run_queues_the_same_events(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $students = [];
        for ($i = 0; $i < 4; $i++) {
            $students[] = (int) $generator->create_and_enrol($course, 'student')->id;
        }
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $students[0],
        ]);
        $generator->get_plugin_generator('mod_forum')->create_post([
            'discussion' => $discussion->id, 'parent' => $DB->get_field('forum_discussions', 'firstpost', ['id' => $discussion->id]),
            'userid' => $students[1], 'subject' => 'r', 'message' => 'm',
        ]);
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $unique = (int) $DB->get_field_sql('SELECT COALESCE(MAX(uniqueid), 0) FROM {quiz_attempts}') + 1;
        foreach ([[$students[0], 'finished', 0], [$students[2], 'inprogress', 0], [$students[3], 'finished', 1]] as [$userid, $state, $preview]) {
            $DB->insert_record('quiz_attempts', (object) [
                'quiz' => $quiz->id, 'userid' => $userid, 'attempt' => 1, 'uniqueid' => $unique++, 'layout' => '', 'currentpage' => 0,
                'preview' => $preview, 'state' => $state, 'timestart' => $this->now - 100, 'timefinish' => $state === 'finished' ? $this->now - 50 : 0,
                'timemodified' => $this->now - 50, 'timemodifiedoffline' => 0, 'sumgrades' => 1.0,
            ]);
        }
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        \grade_item::fetch(['courseid' => $course->id, 'itemmodule' => 'assign', 'iteminstance' => $assign->id, 'itemnumber' => 0])
            ->update_final_grade($students[0], 80, 'test');
        (new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);

        $withoutmemo = new class extends \local_assistente\infrastructure\moodle\participation_reader {
            #[\Override]
            public function with_run_memo(\Closure $work): mixed {
                return $work();
            }
        };
        $identities = function (?\local_assistente\infrastructure\moodle\participation_reader $reader) use ($course, $DB): array {
            $DB->delete_records('local_assistente_event', ['course_id' => $course->id]);
            (new initial_sync_service(null, null, null, null, $reader, null, null, null, null, fn() => $this->now))->run($course->id);
            $rows = [];
            foreach ($DB->get_records('local_assistente_event', ['course_id' => $course->id], 'id ASC') as $event) {
                $rows[] = $event->event_type . ':' . json_decode($event->payload, true)['identity'];
            }
            sort($rows);
            return $rows;
        };

        $with = $identities(null);
        $without = $identities($withoutmemo);

        $this->assertNotEmpty(array_filter($with, fn($r) => str_starts_with($r, 'participation_changed:')));
        $this->assertSame($without, $with);
    }

    /**
     * A course of its own for the forum tests: no material, questionnaire or grade, only the forums asked for.
     *
     * @return \stdClass course, a student and a teacher
     */
    private function forum_course(): \stdClass {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 3]);
        return (object) [
            'course' => $course,
            'student' => $generator->create_and_enrol($course, 'student'),
            'teacher' => $generator->create_and_enrol($course, 'editingteacher'),
        ];
    }

    /**
     * A forum in the first section of the course.
     *
     * @param \stdClass $data from forum_course()
     * @return \stdClass the forum with its cmid
     */
    private function forum(\stdClass $data): \stdClass {
        return $this->getDataGenerator()->create_module('forum', ['course' => $data->course->id], ['section' => 1]);
    }

    /**
     * A discussion of the student in a forum, with its first message.
     *
     * @param \stdClass $data from forum_course()
     * @param \stdClass $forum
     * @param string $name
     * @param string $message
     * @return \stdClass the discussion record
     */
    private function discussion(\stdClass $data, \stdClass $forum, string $name, string $message): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $data->course->id, 'forum' => $forum->id, 'userid' => $data->student->id,
            'name' => $name, 'message' => "<p>{$message}</p>",
        ]);
    }

    /**
     * A registry that runs only the rebuild of the forums, with the given adapter, so that the test can see what the LLM receives.
     *
     * @param fake_adapter $llm
     * @return handler_registry
     */
    private function forum_registry(fake_adapter $llm): handler_registry {
        $registry = new handler_registry();
        $registry->register(event_type::FORUM_REBUILD_REQUESTED, new forum_analysis_handler($llm));
        return $registry;
    }

    /**
     * Statuses of the requests of rebuild of a course, oldest first.
     *
     * @param int $courseid
     * @return string[]
     */
    private function rebuild_statuses(int $courseid): array {
        global $DB;
        return array_values($DB->get_fieldset_select(
            'local_assistente_event',
            'status',
            'course_id = ? AND event_type = ? ORDER BY id ASC',
            [$courseid, 'forum_rebuild_requested']
        ));
    }

    /**
     * Cmids of the forums that have a request of rebuild in the course, in order.
     *
     * @param int $courseid
     * @return int[]
     */
    private function requested_forums(int $courseid): array {
        global $DB;
        $cmids = [];
        foreach ($DB->get_records('local_assistente_event', ['course_id' => $courseid, 'event_type' => 'forum_rebuild_requested'], 'id ASC') as $event) {
            $payload = json_decode($event->payload, true);
            $this->assertSame(['cmid', 'identity'], array_keys($payload), 'The payload of the request holds only the forum');
            $cmids[] = $payload['cmid'];
        }
        return $cmids;
    }

    /**
     * A forum with several discussions gets one request, not one for each discussion or message, and no historical event is simulated.
     */
    public function test_a_forum_with_several_discussions_has_one_request(): void {
        $data = $this->forum_course();
        $forum = $this->forum($data);
        $this->discussion($data, $forum, 'A', 'texto a');
        $this->discussion($data, $forum, 'B', 'texto b');
        $this->discussion($data, $forum, 'C', 'texto c');

        $this->config()->enable($data->course->id);

        $this->assertSame([(int) $forum->cmid], $this->requested_forums($data->course->id));
        $this->assertSame(1, $this->events($data->course->id, 'forum_rebuild_requested'));
        foreach (['forum_post_created', 'forum_post_updated', 'forum_post_deleted', 'forum_discussion_deleted'] as $type) {
            $this->assertSame(0, $this->events($data->course->id, $type), $type);
        }
    }

    /**
     * Each forum of the course has its own request; the report counts them.
     */
    public function test_each_forum_of_the_course_has_its_request(): void {
        $data = $this->forum_course();
        $forums = [$this->forum($data), $this->forum($data), $this->forum($data)];
        $this->discussion($data, $forums[0], 'A', 'texto');
        (new \local_assistente\infrastructure\persistence\config_repository())->create($data->course->id, true);
        $sync = new initial_sync_service(null, null, null, null, null, null, null, null, null, fn() => $this->now);

        $sync->run($data->course->id);

        $this->assertSame(array_map(static fn($f) => (int) $f->cmid, $forums), $this->requested_forums($data->course->id));
        $this->assertSame(3, $sync->last_report()['forum']);
    }

    /**
     * A course without forums queues no request of rebuild, and the report counts none.
     */
    public function test_a_course_without_forums_has_no_request(): void {
        $data = $this->forum_course();
        (new \local_assistente\infrastructure\persistence\config_repository())->create($data->course->id, true);
        $sync = new initial_sync_service(null, null, null, null, null, null, null, null, null, fn() => $this->now);

        $sync->run($data->course->id);

        $this->assertSame(0, $this->events($data->course->id, 'forum_rebuild_requested'));
        $this->assertSame(0, $sync->last_report()['forum']);
    }

    /**
     * A course that is not enabled queues nothing, forums included (DEC-45 D3, Q9).
     */
    public function test_a_course_that_is_not_enabled_has_no_request(): void {
        $data = $this->forum_course();
        $this->forum($data);
        $sync = new initial_sync_service(null, null, null, null, null, null, null, null, null, fn() => $this->now);

        $sync->run($data->course->id);

        $this->assertSame(0, $this->events($data->course->id, 'forum_rebuild_requested'));
        $this->assertSame(0, $sync->last_report()['forum'], 'A request that the queue skipped is not counted');
    }

    /**
     * Enabling again before the first request ran does not stack a second rebuild: pending requests of a forum coalesce.
     */
    public function test_enabling_again_while_the_request_is_pending_coalesces(): void {
        $data = $this->forum_course();
        $forum = $this->forum($data);
        $this->discussion($data, $forum, 'A', 'texto');
        $this->config()->enable($data->course->id);
        $this->config()->disable($data->course->id);
        $this->assertSame(['pending'], $this->rebuild_statuses($data->course->id), 'Disabling keeps the pending request');

        $this->now += 3600;
        $this->config()->enable($data->course->id);

        $this->assertSame(1, $this->events($data->course->id, 'forum_rebuild_requested'));
    }

    /**
     * Enabling again after the request was processed asks for a new rebuild (DEC-45 D3: the synchronization runs again), and the one
     * already done is kept.
     */
    public function test_enabling_again_after_the_processing_asks_for_a_new_rebuild(): void {
        global $DB;
        $data = $this->forum_course();
        $forum = $this->forum($data);
        $this->discussion($data, $forum, 'A', 'texto');
        $this->config()->enable($data->course->id);
        (new queue_processor(null, $this->forum_registry(new fake_adapter())))->run_cycle();
        $this->assertSame(['processed'], $this->rebuild_statuses($data->course->id));

        $this->config()->disable($data->course->id);
        $this->now += 3600;
        $this->config()->enable($data->course->id);

        $this->assertSame(2, $this->events($data->course->id, 'forum_rebuild_requested'));
        $this->assertSame(['processed', 'pending'], $this->rebuild_statuses($data->course->id), 'The one done is kept, the new one waits');
        $this->assertSame(
            1,
            $DB->count_records('local_assistente_event', ['course_id' => $data->course->id, 'event_type' => 'forum_rebuild_requested', 'status' => 'pending'])
        );
    }

    /**
     * The request is processed by the real queue and the snapshot is complete: every discussion and message that exists, the replies,
     * and not the private replies, with the generic role and no identification of the author.
     */
    public function test_the_real_queue_rebuilds_the_complete_snapshot(): void {
        global $DB;
        $data = $this->forum_course();
        $forum = $this->forum($data);
        $a = $this->discussion($data, $forum, 'Duvida A', 'primeira mensagem de a');
        $this->discussion($data, $forum, 'Duvida B', 'primeira mensagem de b');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $generator->create_post(['discussion' => $a->id, 'parent' => $a->firstpost, 'userid' => $data->teacher->id, 'subject' => 'Re', 'message' => '<p>resposta do professor</p>']);
        $generator->create_post([
            'discussion' => $a->id, 'parent' => $a->firstpost, 'userid' => $data->teacher->id, 'subject' => 'Re',
            'message' => '<p>resposta privada sigilosa</p>', 'privatereplyto' => $data->student->id,
        ]);
        $spy = new fake_adapter();
        $this->config()->enable($data->course->id);

        (new queue_processor(null, $this->forum_registry($spy)))->run_cycle();

        $this->assertSame(1, $spy->calls());
        $context = end($spy->requests)['context']->data;
        $this->assertSame('rebuild', $context['mode']);
        $this->assertCount(2, $context['discussion_ids']);
        $json = json_encode($context);
        foreach (['primeira mensagem de a', 'primeira mensagem de b', 'resposta do professor'] as $text) {
            $this->assertStringContainsString($text, $json);
        }
        $this->assertStringNotContainsString('resposta privada sigilosa', $json);
        $this->assertDoesNotMatchRegularExpression('/user_?id|author|firstname|lastname|@/i', $json);
        $current = (new forum_analysis_repository())->find_current((int) $data->course->id, (int) $forum->cmid);
        $this->assertNotNull($current);
        $this->assertSame(['processed'], $this->rebuild_statuses($data->course->id));
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'forum_analysis']));
    }

    /**
     * A forum with no message is an empty snapshot, written with no call to the LLM.
     */
    public function test_an_empty_forum_is_an_empty_snapshot(): void {
        $data = $this->forum_course();
        $forum = $this->forum($data);
        $spy = new fake_adapter();
        $this->config()->enable($data->course->id);

        (new queue_processor(null, $this->forum_registry($spy)))->run_cycle();

        $this->assertSame(0, $spy->calls());
        $this->assertSame(['processed'], $this->rebuild_statuses($data->course->id));
        $current = (new forum_analysis_repository())->find_current((int) $data->course->id, (int) $forum->cmid);
        $this->assertNotNull($current);
        $this->assertNull($current->summary);
        $this->assertSame([], (new forum_analysis_repository())->find_difficulties((int) $current->id));
    }

    /**
     * A failure of the LLM in the rebuild publishes nothing: the snapshot of before stays the current one, and the request goes back to
     * be tried again.
     */
    public function test_a_failed_rebuild_keeps_the_previous_snapshot(): void {
        $data = $this->forum_course();
        $forum = $this->forum($data);
        $this->discussion($data, $forum, 'A', 'texto');
        $repository = new forum_analysis_repository();
        $ok = new fake_adapter();
        $ok->respond_valid(['forum_summary' => 'Resumo anterior', 'difficulties' => []]);
        $this->config()->enable($data->course->id);
        (new queue_processor(null, $this->forum_registry($ok)))->run_cycle();
        $before = $repository->find_current((int) $data->course->id, (int) $forum->cmid);
        $this->assertSame('Resumo anterior', $before->summary);

        $this->config()->disable($data->course->id);
        $this->now += 3600;
        $this->config()->enable($data->course->id);
        $broken = new fake_adapter();
        $broken->fail_with('timeout');
        (new queue_processor(null, $this->forum_registry($broken)))->run_cycle();

        $current = $repository->find_current((int) $data->course->id, (int) $forum->cmid);
        $this->assertSame($before->id, $current->id, 'The snapshot of before is still the current one');
        $this->assertSame('Resumo anterior', $current->summary);
        $this->assertCount(1, $repository->find_history((int) $data->course->id, (int) $forum->cmid));
        $this->assertSame(['processed', 'pending'], $this->rebuild_statuses($data->course->id), 'The new request is tried again');
    }

    /**
     * A real deletion after the initial synchronization is its own event, never discarded as a duplicate of the rebuild, and its
     * rebuild leaves the deleted discussion out.
     */
    public function test_a_real_deletion_after_the_synchronization_is_not_discarded(): void {
        global $DB;
        $data = $this->forum_course();
        $forum = $this->forum($data);
        $a = $this->discussion($data, $forum, 'A', 'texto de a');
        $b = $this->discussion($data, $forum, 'B', 'texto de b que sera excluido');
        $this->config()->enable($data->course->id);
        (new queue_processor(null, $this->forum_registry(new fake_adapter())))->run_cycle();
        $this->assertSame(['processed'], $this->rebuild_statuses($data->course->id));

        forum_delete_discussion(
            $DB->get_record('forum_discussions', ['id' => $b->id], '*', MUST_EXIST),
            true,
            $data->course,
            get_coursemodule_from_id('forum', $forum->cmid, 0, false, MUST_EXIST),
            $DB->get_record('forum', ['id' => $forum->id], '*', MUST_EXIST)
        );
        // The real deletion emits the Moodle event, and the observer queues its own event, independent of the request of the rebuild.
        $this->assertSame(1, $this->events($data->course->id, 'forum_discussion_deleted'), 'The observer queued the real deletion');
        $deleted = (new forum_event_service())->discussion_deleted((int) $data->course->id, (int) $forum->cmid, (int) $b->id);
        $this->assertSame(\local_assistente\application\enqueue_status::REUSED, $deleted->status, 'The same deletion is one event');
        $registry = new handler_registry();
        $spy = new fake_adapter();
        $registry->register(event_type::FORUM_DISCUSSION_DELETED, new forum_analysis_handler($spy));
        (new queue_processor(null, $registry))->run_cycle();

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $deleted->event->id]));
        $context = end($spy->requests)['context']->data;
        $this->assertSame([(int) $a->id], $context['discussion_ids']);
        $this->assertStringNotContainsString('texto de b que sera excluido', json_encode($context));
        $this->assertSame(1, $this->events($data->course->id, 'forum_rebuild_requested'));
        $this->assertSame(1, $this->events($data->course->id, 'forum_discussion_deleted'));
    }

    /**
     * Integrated flow (CL-47, T121), with nothing registered by hand: enabling the course runs the real synchronization, which queues the
     * rebuild request; the real queue, with the default registry (`handler_registry::default()`, what the cron task uses), hands it to the
     * handler that the plugin registers, which reads the real forum and saves the snapshot. Only the LLM is a double: the plugin has no
     * adapter but the simulated one (adapter_factory), whose answers are marked "[SIMULADO]".
     */
    public function test_the_default_registry_rebuilds_the_forum_after_the_initial_synchronization(): void {
        global $DB;
        $data = $this->forum_course();
        $forum = $this->forum($data);
        $first = $this->discussion($data, $forum, 'Duvida A', 'primeira mensagem de a');
        $this->discussion($data, $forum, 'Duvida B', 'primeira mensagem de b');
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_post([
            'discussion' => $first->id, 'parent' => $first->firstpost, 'userid' => $data->teacher->id, 'subject' => 'Re',
            'message' => '<p>resposta do professor</p>',
        ]);
        $repository = new forum_analysis_repository();
        $this->assertNull($repository->find_current((int) $data->course->id, (int) $forum->cmid), 'No snapshot before the synchronization');

        $this->assertTrue($this->config()->enable($data->course->id));
        $request = $DB->get_record(
            'local_assistente_event',
            ['course_id' => $data->course->id, 'event_type' => 'forum_rebuild_requested'],
            '*',
            MUST_EXIST
        );
        $this->assertSame('pending', $request->status);
        $this->assertSame((int) $forum->cmid, json_decode($request->payload, true)['cmid']);

        (new queue_processor())->run_cycle();

        $request = $DB->get_record('local_assistente_event', ['id' => $request->id], '*', MUST_EXIST);
        $this->assertSame('processed', $request->status, (string) $request->response);
        $this->assertStringContainsString('forum analysis saved (rebuild)', (string) $request->response, 'The forum handler ran, in the rebuild mode');
        $processing = (new \local_assistente\infrastructure\persistence\event_repository())->find_processing((int) $request->id);
        $analysis = $repository->find_by_processing((int) $processing->id);
        $this->assertNotNull($analysis, 'The snapshot belongs to the processing of the request');
        $this->assertStringContainsString('[SIMULADO]', (string) $analysis->summary, 'The LLM was the simulated adapter');
        $current = $repository->find_current((int) $data->course->id, (int) $forum->cmid);
        $this->assertSame($analysis->id, $current->id, 'It is the current snapshot of the forum');
        $this->assertCount(1, $repository->find_history((int) $data->course->id, (int) $forum->cmid));
        $this->assertSame(
            0,
            $DB->count_records('local_assistente_event', ['course_id' => $data->course->id, 'status' => 'failure']),
            'The queue left no event of the course in failure'
        );
    }

    /**
     * Every forum module of the course takes part, hidden forums and forums of announcements included: the synchronization applies no
     * filter of visibility or type, as the observers analyse any forum (CL-47; no rule of eligibility of forums exists).
     */
    public function test_hidden_forums_and_forums_of_announcements_take_part(): void {
        global $DB;
        $data = $this->forum_course();
        $generator = $this->getDataGenerator();
        $ordinary = $this->forum($data);
        $hidden = $generator->create_module('forum', ['course' => $data->course->id, 'visible' => 0], ['section' => 1]);
        $news = $generator->create_module('forum', ['course' => $data->course->id, 'type' => 'news'], ['section' => 1]);
        $this->assertSame(0, (int) $DB->get_field('course_modules', 'visible', ['id' => $hidden->cmid]), 'The forum is really hidden');
        $this->assertSame('news', $DB->get_field('forum', 'type', ['id' => $news->id]), 'The forum is really of announcements');

        $this->config()->enable($data->course->id);

        $this->assertSame(
            array_map('intval', [$ordinary->cmid, $hidden->cmid, $news->cmid]),
            $this->requested_forums($data->course->id)
        );
    }
}
