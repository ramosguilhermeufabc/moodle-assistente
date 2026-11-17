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

/**
 * Tells whether a URL points to YouTube (DEC-33 D2: a mod_url "that points to YouTube" is a functional material).
 *
 * Only the host is considered: youtube.com, youtu.be and youtube-nocookie.com, with or without the www or m
 * prefix, over http or https. The path is not validated, so a channel or a playlist URL also counts.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class youtube_url {
    /** @var string[] hosts accepted, without the www or m prefix */
    private const HOSTS = ['youtube.com', 'youtu.be', 'youtube-nocookie.com'];

    /**
     * Whether the URL points to YouTube.
     *
     * @param string $url
     * @return bool
     */
    public static function is_youtube(string $url): bool {
        $parts = parse_url(trim($url));
        if ($parts === false || !isset($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower($parts['host']);
        $host = preg_replace('/^(www|m)\./', '', $host);
        return in_array($host, self::HOSTS, true);
    }
}
