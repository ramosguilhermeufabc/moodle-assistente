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

use local_assistente\domain\criteria\criterion_percentage;

/**
 * Repository of the criteria table, the second half of the configuration aggregate (plan 003, "Agregados").
 *
 * Besides the domain of forum_criterion (0 = disabled, 1 = participation required), it validates that the activity and
 * frequency criteria are percentages from 0 to 100 with at most three decimals (DEC-40 D1). Whether a criterion may be
 * changed after it was saved is a rule of the application (course_config_service, DEC-45 D2), not of this repository.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criteria_repository extends repository {
    /** @var string[] numeric criteria validated as percentages */
    private const PERCENTAGE_FIELDS = ['activity_criterion', 'frequency_criterion'];

    #[\Override]
    protected function table(): string {
        return 'local_assistente_criteria';
    }

    /**
     * Criteria of a configuration (one row at most, the schema has a unique key).
     *
     * @param int $configid
     * @return \stdClass|null
     */
    public function find_by_config(int $configid): ?\stdClass {
        return $this->find_by(['config_id' => $configid]);
    }

    #[\Override]
    protected function validate(\stdClass $record): void {
        parent::validate($record);
        foreach (self::PERCENTAGE_FIELDS as $field) {
            if (property_exists($record, $field) && !criterion_percentage::is_valid($record->$field)) {
                $shown = is_scalar($record->$field) ? (string) $record->$field : gettype($record->$field);
                throw new invalid_domain_value_exception(
                    "Value '{$shown}' of {$this->table()}.{$field} must be a percentage from 0 to 100 with at most 3 decimals."
                );
            }
        }
    }
}
