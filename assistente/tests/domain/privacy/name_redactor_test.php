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


namespace local_assistente\domain\privacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the redaction of names (T104; DEC-56, quickstart 017 Q9): full name, first name and last name, whole words, case and accents.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(name_redactor::class)]
final class name_redactor_test extends \basic_testcase {
    /**
     * Redact a text for Maria Souza.
     *
     * @param string $text
     * @return array{0: string, 1: int}
     */
    private static function maria(string $text): array {
        return (new name_redactor(['Maria'], ['Souza']))->redact($text);
    }

    /**
     * Cases: text, expected text, occurrences.
     *
     * @return array
     */
    public static function cases(): array {
        $m = name_redactor::MARKER;
        return [
            'full name is one occurrence' => ['A aluna Maria Souza perguntou.', "A aluna {$m} perguntou.", 1],
            'first name alone' => ['Maria perguntou sobre prazos.', "{$m} perguntou sobre prazos.", 1],
            'last name alone' => ['A dúvida de Souza foi sobre notas.', "A dúvida de {$m} foi sobre notas.", 1],
            'first and last apart' => ['Maria e o colega Souza discutiram.', "{$m} e o colega {$m} discutiram.", 2],
            'upper case' => ['MARIA SOUZA escreveu', "{$m} escreveu", 1],
            'lower case' => ['maria souza escreveu', "{$m} escreveu", 1],
            'accents of the text' => ['Mária Sôuza respondeu', "{$m} respondeu", 1],
            'blanks between the names' => ["Maria \n  Souza respondeu", "{$m} respondeu", 1],
            'several occurrences' => ['Maria disse que Maria Souza viu Souza', "{$m} disse que {$m} viu {$m}", 3],
            'inside a longer word is not found' => ['Mariana e Marialva e Sousa e Souzas', 'Mariana e Marialva e Sousa e Souzas', 0],
            'touching letters or digits' => ['xMaria Maria2 _Maria Maria_', 'xMaria Maria2 _Maria Maria_', 0],
            'punctuation is a boundary' => ['(Maria), Souza; "Maria Souza".', "({$m}), {$m}; \"{$m}\".", 3],
            'hyphen is a boundary, a risk that is accepted' => ['Maria-Clara chegou', "{$m}-Clara chegou", 1],
            'empty text' => ['', '', 0],
            'no name' => ['Texto sem nenhum estudante.', 'Texto sem nenhum estudante.', 0],
            'other characters are kept' => ["Linha 1\n\tMaria\r\n✓ ação — fim", "Linha 1\n\t{$m}\r\n✓ ação — fim", 1],
        ];
    }

    #[DataProvider('cases')]
    public function test_redaction(string $text, string $expected, int $count): void {
        $this->assertSame([$expected, $count], self::maria($text));
    }

    /**
     * Accents of the name: a name with accents is found in a text with or without them, and the other way round.
     */
    public function test_accents_in_the_name_and_in_the_text(): void {
        $redactor = new name_redactor(['José'], ['Conceição']);

        $this->assertSame(['[removido] chegou', 1], $redactor->redact('Jose Conceicao chegou'));
        $this->assertSame(['[removido] chegou', 1], $redactor->redact('JOSÉ CONCEIÇÃO chegou'));
        $this->assertSame(['o [removido] disse', 1], $redactor->redact('o José disse'));
        $plain = new name_redactor(['Jose'], ['Conceicao']);
        $this->assertSame(['[removido] chegou', 1], $plain->redact('José Conceição chegou'));
        // A text with the letter and its combining mark apart (decomposed) is found and replaced whole.
        $this->assertSame(['[removido] fim', 1], $redactor->redact("Jose\u{0301} fim"));
    }

    /**
     * Names with several words are phrases of the field; repeating the redaction changes nothing; the marker itself is not a name.
     */
    public function test_compound_names_repetition_and_the_marker(): void {
        $redactor = new name_redactor(['Maria Clara'], ['da Silva']);

        $once = $redactor->redact('Maria Clara da Silva e Maria Clara e da Silva e Clara');
        $twice = $redactor->redact($once[0]);

        $this->assertSame(['[removido] e [removido] e [removido] e Clara', 3], $once);
        $this->assertSame([$once[0], 0], $twice, 'Repeating changes nothing');
        // A student called "removido" would be redacted like any other word; the marker of an earlier redaction has its brackets and is found too.
        $this->assertSame(['[[removido]]', 1], (new name_redactor(['removido'], ['x']))->redact('[removido]'));
    }

    /**
     * Two students with the same or partly the same names: the names of every student asked are redacted, in the text of the others too.
     */
    public function test_coinciding_and_partly_coinciding_names(): void {
        $same = new name_redactor(['Maria'], ['Souza']);
        $this->assertSame(['[removido] e [removido]', 2], $same->redact('Maria Souza e Maria'), 'The other Maria is redacted too: the text does not tell them apart');

        $partly = new name_redactor(['Maria'], ['Souza']);
        $this->assertSame(['João [removido]', 1], $partly->redact('João Souza'), 'A shared last name redacts the other student\'s last name');

        $both = new name_redactor(['Maria', 'João'], ['Souza', 'Lima']);
        $this->assertSame(['[removido] e [removido] e [removido]', 3], $both->redact('Maria Souza e João Lima e Lima'));
    }

    /**
     * Empty names are ignored: with no usable name nothing is found, and a blank name never matches blanks.
     */
    public function test_no_usable_name_redacts_nothing(): void {
        $this->assertFalse((new name_redactor([''], ['  ']))->has_names());
        $this->assertSame(['Texto com espaços  e mais', 0], (new name_redactor([' '], ['']))->redact('Texto com espaços  e mais'));
        $this->assertTrue((new name_redactor(['Ana'], ['']))->has_names());
        $this->assertSame(['[removido] chegou', 1], (new name_redactor(['Ana'], ['']))->redact('Ana chegou'));
    }

    /**
     * JSON: only the values are redacted, keys and numbers stay, accents written as \u escapes are found, and an unchanged document is
     * returned as it was; invalid JSON is redacted as a text.
     */
    public function test_json_documents(): void {
        $redactor = new name_redactor(['José'], ['Souza']);
        $json = '{"summary":"José Souza perguntou","José":"chave","n":80.0,"list":[{"t":"José"},3],"ok":true}';

        [$out, $count] = $redactor->redact_json($json);

        $this->assertSame(2, $count);
        $decoded = json_decode($out, true);
        $this->assertSame('[removido] perguntou', $decoded['summary']);
        $this->assertSame('chave', $decoded['José'], 'The key is not a text of the student');
        $this->assertSame('[removido]', $decoded['list'][0]['t']);
        $this->assertSame([80.0, 3, true], [$decoded['n'], $decoded['list'][1], $decoded['ok']]);
        $this->assertStringContainsString('80.0', $out);
        $this->assertSame(['{"a":"nada"}', 0], $redactor->redact_json('{"a":"nada"}'));
        $this->assertSame(['[removido] {', 1], $redactor->redact_json('José {'), 'Invalid JSON is redacted as a text');
        $this->assertSame(['', 0], $redactor->redact_json(''));
    }
}
