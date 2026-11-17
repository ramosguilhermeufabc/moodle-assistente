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


namespace local_assistente\application\privacy;

/**
 * Internal signal of student_event_eraser: events of the student that were not in the set of locks when the transaction read them. Not
 * meant to leave the eraser.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_event_set_changed extends \RuntimeException {
    /**
     * Constructor.
     *
     * @param int[] $ids the events that were not locked
     */
    public function __construct(
        /** @var int[] events not locked */
        public readonly array $ids
    ) {
        parent::__construct('Events of the student appeared after the reading: ' . implode(', ', $ids));
    }
}
