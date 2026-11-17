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

namespace local_assistente\application\context;

use local_assistente\application\material\material_service;
use local_assistente\domain\material\material_availability;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\duplicate_record_exception;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\material_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the context snapshot builder (T064; quickstart 008 Q3, Q4, Q6, Q7; DEC-26, DEC-50, DEC-51).
 *
 * The LLM is always the simulated adapter (DEC-48). Written against the Moodle test framework; executed only in a Moodle 5.2
 * with PHPUnit.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(context_snapshot_builder::class)]
final class context_snapshot_builder_test extends \advanced_testcase {
    /** @var material_repository */
    private material_repository $repository;

    /** @var material_service */
    private material_service $service;

    /** @var context_snapshot_builder */
    private context_snapshot_builder $builder;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new material_repository();
        $this->service = new material_service($this->repository, null, new fake_adapter());
        $this->builder = new context_snapshot_builder($this->repository);
    }

    /**
     * A course with two students.
     *
     * @return \stdClass course, s1, s2
     */
    private function course(): \stdClass {
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $s1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $s2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        return (object) ['course' => $course, 'ids' => [(int) $s1->id, (int) $s2->id], 's1' => $s1, 's2' => $s2];
    }

    /**
     * A processed page in a section.
     *
     * @param \stdClass $c the course
     * @param int $section section number
     * @param string $name
     * @param string $content
     * @return int cmid
     */
    private function page(\stdClass $c, int $section, string $name, string $content = '<p>Texto</p>'): int {
        $page = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $c->course->id, 'name' => $name, 'content' => $content],
            ['section' => $section]
        );
        $this->assertTrue($this->service->process((int) $c->course->id, (int) $page->cmid)->success);
        return (int) $page->cmid;
    }

    /**
     * course_sections.id of a section number.
     *
     * @param \stdClass $c
     * @param int $number
     * @return int
     */
    private function section_id(\stdClass $c, int $number): int {
        global $DB;
        return (int) $DB->get_field('course_sections', 'id', ['course' => $c->course->id, 'section' => $number], MUST_EXIST);
    }

    /**
     * Restrict a module to a group, so that only its members get it.
     *
     * @param \stdClass $c
     * @param int $cmid
     * @param \stdClass $member the only student that keeps access
     */
    private function restrict_to(\stdClass $c, int $cmid, \stdClass $member): void {
        global $DB;
        $group = $this->getDataGenerator()->create_group(['courseid' => $c->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&', 'showc' => [false], 'c' => [['type' => 'group', 'id' => (int) $group->id]],
        ]), ['id' => $cmid]);
        rebuild_course_cache((int) $c->course->id, true);
    }

    /**
     * Item of a material in a snapshot.
     *
     * @param array $snapshot
     * @param int $cmid
     * @return array
     */
    private function item(array $snapshot, int $cmid): array {
        foreach ($snapshot['materials'] as $item) {
            if ($item['cmid'] === $cmid) {
                return $item;
            }
        }
        $this->fail("Material {$cmid} is not in the snapshot");
    }

    /**
     * Q4: only the materials of the section of the analysis.
     */
    public function test_only_materials_of_the_same_section(): void {
        $c = $this->course();
        $in = $this->page($c, 1, 'Aula 1');
        $out = $this->page($c, 2, 'Aula 2');

        $snapshot = $this->builder->build((int) $c->course->id, $this->section_id($c, 1), $c->ids);

        $this->assertSame([$in], array_column($snapshot['materials'], 'cmid'));
        $this->assertNotContains($out, array_column($snapshot['materials'], 'cmid'));
    }

    /**
     * Q9: after a move the next snapshot uses the real section of the module, although `material.section_id` is stale (Moodle 5.2
     * emits no event on a move), and the move creates no version. A snapshot already saved is not changed.
     */
    public function test_moved_material_follows_the_real_section(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Aula');
        $a = $this->section_id($c, 1);
        $b = $this->section_id($c, 2);
        $material = $this->repository->find_by_cmid((int) $c->course->id, $cmid);
        $processingid = $this->processing((int) $c->course->id);
        $events = new event_repository();
        $this->builder->build_and_save($processingid, (int) $c->course->id, $a, $c->ids, $events, 100);
        $saved = $events->find_context($processingid)->context_json;
        $this->assertSame([$cmid], array_column(json_decode($saved, true)['materials'], 'cmid'));

        (new \core_courseformat\local\cmactions(get_course((int) $c->course->id)))->move_end_section($cmid, $b);

        $this->assertSame($a, (int) $this->repository->find((int) $material->id)->section_id, 'The stored section is stale');
        $inb = $this->builder->build((int) $c->course->id, $b, $c->ids);
        $ina = $this->builder->build((int) $c->course->id, $a, $c->ids);
        $this->assertSame([$cmid], array_column($inb['materials'], 'cmid'));
        $this->assertTrue($this->item($inb, $cmid)['eligible']);
        $this->assertSame([], $ina['materials']);
        $this->assertCount(1, $this->repository->find_versions((int) $material->id), 'A move creates no version');
        $this->assertSame($a, (int) $this->repository->find((int) $material->id)->section_id, 'The builder writes nothing');
        $this->assertSame($saved, $events->find_context($processingid)->context_json, 'The saved snapshot is not changed');
    }

    /**
     * A removed module has no section: it is not listed.
     */
    public function test_removed_module_is_not_listed(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Aula');
        $this->setAdminUser();
        (new \core_courseformat\local\cmactions(get_course((int) $c->course->id)))->delete($cmid);

        $snapshot = $this->builder->build((int) $c->course->id, $this->section_id($c, 1), $c->ids);

        $this->assertSame([], $snapshot['materials']);
    }

    /**
     * Q6: the snapshot lists the materials, the versions used, the evaluation and the generation language.
     */
    public function test_snapshot_lists_materials_versions_and_language(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Aula 1');
        $material = $this->repository->find_by_cmid((int) $c->course->id, $cmid);
        $version = $this->repository->find_current_version($material);

        $snapshot = $this->builder->build((int) $c->course->id, $this->section_id($c, 1), $c->ids, 1000);

        $this->assertSame(context_snapshot_builder::FORMAT, $snapshot['format']);
        $this->assertSame(1000, $snapshot['created_at']);
        $this->assertSame(2, $snapshot['students_evaluated']);
        $this->assertNotSame('', $snapshot['language']);
        $item = $this->item($snapshot, $cmid);
        $this->assertTrue($item['eligible']);
        $this->assertNull($item['reason']);
        $this->assertSame((int) $version->id, $item['version_id']);
        $this->assertSame($version->summary, $item['summary']);
        $this->assertSame([(int) $version->id], $snapshot['used_versions']);
        $this->assertSame(['coverage' => 'all', 'available_students' => 2, 'total_students' => 2], $item['availability']);
    }

    /**
     * Q3: a material unavailable to the students stays out of the context.
     */
    public function test_unavailable_material_is_left_out(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Oculto');
        set_coursemodule_visible($cmid, 0);

        $snapshot = $this->builder->build((int) $c->course->id, $this->section_id($c, 1), $c->ids);

        $item = $this->item($snapshot, $cmid);
        $this->assertFalse($item['eligible']);
        $this->assertSame('not_available_to_all', $item['reason']);
        $this->assertSame('none', $item['availability']['coverage']);
        $this->assertArrayNotHasKey('summary', $item);
        $this->assertSame([], $snapshot['used_versions']);
    }

    /**
     * Q7: a material available to part of the students is not treated as universal: it is summarised but out of the context.
     */
    public function test_partially_available_material_is_not_universal(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Parcial');
        $this->restrict_to($c, $cmid, $c->s1);

        $snapshot = $this->builder->build((int) $c->course->id, $this->section_id($c, 1), $c->ids);

        $item = $this->item($snapshot, $cmid);
        $this->assertSame('partial', $item['availability']['coverage']);
        $this->assertSame(1, $item['availability']['available_students']);
        $this->assertFalse($item['eligible']);
        $this->assertSame('not_available_to_all', $item['reason']);
        $material = $this->repository->find_by_cmid((int) $c->course->id, $cmid);
        $this->assertNotNull($this->repository->find_current_version($material)->summary, 'It is still summarised');
        $this->assertSame([], $snapshot['used_versions']);
    }

    /**
     * The same material is eligible for a group of students that all reach it, and not for a larger group (per student and
     * context evaluation).
     */
    public function test_availability_is_evaluated_for_the_students_of_the_analysis(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Restrito');
        $this->restrict_to($c, $cmid, $c->s1);
        $section = $this->section_id($c, 1);

        $only = $this->builder->build((int) $c->course->id, $section, [$c->ids[0]]);
        $both = $this->builder->build((int) $c->course->id, $section, $c->ids);

        $this->assertTrue($this->item($only, $cmid)['eligible']);
        $this->assertFalse($this->item($both, $cmid)['eligible']);
    }

    /**
     * A material without a processed version is out, whatever its availability; the pending summary is not availability.
     */
    public function test_material_without_a_completed_version_is_out(): void {
        $c = $this->course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $c->course->id], ['section' => 1]);
        $materialid = $this->repository->register(
            (int) $c->course->id,
            $this->section_id($c, 1),
            (int) $page->cmid,
            \local_assistente\domain\material_type::TEXT,
            'Sem versao',
            material_availability::AVAILABLE
        );
        $this->repository->add_version($materialid, str_repeat('a', 64), material_availability::AVAILABLE);

        $snapshot = $this->builder->build((int) $c->course->id, $this->section_id($c, 1), $c->ids);

        $item = $this->item($snapshot, (int) $page->cmid);
        $this->assertSame('no_current_version', $item['reason']);
        $this->assertSame('all', $item['availability']['coverage'], 'Available to all, yet out: summary is another dimension');
    }

    /**
     * The persisted availability fields are neither read nor changed by the snapshot.
     */
    public function test_persisted_availability_does_not_decide_nor_change(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Aula');
        $material = $this->repository->find_by_cmid((int) $c->course->id, $cmid);
        $this->repository->update_availability((int) $material->id, material_availability::UNAVAILABLE);
        $versionbefore = $this->repository->find_current_version($this->repository->find((int) $material->id));

        $snapshot = $this->builder->build((int) $c->course->id, $this->section_id($c, 1), $c->ids);

        $this->assertTrue($this->item($snapshot, $cmid)['eligible'], 'Evaluated now, not taken from material.availability');
        $after = $this->repository->find((int) $material->id);
        $this->assertSame('unavailable', $after->availability);
        $this->assertEquals($versionbefore, $this->repository->find_current_version($after));
    }

    /**
     * Historical availability: the material changes, each version keeps what it recorded.
     */
    public function test_material_availability_changes_without_altering_version_history(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Aula', '<p>v1</p>');
        $material = $this->repository->find_by_cmid((int) $c->course->id, $cmid);
        $v1 = $this->repository->find_current_version($material);
        $this->assertSame('available', $v1->availability, 'The version recorded what was observed when it was created (T058)');

        $this->repository->update_availability((int) $material->id, material_availability::UNAVAILABLE);
        $this->assertSame('available', $this->repository->find_version((int) $material->id, 1)->availability);
        $v2id = $this->repository->add_version((int) $material->id, str_repeat('b', 64), material_availability::PENDING);
        $this->repository->update_availability((int) $material->id, material_availability::AVAILABLE);

        $this->assertSame('available', $this->repository->find((int) $material->id)->availability);
        $this->assertSame('pending', $this->repository->find_version_by_id($v2id)->availability);
        $this->assertSame('available', $this->repository->find_version((int) $material->id, 1)->availability);
    }

    /**
     * A snapshot already built is not changed when the availability changes later.
     */
    public function test_saved_snapshot_is_not_replaced_by_later_changes(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Aula');
        $processingid = $this->processing((int) $c->course->id);
        $events = new event_repository();
        $section = $this->section_id($c, 1);

        $this->builder->build_and_save($processingid, (int) $c->course->id, $section, $c->ids, $events, 500);
        $stored = $events->find_context($processingid)->context_json;
        $this->assertTrue($this->item(json_decode($stored, true), $cmid)['eligible']);

        set_coursemodule_visible($cmid, 0);
        $material = $this->repository->find_by_cmid((int) $c->course->id, $cmid);
        $this->repository->update_availability((int) $material->id, material_availability::UNAVAILABLE);
        $later = $this->builder->build((int) $c->course->id, $section, $c->ids);

        $this->assertFalse($this->item($later, $cmid)['eligible'], 'A new evaluation sees the change');
        $this->assertSame($stored, $events->find_context($processingid)->context_json, 'The stored snapshot does not');
        $this->expectException(duplicate_record_exception::class);
        $this->builder->build_and_save($processingid, (int) $c->course->id, $section, $c->ids, $events, 600);
    }

    /**
     * Without relevant students nothing is eligible (conservative reading; the specification is silent).
     */
    public function test_no_students_means_nothing_eligible(): void {
        $c = $this->course();
        $cmid = $this->page($c, 1, 'Aula');

        $snapshot = $this->builder->build((int) $c->course->id, $this->section_id($c, 1), []);

        $this->assertSame('no_students', $this->item($snapshot, $cmid)['reason']);
        $this->assertSame([], $snapshot['used_versions']);
    }

    /**
     * Create a processing to attach a snapshot to.
     *
     * @param int $courseid
     * @return int
     */
    private function processing(int $courseid): int {
        $events = new event_repository();
        $event = $events->create_event($courseid, \local_assistente\domain\event\event_type::QUIZ_PUBLISHED, ['cmid' => 1], 400);
        $events->begin_attempt((int) $event->id, 450);
        return (int) $events->find_processing((int) $event->id)->id;
    }
}
