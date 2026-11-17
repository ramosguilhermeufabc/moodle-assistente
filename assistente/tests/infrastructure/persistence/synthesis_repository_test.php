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
use local_assistente\domain\synthesis\comparable_set;
use local_assistente\domain\synthesis\fingerprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the repository of the pedagogical synthesis (T096; quickstart 016 Q4, Q13 at persistence level; DEC-14, DEC-29, DEC-41).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(synthesis_repository::class)]
final class synthesis_repository_test extends \advanced_testcase {
    /** @var synthesis_repository */
    private synthesis_repository $repository;

    /** @var event_repository */
    private event_repository $events;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var int[] section ids of the course (1 and 2) */
    private array $sections;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback. A course with two sections.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new synthesis_repository();
        $this->events = new event_repository();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $this->sections = array_map('intval', array_values(
            $DB->get_fieldset_select('course_sections', 'id', 'course = ? AND section > 0', [$this->course->id], 'section')
        ));
    }

    /**
     * An event that asks for the synthesis of a section and its processing.
     *
     * @return \stdClass the processing
     */
    private function processing(): \stdClass {
        $event = $this->events->create_event(
            (int) $this->course->id,
            event_type::SYNTHESIS_REQUESTED,
            ['section_id' => $this->sections[0]],
            time()
        );
        $this->events->begin_attempt((int) $event->id, time());
        return $this->events->find_processing((int) $event->id);
    }

    /**
     * The fingerprint of a comparable set with a grade.
     *
     * @param float $grade
     * @return string
     */
    private function print(float $grade): string {
        return fingerprint::of(comparable_set::from(['activities' => [['activity_id' => 1, 'grades' => [$grade]]]]));
    }

    /**
     * Save a version.
     *
     * @param int $section index of the section
     * @param string $content
     * @param string|null $fingerprint
     * @param int|null $now
     * @return \stdClass
     */
    private function save(int $section, string $content = 'Síntese', ?string $fingerprint = null, ?int $now = null): \stdClass {
        return $this->repository->save(
            (int) $this->course->id,
            $this->sections[$section],
            (int) $this->processing()->id,
            $content,
            $fingerprint ?? $this->print(70.0),
            $now
        );
    }

    /**
     * Q4: the first version of a section is 1, and it is linked to the analysis of its processing.
     */
    public function test_the_first_version_is_one_and_is_linked_to_its_processing(): void {
        global $DB;
        $processing = $this->processing();
        $fingerprint = $this->print(70.0);

        $row = $this->repository->save((int) $this->course->id, $this->sections[0], (int) $processing->id, 'Texto da síntese', $fingerprint, 1000);

        $this->assertSame(1, (int) $row->version);
        $this->assertSame('Texto da síntese', $row->content);
        $this->assertSame($fingerprint, $row->input_fingerprint);
        $this->assertSame(1000, (int) $row->created_at);
        $this->assertSame([(int) $this->course->id, $this->sections[0]], [(int) $row->course_id, (int) $row->section_id]);
        $analysis = $this->repository->find_by_processing((int) $processing->id);
        $this->assertSame((int) $analysis->id, (int) $row->analysis_id);
        $this->assertSame('section_synthesis', $analysis->analysis_type);
        $this->assertNull($analysis->cmid);
        $this->assertSame((int) $row->id, (int) $this->repository->find_by_analysis((int) $analysis->id)->id);
        $this->assertSame(1, $DB->count_records('local_assistente_pedagogical_synthesis'));
    }

    /**
     * Each new version is the highest number plus one, the history is kept and the current one is the latest.
     */
    public function test_versions_are_numbered_in_sequence_and_the_history_is_kept(): void {
        $first = $this->save(0, 'Primeira', $this->print(10.0), 100);
        $second = $this->save(0, 'Segunda', $this->print(20.0), 200);
        $third = $this->save(0, 'Terceira', $this->print(30.0), 300);

        $this->assertSame([1, 2, 3], [(int) $first->version, (int) $second->version, (int) $third->version]);
        $current = $this->repository->find_current((int) $this->course->id, $this->sections[0]);
        $this->assertSame((int) $third->id, (int) $current->id);
        $history = $this->repository->find_history((int) $this->course->id, $this->sections[0]);
        $this->assertSame([3, 2, 1], array_map(fn($r) => (int) $r->version, array_values($history)));
        $this->assertSame('Primeira', $this->repository->find_version((int) $this->course->id, $this->sections[0], 1)->content);
        $this->assertSame($this->print(10.0), $this->repository->find_version((int) $this->course->id, $this->sections[0], 1)->input_fingerprint);
        $this->assertNull($this->repository->find_version((int) $this->course->id, $this->sections[0], 4));
    }

    /**
     * Q13: a version of one section never changes another section; each has its own numbering.
     */
    public function test_sections_are_independent(): void {
        $a1 = $this->save(0, 'A1');
        $b1 = $this->save(1, 'B1');
        $a2 = $this->save(0, 'A2');

        $this->assertSame([1, 1, 2], [(int) $a1->version, (int) $b1->version, (int) $a2->version]);
        $this->assertSame('B1', $this->repository->find_current((int) $this->course->id, $this->sections[1])->content);
        $this->assertCount(1, $this->repository->find_history((int) $this->course->id, $this->sections[1]));
        $this->assertCount(2, $this->repository->find_history((int) $this->course->id, $this->sections[0]));
    }

    /**
     * Another course sees nothing of this one, and a section without synthesis has no current version.
     */
    public function test_missing_records_read_as_null_or_empty(): void {
        $other = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $this->save(0);

        $this->assertNull($this->repository->find_current((int) $this->course->id, $this->sections[1]));
        $this->assertNull($this->repository->find_current((int) $other->id, $this->sections[0]));
        $this->assertSame([], $this->repository->find_history((int) $other->id, $this->sections[0]));
        $this->assertNull($this->repository->find_by_processing(424242));
        $this->assertNull($this->repository->find_by_analysis(424242));
    }

    /**
     * A second save for the same processing is rejected and leaves the first untouched.
     */
    public function test_second_save_for_the_same_processing_is_rejected(): void {
        global $DB;
        $processing = $this->processing();
        $this->repository->save((int) $this->course->id, $this->sections[0], (int) $processing->id, 'Primeira', $this->print(10.0));

        try {
            $this->repository->save((int) $this->course->id, $this->sections[0], (int) $processing->id, 'Outra', $this->print(20.0));
            $this->fail('A second version was saved for the same processing');
        } catch (duplicate_record_exception) {
            $this->assertSame(1, $DB->count_records('local_assistente_pedagogical_synthesis'));
            $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'section_synthesis']));
            $this->assertSame('Primeira', $this->repository->find_current((int) $this->course->id, $this->sections[0])->content);
        }
    }

    /**
     * Invalid values and references write nothing.
     *
     * @return array
     */
    public static function invalid_inputs(): array {
        $good = str_repeat('a', 64);
        return [
            'empty content' => [fn($t) => [$t->sections[0], '', $good]],
            'blank content' => [fn($t) => [$t->sections[0], "  \n ", $good]],
            'short fingerprint' => [fn($t) => [$t->sections[0], 'x', str_repeat('a', 63)]],
            'long fingerprint' => [fn($t) => [$t->sections[0], 'x', str_repeat('a', 65)]],
            'uppercase fingerprint' => [fn($t) => [$t->sections[0], 'x', str_repeat('A', 64)]],
            'not hexadecimal' => [fn($t) => [$t->sections[0], 'x', str_repeat('g', 64)]],
            'unknown section' => [fn($t) => [987654, 'x', $good]],
            'section zero' => [fn($t) => [0, 'x', $good]],
            'section of another course' => [fn($t) => [$t->other_section(), 'x', $good]],
        ];
    }

    /**
     * A section of a course that is not the course of the test.
     *
     * @return int
     */
    public function other_section(): int {
        global $DB;
        $other = $this->getDataGenerator()->create_course(['numsections' => 1]);
        return (int) $DB->get_field('course_sections', 'id', ['course' => $other->id, 'section' => 1]);
    }

    /**
     * Sections, contents and fingerprints that are not valid are refused and nothing is written.
     *
     * @param \Closure $build builds [section, content, fingerprint] from the test case
     */
    #[DataProvider('invalid_inputs')]
    public function test_invalid_input_writes_nothing(\Closure $build): void {
        global $DB;
        [$section, $content, $fingerprint] = $build($this);

        try {
            $this->repository->save((int) $this->course->id, $section, (int) $this->processing()->id, $content, $fingerprint);
            $this->fail('The invalid synthesis was saved');
        } catch (invalid_domain_value_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_pedagogical_synthesis'));
            $this->assertSame(0, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'section_synthesis']));
        }
    }

    /**
     * A course that does not exist is refused, and so is a processing that does not exist; nothing is written.
     */
    public function test_unknown_course_and_processing_are_refused(): void {
        global $DB;
        try {
            $this->repository->save(987654, $this->sections[0], (int) $this->processing()->id, 'x', $this->print(1.0));
            $this->fail('The course does not exist');
        } catch (invalid_domain_value_exception) {
            $this->assertSame(0, $DB->count_records('local_assistente_pedagogical_synthesis'));
        }
        $this->expectException(\dml_missing_record_exception::class);

        $this->repository->save((int) $this->course->id, $this->sections[0], 987654, 'x', $this->print(1.0));
    }

    /**
     * The unique index of (course, section, version) is the last guard: a number that is taken is refused by the database even if the
     * lock were skipped, and nothing of the attempt stays.
     */
    public function test_a_taken_number_is_refused_by_the_unique_index(): void {
        global $DB;
        $this->save(0, 'Primeira');
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $taken = $generator->create_pedagogical_synthesis([
            'course_id' => $this->course->id, 'section_id' => $this->sections[0], 'version' => 2,
        ]);
        $analyses = $DB->count_records('local_assistente_analysis', ['analysis_type' => 'section_synthesis']);

        // The next number would be 3, so the collision is made with a writer that skipped the lock: version 2 is inserted directly.
        $this->expectException(duplicate_record_exception::class);
        try {
            (new table_repository('local_assistente_pedagogical_synthesis'))->insert((object) [
                'analysis_id' => $taken->analysis_id, 'course_id' => $this->course->id, 'section_id' => $this->sections[0],
                'version' => 2, 'content' => 'x', 'input_fingerprint' => $this->print(1.0), 'created_at' => time(),
            ]);
        } finally {
            $this->assertSame($analyses, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'section_synthesis']));
        }
    }

    /**
     * A failure in the middle of the writes rolls everything back: a database trigger makes the database refuse the version after its
     * analysis was written, and the earlier version stays current.
     */
    public function test_failure_midway_rolls_back_everything(): void {
        global $DB;
        $this->save(0, 'Primeira', $this->print(10.0));
        $processing = $this->processing();
        $table = $DB->get_prefix() . 'local_assistente_pedagogical_synthesis';
        try {
            // One statement only (Moodle refuses several): a version above 1 gets NULL in a NOT NULL column.
            $DB->execute("CREATE TRIGGER t096_refuse BEFORE INSERT ON {$table} FOR EACH ROW
                SET NEW.content = IF(NEW.version > 1, NULL, NEW.content)");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }

        try {
            $this->repository->save((int) $this->course->id, $this->sections[0], (int) $processing->id, 'Segunda', $this->print(20.0));
            $saved = true;
        } catch (\dml_exception) {
            $saved = false;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t096_refuse');
        }

        $this->assertFalse($saved, 'The trigger must have refused the second version');
        $this->assertSame(1, $DB->count_records('local_assistente_pedagogical_synthesis'));
        $this->assertSame(1, $DB->count_records('local_assistente_analysis', ['analysis_type' => 'section_synthesis']));
        $this->assertNull($this->repository->find_by_processing((int) $processing->id), 'The analysis of the failed version was rolled back');
        $this->assertSame('Primeira', $this->repository->find_current((int) $this->course->id, $this->sections[0])->content);
        // The next attempt takes the number that was not used.
        $this->assertSame(2, (int) $this->save(0, 'Segunda')->version);
    }

    /**
     * Only the content, its fingerprint and the links are stored: no student, grade or criteria.
     */
    public function test_only_the_synthesis_is_stored(): void {
        global $DB;

        $this->assertSame(
            ['id', 'analysis_id', 'course_id', 'section_id', 'version', 'content', 'input_fingerprint', 'created_at'],
            array_keys($DB->get_columns('local_assistente_pedagogical_synthesis'))
        );
    }
}
