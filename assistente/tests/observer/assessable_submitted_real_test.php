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

namespace local_assistente\observer;

use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The event `\mod_assign\event\assessable_submitted` emitted by the real functions of mod_assign (T132; quickstart 022 Q10).
 *
 * The student sends the assignment through assign::save_submission() (assignment without drafts: the submission is final) and through
 * assign::submit_for_grading() (assignment with drafts: the student confirms). Both are the functions that the pages of the assignment
 * call. The queue event is `participation_changed` with the source `submission`, identified by module, student, source and moment.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(participation_observer::class)]
final class assessable_submitted_real_test extends \advanced_testcase {
    /**
     * Real transactions and locks are used by the queue; the observers of the plugin, as in db/events.php.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $observers = [];
        include($CFG->dirroot . '/local/assistente/db/events.php');
        foreach ($observers as $i => $observer) {
            $observers[$i] += ['includefile' => null, 'priority' => 0];
        }
        \core\event\manager::phpunit_replace_observers($observers);
    }

    /**
     * Course with the assistant, a student and an assignment.
     *
     * @param int $drafts 1 when the student must confirm the submission
     * @return array [assign, student, cmid]
     */
    private function assignment(int $drafts): array {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        (new config_repository())->create($course->id, true);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'submissiondrafts' => $drafts,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $cm = get_coursemodule_from_id('assign', $module->cmid, $course->id, false, MUST_EXIST);
        $assign = new \assign(\context_module::instance($module->cmid), $cm, $course);
        return [$assign, $student, (int) $module->cmid];
    }

    /**
     * Queued `participation_changed` events, as payloads.
     *
     * @return array[]
     */
    private function queued(): array {
        global $DB;
        $rows = $DB->get_records('local_assistente_event', ['event_type' => event_type::PARTICIPATION_CHANGED->value], 'id ASC');
        return array_values(array_map(static fn($r) => json_decode($r->payload, true), $rows));
    }

    /**
     * Save the online text of the student.
     *
     * @param \assign $assign
     * @param \stdClass $student
     * @return array notices of the function
     */
    private function save_text(\assign $assign, \stdClass $student): array {
        $this->setUser($student);
        $data = (object) ['userid' => $student->id, 'onlinetext_editor' => [
            'text' => '<p>Resposta</p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid(),
        ]];
        $notices = [];
        $this->assertTrue($assign->save_submission($data, $notices));
        return $notices;
    }

    /**
     * Without drafts, assign::save_submission() makes the submission final and emits assessable_submitted.
     */
    public function test_save_submission_without_drafts_queues_the_participation(): void {
        [$assign, $student, $cmid] = $this->assignment(0);
        $this->assertSame([], $this->queued());

        $this->save_text($assign, $student);

        $queued = $this->queued();
        $this->assertCount(1, $queued);
        $this->assertSame($cmid, $queued[0]['cmid']);
        $this->assertSame((int) $student->id, $queued[0]['userid']);
        $this->assertSame('submission', $queued[0]['source']);
    }

    /**
     * With drafts, saving emits nothing; assign::submit_for_grading() emits assessable_submitted.
     */
    public function test_submit_for_grading_with_drafts_queues_the_participation(): void {
        [$assign, $student, $cmid] = $this->assignment(1);
        $this->save_text($assign, $student);
        $this->assertSame([], $this->queued(), 'A draft is not a submission');

        $notices = [];
        $this->assertTrue($assign->submit_for_grading((object) ['userid' => $student->id], $notices));

        $queued = $this->queued();
        $this->assertCount(1, $queued);
        $this->assertSame($cmid, $queued[0]['cmid']);
        $this->assertSame((int) $student->id, $queued[0]['userid']);
        $this->assertSame('submission', $queued[0]['source']);
    }
}
