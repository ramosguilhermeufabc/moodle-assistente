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

use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\persistence\criteria_result_repository;
use local_assistente\infrastructure\persistence\forum_analysis_repository;
use local_assistente\infrastructure\persistence\frequency_repository;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\performance_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use local_assistente\infrastructure\persistence\quiz_results_repository;
use local_assistente\infrastructure\persistence\synthesis_repository;

/**
 * Collector of what a report version references (T116; spec-019 contract "Agenda" and "Manifesto"; DEC-39 D1, DEC-44 D1).
 *
 * It runs without a user (scheduled task), so it reads the repositories directly and never the authorized queries of T109/T110. For each block it
 * takes the current analyses, the same ones those queries show: the current analysis of each student for the grades, the frequency and the criteria,
 * of each questionnaire (the analyses of the questionnaire and the current results), of each forum, the current summary of each material and the
 * current version of the synthesis of each section. It returns the blocks in the form {@see report_builder::build()} takes. A block with no reference
 * is `no_data`; a failure to read is not turned into a state, it is thrown, so that the execution fails as a whole (DEC-17).
 *
 * The activities that fix the final date are the eligible ones of DEC-39 D1 that have a date of their own (due date, closing time or equivalent,
 * {@see course_module_reader::DUE_DATE_FIELDS}); `course.enddate` is never used (contract "Agenda"). Responsibility decided on 28/10/2026: this collector
 * is the place where the dispatcher learns what a version references; the builder only organizes and the repository only persists.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_collector {
    /**
     * Constructor.
     *
     * @param course_module_reader|null $modules
     * @param course_section_reader|null $sections
     * @param student_reader|null $students
     * @param performance_repository|null $performance
     * @param frequency_repository|null $frequency
     * @param criteria_result_repository|null $criteria
     * @param quiz_initial_repository|null $initial
     * @param quiz_results_repository|null $results
     * @param forum_analysis_repository|null $forums
     * @param material_repository|null $materials
     * @param synthesis_repository|null $syntheses
     */
    public function __construct(
        private ?course_module_reader $modules = null,
        private ?course_section_reader $sections = null,
        private ?student_reader $students = null,
        private ?performance_repository $performance = null,
        private ?frequency_repository $frequency = null,
        private ?criteria_result_repository $criteria = null,
        private ?quiz_initial_repository $initial = null,
        private ?quiz_results_repository $results = null,
        private ?forum_analysis_repository $forums = null,
        private ?material_repository $materials = null,
        private ?synthesis_repository $syntheses = null
    ) {
        $this->modules ??= new course_module_reader();
        $this->sections ??= new course_section_reader();
        $this->students ??= new student_reader();
        $this->performance ??= new performance_repository();
        $this->frequency ??= new frequency_repository();
        $this->criteria ??= new criteria_result_repository();
        $this->initial ??= new quiz_initial_repository();
        $this->results ??= new quiz_results_repository();
        $this->forums ??= new forum_analysis_repository();
        $this->materials ??= new material_repository();
        $this->syntheses ??= new synthesis_repository();
    }

    /**
     * The own dates of the eligible activities of a course, for {@see \local_assistente\domain\report\report_schedule::calculate()}.
     *
     * @param int $courseid
     * @return int[] Unix instants; [] when no activity has a date
     */
    public function activity_dates(int $courseid): array {
        $dates = [];
        foreach ($this->modules->get_modules($courseid) as $module) {
            if ($this->is_eligible($module) && $module->due_date !== null) {
                $dates[] = (int) $module->due_date;
            }
        }
        return $dates;
    }

    /**
     * Whether an activity is eligible (DEC-39 D1): a visible activity of Moodle, not a didactic resource (page, file, URL, book, folder, label),
     * that meets at least one category: numeric or scale grade, completion enabled, submission (assignment) or forum. The availability to each
     * student (DEC-26) does not apply to the date of the report, which belongs to the course.
     *
     * @param \stdClass $module as {@see course_module_reader::get_modules()} gives
     * @return bool
     */
    private function is_eligible(\stdClass $module): bool {
        if ($module->is_resource || !$module->visible) {
            return false;
        }
        return $module->gradable || $module->completion_enabled || in_array($module->modname, ['assign', 'forum'], true);
    }

    /**
     * The blocks of a manifest for a course, with the current references.
     *
     * @param int $courseid
     * @return array<string, array> block name => input of {@see report_builder::build()}
     */
    public function blocks(int $courseid): array {
        $studentids = $this->students->get_students($courseid);
        $modules = $this->modules->get_modules($courseid);

        $grades = $frequency = $criteria = [];
        foreach ($studentids as $studentid) {
            $grades[] = $this->performance->find_current_for_student($courseid, $studentid)?->id;
            $frequency[] = $this->frequency->find_current_for_student($courseid, $studentid)?->id;
            $criteria[] = $this->criteria->find_current_for_student($courseid, $studentid)?->id;
        }

        $quizzes = $forums = $materials = [];
        foreach ($modules as $module) {
            if ($module->modname === 'quiz') {
                foreach ($this->initial->find_for_quiz($courseid, $module->cmid) as $analysis) {
                    $quizzes[] = $analysis->id;
                }
                $quizzes[] = $this->results->find_current($courseid, $module->cmid)?->id;
            } else if ($module->modname === 'forum') {
                $forums[] = $this->forums->find_current($courseid, $module->cmid)?->id;
            } else if ($module->is_resource) {
                $material = $this->materials->find_by_cmid($courseid, $module->cmid);
                $version = $material === null ? null : $this->materials->find_current_version($material);
                if ($version !== null && $version->summary_status === 'completed') {
                    $materials[] = $version->id;
                }
            }
        }

        $syntheses = [];
        foreach ($this->sections->get_sections($courseid) as $section) {
            $current = $this->syntheses->find_current($courseid, $section->id);
            if ($current !== null) {
                $syntheses[(int) $section->id] = (int) $current->version;
            }
        }

        return [
            report_builder::BLOCK_GRADES => $this->block(analysis_ids: $grades),
            report_builder::BLOCK_QUIZ_METRICS => $this->block(analysis_ids: $quizzes),
            report_builder::BLOCK_FREQUENCY => $this->block(analysis_ids: $frequency),
            report_builder::BLOCK_FORUMS => $this->block(analysis_ids: $forums),
            report_builder::BLOCK_BELOW_CRITERIA => $this->block(analysis_ids: $criteria),
            report_builder::BLOCK_MATERIAL_SUMMARIES => $this->block(material_versions: $materials),
            report_builder::BLOCK_SECTION_SYNTHESIS => $this->block(synthesis_versions: $syntheses),
        ];
    }

    /**
     * One block: valid with its references, or no_data without any.
     *
     * @param array $analysis_ids ids, nulls are ignored
     * @param array $material_versions ids, nulls are ignored
     * @param array $synthesis_versions section id => version
     * @return array
     */
    private function block(array $analysis_ids = [], array $material_versions = [], array $synthesis_versions = []): array {
        $analysis_ids = array_values(array_filter(array_map(static fn($id) => $id === null ? null : (int) $id, $analysis_ids)));
        $material_versions = array_values(array_filter(array_map(static fn($id) => $id === null ? null : (int) $id, $material_versions)));
        if (!$analysis_ids && !$material_versions && !$synthesis_versions) {
            return ['state' => 'no_data'];
        }
        return [
            'state' => 'valid',
            'analysis_ids' => $analysis_ids,
            'material_versions' => $material_versions,
            'synthesis_versions' => $synthesis_versions,
        ];
    }
}
