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

global $CFG;
require_once($CFG->libdir . '/html2text/lib.php');

/**
 * Converts HTML to plain text keeping the words as written (T062).
 *
 * Moodle's html_to_text() writes the content of headings and of bold text in capitals, which changes the material the model
 * summarises (acronyms, proper names). This converter is core_html2text with that change removed: entities, lists, line breaks
 * and spaces are handled as Moodle does. Links are not listed and lines are not wrapped. Emphasis stays marked with underscores,
 * as Moodle writes it.
 *
 * Moodle API: core_html2text (lib/html2text/lib.php).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class html_text_converter extends \core_html2text {
    /**
     * Convert HTML to plain text.
     *
     * @param string $html
     * @return string
     */
    public static function to_text(string $html): string {
        return (new self($html, ['width' => 0, 'do_links' => 'none']))->getText();
    }

    /**
     * Keep the case of the text (the parent writes it in capitals).
     *
     * @param string $str text, with HTML entities
     * @return string the same text
     */
    #[\Override]
    protected function strtoupper($str) {
        return $str;
    }
}
