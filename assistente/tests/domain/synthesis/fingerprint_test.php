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


namespace local_assistente\domain\synthesis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the comparable set and of its fingerprint (T095; quickstart 016 Q5, Q6, Q7, Q9, Q11; DEC-41, DEC-19, DEC-34 D3).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(fingerprint::class)]
#[CoversClass(comparable_set::class)]
final class fingerprint_test extends \basic_testcase {
    /**
     * Facts of a section with two activities, frequency, a questionnaire, a forum and a material.
     *
     * @param array $overrides blocks to replace
     * @return array
     */
    private static function facts(array $overrides = []): array {
        return $overrides + [
            'activities' => [
                ['activity_id' => 11, 'grades' => [80.0, 60.0, null]],
                ['activity_id' => 12, 'grades' => [100.0, 50.0]],
            ],
            'frequency' => [50.0, 100.0, null],
            'questionnaires' => [[
                'cmid' => 21, 'coverage' => 0.5,
                'metrics' => ['mean' => 70.0, 'population_standard_deviation' => 10.0, 'minimum' => 50.0, 'maximum' => 90.0],
                'questions' => [
                    ['question_id' => 2, 'question_version' => 1, 'complexity' => 0.4, 'average_correctness' => 0.75,
                        'correct_count' => 3, 'incorrect_count' => 1, 'partial_result' => null],
                    ['question_id' => 1, 'question_version' => 1, 'complexity' => 0.2, 'average_correctness' => 0.5,
                        'correct_count' => 2, 'incorrect_count' => 2, 'partial_result' => 0.5],
                ],
            ]],
            'forums' => [['cmid' => 31, 'summary' => 'Resumo do fórum', 'difficulties' => [
                ['discussion_id' => 5, 'description' => 'Dúvida B'], ['discussion_id' => 4, 'description' => 'Dúvida A'],
            ]]],
            'materials' => [['material_id' => 41, 'summary' => 'Resumo do material']],
        ];
    }

    /**
     * The fingerprint of some facts.
     *
     * @param array $facts
     * @return string
     */
    private static function print(array $facts): string {
        return fingerprint::of(comparable_set::from($facts));
    }

    /**
     * Q5: the same values give the same fingerprint, which is 64 hexadecimal characters.
     */
    public function test_the_same_values_give_the_same_fingerprint(): void {
        $first = self::print(self::facts());
        $second = self::print(self::facts());

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
    }

    /**
     * Q9: the order of the entities and of the students, and timestamps and identifiers of processing or analysis that come along, do not
     * change the fingerprint.
     */
    public function test_order_and_transient_metadata_do_not_change_the_fingerprint(): void {
        $base = self::print(self::facts());
        $shuffled = self::facts([
            'activities' => [
                ['activity_id' => 12, 'grades' => [50.0, 100.0], 'created_at' => 111, 'analysis_id' => 9, 'processing_id' => 3],
                ['activity_id' => 11, 'grades' => [null, 60.0, 80.0], 'timecreated' => 222, 'llm_model' => 'x'],
            ],
            'frequency' => [null, 100.0, 50.0],
            'materials' => [['material_id' => 41, 'summary' => 'Resumo do material', 'created_at' => 999, 'version' => 7]],
            'forums' => [['cmid' => 31, 'summary' => 'Resumo do fórum', 'analysis_id' => 5, 'created_at' => 1, 'difficulties' => [
                ['discussion_id' => 4, 'description' => 'Dúvida A', 'created_at' => 5], ['discussion_id' => 5, 'description' => 'Dúvida B'],
            ]]],
        ]);
        $questions = array_reverse(self::facts()['questionnaires'][0]['questions']);
        $shuffled['questionnaires'] = [['cmid' => 21, 'coverage' => 0.5, 'processing_id' => 4, 'questions' => $questions,
            'metrics' => ['maximum' => 90.0, 'minimum' => 50.0, 'population_standard_deviation' => 10.0, 'mean' => 70.0]]];

        $this->assertSame($base, self::print($shuffled));
    }

    /**
     * What the set holds is what the fingerprint depends on: a new value, a new text or a new entity gives another fingerprint.
     *
     * @return array
     */
    public static function changes(): array {
        return [
            'a grade' => [['activities' => [['activity_id' => 11, 'grades' => [80.0, 61.0, null]], ['activity_id' => 12, 'grades' => [100.0, 50.0]]]]],
            'a student without result gets one' => [['frequency' => [50.0, 100.0, 10.0]]],
            'a new post changes the summary of the forum (Q8)' => [['forums' => [['cmid' => 31, 'summary' => 'Resumo novo', 'difficulties' => []]]]],
            'a new material' => [['materials' => [['material_id' => 41, 'summary' => 'Resumo do material'], ['material_id' => 42, 'summary' => 'Outro']]]],
            'the coverage' => [['questionnaires' => [['cmid' => 21, 'coverage' => 0.6]]]],
            'a text differs by one space' => [['materials' => [['material_id' => 41, 'summary' => 'Resumo do material ']]]],
        ];
    }

