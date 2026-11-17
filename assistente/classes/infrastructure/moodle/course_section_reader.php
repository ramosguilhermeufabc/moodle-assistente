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

namespace local_assistente\infrastructure\moodle;

use local_assistente\domain\section\section_resolution;
use local_assistente\domain\section\section_resolution_status;

/**
 * Reads courses and sections, and resolves or validates the section of a module (spec-004).
 *
 * Moodle API: get_fast_modinfo() with core_course\modinfo (get_cms, get_section_info_all,
 * get_section_info_by_id) and core_course\cm_info (sectionid, deletioninprogress); get_config();
 * core_date::get_server_timezone(). Verified by reading the MOODLE_502_STABLE sources; behaviour
 * in a running Moodle 5.2 is validated in CL-16, CL-39 and CL-41 (tasks T030 and T036).
 *
 * DML justification: course and course_modules are read with get_record(IGNORE_MISSING) because
 * get_course() and get_course_and_cm_from_cmid() throw for missing records, and the contract needs an
 * explicit "cmid not found" outcome distinct from "cmid of another course". The reader never writes.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_section_reader {
    /**
     * Read the data of a course used by the analyses.
     *
     * @param int $courseid
     * @return \stdClass|null id, fullname, shortname, lang (forced language or empty), enddate (0 when unset); null when missing
     */
    public function get_course(int $courseid): ?\stdClass {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname, lang, enddate', IGNORE_MISSING);
        return $course ?: null;
    }

    /**
     * Sections of a course, including delegated sections (subsections), in section-number order.
     *
     * @param int $courseid
     * @return \stdClass[] each with id (course_sections.id), number, name, visible, delegated, component, itemid; [] when the course is missing
     */
    public function get_sections(int $courseid): array {
        if ($this->get_course($courseid) === null) {
            return [];
        }
        $sections = [];
        foreach (get_fast_modinfo($courseid)->get_section_info_all() as $info) {
            $sections[] = (object) [
                'id' => (int) $info->id,
                'number' => (int) $info->sectionnum,
                'name' => (string) $info->name,
                'visible' => (bool) $info->visible,
                'delegated' => $info->is_delegated(),
                'component' => $info->component,
                'itemid' => $info->itemid,
            ];
        }
        return $sections;
    }

    /**
     * Resolve the section of a module: the course_sections.id directly associated to it.
     *
     * A module inside a subsection resolves to the delegated section. Outcomes without a section are
     * explicit and distinct: cmid not found (or being deleted), cmid of another course, and a section
     * that is not valid for the course. Nothing is written.
     *
     * @param int $courseid
     * @param int $cmid
     * @return section_resolution
     */
    public function resolve_module_section(int $courseid, int $cmid): section_resolution {
        global $DB;

        $module = $DB->get_record('course_modules', ['id' => $cmid], 'id, course', IGNORE_MISSING);
        if (!$module) {
            return section_resolution::unresolved(section_resolution_status::CMID_NOT_FOUND);
        }
        if ((int) $module->course !== $courseid) {
            return section_resolution::unresolved(section_resolution_status::CMID_OTHER_COURSE);
        }

        $modinfo = get_fast_modinfo($courseid);
        $cms = $modinfo->get_cms();
        // Hypothesis (CL-16): a module that is scheduled for deletion is not a valid source.
        if (!isset($cms[$cmid]) || $cms[$cmid]->deletioninprogress) {
            return section_resolution::unresolved(section_resolution_status::CMID_NOT_FOUND);
        }

        $sectionid = (int) $cms[$cmid]->sectionid;
        if ($modinfo->get_section_info_by_id($sectionid) === null) {
            return section_resolution::unresolved(section_resolution_status::SECTION_INVALID);
        }
        return section_resolution::resolved($sectionid);
    }

    /**
     * Validate a section: accepted only when it exists in that course.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return section_resolution
     */
    public function validate_section(int $courseid, int $sectionid): section_resolution {
        if ($this->get_course($courseid) === null
                || get_fast_modinfo($courseid)->get_section_info_by_id($sectionid) === null) {
            return section_resolution::unresolved(section_resolution_status::SECTION_INVALID);
        }
        return section_resolution::resolved($sectionid);
    }

    /**
     * Language forced in the course (DEC-46), or null when the course does not force one.
     *
     * @param int $courseid
     * @return string|null
     */
    public function forced_language(int $courseid): ?string {
        $course = $this->get_course($courseid);
        return ($course && $course->lang !== '') ? $course->lang : null;
    }

    /**
     * Default language of the site (DEC-46).
     *
     * @return string
     */
    public function site_language(): string {
        return (string) (get_config('core', 'lang') ?: 'en');
    }

    /**
     * Language used to generate content for a course: the forced one, otherwise the site default (DEC-46).
     *
     * @param int $courseid
     * @return string
     */
    public function generation_language(int $courseid): string {
        return $this->forced_language($courseid) ?? $this->site_language();
    }

    /**
     * Time zone of the site as an identifier such as America/Sao_Paulo (DEC-44 D2).
     *
     * @return string
     */
    public function site_timezone(): string {
        return \core_date::get_server_timezone();
    }

    /**
     * End date of the course (DEC-39 D2), or null when it is not set.
     *
     * @param int $courseid
     * @return int|null unix timestamp
     */
    public function course_end_date(int $courseid): ?int {
        $course = $this->get_course($courseid);
        return ($course && (int) $course->enddate > 0) ? (int) $course->enddate : null;
    }
}
