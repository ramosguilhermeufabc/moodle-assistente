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

namespace local_assistente\application\llm;

use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the privacy guard of the requests to the LLM (quickstart 009 Q9, generic part; quickstart 010 Q11; DEC-34 D3).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(context_guard::class)]
#[CoversClass(guarded_llm_port::class)]
final class context_guard_test extends \basic_testcase {
    /**
     * Keys that identify a person, in several spellings.
     *
     * @return array
     */
    public static function identifier_keys(): array {
        return [
            'userid' => ['userid'], 'user_id' => ['user_id'], 'userId' => ['userId'], 'author' => ['author'],
            'author_id' => ['author_id'], 'student_id' => ['student_id'], 'username' => ['username'],
            'firstname' => ['firstname'], 'last-name' => ['last-name'], 'fullname' => ['fullname'], 'email' => ['email'],
            'E-Mail' => ['E-Mail'], 'idnumber' => ['idnumber'],
        ];
    }

    /**
     * A key that identifies a student is rejected, at the top and nested in messages.
     *
     * @param string $key
     */
    #[DataProvider('identifier_keys')]
    public function test_identifier_keys_are_rejected(string $key): void {
        $guard = new context_guard();
        $top = new llm_context(llm_use_case::FORUM_ANALYSIS, [$key => 'x']);
        $nested = new llm_context(llm_use_case::FORUM_ANALYSIS, ['messages' => [['text' => 'hello', $key => 'x']]]);

        foreach ([$top, $nested] as $context) {
            try {
                $guard->assert_clean($context, 'Analyse');
                $this->fail("The key '{$key}' must be rejected");
            } catch (student_identifier_exception $e) {
                $this->assertStringNotContainsString('hello', $e->getMessage(), 'The message does not repeat the content');
            }
        }
    }

    /**
     * A request with only aggregates and texts without identifiers passes.
     */
    public function test_clean_context_passes(): void {
        $context = new llm_context(llm_use_case::PEDAGOGICAL_SYNTHESIS, [
            'activities' => [['mean' => 71.5, 'min' => 20, 'max' => 100, 'valid' => 18, 'no_data' => 2]],
            'forum_summary' => 'Students had doubts about the grading rule.',
            'discussion_ids' => [1, 2],
            'student_count' => 20,
        ]);

        (new context_guard())->assert_clean($context, 'Write a synthesis.');
        $this->assertTrue(true);
    }

    /**
     * An e-mail address in a text of the context or in the instruction is rejected.
     */
    public function test_email_addresses_are_rejected(): void {
        $guard = new context_guard();

        foreach ([
            [new llm_context(llm_use_case::FORUM_ANALYSIS, ['text' => 'write to maria.silva@example.com']), 'Analyse'],
            [new llm_context(llm_use_case::FORUM_ANALYSIS, ['text' => 'fine']), 'Answer to joao@escola.edu.br'],
        ] as [$context, $instruction]) {
            try {
                $guard->assert_clean($context, $instruction);
                $this->fail('An e-mail address must be rejected');
            } catch (student_identifier_exception $e) {
                $this->assertStringNotContainsString('@', $e->getMessage(), 'The address is not repeated in the message');
            }
        }
    }

    /**
     * Names the caller forbids are found in the texts, ignoring case, and are not repeated in the message.
     */
    public function test_forbidden_literals_are_found(): void {
        $guard = new context_guard();
        $context = new llm_context(llm_use_case::FORUM_ANALYSIS, ['messages' => ['I think MARIA SILVA is right']]);

        try {
            $guard->assert_clean($context, 'Analyse', ['Maria Silva', 'Joao']);
            $this->fail('The forbidden name must be found');
        } catch (student_identifier_exception $e) {
            $this->assertStringNotContainsString('Maria', $e->getMessage());
        }

        $guard->assert_clean(new llm_context(llm_use_case::FORUM_ANALYSIS, ['messages' => ['all good']]), 'Analyse', ['Maria Silva', '', '  ']);
        $this->assertTrue(true, 'Empty literals are ignored');
    }

    /**
     * The guarded port refuses before calling the adapter, and lets clean requests through.
     */
    public function test_guarded_port_does_not_call_the_adapter_for_a_dirty_request(): void {
        $counting = new class implements \local_assistente\infrastructure\llm\llm_port {
            /** @var int calls that reached the adapter */
            public int $calls = 0;

            #[\Override]
            public function evaluate(llm_context $context, string $instruction): \local_assistente\infrastructure\llm\structured_result {
                $this->calls++;
                return \local_assistente\infrastructure\llm\structured_result::valid($context->usecase, ['coverage' => 0.5]);
            }
        };
        $port = new guarded_llm_port($counting);

        try {
            $port->evaluate(new llm_context(llm_use_case::QUIZ_COVERAGE, ['user_id' => 5]), 'Cover');
            $this->fail('The request must be refused');
        } catch (student_identifier_exception $e) {
            $this->assertSame(0, $counting->calls);
        }

        $this->assertTrue($port->evaluate(new llm_context(llm_use_case::QUIZ_COVERAGE, ['text' => 'ok']), 'Cover')->is_valid());
        $this->assertSame(1, $counting->calls);

        $strict = $port->forbidding(['Maria']);
        $this->expectException(student_identifier_exception::class);
        $strict->evaluate(new llm_context(llm_use_case::QUIZ_COVERAGE, ['text' => 'maria']), 'Cover');
    }
}
