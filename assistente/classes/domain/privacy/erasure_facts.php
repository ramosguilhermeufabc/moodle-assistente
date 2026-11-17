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
 * Everything the state of an erasure is decided from (T142): the residue found by identifier and in the texts, whether the name is
 * available, the retry tasks and the audit events of the student.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_facts {
    /**
     * Constructor.
     *
     * @param erasure_key $key
     * @param int[] $idresidue courses of the key where something of the student remains by identifier
     * @param bool $nameavailable whether the first and the last name can be searched in the texts
     * @param int[] $textresidue courses of the key where a preserved text still cites the name; only meaningful with the name available
     * @param erasure_task_fact[] $tasks the retry tasks of the student
     * @param erasure_event_fact[] $events the audit events of the student, in any order
     */
    public function __construct(
        public readonly erasure_key $key,
        public readonly array $idresidue,
        public readonly bool $nameavailable,
        public readonly array $textresidue = [],
        public readonly array $tasks = [],
        public readonly array $events = []
    ) {
    }
}
