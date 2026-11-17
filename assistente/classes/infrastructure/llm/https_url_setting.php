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

namespace local_assistente\infrastructure\llm;

/**
 * Site setting of the LLM endpoint: a text setting that, when saved, only accepts an https URL without user or password (DEC-60 D2).
 *
 * Moodle API: admin_setting_configtext, with its validate() method extended. An empty value is accepted (no default).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class https_url_setting extends \admin_setting_configtext {
    #[\Override]
    public function validate($data) {
        $valid = parent::validate($data);
        if ($valid !== true) {
            return $valid;
        }
        $data = trim((string) $data);
        return ($data === '' || http_adapter::is_acceptable_url($data))
            ? true : get_string('settings:llmurl_invalid', 'local_assistente');
    }
}
