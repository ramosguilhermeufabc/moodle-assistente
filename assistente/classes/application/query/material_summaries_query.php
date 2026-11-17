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
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\persistence\material_repository;

/**
 * Query of the summaries of the materials (T110; spec-018 contract "resumos de materiais", spec-008).
 *
 * For each material (resource) of the course it shows the summary of the CURRENT version, the one that is completed and chosen by the
 * plugin (DEC-50): older versions are history. A material with no current version yet, because it was not summarized or its summary
 * failed, is "sem dados". The text is returned as it was stored, as text.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class material_summaries_query extends aggregate_query {
    /**
     * Constructor; every collaborator can be replaced by tests.
     *
     * @param access_service|null $access
     * @param course_config_service|null $config
     * @param presentation|null $presentation
     * @param material_repository|null $materials
     * @param course_module_reader|null $modules
     */
    public function __construct(
        ?access_service $access = null,
        ?course_config_service $config = null,
        ?presentation $presentation = null,
        private ?material_repository $materials = null,
        private ?course_module_reader $modules = null
    ) {
        parent::__construct($access, $config, $presentation);
        $this->materials ??= new material_repository();
        $this->modules ??= new course_module_reader();
    }

    /**
     * The summaries of the current versions of the materials of a course.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param int|null $groupid must be null (aggregate query)
     * @return array{available: bool, message: string|null, rows: array} each row has `cmid`, `material`, `status`, `version` and `summary`
     */
    public function query(int $courseid, int $userid, ?int $groupid = null): array {
        return $this->run($courseid, $userid, $groupid, function () use ($courseid): array {
            $rows = [];
            foreach ($this->modules->get_modules($courseid) as $module) {
                if ($module->is_resource) {
                    $rows[] = ['cmid' => $module->cmid, 'material' => $module->name] + $this->material($courseid, $module->cmid);
                }
            }
            return $rows;
        });
    }

    /**
     * The current summary of a material.
     *
     * @param int $courseid
     * @param int $cmid
     * @return array{status: array, version: int|null, summary: string|null}
     */
    private function material(int $courseid, int $cmid): array {
        $material = $this->materials->find_by_cmid($courseid, $cmid);
        $version = $material === null ? null : $this->materials->find_current_version($material);
        if ($version === null) {
            return ['status' => $this->shown(result_state::NO_DATA, $this->presentation->value(result_state::NO_DATA, null)), 'version' => null, 'summary' => null];
        }
        return ['status' => ['state' => result_state::VALID->value, 'text' => null], 'version' => (int) $version->version, 'summary' => (string) $version->summary];
    }
}
