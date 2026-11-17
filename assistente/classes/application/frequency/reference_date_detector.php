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

namespace local_assistente\application\frequency;

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\persistence\config_repository;

/**
 * Detects the reference dates that have passed without any event of Moodle and queues the recalculation of the course (T088; spec-014,
 * DEC-39 D2).
 *
 * Nothing in Moodle announces that a due date or the end of a course arrived, so a scheduled task asks. For each course where the assistant is
 * enabled, the reference date of each visible activity module is its due or closing date, otherwise the end date of the course; when
 * that date has passed (the moment is strictly after it, DEC-54, as in frequency_calculator) the activity enters the denominator of the students who
 * did not take part, and the course is recalculated by the frequency handler (T087), which recalculates every student whatever the event.
 *
 * One `reference_date_reached` event is queued for each distinct reference date of a course, with the lowest `cmid` that has it, because
 * the handler recalculates the whole course for any of them: modules that share a date, in particular all those that fall back to the end
 * of the course, do not queue one recalculation each. The identity of the event is the module and the date, so the same date queues once
 * however many times the task runs, and a new date for the module is a new event. Resources, hidden modules and modules with no date at
 * all (no due date and no end of the course) queue nothing.
 *
 * It reads and queues; it calculates no frequency.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reference_date_detector {
    /** @var event_service */
    private event_service $events;

    /** @var course_module_reader */
    private course_module_reader $modules;

    /** @var course_section_reader */
    private course_section_reader $sections;

    /** @var config_repository */
    private config_repository $configs;

    /**
     * Constructor.
     *
     * @param event_service|null $events
     * @param course_module_reader|null $modules
     * @param course_section_reader|null $sections
     * @param config_repository|null $configs
     */
    public function __construct(
        ?event_service $events = null,
        ?course_module_reader $modules = null,
        ?course_section_reader $sections = null,
        ?config_repository $configs = null
    ) {
        $this->events = $events ?? new event_service();
        $this->modules = $modules ?? new course_module_reader();
        $this->sections = $sections ?? new course_section_reader();
        $this->configs = $configs ?? new config_repository();
    }

    /**
     * Queue the recalculation of every course that has a reference date passed.
     *
     * @param int|null $now unix time; the current time when null
     * @return enqueue_result[] one for each distinct date found
     */
    public function detect(?int $now = null): array {
        $now ??= time();
        $results = [];
        foreach ($this->configs->find_enabled_course_ids() as $courseid) {
            $courseend = $this->sections->course_end_date($courseid);
            $bydate = [];
            foreach ($this->modules->get_modules($courseid) as $module) {
                if ($module->is_resource || !$module->visible) {
                    continue;
                }
                $reference = ($module->due_date ?? 0) > 0 ? (int) $module->due_date : (($courseend ?? 0) > 0 ? (int) $courseend : null);
                if ($reference === null || $now <= $reference) {
                    continue;
                }
                $bydate[$reference] = isset($bydate[$reference]) ? min($bydate[$reference], $module->cmid) : $module->cmid;
            }
            ksort($bydate);
            foreach ($bydate as $reference => $cmid) {
                $results[] = $this->events->enqueue($courseid, event_type::REFERENCE_DATE_REACHED, [
                    'cmid' => $cmid, 'reference_date' => $reference,
                ]);
            }
        }
        return $results;
    }
}
