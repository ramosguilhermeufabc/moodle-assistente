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
use local_assistente\domain\result_state;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\persistence\synthesis_repository;

/**
 * Query of the pedagogical synthesis (T110; spec-018 contract "síntese", DEC-41, spec-016).
 *
 * For each section of the course it shows the CURRENT synthesis, the version in force, the highest number (DEC-41); older versions are
 * history. A section with no synthesis is "sem dados". The content is generated text and is returned as it was stored, as text, never
 * interpreted as HTML here; the renderer escapes it.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class synthesis_query extends aggregate_query {
    /**
     * Constructor; every collaborator can be replaced by tests.
     *
     * @param access_service|null $access
     * @param course_config_service|null $config
     * @param presentation|null $presentation
     * @param synthesis_repository|null $syntheses
     * @param course_section_reader|null $sections
     */
    public function __construct(
        ?access_service $access = null,
        ?course_config_service $config = null,
        ?presentation $presentation = null,
        private ?synthesis_repository $syntheses = null,
        private ?course_section_reader $sections = null
    ) {
        parent::__construct($access, $config, $presentation);
        $this->syntheses ??= new synthesis_repository();
        $this->sections ??= new course_section_reader();
    }

    /**
     * The synthesis in force of each section of a course.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param int|null $groupid must be null (aggregate query)
     * @return array{available: bool, message: string|null, rows: array} each row has `section_id`, `section`, `status`, `version` and `content`
     */
    public function query(int $courseid, int $userid, ?int $groupid = null): array {
        return $this->run($courseid, $userid, $groupid, function () use ($courseid): array {
            $rows = [];
            foreach ($this->sections->get_sections($courseid) as $section) {
                $current = $this->syntheses->find_current($courseid, $section->id);
                $rows[] = ['section_id' => $section->id, 'section' => $section->name] + ($current === null
                    ? ['status' => $this->shown(result_state::NO_DATA, $this->presentation->value(result_state::NO_DATA, null)), 'version' => null, 'content' => null]
                    : ['status' => ['state' => result_state::VALID->value, 'text' => null], 'version' => (int) $current->version, 'content' => (string) $current->content]);
            }
            return $rows;
        });
    }
}
