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

namespace local_assistente\infrastructure\llm;

use local_assistente\application\llm\guarded_llm_port;
use local_assistente\application\llm\student_identifier_exception;
use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the adapter factory (quickstart 009 Q5, database part; DEC-33 D1; DEC-34 D1 and D2).
 *
 * The reading of the key in web and cron/CLI execution, and its absence in clear in the database of a real site, are validated by
 * CL-32 (T138); here the code is checked to read no environment variable and to write no setting (DEC-69).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(adapter_factory::class)]
final class adapter_factory_test extends \advanced_testcase {
    /**
     * Under PHPUnit the simulated adapter is used; the real one exists and is the one outside tests (T134).
     */
    public function test_simulated_adapter_in_tests(): void {
        $this->assertSame(http_adapter::class, adapter_factory::REAL_ADAPTER);
        $this->assertFalse(adapter_factory::has_real_adapter());
        $this->assertInstanceOf(fake_adapter::class, adapter_factory::create_adapter());
        $this->assertInstanceOf(llm_port::class, adapter_factory::create());
        $this->assertInstanceOf(llm_port::class, new (adapter_factory::REAL_ADAPTER)());
    }

    /**
     * The port handed to consumers is behind the privacy guard, and works for a clean request.
     */
    public function test_port_is_guarded(): void {
        $port = adapter_factory::create();
        $this->assertInstanceOf(guarded_llm_port::class, $port);

        $this->assertTrue($port->evaluate(new llm_context(llm_use_case::QUIZ_COVERAGE, ['text' => 'ok']), 'Cover')->is_valid());
        $this->expectException(student_identifier_exception::class);
        $port->evaluate(new llm_context(llm_use_case::QUIZ_COVERAGE, ['author' => 'x']), 'Cover');
    }

    /**
     * Quickstart 009 Q5, database part: using the port writes no setting and no key in the database.
     */
    public function test_no_credential_in_the_plugin_configuration(): void {
        global $DB;
        $this->resetAfterTest();
        $before = $DB->count_records('config_plugins', ['plugin' => 'local_assistente']);

        adapter_factory::create()->evaluate(new llm_context(llm_use_case::MATERIAL_SUMMARY, ['text' => 'x']), 'Summarise');

        $this->assertSame($before, $DB->count_records('config_plugins', ['plugin' => 'local_assistente']));
        foreach ($DB->get_records('config_plugins', ['plugin' => 'local_assistente']) as $setting) {
            $this->assertDoesNotMatchRegularExpression('/key|secret|token|password|credential/i', $setting->name);
        }
    }

    /**
     * Architecture check (DEC-69): no environment variable, no ASSISTENTE_LLM_API_KEY, no set_config in the plugin code (the key is
     * saved by the administration screen only), and the plugin configuration is read only by http_adapter and ytdlp_client.
     */
    public function test_plugin_code_reads_no_environment_and_stores_no_setting(): void {
        global $CFG;
        $root = $CFG->dirroot . '/local/assistente';
        $mayread = ['http_adapter.php', 'ytdlp_client.php'];
        $offenders = [];
        $files = [$root . '/settings.php'];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/classes', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        foreach ($files as $path) {
            $code = '';
            foreach (token_get_all(file_get_contents($path)) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }
            $name = substr($path, strlen($root) + 1);
            if (preg_match('/ASSISTENTE_LLM_API_KEY|\bgetenv\s*\(|\$_ENV|\$_SERVER\s*\[\s*[\'"]ASSISTENTE|\bset_config\s*\(/', $code)) {
                $offenders[] = $name . ' (environment, key name or set_config)';
            }
            if (preg_match('/\bget_config\s*\(\s*[\'"]local_assistente/', $code) && !in_array(basename($path), $mayread, true)) {
                $offenders[] = $name . ' (reads the plugin configuration)';
            }
        }
        $this->assertSame([], $offenders);
    }

    /**
     * The code that reads the plugin configuration is exactly the two allowed classes (the check above is not vacuous).
     */
    public function test_the_two_allowed_readers_exist(): void {
        global $CFG;
        foreach (['infrastructure/llm/http_adapter.php', 'infrastructure/moodle/ytdlp_client.php'] as $file) {
            $this->assertStringContainsString("get_config('local_assistente'", file_get_contents($CFG->dirroot . '/local/assistente/classes/' . $file));
        }
    }
}
