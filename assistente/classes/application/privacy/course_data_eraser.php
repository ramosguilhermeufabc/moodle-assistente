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

use local_assistente\infrastructure\persistence\course_erasure_repository;

/**
 * Removes the data of the plugin of a course that Moodle deleted (T103; spec-017, DEC-42 D2, quickstart 017 Q10).
 *
 * Every row of the plugin of that course goes: events, processings and their attempts, snapshots, analyses and all their results, the
 * syntheses, the materials and their versions, the reports and their versions, the criteria and the configuration (the tables and the keys
 * are in course_erasure_repository). Nothing is kept: the contract keeps no history of a deleted course. The rows of the other courses are not
 * touched.
 *
 * Idempotent: a course with no data, or erased twice, loses nothing and is not an error. All or nothing: a failure of the database leaves the
 * data of the course exactly as it was and reaches the caller. The call returns how many rows each table lost, so that the caller can log it.
 *
 * Not here: the student erasure and the redaction of names (T104, DEC-56).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_data_eraser {
    /** @var course_erasure_repository */
    private course_erasure_repository $repository;

    /**
     * Constructor.
     *
     * @param course_erasure_repository|null $repository
     */
    public function __construct(?course_erasure_repository $repository = null) {
        $this->repository = $repository ?? new course_erasure_repository();
    }

    /**
     * Erase the data of a course.
     *
     * @param int $courseid
     * @return array<string, int> rows deleted from each table
     * @throws \InvalidArgumentException when the course is not a positive identifier
     * @throws \dml_exception when the database fails; nothing is deleted then
     */
    public function erase(int $courseid): array {
        return $this->repository->delete_course_rows($courseid);
    }
}
