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

/**
 * Calls the yt-dlp program to read a YouTube video without a YouTube API or key and without downloading the video (DEC-67).
 *
 * The program path is the site setting `local_assistente/ytdlppath` (admin_setting_configexecutable), default `/usr/local/bin/yt-dlp` (DEC-68). With no path, or with a file that
 * is not executable, nothing is called and every method answers null. The program is started with an argument list (proc_open without a
 * shell), never with a command string, and the address always comes after `--`. Any failure of the program (exit code, invalid output,
 * timeout of the site) is null: the material then has no YouTube content. This is the only place that runs the program, so a test replaces it.
 *
 * Moodle API: get_config(), make_request_directory().
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ytdlp_client {
    /** @var string path of the program when the setting was never saved (DEC-68); the default of the setting in settings.php */
    public const DEFAULT_PATH = '/usr/local/bin/yt-dlp';

    /** @var int seconds the site is asked to wait for the answer of YouTube */
    private const SOCKET_TIMEOUT = 30;

    /** @var string|null path given by the caller; null reads the setting at each call */
    private ?string $path;

    /**
     * Constructor.
     *
     * @param string|null $path executable path; null uses the site setting
     */
    public function __construct(?string $path = null) {
        $this->path = $path;
    }

    /**
     * Path of the program: the one given to the constructor, else the saved setting, else the default (an empty saved value means none).
     *
     * @return string
     */
    private function configured_path(): string {
        if ($this->path !== null) {
            return $this->path;
        }
        $saved = get_config('local_assistente', 'ytdlppath');
        return $saved === false ? self::DEFAULT_PATH : (string) $saved;
    }

    /**
     * Whether the program is configured and executable.
     *
     * @return bool
     */
    public function available(): bool {
        $path = $this->configured_path();
        return trim($path) !== '' && is_file($path) && is_executable($path);
    }

    /**
     * Title, description and the languages of the subtitles of a video.
     *
     * @param string $url address already validated as YouTube
     * @return array|null [title, description, subtitles => language codes, automatic => language codes]; null when unavailable or failed
     */
    public function metadata(string $url): ?array {
        $out = $this->run(['--dump-single-json', '--skip-download', '--no-playlist', '--no-warnings', '--ignore-no-formats-error', '--', $url]);
        $data = $out === null ? null : json_decode($out, true);
        if (!is_array($data)) {
            return null;
        }
        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'subtitles' => array_map('strval', array_keys((array) ($data['subtitles'] ?? []))),
            'automatic' => array_map('strval', array_keys((array) ($data['automatic_captions'] ?? []))),
        ];
    }

    /**
     * Text of the subtitle of a video in one language.
     *
     * @param string $url address already validated as YouTube
     * @param string $language subtitle language code, as listed by metadata()
     * @param bool $automatic whether the subtitle is an automatic one
     * @return string|null plain text without time marks; null when unavailable, failed or empty
     */
    public function subtitle(string $url, string $language, bool $automatic): ?string {
        $dir = make_request_directory();
        $out = $this->run([
            '--skip-download', '--no-playlist', '--no-warnings', '--ignore-no-formats-error',
            $automatic ? '--write-auto-subs' : '--write-subs',
            '--sub-langs', $language, '--sub-format', 'vtt',
            '--paths', $dir, '-o', 'subtitle', '--', $url,
        ]);
        $files = glob($dir . '/*.vtt');
        if ($out === null || !$files) {
            return null;
        }
        $text = self::vtt_to_text((string) file_get_contents($files[0]));
        return $text === '' ? null : $text;
    }

    /**
     * Plain text of a WebVTT file: no header, cue identifiers, time marks or tags, and no line repeated right after itself.
     *
     * @param string $vtt
     * @return string
     */
    public static function vtt_to_text(string $vtt): string {
        $lines = [];
        foreach (preg_split('/\R/', $vtt) as $line) {
            $line = trim(html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($line === '' || str_contains($line, '-->') || preg_match('/^(WEBVTT|Kind:|Language:|NOTE\b)/', $line)) {
                continue;
            }
            if (($lines ? end($lines) : null) !== $line) {
                $lines[] = $line;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Run the program with an argument list and return what it wrote to the standard output.
     *
     * @param string[] $arguments
     * @return string|null null when not available, not started or with a non-zero exit code
     */
    protected function run(array $arguments): ?string {
        if (!$this->available()) {
            return null;
        }
        $path = $this->configured_path();
        $process = @proc_open(
            array_merge([$path, '--socket-timeout', (string) self::SOCKET_TIMEOUT], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            return null;
        }
        $stdout = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0 ? $stdout : null;
    }
}
