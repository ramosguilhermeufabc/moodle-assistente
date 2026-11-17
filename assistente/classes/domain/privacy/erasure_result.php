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

namespace local_assistente\domain\privacy;

/**
 * The answer about an erasure (T142): the state and what supports it.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_result {
    /** The evidence is only the data now. */
    public const EVIDENCE_DATA = 'data';
    /** The data and a `completed` event of the exact case. */
    public const EVIDENCE_DATA_AND_EVENTS = 'data+events';

    /**
     * Constructor.
     *
     * @param erasure_state $state
     * @param bool $closedbyretry true only for NO_RESIDUE_FOUND with a `completed` event of the exact key, of its latest generation, made
     *        by the retry task
     * @param string|null $closedby 'retry_task' or 'new_request' when the exact case has a `completed` event of its latest generation
     * @param string $evidence EVIDENCE_DATA or EVIDENCE_DATA_AND_EVENTS
     */
    public function __construct(
        public readonly erasure_state $state,
        public readonly bool $closedbyretry = false,
        public readonly ?string $closedby = null,
        public readonly string $evidence = self::EVIDENCE_DATA
    ) {
    }
}
