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
use local_assistente\domain\truncated_percentage;

/**
 * Applies the criteria of a course to the results of one student (T091; spec-015, DEC-11, DEC-40, DEC-39, DEC-24, DEC-54).
 *
 * Pure: no Moodle, database, clock or LLM. The moment is an argument, and every limit comes from the criteria it receives; there is no
 * threshold of its own. The three criteria are of minimum (DEC-11, DEC-40): the student meets a criterion with value >= limit and is
 * below it with value < limit. Values and limits are compared truncated to three decimals (DEC-36 D2). A dimension without data is
 * NO_DATA and never becomes zero (DEC-24).
 *
 * - Activity (D2): the final course total of the student, in percent, against `activity_criterion`; no total, NO_DATA.
 * - Forum (D3): `forum_criterion = 0` is disabled and always MEETS. With 1, the student meets it if posted at least once in each eligible
 *   forum, available to the student, whose reference date has passed (strictly after it, DEC-54); one forum without a post is BELOW;
 *   no such forum, NO_DATA.
 * - Frequency (D4): the sum of participating activities over the sum of activities of all the sections of the student, in percent (not
 *   the mean of the sections); a sum of zero activities, NO_DATA.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criteria_evaluator {
    /**
     * Evaluate a student.
     *
     * @param array $criteria activity_criterion and frequency_criterion (percent from 0 to 100, at most three decimals) and
     *        forum_criterion (0 or 1)
     * @param float|null $coursetotal final course total of the student in percent from 0 to 100; null when there is none
     * @param array $forums one fact for each forum module of the course: cmid (int > 0), eligible (bool: an activity of the forum
     *        category for the student), available (bool: available to the student), reference_date (int|null: delivery or closing
     *        time or course end, null or 0 when none) and posted (bool: the student posted at least once)
     * @param array $frequency one row for each section of the student: total_activities and participating_activities (int >= 0, the
     *        latter not above the former)
     * @param int $now moment of the evaluation, unix time
     * @return criteria_evaluation
     * @throws \InvalidArgumentException for an invalid criterion or fact
     */
    public function evaluate(array $criteria, ?float $coursetotal, array $forums, array $frequency, int $now): criteria_evaluation {
        $limits = $this->limits($criteria);
        $activityvalue = $this->percent($coursetotal, 'course total');
        [$forumstatus, $due, $missing] = $this->forum($limits['forum_criterion'], $forums, $now);
        [$frequencyvalue, $total] = $this->global_frequency($frequency);

        return new criteria_evaluation(
            $activityvalue === null
                ? criteria_status::NO_DATA
                : $this->minimum($activityvalue, $limits['activity_criterion']),
            $forumstatus,
            $total === 0 ? criteria_status::NO_DATA : $this->minimum($frequencyvalue, $limits['frequency_criterion']),
            $activityvalue,
            $total === 0 ? null : $frequencyvalue,
            $due,
            $missing,
            $limits
        );
    }

    /**
     * Criterion of minimum: meets with value >= limit, below with value < limit.
     *
     * @param float $value
     * @param float $limit
     * @return criteria_status
     */
    private function minimum(float $value, float $limit): criteria_status {
        return truncated_percentage::of($value) >= truncated_percentage::of($limit) ? criteria_status::MEETS : criteria_status::BELOW;
    }

    /**
     * Validate the criteria.
     *
     * @param array $criteria
     * @return array the limits, with the percentages as floats and the forum criterion as int
     */
    private function limits(array $criteria): array {
        foreach (['activity_criterion', 'frequency_criterion'] as $name) {
            if (!array_key_exists($name, $criteria) || !criterion_percentage::is_valid($criteria[$name])) {
                throw new \InvalidArgumentException("The criterion {$name} is missing or is not a percentage from 0 to 100.");
            }
        }
        $forum = $criteria['forum_criterion'] ?? null;
        if (!in_array($forum, [0, 1, '0', '1'], true)) {
            throw new \InvalidArgumentException('The criterion forum_criterion must be 0 or 1.');
        }
        return [
            'activity_criterion' => (float) $criteria['activity_criterion'],
            'forum_criterion' => (int) $forum,
            'frequency_criterion' => (float) $criteria['frequency_criterion'],
        ];
    }

    /**
     * Validate and truncate a percentage.
     *
     * @param float|null $value
     * @param string $what
     * @return float|null
     */
    private function percent(?float $value, string $what): ?float {
        if ($value === null) {
            return null;
        }
        if (is_nan($value) || $value < 0.0 || $value > 100.0) {
            throw new \InvalidArgumentException("The {$what} must be a percentage from 0 to 100.");
        }
        return truncated_percentage::of($value);
    }

    /**
     * The forum status and the forums that were considered.
     *
     * @param int $criterion forum_criterion
     * @param array $forums
     * @param int $now
     * @return array [status, cmids of the forums due, cmids of those without a post]
     */
    private function forum(int $criterion, array $forums, int $now): array {
        $merged = [];
        foreach ($forums as $fact) {
            $cmid = $fact['cmid'] ?? null;
            if (!is_int($cmid) || $cmid <= 0) {
                throw new \InvalidArgumentException('The forum fact is missing its module.');
            }
            $merged[$cmid]['posted'] = ($merged[$cmid]['posted'] ?? false) || (bool) ($fact['posted'] ?? false);
            $merged[$cmid]['fact'] ??= $fact;
        }
        $due = [];
        $missing = [];
        foreach ($merged as $cmid => $entry) {
            $fact = $entry['fact'];
            $reference = (int) ($fact['reference_date'] ?? 0);
            if (!($fact['eligible'] ?? false) || !($fact['available'] ?? false) || $reference <= 0 || $now <= $reference) {
                continue;
            }
            $due[] = $cmid;
            if (!$entry['posted']) {
                $missing[] = $cmid;
            }
        }
        sort($due);
        sort($missing);
        if ($criterion === 0) {
            return [criteria_status::MEETS, $due, $missing];
        }
        if ($due === []) {
            return [criteria_status::NO_DATA, $due, $missing];
        }
        return [$missing === [] ? criteria_status::MEETS : criteria_status::BELOW, $due, $missing];
    }

    /**
     * The global frequency: sum of participating over sum of total, not the mean of the sections.
     *
     * @param array $rows
     * @return array [percentage truncated, sum of the activities]
     */
    private function global_frequency(array $rows): array {
        $total = 0;
        $participating = 0;
        foreach ($rows as $row) {
            $t = $row['total_activities'] ?? null;
            $p = $row['participating_activities'] ?? null;
            if (!is_int($t) || !is_int($p) || $t < 0 || $p < 0 || $p > $t) {
                throw new \InvalidArgumentException('A frequency row needs total_activities and participating_activities, integers with 0 <= participating <= total.');
            }
            $total += $t;
            $participating += $p;
        }
        return [$total === 0 ? 0.0 : truncated_percentage::of($participating / $total * 100), $total];
    }
}
