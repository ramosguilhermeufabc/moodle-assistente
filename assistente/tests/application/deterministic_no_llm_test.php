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


namespace local_assistente\application;

use local_assistente\application\forum\forum_analysis_handler;
use local_assistente\application\queue\handler_registry;
use local_assistente\application\queue\queue_processor;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * No LLM in the deterministic calculations (T094; quickstart 009 Q4, 011 Q4, 012 Q10, 015 Q3; spec-009 "Regras"): metrics, grades,
 * frequency and criteria never reach the LLM port.
 *
 * Three independent proofs, because the deterministic handlers receive no port to put a spy on:
 * 1. Source: the code that reaches the LLM layer (port, factory, context, use cases, results) is exactly a short list; the deterministic
 *    components are not on it, and the scan is shown to find a reference when there is one.
 * 2. Construction: the handlers of the registry are told apart by what their constructors accept; the three that take a port are the
 *    analyses that need the LLM, and the deterministic ones have no way to receive one.
 * 3. Run: the deterministic flow of a real course (grade, participation, criteria) runs through the real queue; nothing it writes carries
 *    the mark `[SIMULADO]` that the only adapter puts on every text it generates, while the forum analysis, run next as the control with a
 *    spy on the port, is called and does write it.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class deterministic_no_llm_test extends \advanced_testcase {
    /** @var string pattern of the names that belong to the LLM layer */
    private const LLM_NAMES = '/\b(llm_port|adapter_factory|guarded_llm_port|fake_adapter|llm_context|llm_use_case|structured_result|result_validator|llm_result_adapter|context_guard)\b|\\\\(infrastructure|application|domain)\\\\llm\\\\/';

    /** @var string[] the only classes outside the LLM layer that reach it: the analyses that need an LLM (spec-009) */
    private const LLM_USERS = [
        'classes/application/forum/forum_analysis_handler.php',
        'classes/application/material/material_service.php',
        'classes/application/quiz/initial_analysis_handler.php',
        'classes/application/synthesis/synthesis_handler.php',
    ];

    /**
     * Whether a source mentions the LLM layer in its code (not in comments).
     *
     * @param string $source php source
     * @return bool
     */
    private static function reaches_llm(string $source): bool {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (!in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    $code .= $token[1];
                }
            } else {
                $code .= $token;
            }
        }
        return preg_match(self::LLM_NAMES, $code) === 1;
    }

    /**
     * Source files of the plugin outside the LLM layer, relative to the plugin root.
     *
     * @return string[]
     */
    private static function files_outside_the_llm_layer(): array {
        global $CFG;
        $root = $CFG->dirroot . '/local/assistente';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/classes', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($file->getExtension() === 'php' && !preg_match('#^classes/(application|domain|infrastructure)/llm/#', $relative)) {
                $files[] = $relative;
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Proof 1: only the three analyses that need an LLM reach the LLM layer; metrics, grades, frequency and criteria do not.
     */
    public function test_only_the_llm_analyses_reach_the_llm_layer(): void {
        global $CFG;
        $users = [];
        foreach (self::files_outside_the_llm_layer() as $file) {
            if (self::reaches_llm(file_get_contents($CFG->dirroot . '/local/assistente/' . $file))) {
                $users[] = $file;
            }
        }

        $this->assertSame(self::LLM_USERS, $users, 'A new use of the LLM must be a decision, not an accident');
        foreach ($users as $file) {
            $this->assertDoesNotMatchRegularExpression('#^classes/(domain|infrastructure/(persistence|moodle))/|/(grade|frequency|criteria)/#', $file);
        }
    }

    /**
     * The scan can fail: it finds a reference in code, and ignores one in a comment or in the name of a column.
     */
    public function test_the_scan_finds_a_reference_when_there_is_one(): void {
        $this->assertTrue(self::reaches_llm('<?php use local_assistente\infrastructure\llm\llm_port; class A {}'));
        $this->assertTrue(self::reaches_llm('<?php $port = adapter_factory::create();'));
        $this->assertTrue(self::reaches_llm('<?php $x = new \local_assistente\application\llm\guarded_llm_port($a);'));
        $this->assertFalse(self::reaches_llm('<?php // the llm_port is not used here' . "\n" . '/** adapter_factory */ $x = 1;'));
        $this->assertFalse(self::reaches_llm('<?php $percentage = $a / $b * 100;'));
    }

    /**
     * Proof 2: by construction. The handlers that accept a port are exactly the LLM analyses; every deterministic handler (metrics, grades,
     * frequency, criteria) has no parameter through which a port could reach it.
     */
    public function test_deterministic_handlers_cannot_receive_a_port(): void {
        $withport = [];
        $without = [];
        foreach (handler_registry::DEFAULT_HANDLERS as $type => $class) {
            $accepts = false;
            foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
                $name = ltrim((string) $parameter->getType(), '?');
                $accepts = $accepts || is_a($name, \local_assistente\infrastructure\llm\llm_port::class, true);
            }
            $accepts ? $withport[] = $type : $without[] = $type;
        }

        $this->assertSame(
            ['quiz_published', 'forum_post_created', 'forum_post_updated', 'forum_post_deleted', 'forum_discussion_deleted', 'forum_rebuild_requested', 'synthesis_requested'],
            $withport
        );
        foreach (['grade_changed', 'quiz_attempt_submitted', 'quiz_attempt_manually_graded', 'quiz_attempt_regraded', 'quiz_closed',
                'participation_changed', 'reference_date_reached', 'criteria_check_requested', 'course_criteria_check_requested'] as $deterministic) {
            $this->assertContains($deterministic, $without);
        }
    }

    /**
     * Proof 3: the deterministic flow of a real course runs through the real queue and writes nothing generated by an LLM, while the
     * control (the forum analysis, with a spy on the port) is called and does leave the mark of the simulated adapter.
     */
    public function test_the_deterministic_flow_writes_nothing_generated_and_the_control_does(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1, 'startdate' => $now - 500 - DAYSECS, 'enddate' => $now - 500]);
        $configs = new config_repository();
        $config = $configs->create($course->id, true);
        $configs->insert_criteria((int) $config->id, 60, 1, 40);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'duedate' => $now - 1000, 'grade' => 100]);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $student->id,
        ]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, ['userid' => $student->id, 'rawgrade' => 80]);
        $spy = new fake_adapter();

        // The deterministic handlers alone: the registry has no LLM analysis, and the queue leaves the forum events pending.
        $deterministic = new handler_registry();
        foreach (handler_registry::DEFAULT_HANDLERS as $type => $class) {
            if (!in_array($type, ['quiz_published', 'forum_post_created', 'forum_post_updated', 'forum_post_deleted', 'forum_discussion_deleted', 'forum_rebuild_requested', 'material_changed', 'synthesis_requested'], true)) {
                $deterministic->register(event_type::from($type), new $class());
            }
        }
        (new queue_processor(null, $deterministic))->run_cycle();

        $types = $DB->get_fieldset_select('local_assistente_analysis', 'DISTINCT analysis_type', '1 = 1');
        sort($types);
        $this->assertSame(['criteria_check', 'frequency', 'grade_performance'], $types, 'The three deterministic analyses were made');
        $this->assertSame(0, $spy->calls());
        $this->assertSame(0, $DB->count_records_select('local_assistente_analysis', "summary LIKE '%SIMULADO%'"));
        $this->assertSame(0, $DB->count_records_select('local_assistente_processing_context', "context_json LIKE '%SIMULADO%'"));
        $this->assertSame(0, $DB->count_records('local_assistente_forum_difficulty'));

        // The control: the forum analysis with the spy reaches the port and writes the mark. The generator does not emit the Moodle event of
        // the message (T083), so the event of the plugin is queued as the observer would.
        $discussion = $DB->get_record('forum_discussions', ['forum' => $forum->id], '*', MUST_EXIST);
        (new event_service())->enqueue((int) $course->id, event_type::FORUM_POST_CREATED, [
            'cmid' => (int) $forum->cmid, 'discussionid' => (int) $discussion->id, 'postid' => (int) $discussion->firstpost,
        ]);
        $control = new handler_registry();
        foreach (['forum_post_created', 'forum_post_updated', 'forum_post_deleted', 'forum_discussion_deleted', 'forum_rebuild_requested'] as $type) {
            $control->register(event_type::from($type), new forum_analysis_handler($spy));
        }
        (new queue_processor(null, $control))->run_cycle();

        $this->assertGreaterThanOrEqual(1, $spy->calls(), 'The spy sees the call of the analysis that needs the LLM');
        $this->assertGreaterThanOrEqual(1, $DB->count_records_select('local_assistente_analysis', "analysis_type = 'forum_analysis' AND summary LIKE '%SIMULADO%'"));
    }
}
