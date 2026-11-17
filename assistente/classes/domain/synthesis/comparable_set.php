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

use local_assistente\domain\truncated_percentage;

/**
 * The comparable set of a section: exactly what is sent to the LLM to write the synthesis, and what the fingerprint is made of (T095;
 * spec-016, DEC-41 D1 and D2, DEC-19, DEC-34 D3).
 *
 * Pure: no Moodle, database, clock or LLM. It receives facts already read for one section and keeps only aggregates and texts already
 * produced:
 * - performance: for each activity of the section, the mean, minimum and maximum of the current grades (in percent) and how many students
 *   have a `valid` result and how many have `no_data`; there is no single index of the section, which would weigh the activities in a
 *   way nobody chose (DEC-41 D1);
 * - frequency: the same statistics over the percentages of the section;
 * - questionnaires: coverage, complexity and the metrics of the current analyses;
 * - forums: the summary and the difficulties of the current snapshot of each forum;
 * - materials: the summaries of the eligible materials.
 * The individual grades only enter to be aggregated and never leave. Nothing that identifies a student, no name, and nothing of the
 * criteria of spec-015 (they are of the course, not of the section). Only the fields of the set are read from each fact, so a timestamp, an
 * identifier of processing or analysis, or data of the LLM that comes along is not part of the set (DEC-19).
 *
 * A block with no result is the string `no_data`; a statistic over no valid value is `no_data` with null numbers (DEC-24). Entities are
 * in ascending order of their id, so that the order of the facts does not matter. Numbers are truncated to three decimals, never rounded.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class comparable_set {
    /** @var string marker of a block or a statistic without result */
    public const NO_DATA = 'no_data';

    /** @var string[] the blocks of the set, in the order of the facts */
    public const BLOCKS = ['activities', 'frequency', 'questionnaires', 'forums', 'materials'];

    /**
     * Constructor; use from().
     *
     * @param array $data the normalized set
     */
    private function __construct(
        /** @var array the normalized set */
        private readonly array $data
    ) {
    }

    /**
     * Build the set of a section from its facts.
     *
     * @param array $facts any of the blocks of BLOCKS (a missing block is `no_data`):
     *        activities: list of ['activity_id' => int > 0, 'grades' => list of float|null percent from 0 to 100, one for each student];
     *        frequency: list of float|null percent from 0 to 100, one for each student;
     *        questionnaires: list of ['cmid' => int > 0, 'coverage' => float|null, 'metrics' => null or ['mean', 'population_standard_deviation',
     *          'minimum', 'maximum' as float|null], 'questions' => list of ['question_id' => int > 0, 'question_version' => int, 'complexity',
     *          'average_correctness', 'partial_result' as float|null, 'correct_count', 'incorrect_count' as int|null]];
     *        forums: list of ['cmid' => int > 0, 'summary' => string|null, 'difficulties' => list of ['discussion_id' => int > 0,
     *          'description' => string]];
     *        materials: list of ['material_id' => int > 0, 'summary' => string]
     * @return self
     * @throws \InvalidArgumentException for an unknown block (the criteria of spec-015 among them) or an invalid fact
     */
    public static function from(array $facts): self {
        $unknown = array_diff(array_keys($facts), self::BLOCKS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Not part of the comparable set: ' . implode(', ', $unknown) . '.');
        }
        return new self([
            'activities' => self::activities($facts['activities'] ?? []),
            'frequency' => self::statistics(array_values($facts['frequency'] ?? []), 'frequency'),
            'questionnaires' => self::questionnaires($facts['questionnaires'] ?? []),
            'forums' => self::forums($facts['forums'] ?? []),
            'materials' => self::materials($facts['materials'] ?? []),
        ]);
    }

    /**
     * The normalized set: blocks in a fixed order, entities in ascending order of id, `no_data` for a block with no result.
     *
     * @return array
     */
    public function to_array(): array {
        return $this->data;
    }

    /**
     * Whether the set has at least one pedagogical result: a valid grade or frequency, a questionnaire or a forum with something to say.
     * Materials alone are context, not a result (DEC-41 D3): the synthesis is not made from them.
     *
     * @return bool
     */
    public function has_pedagogical_result(): bool {
        if (is_array($this->data['activities'])) {
            foreach ($this->data['activities'] as $activity) {
                if ($activity['valid'] > 0) {
                    return true;
                }
            }
        }
        if ($this->data['frequency']['valid'] > 0) {
            return true;
        }
        foreach ((array) ($this->data['questionnaires'] === self::NO_DATA ? [] : $this->data['questionnaires']) as $questionnaire) {
            if ($questionnaire['coverage'] !== null || $questionnaire['metrics'] !== null || $questionnaire['questions'] !== []) {
                return true;
            }
        }
        foreach ((array) ($this->data['forums'] === self::NO_DATA ? [] : $this->data['forums']) as $forum) {
            if ($forum['summary'] !== null || $forum['difficulties'] !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * Statistics of the activities.
     *
     * @param mixed $activities
     * @return array|string
     */
    private static function activities(mixed $activities): array|string {
        $rows = [];
        foreach (self::list($activities, 'activities') as $activity) {
            $id = self::id($activity['activity_id'] ?? null, 'activity_id');
            isset($rows[$id]) && throw new \InvalidArgumentException("The activity {$id} appears twice.");
            $rows[$id] = ['activity_id' => $id] + self::statistics(array_values(self::list($activity['grades'] ?? [], 'grades')), "activity {$id}");
        }
        ksort($rows);
        return $rows === [] ? self::NO_DATA : array_values($rows);
    }

    /**
     * Mean, minimum, maximum and counts of a list of percentages; null is a student without result.
     *
     * @param array $values
     * @param string $what
     * @return array
     */
    private static function statistics(array $values, string $what): array {
        $valid = [];
        $nodata = 0;
        foreach ($values as $value) {
            if ($value === null) {
                $nodata++;
                continue;
            }
            if ((!is_int($value) && !is_float($value)) || is_nan($value) || $value < 0 || $value > 100) {
                throw new \InvalidArgumentException("A value of {$what} must be a percentage from 0 to 100, or null.");
            }
            $valid[] = (float) $value;
        }
        $has = $valid !== [];
        return [
            'maximum' => $has ? truncated_percentage::of(max($valid)) : null,
            'mean' => $has ? truncated_percentage::of(array_sum($valid) / count($valid)) : null,
            'minimum' => $has ? truncated_percentage::of(min($valid)) : null,
            'no_data' => $nodata,
            'state' => $has ? 'valid' : self::NO_DATA,
            'valid' => count($valid),
        ];
    }

    /**
     * Coverage, complexity and metrics of the questionnaires.
     *
     * @param mixed $questionnaires
     * @return array|string
     */
    private static function questionnaires(mixed $questionnaires): array|string {
        $rows = [];
        foreach (self::list($questionnaires, 'questionnaires') as $questionnaire) {
            $cmid = self::id($questionnaire['cmid'] ?? null, 'cmid');
            isset($rows[$cmid]) && throw new \InvalidArgumentException("The questionnaire {$cmid} appears twice.");
            $questions = [];
            foreach (self::list($questionnaire['questions'] ?? [], 'questions') as $question) {
                $id = self::id($question['question_id'] ?? null, 'question_id');
                $version = self::count($question['question_version'] ?? 0, 'question_version') ?? 0;
                $questions["{$id}:{$version}"] = [
                    'average_correctness' => self::number($question['average_correctness'] ?? null),
                    'complexity' => self::number($question['complexity'] ?? null),
                    'correct_count' => self::count($question['correct_count'] ?? null, 'correct_count'),
                    'incorrect_count' => self::count($question['incorrect_count'] ?? null, 'incorrect_count'),
                    'partial_result' => self::number($question['partial_result'] ?? null),
                    'question_id' => $id,
                    'question_version' => $version,
                ];
            }
            uasort($questions, static fn($a, $b) => [$a['question_id'], $a['question_version']] <=> [$b['question_id'], $b['question_version']]);
            $metrics = $questionnaire['metrics'] ?? null;
            $rows[$cmid] = [
                'cmid' => $cmid,
                'coverage' => self::number($questionnaire['coverage'] ?? null),
                'metrics' => $metrics === null ? null : [
                    'maximum' => self::number($metrics['maximum'] ?? null),
                    'mean' => self::number($metrics['mean'] ?? null),
                    'minimum' => self::number($metrics['minimum'] ?? null),
                    'population_standard_deviation' => self::number($metrics['population_standard_deviation'] ?? null),
                ],
                'questions' => array_values($questions),
            ];
        }
        ksort($rows);
        return $rows === [] ? self::NO_DATA : array_values($rows);
    }

    /**
     * Summary and difficulties of the forums.
     *
     * @param mixed $forums
     * @return array|string
     */
    private static function forums(mixed $forums): array|string {
        $rows = [];
        foreach (self::list($forums, 'forums') as $forum) {
            $cmid = self::id($forum['cmid'] ?? null, 'cmid');
            isset($rows[$cmid]) && throw new \InvalidArgumentException("The forum {$cmid} appears twice.");
            $difficulties = [];
            foreach (self::list($forum['difficulties'] ?? [], 'difficulties') as $difficulty) {
                $id = self::id($difficulty['discussion_id'] ?? null, 'discussion_id');
                $difficulties[$id] = ['description' => self::text($difficulty['description'] ?? null), 'discussion_id' => $id];
            }
            ksort($difficulties);
            $rows[$cmid] = [
                'cmid' => $cmid,
                'difficulties' => array_values($difficulties),
                'summary' => ($forum['summary'] ?? null) === null ? null : self::text($forum['summary']),
            ];
        }
        ksort($rows);
        return $rows === [] ? self::NO_DATA : array_values($rows);
    }

    /**
     * Summaries of the materials.
     *
     * @param mixed $materials
     * @return array|string
     */
    private static function materials(mixed $materials): array|string {
        $rows = [];
        foreach (self::list($materials, 'materials') as $material) {
            $id = self::id($material['material_id'] ?? null, 'material_id');
            isset($rows[$id]) && throw new \InvalidArgumentException("The material {$id} appears twice.");
            $rows[$id] = ['material_id' => $id, 'summary' => self::text($material['summary'] ?? null)];
        }
        ksort($rows);
        return $rows === [] ? self::NO_DATA : array_values($rows);
    }

    /**
     * A list of facts.
     *
     * @param mixed $value
     * @param string $what
     * @return array
     */
    private static function list(mixed $value, string $what): array {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("The block {$what} must be a list.");
        }
        return $value;
    }

    /**
     * A positive integer identifier.
     *
     * @param mixed $value
     * @param string $name
     * @return int
     */
    private static function id(mixed $value, string $name): int {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("The field {$name} must be a positive integer.");
        }
        return $value;
    }

    /**
     * A non-negative integer, or null.
     *
     * @param mixed $value
     * @param string $name
     * @return int|null
     */
    private static function count(mixed $value, string $name): ?int {
        if ($value !== null && (!is_int($value) || $value < 0)) {
            throw new \InvalidArgumentException("The field {$name} must be a non-negative integer or null.");
        }
        return $value;
    }

    /**
     * A number kept as it was calculated, truncated to three decimals, or null.
     *
     * @param mixed $value
     * @return float|null
     */
    private static function number(mixed $value): ?float {
        if ($value === null) {
            return null;
        }
        if ((!is_int($value) && !is_float($value)) || is_nan($value) || is_infinite($value)) {
            throw new \InvalidArgumentException('A number of the set must be finite.');
        }
        return truncated_percentage::of((float) $value);
    }

    /**
     * A text exactly as stored.
     *
     * @param mixed $value
     * @return string
     */
    private static function text(mixed $value): string {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('A text of the set must be a string.');
        }
        return $value;
    }
}
