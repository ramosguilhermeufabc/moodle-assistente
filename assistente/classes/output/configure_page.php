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

namespace local_assistente\output;

use local_assistente\application\access_service;
use local_assistente\application\course_config_service;
use local_assistente\application\sync\initial_sync_service;
use local_assistente\form\course_config_form;

/**
 * What the configuration page needs: the data of the form and the saving, behind `local/assistente:manage` (T126; spec-020).
 *
 * The page configure.php is thin. Enabling runs the initial synchronization (T121), so the service is built with it. The criteria are
 * shown read only to whoever may not correct them (DEC-45 D2).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configure_page {
    /** @var course_config_service */
    private course_config_service $service;

    /** @var access_service */
    private access_service $access;

    /**
     * Constructor.
     *
     * @param course_config_service|null $service null builds the one with the initial synchronization
     * @param access_service|null $access
     */
    public function __construct(?course_config_service $service = null, ?access_service $access = null) {
        $this->service = $service ?? new course_config_service(null, null, null, null, new initial_sync_service());
        $this->access = $access ?? new access_service();
    }

    /**
     * Custom data of the form.
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @return array
     * @throws \core\exception\required_capability_exception without `local/assistente:manage`
     */
    public function custom_data(int $courseid, ?int $userid = null): array {
        $this->access->require_manage($courseid, $userid);
        $criteria = $this->service->current_criteria($courseid);
        return [
            'courseid' => $courseid,
            'enabled' => $this->service->is_enabled($courseid),
            'criteria' => $criteria,
            'criteria_locked' => $criteria !== null && !$this->service->can_edit_criteria($courseid, $userid),
        ];
    }

    /**
     * Save a validated submission.
     *
     * @param \stdClass $data data of the form
     * @param int|null $userid null for the current user
     * @throws \core\exception\required_capability_exception without `local/assistente:manage`
     * @throws \local_assistente\application\criteria_immutable_exception for criteria that are read only for the user
     */
    public function save(\stdClass $data, ?int $userid = null): void {
        course_config_form::submit($data, $this->service, $userid);
    }

    /**
     * Save a validated submission and say so when the criteria could not be changed.
     *
     * The form of a user who may not correct saved criteria is frozen and never sends other values, so the refusal only happens for a
     * request that was built by hand. It is answered with a message instead of the error page of Moodle; nothing was changed. Any other
     * failure, such as the lack of `manage`, is not hidden.
     *
     * @param \stdClass $data data of the form
     * @param int|null $userid null for the current user
     * @return string|null the message to show when the criteria are read only for the user, null when everything was saved
     * @throws \core\exception\required_capability_exception without `local/assistente:manage`
     */
    public function save_or_explain(\stdClass $data, ?int $userid = null): ?string {
        try {
            $this->save($data, $userid);
        } catch (\local_assistente\application\criteria_immutable_exception $e) {
            return get_string('form:criteria_immutable', 'local_assistente');
        }
        return null;
    }
}
