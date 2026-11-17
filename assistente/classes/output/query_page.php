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
 * A page of one query: a title, the message when the assistant is not available, and the table (spec-021; quickstart 021 Q3, Q6 to Q9).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class query_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param string $title
     * @param array $result what a query returns: `available`, `message` and `rows`
     * @param bool $filtered whether a filter was applied: an empty result then says that no version matches, not that there is no data
     */
    public function __construct(
        /** @var string title of the table */
        private readonly string $title,
        /** @var array result of the query */
        private readonly array $result,
        /** @var bool whether a filter was applied */
        private readonly bool $filtered = false
    ) {
    }

    #[\Override]
    public function export_for_template(\renderer_base $output): array {
        $available = (bool) ($this->result['available'] ?? false);
        $table = $available ? table_data::build($this->result['rows'] ?? []) : ['hasrows' => false, 'columns' => [], 'rows' => []];
        return [
            'title' => $this->title,
            'available' => $available,
            'message' => $this->result['message'] ?? null,
            'norows' => $available && !$table['hasrows'] && !$this->filtered,
            'noresults' => $available && !$table['hasrows'] && $this->filtered,
        ] + $table;
    }
}
