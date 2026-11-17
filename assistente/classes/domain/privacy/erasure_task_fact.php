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
 * A retry task as the state of an erasure sees it (T142): its identifier, its courses, the attempts left and whether it is running.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_task_fact {
    /**
     * Constructor.
     *
     * @param int $id identifier of the task
     * @param int[] $courses
     * @param int $attempts attempts left; 0 is a task that stopped
     * @param bool $running whether the task is running now
     */
    public function __construct(
        public readonly int $id,
        public readonly array $courses,
        public readonly int $attempts,
        public readonly bool $running = false
    ) {
    }
}
