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

/**
 * Site settings of local_assistente: the path of the yt-dlp program (DEC-67) and the LLM URL, model and key (DEC-60 D2, DEC-69).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_assistente', get_string('pluginname', 'local_assistente'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configexecutable(
            'local_assistente/ytdlppath',
            get_string('settings:ytdlppath', 'local_assistente'),
            get_string('settings:ytdlppath_desc', 'local_assistente'),
            \local_assistente\infrastructure\moodle\ytdlp_client::DEFAULT_PATH
        ));

        // The LLM (DEC-60 D2, DEC-69): URL, model and key, all with no default, changed only by who can configure the site.
        $settings->add(new \local_assistente\infrastructure\llm\https_url_setting(
            'local_assistente/llmurl',
            get_string('settings:llmurl', 'local_assistente'),
            get_string('settings:llmurl_desc', 'local_assistente'),
            '',
            PARAM_RAW_TRIMMED
        ));

        $settings->add(new admin_setting_configtext(
            'local_assistente/llmmodel',
            get_string('settings:llmmodel', 'local_assistente'),
            get_string('settings:llmmodel_desc', 'local_assistente'),
            '',
            PARAM_RAW_TRIMMED
        ));

        $settings->add(new admin_setting_encryptedpassword(
            'local_assistente/llmapikey',
            get_string('settings:llmapikey', 'local_assistente'),
            get_string('settings:llmapikey_desc', 'local_assistente')
        ));

        // Zero Data Retention (DEC-71): on by default; the administrator decides whether to require it.
        $settings->add(new admin_setting_configcheckbox(
            'local_assistente/llmzdr',
            get_string('settings:llmzdr', 'local_assistente'),
            get_string('settings:llmzdr_desc', 'local_assistente'),
            1
        ));
    }
}
