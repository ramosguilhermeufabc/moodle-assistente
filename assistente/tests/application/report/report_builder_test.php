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

namespace local_assistente\application\report;

use local_assistente\domain\report\report_schedule;
use local_assistente\domain\result_state;
use local_assistente\infrastructure\persistence\report_repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the builder of the report manifest (T114; quickstart 019 Q9 and quickstart 017 Q5; DEC-44 D1).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_builder::class)]
final class report_builder_test extends \advanced_testcase {
    /** @var report_builder */
    private report_builder $builder;

    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->builder = new report_builder();
    }

    /**
     * Every block with no data, then the given blocks replace theirs.
     *
     * @param array $given blocks by name
     * @return array
     */
    private function blocks(array $given = []): array {
        $blocks = array_fill_keys(report_builder::BLOCKS, ['state' => result_state::NO_DATA]);
        return $given + $blocks;
    }

    /**
     * Q9: the manifest has the type, the moment and one entry per block with its state and only references; no history block.
     */
    public function test_manifest_has_type_moment_blocks_and_references_only(): void {
        $json = $this->builder->build(report_schedule::TYPE_PERIODIC, 1790000000, $this->blocks([
            report_builder::BLOCK_GRADES => ['state' => result_state::VALID, 'analysis_ids' => [30, 10, 20]],
            report_builder::BLOCK_MATERIAL_SUMMARIES => ['state' => 'valid', 'material_versions' => [7]],
            report_builder::BLOCK_SECTION_SYNTHESIS => ['state' => 'valid', 'synthesis_versions' => [12 => 3, 11 => 1]],
            report_builder::BLOCK_FORUMS => ['state' => result_state::ERROR],
        ]));

        $manifest = json_decode($json, true);
        $this->assertSame(['type', 'generated_at', 'blocks'], array_keys($manifest));
        $this->assertSame('periodic', $manifest['type']);
        $this->assertSame(1790000000, $manifest['generated_at']);
        $this->assertSame(report_builder::BLOCKS, array_column($manifest['blocks'], 'block'));
        $this->assertNotContains('history', array_column($manifest['blocks'], 'block'));
        $by = array_column($manifest['blocks'], null, 'block');
        $this->assertSame([10, 20, 30], $by['grades']['refs']['analysis_ids']);
        $this->assertSame('valid', $by['grades']['state']);
        $this->assertSame([7], $by['material_summaries']['refs']['material_versions']);
        $this->assertSame(
            [['section_id' => 11, 'version' => 1], ['section_id' => 12, 'version' => 3]],
            $by['section_synthesis']['refs']['synthesis_versions']
        );
        $this->assertSame('error', $by['forums']['state']);
        $this->assertSame('no_data', $by['frequency']['state']);
        $this->assertSame(['block', 'state', 'refs'], array_keys($by['grades']));
        $this->assertSame(['analysis_ids', 'material_versions', 'synthesis_versions'], array_keys($by['grades']['refs']));
    }

    /**
     * The same input always gives the same text, whatever the order of the blocks and of the ids it came in.
     */
    public function test_output_is_deterministic(): void {
        $one = $this->builder->build('final', 5, $this->blocks([
            report_builder::BLOCK_FREQUENCY => ['state' => 'valid', 'analysis_ids' => [3, 1, 2, 2]],
            report_builder::BLOCK_GRADES => ['state' => 'valid', 'analysis_ids' => ['9', 8]],
        ]));
        $two = $this->builder->build('final', 5, array_reverse($this->blocks([
            report_builder::BLOCK_GRADES => ['state' => 'valid', 'analysis_ids' => [8, 9]],
            report_builder::BLOCK_FREQUENCY => ['state' => 'valid', 'analysis_ids' => [1, 2, 3]],
        ]), true));

        $this->assertSame($one, $two);
        $this->assertSame('final', json_decode($one, true)['type']);
    }

    /**
     * Nothing but references can enter: a value of a result, a text or a field outside the reference lists is refused.
     *
     * @return array<string, array{array}>
     */
    public static function values_that_would_be_copied(): array {
        return [
            'grade as extra field' => [['state' => 'valid', 'analysis_ids' => [1], 'grade' => 87.5]],
            'student name' => [['state' => 'valid', 'analysis_ids' => [1], 'students' => ['Ana']]],
            'text as reference' => [['state' => 'valid', 'analysis_ids' => ['Ana']]],
            'float as reference' => [['state' => 'valid', 'analysis_ids' => [1.5]]],
            'negative id' => [['state' => 'valid', 'analysis_ids' => [-1]]],
            'zero id' => [['state' => 'valid', 'material_versions' => [0]]],
            'nested value' => [['state' => 'valid', 'analysis_ids' => [[1]]]],
            'references not a list' => [['state' => 'valid', 'analysis_ids' => 5]],
        ];
    }

    /**
     * @param array $block
     */
    #[DataProvider('values_that_would_be_copied')]
    public function test_no_value_can_be_copied(array $block): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->build('periodic', 1, $this->blocks([report_builder::BLOCK_GRADES => $block]));
    }

    /**
     * The history block does not exist, no block may be missing and the state must agree with the references.
     */
    public function test_structure_and_states_are_enforced(): void {
        $cases = [
            'history block' => fn() => $this->builder->build('periodic', 1, $this->blocks(['history' => ['state' => 'no_data']])),
            'missing block' => fn() => $this->builder->build('periodic', 1, array_slice($this->blocks(), 1)),
            'valid without reference' => fn() => $this->builder->build('periodic', 1, $this->blocks([
                report_builder::BLOCK_GRADES => ['state' => 'valid'],
            ])),
            'no_data with reference' => fn() => $this->builder->build('periodic', 1, $this->blocks([
                report_builder::BLOCK_GRADES => ['state' => 'no_data', 'analysis_ids' => [1]],
            ])),
            'unknown state' => fn() => $this->builder->build('periodic', 1, $this->blocks([
                report_builder::BLOCK_GRADES => ['state' => 'maybe'],
            ])),
            'block without state' => fn() => $this->builder->build('periodic', 1, $this->blocks([report_builder::BLOCK_GRADES => []])),
            'unknown type' => fn() => $this->builder->build('weekly', 1, $this->blocks()),
            'no moment' => fn() => $this->builder->build('periodic', 0, $this->blocks()),
        ];
        foreach ($cases as $label => $call) {
            try {
                $call();
                $this->fail("Must be refused: {$label}");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * Q9 / 017 Q5: from a stored report version the manifest leads to the analysis ids and to the versions of the syntheses.
     */
    public function test_a_stored_version_leads_to_its_analyses_and_syntheses(): void {
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_assistente');
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $analysis = $generator->create_analysis(['course_id' => $course->id, 'analysis_type' => 'grade_performance']);
        $synthesis = $generator->create_pedagogical_synthesis(['course_id' => $course->id]);
        $repository = new report_repository();
        $report = $repository->create((int) $course->id, report_schedule::calculate([], null, 'UTC', null, 0));

        $json = $this->builder->build(report_schedule::TYPE_PERIODIC, 1790000000, $this->blocks([
            report_builder::BLOCK_GRADES => ['state' => 'valid', 'analysis_ids' => [(int) $analysis->id]],
            report_builder::BLOCK_SECTION_SYNTHESIS => [
                'state' => 'valid', 'synthesis_versions' => [(int) $synthesis->section_id => (int) $synthesis->version],
            ],
        ]));
        $version = $repository->add_version((int) $report->id, $json, report_schedule::calculate([], null, 'UTC', null, 0), 1790000000);

        $read = $this->builder->read($repository->find_current_version((int) $report->id)->snapshot_json);

        $this->assertSame($version->snapshot_json, $json);
        $this->assertSame([(int) $analysis->id], $read['blocks']['grades']['analysis_ids']);
        $this->assertSame([(int) $synthesis->section_id => (int) $synthesis->version], $read['blocks']['section_synthesis']['synthesis_versions']);
        $this->assertSame('periodic', $read['type']);
        $this->assertSame(1790000000, $read['generated_at']);
        $this->assertSame('no_data', $read['blocks']['frequency']['state']);
    }

    /**
     * A text that is not a manifest is refused when read.
     */
    public function test_read_refuses_what_is_not_a_manifest(): void {
        foreach (['', '{oops', '{}', '{"type":"periodic","generated_at":1,"blocks":[]}'] as $text) {
            try {
                $this->builder->read($text);
                $this->fail("Must be refused: {$text}");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