    #[DataProvider('changes')]
    public function test_a_change_in_the_information_changes_the_fingerprint(array $change): void {
        $this->assertNotSame(self::print(self::facts()), self::print(self::facts($change)));
    }

    /**
     * Numbers are compared truncated to three decimals: values that differ after the third decimal are the same information.
     */
    public function test_numbers_are_truncated_to_three_decimals(): void {
        $a = self::print(self::facts(['frequency' => [66.6666, 33.3339]]));
        $b = self::print(self::facts(['frequency' => [66.6669, 33.3331]]));
        $c = self::print(self::facts(['frequency' => [66.667, 33.333]]));

        $this->assertSame($a, $b, '66.6666 and 66.6669 are both 66.666');
        $this->assertNotSame($a, $c, '66.667 is another value');
        $this->assertStringContainsString('"mean":"50.000"', fingerprint::canonical_json(comparable_set::from(self::facts(['frequency' => [50.0]]))->to_array()));
    }

    /**
     * The canonical JSON has sorted keys, numbers with a fraction as text, integers as integers and texts as stored.
     */
    public function test_the_canonical_json(): void {
        $json = fingerprint::canonical_json(['b' => 1.5, 'a' => ['z' => 2, 'y' => 'Olá / ç'], 'c' => [0.1234, 3]]);

        $this->assertSame('{"a":{"y":"Olá / ç","z":2},"b":"1.500","c":["0.123",3]}', $json);
        $this->assertSame(fingerprint::canonical_json(['a' => 1, 'b' => 2]), fingerprint::canonical_json(['b' => 2, 'a' => 1]));
    }

    /**
     * Q7: a section with two activities has statistics for each activity, and no single index of the section.
     */
    public function test_each_activity_has_its_statistics_and_the_section_has_no_single_index(): void {
        $set = comparable_set::from(self::facts())->to_array();

        $this->assertCount(2, $set['activities']);
        $this->assertSame(
            ['activity_id' => 11, 'maximum' => 80.0, 'mean' => 70.0, 'minimum' => 60.0, 'no_data' => 1, 'state' => 'valid', 'valid' => 2],
            $set['activities'][0]
        );
        $this->assertSame(
            ['activity_id' => 12, 'maximum' => 100.0, 'mean' => 75.0, 'minimum' => 50.0, 'no_data' => 0, 'state' => 'valid', 'valid' => 2],
            $set['activities'][1]
        );
        $this->assertSame(['activities', 'forums', 'frequency', 'materials', 'questionnaires'], self::sorted_keys($set));
        $flat = json_encode($set);
        $this->assertStringNotContainsString('section_index', $flat);
        $this->assertStringNotContainsString('overall', $flat);
    }

    /**
     * The keys of a set, sorted.
     *
     * @param array $set
     * @return string[]
     */
    private static function sorted_keys(array $set): array {
        $keys = array_keys($set);
        sort($keys);
        return $keys;
    }

    /**
     * The frequency has the same statistics; the mean is truncated, not rounded.
     */
    public function test_the_frequency_statistics_are_truncated(): void {
        $set = comparable_set::from(['frequency' => [100.0, 100.0, 0.0]])->to_array();

        $this->assertSame(66.666, $set['frequency']['mean']);
        $this->assertSame([100.0, 0.0, 3, 0], [$set['frequency']['maximum'], $set['frequency']['minimum'], $set['frequency']['valid'], $set['frequency']['no_data']]);
    }

    /**
     * A block with no result is `no_data`; a statistic without a valid value is `no_data` with null numbers, and a zero is a value.
     */
    public function test_absences_are_no_data_and_never_zero(): void {
        $empty = comparable_set::from([])->to_array();
        $nogrades = comparable_set::from(['activities' => [['activity_id' => 11, 'grades' => [null, null]]], 'frequency' => [null]])->to_array();
        $zero = comparable_set::from(['frequency' => [0.0]])->to_array();

        $this->assertSame('no_data', $empty['activities']);
        $this->assertSame('no_data', $empty['questionnaires']);
        $this->assertSame('no_data', $empty['forums']);
        $this->assertSame('no_data', $empty['materials']);
        $this->assertSame('no_data', $empty['frequency']['state']);
        $this->assertNull($empty['frequency']['mean']);
        $this->assertSame(
            ['activity_id' => 11, 'maximum' => null, 'mean' => null, 'minimum' => null, 'no_data' => 2, 'state' => 'no_data', 'valid' => 0],
            $nogrades['activities'][0]
        );
        $this->assertSame(['valid', 0.0], [$zero['frequency']['state'], $zero['frequency']['mean']], 'A real zero is a value');
        $this->assertNotSame(self::print(['frequency' => [null]]), self::print(['frequency' => [0.0]]));
    }

