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


namespace local_assistente\application\history;

use local_assistente\application\criteria\criteria_handler;
use local_assistente\application\forum\forum_analysis_handler;
use local_assistente\application\frequency\frequency_handler;
use local_assistente\application\grade\performance_handler;
use local_assistente\application\quiz\initial_analysis_handler;
use local_assistente\application\quiz\results_handler;
use local_assistente\application\synthesis\synthesis_handler;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\event_repository;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The history only grows, and growing does not break reading it (T105; quickstart 017 Q11; DEC-42 D4; risks R-011-01, R-016-01, R-019-01).
 *
 * Repeated executions of every kind of analysis that exists (performance, frequency, criteria, forum, questionnaire initial and results,
 * synthesis) are made by the real handlers over several rounds, as months of use would. The test shows that: nothing is purged, by the
 * handlers, by the scheduled tasks of the plugin or by any code that deletes (only the erasure of a deleted course does); every earlier
 * analysis is still there, byte for byte; every one can still be traced and its history read (lineage_reader, T101); and how much each
 * type of analysis grew is measured and printed. R-019-01 (reports) cannot be measured: no code writes a report yet, so the table has no row.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class history_growth_test extends \advanced_testcase {
    /** @var int rounds of repeated executions; each one stands for a period of use */
    private const ROUNDS = 5;

    /** @var string[] kinds of analysis that the plugin writes today */
    private const TYPES = ['grade_performance', 'frequency', 'criteria_check', 'forum_analysis', 'quiz_initial', 'quiz_results', 'section_synthesis'];

    /** @var string[] tables with results that belong to an analysis by `analysis_id` */
    private const RESULT_TABLES = [
        'local_assistente_questionnaire_analysis', 'local_assistente_questionnaire_metric', 'local_assistente_question_result',
        'local_assistente_forum_difficulty', 'local_assistente_performance', 'local_assistente_frequency',
        'local_assistente_criteria_result', 'local_assistente_pedagogical_synthesis',
    ];

    /** @var int counter that keeps the occurrences distinct */
    private static int $occurred = 15000;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \stdClass */
    private \stdClass $assign;

    /** @var \stdClass */
    private \stdClass $forum;

    /** @var \stdClass */
    private \stdClass $quiz;

    /** @var \stdClass */
    private \stdClass $discussion;

    /** @var int[] ids of the first section */
    private int $section;

    /** @var event_repository */
    private event_repository $events;

    /**
     * A course with the assistant and its criteria, a student, an assignment with a grade, a forum with a discussion and a quiz.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 1, 'startdate' => $now - 500 - DAYSECS, 'enddate' => $now - 500]);
        $configs = new config_repository();
        $config = $configs->create($this->course->id, true);
        $configs->insert_criteria((int) $config->id, 60, 1, 40);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id, 'duedate' => $now - 1000, 'grade' => 100,
        ], ['section' => 1]);
        $this->forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id], ['section' => 1]);
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id], ['section' => 1]);
        $this->discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $this->student->id,
        ]);
        $this->section = (int) $DB->get_field('course_sections', 'id', ['course' => $this->course->id, 'section' => 1]);
        $this->events = new event_repository();
    }

    /**
     * Create an event, begin its attempt, hand it to a handler and finish the attempt as the queue does.
     *
     * @param event_type $type
     * @param array $payload
     * @param \local_assistente\application\queue\event_handler $handler
     */
    private function run_event(event_type $type, array $payload, $handler): void {
        $event = $this->events->create_event((int) $this->course->id, $type, $payload, time());
        $attempt = $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        $result = $handler->handle($event, $processing);
        $this->assertTrue($result->success, $type->value . ': ' . $result->message);
        $this->events->complete_attempt((int) $attempt->id, time(), $result->message);
    }

    /**
     * One round of repeated executions: every kind of analysis is made once more, with information that changed.
     *
     * @param int $round from 1
     */
    private function round(int $round): void {
        global $DB;
        $now = time();
        grade_update('mod/assign', $this->course->id, 'mod', 'assign', $this->assign->id, 0, [
            'userid' => $this->student->id, 'rawgrade' => 40 + 8 * $round,
        ]);
        $itemid = (int) $DB->get_field('grade_items', 'id', [
            'courseid' => $this->course->id, 'itemmodule' => 'assign', 'iteminstance' => $this->assign->id, 'itemnumber' => 0,
        ]);
        $uid = (int) $this->student->id;
        $clock = fn() => $now;
        $this->run_event(event_type::GRADE_CHANGED, ['userid' => $uid, 'itemid' => $itemid, 'occurred_at' => ++self::$occurred], new performance_handler());
        $this->run_event(event_type::PARTICIPATION_CHANGED, [
            'cmid' => (int) $this->assign->cmid, 'userid' => $uid, 'source' => 'grade', 'occurred_at' => ++self::$occurred,
        ], new frequency_handler(null, null, null, null, null, null, null, null, $clock));
        $this->run_event(
            event_type::CRITERIA_CHECK_REQUESTED,
            ['userid' => $uid],
            new criteria_handler(null, null, null, null, null, null, null, null, null, null, $clock)
        );
        $this->run_event(event_type::FORUM_POST_CREATED, [
            'cmid' => (int) $this->forum->cmid, 'discussionid' => (int) $this->discussion->id, 'postid' => (int) $this->discussion->firstpost,
        ], new forum_analysis_handler((new fake_adapter())->respond_with_callback(static fn($context) => [
            'use_case' => 'forum_analysis', 'status' => 'valid',
            'data' => ['forum_summary' => 'Resumo ' . $round, 'difficulties' => [
                ['discussion_id' => $context->discussion_ids()[0], 'description' => 'Dúvida ' . $round],
            ]],
        ])));
        $this->run_event(event_type::QUIZ_PUBLISHED, ['cmid' => (int) $this->quiz->cmid], new initial_analysis_handler(new fake_adapter()));
        $this->run_event(event_type::QUIZ_CLOSED, ['cmid' => (int) $this->quiz->cmid, 'timeclose' => $now - 100 - $round], new results_handler());
        $this->run_event(event_type::SYNTHESIS_REQUESTED, ['section_id' => $this->section], new synthesis_handler(new fake_adapter()));
        $this->waitForSecond();
    }

    /**
     * The analyses of the course by type, with the ids in the order they were made.
     *
     * @return array<string, int[]>
     */
    private function analyses_by_type(): array {
        global $DB;
        $by = array_fill_keys(self::TYPES, []);
        foreach ($DB->get_records('local_assistente_analysis', ['course_id' => $this->course->id], 'id ASC') as $row) {
            $by[$row->analysis_type][] = (int) $row->id;
        }
        return $by;
    }

    /**
     * A digest of an analysis and of every result that belongs to it, to tell later that it did not change.
     *
     * @param int $analysisid
     * @return string
     */
    private function digest(int $analysisid): string {
        global $DB;
        $parts = [json_encode($DB->get_record('local_assistente_analysis', ['id' => $analysisid], '*', MUST_EXIST))];
        foreach (self::RESULT_TABLES as $table) {
            $parts[] = $table . json_encode(array_values($DB->get_records($table, ['analysis_id' => $analysisid], 'id ASC')));
        }
        $processing = (int) $DB->get_field('local_assistente_analysis', 'processing_id', ['id' => $analysisid]);
        $parts[] = json_encode(array_values($DB->get_records('local_assistente_processing_context', ['processing_id' => $processing])));
        $parts[] = json_encode(array_values($DB->get_records('local_assistente_processing_attempt', ['processing_id' => $processing], 'id ASC')));
        return md5(implode('|', $parts));
    }

    /**
     * Rows of the results of each type of analysis, and the analyses, for the report of the volume.
     *
     * @return array<string, array>
     */
    private function volume(): array {
        global $DB;
        $volume = [];
        foreach ($this->analyses_by_type() as $type => $ids) {
            $rows = 0;
            foreach ($ids as $id) {
                foreach (self::RESULT_TABLES as $table) {
                    $rows += $DB->count_records($table, ['analysis_id' => $id]);
                }
            }
            $volume[$type] = ['analyses' => count($ids), 'result_rows' => $rows];
        }
        return $volume;
    }

    /**
     * Q11: repeated executions purge nothing, and the growth is measured. Every earlier analysis stays, unchanged, and can be traced.
     */
    public function test_repeated_executions_purge_nothing_and_every_analysis_stays_readable(): void {
        $digests = [];
        $reader = new lineage_reader();
        $report = [];
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->round($round);
            $by = $this->analyses_by_type();
            foreach (self::TYPES as $type) {
                $this->assertCount($round, $by[$type], "{$type} after round {$round}: one more analysis in each round, none purged");
            }
            foreach ($by as $ids) {
                foreach ($ids as $id) {
                    $digests[$id] ??= $this->digest($id);
                }
            }
            $report[$round] = $this->volume();
        }

        // Nothing earlier changed or went away.
        foreach ($digests as $id => $digest) {
            $this->assertSame($digest, $this->digest($id), "The analysis {$id} changed");
        }
        // Every analysis can be traced to its event and its source, however many came after it.
        $by = $this->analyses_by_type();
        foreach ($by as $type => $ids) {
            foreach ($ids as $id) {
                $lineage = $reader->trace_analysis($id);
                $this->assertNotNull($lineage, "{$type} #{$id}");
                $this->assertNotEmpty($lineage->attempts);
                $this->assertNotEmpty($lineage->sources, "{$type} #{$id} lost its sources");
            }
            // The earlier states are reachable from the newest one: all of them, in order, the newest first.
            $this->assertSame(array_reverse($ids), array_map(fn($a) => (int) $a->id, $reader->history(end($ids))), "history of {$type}");
        }
        // The volume grows by the same amount in each round: linear, with nothing discarded along the way.
        foreach (self::TYPES as $type) {
            $steps = [];
            for ($round = 2; $round <= self::ROUNDS; $round++) {
                $steps[] = $report[$round][$type]['result_rows'] - $report[$round - 1][$type]['result_rows'];
            }
            $this->assertCount(1, array_unique($steps), "{$type}: the growth per round is not constant (" . implode(', ', $steps) . ')');
            $this->assertGreaterThanOrEqual(0, $steps[0]);
        }
        $this->print_report($report);
    }

    /**
     * The scheduled tasks of the plugin, run after the history grew, delete nothing.
     */
    public function test_the_scheduled_tasks_purge_nothing(): void {
        for ($round = 1; $round <= 3; $round++) {
            $this->round($round);
        }
        $digests = [];
        foreach ($this->analyses_by_type() as $ids) {
            foreach ($ids as $id) {
                $digests[$id] = $this->digest($id);
            }
        }
        global $DB;
        $events = $DB->get_fieldset_select('local_assistente_event', 'id', '1 = 1');

        ob_start();
        try {
            (new \local_assistente\task\process_queue())->execute();
            (new \local_assistente\task\detect_quiz_close())->execute();
            (new \local_assistente\task\detect_reference_dates())->execute();
        } finally {
            ob_end_clean();
        }

        // The queue also handled the events the observers made while the rounds ran, so the analyses may be more; none is fewer or changed.
        foreach ($digests as $id => $digest) {
            $this->assertSame($digest, $this->digest($id), "The analysis {$id} changed or went away");
        }
        $this->assertSame([], array_diff($events, $DB->get_fieldset_select('local_assistente_event', 'id', '1 = 1')), 'No event went away');
        foreach ($this->analyses_by_type() as $type => $ids) {
            $this->assertGreaterThanOrEqual(3, count($ids), $type);
        }
    }

    /**
     * Nothing in the code deletes history: the only code that deletes rows of the plugin is the erasure of the data of a deleted course
     * (DEC-42 D2) and the deletion of the individual results of a student (DEC-42 D1). A new place has to be added to the list, on purpose.
     */
    public function test_no_code_deletes_history_except_the_erasures_that_the_decisions_allow(): void {
        global $CFG;
        // The erasure of a deleted course (T103) and the erasure of the individual results of a student (T104, DEC-42 D1).
        $allowed = [
            'classes/infrastructure/persistence/course_erasure_repository.php',
            'classes/infrastructure/persistence/student_data_repository.php',
        ];
        $root = $CFG->dirroot . '/local/assistente';
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/classes', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = '';
            foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    $code .= is_array($token) ? $token[1] : $token;
                }
            }
            if (preg_match('/->\s*(delete_records|delete_records_select)\s*\(|\bDELETE\s+FROM\b|\bTRUNCATE\s+TABLE\b/i', $code)) {
                $found[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
        sort($found);
        $this->assertSame($allowed, $found, 'History is deleted only where DEC-42 allows');
    }

    /**
     * Print the measured growth: analyses and result rows of each type after each round.
     *
     * @param array $report volume by round
     */
    private function print_report(array $report): void {
        $lines = ["\nT105 growth of the history by type of analysis (analyses / result rows), after each of " . self::ROUNDS . " rounds:"];
        $lines[] = sprintf('%-20s %s', 'type', implode('  ', array_map(fn($r) => sprintf('%-9s', "round {$r}"), array_keys($report))));
        foreach (self::TYPES as $type) {
            $cells = array_map(fn($r) => sprintf('%-9s', $report[$r][$type]['analyses'] . '/' . $report[$r][$type]['result_rows']), array_keys($report));
            $lines[] = sprintf('%-20s %s', $type, implode('  ', $cells));
        }
        fwrite(STDERR, implode("\n", $lines) . "\n");
    }
}
