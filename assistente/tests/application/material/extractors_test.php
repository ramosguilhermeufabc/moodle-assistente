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

require_once(__DIR__ . '/../../fixtures/minimal_pdf.php');

use local_assistente\fixtures\minimal_pdf;
use local_assistente\domain\material_type;
use local_assistente\infrastructure\moodle\ytdlp_client;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the extraction of PDF and YouTube content (DEC-67; Q-008-03; T135).
 *
 * The PDF is a real, minimal PDF read by the bundled smalot/pdfparser. The yt-dlp program is never run: ytdlp_client is replaced by a double
 * that answers what the program would print (no network).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(pdf_extractor::class)]
#[CoversClass(youtube_extractor::class)]
#[CoversClass(\local_assistente\infrastructure\moodle\ytdlp_client::class)]
final class extractors_test extends \advanced_testcase {
    /**
     * A file resource holding the given content.
     *
     * @param string $content
     * @return int cmid
     */
    private function resource(string $content): int {
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $context = \context_module::instance($resource->cmid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_resource', 'content');
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_resource', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'apostila.pdf',
        ], $content);
        return (int) $resource->cmid;
    }

    /**
     * A URL module with the given address.
     *
     * @param string $address
     * @return int cmid
     */
    private function url(string $address): int {
        $course = $this->getDataGenerator()->create_course();
        return (int) $this->getDataGenerator()->create_module('url', ['course' => $course->id, 'externalurl' => $address])->cmid;
    }

    /**
     * Double of the yt-dlp client: answers what the program would print and records the calls.
     *
     * @param array|null $metadata
     * @param string|null $subtitle
     * @param bool $available
     * @return ytdlp_client
     */
    private function client(?array $metadata, ?string $subtitle = null, bool $available = true): ytdlp_client {
        return new class ($metadata, $subtitle, $available) extends ytdlp_client {
            /** @var array[] subtitle requests: [language, automatic] */
            public array $requests = [];

            /**
             * Constructor.
             *
             * @param array|null $metadata
             * @param string|null $subtitle
             * @param bool $available
             */
            public function __construct(private ?array $metadata, private ?string $subtitle, private bool $available) {
                parent::__construct('/nonexistent');
            }

            #[\Override]
            public function available(): bool {
                return $this->available;
            }

            #[\Override]
            public function metadata(string $url): ?array {
                return $this->metadata;
            }

            #[\Override]
            public function subtitle(string $url, string $language, bool $automatic): ?string {
                $this->requests[] = [$language, $automatic];
                return $this->subtitle;
            }
        };
    }

    /**
     * (a) A PDF with text gives its text.
     */
    public function test_pdf_with_text_gives_the_text(): void {
        $this->resetAfterTest();
        $cmid = $this->resource(minimal_pdf::make('Fotossintese e clorofila'));

        $content = (new pdf_extractor())->extract($cmid);

        $this->assertNotNull($content);
        $this->assertSame(material_type::PDF, $content->type);
        $this->assertStringContainsString('Fotossintese e clorofila', $content->text);
        $this->assertNull($content->reference);
        $this->assertFalse($content->extraction_pending());
    }

    /**
     * (b) A PDF without text (scanned or protected) and a file that is not a PDF have no content.
     */
    public function test_pdf_without_text_gives_null(): void {
        $this->resetAfterTest();

        $this->assertNull((new pdf_extractor())->extract($this->resource(minimal_pdf::make(null))), 'No text, no OCR');
        $this->assertNull((new pdf_extractor())->extract($this->resource('not a pdf')), 'A file the library cannot read');
    }

    /**
     * (c) A YouTube video with a subtitle gives title, description and subtitle; the subtitle is the one of the language of the course.
     */
    public function test_youtube_with_subtitle_gives_title_description_and_subtitle(): void {
        $this->resetAfterTest();
        $cmid = $this->url('https://www.youtube.com/watch?v=abc123');
        $client = $this->client([
            'title' => 'Aula de fotossintese', 'description' => 'Sobre as plantas',
            'subtitles' => ['en', 'pt-BR'], 'automatic' => ['pt'],
        ], 'Hoje vamos falar de plantas');

        $content = (new youtube_extractor(null, $client))->extract($cmid);

        $this->assertNotNull($content);
        $this->assertSame(material_type::YOUTUBE, $content->type);
        $this->assertStringContainsString('Aula de fotossintese', $content->text);
        $this->assertStringContainsString('Sobre as plantas', $content->text);
        $this->assertStringContainsString('Hoje vamos falar de plantas', $content->text);
        $this->assertSame([['en', false]], $client->requests, 'The language of the course is the site default (en) here');
    }

    /**
     * The subtitle language: the course language first, else the first available; automatic ones are accepted.
     */
    public function test_youtube_subtitle_language_choice(): void {
        $this->resetAfterTest();
        $cmid = $this->url('https://youtu.be/abc123');
        $course = get_course((int) get_coursemodule_from_id('url', $cmid)->course);
        $DB = $GLOBALS['DB'];
        $DB->set_field('course', 'lang', 'pt_br', ['id' => $course->id]);

        $written = $this->client(['title' => 't', 'description' => '', 'subtitles' => ['en', 'pt-BR'], 'automatic' => ['pt']], 'x');
        (new youtube_extractor(null, $written))->extract($cmid);
        $this->assertSame([['pt-BR', false]], $written->requests, 'Course language, written first');

        $auto = $this->client(['title' => 't', 'description' => '', 'subtitles' => ['en'], 'automatic' => ['pt']], 'x');
        (new youtube_extractor(null, $auto))->extract($cmid);
        $this->assertSame([['pt', true]], $auto->requests, 'Course language, automatic accepted');

        $first = $this->client(['title' => 't', 'description' => '', 'subtitles' => ['fr', 'en'], 'automatic' => ['de']], 'x');
        (new youtube_extractor(null, $first))->extract($cmid);
        $this->assertSame([['fr', false]], $first->requests, 'No course language: the first available');

        $onlyauto = $this->client(['title' => 't', 'description' => '', 'subtitles' => [], 'automatic' => ['de', 'es']], 'x');
        (new youtube_extractor(null, $onlyauto))->extract($cmid);
        $this->assertSame([['de', true]], $onlyauto->requests);
    }

    /**
     * (d) A YouTube video without subtitle gives title and description.
     */
    public function test_youtube_without_subtitle_gives_title_and_description(): void {
        $this->resetAfterTest();
        $cmid = $this->url('https://www.youtube.com/watch?v=abc123');
        $client = $this->client(['title' => 'Aula', 'description' => 'Descricao', 'subtitles' => [], 'automatic' => []]);

        $content = (new youtube_extractor(null, $client))->extract($cmid);

        $this->assertNotNull($content);
        $this->assertSame("Title:\nAula\n\nDescription:\nDescricao", $content->text);
        $this->assertSame([], $client->requests, 'No subtitle to ask for');
    }

    /**
     * No program, a failed program, nothing to read, an address that is not YouTube and a missing module: no content, no call.
     */
    public function test_youtube_without_content(): void {
        $this->resetAfterTest();
        $youtube = $this->url('https://www.youtube.com/watch?v=abc123');
        $meta = ['title' => 'Aula', 'description' => '', 'subtitles' => [], 'automatic' => []];

        $this->assertNull((new youtube_extractor(null, $this->client($meta, null, false)))->extract($youtube), 'Program not available');
        $this->assertNull((new youtube_extractor(null, $this->client(null)))->extract($youtube), 'The program failed');
        $this->assertNull(
            (new youtube_extractor(null, $this->client(['title' => '', 'description' => ' ', 'subtitles' => [], 'automatic' => []])))
                ->extract($youtube),
            'Nothing to read'
        );
        $other = $this->client($meta);
        $this->assertNull((new youtube_extractor(null, $other))->extract($this->url('https://example.com/video')), 'Not YouTube');
        $this->assertNull((new youtube_extractor(null, $other))->extract(99999999));
    }

    /**
     * The real client without a configured program, or with a missing file, is not available and answers null.
     */
    public function test_client_without_program_is_not_available(): void {
        $this->resetAfterTest();
        set_config('ytdlppath', '', 'local_assistente');

        $this->assertFalse((new ytdlp_client())->available());
        $this->assertNull((new ytdlp_client())->metadata('https://youtu.be/x'));
        $this->assertFalse((new ytdlp_client('/nonexistent/yt-dlp'))->available());
        $this->assertNull((new ytdlp_client('/nonexistent/yt-dlp'))->subtitle('https://youtu.be/x', 'en', false));
    }

    /**
     * DEC-68: a setting that was never saved uses the default path; only an empty saved value means no program.
     */
    public function test_client_uses_the_default_path_when_the_setting_was_never_saved(): void {
        $this->resetAfterTest();
        unset_config('ytdlppath', 'local_assistente');

        $this->assertSame('/usr/local/bin/yt-dlp', ytdlp_client::DEFAULT_PATH);
        $this->assertSame(is_executable(ytdlp_client::DEFAULT_PATH), (new ytdlp_client())->available());
    }

    /**
     * WebVTT to plain text: no header, time marks or tags, and no repeated line.
     */
    public function test_vtt_to_text(): void {
        $vtt = "WEBVTT\nKind: captions\nLanguage: en\n\n00:00:01.000 --> 00:00:02.000\nHello <c>there</c>\n\n"
            . "00:00:02.000 --> 00:00:03.000\nHello there\nsecond &amp; line\n";

        $this->assertSame("Hello there\nsecond & line", ytdlp_client::vtt_to_text($vtt));
    }
}