    /**
     * Q11: a section with only materials has no pedagogical result; with a grade it has, and the frequency is `no_data`.
     */
    public function test_materials_alone_are_not_a_result(): void {
        $materials = ['materials' => [['material_id' => 41, 'summary' => 'Resumo']]];

        $this->assertFalse(comparable_set::from($materials)->has_pedagogical_result());
        $this->assertFalse(comparable_set::from([])->has_pedagogical_result());
        $this->assertFalse(comparable_set::from(['activities' => [['activity_id' => 1, 'grades' => [null]]], 'frequency' => [null]] + $materials)->has_pedagogical_result());
        $withgrade = comparable_set::from($materials + ['activities' => [['activity_id' => 1, 'grades' => [70.0]]]]);
        $this->assertTrue($withgrade->has_pedagogical_result());
        $this->assertSame('no_data', $withgrade->to_array()['frequency']['state']);
        $this->assertTrue(comparable_set::from(['frequency' => [0.0]])->has_pedagogical_result());
        $this->assertTrue(comparable_set::from(['forums' => [['cmid' => 3, 'summary' => 'x']]])->has_pedagogical_result());
        $this->assertTrue(comparable_set::from(['questionnaires' => [['cmid' => 3, 'coverage' => 0.0]]])->has_pedagogical_result());
        $this->assertFalse(comparable_set::from(['forums' => [['cmid' => 3, 'summary' => null]]])->has_pedagogical_result());
    }

    /**
     * Q6: the set carries only aggregates and texts already produced: no individual grade, no identifier or name of a student.
     */
    public function test_nothing_individual_leaves_the_set(): void {
        $facts = self::facts();
        $facts['activities'][0]['userid'] = 77;
        $facts['activities'][0]['students'] = [['id' => 77, 'name' => 'Maria Souza', 'grade' => 61.5]];
        $facts['forums'][0]['authors'] = ['Maria Souza'];
        $json = fingerprint::canonical_json(comparable_set::from($facts)->to_array());

        $this->assertStringNotContainsString('Maria', $json);
        $this->assertStringNotContainsString('userid', $json);
        $this->assertStringNotContainsString('students', $json);
        $this->assertStringNotContainsString('61.5', $json);
        $this->assertStringNotContainsString('authors', $json);
    }

    /**
     * Q6: the criteria of spec-015 are not part of the set, and no other unknown block is accepted.
     */
    public function test_criteria_and_unknown_blocks_are_refused(): void {
        foreach (['criteria', 'criteria_results', 'students', 'grades'] as $block) {
            try {
                comparable_set::from(self::facts() + [$block => [1]]);
                $this->fail("{$block} was accepted");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($block, $e->getMessage());
            }
        }
    }

    /**
     * Invalid facts are refused, never read as zero.
     *
     * @return array
     */
    public static function invalid_facts(): array {
        return [
            'grade above 100' => [['activities' => [['activity_id' => 1, 'grades' => [100.1]]]]],
            'grade below 0' => [['activities' => [['activity_id' => 1, 'grades' => [-0.1]]]]],
            'grade as text' => [['activities' => [['activity_id' => 1, 'grades' => ['70']]]]],
            'frequency above 100' => [['frequency' => [101.0]]],
            'activity without id' => [['activities' => [['grades' => [1.0]]]]],
            'activity id zero' => [['activities' => [['activity_id' => 0, 'grades' => []]]]],
            'activity twice' => [['activities' => [['activity_id' => 1, 'grades' => []], ['activity_id' => 1, 'grades' => []]]]],
            'forum twice' => [['forums' => [['cmid' => 1], ['cmid' => 1]]]],
            'material without summary' => [['materials' => [['material_id' => 1]]]],
            'material summary not a text' => [['materials' => [['material_id' => 1, 'summary' => 5]]]],
            'difficulty without text' => [['forums' => [['cmid' => 1, 'difficulties' => [['discussion_id' => 1]]]]]],
            'questionnaire twice' => [['questionnaires' => [['cmid' => 1], ['cmid' => 1]]]],
            'negative count' => [['questionnaires' => [['cmid' => 1, 'questions' => [['question_id' => 1, 'correct_count' => -1]]]]]],
            'infinite number' => [['questionnaires' => [['cmid' => 1, 'coverage' => INF]]]],
            'block not a list' => [['activities' => 'x']],
        ];
    }

    #[DataProvider('invalid_facts')]
    public function test_invalid_facts_are_refused(array $facts): void {
        $this->expectException(\InvalidArgumentException::class);

        comparable_set::from($facts);
    }
}
