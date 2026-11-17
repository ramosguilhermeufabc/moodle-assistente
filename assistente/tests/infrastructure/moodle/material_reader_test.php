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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the material reader (DEC-33 D2 and D3).
 *
 * The fixture that replaces the file of a resource, and the mimetype detection, are validated in a running
 * Moodle 5.2 by CL-02 (T036).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(material_reader::class)]
final class material_reader_test extends \advanced_testcase {
    /**
     * Replace the files of a resource by one file.
     *
     * @param int $cmid
     * @param string $filename
     * @param string $content
     */
    private function set_resource_file(int $cmid, string $filename, string $content): void {
        $fs = get_file_storage();
        $contextid = \context_module::instance($cmid)->id;
        $fs->delete_area_files($contextid, 'mod_resource', 'content');
        $fs->create_file_from_string([
            'contextid' => $contextid, 'component' => 'mod_resource', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
        ], $content);
    }

    /**
     * Only PDF resources, pages and YouTube URLs are materials; other files, other URLs and other modules are not.
     */
    public function test_only_pdf_text_and_youtube_are_materials(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2]);

        $pdf = $generator->create_module('resource', ['course' => $course->id, 'name' => 'Apostila'], ['section' => 1]);
        $this->set_resource_file($pdf->cmid, 'apostila.pdf', '%PDF-1.4 conteudo');
        $text = $generator->create_module('page', ['course' => $course->id, 'name' => 'Resumo', 'content' => '<p>Texto</p>'], ['section' => 2]);
        $youtube = $generator->create_module('url', ['course' => $course->id, 'name' => 'Video', 'externalurl' => 'https://www.youtube.com/watch?v=abc']);
        $docx = $generator->create_module('resource', ['course' => $course->id, 'name' => 'Documento']);
        $this->set_resource_file($docx->cmid, 'documento.docx', 'conteudo');
        $vimeo = $generator->create_module('url', ['course' => $course->id, 'name' => 'Outro video', 'externalurl' => 'https://vimeo.com/1']);
        $generator->create_module('forum', ['course' => $course->id]);
        $generator->create_module('label', ['course' => $course->id, 'intro' => 'Rotulo']);

        $materials = (new material_reader())->get_materials($course->id);

        $this->assertEqualsCanonicalizing([(int) $pdf->cmid, (int) $text->cmid, (int) $youtube->cmid], array_keys($materials));
        $this->assertSame('pdf', $materials[(int) $pdf->cmid]->material_type);
        $this->assertSame('text', $materials[(int) $text->cmid]->material_type);
        $this->assertSame('youtube', $materials[(int) $youtube->cmid]->material_type);
        $this->assertSame('Apostila', $materials[(int) $pdf->cmid]->name);
        $this->assertSame((int) get_fast_modinfo($course->id)->get_section_info(1)->id, $materials[(int) $pdf->cmid]->section_id);
        $this->assertNull((new material_reader())->get_material($course->id, (int) $docx->cmid));
        $this->assertNull((new material_reader())->get_material($course->id, (int) $vimeo->cmid));
        $this->assertSame([], (new material_reader())->get_materials(987654));
    }

    /**
     * The content hash changes only with the processable content (DEC-33 D3): not with name, visibility or section.
     */
    public function test_content_hash_changes_only_with_the_content(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2]);
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => 'Pagina', 'content' => 'Um']);
        $reader = new material_reader();
        $before = $reader->get_material($course->id, $page->cmid);

        // Name, visibility and section do not change the content.
        $DB->set_field('page', 'name', 'Novo nome', ['id' => $page->id]);
        $DB->set_field('course_modules', 'visible', 0, ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);
        $same = $reader->get_material($course->id, $page->cmid);
        $this->assertSame($before->content_hash, $same->content_hash);
        $this->assertFalse($same->visible);

        // The text does.
        $DB->set_field('page', 'content', 'Dois', ['id' => $page->id]);
        $this->assertNotSame($before->content_hash, $reader->get_material($course->id, $page->cmid)->content_hash);
    }

    /**
     * The content of each kind of material is handed over as a reference: text, URL and stored file.
     */
    public function test_content_references(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $pdf = $generator->create_module('resource', ['course' => $course->id]);
        $this->set_resource_file($pdf->cmid, 'a.pdf', '%PDF-1.4 x');
        $page = $generator->create_module('page', ['course' => $course->id, 'content' => '<p>Conteudo</p>']);
        $url = $generator->create_module('url', ['course' => $course->id, 'externalurl' => 'https://youtu.be/abc']);
        $reader = new material_reader();

        $file = $reader->get_file($pdf->cmid);
        $this->assertSame('a.pdf', $file->get_filename());
        $this->assertSame('application/pdf', $file->get_mimetype());
        $this->assertSame('%PDF-1.4 x', $file->get_content());
        $this->assertSame('<p>Conteudo</p>', $reader->get_page_content($page->cmid)->content);
        $this->assertSame('https://youtu.be/abc', $reader->get_url($url->cmid));

        $this->assertNull($reader->get_page_content($pdf->cmid));
        $this->assertNull($reader->get_url($page->cmid));
        $this->assertNull($reader->get_page_content(987654));
        $this->assertNull($reader->get_url(987654));
    }

    /**
     * A material scheduled for deletion is not listed.
     */
    public function test_material_being_deleted_is_not_listed(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);

        $this->assertSame([], (new material_reader())->get_materials($course->id));
    }
}
