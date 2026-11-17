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

/**
 * Replaces the name of a student in a text by `[removido]` (T104; DEC-56). Pure: no Moodle, no database.
 *
 * What is looked for, as DEC-56 says: the full name (first name and last name, in the order of the user record, with any run of blanks
 * between them), the first name and the last name, each one as a whole word, without telling upper case from lower case and without telling
 * an accented letter from the plain one. The names are those of the fields of the record, as phrases ("da Silva" is found as "da Silva", not
 * as "da" and "Silva"). There is no minimum length, as DEC-56 has none: a short or common name redacts every word equal to it, which is the
 * accepted risk of false positives; "Ana-Maria" is two words to the match, so "Ana" is found in it.
 *
 * A word is whole when no letter, digit or underscore touches it. The text is matched in a folded copy (decomposed, without the combining
 * marks, in lower case) and the replacement is made in the original, by position, so every other character of the text is kept as it was.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class name_redactor {
    /** @var string what replaces each occurrence */
    public const MARKER = '[removido]';

    /** @var string[] regular expressions over the folded text, longest forms first */
    private array $patterns = [];

    /**
     * Constructor.
     *
     * @param string[] $firstnames first names of the students whose name is to be removed
     * @param string[] $lastnames last names, in the same order
     */
    public function __construct(array $firstnames, array $lastnames) {
        $full = [];
        $single = [];
        foreach ($firstnames as $i => $first) {
            $last = $lastnames[$i] ?? '';
            $first = self::fold(trim((string) $first));
            $last = self::fold(trim((string) $last));
            if ($first !== '' && $last !== '') {
                $full[] = self::phrase($first) . '\s+' . self::phrase($last);
            }
            foreach ([$first, $last] as $name) {
                if ($name !== '') {
                    $single[] = self::phrase($name);
                }
            }
        }
        // Longest forms first, so that the full name is one occurrence and "Maria Souza" does not become two markers.
        usort($full, static fn($a, $b) => strlen($b) <=> strlen($a));
        $single = array_values(array_unique($single));
        usort($single, static fn($a, $b) => strlen($b) <=> strlen($a));
        $alternatives = array_merge(array_values(array_unique($full)), $single);
        if ($alternatives) {
            $this->patterns[] = '/(?<![\p{L}\p{N}_])(?:' . implode('|', $alternatives) . ')(?![\p{L}\p{N}_])/u';
        }
    }

    /**
     * Whether there is any name to look for.
     *
     * @return bool
     */
    public function has_names(): bool {
        return $this->patterns !== [];
    }

    /**
     * Redact a text.
     *
     * @param string $text
     * @return array{0: string, 1: int} the text and the number of occurrences replaced; the same text and 0 when there is none
     */
    public function redact(string $text): array {
        if ($text === '' || !$this->patterns) {
            return [$text, 0];
        }
        [$folded, $map, $chars] = self::fold_with_map($text);
        if (!preg_match_all($this->patterns[0], $folded, $found, PREG_OFFSET_CAPTURE) || !$found[0]) {
            return [$text, 0];
        }
        $out = '';
        $cursor = 0;
        $count = 0;
        foreach ($found[0] as [$match, $byteoffset]) {
            $first = mb_strlen(substr($folded, 0, $byteoffset));
            $last = $first + mb_strlen($match) - 1;
            $from = $map[$first];
            $to = $map[$last];
            // A combining mark that follows the last letter belongs to it and goes with it.
            while (isset($chars[$to + 1]) && preg_match('/^\p{Mn}+$/u', $chars[$to + 1])) {
                $to++;
            }
            if ($from < $cursor) {
                continue;
            }
            $out .= implode('', array_slice($chars, $cursor, $from - $cursor)) . self::MARKER;
            $cursor = $to + 1;
            $count++;
        }
        $out .= implode('', array_slice($chars, $cursor));
        return [$out, $count];
    }

    /**
     * Redact every text inside a JSON document, in the values and not in the keys. A document that is not valid JSON is redacted as a text.
     *
     * @param string $json
     * @return array{0: string, 1: int} the document (the same one when nothing was found) and the number of occurrences
     */
    public function redact_json(string $json): array {
        $decoded = json_decode($json, true);
        if ($json === '' || (!is_array($decoded) && json_last_error() !== JSON_ERROR_NONE)) {
            return $this->redact($json);
        }
        if (!is_array($decoded)) {
            return [$json, 0];
        }
        $count = 0;
        $walk = function (mixed $value) use (&$walk, &$count): mixed {
            if (is_string($value)) {
                [$text, $n] = $this->redact($value);
                $count += $n;
                return $text;
            }
            return is_array($value) ? array_map($walk, $value) : $value;
        };
        $changed = $walk($decoded);
        if ($count === 0) {
            return [$json, 0];
        }
        return [json_encode($changed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), $count];
    }

    /**
     * A name as a regular expression, with a run of blanks inside it matching any run of blanks.
     *
     * @param string $folded
     * @return string
     */
    private static function phrase(string $folded): string {
        return implode('\s+', array_map(static fn($w) => preg_quote($w, '/'), preg_split('/\s+/u', $folded, -1, PREG_SPLIT_NO_EMPTY)));
    }

    /**
     * A text without accents and in lower case.
     *
     * @param string $text
     * @return string
     */
    private static function fold(string $text): string {
        return self::fold_with_map($text)[0];
    }

    /**
     * The folded text, the position in the original of each character of the folded text, and the characters of the original.
     *
     * @param string $text
     * @return array{0: string, 1: int[], 2: string[]}
     */
    private static function fold_with_map(string $text): array {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $folded = '';
        $map = [];
        foreach ($chars as $i => $char) {
            $decomposed = \Normalizer::normalize($char, \Normalizer::FORM_D);
            $plain = mb_strtolower(preg_replace('/\p{Mn}+/u', '', $decomposed === false ? $char : $decomposed));
            foreach (preg_split('//u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
                $folded .= $c;
                $map[] = $i;
            }
        }
        return [$folded, $map, $chars];
    }
}
