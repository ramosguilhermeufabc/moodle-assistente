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

use local_assistente\application\context\context_snapshot_builder;
use local_assistente\application\llm\instruction_builder;
use local_assistente\application\synthesis\synthesis_trigger;
use local_assistente\application\llm\llm_result_adapter;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\event_handler;
use local_assistente\application\queue\handler_result;
use local_assistente\domain\event\event_type;
use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use local_assistente\infrastructure\llm\adapter_factory;
use local_assistente\infrastructure\llm\llm_port;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\moodle\forum_reader;
use local_assistente\infrastructure\moodle\html_text_converter;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\forum_analysis_repository;

/**
 * Handler of the forum events: the analysis of a forum (T082; spec-013; DEC-03, DEC-34, DEC-38).
 *
 * Every execution writes one complete snapshot of the forum (T081): the summary and the difficulties of all the discussions, in one
 * transaction, only when the answer of the LLM is valid. Any failure writes nothing, so the previous snapshot stays the current one
 * and a message of an event that failed does not enter the summary (R-013-01).
 *
 * Mode, chosen by the event (DEC-38):
 * - incremental, for a message created or edited (`forum_post_created`, `forum_post_updated`): the LLM receives the current summary
 *   (empty for the first message), the message, marked as an edition when it is one, the current difficulties of the affected discussion
 *   and the summaries of the eligible materials of the section. The difficulties returned replace those of the affected discussion
 *   (zero or more); the other discussions are copied unchanged from the current snapshot with no call. A `discussion_id` of another
 *   discussion makes the answer invalid (`invalid_response`), because the context lists only the affected one.
 * - rebuild, for a message or a discussion deleted (`forum_post_deleted`, `forum_discussion_deleted`): the LLM receives all the
 *   messages and discussions that still exist, with no previous summary or difficulties, so nothing deleted survives. A forum left without
 *   any message is a snapshot without summary (null) and without difficulties, written with no call.
 *   The same rebuild answers `forum_rebuild_requested`, the internal request of the initial synchronization (CL-47): it names only the
 *   forum (no discussion or message) and the current state is read when it is processed, not when it was requested.
 *
 * The context holds the text of the messages, the generic role (teacher or student), the name of the discussion and the materials, and
 * no author, user id, name or e-mail (DEC-34 D3). Private replies are left out, in both modes: an incremental event for a private reply is
 * done with nothing written, and the rebuild does not list them. The specification does not say whether they enter an analysis, and
 * leaving them out is the conservative reading (to be confirmed). A message that no longer exists when an incremental
 * event is processed is done with nothing written: the event of its deletion rebuilds the forum.
 *
 * The section comes from the `cmid` of the forum (T021); not resolved is a technical error. The snapshot of materials is built once for
 * the processing and reused by its retries (T064). Idempotent: a processing that already has its analysis does nothing.
 *
 * The texts of the tasks are provisional: Q-009-05 (instructions of the uses) is open.
 *
 * When the analysis is saved, or already was, it asks for the synthesis of the section of the forum (T098, DEC-41 D3); a failure to queue it fails the
 * attempt, and the retry asks again.
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class forum_analysis_handler implements event_handler {
    /** @var string task of the analysis; the language of the course is appended (Q-009-05 is open) */
    public const ANALYSIS_TASK = 'Summarize the forum for a course teacher and list the learning difficulties that the messages show, '
        . 'each with the id of its discussion. Return only the summary and the difficulties.';

    /** @var event_type[] events that rebuild the forum */
    private const REBUILD_EVENTS = [
        event_type::FORUM_POST_DELETED,
        event_type::FORUM_DISCUSSION_DELETED,
        event_type::FORUM_REBUILD_REQUESTED,
    ];

    /** @var forum_analysis_repository */
    private forum_analysis_repository $repository;

    /** @var forum_reader */
    private forum_reader $forums;

    /** @var course_section_reader */
    private course_section_reader $sections;

    /** @var student_reader */
    private student_reader $students;

    /** @var context_snapshot_builder */
    private context_snapshot_builder $snapshots;

    /** @var event_repository */
    private event_repository $events;

    /** @var llm_port */
    private llm_port $port;

    /** @var instruction_builder */
    private instruction_builder $instructions;

    /** @var synthesis_trigger */
    private synthesis_trigger $synthesis;

    /**
     * Constructor; every collaborator has a default.
     *
     * @param llm_port|null $port the port; the simulated one behind the privacy guard by default
     * @param forum_analysis_repository|null $repository
     * @param forum_reader|null $forums
     * @param course_section_reader|null $sections
     * @param student_reader|null $students
     * @param context_snapshot_builder|null $snapshots
     * @param event_repository|null $events
     * @param instruction_builder|null $instructions
     * @param synthesis_trigger|null $synthesis
     */
    public function __construct(
        ?llm_port $port = null,
        ?forum_analysis_repository $repository = null,
        ?forum_reader $forums = null,
        ?course_section_reader $sections = null,
        ?student_reader $students = null,
        ?context_snapshot_builder $snapshots = null,
        ?event_repository $events = null,
        ?instruction_builder $instructions = null,
        ?synthesis_trigger $synthesis = null
    ) {
        $this->port = $port ?? adapter_factory::create();
        $this->repository = $repository ?? new forum_analysis_repository();
        $this->forums = $forums ?? new forum_reader();
        $this->sections = $sections ?? new course_section_reader();
        $this->students = $students ?? new student_reader();
        $this->snapshots = $snapshots ?? new context_snapshot_builder();
        $this->events = $events ?? new event_repository();
        $this->instructions = $instructions ?? new instruction_builder($this->sections);
        $this->synthesis = $synthesis ?? new synthesis_trigger();
    }

    #[\Override]
    public function handle(\stdClass $event, \stdClass $processing): handler_result {
        $started = microtime(true);
        $type = event_type::tryFrom((string) $event->event_type);
        $payload = json_decode((string) $event->payload, true);
        $cmid = is_array($payload) ? ($payload['cmid'] ?? null) : null;
        $discussionid = is_array($payload) ? ($payload['discussionid'] ?? null) : null;
        if ($type === null || !is_int($cmid) || $cmid <= 0) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the event has no valid forum or discussion');
        }
        // The request of the initial synchronization names only the forum; every other event also names the discussion it affects.
        if ($type !== event_type::FORUM_REBUILD_REQUESTED && (!is_int($discussionid) || $discussionid <= 0)) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the event has no valid forum or discussion');
        }
        $courseid = (int) $event->course_id;
        $processingid = (int) $processing->id;

        if ($this->repository->find_by_processing($processingid) !== null) {
            try {
                $this->synthesis->request_for_module($courseid, $cmid);
            } catch (\Throwable $e) {
                return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
            }
            return handler_result::success('the forum analysis of this processing already exists');
        }
        $forum = $this->forums->get_forum($cmid);
        if ($forum === null || $forum->course_id !== $courseid) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the forum does not exist in the course');
        }
        $section = $this->sections->resolve_module_section($courseid, $cmid);
        if (!$section->is_resolved()) {
            return handler_result::failure(
                attempt_failure::TECHNICAL,
                'the section of the forum is not resolved (' . $section->status->value . ')'
            );
        }

        try {
            $materials = $this->materials($processingid, $courseid, (int) $section->sectionid);
            $current = $this->repository->find_current($courseid, $cmid);
            $existing = array_column($this->forums->get_discussions($cmid), null, 'discussion_id');
            $rebuild = in_array($type, self::REBUILD_EVENTS, true);

            if ($rebuild) {
                $outcome = $this->rebuild($existing, $materials, $courseid);
            } else {
                $outcome = $this->incremental($type, $payload, (int) $discussionid, $existing, $current, $materials, $courseid);
            }
            if ($outcome instanceof handler_result) {
                return $outcome;
            }
            [$summary, $difficulties, $calls] = $outcome;

            $this->repository->save($courseid, $cmid, $processingid, $summary, $difficulties);
            $this->synthesis->request_section($courseid, (int) $section->sectionid);
        } catch (\Throwable $e) {
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }

        return handler_result::success(sprintf(
            'forum analysis saved (%s): %d difficulties, %d LLM calls, %.2f s',
            $rebuild ? 'rebuild' : 'incremental',
            count($difficulties),
            $calls,
            microtime(true) - $started
        ));
    }

    /**
     * Incremental mode: the affected discussion goes to the LLM, the others are copied.
     *
     * @param event_type $type
     * @param array $payload
     * @param int $discussionid the affected discussion
     * @param array $existing discussions that exist now, by id
     * @param \stdClass|null $current the current snapshot
     * @param array $materials
     * @param int $courseid
     * @return array|handler_result [summary, difficulties, calls], or a result when there is nothing to do or the answer is not valid
     */
    private function incremental(
        event_type $type,
        array $payload,
        int $discussionid,
        array $existing,
        ?\stdClass $current,
        array $materials,
        int $courseid
    ): array|handler_result {
        $message = $this->forums->get_message((int) ($payload['postid'] ?? 0));
        if ($message === null || $message->discussion_id !== $discussionid || !isset($existing[$discussionid])) {
            return handler_result::success('the message no longer exists: nothing to analyse, its deletion rebuilds the forum');
        }
        if ($message->private_reply) {
            // A private reply is left out of the analysis in the incremental mode too, as in the rebuild (T084 found it sent to the LLM).
            return handler_result::success('the message is a private reply: left out of the analysis');
        }

        $before = $current === null ? [] : array_values($this->repository->find_difficulties((int) $current->id));
        $own = array_values(array_filter($before, static fn($d) => (int) $d->discussion_id === $discussionid));
        $result = $this->port->evaluate(
            new llm_context(llm_use_case::FORUM_ANALYSIS, [
                'mode' => 'incremental',
                'current_summary' => $current === null ? '' : (string) $current->summary,
                'discussion' => ['discussion_id' => $discussionid, 'name' => $existing[$discussionid]->name],
                'message' => [
                    'text' => $this->plain($message),
                    'role' => $message->role,
                    'edited' => $type === event_type::FORUM_POST_UPDATED,
                ],
                'current_difficulties' => array_map(static fn($d) => ['description' => $d->description], $own),
                'materials' => $materials,
                'discussion_ids' => [$discussionid],
            ]),
            $this->instructions->for_course($courseid, self::ANALYSIS_TASK)
        );
        if (!$result->is_valid()) {
            return llm_result_adapter::failure_of($result);
        }

        $difficulties = [];
        foreach ($before as $difficulty) {
            $other = (int) $difficulty->discussion_id;
            if ($other !== $discussionid && isset($existing[$other])) {
                $difficulties[] = ['discussion_id' => $other, 'description' => $difficulty->description];
            }
        }
        foreach ($result->data['difficulties'] as $returned) {
            $difficulties[] = ['discussion_id' => $returned['discussion_id'], 'description' => $returned['description']];
        }
        return [$result->data['forum_summary'], $difficulties, 1];
    }

    /**
     * Rebuild mode: everything that still exists goes to the LLM, with no previous summary or difficulties.
     *
     * @param array $existing discussions that exist now, by id
     * @param array $materials
     * @param int $courseid
     * @return array|handler_result [summary, difficulties, calls], or a result when the answer is not valid
     */
    private function rebuild(array $existing, array $materials, int $courseid): array|handler_result {
        $discussions = [];
        foreach ($existing as $id => $discussion) {
            $messages = array_values(array_filter(
                $this->forums->get_messages($id),
                static fn($m) => !$m->private_reply
            ));
            if ($messages) {
                $discussions[] = [
                    'discussion_id' => $id,
                    'name' => $discussion->name,
                    'messages' => array_map(fn($m) => ['text' => $this->plain($m), 'role' => $m->role], $messages),
                ];
            }
        }
        if (!$discussions) {
            return [null, [], 0];
        }

        $result = $this->port->evaluate(
            new llm_context(llm_use_case::FORUM_ANALYSIS, [
                'mode' => 'rebuild',
                'discussions' => $discussions,
                'materials' => $materials,
                'discussion_ids' => array_column($discussions, 'discussion_id'),
            ]),
            $this->instructions->for_course($courseid, self::ANALYSIS_TASK)
        );
        if (!$result->is_valid()) {
            return llm_result_adapter::failure_of($result);
        }
        return [$result->data['forum_summary'], $result->data['difficulties'], 1];
    }

    /**
     * Summaries of the eligible materials of the section, from the snapshot of the processing (built once, reused by the retries).
     *
     * @param int $processingid
     * @param int $courseid
     * @param int $sectionid
     * @return array list of ['name' => string, 'summary' => string]
     */
    private function materials(int $processingid, int $courseid, int $sectionid): array {
        $stored = $this->events->find_context($processingid);
        $snapshot = $stored !== null
            ? json_decode($stored->context_json, true, 512, JSON_THROW_ON_ERROR)
            : $this->snapshots->build_and_save($processingid, $courseid, $sectionid, $this->students->get_students($courseid), $this->events);
        $materials = [];
        foreach ($snapshot['materials'] as $material) {
            if ($material['eligible']) {
                $materials[] = ['name' => $material['name'], 'summary' => $material['summary']];
            }
        }
        return $materials;
    }

    /**
     * The text of a message as plain text: HTML and Moodle auto-format are converted, plain text and Markdown are kept as stored.
     *
     * @param \stdClass $message
     * @return string
     */
    private function plain(\stdClass $message): string {
        $text = in_array((string) $message->message_format, [(string) FORMAT_PLAIN, (string) FORMAT_MARKDOWN], true)
            ? $message->message
            : html_text_converter::to_text($message->message);
        return trim($text);
    }
}
