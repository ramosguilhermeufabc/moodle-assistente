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

use local_assistente\domain\material\youtube_url;
use local_assistente\domain\material_type;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\moodle\material_reader;
use local_assistente\infrastructure\moodle\ytdlp_client;

/**
 * Extracts the title, description and subtitle of a YouTube material (DEC-67; Q-008-03; T135).
 *
 * No YouTube API or key: the program yt-dlp (ytdlp_client) reads the data without downloading the video. The subtitle is the one in the
 * generation language of the course (course_section_reader::generation_language(), compared by the base language, so pt_br matches pt-BR); if there is
 * none, the first one available; automatic subtitles are accepted, a written one is preferred in each step. With no subtitle the content
 * is the title and the description. Without a configured program, with a failure of the program, or with nothing to read, there is no content.
 * Only an address that youtube_url accepts is handed to the program.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class youtube_extractor implements content_extractor {
    /** @var material_reader */
    private material_reader $reader;

    /** @var ytdlp_client */
    private ytdlp_client $client;

    /** @var course_section_reader */
    private course_section_reader $sections;

    /**
     * Constructor.
     *
     * @param material_reader|null $reader
     * @param ytdlp_client|null $client the only way to the program; a test passes a double
     * @param course_section_reader|null $sections gives the generation language of the course
     */
    public function __construct(
        ?material_reader $reader = null,
        ?ytdlp_client $client = null,
        ?course_section_reader $sections = null
    ) {
        $this->reader = $reader ?? new material_reader();
        $this->client = $client ?? new ytdlp_client();
        $this->sections = $sections ?? new course_section_reader();
    }

    #[\Override]
    public function type(): material_type {
        return material_type::YOUTUBE;
    }

    #[\Override]
    public function extract(int $cmid): ?extracted_content {
        $url = trim((string) $this->reader->get_url($cmid));
        if ($url === '' || !youtube_url::is_youtube($url) || !$this->client->available()) {
            return null;
        }
        $meta = $this->client->metadata($url);
        if ($meta === null) {
            return null;
        }

        $subtitle = null;
        $choice = $this->choose_language($meta, $this->generation_language($cmid));
        if ($choice !== null) {
            $subtitle = $this->client->subtitle($url, $choice[0], $choice[1]);
        }

        $parts = [];
        foreach (['Title' => $meta['title'], 'Description' => $meta['description'], 'Subtitles' => (string) $subtitle] as $label => $value) {
            if (trim($value) !== '') {
                $parts[] = "{$label}:\n" . trim($value);
            }
        }
        return $parts ? new extracted_content(material_type::YOUTUBE, implode("\n\n", $parts)) : null;
    }

    /**
     * Generation language of the course of the module.
     *
     * @param int $cmid
     * @return string Moodle language code
     */
    private function generation_language(int $cmid): string {
        $cm = get_coursemodule_from_id('url', $cmid, 0, false, IGNORE_MISSING);
        return $cm ? $this->sections->generation_language((int) $cm->course) : '';
    }

    /**
     * Which subtitle to read: language of the course (written, then automatic), else the first written, else the first automatic.
     *
     * @param array $meta result of ytdlp_client::metadata()
     * @param string $language Moodle language code of the course
     * @return array|null [subtitle language code, whether it is automatic]; null when the video has no subtitle
     */
    private function choose_language(array $meta, string $language): ?array {
        $base = strtolower(preg_split('/[_-]/', $language)[0] ?? '');
        foreach ([[$meta['subtitles'], false], [$meta['automatic'], true]] as [$codes, $automatic]) {
            foreach ($codes as $code) {
                if ($base !== '' && strtolower(preg_split('/[_-]/', $code)[0]) === $base) {
                    return [$code, $automatic];
                }
            }
        }
        if ($meta['subtitles']) {
            return [$meta['subtitles'][0], false];
        }
        return $meta['automatic'] ? [$meta['automatic'][0], true] : null;
    }
}
