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

namespace local_assistente\infrastructure\persistence;

use local_assistente\domain\event\event_type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the repository of the analysis of a forum (T081; spec-013 data-model; DEC-38).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(forum_analysis_repository::class)]
final class forum_analysis_repository_test extends \advanced_testcase {
    /** @var forum_analysis_repository */
    private forum_analysis_repository $repository;

    /** @var event_repository */
    private event_repository $events;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass forum with cmid */
    private \stdClass $forum;

    /** @var int[] discussions of the forum */
    private array $discussions;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback. A course with a forum of three discussions.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->repository = new forum_analysis_repository();
        $this->events = new event_repository();
        $this->course = $this->getDataGenerator()->create_course();
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $author = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->discussions = [];
        foreach (range(1, 3) as $i) {
            $this->discussions[] = (int) $generator->create_discussion([
                'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $author->id,
            ])->id;
        }
    }

    /**
     * An event of the forum and its processing.
     *
     * @return \stdClass the processing
     */
    private function processing(): \stdClass {
        static $post = 100;
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::FORUM_POST_CREATED,
            ['cmid' => (int) $this->forum->cmid, 'discussionid' => $this->discussions[0], 'postid' => ++$post],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());
        return $this->events->find_processing((int) $event->id);
    }

    /**
     * Difficulties for some discussions.
     *
     * @param array $byindex list of [discussion index, description]
     * @return array
     */
    private function difficulties(array $byindex): array {
        return array_map(fn($d) => ['discussion_id' => $this->discussions[$d[0]], 'description' => $d[1]], $byindex);
    }

    /**
     * Normal case: the snapshot is written and read back, with several difficulties for one discussion and none for another.
     */
    public function test_saves_and_reads_the_snapshot(): void {
        $processing = $this->processing();

        $id = $this->repository->save(
            (int) $this->course->id,
            (int) $this->forum->cmid,
            (int) $processing->id,
            'Resumo do forum',
            $this->difficulties([[0, 'Confusao entre A e B'], [0, 'Falta de exemplo'], [2, 'Duvida sobre C']]),
            1000
        );

        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame($id, (int) $analysis->id);
        $this->assertSame('forum_analysis', $analysis->analysis_type);
        $this->assertSame((int) $this->forum->cmid, (int) $analysis->cmid);
        $this->assertSame('Resumo do forum', $analysis->summary);
        $this->assertSame(1000, (int) $analysis->created_at);
        $rows = array_values($this->repository->find_difficulties($id));
        $this->assertSame(
            [[$this->discussions[0], 'Confusao entre A e B'], [$this->discussions[0], 'Falta de exemplo'], [$this->discussions[2], 'Duvida sobre C']],
            array_map(static fn($r) => [(int) $r->discussion_id, $r->description], $rows),
            '0..N for each discussion; none for the second one'
        );
        $this->assertCount(2, $this->repository->find_difficulties($id, $this->discussions[0]));
        $this->assertSame([], $this->repository->find_difficulties($id, $this->discussions[1]));
    }

    /**
     * A snapshot with a summary and no difficulty is valid.
     */
    public function test_snapshot_without_difficulties_is_valid(): void {
        $processing = $this->processing();

        $id = $this->repository->save((int) $this->course->id, (int) $this->forum->cmid, (int) $processing->id, 'So o resumo', []);

        $this->assertSame([], $this->repository->find_difficulties($id));
        $this->assertSame('So o resumo', $this->repository->find_by_processing((int) $processing->id)->summary);
    }

    /**
     * Each execution is a new immutable snapshot: the current one is the latest, the history is kept, and the difficulties removed from
     * the current snapshot stay in the old ones.
     */
    public function test_current_is_the_latest_and_removed_difficulties_stay_in_the_old_snapshot(): void {
        $courseid = (int) $this->course->id;
        $cmid = (int) $this->forum->cmid;
        $first = $this->repository->save($courseid, $cmid, (int) $this->processing()->id, 'v1', $this->difficulties([[0, 'Duvida']]), 100);
        $second = $this->repository->save($courseid, $cmid, (int) $this->processing()->id, 'v2', [], 200);

        $this->assertSame($second, (int) $this->repository->find_current($courseid, $cmid)->id);
        $this->assertSame([$second, $first], array_map(
            static fn($a) => (int) $a->id,
            array_values($this->repository->find_history($courseid, $cmid))
        ));
        $this->assertCount(1, $this->repository->find_difficulties($first), 'The old snapshot keeps its difficulty');
        $this->assertSame([], $this->repository->find_difficulties($second), 'The current one no longer has it');
    }

    /**
     * Idempotency: a second save for the same processing is rejected and the first is untouched.
     */
    public function test_second_save_for_the_same_processing_is_rejected(): void {
        global $DB;
        $processing = $this->processing();
        $first = $this->repository->save((int) $this->course->id, (int) $this->forum->cmid, (int) $processing->id, 'v1', $this->difficulties([[0, 'Duvida']]));

        try {
            $this->repository->save((int) $this->course->id, (int) $this->forum->cmid, (int) $processing->id, 'v2', []);
            $this->fail('A second snapshot was saved');
        } catch (duplicate_record_exception) {
            $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'forum_analysis']));
            $this->assertCount(1, $this->repository->find_difficulties($first));
        }
    }

    /**
     * Invalid values and references write nothing.
     *
     * @param \Closure $build builds the difficulties from the test case
     */
    #[DataProvider('invalid_inputs')]
    public function test_invalid_input_writes_nothing(\Closure $build): void {
        global $DB;
        $processing = $this->processing();

        try {
            $this->repository->save((int) $this->course->id, (int) $this->forum->cmid, (int) $processing->id, 'Resumo', $build($this));
            $this->fail('The invalid snapshot was saved');
        } catch (invalid_domain_value_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'forum_analysis']));
            $this->assertSame(0, $DB->count_records('local_assistente_forum_difficulty'));
        }
    }

    /**
     * Invalid inputs.
     *
     * @return array
     */
    public static function invalid_inputs(): array {
        return [
            'empty description' => [fn($t) => $t->difficulties([[0, 'Boa'], [1, '   ']])],
            'description missing' => [fn($t) => [['discussion_id' => $t->discussions[0]]]],
            'discussion id zero' => [fn($t) => [['discussion_id' => 0, 'description' => 'x']]],
            'discussion that does not exist' => [fn($t) => [['discussion_id' => 987654, 'description' => 'x']]],
        ];
    }

    /**
     * A discussion of another forum, and a module that is not a forum, are refused.
     */
    public function test_discussion_of_another_forum_and_a_module_that_is_not_a_forum_are_refused(): void {
        $other = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $foreign = (int) $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $other->id, 'userid' => get_admin()->id,
        ])->id;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);

        foreach ([
            [(int) $this->forum->cmid, [['discussion_id' => $foreign, 'description' => 'x']]],
            [(int) $page->cmid, []],
        ] as [$cmid, $rows]) {
            try {
                $this->repository->save((int) $this->course->id, $cmid, (int) $this->processing()->id, 'r', $rows);
                $this->fail('A reference outside the forum was accepted');
            } catch (invalid_domain_value_exception) {
                $this->assertNull($this->repository->find_current((int) $this->course->id, $cmid));
            }
        }
    }

    /**
     * A failure in the middle of the writes rolls everything back: a database trigger makes the database refuse the second difficulty.
     */
    public function test_failure_midway_rolls_back_everything(): void {
        global $DB;
        $processing = $this->processing();
        $table = $DB->get_prefix() . 'local_assistente_forum_difficulty';
        try {
            // One statement only (Moodle refuses several): the row of the second discussion gets NULL in a NOT NULL column.
            $DB->execute("CREATE TRIGGER t081_refuse BEFORE INSERT ON {$table} FOR EACH ROW
                SET NEW.description = IF(NEW.discussion_id = {$this->discussions[1]}, NULL, NEW.description)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }

        try {
            $this->repository->save((int) $this->course->id, (int) $this->forum->cmid, (int) $processing->id, 'r', $this->difficulties([[0, 'a'], [1, 'b']]));
            $saved = true;
        } catch (\dml_exception) {
            $saved = false;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t081_refuse');
        }

        $this->assertFalse($saved, 'The trigger must have refused the second difficulty');
        $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'forum_analysis']));
        $this->assertSame(0, $DB->count_records('local_assistente_forum_difficulty'));
    }

    /**
     * A processing that does not exist is a missing record, and nothing is written.
     */
    public function test_unknown_processing_is_rejected(): void {
        $this->expectException(\dml_missing_record_exception::class);

        $this->repository->save((int) $this->course->id, (int) $this->forum->cmid, 987654, 'r', []);
    }

    /**
     * Missing records read as null or empty; another course and another forum see nothing.
     */
    public function test_missing_records_read_as_null_or_empty(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->repository->save((int) $this->course->id, (int) $this->forum->cmid, (int) $this->processing()->id, 'r', []);

        $this->assertNull($this->repository->find_by_processing(424242));
        $this->assertSame([], $this->repository->find_difficulties(424242));
        $this->assertNull($this->repository->find_current((int) $other->id, (int) $this->forum->cmid));
        $this->assertNull($this->repository->find_current((int) $this->course->id, 987654));
    }
}
