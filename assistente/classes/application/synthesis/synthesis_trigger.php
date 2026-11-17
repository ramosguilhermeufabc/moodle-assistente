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


namespace local_assistente\application\synthesis;

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\course_section_reader;
use local_assistente\infrastructure\moodle\gradebook_reader;

/**
 * Queues the synthesis of the course sections that an analysis affected (T098; spec-016, DEC-41 D3, DEC-21, plan 022 "encadeamentos
 * internos").
 *
 * One `synthesis_requested` event for each affected (course, section): the event service reuses the one that is still pending, so a burst
 * of analyses of a section is one synthesis, and does nothing for a course without the assistant. Nothing waits for a result that is
 * missing: the synthesis handler (T097) uses the analyses that are current and marks the others as no data. The trigger calculates
 * nothing and calls no LLM.
 *
 * The section is always read from the real relation of the module in the course, never assumed:
 * - a module (questionnaire, forum) belongs to the section of `course_modules.section`, resolved by course_section_reader;
 * - a grade item belongs to the section of the module it grades; an item that is not of a module (the total of the course, a category, a
 *   manual item) belongs to no section and asks for nothing, as does an item of a module that no longer exists;
 * - a section id that already comes from an analysis (the frequency has one row for each section) is used as it is.
 * A module whose section cannot be resolved throws a RuntimeException that names the reason, so that the handler fails the attempt and the
 * request is made again by the retry (the analysis is already saved); the request is never dropped without a trace.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class synthesis_trigger {
    /** @var event_service */
    private event_service $events;

    /** @var course_section_reader */
    private course_section_reader $sections;

    /** @var gradebook_reader */
    private gradebook_reader $gradebook;

    /**
     * Constructor.
     *
     * @param event_service|null $events
     * @param course_section_reader|null $sections
     * @param gradebook_reader|null $gradebook
     */
    public function __construct(
        ?event_service $events = null,
        ?course_section_reader $sections = null,
        ?gradebook_reader $gradebook = null
    ) {
        $this->events = $events ?? new event_service();
        $this->sections = $sections ?? new course_section_reader();
        $this->gradebook = $gradebook ?? new gradebook_reader();
    }

    /**
     * Ask for the synthesis of a section.
     *
     * @param int $courseid
     * @param int $sectionid id of the course section
     * @return enqueue_result
     * @throws \InvalidArgumentException when the course or the section is missing
     */
    public function request_section(int $courseid, int $sectionid): enqueue_result {
        if ($courseid <= 0 || $sectionid <= 0) {
            throw new \InvalidArgumentException('The course and the section of the synthesis request are required.');
        }
        return $this->events->enqueue($courseid, event_type::SYNTHESIS_REQUESTED, ['section_id' => $sectionid]);
    }

    /**
     * Ask for the synthesis of several sections, once for each.
     *
     * @param int $courseid
     * @param int[] $sectionids ids of the course sections; repeated ones count once
     */
    public function request_sections(int $courseid, array $sectionids): void {
        $unique = array_unique(array_map('intval', $sectionids));
        sort($unique);
        foreach ($unique as $sectionid) {
            $this->request_section($courseid, $sectionid);
        }
    }

    /**
     * Ask for the synthesis of the section of a module.
     *
     * @param int $courseid
     * @param int $cmid
     * @return enqueue_result
     * @throws \RuntimeException when the section of the module cannot be resolved
     */
    public function request_for_module(int $courseid, int $cmid): enqueue_result {
        $section = $this->sections->resolve_module_section($courseid, $cmid);
        if (!$section->is_resolved()) {
            throw new \RuntimeException("The section of the module {$cmid} is not resolved ({$section->status->value}): the synthesis was not requested.");
        }
        return $this->request_section($courseid, (int) $section->sectionid);
    }

    /**
     * Ask for the synthesis of the section of the module that a grade item grades.
     *
     * @param int $courseid
     * @param int $itemid
     * @return enqueue_result|null null when the item is not an item of a module, so it belongs to no section
     * @throws \RuntimeException when the section of its module cannot be resolved
     */
    public function request_for_grade_item(int $courseid, int $itemid): ?enqueue_result {
        foreach ($this->gradebook->get_items($courseid) as $item) {
            if ($item->item_id === $itemid) {
                return $this->request_for_module($courseid, $item->cmid);
            }
        }
        return null;
    }
}
