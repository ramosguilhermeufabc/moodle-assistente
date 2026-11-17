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

namespace local_assistente\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Filters of the query pages: group, section and report version (spec-020; DEC-43 D1; DEC-44).
 *
 * The group filter exists only in the individual queries (`individual` custom data); the aggregate queries have no group filter
 * (DEC-43). The choices are given by the page, already limited to what the user may see (the groups from student_scope::groups(),
 * the sections of the course, the versions of the report); the form accepts nothing outside them, and the query services apply the
 * visibility again in the backend.
 *
 * The version and the execution date of the report and of its history (Q-020-02, DEC-44): `versions` offers the numbers that exist and
 * `dates` adds the first and the last day of the execution date, both optional and both inclusive. The days are those of the site time
 * zone (DEC-44 D2), not of the user; a first day after the last day is a validation error of the form. version_filter turns the choices
 * into the limits.
 *
 * Custom data: `individual` (bool), `groups` (id => name), `sections` (id => name), `versions` (number => label), `dates` (bool).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_filter_form extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $data = $this->_customdata;
        $all = ['' => get_string('form:filter_all', 'local_assistente')];

        $mform->addElement('hidden', 'id', (int) ($data['courseid'] ?? 0));
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tab', (string) ($data['tab'] ?? ''));
        $mform->setType('tab', PARAM_ALPHANUMEXT);

        if (!empty($data['individual'])) {
            $mform->addElement('select', 'groupid', get_string('form:filter_group', 'local_assistente'), $all + ($data['groups'] ?? []));
            $mform->setType('groupid', PARAM_RAW);
        }
        if (!empty($data['sections'])) {
            $mform->addElement('select', 'sectionid', get_string('form:filter_section', 'local_assistente'), $all + $data['sections']);
            $mform->setType('sectionid', PARAM_RAW);
        }
        if (!empty($data['versions'])) {
            $mform->addElement('select', 'version', get_string('form:filter_version', 'local_assistente'), $all + $data['versions']);
            $mform->setType('version', PARAM_RAW);
        }
        if (!empty($data['dates'])) {
            $options = ['optional' => true, 'timezone' => \core_date::get_server_timezone()];
            $mform->addElement('date_selector', 'from', get_string('form:filter_from', 'local_assistente'), $options);
            $mform->addElement('date_selector', 'to', get_string('form:filter_to', 'local_assistente'), $options);
        }
        $this->add_action_buttons(false, get_string('form:filter_apply', 'local_assistente'));
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $choices = [
            'groupid' => !empty($this->_customdata['individual']) ? ($this->_customdata['groups'] ?? []) : [],
            'sectionid' => $this->_customdata['sections'] ?? [],
            'version' => $this->_customdata['versions'] ?? [],
        ];
        foreach ($choices as $name => $allowed) {
            $value = $data[$name] ?? '';
            if ($value !== '' && $value !== null && !array_key_exists($value, $allowed)) {
                $errors[$name] = get_string('form:filter_error', 'local_assistente');
            }
        }
        $from = (int) ($data['from'] ?? 0);
        $to = (int) ($data['to'] ?? 0);
        if (!empty($this->_customdata['dates']) && $from > 0 && $to > 0 && $from > $to) {
            $errors['to'] = get_string('form:filter_period_error', 'local_assistente');
        }
        return $errors;
    }

    /**
     * The chosen filters of a validated submission; an empty choice is null.
     *
     * @param \stdClass|null $data the validated data (get_data())
     * @return array{groupid: int|null, sectionid: int|null, version: int|null}
     */
    public static function selected(?\stdClass $data): array {
        $pick = static fn(string $name): ?int => ($data !== null && isset($data->{$name}) && $data->{$name} !== '')
            ? (int) $data->{$name} : null;
        return ['groupid' => $pick('groupid'), 'sectionid' => $pick('sectionid'), 'version' => $pick('version')];
    }

    /**
     * The first and the last day chosen, as instants of those days in the site time zone; an empty choice is null. They are the input of
     * version_filter::of(), which makes the limits inclusive.
     *
     * @param \stdClass|null $data the validated data (get_data())
     * @return array{from: int|null, to: int|null}
     */
    public static function selected_period(?\stdClass $data): array {
        $pick = static fn(string $name): ?int => ($data !== null && !empty($data->{$name})) ? (int) $data->{$name} : null;
        return ['from' => $pick('from'), 'to' => $pick('to')];
    }
}
