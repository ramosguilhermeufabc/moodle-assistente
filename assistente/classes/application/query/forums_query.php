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
use local_assistente\application\history\source_status_resolver;
use local_assistente\domain\result_state;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\persistence\forum_analysis_repository;

/**
 * Query of the forums (T110; spec-018 contract "fóruns", DEC-38, DEC-42 D3).
 *
 * For each forum of the course it shows the CURRENT snapshot, the latest successful `forum_analysis` (spec-013): the summary of the forum
 * and the difficulties by discussion. Difficulties removed from the current snapshot stay only in the history. A forum with no snapshot is
 * "sem dados"; a discussion that Moodle no longer has keeps its difficulty and says "fonte removida". The texts are returned as they were
 * stored, as text; escaping them is the job of the renderer.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class forums_query extends aggregate_query {
    /**
     * Constructor; every collaborator can be replaced by tests.
     *
     * @param access_service|null $access
     * @param course_config_service|null $config
     * @param presentation|null $presentation
     * @param forum_analysis_repository|null $forums
     * @param source_status_resolver|null $sources
     * @param course_module_reader|null $modules
     */
    public function __construct(
        ?access_service $access = null,
        ?course_config_service $config = null,
        ?presentation $presentation = null,
        private ?forum_analysis_repository $forums = null,
        private ?source_status_resolver $sources = null,
        private ?course_module_reader $modules = null
    ) {
        parent::__construct($access, $config, $presentation);
        $this->forums ??= new forum_analysis_repository();
        $this->sources ??= new source_status_resolver();
        $this->modules ??= new course_module_reader();
    }

    /**
     * The current snapshot of the forums of a course.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param int|null $groupid must be null (aggregate query)
     * @return array{available: bool, message: string|null, rows: array} each row has `cmid`, `forum`, `status`, `summary` and `difficulties`
     */
    public function query(int $courseid, int $userid, ?int $groupid = null): array {
        return $this->run($courseid, $userid, $groupid, function () use ($courseid): array {
            $rows = [];
            foreach ($this->modules->get_modules($courseid) as $module) {
                if ($module->modname === 'forum') {
                    $rows[] = ['cmid' => $module->cmid, 'forum' => $module->name] + $this->forum($courseid, $module->cmid);
                }
            }
            return $rows;
        });
    }

    /**
     * The current snapshot of a forum.
     *
     * @param int $courseid
     * @param int $cmid
     * @return array{status: array, summary: string|null, difficulties: array}
     */
    private function forum(int $courseid, int $cmid): array {
        $analysis = $this->forums->find_current($courseid, $cmid);
        if ($analysis === null) {
            return ['status' => $this->shown(result_state::NO_DATA, $this->presentation->value(result_state::NO_DATA, null)), 'summary' => null, 'difficulties' => []];
        }
        $difficulties = [];
        foreach ($this->forums->find_difficulties((int) $analysis->id) as $difficulty) {
            $discussion = (int) $difficulty->discussion_id;
            $difficulties[] = [
                'discussion_id' => $discussion,
                'description' => (string) $difficulty->description,
                'source' => $this->presentation->source_note($this->sources->status_of('forum_discussion', $discussion, $courseid)),
            ];
        }
        return [
            'status' => ['state' => result_state::VALID->value, 'text' => null],
            'summary' => $analysis->summary === null ? null : (string) $analysis->summary,
            'difficulties' => $difficulties,
        ];
    }
}
