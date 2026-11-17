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

namespace local_assistente\infrastructure\llm;

use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the validation of the LLM answers (quickstart 009 Q2, Q6, Q7; AT-049; spec-010 §Regras).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(result_validator::class)]
final class result_validator_test extends \basic_testcase {
    /**
     * An envelope with status valid.
     *
     * @param llm_use_case $use
     * @param mixed $data
     * @return array
     */
    private static function envelope(llm_use_case $use, mixed $data): array {
        return ['use_case' => $use->value, 'status' => 'valid', 'data' => $data];
    }

    /**
     * Validate an answer for a use.
     *
     * @param llm_use_case $use
     * @param mixed $raw
     * @param array $contextdata
     * @return structured_result
     */
    private function validate(llm_use_case $use, mixed $raw, array $contextdata = []): structured_result {
        return (new result_validator())->validate(new llm_context($use, $contextdata), $raw);
    }

    /**
     * Assert that a result is the failure invalid_response.
     *
     * @param structured_result $result
     */
    private function assert_invalid(structured_result $result): void {
        $this->assertFalse($result->is_valid());
        $this->assertSame('invalid_response', $result->errorcode);
        $this->assertNull($result->data, 'Nothing is handed over to be persisted');
    }

    /**
     * Quickstart 009 Q1 (validation side): a valid answer is accepted for each use, with only the contract fields kept.
     */
    public function test_valid_answers_for_each_use(): void {
        $summary = $this->validate(llm_use_case::MATERIAL_SUMMARY, self::envelope(llm_use_case::MATERIAL_SUMMARY, ['summary' => 'Texto', 'extra' => 'x']));
        $this->assertSame(['summary' => 'Texto'], $summary->data, 'Extra fields are dropped');

        $coverage = $this->validate(llm_use_case::QUIZ_COVERAGE, self::envelope(llm_use_case::QUIZ_COVERAGE, ['coverage' => 0.7]));
        $this->assertEqualsWithDelta(0.7, $coverage->data['coverage'], 1e-9);

        $complexity = $this->validate(llm_use_case::QUESTION_COMPLEXITY, self::envelope(llm_use_case::QUESTION_COMPLEXITY, ['complexity' => 1]));
        $this->assertEqualsWithDelta(1.0, $complexity->data['complexity'], 1e-9);

        $synthesis = $this->validate(llm_use_case::PEDAGOGICAL_SYNTHESIS, self::envelope(llm_use_case::PEDAGOGICAL_SYNTHESIS, ['content' => 'Síntese']));
        $this->assertSame(['content' => 'Síntese'], $synthesis->data);

        $this->assertTrue($summary->is_valid() && $coverage->is_valid() && $complexity->is_valid() && $synthesis->is_valid());
    }

    /**
     * Values for the coverage and the complexity that are accepted: from 0.0 to 1.0 in steps of 0.1.
     *
     * @return array
     */
    public static function good_tenths(): array {
        return [[0], [0.0], [0.1], [0.2], [0.3], [0.5], [0.7], [0.9], [1], [1.0]];
    }

    /**
     * Values that are not: out of range, off the step, or not a number. Quickstart 009 Q6: 1.1, -0.1 and 0.35.
     *
     * @return array
     */
    public static function bad_tenths(): array {
        return [
            'above one' => [1.1], 'negative' => [-0.1], 'off the step' => [0.35], 'hundredth' => [0.01], 'two' => [2],
            'string' => ['0.5'], 'null' => [null], 'true' => [true], 'nan' => [NAN], 'infinite' => [INF], 'array' => [[0.5]],
        ];
    }

    /**
     * An accepted value.
     *
     * @param mixed $value
     */
    #[DataProvider('good_tenths')]
    public function test_coverage_and_complexity_accept_the_step(mixed $value): void {
        $this->assertTrue($this->validate(llm_use_case::QUIZ_COVERAGE, self::envelope(llm_use_case::QUIZ_COVERAGE, ['coverage' => $value]))->is_valid());
        $this->assertTrue($this->validate(llm_use_case::QUESTION_COMPLEXITY, self::envelope(llm_use_case::QUESTION_COMPLEXITY, ['complexity' => $value]))->is_valid());
    }

    /**
     * A rejected value: invalid_response and nothing persisted. The value is not rounded to a neighbour.
     *
     * @param mixed $value
     */
    #[DataProvider('bad_tenths')]
    public function test_coverage_and_complexity_reject_what_is_off_the_contract(mixed $value): void {
        $this->assert_invalid($this->validate(llm_use_case::QUIZ_COVERAGE, self::envelope(llm_use_case::QUIZ_COVERAGE, ['coverage' => $value])));
        $this->assert_invalid($this->validate(llm_use_case::QUESTION_COMPLEXITY, self::envelope(llm_use_case::QUESTION_COMPLEXITY, ['complexity' => $value])));
    }

