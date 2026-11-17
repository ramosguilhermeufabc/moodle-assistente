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
 * Upgrade file test (spec-002 quickstart Q6, T019).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * The upgrade function exists, has no steps yet and succeeds from any old version.

     */
    public function test_the_upgrade_function_exists_and_has_no_steps(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/assistente/db/upgrade.php');

        $this->assertTrue(function_exists('xmldb_local_assistente_upgrade'));
        $this->assertTrue(xmldb_local_assistente_upgrade(2026102602));
        $this->assertTrue(xmldb_local_assistente_upgrade(0));
    }

    /**
     * Rule for future schema changes: every step ends with a plugin savepoint (checked on the file itself).
     */
    public function test_every_step_of_the_file_ends_with_a_savepoint(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/local/assistente/db/upgrade.php');
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= $token[1];
            } else if (!is_array($token)) {
                $code .= $token;
            }
        }
        $steps = preg_match_all('/\$oldversion\s*<\s*\d+/', $code);
        $savepoints = preg_match_all('/upgrade_plugin_savepoint\(\s*true\s*,\s*\d+\s*,\s*\'local\'\s*,\s*\'assistente\'\s*\)/', $code);
        $this->assertSame($steps, $savepoints);
    }
}
