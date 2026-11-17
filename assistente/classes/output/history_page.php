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
 * The history of an entity or of the report: one row for each version, newest first (spec-021; spec-017).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param string $title
     * @param array $result what history_query or report_view_model::history() returns
     * @param bool $filtered whether a filter was applied
     */
    public function __construct(
        /** @var string title */
        private readonly string $title,
        /** @var array result */
        private readonly array $result,
        /** @var bool whether a filter was applied */
        private readonly bool $filtered = false
    ) {
    }

    #[\Override]
    public function export_for_template(\renderer_base $output): array {
        return (new query_page($this->title, $this->result, $this->filtered))->export_for_template($output);
    }
}
