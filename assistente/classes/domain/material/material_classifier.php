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

namespace local_assistente\domain\material;

use local_assistente\domain\material_type;

/**
 * Decides whether a Moodle module is a functional material and of which type (DEC-33 D2; constitution section 6).
 *
 * - PDF: a file resource (`mod_resource`) whose file is a PDF.
 * - Text: a page (`mod_page`).
 * - YouTube: a URL (`mod_url`) that points to YouTube.
 *
 * Everything else is NOT a material: any other file type, labels, Book, Folder and every other module. The rule is pure: the
 * caller reads the module name, the mimetype of the file and the URL from Moodle and passes them in.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class material_classifier {
    /** @var string[] modules that can be a material; the others are never read as one */
    public const CANDIDATE_MODULES = ['resource', 'page', 'url'];

    /** @var string mimetype that Moodle gives to a PDF file */
    public const PDF_MIMETYPE = 'application/pdf';

    /**
     * Type of the material, or null when the module is not a functional material.
     *
     * @param string $modname module name, with or without the `mod_` prefix
     * @param string|null $mimetype mimetype of the file of a resource; null when it has no file
     * @param string|null $url address of a URL module; null when it has none
     * @return material_type|null
     */
    public static function classify(string $modname, ?string $mimetype = null, ?string $url = null): ?material_type {
        return match (self::normalise_module($modname)) {
            'resource' => self::normalise_mimetype($mimetype) === self::PDF_MIMETYPE ? material_type::PDF : null,
            'page' => material_type::TEXT,
            'url' => ($url !== null && youtube_url::is_youtube($url)) ? material_type::YOUTUBE : null,
            default => null,
        };
    }

    /**
     * Whether a module can be a material, so that the reader only inspects those.
     *
     * @param string $modname
     * @return bool
     */
    public static function is_candidate(string $modname): bool {
        return in_array(self::normalise_module($modname), self::CANDIDATE_MODULES, true);
    }

    /**
     * Module name without the prefix, in lower case.
     *
     * @param string $modname
     * @return string
     */
    private static function normalise_module(string $modname): string {
        $name = strtolower(trim($modname));
        return str_starts_with($name, 'mod_') ? substr($name, 4) : $name;
    }

    /**
     * Mimetype without parameters, in lower case.
     *
     * @param string|null $mimetype
     * @return string|null
     */
    private static function normalise_mimetype(?string $mimetype): ?string {
        if ($mimetype === null) {
            return null;
        }
        return strtolower(trim(explode(';', $mimetype)[0]));
    }
}
