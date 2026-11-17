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

use local_assistente\infrastructure\persistence\student_data_repository;

/**
 * What the plugin holds of a student in some courses, for the export of the Privacy API (T104; DEC-42 D1, quickstart 017 Q7): the individual
 * results (performance, frequency with its activities, criteria) and the entries of the student in the snapshots. The texts that the plugin
 * preserves are not exported (DEC-56 only redacts them). Read only.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_data_exporter {
    /** @var student_data_repository */
    private student_data_repository $data;

    /**
     * Constructor.
     *
     * @param student_data_repository|null $data
     */
    public function __construct(?student_data_repository $data = null) {
        $this->data = $data ?? new student_data_repository();
    }

    /**
     * The data of the student by course; a course with nothing is not in the result.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return array<int, array{performance: array, frequency: array, criteria: array, snapshots: array}>
     */
    public function export(int $userid, array $courseids): array {
        $result = [];
        foreach (array_unique(array_map('intval', $courseids)) as $courseid) {
            $data = $this->data->export($userid, $courseid);
            if ($data['performance'] || $data['frequency'] || $data['criteria'] || $data['snapshots']) {
                $result[$courseid] = $data;
            }
        }
        ksort($result);
        return $result;
    }
}
