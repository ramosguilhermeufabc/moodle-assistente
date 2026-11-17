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

namespace local_assistente\application\material;

use local_assistente\domain\material_type;

/**
 * Contract of the extraction of the content of a material (spec-008; T062).
 *
 * One implementation per type of material. The text of a page is extracted by page_extractor; the text of a PDF by pdf_extractor, with
 * the bundled smalot/pdfparser library; and the title, description and subtitle of a YouTube video by youtube_extractor, with the
 * yt-dlp program (DEC-67; T135).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface content_extractor {
    /**
     * Type of material this extractor handles.
     *
     * @return material_type
     */
    public function type(): material_type;

    /**
     * Content of a material.
     *
     * @param int $cmid course module id of the material
     * @return extracted_content|null null when the content is missing (module, page, file or URL removed or empty address)
     */
    public function extract(int $cmid): ?extracted_content;
}
