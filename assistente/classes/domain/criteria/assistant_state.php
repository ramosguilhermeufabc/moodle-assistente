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

namespace local_assistente\domain\criteria;

/**
 * State of the assistant in a course (DEC-45 D3).
 *
 * A course without a configuration and a course with the assistant disabled behave the same: no data is processed and
 * the queries say "assistente não habilitado". The two cases are kept apart so that the service and the tests can tell
 * them apart, but only ENABLED processes data.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum assistant_state: string {
    /** The course has no configuration. */
    case NOT_CONFIGURED = 'not_configured';
    /** The course has a configuration with the assistant disabled. */
    case DISABLED = 'disabled';
    /** The assistant is enabled in the course. */
    case ENABLED = 'enabled';

    /**
     * Whether data is processed and consulted in this state.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this === self::ENABLED;
    }
}
