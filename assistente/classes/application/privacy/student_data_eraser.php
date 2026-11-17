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

use local_assistente\application\delivery\event_redelivery_scheduler;
use local_assistente\domain\privacy\name_redactor;
use local_assistente\infrastructure\persistence\history_redaction_writer;
use local_assistente\infrastructure\persistence\student_data_repository;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * The erasure of the data of a student that the Privacy API asks for (T104; spec-017, DEC-42 D1, DEC-56, DEC-57). The provider calls it.
 *
 * For the courses asked (or every course where the plugin holds something of the student), in ONE transaction and under the locks that
 * student_event_eraser takes before it (the lock of the student, the lock of each event that is not final and the lock of each section whose
 * synthesis will be redacted, in the global order of unit_of_work):
 * 1. the individual results of the student are deleted and the payload of every event of the student is neutralized (student_event_eraser);
 * 2. the entries of the student are taken out of the snapshots of context;
 * 3. the name of the student (full name, first name, last name) is redacted in the five preserved texts of each course (DEC-56);
 * 4. the identifier of the student is neutralized in the failure messages of events and attempts;
 * 5. the retry tasks of Moodle that hold a delivery of an event of the student (event_redelivery_scheduler) are removed, so that none of
 *    them creates, after the erasure, an event about what happened before it. A task that Moodle had already started is not stopped by
 *    this: the guard it runs inside event_service::enqueue(), under the lock of the student that this erasure holds, finds its record gone.
 * Ids, versions, statuses, dates, events, attempts and analyses stay (DEC-42 D3, DEC-57); the references to records of Moodle that the student
 * made (attempt, message, discussion) stay too. Any failure undoes the four steps. Repeating the call changes nothing: it finds nothing.
 *
 * The result is returned for the caller to record: counts only, never the student.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_data_eraser {
    /** @var student_data_repository */
    private student_data_repository $data;

    /** @var history_redaction_writer */
    private history_redaction_writer $writer;

    /** @var student_event_eraser */
    private student_event_eraser $events;

    /**
     * Constructor.
     *
     * @param student_data_repository|null $data
     * @param history_redaction_writer|null $writer
     * @param student_event_eraser|null $events
     */
    public function __construct(
        ?student_data_repository $data = null,
        ?history_redaction_writer $writer = null,
        ?student_event_eraser $events = null
    ) {
        $this->data = $data ?? new student_data_repository();
        $this->writer = $writer ?? new history_redaction_writer();
        $this->events = $events ?? new student_event_eraser($this->data, new unit_of_work());
    }

    /**
     * Erase the data of a student.
     *
     * @param int $userid
     * @param int[]|null $courseids the courses asked; null for every course where the plugin holds something of the student
     * @param \Closure|null $afterwards seam for tests: runs in the same transaction after the steps above
     * @return array{courses: int[], rounds: int, events: int, rows: array<string, int>, snapshots: int, messages: int, texts: array<string, array>, redaction_possible: bool} `redaction_possible` is false when the name is unavailable: the steps by identifier were done, but the preserved texts could not be redacted
     * @throws \InvalidArgumentException when the student is not a positive identifier
     * @throws \Throwable what the steps throw; nothing is changed then
     */
    public function erase(int $userid, ?array $courseids = null, ?\Closure $afterwards = null): array {
        if ($userid <= 0) {
            throw new \InvalidArgumentException('The student must be a positive identifier.');
        }
        $courses = $courseids === null ? $this->data->course_ids_with_data($userid) : array_values(array_unique(array_map('intval', $courseids)));
        $available = $this->data->names_available($userid);
        $names = $this->data->names($userid);
        $redactor = $available ? new name_redactor([$names[0]], [$names[1]]) : new name_redactor([], []);
        $sections = $this->data->synthesis_sections($courses);
        $locks = [];
        foreach ($sections as $courseid => $ids) {
            foreach ($ids as $sectionid) {
                $locks[] = unit_of_work::section_resource($courseid, $sectionid);
            }
        }

        $done = ['snapshots' => 0, 'messages' => 0, 'texts' => []];
        $within = function (int $userid) use ($courses, $courseids, $redactor, $afterwards, &$done): void {
            $done['snapshots'] = $this->writer->remove_snapshot_entries($userid, $courses, $this->data);
            foreach ($courses as $courseid) {
                foreach ($this->writer->redact_course_texts($courseid, $redactor) as $column => $counts) {
                    $done['texts'][$column]['texts'] = ($done['texts'][$column]['texts'] ?? 0) + $counts['texts'];
                    $done['texts'][$column]['occurrences'] = ($done['texts'][$column]['occurrences'] ?? 0) + $counts['occurrences'];
                }
            }
            $done['messages'] = $this->writer->neutralize_messages($userid, $courses);
            // Of the courses asked, or of every course: a student who only has a delivery waiting has no other data to name a course.
            (new event_redelivery_scheduler())->remove_tasks_of($userid, $courseids === null ? null : $courses);
            if ($afterwards !== null) {
                $afterwards($userid);
            }
        };
        $result = $this->events->erase($userid, $within, $courses, $locks);

        return ['courses' => $courses, 'rounds' => $result['rounds'], 'events' => $result['events'], 'rows' => $result['rows'], 'redaction_possible' => $available] + $done;
    }

    /**
     * The line of counts that is recorded for an erasure: courses and counts only, never a name, a text or an identifier of a student.
     *
     * @param array $result what erase() returned
     * @return string
     */
    public static function summary(array $result): string {
        $texts = 0;
        foreach ($result['texts'] as $counts) {
            $texts += $counts['texts'];
        }
        return sprintf(
            'local_assistente privacy: courses %s, events neutralized %d, individual rows %d, snapshots %d, texts redacted %d, messages %d%s',
            implode(',', $result['courses']),
            $result['events'],
            array_sum($result['rows']),
            $result['snapshots'],
            $texts,
            $result['messages'],
            $result['redaction_possible'] ? '' : ', names unavailable: texts not redacted'
        );
    }
}
