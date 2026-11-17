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
 * The key of a case of erasure (T142; DEC-58): one student and the set of courses where the erasure was asked.
 *
 * The courses are positive integers, without repetition and in ascending order, so that the same case always has the same key, the same
 * custom data of the retry task (which is what Moodle compares to avoid a duplicate task) and the same correlation of the audit events.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_key {
    /** @var int */
    private int $userid;

    /** @var int[] */
    private array $courses;

    /**
     * Constructor.
     *
     * @param int $userid
     * @param int[] $courseids in any order, with or without repetition
     * @throws \InvalidArgumentException for a student or a course that is not positive, or for an empty set of courses
     */
    public function __construct(int $userid, array $courseids) {
        $courses = array_values(array_unique(array_map('intval', $courseids)));
        sort($courses);
        if ($userid <= 0 || $courses === [] || $courses[0] <= 0) {
            throw new \InvalidArgumentException('A case of erasure needs a positive student and positive courses.');
        }
        $this->userid = $userid;
        $this->courses = $courses;
    }

    /**
     * Rebuild a key from the decoded custom data of a task.
     *
     * @param mixed $data the decoded custom data
     * @return self
     * @throws \InvalidArgumentException when the data is not a key
     */
    public static function from_custom_data(mixed $data): self {
        $data = (array) $data;
        if (!isset($data['userid'], $data['courseids']) || !is_array($data['courseids'])) {
            throw new \InvalidArgumentException('The custom data of the task is not a case of erasure.');
        }
        return new self((int) $data['userid'], $data['courseids']);
    }

    /**
     * The student.
     *
     * @return int
     */
    public function userid(): int {
        return $this->userid;
    }

    /**
     * The courses, ascending.
     *
     * @return int[]
     */
    public function courses(): array {
        return $this->courses;
    }

    /**
     * The custom data of the task, always the same text for the same key.
     *
     * @return array{userid: int, courseids: int[]}
     */
    public function to_custom_data(): array {
        return ['userid' => $this->userid, 'courseids' => $this->courses];
    }

    /**
     * Whether another set of courses is exactly the one of this key.
     *
     * @param int[] $courses
     * @return bool
     */
    public function has_courses(array $courses): bool {
        $other = array_values(array_unique(array_map('intval', $courses)));
        sort($other);
        return $other === $this->courses;
    }

    /**
     * Whether a set of courses shares a course with this key.
     *
     * @param int[] $courses
     * @return bool
     */
    public function intersects(array $courses): bool {
        return array_intersect($this->courses, array_map('intval', $courses)) !== [];
    }
}
