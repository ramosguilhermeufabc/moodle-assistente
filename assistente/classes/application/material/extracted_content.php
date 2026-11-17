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
 * Content of a material, ready to be handed to the LLM port (spec-008; T062).
 *
 * Either the text itself (page, PDF and YouTube, DEC-67) or a reference to the content (file data or URL). A reference has no extracted
 * text, so `text` is null and `extraction_pending` is true; no extractor returns a reference since T135, but the value object keeps it. Nothing here identifies a student (DEC-34).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extracted_content {
    /**
     * Constructor.
     *
     * @param material_type $type
     * @param string|null $text the text of the material; null when only a reference is available
     * @param array|null $reference reference to the content (file data or URL); null when the text is available
     */
    public function __construct(
        /** @var material_type type of the material */
        public readonly material_type $type,
        /** @var string|null text of the material */
        public readonly ?string $text = null,
        /** @var array|null reference to the content */
        public readonly ?array $reference = null
    ) {
        if (($text === null) === ($reference === null)) {
            throw new \InvalidArgumentException('A content has either a text or a reference, not both and not neither.');
        }
    }

    /**
     * Whether the text of the material is not available yet (a content with a reference and no text).
     *
     * @return bool
     */
    public function extraction_pending(): bool {
        return $this->text === null;
    }

    /**
     * Whether there is anything to process: a reference, or a text that is not empty.
     *
     * @return bool
     */
    public function has_content(): bool {
        return $this->reference !== null || trim((string) $this->text) !== '';
    }

    /**
     * Data to put in the context handed to the port.
     *
     * @return array
     */
    public function to_context_data(): array {
        return [
            'type' => $this->type->value,
            'text' => $this->text,
            'reference' => $this->reference,
            'extraction_pending' => $this->extraction_pending(),
        ];
    }
}
