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
use local_assistente\domain\report\version_filter;
use local_assistente\infrastructure\persistence\forum_analysis_repository;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use local_assistente\infrastructure\persistence\quiz_results_repository;
use local_assistente\infrastructure\persistence\synthesis_repository;
use local_assistente\infrastructure\persistence\table_repository;

/**
 * Query of the history (T110; spec-018 contract "histórico", Q-018-04, DEC-42, spec-017).
 *
 * The previous executions and versions of one entity of the course, newest first, each with its moment and its version: the executions of a
 * questionnaire, the snapshots of a forum, the versions of a material, of the synthesis of a section and of the report of the course. They
 * are immutable and nothing here changes them. The entity comes from a fixed list (history_kind) and a number, never from free text. When
 * the Moodle record that the history came from no longer exists, every entry says "fonte removida" and keeps its content. The history of
 * the individual results of a student (grades, frequency, criteria) is not part of this query.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_query extends aggregate_query {
    /**
     * Constructor; every collaborator can be replaced by tests.
     *
     * @param access_service|null $access
     * @param course_config_service|null $config
     * @param presentation|null $presentation
     * @param source_status_resolver|null $sources
     * @param quiz_initial_repository|null $initial
     * @param quiz_results_repository|null $results
     * @param forum_analysis_repository|null $forums
     * @param material_repository|null $materials
     * @param synthesis_repository|null $syntheses
     */
    public function __construct(
        ?access_service $access = null,
        ?course_config_service $config = null,
        ?presentation $presentation = null,
        private ?source_status_resolver $sources = null,
        private ?quiz_initial_repository $initial = null,
        private ?quiz_results_repository $results = null,
        private ?forum_analysis_repository $forums = null,
        private ?material_repository $materials = null,
        private ?synthesis_repository $syntheses = null
    ) {
        parent::__construct($access, $config, $presentation);
        $this->sources ??= new source_status_resolver();
        $this->initial ??= new quiz_initial_repository();
        $this->results ??= new quiz_results_repository();
        $this->forums ??= new forum_analysis_repository();
        $this->materials ??= new material_repository();
        $this->syntheses ??= new synthesis_repository();
    }

    /**
     * The history of an entity.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param history_kind $kind
     * @param int $id the module (quiz, forum, material) or the section (synthesis); ignored for the report
     * @param int|null $groupid must be null (aggregate query)
     * @param version_filter|null $filter version number and execution dates (Q-020-02, DEC-44), only for the history of the report: it
     *         keeps the versions that pass both limits; without a filter every version is listed, as before. `current` is the mark of the
     *         newest version of the report, whether or not the filter keeps it.
     * @return array{available: bool, message: string|null, rows: array} each row has `type`, `version`, `analysis_id`, `created_at`,
     *         `current`, `content` and `source`; newest first
     * @throws \InvalidArgumentException for an id that is not positive where one is needed, a group, or a filter on another history than
     *         the report (the dates of the other histories are those of the analyses, not of an execution of the report)
     * @throws \local_assistente\application\history\lineage_exception when the record belongs to another course
     */
    public function query(
        int $courseid,
        int $userid,
        history_kind $kind,
        int $id = 0,
        ?int $groupid = null,
        ?version_filter $filter = null
    ): array {
        if ($filter !== null && !$filter->is_empty() && $kind !== history_kind::REPORT) {
            throw new \InvalidArgumentException('The filter by version and execution date applies only to the history of the report.');
        }
        return $this->run($courseid, $userid, $groupid, function () use ($courseid, $kind, $id, $filter): array {
            $source = $this->source($courseid, $kind, $id);
            $entries = match ($kind) {
                history_kind::QUIZ => $this->quiz($courseid, $id),
                history_kind::FORUM => $this->forum($courseid, $id),
                history_kind::MATERIAL => $this->material($courseid, $id),
                history_kind::SYNTHESIS => $this->synthesis($courseid, $id),
                history_kind::REPORT => $this->report($courseid),
            };
            if ($filter !== null && !$filter->is_empty()) {
                $entries = array_values(array_filter(
                    $entries,
                    static fn(array $entry): bool => $filter->matches((int) $entry['version'], (int) $entry['created_at'])
                ));
            }
            return array_map(static fn(array $entry): array => $entry + ['source' => $source], $entries);
        });
    }

    /**
     * The indication of a removed source of the entity.
     *
     * @param int $courseid
     * @param history_kind $kind
     * @param int $id
     * @return string|null
     */
    private function source(int $courseid, history_kind $kind, int $id): ?string {
        $type = match ($kind) {
            history_kind::QUIZ, history_kind::FORUM, history_kind::MATERIAL => 'course_module',
            history_kind::SYNTHESIS => 'course_section',
            history_kind::REPORT => null,
        };
        return $type === null ? null : $this->presentation->source_note($this->sources->status_of($type, $id, $courseid));
    }

    /**
     * An entry of the history.
     *
     * @param string $type
     * @param int|null $version
     * @param int|null $analysis
     * @param int $created
     * @param bool $current
     * @param string|null $content the text of the version, when it has one
     * @return array
     */
    private function entry(string $type, ?int $version, ?int $analysis, int $created, bool $current, ?string $content = null): array {
        return [
            'type' => $type, 'version' => $version, 'analysis_id' => $analysis, 'created_at' => $created, 'current' => $current,
            'content' => $content,
        ];
    }

    /**
     * The executions of a questionnaire.
     *
     * @param int $courseid
     * @param int $cmid
     * @return array[]
     */
    private function quiz(int $courseid, int $cmid): array {
        $entries = [];
        foreach ([['quiz_initial', $this->initial->find_for_quiz($courseid, $cmid)], ['quiz_results', $this->results->find_history($courseid, $cmid)]] as [$type, $analyses]) {
            foreach (array_values($analyses) as $index => $analysis) {
                $entries[] = $this->entry($type, null, (int) $analysis->id, (int) $analysis->created_at, $index === 0);
            }
        }
        usort($entries, static fn(array $a, array $b): int => [$b['created_at'], $b['analysis_id']] <=> [$a['created_at'], $a['analysis_id']]);
        return $entries;
    }

    /**
     * The snapshots of a forum.
     *
     * @param int $courseid
     * @param int $cmid
     * @return array[]
     */
    private function forum(int $courseid, int $cmid): array {
        $entries = [];
        foreach (array_values($this->forums->find_history($courseid, $cmid)) as $index => $analysis) {
            $entries[] = $this->entry('forum_analysis', null, (int) $analysis->id, (int) $analysis->created_at, $index === 0, $analysis->summary === null ? null : (string) $analysis->summary);
        }
        return $entries;
    }

    /**
     * The versions of a material, newest first; the content is the summary of the version when it was completed.
     *
     * @param int $courseid
     * @param int $cmid
     * @return array[]
     */
    private function material(int $courseid, int $cmid): array {
        $material = $this->materials->find_by_cmid($courseid, $cmid);
        if ($material === null) {
            return [];
        }
        $entries = [];
        foreach (array_reverse(array_values($this->materials->find_versions((int) $material->id))) as $version) {
            $completed = $version->summary_status === \local_assistente\domain\material\summary_status::COMPLETED->value;
            $entries[] = $this->entry(
                'material_version', (int) $version->version, null, (int) $version->created_at,
                $material->current_version !== null && (int) $material->current_version === (int) $version->id,
                $completed ? (string) $version->summary : null
            );
        }
        return $entries;
    }

    /**
     * The versions of the synthesis of a section, newest first.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return array[]
     */
    private function synthesis(int $courseid, int $sectionid): array {
        $entries = [];
        foreach (array_values($this->syntheses->find_history($courseid, $sectionid)) as $index => $row) {
            $entries[] = $this->entry('synthesis_version', (int) $row->version, (int) $row->analysis_id, (int) $row->created_at, $index === 0, (string) $row->content);
        }
        return $entries;
    }

    /**
     * The versions of the report of the course, newest first. The snapshot of a report is not shown by this query.
     *
     * @param int $courseid
     * @return array[]
     */
    private function report(int $courseid): array {
        $report = (new table_repository('local_assistente_report'))->find_by(['course_id' => $courseid]);
        if ($report === null) {
            return [];
        }
        $entries = [];
        $versions = (new table_repository('local_assistente_report_version'))->find_all_by(['report_id' => (int) $report->id], 'version DESC');
        foreach (array_values($versions) as $index => $version) {
            $entries[] = $this->entry('report_version', (int) $version->version, null, (int) $version->created_at, $index === 0);
        }
        return $entries;
    }
}
