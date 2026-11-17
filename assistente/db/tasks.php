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

/**
 * Scheduled tasks of local_assistente (DEC-32 D1).
 *
 * The queue runs every 15 minutes by default. The administrator changes the interval in the native schedule of the Moodle
 * scheduled tasks; the plugin has no own form or setting for it.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_assistente\task\process_queue',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Final recalculation of the results of a questionnaire whose closing time passed (T074; DEC-36 D4).
    [
        'classname' => '\local_assistente\task\detect_quiz_close',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Recalculation of the frequency when a reference date passes with no event of Moodle (T088; DEC-39 D2).
    [
        'classname' => '\local_assistente\task\detect_reference_dates',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Generation of the due versions of the reports, every 15 minutes (T116; the 72 hours and the final update are decided by the schedule, not by this interval).
    [
        'classname' => '\local_assistente\task\report_dispatcher',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Promotion of the completed version that never became current, through the switch of DEC-62 (CL-51; completed versions only).
    [
        'classname' => '\local_assistente\task\reconcile_material_versions',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
