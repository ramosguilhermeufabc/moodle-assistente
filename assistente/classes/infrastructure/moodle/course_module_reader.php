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

namespace local_assistente\infrastructure\moodle;

/**
 * Reads course modules: section, visibility, availability for a user, activity/resource and due dates
 * (spec-004; DEC-26, DEC-39, DEC-44).
 *
 * `cmid` identifies the module in the course. Availability for a student is the module being visible to that
 * student with the access restrictions of Moodle met (DEC-26); the reader does not decide eligibility.
 *
 * Moodle API: get_fast_modinfo($course, $userid) with core_course\cm_info (visible, uservisible, sectionid,
 * sectionnum, modname, instance, name, deletioninprogress); plugin_supports('mod', ..., FEATURE_MOD_ARCHETYPE).
 * Verified by reading the MOODLE_502_STABLE sources; behaviour in a running Moodle 5.2 is validated in CL-36 and
 * CL-02 (tasks T032 and T036). Hypothesis: for a student, uservisible equals "visible and restrictions met".
 *
 * DML justification: the due or closing date has no API common to all modules, so it is read from the
 * module table itself. The map below is a hypothesis of the core modules and their date columns, verified in
 * CL-36 (T032); a module outside the map has no date, and the caller falls back to the course end date (DEC-39 D2).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_reader {
    /** @var string[] module name => column of its due/closing date (hypothesis, CL-36) */
    public const DUE_DATE_FIELDS = [
        'assign' => 'duedate',
        'quiz' => 'timeclose',
        'forum' => 'duedate',
        'lesson' => 'deadline',
        'workshop' => 'submissionend',
        'choice' => 'timeclose',
        'data' => 'timeavailableto',
    ];

    /**
     * Read one module.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int|null $userid availability is evaluated for this user (a student); null leaves it unset
     * @return \stdClass|null null when the module does not exist in the course or is being deleted
     */
    public function get_module(int $courseid, int $cmid, ?int $userid = null): ?\stdClass {
        $modules = $this->get_modules($courseid, $userid);
        return $modules[$cmid] ?? null;
    }

    /**
     * Read all modules of a course, except those being deleted.
     *
     * Each item has: cmid, course_id, section_id (course_sections.id), section_number, modname, instance, name,
     * visible, is_resource, completion_enabled (bool), gradable (bool: numeric or scale grade item), available_to_user (bool|null), due_date (int|null) and due_date_field (string|null).
     *
     * @param int $courseid
     * @param int|null $userid availability is evaluated for this user; null leaves it unset
     * @return \stdClass[] indexed by cmid; [] when the course does not exist
     */
    public function get_modules(int $courseid, ?int $userid = null): array {
        global $DB;
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return [];
        }

        $modinfo = get_fast_modinfo($courseid, $userid ?? 0);
        $gradable = $this->gradable_instances($courseid);
        $completion = new \completion_info(get_course($courseid));
        $modules = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            $date = $this->due_date($cm->modname, (int) $cm->instance);
            $modules[(int) $cm->id] = (object) [
                'cmid' => (int) $cm->id,
                'course_id' => (int) $cm->course,
                'section_id' => (int) $cm->sectionid,
                'section_number' => (int) $cm->sectionnum,
                'modname' => (string) $cm->modname,
                'instance' => (int) $cm->instance,
                'name' => (string) $cm->name,
                'visible' => (bool) $cm->visible,
                'is_resource' => $this->is_resource($cm->modname),
                'completion_enabled' => (bool) $completion->is_enabled($cm),
                'gradable' => isset($gradable[$cm->modname . ':' . $cm->instance]),
                'available_to_user' => $userid === null ? null : (bool) $cm->uservisible,
                'due_date' => $date['value'],
                'due_date_field' => $date['field'],
            ];
        }
        return $modules;
    }

    /**
     * For each module, how many of the given users it is available to (DEC-26).
     *
     * One reading of the course per user, with the same rule as get_modules(): `available_to_user`. The users are counted once each.
     * It decides nothing: whether the count is enough is for the caller (the snapshot needs all the relevant students, T064).
     *
     * @param int $courseid
     * @param int[] $userids
     * @return int[] number of users by cmid; a module available to nobody is absent
     */
    public function count_available_to_users(int $courseid, array $userids): array {
        $counts = [];
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            foreach ($this->available_cmids($courseid, $userid) as $cmid) {
                $counts[$cmid] = ($counts[$cmid] ?? 0) + 1;
            }
        }
        return $counts;
    }

    /**
     * Whether one module is available to at least one of the given users (DEC-26; the latest known availability of a material, T058).
     *
     * The same rule as count_available_to_users(), but it stops at the first user that has access: the cost of reading the course
     * for each user (the dominant cost, measured in T058) is paid only until one is found.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int[] $userids
     * @return bool false when there are no users
     */
    public function is_available_to_any_user(int $courseid, int $cmid, array $userids): bool {
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            if (in_array($cmid, $this->available_cmids($courseid, $userid), true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The modules of a course available to a user: the single place of the per-user rule (module visible to the user with the access
     * restrictions of Moodle met, DEC-26).
     *
     * The rule is the native `cm_info::uservisible` of `get_fast_modinfo($courseid, $userid)` (DEC-72; CL-48). The deadlines, grade items
     * and completion of the modules are not read, as get_modules() does: they are not part of the rule. There is no shortcut for hidden
     * modules and no cache of this plugin.
     *
     * @param int $courseid
     * @param int $userid
     * @return int[] cmids; [] when the course does not exist
     */
    private function available_cmids(int $courseid, int $userid): array {
        global $DB;
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return [];
        }
        $cmids = [];
        foreach (get_fast_modinfo($courseid, $userid)->get_cms() as $cm) {
            if (!$cm->deletioninprogress && $cm->uservisible) {
                $cmids[] = (int) $cm->id;
            }
        }
        return $cmids;
    }

    /**
     * The activities of a course that have a numeric or scale grade item, as `modname:instance` keys (DEC-39 D1, "avaliação numérica ou por escala").
     *
     * @param int $courseid
     * @return array<string, true>
     */
    private function gradable_instances(int $courseid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/grade/constants.php'); // GRADE_TYPE_* are not loaded in every context (the cron does not load the gradebook).
        $instances = [];
        $items = $DB->get_records_select(
            'grade_items',
            "courseid = :course AND itemtype = :type AND gradetype IN (:value, :scale)",
            ['course' => $courseid, 'type' => 'mod', 'value' => GRADE_TYPE_VALUE, 'scale' => GRADE_TYPE_SCALE],
            '',
            'id, itemmodule, iteminstance'
        );
        foreach ($items as $item) {
            $instances[$item->itemmodule . ':' . $item->iteminstance] = true;
        }
        return $instances;
    }

    /**
     * Whether a module is a resource (file, page, URL...) and not an activity (DEC-39 D1).
     *
     * @param string $modname
     * @return bool
     */
    public function is_resource(string $modname): bool {
        return plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER) == MOD_ARCHETYPE_RESOURCE;
    }

    /**
     * Due or closing date of a module instance.
     *
     * @param string $modname
     * @param int $instance
     * @return array{value: int|null, field: string|null} the value is null when the module has no date or it is unset (0)
     */
    private function due_date(string $modname, int $instance): array {
        global $DB;
        $field = self::DUE_DATE_FIELDS[$modname] ?? null;
        if ($field === null) {
            return ['value' => null, 'field' => null];
        }
        $value = $DB->get_field($modname, $field, ['id' => $instance], IGNORE_MISSING);
        $value = ($value === false || $value === null) ? 0 : (int) $value;
        return ['value' => $value > 0 ? $value : null, 'field' => $field];
    }
}
