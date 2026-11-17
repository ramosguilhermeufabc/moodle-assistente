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
 * The current version of the report: its header, a link to the history and one table for each block (spec-021; quickstart 021 Q2).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param array $result what report_view_model::current() returns
     * @param \moodle_url $historyurl page of the history of the report
     * @param bool $filtered whether a filter was applied: no report then says that no version matches
     */
    public function __construct(
        /** @var array result of the view model */
        private readonly array $result,
        /** @var \moodle_url history of the report */
        private readonly \moodle_url $historyurl,
        /** @var bool whether a filter was applied */
        private readonly bool $filtered = false
    ) {
    }

    #[\Override]
    public function export_for_template(\renderer_base $output): array {
        $available = (bool) ($this->result['available'] ?? false);
        $report = $available ? ($this->result['rows'][0] ?? null) : null;
        $blocks = [];
        foreach ($report['blocks'] ?? [] as $name => $block) {
            $table = table_data::build($block['rows'] ?? []);
            $state = (string) ($block['state'] ?? '');
            $blocks[] = [
                'title' => self::block_title((string) $name),
                'statetext' => $state === 'valid' ? null : get_string($state === 'error' ? 'query:error' : 'query:no_data', 'local_assistente'),
            ] + $table;
        }
        return [
            'available' => $available,
            'message' => $this->result['message'] ?? null,
            'hasreport' => $report !== null,
            'noreport' => $available && $report === null && !$this->filtered,
            'noresults' => $available && $report === null && $this->filtered,
            'versiontext' => $report === null ? null : get_string('report:current_version', 'local_assistente', (int) $report['version']),
            'generated' => $report === null ? null : userdate((int) $report['generated_at'], get_string('strftimedatetimeshort')),
            'historyurl' => $this->historyurl->out(false),
            'blocks' => $blocks,
        ];
    }

    /**
     * Title of a block of the report.
     *
     * @param string $name
     * @return string
     */
    private static function block_title(string $name): string {
        $identifier = 'block:' . $name;
        return get_string_manager()->string_exists($identifier, 'local_assistente') ? get_string($identifier, 'local_assistente') : $name;
    }
}
