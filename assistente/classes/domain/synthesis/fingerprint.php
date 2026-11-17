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

/**
 * Fingerprint of the comparable set: SHA-256 in hexadecimal of its canonical JSON (T095; spec-016, DEC-41 D2, DEC-19).
 *
 * The canonical form has the keys of every object in ascending order, the entities in the order of their id (the set already comes so),
 * the numbers with a fraction as text truncated to three decimals (`66.666`; integers such as ids and counts stay integers), and the texts
 * exactly as stored. It contains nothing else: no timestamp, no identifier of processing or analysis, no data of the LLM, because the
 * comparable set has none, so the same pedagogical information always gives the same fingerprint and an equal one means there is nothing
 * new to write about. Pure.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fingerprint {
    /** @var int length of the fingerprint */
    public const LENGTH = 64;

    /**
     * The fingerprint of a set.
     *
     * @param comparable_set $set
     * @return string 64 hexadecimal characters
     */
    public static function of(comparable_set $set): string {
        return hash('sha256', self::canonical_json($set->to_array()));
    }

    /**
     * The canonical JSON of a normalized set.
     *
     * @param array $data
     * @return string
     */
    public static function canonical_json(array $data): string {
        return json_encode(self::canonical($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Sort the keys of the objects and write the floats as text with three decimals.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function canonical(mixed $value): mixed {
        if (is_float($value)) {
            return number_format($value, 3, '.', '');
        }
        if (!is_array($value)) {
            return $value;
        }
        $canonical = array_map(self::canonical(...), $value);
        if (!array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }
        return $canonical;
    }
}
