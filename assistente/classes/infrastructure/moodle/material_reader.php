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

use local_assistente\domain\material\material_classifier;
use local_assistente\domain\material_type;

/**
 * Reads the functional materials of a course: PDF, text and YouTube (spec-004; DEC-33 D2 and D3).
 *
 * PDF = a file resource (mod_resource) whose file is a PDF; text = a page (mod_page); YouTube = a URL (mod_url)
 * that points to YouTube. Any other module or file type, labels, Book and Folder are not materials.
 *
 * Each material carries a `content_hash` that identifies its processable content (DEC-33 D3): the file content
 * hash of the PDF, the SHA-1 of the page text, the SHA-1 of the URL. A change of name, visibility, access
 * restrictions or section does not change it, so the consumer creates a new version only when it changes.
 *
 * The reader only identifies and reads the content. Extracting text from a PDF or a YouTube video is not done here (DEC-67,
 * T135): it belongs to pdf_extractor and youtube_extractor, which read the stored file and the URL given by this reader.
 *
 * Moodle API: get_fast_modinfo() (cm_info: modname, instance, sectionid, visible, context); File API:
 * get_file_storage()->get_area_files() and stored_file::get_mimetype(), get_contenthash(). Verified by reading
 * the MOODLE_502_STABLE sources; behaviour in a running Moodle 5.2 is validated in CL-02 (T036).
 *
 * DML justification: the text of mod_page and the address of mod_url are read from their tables (page, url),
 * which are the only place where Moodle keeps them; the reader never writes.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class material_reader {
    /** @var string material type of a PDF file resource */
    public const TYPE_PDF = 'pdf';

    /** @var string material type of a page */
    public const TYPE_TEXT = 'text';

    /** @var string material type of a YouTube URL */
    public const TYPE_YOUTUBE = 'youtube';

    /**
     * Functional materials of a course, except modules being deleted.
     *
     * Each item has: cmid, course_id, section_id, material_type (pdf, text or youtube), name, visible,
     * content_hash. Availability for students is not evaluated here (see course_module_reader, DEC-26).
     *
     * @param int $courseid
     * @return \stdClass[] indexed by cmid, in course order; [] when the course does not exist
     */
    public function get_materials(int $courseid): array {
        global $DB;
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return [];
        }
        $materials = [];
        foreach (get_fast_modinfo($courseid)->get_cms() as $cm) {
            if ($cm->deletioninprogress || !material_classifier::is_candidate((string) $cm->modname)) {
                continue;
            }
            $material = $this->describe($cm);
            if ($material !== null) {
                $materials[(int) $cm->id] = $material;
            }
        }
        return $materials;
    }

    /**
     * One material of a course.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass|null null when the module is not a functional material (or does not exist)
     */
    public function get_material(int $courseid, int $cmid): ?\stdClass {
        return $this->get_materials($courseid)[$cmid] ?? null;
    }

    /**
     * Text of a page material.
     *
     * @param int $cmid
     * @return \stdClass|null content (as stored) and content_format; null when the cmid is not a page
     */
    public function get_page_content(int $cmid): ?\stdClass {
        global $DB;
        $cm = get_coursemodule_from_id('page', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        $page = $DB->get_record('page', ['id' => $cm->instance], 'id, content, contentformat', IGNORE_MISSING);
        return $page ? (object) ['content' => (string) $page->content, 'content_format' => (int) $page->contentformat] : null;
    }

    /**
     * Address of a URL material.
     *
     * @param int $cmid
     * @return string|null the external URL; null when the cmid is not a URL module
     */
    public function get_url(int $cmid): ?string {
        global $DB;
        $cm = get_coursemodule_from_id('url', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        $url = $DB->get_field('url', 'externalurl', ['id' => $cm->instance], IGNORE_MISSING);
        return $url === false ? null : (string) $url;
    }

    /**
     * Stored file of a file resource, read through the File API.
     *
     * @param int $cmid
     * @return \stored_file|null null when the cmid has no file
     */
    public function get_file(int $cmid): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            \context_module::instance($cmid)->id,
            'mod_resource',
            'content',
            0,
            'sortorder DESC, id ASC',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Describe a module as a material, or null when it is not a functional one.
     *
     * @param \core_course\cm_info $cm
     * @return \stdClass|null
     */
    private function describe(\core_course\cm_info $cm): ?\stdClass {
        $file = $cm->modname === 'resource' ? $this->get_file((int) $cm->id) : null;
        $page = $cm->modname === 'page' ? $this->get_page_content((int) $cm->id) : null;
        $url = $cm->modname === 'url' ? $this->get_url((int) $cm->id) : null;

        // The rule of what is a material, and of which type, is in the domain (T059); here Moodle is only read.
        $classified = material_classifier::classify(
            (string) $cm->modname,
            $file !== null ? $file->get_mimetype() : null,
            $url
        );
        // A page whose record is missing has no content to process.
        if ($classified === null || ($classified === material_type::TEXT && $page === null)) {
            return null;
        }

        $type = $classified->value;
        $hash = match ($classified) {
            material_type::PDF => $file->get_contenthash(),
            material_type::TEXT => sha1($page->content),
            material_type::YOUTUBE => sha1($url),
        };
        return (object) [
            'cmid' => (int) $cm->id,
            'course_id' => (int) $cm->course,
            'section_id' => (int) $cm->sectionid,
            'material_type' => $type,
            'name' => (string) $cm->name,
            'visible' => (bool) $cm->visible,
            'content_hash' => $hash,
        ];
    }
}
