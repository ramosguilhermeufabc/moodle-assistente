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

namespace local_assistente\application\report;

use local_assistente\domain\report\report_schedule;
use local_assistente\domain\result_state;

/**
 * Builder of the manifest of a report version, the content of `report_version.snapshot_json` (spec-019 contract "Manifesto"; DEC-44 D1; T114).
 *
 * The manifest holds only references: for each block, the ids of the immutable analyses it used (`analysis_ids`), the ids of the material
 * versions (`material_versions`) and the version of the synthesis of each section (`synthesis_versions`), plus the state of the block. It
 * carries the type of the version (`periodic` or `final`) and the moment it was generated. No value of a result is copied, so nothing about a
 * student is kept outside the analyses (DEC-42 D1), and there is no block of history.
 *
 * The builder does not read Moodle or the database, and does not decide what the current analyses of a block are: the caller passes the
 * references already resolved. The same input always gives the same text (fixed order of the blocks, ascending ids, no extra whitespace).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_builder {
    /** Block of the grades and performance of the students. */
    public const BLOCK_GRADES = 'grades';

    /** Block of the metrics and the coverage of the questionnaires. */
    public const BLOCK_QUIZ_METRICS = 'quiz_metrics';

    /** Block of the frequency. */
    public const BLOCK_FREQUENCY = 'frequency';

    /** Block of the forums. */
    public const BLOCK_FORUMS = 'forums';

    /** Block of the students below the criteria. */
    public const BLOCK_BELOW_CRITERIA = 'below_criteria';

    /** Block of the summaries of the materials. */
    public const BLOCK_MATERIAL_SUMMARIES = 'material_summaries';

    /** Block of the pedagogical synthesis by section. */
    public const BLOCK_SECTION_SYNTHESIS = 'section_synthesis';

    /** Blocks of a report, in the order they appear in the manifest. The history of spec-017 is not among them (DEC-44 D1). */
    public const BLOCKS = [
        self::BLOCK_GRADES,
        self::BLOCK_QUIZ_METRICS,
        self::BLOCK_FREQUENCY,
        self::BLOCK_FORUMS,
        self::BLOCK_BELOW_CRITERIA,
        self::BLOCK_MATERIAL_SUMMARIES,
        self::BLOCK_SECTION_SYNTHESIS,
    ];

    /**
     * Build the manifest of a version.
     *
     * @param string $type {@see report_schedule::TYPE_PERIODIC} or {@see report_schedule::TYPE_FINAL}
     * @param int $generated_at Unix instant the version is generated
     * @param array $blocks Every block of {@see self::BLOCKS}, keyed by name, each as
     *     `['state' => result_state|string, 'analysis_ids' => int[], 'material_versions' => int[], 'synthesis_versions' => array<int, int>]`
     *     (the reference lists are optional, empty by default; `synthesis_versions` maps a section id to its version number). The state
     *     `valid` needs at least one reference and `no_data` none.
     * @return string the JSON text for `snapshot_json`
     * @throws \InvalidArgumentException on an unknown type, a missing, unknown or history block, an invalid state or reference
     */
    public function build(string $type, int $generated_at, array $blocks): string {
        if (!in_array($type, [report_schedule::TYPE_PERIODIC, report_schedule::TYPE_FINAL], true)) {
            throw new \InvalidArgumentException("Unknown type of report version: {$type}.");
        }
        if ($generated_at <= 0) {
            throw new \InvalidArgumentException('The moment of generation must be a positive Unix instant.');
        }
        $unknown = array_diff(array_map('strval', array_keys($blocks)), self::BLOCKS);
        if ($unknown) {
            throw new \InvalidArgumentException('Unknown block of the report: ' . implode(', ', $unknown) . '.');
        }

        $manifest = ['type' => $type, 'generated_at' => $generated_at, 'blocks' => []];
        foreach (self::BLOCKS as $name) {
            if (!isset($blocks[$name])) {
                throw new \InvalidArgumentException("The block {$name} is missing; a block without data is passed as no_data.");
            }
            $manifest['blocks'][] = $this->block($name, $blocks[$name]);
        }
        return json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Read a manifest back: validated, with the references as lists of ints.
     *
     * Gives the path from a report version to its analyses and syntheses (quickstart 017 Q5).
     *
     * @param string $snapshot_json
     * @return array{type: string, generated_at: int, blocks: array<string, array{state: string, analysis_ids: int[],
     *     material_versions: int[], synthesis_versions: array<int, int>}>} blocks keyed by name
     * @throws \InvalidArgumentException when the text is not a manifest of this builder
     */
    public function read(string $snapshot_json): array {
        $data = json_decode($snapshot_json, true);
        if (!is_array($data) || !isset($data['type'], $data['generated_at'], $data['blocks']) || !is_array($data['blocks'])) {
            throw new \InvalidArgumentException('The text is not a report manifest.');
        }
        $byname = [];
        foreach ($data['blocks'] as $block) {
            if (!is_array($block) || !isset($block['block'])) {
                throw new \InvalidArgumentException('A block of the manifest has no name.');
            }
            $byname[$block['block']] = [
                'state' => $block['state'] ?? '',
                'analysis_ids' => $block['refs']['analysis_ids'] ?? [],
                'material_versions' => $block['refs']['material_versions'] ?? [],
                'synthesis_versions' => [],
            ];
            foreach ($block['refs']['synthesis_versions'] ?? [] as $ref) {
                $byname[$block['block']]['synthesis_versions'][(int) $ref['section_id']] = (int) $ref['version'];
            }
        }
        // Rebuilding validates everything and fixes the type of the values.
        $rebuilt = json_decode($this->build((string) $data['type'], (int) $data['generated_at'], $byname), true);
        $blocks = [];
        foreach ($rebuilt['blocks'] as $block) {
            $synthesis = [];
            foreach ($block['refs']['synthesis_versions'] as $ref) {
                $synthesis[$ref['section_id']] = $ref['version'];
            }
            $blocks[$block['block']] = [
                'state' => $block['state'],
                'analysis_ids' => $block['refs']['analysis_ids'],
                'material_versions' => $block['refs']['material_versions'],
                'synthesis_versions' => $synthesis,
            ];
        }
        return ['type' => $rebuilt['type'], 'generated_at' => $rebuilt['generated_at'], 'blocks' => $blocks];
    }

    /**
     * One block of the manifest.
     *
     * @param string $name
     * @param mixed $input
     * @return array
     */
    private function block(string $name, mixed $input): array {
        if (!is_array($input) || !isset($input['state'])) {
            throw new \InvalidArgumentException("The block {$name} needs a state.");
        }
        $state = $input['state'] instanceof result_state ? $input['state'] : result_state::tryFrom((string) $input['state']);
        if ($state === null) {
            throw new \InvalidArgumentException("The block {$name} has an invalid state.");
        }
        $unknown = array_diff(array_keys($input), ['state', 'analysis_ids', 'material_versions', 'synthesis_versions']);
        if ($unknown) {
            throw new \InvalidArgumentException("The block {$name} has unknown fields: " . implode(', ', $unknown) . '. Only references are kept.');
        }

        $synthesis = [];
        foreach ($input['synthesis_versions'] ?? [] as $sectionid => $version) {
            $synthesis[$this->id($sectionid, $name)] = $this->id($version, $name);
        }
        ksort($synthesis);
        $refs = [
            'analysis_ids' => $this->ids($input['analysis_ids'] ?? [], $name),
            'material_versions' => $this->ids($input['material_versions'] ?? [], $name),
            'synthesis_versions' => array_map(
                static fn(int $section, int $version): array => ['section_id' => $section, 'version' => $version],
                array_keys($synthesis),
                array_values($synthesis)
            ),
        ];
        $count = count($refs['analysis_ids']) + count($refs['material_versions']) + count($refs['synthesis_versions']);
        if ($state === result_state::VALID && $count === 0) {
            throw new \InvalidArgumentException("The block {$name} is valid but has no reference.");
        }
        if ($state === result_state::NO_DATA && $count > 0) {
            throw new \InvalidArgumentException("The block {$name} has no data but has references.");
        }
        return ['block' => $name, 'state' => $state->value, 'refs' => $refs];
    }

    /**
     * A list of ids: positive ints only (a value is never copied), unique and ascending.
     *
     * @param mixed $ids
     * @param string $name block, for the messages
     * @return int[]
     */
    private function ids(mixed $ids, string $name): array {
        if (!is_array($ids)) {
            throw new \InvalidArgumentException("The references of the block {$name} must be a list of ids.");
        }
        $clean = array_values(array_unique(array_map(fn($id): int => $this->id($id, $name), $ids)));
        sort($clean);
        return $clean;
    }

    /**
     * One reference: a positive int (or its decimal text, as the database returns it).
     *
     * @param mixed $id
     * @param string $name block, for the messages
     * @return int
     */
    private function id(mixed $id, string $name): int {
        if (is_string($id) && ctype_digit($id)) {
            $id = (int) $id;
        }
        if (!is_int($id) || $id <= 0) {
            throw new \InvalidArgumentException("A reference of the block {$name} must be a positive integer id.");
        }
        return $id;
    }
}
