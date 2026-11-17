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

use local_assistente\application\access_service;
use local_assistente\application\course_config_service;
use local_assistente\domain\criteria\criterion_percentage;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form of the configuration of the assistant of a course: enabling and the criteria (spec-020; DEC-45 D1 and D2; DEC-40 D1).
 *
 * The form is only the interface and the validation of the format. The submission is delegated to course_config_service
 * (submit()), which checks `local/assistente:manage` in the backend before it persists anything; the form does not decide who
 * may save. Once the criteria are saved they are shown read only to a teacher and stay editable for the user that the correction
 * policy accepts (the `criteria_locked` custom data is computed by the page with course_config_service::can_edit_criteria()).
 *
 * Custom data: `courseid` (int), `criteria_locked` (bool, default false), `enabled` (bool, current state), `criteria` (stdClass|null
 * with activity_criterion, forum_criterion, frequency_criterion of the saved criteria).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_config_form extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $data = $this->_customdata;
        $locked = !empty($data['criteria_locked']);
        $criteria = $data['criteria'] ?? null;

        $mform->addElement('hidden', 'id', (int) $data['courseid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('advcheckbox', 'enabled', get_string('form:enabled', 'local_assistente'));
        $mform->setDefault('enabled', !empty($data['enabled']) ? 1 : 0);

        $mform->addElement('text', 'activity_criterion', get_string('form:activity_criterion', 'local_assistente'));
        $mform->setType('activity_criterion', PARAM_RAW_TRIMMED);
        $mform->addElement('advcheckbox', 'forum_criterion', get_string('form:forum_criterion', 'local_assistente'));
        $mform->addElement('text', 'frequency_criterion', get_string('form:frequency_criterion', 'local_assistente'));
        $mform->setType('frequency_criterion', PARAM_RAW_TRIMMED);

        if ($criteria !== null) {
            $mform->setDefault('activity_criterion', self::display_number($criteria->activity_criterion));
            $mform->setDefault('forum_criterion', (int) $criteria->forum_criterion);
            $mform->setDefault('frequency_criterion', self::display_number($criteria->frequency_criterion));
        }
        if ($locked) {
            $mform->addElement('static', 'criteria_readonly', '', get_string('form:criteria_readonly', 'local_assistente'));
            foreach (['activity_criterion', 'forum_criterion', 'frequency_criterion'] as $name) {
                $mform->hardFreeze($name);
            }
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($this->_customdata['criteria_locked'])) {
            return $errors;
        }
        foreach (['activity_criterion', 'frequency_criterion'] as $name) {
            if (!criterion_percentage::is_valid(self::normalise($data[$name] ?? ''))) {
                $errors[$name] = get_string('form:error_percentage', 'local_assistente');
            }
        }
        return $errors;
    }

    /**
     * A typed number with a decimal comma or point as the plain decimal string that the domain validates.
     *
     * @param mixed $value
     * @return string
     */
    public static function normalise(mixed $value): string {
        return str_replace(',', '.', trim((string) $value));
    }

    /**
     * A stored number for the text field: no trailing zeros ("60", "33.333").
     *
     * @param mixed $value
     * @return string
     */
    private static function display_number(mixed $value): string {
        $text = number_format((float) $value, criterion_percentage::DECIMALS, '.', '');
        return rtrim(rtrim($text, '0'), '.');
    }

    /**
     * Apply a validated submission through the configuration service.
     *
     * Criteria first (they create the configuration, disabled), then the enabled flag, so enabling runs the initial synchronization
     * with the criteria already saved. The service checks `manage` before every write; criteria are sent only when the user may edit them
     * (otherwise the submitted values are ignored, as the form shows them frozen).
     *
     * @param \stdClass $data the validated data of the form
     * @param course_config_service|null $service
     * @param int|null $userid null for the current user
     * @throws \core\exception\required_capability_exception without `local/assistente:manage`
     * @throws \local_assistente\application\criteria_immutable_exception when the criteria are saved and the user may not correct them
     */
    public static function submit(\stdClass $data, ?course_config_service $service = null, ?int $userid = null): void {
        $service ??= new course_config_service();
        $courseid = (int) $data->id;

        (new access_service())->require_manage($courseid, $userid); // Backend check, before anything is persisted (DEC-45 D1).
        if ($service->can_edit_criteria($courseid, $userid)) {
            $service->save_criteria(
                $courseid,
                self::normalise($data->activity_criterion),
                (int) !empty($data->forum_criterion),
                self::normalise($data->frequency_criterion),
                $userid
            );
        } else if (self::differs_from_saved($data, $service->current_criteria($courseid))) {
            // A frozen form never sends other values: this is a direct call trying to change criteria that are read only (Q2).
            throw new \local_assistente\application\criteria_immutable_exception('The criteria are saved and read only for this user.');
        }
        if (!empty($data->enabled)) {
            $service->enable($courseid, $userid);
        } else {
            $service->disable($courseid, $userid);
        }
    }

    /**
     * Whether the submitted criteria are not the saved ones.
     *
     * @param \stdClass $data
     * @param \stdClass|null $saved
     * @return bool
     */
    private static function differs_from_saved(\stdClass $data, ?\stdClass $saved): bool {
        if ($saved === null || !isset($data->activity_criterion, $data->frequency_criterion)) {
            return false;
        }
        return abs((float) self::normalise($data->activity_criterion) - (float) $saved->activity_criterion) > 1e-9
            || abs((float) self::normalise($data->frequency_criterion) - (float) $saved->frequency_criterion) > 1e-9
            || (int) !empty($data->forum_criterion) !== (int) $saved->forum_criterion;
    }
}
