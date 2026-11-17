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
 * The lang/en (required) and lang/pt_br packages must have the same string keys (DEC-46).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lang_parity_test extends \advanced_testcase {
    /**
     * Load the strings of one language pack.
     *
     * @param string $lang
     * @return array
     */
    private function load_strings(string $lang): array {
        global $CFG;
        $string = [];
        $file = "{$CFG->dirroot}/local/assistente/lang/{$lang}/local_assistente.php";
        $this->assertFileExists($file);
        include($file);
        return $string;
    }

    /**
     * Both packs define the same keys, and pluginname is defined.
     */
    public function test_en_and_pt_br_have_the_same_keys(): void {
        $en = $this->load_strings('en');
        $ptbr = $this->load_strings('pt_br');

        $this->assertArrayHasKey('pluginname', $en);
        $this->assertEqualsCanonicalizing(array_keys($en), array_keys($ptbr));
    }
}
