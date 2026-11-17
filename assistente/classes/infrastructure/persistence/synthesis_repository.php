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


namespace local_assistente\infrastructure\persistence;

use local_assistente\domain\analysis_type;
use local_assistente\domain\synthesis\fingerprint;

/**
 * Repository of the pedagogical synthesis of a course section: the aggregate `analysis` (type `section_synthesis`) with its
 * `pedagogical_synthesis` row, one for each version (plan 003 "Agregados" and "Unidades transacionais"; spec-016 data-model and contract;
 * DEC-14, DEC-29, DEC-41; T096).
 *
 * Rules kept here:
 * - A version is born with its analysis, in one transaction, under the Lock API of the course section (unit_of_work::section_resource):
 *   the number is `max(version) + 1` of the course and section, read inside the lock, so the first version is 1 and two writers of the same
 *   section are serialized. The unique index (course, section, version) is the last guard: it is what refuses a duplicated number if the lock
 *   was somehow skipped (CL-07 validates its behaviour on the target database).
 * - The analysis of a version is its own: one analysis for each processing, and a second save for the same processing is rejected
 *   (duplicate_record_exception) and leaves the first untouched. A failure writes nothing, so the current version is the latest, which is
 *   the last successful one, and the earlier versions stay as they were. No method changes or deletes an analysis or a version.
 * - The current version of a section is the one with the highest number; another section never changes (DEC-13).
 * - The section must belong to the course; the processing must exist; the content is a non-empty text (plain text, HTML of the LLM is
 *   shown escaped by who displays it); the fingerprint is the 64 hexadecimal characters of fingerprint::of() (DEC-41 D2).
 * - Only the content and its fingerprint are stored: no student, no individual grade, no criteria of spec-015.
 *
 * It holds no reading of Moodle, no LLM and no decision about whether a new version is due: comparing the fingerprint with the current
 * one, calling the port and resolving the section are the synthesis handler (T097). The comparison and this write are not one unit, so
 * two handlers can both find the fingerprint new; whether this repository must refuse a version equal to the current one under the lock
 * is a question left to T097.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class synthesis_repository extends repository {
    /** @var table_repository the pedagogical_synthesis table */
    private table_repository $syntheses;

    /** @var table_repository the processing table, to check it exists */
    private table_repository $processings;

    /**
     * Constructor.
     *
     * @param unit_of_work $uow
     */
    public function __construct(
        private readonly unit_of_work $uow = new unit_of_work()
    ) {
        $this->syntheses = new table_repository('local_assistente_pedagogical_synthesis');
        $this->processings = new table_repository('local_assistente_processing');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_analysis';
    }

    /**
     * Save a new version of the synthesis of a section, all or nothing.
     *
     * @param int $courseid
     * @param int $sectionid id of the course section
     * @param int $processingid
     * @param string $content plain text of the synthesis
     * @param string $fingerprint input_fingerprint, 64 hexadecimal characters
     * @param int|null $now
     * @return \stdClass the stored `pedagogical_synthesis` row, with its version and its analysis
     * @throws invalid_domain_value_exception when a value or a reference is invalid (nothing is written)
     * @throws duplicate_record_exception when the processing already has an analysis, or the number of the version is taken
     * @throws \dml_missing_record_exception when the processing does not exist
     */
    public function save(
        int $courseid,
        int $sectionid,
        int $processingid,
        string $content,
        string $fingerprint,
        ?int $now = null
    ): \stdClass {
        global $DB;
        if ($courseid <= 0 || !$DB->record_exists('course', ['id' => $courseid])) {
            throw new invalid_domain_value_exception("The course {$courseid} does not exist.");
        }
        if ($sectionid <= 0 || !$DB->record_exists('course_sections', ['id' => $sectionid, 'course' => $courseid])) {
            throw new invalid_domain_value_exception("The section {$sectionid} is not a section of course {$courseid}.");
        }
        if (trim($content) === '') {
            throw new invalid_domain_value_exception('The content of a synthesis cannot be empty.');
        }
        if (!preg_match('/^[0-9a-f]{' . fingerprint::LENGTH . '}$/', $fingerprint)) {
            throw new invalid_domain_value_exception('The input fingerprint must be ' . fingerprint::LENGTH . ' lowercase hexadecimal characters.');
        }
        $this->processings->find($processingid)
            ?? throw new \dml_missing_record_exception('local_assistente_processing', 'id = ?', [$processingid]);
        $now ??= time();

        $id = $this->uow->run(function () use ($courseid, $sectionid, $processingid, $content, $fingerprint, $now): int {
            if ($this->find_by_processing($processingid) !== null) {
                throw new duplicate_record_exception("Processing {$processingid} already has a synthesis analysis.");
            }
            $analysisid = $this->insert((object) [
                'course_id' => $courseid,
                'processing_id' => $processingid,
                'analysis_type' => analysis_type::SECTION_SYNTHESIS->value,
                'cmid' => null,
                'summary' => null,
                'created_at' => $now,
            ]);
            $current = $this->find_current($courseid, $sectionid);
            return $this->syntheses->insert((object) [
                'analysis_id' => $analysisid,
                'course_id' => $courseid,
                'section_id' => $sectionid,
                'version' => $current === null ? 1 : (int) $current->version + 1,
                'content' => $content,
                'input_fingerprint' => $fingerprint,
                'created_at' => $now,
            ]);
        }, [unit_of_work::section_resource($courseid, $sectionid)]);

        return $this->syntheses->find($id);
    }

    /**
     * The synthesis analysis of a processing.
     *
     * @param int $processingid
     * @return \stdClass|null
     */
    public function find_by_processing(int $processingid): ?\stdClass {
        return $this->find_by(['processing_id' => $processingid, 'analysis_type' => analysis_type::SECTION_SYNTHESIS->value]);
    }

    /**
     * The current version of a section: the highest number.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return \stdClass|null null when the section has no synthesis
     */
    public function find_current(int $courseid, int $sectionid): ?\stdClass {
        $history = $this->find_history($courseid, $sectionid);
        return $history ? reset($history) : null;
    }

    /**
     * A version of a section by its number.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param int $version
     * @return \stdClass|null
     */
    public function find_version(int $courseid, int $sectionid, int $version): ?\stdClass {
        return $this->syntheses->find_by(['course_id' => $courseid, 'section_id' => $sectionid, 'version' => $version]);
    }

    /**
     * The version of an analysis.
     *
     * @param int $analysisid
     * @return \stdClass|null
     */
    public function find_by_analysis(int $analysisid): ?\stdClass {
        return $this->syntheses->find_by(['analysis_id' => $analysisid]);
    }

    /**
     * All the versions of a section, newest first.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return \stdClass[] indexed by id; [] when there are none
     */
    public function find_history(int $courseid, int $sectionid): array {
        return $this->syntheses->find_all_by(['course_id' => $courseid, 'section_id' => $sectionid], 'version DESC');
    }
}
