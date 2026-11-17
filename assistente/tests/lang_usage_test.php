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

namespace local_assistente;

/**
 * Every language string of the plugin that the code or a template asks for exists in both language packs (T127; spec-021; DEC-46).
 *
 * The parity between the two packs is checked by lang_parity_test; this checks the use: a string asked for with
 * `get_string('key', 'local_assistente')` or `{{#str}} key, local_assistente {{/str}}` must be defined.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lang_usage_test extends \basic_testcase {
    /**
     * Keys of the strings of a language pack.
     *
     * @param string $language
     * @return string[]
     */
    private static function defined_keys(string $language): array {
        global $CFG;
        $string = [];
        require($CFG->dirroot . "/local/assistente/lang/{$language}/local_assistente.php");
        return array_keys($string);
    }

    /**
     * Static keys asked for in the code and in the templates.
     *
     * @return string[] key => file where it is asked for
     */
    private static function used_keys(): array {
        global $CFG;
        $root = $CFG->dirroot . '/local/assistente';
        $used = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (!$file->isFile() || str_contains($path, '/tests/') || str_contains($path, '/lang/')) {
                continue;
            }
            $source = file_get_contents($path);
            $relative = substr($path, strlen($root) + 1);
            if ($file->getExtension() === 'php') {
                preg_match_all("/get_string\(\s*'([A-Za-z0-9_:]+)'\s*,\s*'local_assistente'/", $source, $matches);
                foreach ($matches[1] as $key) {
                    $used[$key] = $relative;
                }
                foreach (['query:' => 'query:', 'tab:' => 'tab:'] as $prefix) {
                    preg_match_all("/'(" . preg_quote($prefix, '/') . "[a-z_]+)'/", $source, $more);
                    foreach ($more[1] as $key) {
                        $used[$key] = $relative;
                    }
                }
            } else if ($file->getExtension() === 'mustache') {
                preg_match_all('/\{\{#str\}\}\s*([A-Za-z0-9_:]+)\s*,\s*local_assistente\s*\{\{\/str\}\}/', $source, $matches);
                foreach ($matches[1] as $key) {
                    $used[$key] = $relative;
                }
            }
        }
        return $used;
    }

    /**
     * Every key used is defined in English and in Brazilian Portuguese.
     */
    public function test_every_string_in_use_is_defined_in_both_packs(): void {
        $missing = [];
        foreach (['en', 'pt_br'] as $language) {
            $defined = self::defined_keys($language);
            foreach (self::used_keys() as $key => $file) {
                if (!in_array($key, $defined, true)) {
                    $missing[] = "{$language}: {$key} ({$file})";
                }
            }
        }
        $this->assertSame([], $missing);
    }

    /**
     * The strings asked for by name at run time: one title for each tab and one for each block of the report.
     */
    public function test_tabs_and_report_blocks_have_titles(): void {
        $keys = [];
        foreach ([...output\page_controller::INDIVIDUAL_TABS, ...output\page_controller::AGGREGATE_TABS, output\page_controller::TAB_REPORT,
            output\page_controller::TAB_HISTORY, output\page_controller::TAB_CONFIG] as $tab) {
            $keys[] = 'tab:' . $tab;
        }
        foreach (application\report\report_builder::BLOCKS as $block) {
            $keys[] = 'block:' . $block;
        }
        foreach (['en', 'pt_br'] as $language) {
            $this->assertSame([], array_values(array_diff($keys, self::defined_keys($language))), $language);
        }
    }
}
