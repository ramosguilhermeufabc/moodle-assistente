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

namespace local_assistente\application\context;

use local_assistente\domain\material\summary_status;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\material_repository;

/**
 * Builds the context snapshot of an analysis: the materials of one section that may feed the model (T064; spec-008; DEC-26,
 * DEC-51).
 *
 * Eligibility of a material, all conditions together:
 * - it belongs to the section of the analysis, as Moodle holds it now (course_module_reader), not as `material.section_id` was
 *   stored: moving a module emits no event in Moodle 5.2, so the stored value may be stale (Q9). Nothing is written here, and a
 *   move creates no version;
 * - its current version exists and was processed successfully (`summary_status` completed, DEC-50);
 * - it is available to EVERY relevant student, evaluated now, for this course and these students (DEC-26), by the access
 *   restrictions of Moodle (course_module_reader, per user). A material available only to part of the students is summarised
 *   but stays out of the context: it is never treated as universal.
 *
 * The availability is evaluated here and the result is written in the snapshot. The values persisted in
 * `material.availability` (latest known) and `material_version.availability` (historical, immutable) are not read and are not
 * changed: a later change of any of them, or of the access restrictions, never alters a snapshot already built. Availability,
 * summary status and pending extraction are independent: the extraction state does not decide eligibility here.
 *
 * A material whose module no longer resolves in the course (removed) has no section and is not listed; the policy for removed
 * sources is open (CL-15).
 *
 * Gap kept explicit: with no relevant student there is nobody to whom the material could be available, so nothing is eligible
 * (reason `no_students`); the specification does not define this case and the conservative reading was adopted.
 *
 * Structure of `processing_context.context_json` (Q-008-06), format 1, JSON object:
 * - `format` (int): 1.
 * - `course_id`, `section_id` (int): scope of the analysis.
 * - `language` (string): generation language (forced course language, else site default).
 * - `created_at` (int): moment of the evaluation.
 * - `students_evaluated` (int): number of relevant students; their identities are not stored (DEC-34 D3).
 * - `materials` (list), one item per registered material of the section, ordered by `cmid`:
 *   - `material_id`, `cmid` (int), `name`, `material_type` (string);
 *   - `version_id`, `version` (int|null): the current version used; null when there is none;
 *   - `availability`: result of the evaluation: `coverage` (`all`, `partial` or `none`), `available_students`,
 *     `total_students` (int);
 *   - `eligible` (bool) and `reason` (string|null): `no_current_version`, `summary_not_completed`, `not_available_to_all`,
 *     `no_students`; null when eligible;
 *   - `summary` (string): only for eligible materials.
 * - `used_versions` (list of int): ids of the versions whose summary is in the context.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class context_snapshot_builder {
    /** @var int version of the structure of the snapshot */
    public const FORMAT = 1;

    /** @var material_repository */
    private material_repository $materials;

    /** @var course_module_reader */
    private course_module_reader $modules;

    /** @var course_section_reader */
    private course_section_reader $sections;

    /**
     * Constructor.
     *
     * @param material_repository|null $materials
     * @param course_module_reader|null $modules
     * @param course_section_reader|null $sections
     */
    public function __construct(
        ?material_repository $materials = null,
        ?course_module_reader $modules = null,
        ?course_section_reader $sections = null
    ) {
        $this->materials = $materials ?? new material_repository();
        $this->modules = $modules ?? new course_module_reader();
        $this->sections = $sections ?? new course_section_reader();
    }

    /**
     * Evaluate the materials of a section for the relevant students and return the snapshot.
     *
     * @param int $courseid
     * @param int $sectionid course_sections.id
     * @param int[] $studentids the students relevant to the analysis
     * @param int|null $now
     * @return array the snapshot, in the structure documented in the class
     */
    public function build(int $courseid, int $sectionid, array $studentids, ?int $now = null): array {
        $studentids = array_values(array_unique(array_map('intval', $studentids)));
        $total = count($studentids);

        // One reading of the course per student: the availability of Moodle is evaluated for each user.
        $visible = $this->modules->count_available_to_users($courseid, $studentids);

        $items = [];
        $used = [];
        $registered = $this->materials->find_all_by(['course_id' => $courseid], 'cmid ASC');
        $current = $this->modules->get_modules($courseid);
        foreach ($registered as $material) {
            // The section is the one Moodle holds now, not the stored one, which a move leaves stale (Q9). A module that cannot be
            // resolved (removed) has no section and stays out.
            if (($current[(int) $material->cmid]->section_id ?? null) !== $sectionid) {
                continue;
            }
            $available = $visible[(int) $material->cmid] ?? 0;
            $version = $this->materials->find_current_version($material);
            $reason = $this->ineligibility($version, $available, $total);

            $item = [
                'material_id' => (int) $material->id,
                'cmid' => (int) $material->cmid,
                'name' => $material->name,
                'material_type' => $material->material_type,
                'version_id' => $version === null ? null : (int) $version->id,
                'version' => $version === null ? null : (int) $version->version,
                'availability' => [
                    'coverage' => $this->coverage($available, $total)->value,
                    'available_students' => $available,
                    'total_students' => $total,
                ],
                'eligible' => $reason === null,
                'reason' => $reason,
            ];
            if ($reason === null) {
                $item['summary'] = $version->summary;
                $used[] = (int) $version->id;
            }
            $items[] = $item;
        }

        return [
            'format' => self::FORMAT,
            'course_id' => $courseid,
            'section_id' => $sectionid,
            'language' => $this->sections->generation_language($courseid),
            'created_at' => $now ?? time(),
            'students_evaluated' => $total,
            'materials' => $items,
            'used_versions' => $used,
        ];
    }

    /**
     * Build the snapshot and store it, immutable, for a processing.
     *
     * @param int $processingid
     * @param int $courseid
     * @param int $sectionid
     * @param int[] $studentids
     * @param event_repository|null $events
     * @param int|null $now
     * @return array the snapshot stored
     * @throws \local_assistente\infrastructure\persistence\duplicate_record_exception when the processing already has one
     */
    public function build_and_save(
        int $processingid,
        int $courseid,
        int $sectionid,
        array $studentids,
        ?event_repository $events = null,
        ?int $now = null
    ): array {
        $now ??= time();
        $snapshot = $this->build($courseid, $sectionid, $studentids, $now);
        ($events ?? new event_repository())->save_context(
            $processingid,
            json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $now
        );
        return $snapshot;
    }

    /**
     * Why a material stays out of the context.
     *
     * @param \stdClass|null $version the current version
     * @param int $available students to whom the material is available
     * @param int $total relevant students
     * @return string|null null when eligible
     */
    private function ineligibility(?\stdClass $version, int $available, int $total): ?string {
        if ($version === null) {
            return 'no_current_version';
        }
        if ($version->summary_status !== summary_status::COMPLETED->value) {
            return 'summary_not_completed';
        }
        if ($total === 0) {
            return 'no_students';
        }
        return $available === $total ? null : 'not_available_to_all';
    }

    /**
     * Coverage of the availability among the students.
     *
     * @param int $available
     * @param int $total
     * @return coverage
     */
    private function coverage(int $available, int $total): coverage {
        if ($total > 0 && $available === $total) {
            return coverage::ALL;
        }
        return $available > 0 ? coverage::PARTIAL : coverage::NONE;
    }
}
