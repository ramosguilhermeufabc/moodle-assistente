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
 * What is known about the erasure of a student in some courses (T142; DEC-58).
 *
 * There is no "completed": the absence of data is never taken as proof that an erasure was done. NO_RESIDUE_FOUND says only that the
 * checks of the plugin found nothing.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum erasure_state: string {
    /** No data by identifier, the name is available and no preserved text cites it. Nothing is said about what was done. */
    case NO_RESIDUE_FOUND = 'no_residue_found';
    /** No data by identifier, but the name is unavailable, so the preserved texts cannot be checked. Never a success. */
    case TEXTS_UNVERIFIED = 'texts_unverified';
    /** There is residue, no task will remove it and nothing records that it stopped. */
    case INCOMPLETE = 'incomplete';
    /** There is residue and a task with attempts left covers it. */
    case PENDING = 'pending';
    /** There is residue, no task is pending, and the attempts ended (a row with none left, or a stopped event). */
    case STOPPED = 'stopped';
}
