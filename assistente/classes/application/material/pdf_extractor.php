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
use local_assistente\infrastructure\moodle\material_reader;
use Smalot\PdfParser\Parser;

/**
 * Extracts the text of a PDF material (DEC-67; Q-008-03; T135).
 *
 * The file is read through the Moodle File API (material_reader::get_file()) and its text is extracted with the bundled library
 * smalot/pdfparser (`lib/pdfparser`, declared in thirdpartylibs.xml). A PDF without text (scanned or protected) has no content: there is
 * no OCR. A file the library cannot read has no content either.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_extractor implements content_extractor {
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
        return material_type::PDF;
    }

    #[\Override]
    public function extract(int $cmid): ?extracted_content {
        $file = $this->reader->get_file($cmid);
        if ($file === null) {
            return null;
        }
        global $CFG;
        require_once($CFG->dirroot . '/local/assistente/lib/pdfparser/autoload.php');
        try {
            $text = trim((new Parser())->parseContent($file->get_content())->getText());
        } catch (\Throwable $e) {
            return null;
        }
        return $text === '' ? null : new extracted_content(material_type::PDF, $text);
    }
}
