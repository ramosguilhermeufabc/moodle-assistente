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

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests of the catalog of triggers (T131; plan 022 "Catálogo de gatilhos"; quickstart 022 Q1 to Q9; quickstart 006 Q4).
 *
 * The observers of each trigger have their own tests; this checks the catalog as a whole: what db/events.php maps, that the mapped
 * actions of the teachers and students reach the queue, and what must NOT reach it (disabled course, a repeated occurrence, enrolment
 * and role changes, the closing time of a questionnaire).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class catalog_test extends \advanced_testcase {
    /** @var string[] the Moodle events of the catalog: the only ones the plugin may observe */
    private const CATALOG = [
        '\core\event\course_module_created', '\core\event\course_module_updated',
        '\mod_quiz\event\attempt_submitted', '\mod_quiz\event\question_manually_graded', '\mod_quiz\event\attempt_regraded',
        '\core\event\user_graded', '\core\event\grade_deleted',
        '\mod_forum\event\discussion_created', '\mod_forum\event\post_created', '\mod_forum\event\post_updated',
        '\mod_forum\event\post_deleted', '\mod_forum\event\discussion_deleted',
        '\mod_assign\event\assessable_submitted', '\core\event\course_module_completion_updated',
        '\core\event\course_deleted',
    ];

    /**
     * The real queue takes locks and transactions, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The observers declared by the plugin.
     *
     * @return array[]
     */
    private static function observers(): array {
        global $CFG;
        $observers = [];
        require($CFG->dirroot . '/local/assistente/db/events.php');
        return $observers;
    }

    /**
     * Q9: no observer outside the catalog; and none for enrolment, role or the closing time of a questionnaire (Q6, Q7, Q8).
     */
    public function test_only_the_events_of_the_catalog_are_observed(): void {
        $names = array_map(static fn(array $observer): string => $observer['eventname'], self::observers());
        $this->assertSame([], array_values(array_diff($names, self::CATALOG)), 'Observer outside the catalog');
        $this->assertSame([], array_values(array_diff(self::CATALOG, $names)), 'Event of the catalog without observer');
        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression('/enrol|role_|group_member|timeclose/i', $name, 'DEC-47: no observer for enrolment or roles');
        }
    }

    /**
     * Q8: the closing time is detected by a scheduled task, not by an observer.
     */
    public function test_the_closing_time_is_detected_by_a_scheduled_task(): void {
        global $CFG;
        $tasks = [];
        require($CFG->dirroot . '/local/assistente/db/tasks.php');
        $classes = array_map(static fn(array $task): string => $task['classname'], $tasks);
        $this->assertContains('\local_assistente\task\detect_quiz_close', $classes);
        foreach (self::observers() as $observer) {
            $this->assertStringNotContainsString('close', $observer['callback']);
        }
    }

    /**
     * Q2 and Q3: every observer is a callable that only delegates (no LLM, no database access) and records the evidence it relies on.
     */
    public function test_observers_only_delegate_and_cite_their_evidence(): void {
        global $CFG;
        foreach (self::observers() as $observer) {
            $this->assertTrue(is_callable(ltrim($observer['callback'], '\\')), $observer['callback'] . ' is not callable');
            $this->assertFalse($observer['internal'], 'The observer must run after the commit (the queue takes locks)');
        }
        foreach (glob($CFG->dirroot . '/local/assistente/classes/observer/*.php') as $file) {
            $code = '';
            foreach (token_get_all(file_get_contents($file)) as $token) {
                if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    $code .= is_array($token) ? $token[1] : $token;
                }
            }
            $this->assertDoesNotMatchRegularExpression('/\$DB\b|llm_port|adapter_factory|fake_adapter|evaluate\s*\(/i', $code, basename($file));
            $this->assertMatchesRegularExpression('/\b(CL-\d+|T\d{3})\b/', file_get_contents($file), basename($file) . ' cites no validation');
        }
    }

    /**
     * Count the events of the queue in a course.
     *
     * @param int $courseid
     * @return string[] event types, sorted
     */
    private function types(int $courseid): array {
        global $DB;
        $types = $DB->get_fieldset_select('local_assistente_event', 'event_type', 'course_id = ?', [$courseid]);
        sort($types);
        return $types;
    }

    /**
     * The actions that the catalog maps, done in a course.
     *
     * @param \stdClass $course
     * @return string[] the types that the action must create
     */
    private function actions(\stdClass $course): array {
        $generator = $this->getDataGenerator();
        $student = $generator->create_and_enrol($course, 'student');
        $generator->create_module('quiz', ['course' => $course->id]);
        $generator->create_module('page', ['course' => $course->id, 'content' => '<p>x</p>']);
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $student->id,
        ]);
        // The forum API emits no creation event (T083, CL-35): post.php does. The event is emitted as post.php does (SYNTHETIC).
        \mod_forum\event\discussion_created::create([
            'context' => \context_module::instance($forum->cmid),
            'objectid' => $discussion->id,
            'other' => ['forumid' => $forum->id],
        ])->trigger();
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemmodule' => 'assign', 'iteminstance' => $assign->id, 'itemnumber' => 0]);
        $item->update_final_grade($student->id, 70, 'test');
        return ['forum_post_created', 'grade_changed', 'material_changed', 'participation_changed', 'quiz_published'];
    }

    /**
     * Q1: each mapped action of the course creates the matching event of the queue.
     */
    public function test_each_mapped_action_creates_its_event(): void {
        $course = $this->getDataGenerator()->create_course();
        (new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);

        $expected = $this->actions($course);

        $created = array_values(array_unique($this->types($course->id)));
        $this->assertSame($expected, $created);
    }

    /**
     * Q4: the same actions in a course without configuration, and in a disabled one, create nothing.
     */
    public function test_a_course_not_enabled_creates_no_event(): void {
        $none = $this->getDataGenerator()->create_course();
        $this->actions($none);
        $this->assertSame([], $this->types($none->id), 'No configuration');

        $disabled = $this->getDataGenerator()->create_course();
        (new \local_assistente\infrastructure\persistence\config_repository())->create($disabled->id, false);
        $this->actions($disabled);
        $this->assertSame([], $this->types($disabled->id), 'Disabled');
    }

    /**
     * Q5: the same occurrence received twice is one event.
     */
    public function test_the_same_occurrence_twice_is_one_event(): void {
        $course = $this->getDataGenerator()->create_course();
        (new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->assertSame(['quiz_published'], $this->types($course->id));

        $cm = get_coursemodule_from_id('quiz', $quiz->cmid, 0, false, MUST_EXIST);
        for ($i = 0; $i < 2; $i++) {
            \core\event\course_module_updated::create_from_cm($cm, \context_module::instance($quiz->cmid))->trigger();
        }
        $this->assertSame(['quiz_published'], $this->types($course->id), 'The questionnaire is published once in its existence');
    }

    /**
     * Q6 and Q7: enrolling, changing the role, suspending and removing a student create no event (DEC-47).
     */
    public function test_enrolment_and_role_changes_create_no_event(): void {
        $course = $this->getDataGenerator()->create_course();
        (new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
        $generator = $this->getDataGenerator();
        $student = $generator->create_user();

        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($student->id, $course->id, 'teacher');
        role_unassign(
            (int) $this->roleid('student'),
            $student->id,
            \context_course::instance($course->id)->id
        );
        $generator->enrol_user($student->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($instance, $student->id);

        $this->assertSame([], $this->types($course->id));
    }

    /**
     * Id of a role.
     *
     * @param string $shortname
     * @return int
     */
    private function roleid(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
