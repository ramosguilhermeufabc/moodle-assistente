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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the YouTube URL rule (DEC-33 D2).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(youtube_url::class)]
final class youtube_url_test extends \basic_testcase {
    /**
     * URLs that point to YouTube.
     *
     * @return array
     */
    public static function youtube_urls(): array {
        return [
            'watch' => ['https://www.youtube.com/watch?v=abc123'],
            'no www' => ['https://youtube.com/watch?v=abc123'],
            'mobile' => ['https://m.youtube.com/watch?v=abc123'],
            'short' => ['https://youtu.be/abc123'],
            'nocookie embed' => ['https://www.youtube-nocookie.com/embed/abc123'],
            'http' => ['http://www.youtube.com/watch?v=abc123'],
            'upper case host' => ['https://WWW.YouTube.COM/watch?v=abc123'],
            'surrounding spaces' => ['  https://youtu.be/abc123  '],
        ];
    }

    /**
     * URLs that do not.
     *
     * @return array
     */
    public static function other_urls(): array {
        return [
            'other site' => ['https://vimeo.com/12345'],
            'look-alike suffix' => ['https://notyoutube.com/watch?v=abc'],
            'look-alike prefix' => ['https://youtube.com.evil.example/watch?v=abc'],
            'youtube in the path' => ['https://example.com/youtube.com/watch'],
            'no scheme' => ['www.youtube.com/watch?v=abc'],
            'other scheme' => ['ftp://youtube.com/video'],
            'empty' => [''],
            'not a url' => ['not a url'],
        ];
    }

    /**
     * A YouTube URL is recognised.
     *
     * @param string $url
     */
    #[DataProvider('youtube_urls')]
    public function test_youtube_urls_are_recognised(string $url): void {
        $this->assertTrue(youtube_url::is_youtube($url));
    }

    /**
     * Any other URL is not.
     *
     * @param string $url
     */
    #[DataProvider('other_urls')]
    public function test_other_urls_are_not(string $url): void {
        $this->assertFalse(youtube_url::is_youtube($url));
    }
}
