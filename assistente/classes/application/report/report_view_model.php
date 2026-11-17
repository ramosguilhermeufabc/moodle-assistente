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

use local_assistente\application\access_service;
use local_assistente\application\course_config_service;
use local_assistente\application\history\source_status_resolver;
use local_assistente\application\query\presentation;
use local_assistente\application\query\student_scope;
use local_assistente\domain\criteria_status;
use local_assistente\domain\report\version_filter;
use local_assistente\domain\result_state;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\persistence\criteria_result_repository;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\forum_analysis_repository;
use local_assistente\infrastructure\persistence\frequency_repository;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\performance_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use local_assistente\infrastructure\persistence\quiz_results_repository;
use local_assistente\infrastructure\persistence\report_repository;
use local_assistente\infrastructure\persistence\synthesis_repository;
use local_assistente\infrastructure\persistence\table_repository;

/**
 * Reading of a report: renders a stored manifest into the values of the analyses it references (spec-019 contract "Renderização"; DEC-42 D1,
 * DEC-43 D2, DEC-44 D1; T115).
 *
 * Rules kept here:
 * - The backend authorizes first, with `local/assistente:view` in the course (access_service::require_view), before anything is said about the
 *   report; then a course where the assistant is not enabled shows nothing (DEC-45 D3).
 * - The values come from the analyses the manifest references, not from the current ones, so an old version shows what it was built from. An
 *   analysis of another course than the report's is ignored.
 * - The individual blocks (grades, frequency, below criteria) are restricted to the students the user may see: the group filter and the separate
 *   groups of student_scope apply, and a student who is no longer in that list (removed or erased from the course) does not appear, even when
 *   a reference of the manifest still points to an analysis that holds the student's data (DEC-42 D1, DEC-43 D2). The aggregate blocks (questionnaires,
 *   forums, material summaries, synthesis) are never filtered by group.
 * - Values go through presentation (truncation, states); texts are returned as stored and whoever displays them escapes them.
 * - A reference whose record no longer exists is left out of the block. A block with state `no_data` or `error` in the manifest is shown with
 *   that state and without rows.
 *
 * It writes nothing and calls no LLM.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_view_model {
    /** @var table_repository the analysis table */
    private table_repository $analyses;

    /** @var table_repository the processing table */
    private table_repository $processings;

    /** @var table_repository the material table */
    private table_repository $materials_table;

    /** @var array{modules: array<int, string>, sections: array<int, string>} names of the modules and sections of the course being rendered */
    private array $names = ['modules' => [], 'sections' => []];

    /**
     * Constructor.
     *
     * @param report_repository|null $reports
     * @param report_builder|null $builder
     * @param access_service|null $access
     * @param course_config_service|null $config
     * @param presentation|null $presentation
     * @param student_scope|null $scope
     * @param performance_repository|null $performance
     * @param frequency_repository|null $frequency
     * @param criteria_result_repository|null $criteria
     * @param quiz_initial_repository|null $initial
     * @param quiz_results_repository|null $results
     * @param forum_analysis_repository|null $forums
     * @param material_repository|null $materials
     * @param synthesis_repository|null $syntheses
     * @param event_repository|null $events
     * @param source_status_resolver|null $sources
     * @param course_module_reader|null $modules
     * @param course_section_reader|null $sections
     */
    public function __construct(
        private ?report_repository $reports = null,
        private ?report_builder $builder = null,
        private ?access_service $access = null,
        private ?course_config_service $config = null,
        private ?presentation $presentation = null,
        private ?student_scope $scope = null,
        private ?performance_repository $performance = null,
        private ?frequency_repository $frequency = null,
        private ?criteria_result_repository $criteria = null,
        private ?quiz_initial_repository $initial = null,
        private ?quiz_results_repository $results = null,
        private ?forum_analysis_repository $forums = null,
        private ?material_repository $materials = null,
        private ?synthesis_repository $syntheses = null,
        private ?event_repository $events = null,
        private ?source_status_resolver $sources = null,
        private ?course_module_reader $modules = null,
        private ?course_section_reader $sections = null
    ) {
        $this->reports ??= new report_repository();
        $this->builder ??= new report_builder();
        $this->access ??= new access_service();
        $this->config ??= new course_config_service();
        $this->presentation ??= new presentation();
        $this->scope ??= new student_scope();
        $this->performance ??= new performance_repository();
        $this->frequency ??= new frequency_repository();
        $this->criteria ??= new criteria_result_repository();
        $this->initial ??= new quiz_initial_repository();
        $this->results ??= new quiz_results_repository();
        $this->forums ??= new forum_analysis_repository();
        $this->materials ??= new material_repository();
        $this->syntheses ??= new synthesis_repository();
        $this->events ??= new event_repository();
        $this->sources ??= new source_status_resolver();
        $this->modules ??= new course_module_reader();
        $this->sections ??= new course_section_reader();
        $this->analyses = new table_repository('local_assistente_analysis');
        $this->processings = new table_repository('local_assistente_processing');
        $this->materials_table = new table_repository('local_assistente_material');
    }

    /**
     * The current version of the report of a course.
     *
     * @param int $courseid
     * @param int $userid the user that reads
     * @param int|null $groupid filter by group for the individual blocks (DEC-43 D1)
     * @param version_filter|null $filter version number and execution dates (Q-020-02, DEC-44). Without a filter the current version is
     *     shown. With one, the newest version that passes it is shown (by number and by execution date, both inclusive), rendered from the
     *     analyses its own manifest references, as any old version is.
     * @return array{available: bool, message: string|null, rows: array} `rows` is [] when the course has no version yet or none passes
     *     the filter, otherwise one entry `['version', 'type', 'generated_at', 'blocks']` (see {@see self::render()})
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     */
    public function current(int $courseid, int $userid, ?int $groupid = null, ?version_filter $filter = null): array {
        $this->access->require_view($courseid, $userid);
        return $this->presentation->results($this->config->state($courseid), function () use ($courseid, $userid, $groupid, $filter): array {
            $report = $this->reports->find_by_course($courseid);
            if ($report === null) {
                return [];
            }
            $version = $filter === null || $filter->is_empty()
                ? $this->reports->find_current_version((int) $report->id)
                : $this->newest_passing((int) $report->id, $filter);
            return $version === null ? [] : [$this->render($courseid, $userid, $version, $groupid)];
        });
    }

    /**
     * The versions of the report that the filter of the page can offer, with their execution date.
     *
     * Read only; the number is the one that version_filter selects.
     *
     * @param int $courseid
     * @param int $userid
     * @return array<int, int> version number => execution instant, newest first; [] when the assistant is not enabled or there is no version
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     */
    public function versions(int $courseid, int $userid): array {
        $this->access->require_view($courseid, $userid);
        if (!$this->config->state($courseid)->is_enabled()) {
            return [];
        }
        $report = $this->reports->find_by_course($courseid);
        $found = [];
        foreach ($report === null ? [] : $this->reports->find_history((int) $report->id) as $version) {
            $found[(int) $version->version] = (int) $version->created_at;
        }
        return $found;
    }

    /**
     * The newest version of a report that passes the filter.
     *
     * @param int $reportid
     * @param version_filter $filter
     * @return \stdClass|null
     */
    private function newest_passing(int $reportid, version_filter $filter): ?\stdClass {
        foreach ($this->reports->find_history($reportid) as $version) {
            if ($filter->matches((int) $version->version, (int) $version->created_at)) {
                return $version;
            }
        }
        return null;
    }

    /**
     * The versions of the report of a course, newest first, rendered (an old version shows the values of its referenced analyses).
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $groupid
     * @return array{available: bool, message: string|null, rows: array} one entry for each version
     * @throws \core\exception\required_capability_exception without `local/assistente:view` in the course
     */
    public function history(int $courseid, int $userid, ?int $groupid = null): array {
        $this->access->require_view($courseid, $userid);
        return $this->presentation->results($this->config->state($courseid), function () use ($courseid, $userid, $groupid): array {
            $report = $this->reports->find_by_course($courseid);
            $rows = [];
            foreach ($report === null ? [] : $this->reports->find_history((int) $report->id) as $version) {
                $rows[] = $this->render($courseid, $userid, $version, $groupid);
            }
            return $rows;
        });
    }

    /**
     * Render one stored version.
     *
     * @param int $courseid
     * @param int $userid
     * @param \stdClass $version the `report_version` row
     * @param int|null $groupid
     * @return array{version: int, type: string, generated_at: int, blocks: array<string, array{state: string, rows: array}>}
     */
    private function render(int $courseid, int $userid, \stdClass $version, ?int $groupid): array {
        $manifest = $this->builder->read((string) $version->snapshot_json);
        $students = $this->scope->individual($courseid, $userid, $groupid);
        $this->names = [
            'modules' => array_map(static fn($m): string => $m->name, $this->modules->get_modules($courseid)),
            'sections' => array_column(array_map(static fn($s): array => ['id' => (int) $s->id, 'name' => (string) $s->name], $this->sections->get_sections($courseid)), 'name', 'id'),
        ];
        $blocks = [];
        foreach ($manifest['blocks'] as $name => $block) {
            $rows = [];
            if ($block['state'] === result_state::VALID->value) {
                $rows = match ($name) {
                    report_builder::BLOCK_GRADES => $this->grades($courseid, $block['analysis_ids'], $students),
                    report_builder::BLOCK_FREQUENCY => $this->frequency($courseid, $block['analysis_ids'], $students),
                    report_builder::BLOCK_BELOW_CRITERIA => $this->below_criteria($courseid, $block['analysis_ids'], $students),
                    report_builder::BLOCK_QUIZ_METRICS => $this->quizzes($courseid, $block['analysis_ids']),
                    report_builder::BLOCK_FORUMS => $this->forums($courseid, $block['analysis_ids']),
                    report_builder::BLOCK_MATERIAL_SUMMARIES => $this->material_summaries($courseid, $block['material_versions']),
                    report_builder::BLOCK_SECTION_SYNTHESIS => $this->synthesis($courseid, $block['synthesis_versions']),
                };
            }
            $blocks[$name] = ['state' => $block['state'], 'rows' => $rows];
        }
        return [
            'version' => (int) $version->version,
            'type' => $manifest['type'],
            'generated_at' => $manifest['generated_at'],
            'blocks' => $blocks,
        ];
    }

    /**
     * The analyses of the ids that belong to the course (and, when given, to the type), by id.
     *
     * @param int $courseid
     * @param int[] $ids
     * @param string|null $type
     * @return \stdClass[]
     */
    private function analyses_of(int $courseid, array $ids, ?string $type = null): array {
        $found = [];
        foreach ($ids as $id) {
            $analysis = $this->analyses->find($id);
            if ($analysis !== null && (int) $analysis->course_id === $courseid && ($type === null || $analysis->analysis_type === $type)) {
                $found[$id] = $analysis;
            }
        }
        return $found;
    }

    /**
     * Id of the student an execution of the grades was about, from the payload of its event.
     *
     * @param \stdClass $analysis
     * @return int|null
     */
    private function performance_student(\stdClass $analysis): ?int {
        $processing = $this->processings->find((int) $analysis->processing_id);
        $event = $processing === null ? null : $this->events->find((int) $processing->event_id);
        $payload = $event === null ? null : json_decode((string) $event->payload, true);
        return is_array($payload) && is_int($payload['userid'] ?? null) ? $payload['userid'] : null;
    }

    /**
     * @param int $courseid
     * @param int[] $ids
     * @param array[] $students visible students
     * @return array[]
     */
    private function grades(int $courseid, array $ids, array $students): array {
        $by = [];
        foreach ($this->analyses_of($courseid, $ids, 'grade_performance') as $analysis) {
            $studentid = $this->performance_student($analysis);
            if ($studentid !== null) {
                $by[$studentid] = $analysis;
            }
        }
        $rows = [];
        foreach ($students as $student) {
            if (!isset($by[$student['id']])) {
                continue;
            }
            $activities = [];
            foreach ($this->performance->find_rows((int) $by[$student['id']]->id) as $row) {
                $cmid = (int) $row->activity_id;
                $activities[] = [
                    'activity_id' => $cmid,
                    'activity' => $this->names['modules'][$cmid] ?? null,
                    'grade' => $this->shown(result_state::VALID, $row->grade),
                    'source' => $this->presentation->source_note($this->sources->status_of('course_module', $cmid, $courseid)),
                ];
            }
            $rows[] = $student + ['activities' => $activities];
        }
        return $this->presentation->sort_by_name($rows);
    }

    /**
     * @param int $courseid
     * @param int[] $ids
     * @param array[] $students
     * @return array[]
     */
    private function frequency(int $courseid, array $ids, array $students): array {
        $visible = array_column($students, null, 'id');
        $rows = [];
        foreach ($this->analyses_of($courseid, $ids, 'frequency') as $analysis) {
            $by = [];
            foreach ($this->frequency->find_frequencies((int) $analysis->id) as $row) {
                if (isset($visible[(int) $row->student_id])) {
                    $state = (int) $row->total_activities === 0 ? result_state::NO_DATA : result_state::VALID;
                    $by[(int) $row->student_id][] = [
                        'section_id' => (int) $row->section_id,
                        'section' => $this->names['sections'][(int) $row->section_id] ?? null,
                        'total' => (int) $row->total_activities,
                        'participating' => (int) $row->participating_activities,
                        'percentage' => $this->shown($state, $row->percentage),
                    ];
                }
            }
            foreach ($by as $studentid => $sections) {
                $rows[] = $visible[$studentid] + ['course_access' => $this->course_access($analysis, $studentid), 'sections' => $sections];
            }
        }
        return $this->presentation->sort_by_name($rows);
    }

    /**
     * @param int $courseid
     * @param int[] $ids
     * @param array[] $students
     * @return array[]
     */
    private function below_criteria(int $courseid, array $ids, array $students): array {
        $visible = array_column($students, null, 'id');
        $dimensions = ['activity_status' => 'activity', 'forum_status' => 'forum', 'frequency_status' => 'frequency'];
        $rows = [];
        foreach ($this->analyses_of($courseid, $ids, 'criteria_check') as $analysis) {
            foreach ($this->criteria->find_results((int) $analysis->id) as $result) {
                if (!isset($visible[(int) $result->student_id])) {
                    continue;
                }
                $criteria = [];
                $below = false;
                foreach ($dimensions as $column => $name) {
                    $status = criteria_status::from((int) $result->{$column});
                    $below = $below || $status === criteria_status::BELOW;
                    $criteria[$name] = ['status' => $status->value, 'text' => $this->presentation->criteria($status)];
                }
                if ($below) {
                    $rows[] = $visible[(int) $result->student_id] + ['criteria' => $criteria];
                }
            }
        }
        return $this->presentation->sort_by_name($rows);
    }

    /**
     * Coverage (analysis of the questionnaire) and metrics (analysis of the results), one entry for each referenced analysis.
     *
     * @param int $courseid
     * @param int[] $ids
     * @return array[]
     */
    private function quizzes(int $courseid, array $ids): array {
        $rows = [];
        foreach ($this->analyses_of($courseid, $ids) as $analysis) {
            $cmid = $analysis->cmid === null ? null : (int) $analysis->cmid;
            $row = ['analysis_id' => (int) $analysis->id, 'cmid' => $cmid, 'quiz' => $this->names['modules'][$cmid] ?? null, 'type' => $analysis->analysis_type];
            if ($analysis->analysis_type === 'quiz_initial') {
                $qa = $this->initial->find_questionnaire_analysis((int) $analysis->id);
                $row['coverage'] = $qa === null ? null : [
                    'state' => result_state::VALID->value,
                    'text' => $this->presentation->coverage(result_state::VALID, $qa->coverage, $this->had_materials($analysis)),
                ];
            } else if ($analysis->analysis_type === 'quiz_results') {
                $metric = $this->results->find_metric((int) $analysis->id);
                $row['metrics'] = $metric === null ? null : [
                    'mean' => $this->shown(result_state::VALID, $metric->mean),
                    'population_standard_deviation' => $this->shown(result_state::VALID, $metric->population_standard_deviation),
                    'minimum' => $this->shown(result_state::VALID, $metric->minimum),
                    'maximum' => $this->shown(result_state::VALID, $metric->maximum),
                ];
            } else {
                continue;
            }
            $rows[] = $row;
        }
        return $this->ordered($rows, ['cmid', 'analysis_id']);
    }

    /**
     * @param int $courseid
     * @param int[] $ids
     * @return array[]
     */
    private function forums(int $courseid, array $ids): array {
        $rows = [];
        foreach ($this->analyses_of($courseid, $ids, 'forum_analysis') as $analysis) {
            $difficulties = [];
            foreach ($this->forums->find_difficulties((int) $analysis->id) as $difficulty) {
                $discussion = (int) $difficulty->discussion_id;
                $difficulties[] = [
                    'discussion_id' => $discussion,
                    'description' => (string) $difficulty->description,
                    'source' => $this->presentation->source_note($this->sources->status_of('forum_discussion', $discussion, $courseid)),
                ];
            }
            $cmid = $analysis->cmid === null ? null : (int) $analysis->cmid;
            $rows[] = [
                'analysis_id' => (int) $analysis->id,
                'cmid' => $cmid,
                'forum' => $this->names['modules'][$cmid] ?? null,
                'summary' => $analysis->summary === null ? null : (string) $analysis->summary,
                'difficulties' => $difficulties,
            ];
        }
        return $this->ordered($rows, ['cmid', 'analysis_id']);
    }

    /**
     * @param int $courseid
     * @param int[] $versionids
     * @return array[]
     */
    private function material_summaries(int $courseid, array $versionids): array {
        $rows = [];
        foreach ($versionids as $versionid) {
            $version = $this->materials->find_version_by_id($versionid);
            $material = $version === null ? null : $this->materials_table->find((int) $version->material_id);
            if ($material === null || (int) $material->course_id !== $courseid) {
                continue;
            }
            $rows[] = [
                'cmid' => (int) $material->cmid,
                'material' => (string) $material->name,
                'version' => (int) $version->version,
                'summary' => $version->summary === null ? null : (string) $version->summary,
            ];
        }
        return $this->ordered($rows, ['cmid', 'version']);
    }

    /**
     * @param int $courseid
     * @param array<int, int> $sections section id => version
     * @return array[]
     */
    private function synthesis(int $courseid, array $sections): array {
        $rows = [];
        foreach ($sections as $sectionid => $number) {
            $version = $this->syntheses->find_version($courseid, $sectionid, $number);
            if ($version !== null) {
                $rows[] = [
                    'section_id' => $sectionid,
                    'section' => $this->names['sections'][$sectionid] ?? null,
                    'version' => $number,
                    'content' => (string) $version->content,
                ];
            }
        }
        return $this->ordered($rows, ['section_id']);
    }

    /**
     * Whether the execution of the questionnaire analysis had materials (the coverage 0.0 reads "sem materiais" only without them, DEC-35 D2).
     *
     * @param \stdClass $analysis
     * @return bool
     */
    private function had_materials(\stdClass $analysis): bool {
        $context = $this->events->find_context((int) $analysis->processing_id);
        $snapshot = $context === null ? null : json_decode((string) $context->context_json, true);
        return !is_array($snapshot) || !array_key_exists('materials', $snapshot) || !is_array($snapshot['materials']) || $snapshot['materials'] !== [];
    }

    /**
     * The rows in a fixed order (the order the manifest lists them in is not a promise), by the given keys, ascending.
     *
     * @param array[] $rows
     * @param string[] $keys
     * @return array[]
     */
    private function ordered(array $rows, array $keys): array {
        usort($rows, static function (array $a, array $b) use ($keys): int {
            foreach ($keys as $key) {
                $order = ($a[$key] ?? 0) <=> ($b[$key] ?? 0);
                if ($order !== 0) {
                    return $order;
                }
            }
            return 0;
        });
        return $rows;
    }

    /**
     * The access to the course that the snapshot of the execution registered for the student (DEC-39 D3), as evidence next to the percentage.
     *
     * @param \stdClass $analysis the frequency analysis
     * @param int $studentid
     * @return string|null null when the snapshot says nothing about the student
     */
    private function course_access(\stdClass $analysis, int $studentid): ?string {
        $context = $this->events->find_context((int) $analysis->processing_id);
        $snapshot = $context === null ? null : json_decode((string) $context->context_json, true);
        foreach (is_array($snapshot) ? ($snapshot['course_access'] ?? []) : [] as $entry) {
            if (is_array($entry) && ($entry['student_id'] ?? null) === $studentid) {
                $last = isset($entry['last_access']) && is_int($entry['last_access']) && $entry['last_access'] > 0 ? $entry['last_access'] : null;
                return $this->presentation->course_access(isset($entry['accessed']) ? (bool) $entry['accessed'] : null, $last);
            }
        }
        return null;
    }

    /**
     * A value with its state and its text.
     *
     * @param result_state $state
     * @param float|int|string|null $value
     * @return array{state: string, text: string}
     */
    private function shown(result_state $state, float|int|string|null $value): array {
        return ['state' => $state->value, 'text' => $this->presentation->value($state, $value)];
    }
}