    /**
     * Quickstart 009 Q7: another use than the requested one, or a missing required field, is invalid.
     */
    public function test_wrong_use_or_missing_field_is_invalid(): void {
        $this->assert_invalid($this->validate(
            llm_use_case::QUIZ_COVERAGE,
            self::envelope(llm_use_case::QUESTION_COMPLEXITY, ['coverage' => 0.5])
        ));
        $this->assert_invalid($this->validate(llm_use_case::QUIZ_COVERAGE, self::envelope(llm_use_case::QUIZ_COVERAGE, [])));
        $this->assert_invalid($this->validate(llm_use_case::QUIZ_COVERAGE, self::envelope(llm_use_case::QUIZ_COVERAGE, ['complexity' => 0.5])));
        $this->assert_invalid($this->validate(llm_use_case::QUIZ_COVERAGE, ['use_case' => 'quiz_coverage', 'status' => 'valid']));
        $this->assert_invalid($this->validate(llm_use_case::QUIZ_COVERAGE, ['status' => 'valid', 'data' => ['coverage' => 0.5]]));
    }

    /**
     * Quickstart 009 Q2: an answer that does not decode, or has a broken envelope, is invalid.
     */
    public function test_garbage_is_invalid(): void {
        foreach ([null, false, 'not json', 42, [], ['use_case' => 'quiz_coverage'], ['use_case' => 'quiz_coverage', 'status' => 'maybe']] as $raw) {
            $this->assert_invalid($this->validate(llm_use_case::QUIZ_COVERAGE, $raw));
        }
    }

    /**
     * Texts must not be empty.
     */
    public function test_texts_must_not_be_empty(): void {
        foreach ([[llm_use_case::MATERIAL_SUMMARY, 'summary'], [llm_use_case::PEDAGOGICAL_SYNTHESIS, 'content']] as [$use, $field]) {
            foreach ([null, '', '   ', 5, ['x']] as $value) {
                $this->assert_invalid($this->validate($use, self::envelope($use, [$field => $value])));
            }
        }
    }

    /**
     * Forum analysis: a summary, and difficulties of discussions that belong to the context (quickstart 013 Q8).
     */
    public function test_forum_analysis(): void {
        $use = llm_use_case::FORUM_ANALYSIS;
        $context = ['discussion_ids' => [10, 11]];
        $good = ['forum_summary' => 'Resumo', 'difficulties' => [
            ['discussion_id' => 10, 'description' => 'Dúvida A'],
            ['discussion_id' => 10, 'description' => 'Dúvida B'],
            ['discussion_id' => 11, 'description' => 'Dúvida C'],
        ]];

        $ok = $this->validate($use, self::envelope($use, $good + ['extra' => 1]), $context);
        $this->assertTrue($ok->is_valid());
        $this->assertSame($good, $ok->data);
        $this->assertTrue($this->validate($use, self::envelope($use, ['forum_summary' => 'Resumo', 'difficulties' => []]), $context)->is_valid(), 'No difficulty is allowed');

        // A discussion that is not in the context: invalid, nothing is published.
        $this->assert_invalid($this->validate($use, self::envelope($use, ['forum_summary' => 'R', 'difficulties' => [['discussion_id' => 99, 'description' => 'x']]]), $context));
        // No discussion is allowed when the context lists none.
        $this->assert_invalid($this->validate($use, self::envelope($use, ['forum_summary' => 'R', 'difficulties' => [['discussion_id' => 10, 'description' => 'x']]])));
        foreach ([
            'no summary' => ['difficulties' => []],
            'empty summary' => ['forum_summary' => ' ', 'difficulties' => []],
            'no difficulties key' => ['forum_summary' => 'R'],
            'difficulties not a list' => ['forum_summary' => 'R', 'difficulties' => ['a' => ['discussion_id' => 10, 'description' => 'x']]],
            'empty description' => ['forum_summary' => 'R', 'difficulties' => [['discussion_id' => 10, 'description' => '']]],
            'string id' => ['forum_summary' => 'R', 'difficulties' => [['discussion_id' => '10', 'description' => 'x']]],
            'missing id' => ['forum_summary' => 'R', 'difficulties' => [['description' => 'x']]],
            'item not an object' => ['forum_summary' => 'R', 'difficulties' => ['x']],
        ] as $label => $data) {
            $this->assert_invalid($this->validate($use, self::envelope($use, $data), $context));
        }
    }

    /**
     * An error reported by the provider keeps its class; an error without a valid class is invalid_response.
     */
    public function test_errors_reported_by_the_provider(): void {
        $use = llm_use_case::MATERIAL_SUMMARY;
        $limited = $this->validate($use, ['use_case' => $use->value, 'status' => 'error', 'error_code' => 'rate_limit']);
        $this->assertFalse($limited->is_valid());
        $this->assertSame('rate_limit', $limited->errorcode);

        $this->assert_invalid($this->validate($use, ['use_case' => $use->value, 'status' => 'error']));
        $this->assert_invalid($this->validate($use, ['use_case' => $use->value, 'status' => 'error', 'error_code' => 'technical']));
        $this->assert_invalid($this->validate($use, ['use_case' => $use->value, 'status' => 'error', 'error_code' => 5]));
    }
}
