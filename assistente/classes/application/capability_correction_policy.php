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

namespace local_assistente\application;

/**
 * Default correction policy: `local/assistente:correctcriteria` in the course context (DEC-45 D2, closed by CL-42).
 *
 * The capability isolates a permission different from `manage`: every editing teacher and manager has `manage`
 * (DEC-45 D1), but only the roles this capability is granted to may also correct criteria that are already saved. By
 * default that is only `manager` (db/access.php), which is the "administrador (`manager` ou administrador do site,
 * no contexto apropriado)" of DEC-45 D2.
 *
 * The check is the plain Access API (has_capability() in the course context), never a role name or archetype, so it
 * honours the manager's own default context levels (system, category and course, through inheritance), any custom
 * role granted the capability, and any override or prohibition an administrator configures. The site administrator
 * passes through the Moodle model (has_capability()'s `$doanything`, default true): unlike any other user, a
 * prohibition in the course context cannot deny the site administrator, and no role assignment is needed.
 *
 * This policy never checks `manage` itself: course_config_service calls access_service::require_manage() /
 * can_manage() before consulting this policy, and that order must stay (a user without `manage` is refused before
 * this policy is reached).
 *
 * Moodle API: has_capability(), context_course::instance(). Behaviour of `$doanything` and of CAP_PROHIBIT verified
 * by reading the MOODLE_502_STABLE sources of has_capability() and has_capability_in_accessdata() in accesslib.php
 * (CL-42); validated against a running Moodle 5.2 in course_config_service_test.php.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capability_correction_policy implements criteria_correction_policy {
    /** Capability that identifies who, among those with `manage`, may correct saved criteria. */
    public const CORRECT = 'local/assistente:correctcriteria';

    #[\Override]
    public function can_correct(int $courseid, ?int $userid = null): bool {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context !== false && has_capability(self::CORRECT, $context, $userid);
    }
}
