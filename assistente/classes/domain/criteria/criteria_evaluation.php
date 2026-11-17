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

namespace local_assistente\domain\criteria;

use local_assistente\domain\criteria_status;

/**
 * Result of applying the criteria of a course to one student (T091; spec-015, DEC-40).
 *
 * Carries the three statuses, the values that were compared and the limits that were used, so that the context of the execution can
 * keep them (DEC-45 D2). A value is null when its dimension has no data.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criteria_evaluation {
    /**
     * Constructor.
     *
     * @param criteria_status $activity status of the activity criterion
     * @param criteria_status $forum status of the forum criterion
     * @param criteria_status $frequency status of the frequency criterion
     * @param float|null $activityvalue course total of the student, in percent, truncated; null without data
     * @param float|null $frequencyvalue global frequency, in percent, truncated; null without data
     * @param int[] $forumsdue course modules of the eligible forums whose reference date passed, in ascending order
     * @param int[] $forumsmissing those among them where the student did not post, in ascending order
     * @param array $limits the criteria that were used: activity_criterion, forum_criterion and frequency_criterion
     */
    public function __construct(
        /** @var criteria_status activity status */
        public readonly criteria_status $activity,
        /** @var criteria_status forum status */
        public readonly criteria_status $forum,
        /** @var criteria_status frequency status */
        public readonly criteria_status $frequency,
        /** @var float|null activity value compared */
        public readonly ?float $activityvalue,
        /** @var float|null frequency value compared */
        public readonly ?float $frequencyvalue,
        /** @var int[] forums considered */
        public readonly array $forumsdue,
        /** @var int[] forums considered where the student did not post */
        public readonly array $forumsmissing,
        /** @var array limits used */
        public readonly array $limits
    ) {
    }
}
