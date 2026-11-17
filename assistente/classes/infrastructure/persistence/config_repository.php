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

namespace local_assistente\infrastructure\persistence;

/**
 * Repository of the course configuration aggregate: `config` and `criteria` (plan 003; spec-005; DEC-45 D3).
 *
 * One configuration per course (unique key). A configuration is created disabled (`enabled` = 0, DEC-45 D3).
 * The criteria are 1:1 with the configuration. No operation deletes either: disabling the assistant keeps the history.
 *
 * Writing both rows as one unit is the job of the application service, with the unit of work.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config_repository extends repository {
    /** @var criteria_repository */
    private readonly criteria_repository $criteria;

    /**
     * Constructor.
     *
     * @param criteria_repository|null $criteria
     */
    public function __construct(?criteria_repository $criteria = null) {
        $this->criteria = $criteria ?? new criteria_repository();
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_config';
    }

    /**
     * Configuration of a course.
     *
     * @param int $courseid
     * @return \stdClass|null null when the course has no configuration
     */
    public function find_by_course(int $courseid): ?\stdClass {
        return $this->find_by(['course_id' => $courseid]);
    }

    /**
     * Ids of the courses where the assistant is enabled.
     *
     * @return int[] ascending
     */
    public function find_enabled_course_ids(): array {
        return array_map(
            static fn($config) => (int) $config->course_id,
            array_values($this->find_all_by(['enabled' => 1], 'course_id ASC'))
        );
    }

    /**
     * Create the configuration of a course, disabled unless said otherwise.
     *
     * @param int $courseid
     * @param bool $enabled
     * @param int|null $now creation time, the current time when null
     * @return \stdClass the stored configuration
     * @throws duplicate_record_exception when the course already has a configuration
     */
    public function create(int $courseid, bool $enabled = false, ?int $now = null): \stdClass {
        $id = $this->insert((object) [
            'course_id' => $courseid,
            'enabled' => $enabled ? 1 : 0,
            'created_at' => $now ?? time(),
        ]);
        return $this->find($id);
    }

    /**
     * Enable or disable the assistant for a configuration. Nothing else changes and nothing is deleted.
     *
     * @param int $configid
     * @param bool $enabled
     */
    public function set_enabled(int $configid, bool $enabled): void {
        $this->update((object) ['id' => $configid, 'enabled' => $enabled ? 1 : 0]);
    }

    /**
     * Criteria of a configuration.
     *
     * @param int $configid
     * @return \stdClass|null
     */
    public function find_criteria(int $configid): ?\stdClass {
        return $this->criteria->find_by_config($configid);
    }

    /**
     * Insert the criteria of a configuration.
     *
     * @param int $configid
     * @param float|int|string $activity percentage 0 to 100, at most three decimals
     * @param int $forum 0 = disabled, 1 = participation required
     * @param float|int|string $frequency percentage 0 to 100, at most three decimals
     * @return \stdClass the stored criteria
     * @throws invalid_domain_value_exception when a value is outside its domain
     * @throws duplicate_record_exception when the configuration already has criteria
     */
    public function insert_criteria(int $configid, float|int|string $activity, int $forum, float|int|string $frequency): \stdClass {
        $id = $this->criteria->insert((object) [
            'config_id' => $configid,
            'activity_criterion' => $activity,
            'forum_criterion' => $forum,
            'frequency_criterion' => $frequency,
        ]);
        return $this->criteria->find($id);
    }

    /**
     * Replace the values of the criteria of a configuration.
     *
     * The repository does not decide who may do it or when (DEC-45 D2): the application service does.
     *
     * @param int $configid
     * @param float|int|string $activity
     * @param int $forum
     * @param float|int|string $frequency
     * @return \stdClass the stored criteria
     * @throws invalid_domain_value_exception when a value is outside its domain
     * @throws \coding_exception when the configuration has no criteria
     */
    public function update_criteria(int $configid, float|int|string $activity, int $forum, float|int|string $frequency): \stdClass {
        $current = $this->criteria->find_by_config($configid);
        if ($current === null) {
            throw new \coding_exception("The configuration {$configid} has no criteria to update.");
        }
        $this->criteria->update((object) [
            'id' => $current->id,
            'activity_criterion' => $activity,
            'forum_criterion' => $forum,
            'frequency_criterion' => $frequency,
        ]);
        return $this->criteria->find((int) $current->id);
    }
}
