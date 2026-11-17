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

namespace local_assistente\domain\material;

/**
 * State of the summary of a material version (DEC-50; stored in material_version.summary_status).
 *
 * - PENDING: the version exists and its summary was not produced yet. A new version always starts here: a new version does not
 *   imply a completed summary.
 * - COMPLETED: the summary was produced and persisted. It is final, and only a completed version is eligible to become current.
 * - FAILED: producing or persisting the summary failed. It never erases a valid summary and the version does not become current;
 *   a new attempt takes it back to PENDING.
 *
 * It is independent of the availability of the material (DEC-51).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum summary_status: string {
    /** The summary was not produced yet. */
    case PENDING = 'pending';
    /** The summary was produced and persisted. */
    case COMPLETED = 'completed';
    /** Producing or persisting the summary failed. */
    case FAILED = 'failed';

    /**
     * All allowed raw values.
     *
     * @return string[]
     */
    public static function values(): array {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }

    /**
     * Whether the status may change to another one: pending to completed or failed, and failed back to pending (new attempt).
     * Completed is final.
     *
     * @param self $to
     * @return bool
     */
    public function can_become(self $to): bool {
        return match ($this) {
            self::PENDING => $to === self::COMPLETED || $to === self::FAILED,
            self::FAILED => $to === self::PENDING,
            self::COMPLETED => false,
        };
    }
}
