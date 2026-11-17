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

namespace local_assistente\application\sync;

use local_assistente\application\forum\forum_event_service;
use local_assistente\application\frequency\participation_event_service;
use local_assistente\application\grade\grade_event_service;
use local_assistente\application\material\material_event_service;
use local_assistente\application\quiz\quiz_publication_service;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\gradebook_reader;
use local_assistente\infrastructure\moodle\material_reader;
use local_assistente\infrastructure\moodle\participation_reader;
use local_assistente\infrastructure\moodle\student_reader;

/**
 * Initial synchronization of a course when the assistant is enabled, or enabled again (DEC-45 D3; plan 020; quickstart 020 Q9 to Q13).
 *
 * It queues the events for the CURRENT state of the course, through the same services the observers use, so identity, deduplication
 * and the "course not enabled" rule are the ones of the queue. It never simulates historical events and calls no LLM.
 * - materials: one `material_changed` per functional material (the handler decides by the content hash, so enabling again creates no
 *   redundant version);
 * - questionnaires: the publication event of every questionnaire visible to students, once per questionnaire in its whole existence
 *   (DEC-31), so enabling again never asks for a second initial analysis;
 * - grades: one `grade_changed` per student and main grade item with a final grade;
 * - attendance: one `participation_changed` per student and activity in which the student has a signal of participation.
 * - forums: one `forum_rebuild_requested` per forum of the course (CL-47; DEC-38 D2, DEC-45 D3). It is an internal event of the plugin, with
 *   no Moodle event, that coalesces while pending, so enabling again before the first request ran does not stack a second rebuild, and
 *   a request made after it ran creates a new one. The service only queues it: the rebuild reads the current state of the forum when the
 *   queue processes the event (forum_analysis_handler), so a real deletion afterwards still has its own event. Every forum module of the
 *   course is asked for, as the observers analyse any forum; no rule of eligibility of forums exists and none is created here.
 *
 * Volume (R-020-01): the number of events of the last run is kept in last_report(), by kind.
 *
 * Cost (A2): the grades and the signals of participation are the volume, one event per student and activity. They are handed to the queue
 * in batches (event_service::enqueue_many(), same identities and guarantees as one event at a time), and the grades read in the grades
 * step are reused by the participation step instead of read again (one read of the grades of each item for all students). The message
 * counts of each forum and the finished attempts of each quiz are read once for all students of the run
 * (participation_reader::with_run_memo()) and dropped when the participation step ends. What is queued is the same as before.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class initial_sync_service implements initial_sync {
    /** @var string name given to the Moodle event of a material queued by the synchronization */
    public const MATERIAL_MOODLE_EVENT = 'initial_sync';

    /** @var string[] module of each type of functional material */
    private const MODULE_OF_MATERIAL = ['pdf' => 'resource', 'text' => 'page', 'youtube' => 'url'];

    /** @var array<string, int> events handed to the queue by the last run, by kind */
    private array $report = [];

    /** @var \Closure|null clock used by tests */
    private ?\Closure $clock;

    /** @var array<int, array<int, \stdClass|null>>|null final grades read by the grades step: cmid => user id => grade; null before it ran */
    private ?array $grades = null;

    /**
     * Constructor.
     *
     * @param material_reader|null $materials
     * @param course_module_reader|null $modules
     * @param student_reader|null $students
     * @param gradebook_reader|null $gradebook
     * @param participation_reader|null $participation
     * @param material_event_service|null $materialevents
     * @param quiz_publication_service|null $quizevents
     * @param grade_event_service|null $gradeevents
     * @param participation_event_service|null $participationevents
     * @param \Closure|null $clock returns the current time; time() when null
     * @param forum_event_service|null $forumevents
     */
    public function __construct(
        private ?material_reader $materials = null,
        private ?course_module_reader $modules = null,
        private ?student_reader $students = null,
        private ?gradebook_reader $gradebook = null,
        private ?participation_reader $participation = null,
        private ?material_event_service $materialevents = null,
        private ?quiz_publication_service $quizevents = null,
        private ?grade_event_service $gradeevents = null,
        private ?participation_event_service $participationevents = null,
        ?\Closure $clock = null,
        private ?forum_event_service $forumevents = null
    ) {
        $this->materials ??= new material_reader();
        $this->modules ??= new course_module_reader();
        $this->students ??= new student_reader();
        $this->gradebook ??= new gradebook_reader();
        $this->participation ??= new participation_reader();
        $this->materialevents ??= new material_event_service();
        $this->quizevents ??= new quiz_publication_service();
        $this->gradeevents ??= new grade_event_service();
        $this->participationevents ??= new participation_event_service();
        $this->forumevents ??= new forum_event_service();
        $this->clock = $clock;
    }

    #[\Override]
    public function run(int $courseid): void {
        $now = $this->clock ? ($this->clock)() : time();
        $this->report = ['material' => 0, 'quiz' => 0, 'grade' => 0, 'participation' => 0, 'forum' => 0];
        $this->grades = null;

        $modules = $this->modules->get_modules($courseid);
        $this->sync_materials($courseid, $modules, $now);
        $this->sync_quizzes($courseid, $modules);
        $this->sync_forums($courseid, $modules);
        $studentids = $this->students->get_students($courseid);
        $this->sync_grades($courseid, $studentids, $now);
        $this->participation->with_run_memo(fn() => $this->sync_participation($courseid, $modules, $studentids, $now));
    }

    /**
     * Events handed to the queue by the last run, by kind (material, quiz, grade, participation, forum). Counts the calls that created or
     * reused an event; the queue reuses the same occurrence.
     *
     * @return array<string, int>
     */
    public function last_report(): array {
        return $this->report;
    }

    /**
     * Functional materials.
     *
     * @param int $courseid
     * @param \stdClass[] $modules
     * @param int $now
     */
    private function sync_materials(int $courseid, array $modules, int $now): void {
        foreach ($this->materials->get_materials($courseid) as $cmid => $material) {
            $modname = self::MODULE_OF_MATERIAL[$material->material_type] ?? ($modules[$cmid]->modname ?? '');
            if ($this->materialevents->handle_module_event($courseid, (int) $cmid, $modname, self::MATERIAL_MOODLE_EVENT, $now) !== null) {
                $this->report['material']++;
            }
        }
    }

    /**
     * Questionnaires visible to students.
     *
     * @param int $courseid
     * @param \stdClass[] $modules
     */
    private function sync_quizzes(int $courseid, array $modules): void {
        foreach ($modules as $module) {
            if ($module->modname === 'quiz' && $this->quizevents->handle_module_event($courseid, $module->cmid) !== null) {
                $this->report['quiz']++;
            }
        }
    }

    /**
     * Forums: one request of the complete rebuild for each forum of the course.
     *
     * The request carries only the forum; the queue rebuilds it from the state it finds when it processes the event.
     *
     * @param int $courseid
     * @param \stdClass[] $modules
     */
    private function sync_forums(int $courseid, array $modules): void {
        foreach ($modules as $module) {
            if ($module->modname !== 'forum') {
                continue;
            }
            $result = $this->forumevents->rebuild_requested($courseid, (int) $module->cmid);
            if ($result->has_event()) {
                $this->report['forum']++;
            }
        }
    }

    /**
     * Grades: one event per student and main grade item that has a final grade.
     *
     * The grades of each item are read for all the students together and kept for the participation step.
     *
     * @param int $courseid
     * @param int[] $studentids
     * @param int $now
     */
    private function sync_grades(int $courseid, array $studentids, int $now): void {
        $this->grades = [];
        $pairs = [];
        $items = array_filter($this->gradebook->get_items($courseid), static fn($item) => $item->item_number === 0);
        foreach ($items as $item) {
            $this->grades[$item->cmid] = $this->gradebook->get_final_grades($courseid, $item->cmid, $studentids, 0);
            foreach ($this->grades[$item->cmid] as $userid => $grade) {
                if ($grade !== null && $grade->final_grade !== null) {
                    $pairs[] = ['userid' => $userid, 'itemid' => $item->item_id];
                }
            }
        }
        $this->gradeevents->grade_changed_many($courseid, $pairs, $now);
        $this->report['grade'] += count($pairs);
    }

    /**
     * Attendance: one event per student and activity with a signal of participation, with the first source the queue knows.
     *
     * The final grade of the main grade item is the one the grades step read (none for an activity without that item), so it is not read
     * a second time; when the grades step did not run it is read here.
     *
     * @param int $courseid
     * @param \stdClass[] $modules
     * @param int[] $studentids
     * @param int $now
     */
    private function sync_participation(int $courseid, array $modules, array $studentids, int $now): void {
        $items = [];
        foreach ($modules as $module) {
            if ($module->is_resource || !$module->visible) {
                continue;
            }
            foreach ($studentids as $userid) {
                $known = $this->grades === null ? false : ($this->grades[$module->cmid][$userid] ?? null);
                $participation = $this->participation->get_participation($courseid, $userid, $module->cmid, $known);
                $source = $participation === null ? null : $this->first_source($participation->signals);
                if ($source !== null) {
                    $items[] = ['cmid' => (int) $module->cmid, 'userid' => $userid, 'source' => $source];
                }
            }
        }
        $this->participationevents->participation_changed_many($courseid, $items, $now);
        $this->report['participation'] += count($items);
    }
    /**
     * The first signal that the queue has a source for. A finished attempt alone has none (it comes with a grade).
     *
     * @param string[] $signals
     * @return string|null
     */
    private function first_source(array $signals): ?string {
        foreach (participation_event_service::SOURCES as $source) {
            if (in_array($source, $signals, true)) {
                return $source;
            }
        }
        return null;
    }
}
