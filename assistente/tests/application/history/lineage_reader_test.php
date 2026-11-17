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


namespace local_assistente\application\history;

use local_assistente\application\forum\forum_analysis_handler;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\performance_repository;
use local_assistente\infrastructure\persistence\synthesis_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the lineage reader (T101; quickstart 017 Q1, Q2 and Q4; DEC-42). Real course, events, processings and repositories; the forum
 * analysis is made by the real handler with the simulated adapter. The snapshot with three materials is written in the documented structure
 * of the context (Q-008-06) because the test is about reading it, not about building it.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(lineage_reader::class)]
#[CoversClass(lineage::class)]
final class lineage_reader_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \stdClass */
    private \stdClass $forum;

    /** @var event_repository */
    private event_repository $events;

    /** @var lineage_reader */
    private lineage_reader $reader;

    /** @var \local_assistente_generator */
    private $generator;

    /**
     * A course with the assistant, a student and a forum with a discussion.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        (new \local_assistente\infrastructure\persistence\config_repository())->create($this->course->id, true);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id], ['section' => 1]);
        $this->events = new event_repository();
        $this->reader = new lineage_reader();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
    }

    /**
     * An event with its first attempt, as the queue leaves it before the handler.
     *
     * @param event_type $type
     * @param array $payload
     * @param int|null $courseid
     * @return array [event, processing]
     */
    private function started(event_type $type, array $payload, ?int $courseid = null): array {
        $event = $this->events->create_event($courseid ?? (int) $this->course->id, $type, $payload, time());
        $this->events->begin_attempt((int) $event->id, time());
        return [$event, $this->events->find_processing((int) $event->id)];
    }

    /**
     * Run the real forum analysis for a new discussion, with a difficulty of the discussion in the answer.
     *
     * @param string $description
     * @return array [discussion, analysis row, difficulty row, event]
     */
    private function forum_analysis(string $description): array {
        global $DB;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $this->student->id,
        ]);
        $adapter = (new fake_adapter())->respond_with_callback(static fn($context) => [
            'use_case' => 'forum_analysis', 'status' => 'valid',
            'data' => ['forum_summary' => 'Resumo ' . $description, 'difficulties' => [
                ['discussion_id' => $context->discussion_ids()[0], 'description' => $description],
            ]],
        ]);
        [$event, $processing] = $this->started(event_type::FORUM_POST_CREATED, [
            'cmid' => (int) $this->forum->cmid, 'discussionid' => (int) $discussion->id, 'postid' => (int) $discussion->firstpost,
        ]);
        $result = (new forum_analysis_handler($adapter))->handle($event, $processing);
        $this->assertTrue($result->success, (string) $result->message);
        $this->events->complete_attempt((int) $this->events->find_attempts((int) $processing->id)[0]->id, time(), $result->message);
        $analysis = $DB->get_record('local_assistente_analysis', ['processing_id' => $processing->id], '*', MUST_EXIST);
        // A later analysis of the forum keeps the difficulties of the other discussions, so the one of this answer is picked by its discussion.
        $difficulty = $DB->get_record('local_assistente_forum_difficulty', ['analysis_id' => $analysis->id, 'discussion_id' => $discussion->id], '*', MUST_EXIST);
        return [$discussion, $analysis, $difficulty, $event];
    }

    /**
     * Q1: from a difficulty of the forum the chain reaches the analysis, the processing, the attempt, the event and the Moodle source, and the
     * references are complete and without repetition.
     */
    public function test_a_forum_difficulty_reaches_the_event_and_the_moodle_source(): void {
        global $DB;
        [$discussion, $analysis, $difficulty, $event] = $this->forum_analysis('Dúvida sobre prazos');
        $before = $DB->count_records('local_assistente_event');

        $lineage = $this->reader->trace_result('local_assistente_forum_difficulty', (int) $difficulty->id);

        $this->assertSame((int) $analysis->id, (int) $lineage->analysis->id);
        $this->assertSame('forum_analysis', $lineage->analysis->analysis_type);
        $this->assertSame((int) $analysis->processing_id, (int) $lineage->processing->id);
        $this->assertSame((int) $event->id, (int) $lineage->event->id);
        $this->assertSame('forum_post_created', $lineage->event->event_type);
        $this->assertSame((int) $this->forum->cmid, $lineage->payload['cmid']);
        $this->assertCount(1, $lineage->attempts);
        $this->assertSame('processed', $lineage->attempts[0]->status);
        $this->assertSame('local_assistente_forum_difficulty', $lineage->result['table']);
        $this->assertSame('Dúvida sobre prazos', $lineage->result['row']->description);
        $this->assertEqualsCanonicalizing([
            ['type' => 'course_module', 'id' => (int) $this->forum->cmid],
            ['type' => 'forum_discussion', 'id' => (int) $discussion->id],
            ['type' => 'forum_post', 'id' => (int) $discussion->firstpost],
        ], $lineage->sources);
        $this->assertSame($before, $DB->count_records('local_assistente_event'), 'Reading writes nothing');
    }

    /**
     * Q2: an update is a new analysis; the earlier state stays and is reached from the new one, intact.
     */
    public function test_an_update_keeps_the_earlier_state_reachable(): void {
        [, $first] = $this->forum_analysis('Primeira dúvida');
        $this->waitForSecond();
        [, $second] = $this->forum_analysis('Segunda dúvida');

        $history = $this->reader->history((int) $second->id);

        $this->assertSame([(int) $second->id, (int) $first->id], array_map(fn($a) => (int) $a->id, $history));
        $this->assertSame('Resumo Primeira dúvida', $history[1]->summary);
        $this->assertSame([(int) $first->id], array_map(fn($a) => (int) $a->id, $this->reader->history((int) $first->id)));
        $this->assertSame([], $this->reader->history(987654));
    }

    /**
     * Q4: a processing that used three materials lists the three with the version used and the availability evaluated.
     */
    public function test_a_processing_with_three_materials_lists_them_with_their_versions(): void {
        [, $processing] = $this->started(event_type::QUIZ_PUBLISHED, ['cmid' => 77]);
        $snapshot = ['format' => 1, 'course_id' => (int) $this->course->id, 'section_id' => 1, 'language' => 'pt_br', 'created_at' => time(),
            'students_evaluated' => 1, 'used_versions' => [11, 22], 'materials' => [
                ['material_id' => 1, 'cmid' => 31, 'name' => 'Livro', 'material_type' => 'pdf', 'version_id' => 11, 'version' => 2,
                    'availability' => ['coverage' => 'all'], 'eligible' => true, 'reason' => null, 'summary' => 'texto'],
                ['material_id' => 2, 'cmid' => 32, 'name' => 'Página', 'material_type' => 'text', 'version_id' => 22, 'version' => 1,
                    'availability' => ['coverage' => 'all'], 'eligible' => true, 'reason' => null, 'summary' => 'texto'],
                ['material_id' => 3, 'cmid' => 33, 'name' => 'Vídeo', 'material_type' => 'url', 'version_id' => null, 'version' => null,
                    'availability' => ['coverage' => 'none'], 'eligible' => false, 'reason' => 'no_current_version'],
            ]];
        $this->events->save_context((int) $processing->id, json_encode($snapshot), time());
        $analysis = $this->generator->create_analysis([
            'course_id' => $this->course->id, 'processing_id' => $processing->id, 'analysis_type' => 'quiz_initial', 'cmid' => 77,
        ]);

        $lineage = $this->reader->trace_analysis((int) $analysis->id);

        $this->assertCount(3, $lineage->materials);
        $this->assertSame([[1, 11, 2, true], [2, 22, 1, true], [3, null, null, false]], array_map(
            static fn($m) => [$m['material_id'], $m['version_id'], $m['version'], $m['eligible']],
            $lineage->materials
        ));
        $this->assertSame('no_current_version', $lineage->materials[2]['reason']);
        $this->assertArrayNotHasKey('summary', $lineage->materials[0], 'The chain keeps the references, not the texts');
        $this->assertSame('pt_br', $lineage->context['language']);
        $this->assertContains(['type' => 'course_module', 'id' => 77], $lineage->sources);
    }

    /**
     * A legitimate absence is not an error: a processing with no snapshot has no context and no materials; a snapshot of another kind has
     * no materials.
     */
    public function test_absence_of_a_snapshot_or_of_materials_is_not_an_error(): void {
        [, $none] = $this->started(event_type::GRADE_CHANGED, ['userid' => (int) $this->student->id, 'itemid' => 5, 'occurred_at' => time()]);
        $a = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => $none->id, 'analysis_type' => 'grade_performance']);
        [, $other] = $this->started(event_type::GRADE_CHANGED, ['userid' => (int) $this->student->id, 'itemid' => 6, 'occurred_at' => time() + 1]);
        $this->events->save_context((int) $other->id, json_encode(['kind' => 'grade_performance', 'student_id' => (int) $this->student->id]), time());
        $b = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => $other->id, 'analysis_type' => 'grade_performance']);

        $withoutcontext = $this->reader->trace_analysis((int) $a->id);
        $withoutmaterials = $this->reader->trace_analysis((int) $b->id);

        $this->assertNull($withoutcontext->context);
        $this->assertSame([], $withoutcontext->materials);
        $this->assertNotNull($withoutmaterials->context);
        $this->assertSame([], $withoutmaterials->materials);
        $this->assertEqualsCanonicalizing([
            ['type' => 'user', 'id' => (int) $this->student->id], ['type' => 'grade_item', 'id' => 5],
        ], $withoutcontext->sources);
    }

    /**
     * A synthesis is traced to its event, its section is a source, and its versions are the history; another section has its own.
     */
    public function test_a_synthesis_is_traced_and_its_versions_are_its_history(): void {
        global $DB;
        $sections = array_map('intval', array_values($DB->get_fieldset_select('course_sections', 'id', 'course = ? AND section > 0', [$this->course->id], 'section')));
        $repository = new synthesis_repository();
        $versions = [];
        foreach ([[0, 'A1'], [0, 'A2'], [1, 'B1']] as [$s, $text]) {
            [, $processing] = $this->started(event_type::SYNTHESIS_REQUESTED, ['section_id' => $sections[$s]]);
            $versions[$text] = $repository->save((int) $this->course->id, $sections[$s], (int) $processing->id, $text, hash('sha256', $text), time() + count($versions));
        }

        $lineage = $this->reader->trace_result('local_assistente_pedagogical_synthesis', (int) $versions['A2']->id);
        $history = $this->reader->history((int) $versions['A2']->analysis_id);
        $other = $this->reader->history((int) $versions['B1']->analysis_id);

        $this->assertSame('synthesis_requested', $lineage->event->event_type);
        $this->assertSame([['type' => 'course_section', 'id' => $sections[0]]], $lineage->sources, 'The section of the event and of the version is one source');
        $this->assertSame([(int) $versions['A2']->analysis_id, (int) $versions['A1']->analysis_id], array_map(fn($a) => (int) $a->id, $history));
        $this->assertSame([(int) $versions['B1']->analysis_id], array_map(fn($a) => (int) $a->id, $other));
    }

    /**
     * The history of a performance is the one of its student: another student and another course are not mixed in.
     */
    public function test_performance_history_is_of_the_student_and_of_the_course(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $second = $this->getDataGenerator()->create_course();
        (new \local_assistente\infrastructure\persistence\config_repository())->create($second->id, true);
        $this->getDataGenerator()->enrol_user($this->student->id, $second->id, 'student');
        $repository = new performance_repository();
        $make = function (int $courseid, \stdClass $user, int $now) use ($repository) {
            [, $processing] = $this->started(event_type::GRADE_CHANGED, ['userid' => (int) $user->id, 'itemid' => 1, 'occurred_at' => $now], $courseid);
            return $repository->save($courseid, (int) $processing->id, (int) $user->id, [], $now);
        };
        $mine1 = $make((int) $this->course->id, $this->student, 1000);
        $others = $make((int) $this->course->id, $other, 1001);
        $mine2 = $make((int) $this->course->id, $this->student, 1002);
        $elsewhere = $make((int) $second->id, $this->student, 1003);

        $history = $this->reader->history($mine2);

        $this->assertSame([$mine2, $mine1], array_map(fn($a) => (int) $a->id, $history));
        $this->assertSame([$elsewhere], array_map(fn($a) => (int) $a->id, $this->reader->history($elsewhere)));
        $this->assertSame([$others], array_map(fn($a) => (int) $a->id, $this->reader->history($others)));
    }

    /**
     * Ids and tables that do not exist: null for a missing row, an error for a table that is not a result.
     */
    public function test_missing_rows_are_null_and_other_tables_are_refused(): void {
        $this->assertNull($this->reader->trace_analysis(987654));
        $this->assertNull($this->reader->trace_result('local_assistente_forum_difficulty', 987654));
        foreach (['local_assistente_event', 'user', 'local_assistente_analysis', 'nonsense'] as $table) {
            try {
                $this->reader->trace_result($table, 1);
                $this->fail("{$table} was accepted");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * A broken link is an error, never a guess: a processing that does not exist, an event of another course, a payload or a snapshot that
     * is not valid, a snapshot of another course, a material without identifier, a result without analysis, a synthesis of another course.
     */
    public function test_broken_links_are_errors(): void {
        global $DB;
        [$event, $processing] = $this->started(event_type::QUIZ_PUBLISHED, ['cmid' => 5]);
        $cases = [];
        $orphan = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => 987654]);
        $cases['processing missing'] = fn() => $this->reader->trace_analysis((int) $orphan->id);

        $foreign = $this->getDataGenerator()->create_course();
        $mixed = $this->generator->create_analysis(['course_id' => $foreign->id, 'processing_id' => $processing->id]);
        $cases['event of another course'] = fn() => $this->reader->trace_analysis((int) $mixed->id);

        [$e2, $p2] = $this->started(event_type::QUIZ_PUBLISHED, ['cmid' => 6]);
        $a2 = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => $p2->id]);
        $DB->set_field('local_assistente_event', 'payload', 'not json', ['id' => $e2->id]);
        $cases['payload not valid'] = fn() => $this->reader->trace_analysis((int) $a2->id);

        [, $p3] = $this->started(event_type::QUIZ_PUBLISHED, ['cmid' => 7]);
        $a3 = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => $p3->id]);
        $this->events->save_context((int) $p3->id, 'not json', time());
        $cases['snapshot not valid'] = fn() => $this->reader->trace_analysis((int) $a3->id);

        [, $p4] = $this->started(event_type::QUIZ_PUBLISHED, ['cmid' => 8]);
        $a4 = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => $p4->id]);
        $this->events->save_context((int) $p4->id, json_encode(['course_id' => (int) $foreign->id, 'materials' => []]), time());
        $cases['snapshot of another course'] = fn() => $this->reader->trace_analysis((int) $a4->id);

        [, $p5] = $this->started(event_type::QUIZ_PUBLISHED, ['cmid' => 9]);
        $a5 = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => $p5->id]);
        $this->events->save_context((int) $p5->id, json_encode(['course_id' => (int) $this->course->id, 'materials' => [['name' => 'sem id']]]), time());
        $cases['material without identifier'] = fn() => $this->reader->trace_analysis((int) $a5->id);

        $lost = $this->generator->create_criteria_result(['analysis_id' => 987654]);
        $cases['result without analysis'] = fn() => $this->reader->trace_result('local_assistente_criteria_result', (int) $lost->id);

        [, $p6] = $this->started(event_type::SYNTHESIS_REQUESTED, ['section_id' => 1]);
        $a6 = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => $p6->id, 'analysis_type' => 'section_synthesis']);
        $wrong = $this->generator->create_pedagogical_synthesis(['course_id' => $foreign->id, 'analysis_id' => $a6->id]);
        $cases['synthesis of another course'] = fn() => $this->reader->trace_result('local_assistente_pedagogical_synthesis', (int) $wrong->id);

        foreach ($cases as $name => $call) {
            try {
                $call();
                $this->fail("No error for: {$name}");
            } catch (lineage_exception) {
                $this->assertTrue(true);
            }
        }
        $this->assertNotNull($event);
    }

    /**
     * An analysis of a type that is not known cannot have its history read, and the history of a synthesis analysis needs its synthesis.
     */
    public function test_a_history_that_cannot_be_read_is_an_error(): void {
        [, $p] = $this->started(event_type::SYNTHESIS_REQUESTED, ['section_id' => 1]);
        $unknown = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => $p->id, 'analysis_type' => 'mystery']);
        [, $p2] = $this->started(event_type::SYNTHESIS_REQUESTED, ['section_id' => 2]);
        $headless = $this->generator->create_analysis(['course_id' => $this->course->id, 'processing_id' => $p2->id, 'analysis_type' => 'section_synthesis']);

        foreach ([$unknown, $headless] as $analysis) {
            try {
                $this->reader->history((int) $analysis->id);
                $this->fail('The history was read');
            } catch (lineage_exception) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * An analysis whose student was erased (userid 0, DEC-57) has only itself as history: it never gathers the analyses of other erased
     * students, and the history of a student who was not erased is not touched.
     */
    public function test_the_history_of_an_erased_student_does_not_mix_students(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $third = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $repository = new performance_repository();
        $make = function (\stdClass $user, int $now) use ($repository) {
            [$event, $processing] = $this->started(event_type::GRADE_CHANGED, ['userid' => (int) $user->id, 'itemid' => 1, 'occurred_at' => $now]);
            return [(int) $event->id, $repository->save((int) $this->course->id, (int) $processing->id, (int) $user->id, [], null, $now)];
        };
        [$e1, $a1] = $make($this->student, 1000);
        [$e2, $a2] = $make($other, 1001);
        [$e3, $a3] = $make($third, 1002);
        [$e4, $a4] = $make($third, 1003);
        foreach ([$e1, $e2] as $eventid) {
            $payload = json_decode($DB->get_field('local_assistente_event', 'payload', ['id' => $eventid]), true);
            $payload['userid'] = 0;
            $payload['identity'] = str_repeat('0', 40);
            $DB->set_field('local_assistente_event', 'payload', json_encode($payload), ['id' => $eventid]);
        }

        $ids = fn(int $analysis) => array_map(fn($a) => (int) $a->id, $this->reader->history($analysis));

        $this->assertSame([$a1], $ids($a1));
        $this->assertSame([$a2], $ids($a2), 'The second erased student does not gather the first');
        $this->assertSame([$a4, $a3], $ids($a4), 'A student who was not erased keeps the history');
        $lineage = $this->reader->trace_analysis($a2);
        $this->assertNotNull($lineage);
        $this->assertSame([['type' => 'grade_item', 'id' => 1]], $lineage->sources, 'No user among the sources');
    }
}
