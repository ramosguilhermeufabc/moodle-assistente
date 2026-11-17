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
 * Capabilities of local_assistente (DEC-30, DEC-45 D1, DEC-45 D2 as closed by CL-42).
 *
 * Access is by capability, never by role name. The default archetypes are the editing teacher (course context) and the
 * manager (also in the system context, which gives access to every course); the non-editing teacher does not receive
 * either capability by default, but an administrator can grant it through the Moodle permissions. The site
 * administrator passes through the Moodle model.
 *
 * `local/assistente:correctcriteria` is the "contexto apropriado" of DEC-45 D2: a different permission from `manage`,
 * so it is a different capability, granted by default only to `manager` (CL-42; see
 * application/capability_correction_policy.php). It is checked in the course context, so it follows the manager's own
 * default context levels (system, category and course) through role inheritance, exactly like `view` and `manage`
 * already do for the editing teacher and the manager.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Read the assistant of a course: queries, reports and history (DEC-30).
    'local/assistente:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Configure the assistant of a course: enable it and define the criteria (DEC-45 D1).
    'local/assistente:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Correct criteria already saved: the "administrador" of DEC-45 D2, closed by CL-42. Requires `manage` as well
    // (checked first in course_config_service); this capability only narrows who, among those with `manage`, may
    // also correct.
    'local/assistente:correctcriteria' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
