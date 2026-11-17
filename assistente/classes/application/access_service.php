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
 * Authorization of the assistant by capability, checked in the backend (spec-005; DEC-30, DEC-45 D1).
 *
 * - `local/assistente:view` reads a course (queries, reports, history).
 * - `local/assistente:manage` configures it (enable, criteria).
 *
 * The decision is by capability in the course context, never by role name. A capability assigned in the system
 * context (default for the manager archetype) is inherited by every course, which gives access to all of them; the
 * site administrator passes through the Moodle model. The check is the one of the Access API, so overrides, prohibitions
 * and role assignments of the site are honoured. Every entry point (page, form handler, task) must call this service
 * before reading or writing; the UI hiding a link is not an authorization.
 *
 * This service only authorizes. Whether the assistant is enabled in the course is a different question, answered by
 * course_config_service.
 *
 * Moodle API: has_capability(), require_capability() and get_user_capability_course() (accesslib), with
 * \context_course::instance(). Verified by reading the MOODLE_502_STABLE sources; behaviour with the default
 * archetypes in a running Moodle 5.2 is validated in CL-26 (T041).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_service {
    /** Capability to read the assistant of a course. */
    public const VIEW = 'local/assistente:view';

    /** Capability to configure the assistant of a course. */
    public const MANAGE = 'local/assistente:manage';

    /**
     * Whether the user may read the assistant of the course.
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @return bool false also when the course does not exist
     */
    public function can_view(int $courseid, ?int $userid = null): bool {
        return $this->has($courseid, self::VIEW, $userid);
    }

    /**
     * Whether the user may configure the assistant of the course.
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @return bool false also when the course does not exist
     */
    public function can_manage(int $courseid, ?int $userid = null): bool {
        return $this->has($courseid, self::MANAGE, $userid);
    }

    /**
     * Require the capability to read the assistant of the course.
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @throws \core\exception\required_capability_exception when the user lacks it
     * @throws \core\exception\moodle_exception when the course does not exist
     */
    public function require_view(int $courseid, ?int $userid = null): void {
        require_capability(self::VIEW, $this->context($courseid), $userid);
    }

    /**
     * Require the capability to configure the assistant of the course.
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @throws \core\exception\required_capability_exception when the user lacks it
     * @throws \core\exception\moodle_exception when the course does not exist
     */
    public function require_manage(int $courseid, ?int $userid = null): void {
        require_capability(self::MANAGE, $this->context($courseid), $userid);
    }

    /**
     * Courses the user may read: every course where the user has the view capability, directly or by the system context.
     *
     * Hypothesis pending CL-26: for the site administrator this is every course returned by Moodle, which may include
     * the front page course; the consumer decides whether to show it.
     *
     * @param int|null $userid null for the current user
     * @return int[] course ids, ascending
     */
    public function accessible_course_ids(?int $userid = null): array {
        $courses = get_user_capability_course(self::VIEW, $userid);
        if (!$courses) {
            return [];
        }
        $ids = array_map(static fn($course) => (int) $course->id, $courses);
        sort($ids);
        return array_values(array_unique($ids));
    }

    /**
     * Whether the user has a capability in a course.
     *
     * @param int $courseid
     * @param string $capability
     * @param int|null $userid
     * @return bool
     */
    private function has(int $courseid, string $capability, ?int $userid): bool {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context !== false && has_capability($capability, $context, $userid);
    }

    /**
     * Course context, or an exception when the course does not exist.
     *
     * @param int $courseid
     * @return \context_course
     * @throws \core\exception\moodle_exception
     */
    private function context(int $courseid): \context_course {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if ($context === false) {
            throw new \core\exception\moodle_exception('invalidcourseid');
        }
        return $context;
    }
}
