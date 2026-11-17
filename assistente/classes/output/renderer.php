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
 * Renderer of the pages of the assistant: each renderable has its Mustache template.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class renderer extends \plugin_renderer_base {
    /**
     * Render a query page.
     *
     * @param query_page $page
     * @return string
     */
    protected function render_query_page(query_page $page): string {
        return $this->render_from_template('local_assistente/query_table', $page->export_for_template($this));
    }

    /**
     * Render the report.
     *
     * @param report_page $page
     * @return string
     */
    protected function render_report_page(report_page $page): string {
        return $this->render_from_template('local_assistente/report', $page->export_for_template($this));
    }

    /**
     * Render a history.
     *
     * @param history_page $page
     * @return string
     */
    protected function render_history_page(history_page $page): string {
        return $this->render_from_template('local_assistente/history', $page->export_for_template($this));
    }
}
