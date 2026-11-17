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

namespace local_assistente\output;

/**
 * Turns the rows of a query (or of a block of a report) into the context of a native table (spec-021).
 *
 * The rows are the ones the query services return, already formatted by `presentation`: a value is a text, or a `state` with its `text`,
 * or a list of those. This class only chooses the columns, labels them and flattens each cell into lines of text. Every text goes to the
 * template as data, so Mustache escapes it (`{{ }}`); nothing here builds HTML. It reads nothing and decides nothing about the data.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class table_data {
    /** @var string[] keys that identify a record for the code and are not shown */
    private const HIDDEN = ['id', 'cmid', 'section_id', 'analysis_id', 'userid'];

    /** @var string[] keys that hold a timestamp */
    private const TIMES = ['created_at', 'generated_at'];

    /**
     * The context of a table.
     *
     * @param array $rows rows as returned by a query
     * @return array{hasrows: bool, columns: array, rows: array}
     */
    public static function build(array $rows): array {
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys((array) $row) as $key) {
                if (!in_array($key, self::HIDDEN, true) && !in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }
        $columns = array_map(static fn(string $key): array => ['label' => self::label($key)], $keys);
        $out = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($keys as $index => $key) {
                $lines = self::lines($row[$key] ?? null, $key);
                $cells[] = ['rowheader' => $index === 0, 'lines' => array_map(static fn(string $text): array => ['text' => $text], $lines)];
            }
            $out[] = ['cells' => $cells];
        }
        return ['hasrows' => $rows !== [], 'columns' => $columns, 'rows' => $out];
    }

    /**
     * Label of a column: the language string `col:<key>` when there is one, otherwise the key.
     *
     * @param string $key
     * @return string
     */
    public static function label(string $key): string {
        $identifier = 'col:' . $key;
        return get_string_manager()->string_exists($identifier, 'local_assistente')
            ? get_string($identifier, 'local_assistente')
            : $key;
    }

    /**
     * A value as lines of text.
     *
     * @param mixed $value
     * @param string $key the column, to format timestamps
     * @return string[]
     */
    public static function lines(mixed $value, string $key = ''): array {
        if ($value === null) {
            return [];
        }
        if (is_bool($value)) {
            return [get_string($value ? 'yes' : 'no')];
        }
        if (is_int($value) && in_array($key, self::TIMES, true)) {
            return [userdate($value, get_string('strftimedatetimeshort'))];
        }
        if (is_scalar($value)) {
            return [(string) $value];
        }
        $array = (array) $value;
        if (array_key_exists('state', $array) && array_key_exists('text', $array)) {
            return $array['text'] === null ? [] : [(string) $array['text']];
        }
        $lines = [];
        foreach ($array as $subkey => $subvalue) {
            if (is_string($subkey) && in_array($subkey, self::HIDDEN, true)) {
                continue;
            }
            $sub = self::lines($subvalue, is_string($subkey) ? $subkey : '');
            if (is_string($subkey) && $sub !== []) {
                $sub[0] = self::label($subkey) . ': ' . $sub[0];
            }
            array_push($lines, ...$sub);
        }
        return $lines;
    }
}
