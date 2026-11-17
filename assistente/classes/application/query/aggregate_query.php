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

use local_assistente\application\access_service;
use local_assistente\application\course_config_service;

/**
 * What the aggregate queries share (T110; spec-018 common rules, DEC-43, DEC-45 D3): the backend authorizes first, with
 * `local/assistente:view` in the course, before anything is said about the assistant; then the filter by group is refused, because the
 * aggregate queries stay at the level of the course or of the section and are neither filtered nor restricted by group (DEC-43 D1 and D2);
 * then a course where the assistant is not enabled shows no results (DEC-45 D3). No aggregate query takes free text.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class aggregate_query {
    /** @var access_service */
    protected access_service $access;

    /** @var course_config_service */
    protected course_config_service $config;

    /** @var presentation */
    protected presentation $presentation;

    /**
     * Constructor.
     *
     * @param access_service|null $access
     * @param course_config_service|null $config
     * @param presentation|null $presentation
     */
    public function __construct(?access_service $access = null, ?course_config_service $config = null, ?presentation $presentation = null) {
        $this->access = $access ?? new access_service();
        $this->config = $config ?? new course_config_service();
        $this->presentation = $presentation ?? new presentation();
    }

    /**
     * Authorize, refuse a group filter and apply the state of the assistant.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param int|null $groupid must be null: the filter by group is not available in aggregate queries
     * @param \Closure $rows reads the rows, only when the assistant is enabled
     * @return array{available: bool, message: string|null, rows: array}
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     * @throws \InvalidArgumentException when a group is given
     */
    protected function run(int $courseid, int $userid, ?int $groupid, \Closure $rows): array {
        $this->access->require_view($courseid, $userid);
        if ($groupid !== null) {
            throw new \InvalidArgumentException('The filter by group is not available in aggregate queries.');
        }
        return $this->presentation->results($this->config->state($courseid), $rows);
    }

    /**
     * A value with its state and its text.
     *
     * @param \local_assistente\domain\result_state $state
     * @param string $text
     * @return array{state: string, text: string}
     */
    protected function shown(\local_assistente\domain\result_state $state, string $text): array {
        return ['state' => $state->value, 'text' => $text];
    }
}
