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


namespace local_assistente\application\query;

use local_assistente\domain\criteria\assistant_state;
use local_assistente\domain\criteria_status;
use local_assistente\domain\result_state;
use local_assistente\domain\source_status;

/**
 * How the results of the functional queries are shown (T107; spec-018 common rules, DEC-02, DEC-24, DEC-35 D2, DEC-36 D2, DEC-39 D3,
 * DEC-40, DEC-42 D3, DEC-45 D3).
 *
 * It only formats and orders what a query has already read: it does not read the database, does not ask Moodle whether a record exists
 * (the state of a source comes from the source_status_resolver of T102 as a source_status, and the state of the assistant from the
 * course_config_service of T040 as an assistant_state) and creates no indicator. The text of the labels comes from the language
 * strings, through a translator that tests and the interface can replace.
 *
 * Moodle API: get_string (default translator), userdate (default date format), \Collator (php-intl) for the order of names.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class presentation {
    /** @var string[] language strings of the labels */
    public const STRINGS = [
        'query:no_data', 'query:error', 'query:no_materials', 'query:source_removed', 'query:not_enabled',
        'query:criteria_below', 'query:criteria_meets', 'query:access_yes', 'query:access_no', 'query:access_last',
    ];

    /** @var \Closure key => text */
    private \Closure $translate;

    /** @var \Closure timestamp => text */
    private \Closure $dateformat;

    /**
     * Constructor.
     *
     * @param string $decimalseparator the decimal separator of the percentages; the contract shows a comma ("66,66%")
     * @param \Closure|null $translator receives the key of a string of this plugin and returns its text; null uses get_string
     * @param \Closure|null $dateformat receives a timestamp and returns its text; null uses userdate
     */
    public function __construct(
        private string $decimalseparator = ',',
        ?\Closure $translator = null,
        ?\Closure $dateformat = null
    ) {
        $this->translate = $translator ?? static fn(string $key): string => get_string($key, 'local_assistente');
        $this->dateformat = $dateformat ?? static fn(int $time): string => userdate($time, get_string('strftimedatefullshort'));
    }

    /**
     * A percentage truncated, never rounded, to two decimals ("66,66%"; DEC-36 D2). The stored value has three decimals (66.666), and the
     * binary noise of a product such as 0.29 * 100 is discarded before truncating, as truncated_percentage does.
     *
     * @param float|int|string $value a number from 0 to 100, the domain of every percentage of the contracts
     * @return string
     * @throws \InvalidArgumentException for a value that is not a number, is not finite or is outside 0 to 100
     */
    public function percentage(float|int|string $value): string {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('A percentage must be a number.');
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < 0.0 || $number > 100.0) {
            throw new \InvalidArgumentException('A percentage must be a finite number from 0 to 100.');
        }
        $truncated = floor(round($number * 100, 6)) / 100;
        return str_replace('.', $this->decimalseparator, number_format($truncated, 2, '.', '')) . '%';
    }

    /**
     * The value of a result according to its state (DEC-24): the percentage when valid; "sem dados" and "erro técnico" otherwise, never zero.
     *
     * @param result_state $state
     * @param float|int|string|null $value the stored value, used only when the state is VALID (a NO_DATA result keeps 0 in the schema)
     * @return string
     * @throws \InvalidArgumentException for a valid state without a value
     */
    public function value(result_state $state, float|int|string|null $value): string {
        return match ($state) {
            result_state::VALID => $value === null
                ? throw new \InvalidArgumentException('A valid result needs a value.')
                : $this->percentage($value),
            result_state::NO_DATA => $this->text('query:no_data'),
            result_state::ERROR => $this->text('query:error'),
        };
    }

    /**
     * The status of a criterion (DEC-40): 1 meets, 0 below, 2 no data.
     *
     * @param criteria_status $status
     * @return string
     */
    public function criteria(criteria_status $status): string {
        return $this->text(match ($status) {
            criteria_status::MEETS => 'query:criteria_meets',
            criteria_status::BELOW => 'query:criteria_below',
            criteria_status::NO_DATA => 'query:no_data',
        });
    }

    /**
     * A value of the scale 0.0 to 1.0 in steps of 0.1 (`coverage` and `complexity` of spec-010), truncated, never rounded, to one decimal:
     * 0.7 is "0,7". It is not a percentage and is shown without the percent sign.
     *
     * @param float|int|string $value a number from 0.0 to 1.0
     * @return string
     * @throws \InvalidArgumentException for a value that is not a number, is not finite or is outside 0.0 to 1.0
     */
    public function scale(float|int|string $value): string {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('A value of the scale must be a number.');
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < 0.0 || $number > 1.0) {
            throw new \InvalidArgumentException('A value of the scale must be a finite number from 0.0 to 1.0.');
        }
        return str_replace('.', $this->decimalseparator, number_format(floor(round($number * 10, 6)) / 10, 1, '.', ''));
    }

    /**
     * The coverage of a questionnaire (DEC-35 D2), on the scale 0.0 to 1.0 of spec-010: "sem materiais" only for a valid coverage of
     * exactly 0.0 when the snapshot had no material; a zero coverage with materials is a real zero and is shown as "0,0"; no data and error
     * keep their own labels, and the state comes first.
     *
     * @param result_state $state
     * @param float|int|string|null $coverage
     * @param bool $hadmaterials whether the snapshot of the analysis listed at least one material
     * @return string
     */
    public function coverage(result_state $state, float|int|string|null $coverage, bool $hadmaterials): string {
        if ($state === result_state::VALID && !$hadmaterials && $coverage !== null && is_numeric($coverage) && (float) $coverage === 0.0) {
            return $this->text('query:no_materials');
        }
        return $this->scaled($state, $coverage);
    }

    /**
     * The complexity of a question (spec-010), on the scale 0.0 to 1.0, with the labels of the states.
     *
     * @param result_state $state
     * @param float|int|string|null $complexity
     * @return string
     */
    public function complexity(result_state $state, float|int|string|null $complexity): string {
        return $this->scaled($state, $complexity);
    }

    /**
     * A value of the scale by state: the value when valid, the label of the state otherwise, never zero.
     *
     * @param result_state $state
     * @param float|int|string|null $value
     * @return string
     */
    private function scaled(result_state $state, float|int|string|null $value): string {
        return match ($state) {
            result_state::VALID => $value === null
                ? throw new \InvalidArgumentException('A valid result needs a value.')
                : $this->scale($value),
            result_state::NO_DATA => $this->text('query:no_data'),
            result_state::ERROR => $this->text('query:error'),
        };
    }

    /**
     * The access to the course as complementary evidence (DEC-39 D3): it never enters the percentage and never replaces the main state.
     *
     * @param bool|null $accessed null when the snapshot does not say, and then nothing is shown
     * @param int|null $lastaccess timestamp of the last access, when known
     * @return string|null
     */
    public function course_access(?bool $accessed, ?int $lastaccess = null): ?string {
        if ($accessed === null) {
            return null;
        }
        if (!$accessed) {
            return $this->text('query:access_no');
        }
        $text = $this->text('query:access_yes');
        return $lastaccess === null ? $text : $text . ' (' . str_replace('{$a}', ($this->dateformat)($lastaccess), $this->text('query:access_last')) . ')';
    }

    /**
     * A result with its complementary evidence: the main text is always the state of the result.
     *
     * @param result_state $state
     * @param float|int|string|null $value
     * @param bool|null $accessed
     * @param int|null $lastaccess
     * @return array{main: string, complement: string|null}
     */
    public function result(result_state $state, float|int|string|null $value, ?bool $accessed = null, ?int $lastaccess = null): array {
        return ['main' => $this->value($state, $value), 'complement' => $this->course_access($accessed, $lastaccess)];
    }

    /**
     * The indication of a removed source (DEC-42 D3), from the state that the source_status_resolver (T102) gave.
     *
     * @param source_status $status
     * @return string|null "fonte removida", or null when the record still exists
     */
    public function source_note(source_status $status): ?string {
        return $status === source_status::REMOVED ? $this->text('query:source_removed') : null;
    }

    /**
     * The sources that the resolver returned, with the indication of those removed.
     *
     * @param array $resolved each ['type' => string, 'id' => int, 'status' => source_status], as source_status_resolver::resolve gives
     * @return array the same entries, each with 'note' (string or null)
     */
    public function annotate_sources(array $resolved): array {
        return array_map(fn(array $source): array => $source + ['note' => $this->source_note($source['status'])], $resolved);
    }

    /**
     * The message of a course where the assistant is not enabled (DEC-45 D3), the same for a course without configuration.
     *
     * @param assistant_state $state
     * @return string|null "assistente não habilitado", or null when the assistant is enabled
     */
    public function unavailable(assistant_state $state): ?string {
        return $state->is_enabled() ? null : $this->text('query:not_enabled');
    }

    /**
     * The results of a course, only when the assistant is enabled: otherwise the message and no results, and the query is not even run.
     *
     * @param assistant_state $state
     * @param \Closure $results returns the rows of the query
     * @return array{available: bool, message: string|null, rows: array}
     */
    public function results(assistant_state $state, \Closure $results): array {
        $message = $this->unavailable($state);
        return $message === null
            ? ['available' => true, 'message' => null, 'rows' => $results()]
            : ['available' => false, 'message' => $message, 'rows' => []];
    }

    /**
     * Order rows by name, A to Z (DEC-02), with the collation of the language (case and accent weigh last, so Ana comes before ANA and álvaro after alice), then by 'id' when there is
     * one, and finally by the original position, so the same input always gives the same order.
     *
     * @param array $rows each with 'name' and optionally 'id'
     * @return array
     */
    public function sort_by_name(array $rows): array {
        return $this->sorted($rows, fn(array $a, array $b): int => 0);
    }

    /**
     * Order rows by the value shown, with the tie broken by name (DEC-02): two values that are shown the same (66.661 and 66.669 are both
     * "66,66%") are a tie. Rows without a valid value (no data, error) come after those with a value, in either direction, ordered by name.
     *
     * @param array $rows each with 'name', 'state' (result_state), 'value' and optionally 'id'
     * @param bool $descending
     * @return array
     */
    public function sort_by_value(array $rows, bool $descending = false): array {
        $shown = static fn(array $row): ?float => $row['state'] === result_state::VALID && $row['value'] !== null
            ? floor(round((float) $row['value'] * 100, 6)) / 100
            : null;
        return $this->sorted($rows, static function (array $a, array $b) use ($shown, $descending): int {
            $x = $shown($a);
            $y = $shown($b);
            if ($x === null || $y === null) {
                return ($x === null) <=> ($y === null);
            }
            return $descending ? $y <=> $x : $x <=> $y;
        });
    }

    /**
     * Stable ordering by a first comparison and then by name, id and position.
     *
     * @param array $rows
     * @param \Closure $first
     * @return array
     */
    private function sorted(array $rows, \Closure $first): array {
        $collator = new \Collator('pt_BR');
        $indexed = [];
        foreach (array_values($rows) as $position => $row) {
            $indexed[] = [$position, $row];
        }
        usort($indexed, static function (array $a, array $b) use ($first, $collator): int {
            return $first($a[1], $b[1])
                ?: $collator->compare((string) $a[1]['name'], (string) $b[1]['name'])
                ?: ($a[1]['id'] ?? 0) <=> ($b[1]['id'] ?? 0)
                ?: $a[0] <=> $b[0];
        });
        return array_column($indexed, 1);
    }

    /**
     * The text of a label.
     *
     * @param string $key
     * @return string
     */
    private function text(string $key): string {
        return ($this->translate)($key);
    }
}
