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
 * Reads final grades from the Moodle gradebook, as configured in the course (spec-004; DEC-37).
 *
 * The reader returns what the gradebook holds: the final grade, the minimum and maximum of the item, the
 * type (value, scale, text or none) and the overridden and excluded flags. It applies no weight and no
 * conversion; converting to a percentage of the item range is a rule of the performance calculation (spec-012).
 * A student without a grade has final_grade null (no data), never zero.
 *
 * Moodle API: grade_item::fetch_all(), grade_item::fetch(), grade_item::fetch_course_item(),
 * grade_item::get_grade() (returns a grade_grade whose id is empty when the user has no grade);
 * grade_needs_regrade_final_grades() and grade_regrade_final_grades(); get_fast_modinfo()->get_instance_of().
 * Verified by reading the MOODLE_502_STABLE sources; behaviour in a running Moodle 5.2 is validated in CL-34 (T034).
 *
 * Risk R-012-01, validated in CL-34 (closed on 03/11/2026; the lazy path with `needsupdate` was covered only by the PHPUnit tests, an
 * accepted limitation): when the course is flagged as needing a regrade, the reader runs
 * grade_regrade_final_grades() (Moodle's own recalculation, which writes the gradebook tables, not the plugin's)
 * before reading, so that a total is not read stale; if the recalculation reports errors the read fails with an
 * exception, so that a technical failure is never turned into a value.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradebook_reader {
    /** @var string[] gradebook type code => name */
    private const GRADE_TYPES = [0 => 'none', 1 => 'value', 2 => 'scale', 3 => 'text'];

    /**
     * Load the gradebook libraries.
     */
    private function require_grade_libraries(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Make sure the final grades of the course are up to date (R-012-01).
     *
     * @param int $courseid
     * @throws \RuntimeException when the recalculation reports errors
     */
    public function ensure_up_to_date(int $courseid): void {
        $this->require_grade_libraries();
        if (!grade_needs_regrade_final_grades($courseid)) {
            return;
        }
        $result = grade_regrade_final_grades($courseid);
        if ($result !== true) {
            throw new \RuntimeException('The gradebook recalculation of course ' . $courseid . ' reported errors: ' .
                implode('; ', array_map('strval', (array) $result)));
        }
    }

    /**
     * Grade items of the activities of a course.
     *
     * Each item has: item_id, cmid, modname, item_number, name, grade_type (none, value, scale or text),
     * grade_min, grade_max. Category, course and manual items are not returned; items of removed modules are skipped.
     *
     * @param int $courseid
     * @return \stdClass[] ordered by cmid and item number
     */
    public function get_items(int $courseid): array {
        $this->require_grade_libraries();
        $fetched = \grade_item::fetch_all(['courseid' => $courseid, 'itemtype' => 'mod']);
        if (!$fetched) {
            return [];
        }

        $modinfo = get_fast_modinfo($courseid);
        $items = [];
        foreach ($fetched as $item) {
            $cm = $modinfo->get_instance_of((string) $item->itemmodule, (int) $item->iteminstance);
            if ($cm === null) {
                continue;
            }
            $items[] = (object) [
                'item_id' => (int) $item->id,
                'cmid' => (int) $cm->id,
                'modname' => (string) $item->itemmodule,
                'item_number' => (int) $item->itemnumber,
                'name' => (string) $item->get_name(),
                'grade_type' => self::GRADE_TYPES[(int) $item->gradetype] ?? 'none',
                'grade_min' => (float) $item->grademin,
                'grade_max' => (float) $item->grademax,
            ];
        }
        usort($items, static fn($a, $b) => [$a->cmid, $a->item_number] <=> [$b->cmid, $b->item_number]);
        return $items;
    }

    /**
     * Final grade of a student in one activity.
     *
     * Result fields: cmid, item_number, grade_type, grade_min, grade_max, final_grade (float|null: null when the
     * student has no grade), overridden (bool), excluded (bool).
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $userid
     * @param int $itemnumber item number of the module (0 for the main grade)
     * @return \stdClass|null null when the module or its grade item does not exist
     */
    public function get_final_grade(int $courseid, int $cmid, int $userid, int $itemnumber = 0): ?\stdClass {
        $this->require_grade_libraries();
        $cms = get_fast_modinfo($courseid)->get_cms();
        if (!isset($cms[$cmid])) {
            return null;
        }
        $this->ensure_up_to_date($courseid);

        $item = \grade_item::fetch([
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'itemmodule' => $cms[$cmid]->modname,
            'iteminstance' => $cms[$cmid]->instance,
            'itemnumber' => $itemnumber,
        ]);
        if (!$item) {
            return null;
        }
        return $this->describe($item, $userid, $cmid, $itemnumber);
    }

    /**
     * Final grades of many students in one activity, read together: the same result as get_final_grade() for each student, with one
     * recalculation check, one grade item and one read of the grades of that item for all of them, instead of three reads for each.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int[] $userids
     * @param int $itemnumber item number of the module (0 for the main grade)
     * @return array<int, \stdClass|null> user id => what get_final_grade() returns; null for all of them when the module or its grade item
     *         does not exist
     */
    public function get_final_grades(int $courseid, int $cmid, array $userids, int $itemnumber = 0): array {
        $this->require_grade_libraries();
        $none = array_fill_keys($userids, null);
        $cms = get_fast_modinfo($courseid)->get_cms();
        if (!isset($cms[$cmid])) {
            return $none;
        }
        $this->ensure_up_to_date($courseid);

        $item = \grade_item::fetch([
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'itemmodule' => $cms[$cmid]->modname,
            'iteminstance' => $cms[$cmid]->instance,
            'itemnumber' => $itemnumber,
        ]);
        if (!$item) {
            return $none;
        }
        $grades = [];
        foreach (\grade_grade::fetch_all(['itemid' => $item->id]) ?: [] as $grade) {
            $grades[(int) $grade->userid] = $grade;
        }
        $described = [];
        foreach ($userids as $userid) {
            $described[$userid] = $this->describe_grade($item, $grades[$userid] ?? false, $cmid, $itemnumber);
        }
        return $described;
    }

    /**
     * Final total of the course for a student, as the gradebook computes it (DEC-37 D3).
     *
     * Result fields: grade_type, grade_min, grade_max, final_grade (float|null), overridden, excluded.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass
     */
    public function get_course_total(int $courseid, int $userid): \stdClass {
        $this->require_grade_libraries();
        $this->ensure_up_to_date($courseid);
        return $this->describe(\grade_item::fetch_course_item($courseid), $userid, null, null);
    }

    /**
     * Describe the grade of a user in an item.
     *
     * @param \grade_item $item
     * @param int $userid
     * @param int|null $cmid
     * @param int|null $itemnumber
     * @return \stdClass
     */
    private function describe(\grade_item $item, int $userid, ?int $cmid, ?int $itemnumber): \stdClass {
        return $this->describe_grade($item, $item->get_grade($userid, false), $cmid, $itemnumber);
    }

    /**
     * Describe a grade that was already read.
     *
     * @param \grade_item $item
     * @param \grade_grade|false $grade the grade of the user in the item; false or without id when there is none
     * @param int|null $cmid
     * @param int|null $itemnumber
     * @return \stdClass
     */
    private function describe_grade(\grade_item $item, $grade, ?int $cmid, ?int $itemnumber): \stdClass {
        $hasgrade = $grade && !empty($grade->id) && $grade->finalgrade !== null;

        $description = [
            'grade_type' => self::GRADE_TYPES[(int) $item->gradetype] ?? 'none',
            'grade_min' => (float) $item->grademin,
            'grade_max' => (float) $item->grademax,
            'final_grade' => $hasgrade ? (float) $grade->finalgrade : null,
            'overridden' => $grade && !empty($grade->id) && (int) $grade->overridden > 0,
            'excluded' => $grade && !empty($grade->id) && (int) $grade->excluded > 0,
        ];
        if ($cmid !== null) {
            $description = ['cmid' => $cmid, 'item_number' => $itemnumber] + $description;
        }
        return (object) $description;
    }
}
