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

/**
 * Tests of the envelope of the LLM port and of the request context (contracts/llm-port.md).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(structured_result::class)]
#[CoversClass(llm_context::class)]
#[CoversClass(llm_use_case::class)]
final class structured_result_test extends \basic_testcase {
    /**
     * The five uses of the LLM, and only those: statistics and criteria are not among them.
     */
    public function test_the_five_allowed_uses(): void {
        $this->assertSame(
            ['material_summary', 'quiz_coverage', 'question_complexity', 'forum_analysis', 'pedagogical_synthesis'],
            array_map(static fn(llm_use_case $use) => $use->value, llm_use_case::cases())
        );
    }

    /**
     * A valid result carries data and no error code.
     */
    public function test_valid_result(): void {
        $result = structured_result::valid(llm_use_case::QUIZ_COVERAGE, ['coverage' => 0.5]);

        $this->assertTrue($result->is_valid());
        $this->assertSame('valid', $result->status);
        $this->assertSame(llm_use_case::QUIZ_COVERAGE, $result->usecase);
        $this->assertSame(['coverage' => 0.5], $result->data);
        $this->assertNull($result->errorcode);
    }

    /**
     * An error carries a class from the contract and no data.
     */
    public function test_error_result(): void {
        foreach (['timeout', 'transport', 'auth', 'rate_limit', 'invalid_response'] as $code) {
            $result = structured_result::error(llm_use_case::MATERIAL_SUMMARY, $code, 'reason');

            $this->assertFalse($result->is_valid());
            $this->assertSame('error', $result->status);
            $this->assertSame($code, $result->errorcode);
            $this->assertNull($result->data);
            $this->assertSame('reason', $result->errormessage);
        }
        $this->assertSame(
            ['timeout', 'transport', 'auth', 'rate_limit', 'invalid_response'],
            structured_result::ERROR_CODES
        );
    }

    /**
     * A failure class outside the contract is a programming error, not a new state.
     */
    public function test_unknown_error_code_is_rejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        structured_result::error(llm_use_case::MATERIAL_SUMMARY, 'technical');
    }

    /**
     * The context carries the use and the data; the discussions allowed for a forum answer are read from it.
     */
    public function test_context(): void {
        $context = new llm_context(llm_use_case::FORUM_ANALYSIS, ['discussion_ids' => [3, 5, 'x', 7.5]]);

        $this->assertSame(llm_use_case::FORUM_ANALYSIS, $context->usecase);
        $this->assertSame([3, 5], $context->discussion_ids(), 'Only integers count');
        $this->assertSame([], (new llm_context(llm_use_case::QUIZ_COVERAGE))->discussion_ids());
        $this->assertSame([], (new llm_context(llm_use_case::FORUM_ANALYSIS, ['discussion_ids' => 'oops']))->discussion_ids());
    }
}
