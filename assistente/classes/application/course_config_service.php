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

namespace local_assistente\application;

use local_assistente\application\sync\initial_sync;
use local_assistente\domain\criteria\assistant_state;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * Configuration of the assistant in a course: enabling and criteria (spec-005; DEC-45).
 *
 * - Every write requires `local/assistente:manage` in the course, checked here in the backend (D1).
 * - The assistant starts disabled in each course. Whoever manages it can enable it, with optional criteria (D3).
 *   Enabling, or enabling again, runs the initial synchronization through the extension point, after the flag is saved.
 *   Disabling only clears the flag: nothing is deleted, so the history is kept (D3).
 * - A course with no configuration, or with the assistant disabled, is "not enabled": no data is processed and the queries
 *   say "assistente não habilitado". Event creation and queue processing must check is_enabled().
 * - Criteria are read only after they are saved. Only a user accepted by the correction policy may correct them, and the
 *   correction applies to new evaluations only: this service never triggers a recalculation, and the criteria check keeps
 *   the limits it used in its own context (D2).
 *
 * Writes of the configuration and the criteria are one transactional unit, without lock (plan 003, "Unidades
 * transacionais"). Whether the transaction wraps the initial synchronization is deliberately not the case: the sync runs
 * after the commit.
 *
 * Pending, not decided here: what happens when the initial synchronization fails after the flag was saved (the course
 * stays enabled and the exception propagates; how to retry belongs to T121 and CL-40). Who counts as "administrador"
 * for correcting criteria was closed by CL-42 (see criteria_correction_policy).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_config_service {
    /** @var config_repository */
    private config_repository $repository;

    /** @var access_service */
    private access_service $access;

    /** @var criteria_correction_policy */
    private criteria_correction_policy $correction;

    /** @var unit_of_work */
    private unit_of_work $unitofwork;

    /** @var initial_sync|null */
    private ?initial_sync $sync;

    /**
     * Constructor.
     *
     * @param config_repository|null $repository
     * @param access_service|null $access
     * @param criteria_correction_policy|null $correction
     * @param unit_of_work|null $unitofwork
     * @param initial_sync|null $sync the initial synchronization (T121); none runs when null
     */
    public function __construct(
        ?config_repository $repository = null,
        ?access_service $access = null,
        ?criteria_correction_policy $correction = null,
        ?unit_of_work $unitofwork = null,
        ?initial_sync $sync = null
    ) {
        $this->repository = $repository ?? new config_repository();
        $this->access = $access ?? new access_service();
        $this->correction = $correction ?? new capability_correction_policy();
        $this->unitofwork = $unitofwork ?? new unit_of_work();
        $this->sync = $sync;
    }

    /**
     * State of the assistant in a course. For internal use (observers, tasks, queries): no authorization.
     *
     * @param int $courseid
     * @return assistant_state
     */
    public function state(int $courseid): assistant_state {
        $config = $this->repository->find_by_course($courseid);
        if ($config === null) {
            return assistant_state::NOT_CONFIGURED;
        }
        return (int) $config->enabled === 1 ? assistant_state::ENABLED : assistant_state::DISABLED;
    }

    /**
     * Whether the assistant is enabled in a course; false for a course without configuration.
     *
     * @param int $courseid
     * @return bool
     */
    public function is_enabled(int $courseid): bool {
        return $this->state($courseid)->is_enabled();
    }

    /**
     * Enable the assistant in the course, creating the configuration when there is none. Criteria are optional.
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @return bool true when the assistant became enabled now (and the initial synchronization ran); false when it already was
     * @throws \core\exception\required_capability_exception without `local/assistente:manage`
     */
    public function enable(int $courseid, ?int $userid = null): bool {
        $this->access->require_manage($courseid, $userid);

        $changed = $this->unitofwork->run(function () use ($courseid): bool {
            $config = $this->repository->find_by_course($courseid);
            if ($config === null) {
                $this->repository->create($courseid, true);
                return true;
            }
            if ((int) $config->enabled === 1) {
                return false;
            }
            $this->repository->set_enabled((int) $config->id, true);
            return true;
        });

        if ($changed && $this->sync !== null) {
            $this->sync->run($courseid);
        }
        return $changed;
    }

    /**
     * Disable the assistant. Only the flag changes: the history, the configuration and the criteria stay.
     *
     * No event is created and nothing is processed while disabled; events already pending stay pending (plan 020).
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @return bool true when it was enabled and is now disabled; false when there was nothing to do
     * @throws \core\exception\required_capability_exception without `local/assistente:manage`
     */
    public function disable(int $courseid, ?int $userid = null): bool {
        $this->access->require_manage($courseid, $userid);

        return $this->unitofwork->run(function () use ($courseid): bool {
            $config = $this->repository->find_by_course($courseid);
            if ($config === null || (int) $config->enabled !== 1) {
                return false;
            }
            $this->repository->set_enabled((int) $config->id, false);
            return true;
        });
    }

    /**
     * Save the criteria of a course.
     *
     * First save: creates the configuration (disabled) if the course has none, and the criteria, as one unit.
     * Later saves: only a user accepted by the correction policy may change them, and the change applies to new evaluations
     * only (nothing is recalculated). Anyone else gets criteria_immutable_exception.
     *
     * @param int $courseid
     * @param float|int|string $activity percentage 0 to 100, at most three decimals
     * @param int $forum 0 = disabled, 1 = participation required
     * @param float|int|string $frequency percentage 0 to 100, at most three decimals
     * @param int|null $userid null for the current user
     * @return \stdClass the stored criteria
     * @throws \core\exception\required_capability_exception without `local/assistente:manage`
     * @throws criteria_immutable_exception when the criteria were saved and the user may not correct them
     * @throws \local_assistente\infrastructure\persistence\invalid_domain_value_exception for a value outside its domain (nothing is saved)
     */
    public function save_criteria(
        int $courseid,
        float|int|string $activity,
        int $forum,
        float|int|string $frequency,
        ?int $userid = null
    ): \stdClass {
        $this->access->require_manage($courseid, $userid);

        return $this->unitofwork->run(function () use ($courseid, $activity, $forum, $frequency, $userid): \stdClass {
            $config = $this->repository->find_by_course($courseid) ?? $this->repository->create($courseid);
            if ($this->repository->find_criteria((int) $config->id) === null) {
                return $this->repository->insert_criteria((int) $config->id, $activity, $forum, $frequency);
            }
            if (!$this->correction->can_correct($courseid, $userid)) {
                throw new criteria_immutable_exception('The criteria are saved and read only for this user.');
            }
            return $this->repository->update_criteria((int) $config->id, $activity, $forum, $frequency);
        });
    }

    /**
     * Whether the user may change the criteria now: has `manage`, and either none are saved or the user may correct them.
     *
     * For the form and the UI to render the criteria editable or read only; the check on save is repeated by
     * save_criteria().
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @return bool
     */
    public function can_edit_criteria(int $courseid, ?int $userid = null): bool {
        if (!$this->access->can_manage($courseid, $userid)) {
            return false;
        }
        $config = $this->repository->find_by_course($courseid);
        $saved = $config !== null && $this->repository->find_criteria((int) $config->id) !== null;
        return !$saved || $this->correction->can_correct($courseid, $userid);
    }

    /**
     * Criteria of a course for a user who may read it.
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @return \stdClass|null null when the course has no criteria
     * @throws \core\exception\required_capability_exception without `local/assistente:view`
     */
    public function get_criteria(int $courseid, ?int $userid = null): ?\stdClass {
        $this->access->require_view($courseid, $userid);
        return $this->current_criteria($courseid);
    }

    /**
     * Criteria in force, for internal processing (the criteria check): no authorization, no user.
     *
     * @param int $courseid
     * @return \stdClass|null null when the course has no configuration or no criteria
     */
    public function current_criteria(int $courseid): ?\stdClass {
        $config = $this->repository->find_by_course($courseid);
        return $config === null ? null : $this->repository->find_criteria((int) $config->id);
    }
}
