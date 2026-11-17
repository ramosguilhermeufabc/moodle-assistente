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

/**
 * Chooses the adapter behind the LLM port (DEC-33 D1; DEC-34 D1 and D2).
 *
 * In tests the simulated adapter is returned; outside tests, the real one (http_adapter, T134), always behind the guard of the
 * privacy rule.
 *
 * Credentials. This factory reads no setting and no credential. The real adapter reads its key only from the site configuration
 * saved by admin_setting_encryptedpassword (DEC-69), never from the environment and never from the Git. The simulated adapter
 * needs none. Whether the key is read in web and cron/CLI execution is validated in CL-32.
 *
 * Warning: the simulated adapter answers with fixed text marked "[SIMULADO]"; it is used only in tests.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class adapter_factory {
    /**
     * Class of the real adapter (an llm_port that can be built without arguments), or null when there is none (T134).
     */
    public const REAL_ADAPTER = http_adapter::class;

    /**
     * The port to use: the adapter behind the privacy guard.
     *
     * @return llm_port
     */
    public static function create(): llm_port {
        return new guarded_llm_port(self::create_adapter());
    }

    /**
     * The adapter alone, without the guard (for tests that need to script it).
     *
     * @return llm_port
     */
    public static function create_adapter(): llm_port {
        if (self::has_real_adapter()) {
            $class = self::REAL_ADAPTER;
            return new $class();
        }
        return new fake_adapter();
    }

    /**
     * Whether the real adapter is the one in use: there is one and the code is not running under PHPUnit.
     *
     * @return bool
     */
    public static function has_real_adapter(): bool {
        return self::REAL_ADAPTER !== null && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST);
    }
}
