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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the extraction of the content of materials (T062).
 *
 * Written against the Moodle generators; not executed outside a Moodle 5.2 with PHPUnit (see DEC-48 and tasks.md).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(page_extractor::class)]
#[CoversClass(extracted_content::class)]
final class content_extractor_test extends \advanced_testcase {
    /**
     * A page with a given text and format.
     *
     * @param string $content
     * @param int $format
     * @return int cmid
     */
    private function page(string $content, int $format = FORMAT_HTML): int {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => $content,
            'contentformat' => $format,
        ]);
        return (int) $page->cmid;
    }

    /**
     * A file resource holding a file with the given name.
     *
     * @param string $filename
     * @return int cmid
     */
    private function resource(string $filename): int {
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser(); // The resource generator needs a current user.
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $context = \context_module::instance($resource->cmid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_resource', 'content');
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], 'conteudo do arquivo');
        return (int) $resource->cmid;
    }

    /**
     * Normal case: the text of an HTML page is handed over as plain text.
     */
    public function test_page_html_becomes_plain_text(): void {
        $this->resetAfterTest();
        $cmid = $this->page('<h1>Titulo</h1><p>Primeiro <b>paragrafo</b>.</p>');

        $content = (new page_extractor())->extract($cmid);

        $this->assertNotNull($content);
        $this->assertSame(material_type::TEXT, $content->type);
        $this->assertStringContainsString('Primeiro', $content->text);
        $this->assertStringContainsString('paragrafo', $content->text);
        $this->assertStringNotContainsString('<p>', $content->text);
        $this->assertNull($content->reference);
        $this->assertFalse($content->extraction_pending());
        $this->assertTrue($content->has_content());
    }

    /**
     * Plain text and Markdown are handed over as stored.
     */
    public function test_page_plain_and_markdown_are_kept(): void {
        $this->resetAfterTest();
        $plain = $this->page("Linha 1\nLinha 2", FORMAT_PLAIN);
        $markdown = $this->page('# Titulo <b>', FORMAT_MARKDOWN);

        $this->assertSame("Linha 1\nLinha 2", (new page_extractor())->extract($plain)->text);
        $this->assertSame('# Titulo <b>', (new page_extractor())->extract($markdown)->text);
    }

    /**
     * Missing content: a page with no text, and a cmid that is not a page, have no content.
     */
    public function test_page_without_content_returns_null(): void {
        $this->resetAfterTest();
        $empty = $this->page('<p>   </p>');
        $resource = $this->resource('a.pdf');

        $this->assertNull((new page_extractor())->extract($empty));
        $this->assertNull((new page_extractor())->extract($resource), 'A resource is not a page');
        $this->assertNull((new page_extractor())->extract(99999999), 'A module that does not exist');
    }

    /**
     * Types: each extractor says which type it handles.
     */
    public function test_types_and_invalid_input(): void {
        $this->assertSame(material_type::TEXT, (new page_extractor())->type());
        $this->assertSame(material_type::PDF, (new pdf_extractor())->type());
        $this->assertSame(material_type::YOUTUBE, (new youtube_extractor())->type());
    }

    /**
     * The context data of a content, for the port; a content has either text or reference.
     */
    public function test_extracted_content_value_object(): void {
        $text = new extracted_content(material_type::TEXT, 'abc');
        $this->assertSame(
            ['type' => 'text', 'text' => 'abc', 'reference' => null, 'extraction_pending' => false],
            $text->to_context_data()
        );

        $reference = new extracted_content(material_type::YOUTUBE, null, ['kind' => 'url', 'url' => 'https://youtu.be/x']);
        $this->assertTrue($reference->to_context_data()['extraction_pending']);

        $this->assertFalse((new extracted_content(material_type::TEXT, '  '))->has_content());

        $this->expectException(\InvalidArgumentException::class);
        new extracted_content(material_type::TEXT);
    }
}
