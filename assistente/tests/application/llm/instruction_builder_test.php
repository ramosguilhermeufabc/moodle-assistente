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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the instruction builder: the language of the generated content (DEC-46 D1; quickstart 021 Q11 and Q12, generation side).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(instruction_builder::class)]
final class instruction_builder_test extends \advanced_testcase {
    /**
     * The directive is added at the end of the task, with the language code.
     */
    public function test_language_directive_is_added(): void {
        $instruction = instruction_builder::with_language("Summarise the material.\n", 'pt_br');

        $this->assertStringStartsWith('Summarise the material.', $instruction);
        $this->assertStringContainsString("'pt_br'", $instruction);
        $this->assertStringEndsWith('.', $instruction);
    }

    /**
     * A task or a language that is empty is a programming error.
     */
    public function test_empty_task_or_language_is_rejected(): void {
        foreach ([['', 'en'], ['  ', 'en'], ['Task', ''], ['Task', ' ']] as [$task, $language]) {
            try {
                instruction_builder::with_language($task, $language);
                $this->fail('Empty input must be rejected');
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * The language of a course is its forced language, otherwise the site default (DEC-46 D1).
     */
    public function test_language_of_a_course(): void {
        $this->resetAfterTest();
        set_config('lang', 'en');
        $plain = $this->getDataGenerator()->create_course();
        $forced = $this->getDataGenerator()->create_course(['lang' => 'pt_br']);
        $builder = new instruction_builder();

        $this->assertSame('en', $builder->language_for($plain->id));
        $this->assertSame('pt_br', $builder->language_for($forced->id));
        $this->assertStringContainsString("'pt_br'", $builder->for_course($forced->id, 'Write a synthesis.'));
        $this->assertStringContainsString("'en'", $builder->for_course($plain->id, 'Write a synthesis.'));
    }

    /**
     * A change of the language of the course changes the next instruction; nothing already generated is touched, because the
     * builder does not read or write any stored content.
     */
    public function test_new_language_applies_to_the_next_instruction_only(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['lang' => 'pt_br']);
        $builder = new instruction_builder();
        $before = $builder->for_course($course->id, 'Task');

        $DB->set_field('course', 'lang', 'en', ['id' => $course->id]);

        $this->assertStringContainsString("'pt_br'", $before);
        $this->assertStringContainsString("'en'", $builder->for_course($course->id, 'Task'));
        $this->assertSame(0, $DB->count_records('local_assistente_analysis'));
    }
}
