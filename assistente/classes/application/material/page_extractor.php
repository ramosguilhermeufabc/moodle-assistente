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
use local_assistente\infrastructure\moodle\html_text_converter;
use local_assistente\infrastructure\moodle\material_reader;

/**
 * Extracts the text of a page material (mod_page).
 *
 * Hypothesis, to be validated in T066: the text handed to the port is plain text. HTML and Moodle-auto-format content go through
 * html_text_converter; plain text and Markdown are handed over as stored. A page whose text is empty after conversion has no content.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page_extractor implements content_extractor {
    /** @var material_reader */
    private material_reader $reader;

    /**
     * Constructor.
     *
     * @param material_reader|null $reader
     */
    public function __construct(?material_reader $reader = null) {
        $this->reader = $reader ?? new material_reader();
    }

    #[\Override]
    public function type(): material_type {
        return material_type::TEXT;
    }

    #[\Override]
    public function extract(int $cmid): ?extracted_content {
        $page = $this->reader->get_page_content($cmid);
        if ($page === null) {
            return null;
        }
        $text = match ((string) $page->content_format) {
            // Moodle declares the FORMAT_* constants as strings: compare as strings.
            (string) FORMAT_PLAIN, (string) FORMAT_MARKDOWN => $page->content,
            default => html_text_converter::to_text($page->content),
        };
        $text = trim($text);
        return $text === '' ? null : new extracted_content(material_type::TEXT, $text);
    }
}
