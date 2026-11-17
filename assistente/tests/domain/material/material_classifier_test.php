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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the material classifier (quickstart 008 Q11; DEC-33 D2; constitution section 6).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(material_classifier::class)]
final class material_classifier_test extends \basic_testcase {
    /**
     * A PDF file resource, a page and a YouTube URL are the three functional materials.
     */
    public function test_the_three_functional_types(): void {
        $this->assertSame(material_type::PDF, material_classifier::classify('resource', 'application/pdf'));
        $this->assertSame(material_type::TEXT, material_classifier::classify('page'));
        $this->assertSame(material_type::YOUTUBE, material_classifier::classify('url', null, 'https://www.youtube.com/watch?v=abc'));
        $this->assertSame(material_type::YOUTUBE, material_classifier::classify('url', null, 'https://youtu.be/abc'));
    }

    /**
     * The `mod_` prefix and the case of the module name and of the mimetype do not matter; mimetype parameters are ignored.
     */
    public function test_names_are_normalised(): void {
        $this->assertSame(material_type::PDF, material_classifier::classify('mod_resource', 'Application/PDF'));
        $this->assertSame(material_type::PDF, material_classifier::classify(' RESOURCE ', 'application/pdf; charset=binary'));
        $this->assertSame(material_type::TEXT, material_classifier::classify('mod_page'));
        $this->assertSame(material_type::YOUTUBE, material_classifier::classify('MOD_URL', null, 'https://m.youtube.com/watch?v=x'));
    }

    /**
     * Files of a resource that are not PDF.
     *
     * @return array
     */
    public static function not_pdf_mimetypes(): array {
        return [
            'word' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'text' => ['text/plain'],
            'image' => ['image/png'],
            'video' => ['video/mp4'],
            'zip' => ['application/zip'],
            'octet stream' => ['application/octet-stream'],
            'pdf look-alike' => ['application/pdfx'],
            'empty' => [''],
        ];
    }

    /**
     * Quickstart 008 Q11: a resource whose file is not a PDF is not a material.
     *
     * @param string $mimetype
     */
    #[DataProvider('not_pdf_mimetypes')]
    public function test_a_resource_that_is_not_a_pdf_is_not_a_material(string $mimetype): void {
        $this->assertNull(material_classifier::classify('resource', $mimetype));
    }

    /**
     * Content absent: a resource without a file, and a URL that is missing, empty or not YouTube.
     */
    public function test_absent_or_invalid_content_is_not_a_material(): void {
        $this->assertNull(material_classifier::classify('resource'), 'A resource with no file');
        $this->assertNull(material_classifier::classify('url'), 'A URL module with no address');
        $this->assertNull(material_classifier::classify('url', null, ''));
        $this->assertNull(material_classifier::classify('url', null, 'https://vimeo.com/123'));
        $this->assertNull(material_classifier::classify('url', null, 'not a url'));
        $this->assertNull(material_classifier::classify('url', null, 'https://youtube.com.evil.example/watch'));
    }

    /**
     * Modules that are not materials: labels, Book, Folder and the activities (quickstart 008 Q11).
     *
     * @return array
     */
    public static function other_modules(): array {
        return [
            'label' => ['label'], 'book' => ['book'], 'folder' => ['folder'], 'forum' => ['forum'], 'quiz' => ['quiz'],
            'assign' => ['assign'], 'lesson' => ['lesson'], 'imscp' => ['imscp'], 'scorm' => ['scorm'], 'empty' => [''],
            'unknown' => ['not_a_module'], 'prefixed label' => ['mod_label'], 'pageish' => ['pages'],
        ];
    }

    /**
     * Every other module is not a material, whatever its data look like.
     *
     * @param string $modname
     */
    #[DataProvider('other_modules')]
    public function test_other_modules_are_not_materials(string $modname): void {
        $this->assertNull(material_classifier::classify($modname, 'application/pdf', 'https://youtu.be/abc'));
        $this->assertFalse(material_classifier::is_candidate($modname));
    }

    /**
     * Only the three candidate modules are inspected.
     */
    public function test_candidates(): void {
        $this->assertSame(['resource', 'page', 'url'], material_classifier::CANDIDATE_MODULES);
        foreach (['resource', 'page', 'url', 'mod_resource', 'Mod_Page'] as $name) {
            $this->assertTrue(material_classifier::is_candidate($name), $name);
        }
    }
}
