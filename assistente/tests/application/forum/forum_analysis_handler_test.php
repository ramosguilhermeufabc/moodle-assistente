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

namespace local_assistente\application\forum;

use local_assistente\application\llm\guarded_llm_port;
use local_assistente\application\material\material_service;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\forum_analysis_repository;
use local_assistente\infrastructure\persistence\material_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the analysis of a forum (T082; quickstart 013 Q1 to Q12; DEC-34, DEC-38). Real course, forum and queue; the LLM is the
 * simulated adapter (DEC-48). Q13 is the technical validation T084.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(forum_analysis_handler::class)]
final class forum_analysis_handler_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass forum with cmid */
    private \stdClass $forum;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \stdClass */
    private \stdClass $teacher;

    /** @var fake_adapter */
    private fake_adapter $llm;

    /** @var event_repository */
    private event_repository $events;

    /** @var forum_analysis_repository */
    private forum_analysis_repository $repository;

    /** @var forum_analysis_handler */
    private forum_analysis_handler $handler;

    /** @var \mod_forum_generator */
    private $generator;

    /**
     * Course with the assistant enabled, a student and a teacher, a forum in section 1 with a material of the same section and one of
     * another section.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        (new config_repository())->create($this->course->id, true);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Joaquina', 'lastname' => 'Sobrenomeunico']);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id], ['section' => 1]);
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $this->llm = new fake_adapter();
        $this->events = new event_repository();
        $this->repository = new forum_analysis_repository();
        $this->handler = new forum_analysis_handler($this->llm);
    }

    /**
     * A processed page (an eligible material) in a section.
     *
     * @param int $section
     * @param string $name
     * @return int cmid
     */
    private function material(int $section, string $name): int {
        $page = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $this->course->id, 'name' => $name, 'content' => "<p>{$name}</p>"],
            ['section' => $section]
        );
        $this->assertTrue((new material_service(new material_repository(), null, new fake_adapter()))
            ->process((int) $this->course->id, (int) $page->cmid)->success);
        return (int) $page->cmid;
    }

    /**
     * A discussion with its first message.
     *
     * @param string $subject
     * @param string $message
     * @param \stdClass|null $author
     * @return \stdClass discussion and first post id
     */
    private function discussion(string $subject, string $message, ?\stdClass $author = null): \stdClass {
        $record = $this->generator->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => ($author ?? $this->student)->id,
            'name' => $subject, 'message' => "<p>{$message}</p>",
        ]);
        return (object) ['id' => (int) $record->id, 'post' => (int) $record->firstpost];
    }

    /**
     * A reply in a discussion.
     *
     * @param \stdClass $discussion
     * @param string $message
     * @param \stdClass|null $author
     * @param int|null $private user id the reply is private to
     * @return int post id
     */
    private function reply(\stdClass $discussion, string $message, ?\stdClass $author = null, ?int $private = null): int {
        $params = [
            'discussion' => $discussion->id, 'parent' => $discussion->post, 'userid' => ($author ?? $this->student)->id,
            'subject' => 'Re', 'message' => "<p>{$message}</p>",
        ];
        if ($private !== null) {
            $params['privatereplyto'] = $private;
        }
        return (int) $this->generator->create_post($params)->id;
    }

    /**
     * Deliver an event of the forum to the handler.
     *
     * @param event_type $type
     * @param int $discussionid
     * @param int $postid
     * @return array handler_result and processing
     */
    private function deliver(event_type $type, int $discussionid, int $postid = 0): array {
        static $occurred = 7000;
        $payload = ['cmid' => (int) $this->forum->cmid, 'discussionid' => $discussionid];
        if ($type === event_type::FORUM_REBUILD_REQUESTED) {
            $payload = ['cmid' => (int) $this->forum->cmid];
        } else if ($type !== event_type::FORUM_DISCUSSION_DELETED) {
            $payload['postid'] = $postid;
        }
        if (isset($type->fields()['occurred_at'])) {
            $payload['occurred_at'] = ++$occurred;
        }
        $event = $this->events->create_event((int) $this->course->id, $type, $payload, time());
        $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        return [$this->handler->handle($event, $processing), $processing, $event];
    }

    /**
     * Difficulties of the analysis of a processing as [discussion id, description].
     *
     * @param \stdClass $processing
     * @return array
     */
    private function difficulties_of(\stdClass $processing): array {
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        return array_values(array_map(
            static fn($d) => [(int) $d->discussion_id, $d->description],
            $this->repository->find_difficulties((int) $analysis->id)
        ));
    }

    /**
     * The data of the last request to the port.
     *
     * @return array
     */
    private function last_context(): array {
        return end($this->llm->requests)['context']->data;
    }

    /**
     * Q1, Q3, Q4 and Q5: the first message of the forum has an empty current summary; the context has the message, the materials of the
     * same section and not those of another; the analysis keeps the summary and the difficulty with its discussion.
     */
    public function test_first_message_creates_the_analysis_with_the_context_of_the_section(): void {
        $this->material(1, 'Material da secao');
        $this->material(2, 'Material de outra secao');
        $discussion = $this->discussion('Duvida', 'Nao entendi o conceito');
        $this->llm->respond_valid([
            'forum_summary' => 'Resumo 1',
            'difficulties' => [['discussion_id' => $discussion->id, 'description' => 'Conceito nao entendido']],
        ]);

        [$result, $processing] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $discussion->post);

        $this->assertTrue($result->success, (string) $result->response());
        $context = $this->last_context();
        $this->assertSame('incremental', $context['mode']);
        $this->assertSame('', $context['current_summary'], 'Q5: first message, empty summary');
        $this->assertSame('Nao entendi o conceito', $context['message']['text']);
        $this->assertFalse($context['message']['edited']);
        $this->assertSame('student', $context['message']['role']);
        $this->assertSame(['Material da secao'], array_column($context['materials'], 'name'), 'Q4: only the material of the section');
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame('Resumo 1', $analysis->summary);
        $this->assertSame((int) $this->forum->cmid, (int) $analysis->cmid);
        $this->assertSame([[$discussion->id, 'Conceito nao entendido']], $this->difficulties_of($processing), 'Q3');
    }

    /**
     * Q2: a second update keeps the earlier summary in the history, and the LLM receives the current one.
     */
    public function test_second_message_keeps_the_history_and_sends_the_current_summary(): void {
        $discussion = $this->discussion('Duvida', 'Primeira');
        $this->llm->respond_valid(['forum_summary' => 'Resumo 1', 'difficulties' => []]);
        [, $first] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $discussion->post);
        $this->llm->respond_valid(['forum_summary' => 'Resumo 2', 'difficulties' => []]);

        [$result, $second] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $this->reply($discussion, 'Segunda'));

        $this->assertTrue($result->success);
        $this->assertSame('Resumo 1', $this->last_context()['current_summary']);
        $this->assertSame('Resumo 1', $this->repository->find_by_processing((int) $first->id)->summary, 'The earlier summary is still there');
        $this->assertSame('Resumo 2', $this->repository->find_current((int) $this->course->id, (int) $this->forum->cmid)->summary);
        $this->assertCount(2, $this->repository->find_history((int) $this->course->id, (int) $this->forum->cmid));
    }

    /**
     * Q6: discussion A has two difficulties; a new message in A returns one revised and one new, without one of the old; A keeps the two
     * returned and the old one stays in the earlier analysis.
     */
    public function test_difficulties_of_the_affected_discussion_are_replaced(): void {
        $a = $this->discussion('A', 'texto a');
        $this->llm->respond_valid(['forum_summary' => 'r1', 'difficulties' => [
            ['discussion_id' => $a->id, 'description' => 'Antiga 1'],
            ['discussion_id' => $a->id, 'description' => 'Antiga 2'],
        ]]);
        [, $first] = $this->deliver(event_type::FORUM_POST_CREATED, $a->id, $a->post);
        $this->llm->respond_valid(['forum_summary' => 'r2', 'difficulties' => [
            ['discussion_id' => $a->id, 'description' => 'Antiga 1 revisada'],
            ['discussion_id' => $a->id, 'description' => 'Nova'],
        ]]);

        [, $second] = $this->deliver(event_type::FORUM_POST_CREATED, $a->id, $this->reply($a, 'mais'));

        $this->assertSame(['Antiga 1', 'Antiga 2'], array_column($this->difficulties_of($first), 1));
        $this->assertSame(['Antiga 1 revisada', 'Nova'], array_column($this->difficulties_of($second), 1));
        $this->assertSame(['Antiga 1', 'Antiga 2'], array_column($this->last_context()['current_difficulties'], 'description'));
    }

    /**
     * Q7: discussions A and B, a new message in A: the difficulties of B are copied unchanged with no call for B, and the context lists
     * only A.
     */
    public function test_other_discussions_are_copied_without_the_llm(): void {
        $a = $this->discussion('A', 'texto a');
        $b = $this->discussion('B', 'texto b');
        $this->llm->respond_valid(['forum_summary' => 'r1', 'difficulties' => [
            ['discussion_id' => $a->id, 'description' => 'Da A'],
        ]]);
        $this->deliver(event_type::FORUM_POST_CREATED, $a->id, $a->post);
        $this->llm->respond_valid(['forum_summary' => 'r2', 'difficulties' => [['discussion_id' => $a->id, 'description' => 'Da A 2']]]);
        $this->deliver(event_type::FORUM_POST_CREATED, $b->id, $b->post);
        $this->llm->respond_valid(['forum_summary' => 'r3', 'difficulties' => [['discussion_id' => $b->id, 'description' => 'Da B']]]);
        $this->deliver(event_type::FORUM_POST_CREATED, $b->id, $this->reply($b, 'b2'));
        $calls = $this->llm->calls();
        $this->llm->respond_valid(['forum_summary' => 'r4', 'difficulties' => [['discussion_id' => $a->id, 'description' => 'Da A nova']]]);

        [$result, $processing] = $this->deliver(event_type::FORUM_POST_CREATED, $a->id, $this->reply($a, 'a2'));

        $this->assertTrue($result->success);
        $this->assertSame($calls + 1, $this->llm->calls(), 'One call, for A');
        $this->assertSame([$a->id], $this->last_context()['discussion_ids']);
        $this->assertEqualsCanonicalizing(
            [[$a->id, 'Da A nova'], [$b->id, 'Da B']],
            $this->difficulties_of($processing),
            'B is copied unchanged'
        );
    }

    /**
     * Q8: the answer names a difficulty of another discussion in the incremental mode: invalid_response, nothing is published and the
     * previous snapshot stays the current one.
     */
    public function test_a_discussion_of_another_one_is_invalid_and_nothing_is_published(): void {
        $a = $this->discussion('A', 'texto a');
        $b = $this->discussion('B', 'texto b');
        $this->llm->respond_valid(['forum_summary' => 'r1', 'difficulties' => []]);
        $this->deliver(event_type::FORUM_POST_CREATED, $a->id, $a->post);
        $this->llm->respond_with(['use_case' => 'forum_analysis', 'status' => 'valid', 'data' => [
            'forum_summary' => 'r2', 'difficulties' => [['discussion_id' => $b->id, 'description' => 'Da B']],
        ]]);

        [$result, $processing] = $this->deliver(event_type::FORUM_POST_CREATED, $a->id, $this->reply($a, 'a2'));

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::INVALID_RESPONSE, $result->failure);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id));
        $this->assertSame('r1', $this->repository->find_current((int) $this->course->id, (int) $this->forum->cmid)->summary);
    }

    /**
     * Q9 and Q12: a failure of the LLM publishes nothing; the message of that failed event does not enter the current summary, which is
     * the previous one when the next message comes (R-013-01).
     */
    public function test_a_failure_publishes_nothing_and_the_failed_message_stays_out(): void {
        $discussion = $this->discussion('A', 'texto');
        $this->llm->respond_valid(['forum_summary' => 'Resumo 1', 'difficulties' => []]);
        $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $discussion->post);
        $this->llm->fail_with('timeout');

        [$failed, $processing] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $this->reply($discussion, 'mensagem que falhou'));
        $this->llm->respond_valid(['forum_summary' => 'Resumo 3', 'difficulties' => []]);
        [$next] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $this->reply($discussion, 'mensagem seguinte'));

        $this->assertFalse($failed->success);
        $this->assertSame(attempt_failure::TIMEOUT, $failed->failure);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id));
        $this->assertTrue($next->success);
        $this->assertSame('Resumo 1', $this->last_context()['current_summary'], 'The summary of the failed message never existed');
        $this->assertStringNotContainsString('mensagem que falhou', json_encode($this->last_context()['current_summary']));
        $this->assertCount(2, $this->repository->find_history((int) $this->course->id, (int) $this->forum->cmid));
    }

    /**
     * Q10: an edited message goes in the incremental mode marked as an edition; the deletion of a message and of a discussion rebuild
     * from what exists, with no previous summary or difficulty and without the deleted content.
     */
    public function test_edit_is_incremental_and_deletions_rebuild_what_exists(): void {
        global $DB;
        $a = $this->discussion('A', 'texto a');
        $b = $this->discussion('B', 'texto b');
        $reply = $this->reply($a, 'resposta que sera excluida');
        $this->llm->respond_valid(['forum_summary' => 'r1', 'difficulties' => [['discussion_id' => $a->id, 'description' => 'Antiga']]]);
        $this->deliver(event_type::FORUM_POST_CREATED, $a->id, $a->post);

        $this->llm->respond_valid(['forum_summary' => 'r2', 'difficulties' => []]);
        [$edit] = $this->deliver(event_type::FORUM_POST_UPDATED, $a->id, $reply);
        $this->assertTrue($edit->success);
        $this->assertSame('incremental', $this->last_context()['mode']);
        $this->assertTrue($this->last_context()['message']['edited']);

        $DB->set_field('forum_posts', 'deleted', 1, ['id' => $reply]);
        $this->llm->respond_valid(['forum_summary' => 'r3', 'difficulties' => [['discussion_id' => $a->id, 'description' => 'Reconstruida']]]);
        [$postdeleted, $afterpost] = $this->deliver(event_type::FORUM_POST_DELETED, $a->id, $reply);
        $this->assertTrue($postdeleted->success, (string) $postdeleted->response());
        $context = $this->last_context();
        $this->assertSame('rebuild', $context['mode']);
        $this->assertArrayNotHasKey('current_summary', $context, 'No previous summary in a rebuild');
        $this->assertArrayNotHasKey('current_difficulties', $context);
        $this->assertStringNotContainsString('resposta que sera excluida', json_encode($context));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $context['discussion_ids']);
        $this->assertSame([[$a->id, 'Reconstruida']], $this->difficulties_of($afterpost));

        forum_delete_discussion(
            $DB->get_record('forum_discussions', ['id' => $b->id], '*', MUST_EXIST),
            true,
            $this->course,
            get_coursemodule_from_id('forum', $this->forum->cmid, 0, false, MUST_EXIST),
            $DB->get_record('forum', ['id' => $this->forum->id], '*', MUST_EXIST)
        );
        $this->llm->respond_valid(['forum_summary' => 'r4', 'difficulties' => []]);
        [$gone] = $this->deliver(event_type::FORUM_DISCUSSION_DELETED, $b->id);
        $this->assertTrue($gone->success, (string) $gone->response());
        $this->assertSame([$a->id], $this->last_context()['discussion_ids'], 'Only what still exists');
        $this->assertStringNotContainsString('texto b', json_encode($this->last_context()));
    }

    /**
     * Q11: the context sent through the guard of privacy holds no author, user id, name or e-mail.
     */
    public function test_the_context_has_no_author_or_identifier(): void {
        $this->handler = new forum_analysis_handler(new guarded_llm_port($this->llm));
        $discussion = $this->discussion('Duvida', 'Minha duvida');
        $this->reply($discussion, 'Resposta do professor', $this->teacher);

        [$result] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $this->reply($discussion, 'Outra mensagem'));

        $this->assertTrue($result->success, (string) $result->response());
        $json = json_encode($this->last_context());
        $this->assertStringNotContainsString('Joaquina', $json);
        $this->assertStringNotContainsString('Sobrenomeunico', $json);
        $this->assertStringNotContainsString((string) $this->student->email, $json);
        $this->assertDoesNotMatchRegularExpression('/user_?id|author|firstname|lastname|@/i', $json);
    }

    /**
     * Private replies are left out of the context (a conservative reading, to confirm).
     */
    public function test_private_replies_are_left_out(): void {
        $discussion = $this->discussion('A', 'texto');
        $this->reply($discussion, 'resposta privada', $this->teacher, (int) $this->student->id);
        $this->llm->respond_valid(['forum_summary' => 'r', 'difficulties' => []]);

        [$result] = $this->deliver(event_type::FORUM_POST_DELETED, $discussion->id, 99999);

        $this->assertTrue($result->success);
        $this->assertStringNotContainsString('resposta privada', json_encode($this->last_context()));
    }

    /**
     * T084 finding: an incremental event for a private reply (message created or edited) sends nothing to the LLM and writes nothing,
     * as the rebuild already left private replies out.
     */
    public function test_an_incremental_event_for_a_private_reply_is_left_out(): void {
        $discussion = $this->discussion('A', 'texto');
        $private = $this->reply($discussion, 'resposta privada sigilosa', $this->teacher, (int) $this->student->id);

        [$created, $first] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $private);
        [$edited, $second] = $this->deliver(event_type::FORUM_POST_UPDATED, $discussion->id, $private);

        $this->assertTrue($created->success);
        $this->assertTrue($edited->success);
        $this->assertSame(0, $this->llm->calls(), 'Nothing about a private reply reaches the LLM');
        $this->assertNull($this->repository->find_by_processing((int) $first->id));
        $this->assertNull($this->repository->find_by_processing((int) $second->id));
        $this->assertStringContainsString('private reply', (string) $created->response());
    }
    /**
     * A forum left without any message is a snapshot without summary and without difficulties, written with no call.
     */
    public function test_forum_without_messages_is_an_empty_snapshot_without_a_call(): void {
        global $DB;
        $discussion = $this->discussion('A', 'texto');
        $this->llm->respond_valid(['forum_summary' => 'r', 'difficulties' => [['discussion_id' => $discussion->id, 'description' => 'x']]]);
        $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $discussion->post);
        $calls = $this->llm->calls();
        $DB->set_field('forum_posts', 'deleted', 1, ['discussion' => $discussion->id]);

        [$result, $processing] = $this->deliver(event_type::FORUM_POST_DELETED, $discussion->id, $discussion->post);

        $this->assertTrue($result->success, (string) $result->response());
        $this->assertSame($calls, $this->llm->calls());
        $this->assertNull($this->repository->find_by_processing((int) $processing->id)->summary);
        $this->assertSame([], $this->difficulties_of($processing));
    }

    /**
     * A message that no longer exists when the incremental event is processed is done with nothing written.
     */
    public function test_a_message_that_no_longer_exists_writes_nothing(): void {
        $discussion = $this->discussion('A', 'texto');

        [$result, $processing] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, 987654);

        $this->assertTrue($result->success);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id));
        $this->assertSame(0, $this->llm->calls());
    }

    /**
     * A technical error is never a success: a module that is not a forum of the course fails the attempt.
     */
    public function test_a_forum_that_does_not_exist_is_a_technical_failure(): void {
        $event = $this->events->create_event((int) $this->course->id, event_type::FORUM_POST_CREATED, ['cmid' => 987654, 'discussionid' => 1, 'postid' => 1], time());
        $this->events->begin_attempt((int) $event->id, time());

        $result = $this->handler->handle($event, $this->events->find_processing((int) $event->id));

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $this->assertSame(0, $this->llm->calls());
    }

    /**
     * Idempotency: a repeated delivery of a processing that has its analysis does nothing more.
     */
    public function test_repeated_delivery_is_idempotent(): void {
        global $DB;
        $discussion = $this->discussion('A', 'texto');
        [, $processing, $event] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $discussion->post);
        $calls = $this->llm->calls();

        $again = $this->handler->handle($event, $processing);

        $this->assertTrue($again->success);
        $this->assertSame($calls, $this->llm->calls());
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'forum_analysis']));
    }

    /**
     * Registered for the five forum events, and run by the real queue under the lock of the event.
     */
    public function test_registered_and_run_by_the_queue(): void {
        global $DB;
        foreach ([event_type::FORUM_POST_CREATED, event_type::FORUM_POST_UPDATED, event_type::FORUM_POST_DELETED, event_type::FORUM_DISCUSSION_DELETED,
                event_type::FORUM_REBUILD_REQUESTED] as $type) {
            $this->assertInstanceOf(forum_analysis_handler::class, handler_registry::default()->handler_for($type), $type->value);
        }
        $discussion = $this->discussion('A', 'texto');
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::FORUM_POST_CREATED,
            ['cmid' => (int) $this->forum->cmid, 'discussionid' => $discussion->id, 'postid' => $discussion->post],
            time()
        );
        $registry = new handler_registry();
        $registry->register(event_type::FORUM_POST_CREATED, $this->handler);

        (new queue_processor(null, $registry))->run_cycle();

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $event->id]));
        $this->assertNotNull($this->repository->find_by_processing((int) $this->events->find_processing((int) $event->id)->id));
    }

    /**
     * CL-47: the request of the initial synchronization names only the forum, and rebuilds it as a whole: every discussion that exists
     * goes to the LLM in the rebuild mode, with no previous summary or difficulty, and the answer is one complete snapshot.
     */
    public function test_a_rebuild_request_rebuilds_the_whole_forum_without_a_discussion(): void {
        $a = $this->discussion('A', 'texto da discussao a');
        $this->reply($a, 'resposta em a');
        $b = $this->discussion('B', 'texto da discussao b');
        $this->llm->respond_valid(['forum_summary' => 'antigo', 'difficulties' => [['discussion_id' => $a->id, 'description' => 'Antiga']]]);
        $this->deliver(event_type::FORUM_POST_CREATED, $a->id, $a->post);
        $this->llm->respond_valid(['forum_summary' => 'Resumo completo', 'difficulties' => [
            ['discussion_id' => $a->id, 'description' => 'Dificuldade de A'],
            ['discussion_id' => $b->id, 'description' => 'Dificuldade de B'],
        ]]);

        [$result, $processing] = $this->deliver(event_type::FORUM_REBUILD_REQUESTED, 0);

        $this->assertTrue($result->success, (string) $result->response());
        $this->assertStringContainsString('rebuild', (string) $result->response());
        $context = $this->last_context();
        $this->assertSame('rebuild', $context['mode']);
        $this->assertArrayNotHasKey('current_summary', $context);
        $this->assertArrayNotHasKey('current_difficulties', $context);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $context['discussion_ids']);
        $this->assertStringContainsString('texto da discussao a', json_encode($context));
        $this->assertStringContainsString('resposta em a', json_encode($context));
        $this->assertStringContainsString('texto da discussao b', json_encode($context));
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame('Resumo completo', $analysis->summary);
        $this->assertSame([[$a->id, 'Dificuldade de A'], [$b->id, 'Dificuldade de B']], $this->difficulties_of($processing));
        $this->assertSame($analysis->id, $this->repository->find_current((int) $this->course->id, (int) $this->forum->cmid)->id);
    }

    /**
     * CL-47: the request reads the state of the forum when it is processed, not when it was made, and leaves private replies out.
     */
    public function test_a_rebuild_request_reads_the_state_at_processing_and_leaves_private_replies_out(): void {
        $a = $this->discussion('A', 'texto antes do pedido');
        $event = $this->events->create_event((int) $this->course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => (int) $this->forum->cmid], time());
        $b = $this->discussion('B', 'texto depois do pedido');
        $this->reply($a, 'resposta privada sigilosa', $this->teacher, (int) $this->student->id);
        $this->events->begin_attempt((int) $event->id, time());
        $this->llm->respond_valid(['forum_summary' => 'r', 'difficulties' => []]);

        $result = $this->handler->handle($event, $this->events->find_processing((int) $event->id));

        $this->assertTrue($result->success, (string) $result->response());
        $json = json_encode($this->last_context());
        $this->assertStringContainsString('texto depois do pedido', $json, 'A discussion created after the request is in the snapshot');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->last_context()['discussion_ids']);
        $this->assertStringNotContainsString('resposta privada sigilosa', $json);
    }

    /**
     * CL-47: the context of a rebuild request holds no author, user id, name or e-mail (DEC-34 D3).
     */
    public function test_a_rebuild_request_sends_no_author_or_identifier(): void {
        $this->handler = new forum_analysis_handler(new guarded_llm_port($this->llm));
        $discussion = $this->discussion('Duvida', 'Minha duvida');
        $this->reply($discussion, 'Resposta do professor', $this->teacher);

        [$result] = $this->deliver(event_type::FORUM_REBUILD_REQUESTED, 0);

        $this->assertTrue($result->success, (string) $result->response());
        $json = json_encode($this->last_context());
        $this->assertStringNotContainsString('Joaquina', $json);
        $this->assertStringNotContainsString((string) $this->student->email, $json);
        $this->assertDoesNotMatchRegularExpression('/user_?id|author|firstname|lastname|@/i', $json);
    }

    /**
     * CL-47: a forum with no message at all is an empty snapshot, with no call to the LLM.
     */
    public function test_a_rebuild_request_of_an_empty_forum_is_an_empty_snapshot_without_a_call(): void {
        [$result, $processing] = $this->deliver(event_type::FORUM_REBUILD_REQUESTED, 0);

        $this->assertTrue($result->success, (string) $result->response());
        $this->assertSame(0, $this->llm->calls());
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertNull($analysis->summary);
        $this->assertSame([], $this->difficulties_of($processing));
    }

    /**
     * CL-47: a failure of the rebuild publishes nothing: the previous snapshot stays the current one, and the retry can still succeed.
     */
    public function test_a_failed_rebuild_request_keeps_the_previous_snapshot(): void {
        $discussion = $this->discussion('A', 'texto');
        $this->llm->respond_valid(['forum_summary' => 'Anterior', 'difficulties' => [['discussion_id' => $discussion->id, 'description' => 'Dificuldade anterior']]]);
        [, $first] = $this->deliver(event_type::FORUM_POST_CREATED, $discussion->id, $discussion->post);
        $this->llm->fail_with('timeout');

        [$failed, $processing] = $this->deliver(event_type::FORUM_REBUILD_REQUESTED, 0);

        $this->assertFalse($failed->success);
        $this->assertSame(attempt_failure::TIMEOUT, $failed->failure);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id));
        $current = $this->repository->find_current((int) $this->course->id, (int) $this->forum->cmid);
        $this->assertSame('Anterior', $current->summary);
        $this->assertSame([[$discussion->id, 'Dificuldade anterior']], $this->difficulties_of($first));
        $this->assertCount(1, $this->repository->find_history((int) $this->course->id, (int) $this->forum->cmid));
    }

    /**
     * CL-47: an answer that is not valid (a discussion that is not in the context) publishes nothing either.
     */
    public function test_an_invalid_answer_to_a_rebuild_request_publishes_nothing(): void {
        $discussion = $this->discussion('A', 'texto');
        $this->llm->respond_valid(['forum_summary' => 'r', 'difficulties' => [['discussion_id' => $discussion->id + 1000, 'description' => 'x']]]);

        [$result, $processing] = $this->deliver(event_type::FORUM_REBUILD_REQUESTED, 0);

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::INVALID_RESPONSE, $result->failure);
        $this->assertNull($this->repository->find_by_processing((int) $processing->id));
    }

    /**
     * CL-47: the request asks for the synthesis of the section when the snapshot is saved, as every forum analysis does (DEC-41 D3).
     */
    public function test_a_rebuild_request_asks_for_the_synthesis_of_the_section(): void {
        global $DB;
        $this->discussion('A', 'texto');
        $before = $DB->count_records('local_assistente_event', ['course_id' => $this->course->id, 'event_type' => 'synthesis_requested']);

        [$result] = $this->deliver(event_type::FORUM_REBUILD_REQUESTED, 0);

        $this->assertTrue($result->success, (string) $result->response());
        $this->assertSame(
            $before + 1,
            $DB->count_records('local_assistente_event', ['course_id' => $this->course->id, 'event_type' => 'synthesis_requested'])
        );
    }

    /**
     * CL-47: the request is validated by its type. It needs a forum of the course and no discussion; the others still need the discussion.
     */
    public function test_the_payload_is_validated_by_the_type_of_the_event(): void {
        $other = $this->getDataGenerator()->create_course();
        (new config_repository())->create($other->id, true);
        $foreign = $this->getDataGenerator()->create_module('forum', ['course' => $other->id]);
        $unknown = $this->events->create_event((int) $this->course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 987654], time());
        $this->events->begin_attempt((int) $unknown->id, time());
        $elsewhere = $this->events->create_event((int) $this->course->id, event_type::FORUM_REBUILD_REQUESTED, ['cmid' => (int) $foreign->cmid], time());
        $this->events->begin_attempt((int) $elsewhere->id, time());
        $nodiscussion = $this->events->create_event((int) $this->course->id, event_type::FORUM_POST_DELETED, ['cmid' => (int) $this->forum->cmid, 'discussionid' => 1, 'postid' => 1], time());
        $nodiscussion->payload = json_encode(['cmid' => (int) $this->forum->cmid]);

        foreach ([$unknown, $elsewhere] as $event) {
            $result = $this->handler->handle($event, $this->events->find_processing((int) $event->id));
            $this->assertFalse($result->success);
            $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        }
        $processing = (object) ['id' => 0];
        $result = $this->handler->handle($nodiscussion, $processing);
        $this->assertFalse($result->success, 'A deletion event without the discussion is still refused');
        $this->assertStringContainsString('the event has no valid forum or discussion', (string) $result->response());
        $this->assertSame(0, $this->llm->calls());
    }

    /**
     * CL-47: idempotent, like every forum event: delivering a processing that has its snapshot again does not call the LLM again.
     */
    public function test_a_repeated_rebuild_request_is_idempotent(): void {
        global $DB;
        $this->discussion('A', 'texto');
        [, $processing, $event] = $this->deliver(event_type::FORUM_REBUILD_REQUESTED, 0);
        $calls = $this->llm->calls();

        $again = $this->handler->handle($event, $processing);

        $this->assertTrue($again->success);
        $this->assertSame($calls, $this->llm->calls());
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'forum_analysis']));
    }

    /**
     * CL-47: run by the real queue, through the default registry, with the simulated LLM and the whole forum in the snapshot.
     */
    public function test_a_rebuild_request_is_processed_by_the_real_queue(): void {
        global $DB;
        $this->discussion('A', 'texto a');
        $this->discussion('B', 'texto b');
        $request = (new forum_event_service())->rebuild_requested((int) $this->course->id, (int) $this->forum->cmid);
        $registry = new handler_registry();
        $registry->register(event_type::FORUM_REBUILD_REQUESTED, new forum_analysis_handler(new fake_adapter()));

        (new queue_processor(null, $registry))->run_cycle();

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $request->event->id]));
        $analysis = $this->repository->find_by_processing((int) $this->events->find_processing((int) $request->event->id)->id);
        $this->assertNotNull($analysis);
        $this->assertStringContainsString('[SIMULADO]', (string) $analysis->summary);
        $this->assertSame($analysis->id, $this->repository->find_current((int) $this->course->id, (int) $this->forum->cmid)->id);
    }
}
