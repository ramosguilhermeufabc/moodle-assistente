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


namespace local_assistente\application\query;

use core\exception\required_capability_exception;
use local_assistente\application\history\lineage_exception;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\config_repository;
use local_assistente\infrastructure\persistence\course_erasure_repository;
use local_assistente\infrastructure\persistence\event_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the aggregate queries (T110; quickstart 018 Q3, Q4, Q6, Q9 and Q12; DEC-35 D2, DEC-38, DEC-41, DEC-42 D3, DEC-43, DEC-45 D3).
 * Synthetic data only: a course made here, with the rows that the handlers would have written.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(aggregate_query::class)]
#[CoversClass(quiz_metrics_query::class)]
#[CoversClass(forums_query::class)]
#[CoversClass(material_summaries_query::class)]
#[CoversClass(synthesis_query::class)]
#[CoversClass(history_query::class)]
final class aggregate_queries_test extends \advanced_testcase {
    /** @var \stdClass course with the assistant enabled and separate groups */
    private \stdClass $course;

    /** @var \stdClass editing teacher */
    private \stdClass $teacher;

    /** @var \stdClass[] modules by key */
    private array $mods = [];

    /** @var int tick for the creation time of the rows */
    private int $tick = 0;

    /** @var presentation */
    private presentation $presentation;

    /**
     * A course with three questionnaires (without materials, with materials and a real zero, and without any analysis), two forums, three
     * pages and a teacher; the rows of the plugin are made by each test through the helpers.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1, 'numsections' => 3]);
        (new config_repository())->create((int) $this->course->id, true);
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        foreach (['qa' => 'Questionário A', 'qb' => 'Questionário B', 'qc' => 'Questionário C'] as $key => $name) {
            $this->mods[$key] = $generator->create_module('quiz', ['course' => $this->course->id, 'name' => $name]);
        }
        foreach (['f1' => 'Fórum 1', 'f2' => 'Fórum 2'] as $key => $name) {
            $this->mods[$key] = $generator->create_module('forum', ['course' => $this->course->id, 'name' => $name]);
        }
        foreach (['p1' => 'Página 1', 'p2' => 'Página 2', 'p3' => 'Página 3'] as $key => $name) {
            $this->mods[$key] = $generator->create_module('page', ['course' => $this->course->id, 'name' => $name]);
        }
        $string = [];
        include(__DIR__ . '/../../../lang/pt_br/local_assistente.php');
        $strings = $string;
        $this->presentation = new presentation(',', fn(string $key): string => $strings[$key]);
    }

    /**
     * Insert a row filling the required columns the test does not care about.
     *
     * @param string $table
     * @param array $values
     * @return int
     */
    private function row(string $table, array $values): int {
        global $DB;
        $record = [];
        foreach ($DB->get_columns($table) as $name => $column) {
            if ($name === 'id' || array_key_exists($name, $values)) {
                continue;
            }
            if ($column->not_null && $column->default_value === null && !$column->auto_increment) {
                $record[$name] = match (true) {
                    $column->meta_type === 'I' => 1,
                    in_array($column->meta_type, ['N', 'F'], true) => 0,
                    default => 'x',
                };
            }
        }
        return (int) $DB->insert_record($table, (object) ($values + $record));
    }

    /**
     * An analysis with its event, processing and snapshot.
     *
     * @param string $type the analysis type
     * @param int|null $cmid
     * @param array|null $snapshot
     * @param string|null $summary
     * @return int the id of the analysis
     */
    private function analysis(string $type, ?int $cmid, ?array $snapshot = null, ?string $summary = null): int {
        $events = new event_repository();
        $event = $events->create_event((int) $this->course->id, event_type::SYNTHESIS_REQUESTED, ['section_id' => 1 + $this->tick], time());
        $events->begin_attempt((int) $event->id, time());
        $processing = $events->find_processing((int) $event->id);
        if ($snapshot !== null) {
            $events->save_context((int) $processing->id, json_encode($snapshot + ['course_id' => (int) $this->course->id]), time());
        }
        return $this->row('local_assistente_analysis', [
            'course_id' => $this->course->id, 'processing_id' => $processing->id, 'analysis_type' => $type, 'cmid' => $cmid,
            'summary' => $summary, 'created_at' => 1700000000 + (++$this->tick) * 10,
        ]);
    }

    /**
     * The initial analysis of a questionnaire.
     *
     * @param string $quiz key of the module
     * @param float $coverage
     * @param array $complexities complexity of each question, question id => complexity
     * @param bool $materials whether the snapshot lists a material
     * @return int[] question id => questionnaire_question id
     */
    private function initial(string $quiz, float $coverage, array $complexities, bool $materials): array {
        $analysis = $this->analysis('quiz_initial', (int) $this->mods[$quiz]->cmid, ['format' => 1, 'materials' => $materials ? [['material_id' => 1]] : []]);
        $qa = $this->row('local_assistente_questionnaire_analysis', ['analysis_id' => $analysis, 'coverage' => $coverage]);
        $rows = [];
        foreach ($complexities as $question => $complexity) {
            $rows[$question] = $this->row('local_assistente_questionnaire_question', [
                'questionnaire_analysis_id' => $qa, 'question_id' => $question, 'question_version' => 1, 'complexity' => $complexity,
            ]);
        }
        return $rows;
    }

    /**
     * The results of a questionnaire.
     *
     * @param string $quiz key of the module
     * @param array $questions question id => [row id, average, correct, incorrect, partial]
     * @param array|null $metric [mean, sd, minimum, maximum]
     * @return int the analysis
     */
    private function results(string $quiz, array $questions, ?array $metric): int {
        $analysis = $this->analysis('quiz_results', (int) $this->mods[$quiz]->cmid);
        foreach ($questions as $question => [$rowid, $average, $correct, $incorrect, $partial]) {
            $this->row('local_assistente_question_result', [
                'analysis_id' => $analysis, 'questionnaire_question_id' => $rowid, 'question_id' => $question, 'question_version' => 1,
                'average_correctness' => $average, 'correct_count' => $correct, 'incorrect_count' => $incorrect, 'partial_result' => $partial,
            ]);
        }
        if ($metric !== null) {
            $this->row('local_assistente_questionnaire_metric', [
                'analysis_id' => $analysis, 'mean' => $metric[0], 'population_standard_deviation' => $metric[1], 'minimum' => $metric[2], 'maximum' => $metric[3],
            ]);
        }
        return $analysis;
    }

    /**
     * A material with versions.
     *
     * @param string $page key of the module
     * @param array $versions each [summary, status]; the last completed one becomes current
     * @return int the material
     */
    private function material(string $page, array $versions): int {
        global $DB;
        $material = $this->row('local_assistente_material', ['course_id' => $this->course->id, 'cmid' => $this->mods[$page]->cmid, 'section_id' => 1]);
        foreach ($versions as $number => [$summary, $status]) {
            $id = $this->row('local_assistente_material_version', [
                'material_id' => $material, 'version' => $number + 1, 'summary' => $summary, 'summary_status' => $status,
                'created_at' => 1700000000 + (++$this->tick) * 10,
            ]);
            if ($status === 'completed') {
                $DB->set_field('local_assistente_material', 'current_version', $id, ['id' => $material]);
            }
        }
        return $material;
    }

    /**
     * A version of the synthesis of a section.
     *
     * @param int $sectionid
     * @param int $version
     * @param string $content
     */
    private function synthesis(int $sectionid, int $version, string $content): void {
        $analysis = $this->analysis('section_synthesis', null);
        $this->row('local_assistente_pedagogical_synthesis', [
            'analysis_id' => $analysis, 'course_id' => $this->course->id, 'section_id' => $sectionid, 'version' => $version, 'content' => $content,
            'input_fingerprint' => str_repeat((string) $version, 64), 'created_at' => 1700000000 + (++$this->tick) * 10,
        ]);
    }

    /**
     * The id of a section of the course.
     *
     * @param int $number
     * @return int
     */
    private function section(int $number): int {
        global $DB;
        return (int) $DB->get_field('course_sections', 'id', ['course' => $this->course->id, 'section' => $number], MUST_EXIST);
    }

    /**
     * A query with the Portuguese presentation.
     *
     * @param string $class
     * @return object
     */
    private function make(string $class): object {
        return new $class(null, null, $this->presentation);
    }

    /**
     * The rows of a result by a key.
     *
     * @param array $result
     * @param string $key
     * @return array[]
     */
    private function by(array $result, string $key): array {
        return array_column($result['rows'], null, $key);
    }

    /**
     * Digest of every table of the plugin, to tell that a query wrote nothing.
     *
     * @return string
     */
    private function digest(): string {
        global $DB;
        $all = [];
        foreach (course_erasure_repository::TABLES as $table) {
            $all[$table] = array_values($DB->get_records($table, null, 'id'));
        }
        return md5(json_encode($all));
    }

    /**
     * Q3 and Q9 (DEC-35 D2): the metrics of the questionnaires with only the fields of the contract; the coverage 0.0 of the questionnaire
     * with no materials is "sem materiais" and the real zero is "0,0"; a questionnaire with no execution, and a question nobody answered,
     * are "sem dados"; the partial result that does not apply is null; values are truncated and never zero when there is no data.
     */
    public function test_quiz_metrics_with_only_the_contract_fields(): void {
        $a = $this->initial('qa', 0.0, [11 => 0.4, 12 => 0.0], false);
        $this->initial('qb', 0.0, [21 => 0.7], true);
        $this->results('qa', [11 => [$a[11], 66.666, 2, 1, 33.333], 12 => [$a[12], 100, 3, 0, null]], [66.669, 12.5, 50, 100]);

        $result = $this->make(quiz_metrics_query::class)->query((int) $this->course->id, (int) $this->teacher->id);

        $this->assertTrue($result['available']);
        $this->assertSame(['Questionário A', 'Questionário B', 'Questionário C'], array_column($result['rows'], 'quiz'));
        foreach ($result['rows'] as $row) {
            $this->assertSame(['cmid', 'quiz', 'coverage', 'metrics', 'questions'], array_keys($row));
            $this->assertSame(['mean', 'population_standard_deviation', 'minimum', 'maximum'], array_keys($row['metrics']));
            foreach ($row['questions'] as $question) {
                $this->assertSame(
                    ['question_id', 'question_version', 'complexity', 'average_correctness', 'correct_count', 'incorrect_count', 'partial_result'],
                    array_keys($question)
                );
            }
        }
        $rows = $this->by($result, 'quiz');
        $this->assertSame(['state' => 'valid', 'text' => 'sem materiais'], $rows['Questionário A']['coverage'], 'Q9: coverage 0.0 and no materials');
        $this->assertSame(['state' => 'valid', 'text' => '0,0'], $rows['Questionário B']['coverage'], 'Q9: a real zero');
        $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $rows['Questionário C']['coverage']);
        $metrics = array_map(fn($m) => $m['text'], $rows['Questionário A']['metrics']);
        $this->assertSame(['mean' => '66,66%', 'population_standard_deviation' => '12,50%', 'minimum' => '50,00%', 'maximum' => '100,00%'], $metrics);
        $questions = array_column($rows['Questionário A']['questions'], null, 'question_id');
        $this->assertSame('0,4', $questions[11]['complexity']['text']);
        $this->assertSame(['66,66%', '2', '1', '33,33%'], [
            $questions[11]['average_correctness']['text'], $questions[11]['correct_count']['text'], $questions[11]['incorrect_count']['text'],
            $questions[11]['partial_result']['text'],
        ]);
        $this->assertNull($questions[12]['partial_result'], 'Does not apply: null, not zero');
        $this->assertSame('0,0', $questions[12]['complexity']['text']);
        $b = $rows['Questionário B'];
        $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $b['metrics']['mean'], 'No execution: never zero');
        $this->assertSame('sem dados', $b['questions'][0]['average_correctness']['text']);
        $this->assertSame('sem dados', $b['questions'][0]['correct_count']['text']);
        $this->assertSame('0,7', $b['questions'][0]['complexity']['text']);
        $this->assertSame([], $rows['Questionário C']['questions']);
    }

    /**
     * The coverage and the complexity shown are those of the newest initial analysis; an older one is history.
     */
    public function test_the_newest_initial_analysis_is_the_one_shown(): void {
        $this->initial('qa', 0.9, [11 => 0.8], true);
        $this->initial('qa', 0.3, [11 => 0.2], true);

        $rows = $this->by($this->make(quiz_metrics_query::class)->query((int) $this->course->id, (int) $this->teacher->id), 'quiz');

        $this->assertSame('0,3', $rows['Questionário A']['coverage']['text']);
        $this->assertSame(['0,2', '0,8'], array_map(fn($q) => $q['complexity']['text'], $rows['Questionário A']['questions']));
    }

    /**
     * An aggregate null is "sem dados", a question that nobody answered in a questionnaire that has results is "sem dados", and a snapshot
     * that cannot be read does not allow "sem materiais".
     */
    public function test_quiz_states_when_data_is_missing(): void {
        global $DB;
        $a = $this->initial('qa', 0.0, [11 => 0.4, 12 => 0.5], false);
        $this->results('qa', [11 => [$a[11], 0, 0, 3, null]], null);

        $rows = $this->by($this->make(quiz_metrics_query::class)->query((int) $this->course->id, (int) $this->teacher->id), 'quiz');

        $questions = array_column($rows['Questionário A']['questions'], null, 'question_id');
        $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $rows['Questionário A']['metrics']['maximum'], 'No aggregate');
        $this->assertSame('0,00%', $questions[11]['average_correctness']['text'], 'A real zero is zero');
        $this->assertSame('sem dados', $questions[12]['average_correctness']['text'], 'Nobody answered it');

        $DB->delete_records('local_assistente_processing_context');
        $rows = $this->by($this->make(quiz_metrics_query::class)->query((int) $this->course->id, (int) $this->teacher->id), 'quiz');
        $this->assertSame('0,0', $rows['Questionário A']['coverage']['text'], 'Without the snapshot, "no materials" cannot be asserted');
    }

    /**
     * The forums: the current snapshot only, the texts as stored (HTML not interpreted), a forum with no snapshot as "sem dados", and the
     * difficulty of a discussion that is gone keeps its text and says "fonte removida".
     */
    public function test_forums_show_the_current_snapshot(): void {
        $generator = $this->getDataGenerator();
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->mods['f1']->id, 'userid' => $this->teacher->id,
        ]);
        $old = $this->analysis('forum_analysis', (int) $this->mods['f1']->cmid, null, 'Resumo antigo');
        $this->row('local_assistente_forum_difficulty', ['analysis_id' => $old, 'discussion_id' => $discussion->id, 'description' => 'Dificuldade antiga']);
        $new = $this->analysis('forum_analysis', (int) $this->mods['f1']->cmid, null, 'Resumo <b>novo</b> & "atual"');
        $this->row('local_assistente_forum_difficulty', ['analysis_id' => $new, 'discussion_id' => $discussion->id, 'description' => 'Dúvida <i>1</i>']);
        $this->row('local_assistente_forum_difficulty', ['analysis_id' => $new, 'discussion_id' => 987654, 'description' => 'Dúvida de uma discussão apagada']);

        $result = $this->make(forums_query::class)->query((int) $this->course->id, (int) $this->teacher->id);

        $this->assertSame(['Fórum 1', 'Fórum 2'], array_column($result['rows'], 'forum'));
        foreach ($result['rows'] as $row) {
            $this->assertSame(['cmid', 'forum', 'status', 'summary', 'difficulties'], array_keys($row));
        }
        $rows = $this->by($result, 'forum');
        $this->assertSame('Resumo <b>novo</b> & "atual"', $rows['Fórum 1']['summary'], 'Verbatim, as text');
        $this->assertSame(['Dúvida <i>1</i>', 'Dúvida de uma discussão apagada'], array_column($rows['Fórum 1']['difficulties'], 'description'));
        $this->assertSame([null, 'fonte removida'], array_column($rows['Fórum 1']['difficulties'], 'source'));
        $this->assertSame(['discussion_id', 'description', 'source'], array_keys($rows['Fórum 1']['difficulties'][0]));
        $this->assertSame(['state' => 'valid', 'text' => null], $rows['Fórum 1']['status']);
        $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $rows['Fórum 2']['status']);
        $this->assertNull($rows['Fórum 2']['summary']);
        $this->assertSame([], $rows['Fórum 2']['difficulties']);
    }

    /**
     * The summaries of the materials: the current version only, "sem dados" for a material without a current version and for a resource the
     * plugin does not know, and the activities that are not resources are not listed.
     */
    public function test_material_summaries_show_the_current_version(): void {
        $this->material('p1', [['Resumo da versão 1', 'completed'], ['Resumo da versão 2', 'completed'], ['', 'pending']]);
        $this->material('p2', [['', 'failed']]);

        $result = $this->make(material_summaries_query::class)->query((int) $this->course->id, (int) $this->teacher->id);

        $this->assertSame(['Página 1', 'Página 2', 'Página 3'], array_column($result['rows'], 'material'));
        foreach ($result['rows'] as $row) {
            $this->assertSame(['cmid', 'material', 'status', 'version', 'summary'], array_keys($row));
        }
        $rows = $this->by($result, 'material');
        $this->assertSame([2, 'Resumo da versão 2'], [$rows['Página 1']['version'], $rows['Página 1']['summary']]);
        foreach (['Página 2', 'Página 3'] as $name) {
            $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $rows[$name]['status']);
            $this->assertNull($rows[$name]['summary']);
            $this->assertNull($rows[$name]['version']);
        }
    }

    /**
     * The synthesis: the version in force of each section, verbatim and not interpreted, and "sem dados" for the sections without one; a
     * change in a section does not show in another.
     */
    public function test_synthesis_shows_the_version_in_force_by_section(): void {
        $one = $this->section(1);
        $this->synthesis($one, 1, 'Primeira versão');
        $this->synthesis($one, 2, '<script>alert(1)</script> Segunda & "vigente"');
        $this->synthesis($this->section(2), 1, 'Outra seção');

        $result = $this->make(synthesis_query::class)->query((int) $this->course->id, (int) $this->teacher->id);

        foreach ($result['rows'] as $row) {
            $this->assertSame(['section_id', 'section', 'status', 'version', 'content'], array_keys($row));
        }
        $rows = $this->by($result, 'section_id');
        $this->assertSame([2, '<script>alert(1)</script> Segunda & "vigente"'], [$rows[$one]['version'], $rows[$one]['content']]);
        $this->assertSame([1, 'Outra seção'], [$rows[$this->section(2)]['version'], $rows[$this->section(2)]['content']]);
        $this->assertSame(['state' => 'no_data', 'text' => 'sem dados'], $rows[$this->section(3)]['status']);
        $this->assertNull($rows[$this->section(3)]['content']);
        $this->assertCount(4, $result['rows'], 'Sections 0 to 3');
    }

    /**
     * The history of each entity: previous executions and versions, newest first, with the moment and the version, the current one marked.
     */
    public function test_history_of_each_entity(): void {
        $course = (int) $this->course->id;
        $a = $this->initial('qa', 0.2, [11 => 0.4], true);
        $r1 = $this->results('qa', [11 => [$a[11], 10, 0, 1, null]], [10, 0, 10, 10]);
        $r2 = $this->results('qa', [11 => [$a[11], 90, 1, 0, null]], [90, 0, 90, 90]);
        $f1 = $this->analysis('forum_analysis', (int) $this->mods['f1']->cmid, null, 'Resumo 1');
        $f2 = $this->analysis('forum_analysis', (int) $this->mods['f1']->cmid, null, 'Resumo 2');
        $this->material('p1', [['Resumo v1', 'completed'], ['Resumo v2', 'completed']]);
        $one = $this->section(1);
        $this->synthesis($one, 1, 'S1');
        $this->synthesis($one, 2, 'S2');
        $report = $this->row('local_assistente_report', ['course_id' => $course]);
        $this->row('local_assistente_report_version', ['report_id' => $report, 'version' => 1, 'created_at' => 1700000100]);
        $this->row('local_assistente_report_version', ['report_id' => $report, 'version' => 2, 'created_at' => 1700000200]);
        $query = $this->make(history_query::class);
        $teacher = (int) $this->teacher->id;

        $quiz = $query->query($course, $teacher, history_kind::QUIZ, (int) $this->mods['qa']->cmid)['rows'];
        $this->assertSame(['quiz_results', 'quiz_results', 'quiz_initial'], array_column($quiz, 'type'), 'Newest first');
        $this->assertSame([$r2, $r1], array_slice(array_column($quiz, 'analysis_id'), 0, 2));
        $this->assertSame([true, false, true], array_column($quiz, 'current'), 'The newest of each type is the current one');

        $forum = $query->query($course, $teacher, history_kind::FORUM, (int) $this->mods['f1']->cmid)['rows'];
        $this->assertSame([$f2, $f1], array_column($forum, 'analysis_id'));
        $this->assertSame(['Resumo 2', 'Resumo 1'], array_column($forum, 'content'));
        $this->assertSame([true, false], array_column($forum, 'current'));

        $material = $query->query($course, $teacher, history_kind::MATERIAL, (int) $this->mods['p1']->cmid)['rows'];
        $this->assertSame([2, 1], array_column($material, 'version'));
        $this->assertSame([true, false], array_column($material, 'current'));
        $this->assertSame(['Resumo v2', 'Resumo v1'], array_column($material, 'content'));

        $synthesis = $query->query($course, $teacher, history_kind::SYNTHESIS, $one)['rows'];
        $this->assertSame([2, 1], array_column($synthesis, 'version'));
        $this->assertSame(['S2', 'S1'], array_column($synthesis, 'content'));

        $reports = $query->query($course, $teacher, history_kind::REPORT)['rows'];
        $this->assertSame([2, 1], array_column($reports, 'version'));
        $this->assertSame([1700000200, 1700000100], array_column($reports, 'created_at'));
        $this->assertSame([null, null], array_column($reports, 'content'), 'The snapshot of a report is not shown');
        foreach ([$quiz, $forum, $material, $synthesis, $reports] as $rows) {
            foreach ($rows as $row) {
                $this->assertSame(['type', 'version', 'analysis_id', 'created_at', 'current', 'content', 'source'], array_keys($row));
                $this->assertNull($row['source'], 'The sources exist');
            }
        }
        $this->assertSame([], $query->query($course, $teacher, history_kind::FORUM, (int) $this->mods['f2']->cmid)['rows'], 'Nothing to show is empty');
        $this->assertSame([], $query->query($course, $teacher, history_kind::MATERIAL, (int) $this->mods['p3']->cmid)['rows']);
    }

    /**
     * Q12 and DEC-42 D3: an activity or a section removed in Moodle keeps its history and every entry says "fonte removida".
     */
    public function test_a_removed_source_keeps_the_history_and_says_so(): void {
        global $DB;
        $course = (int) $this->course->id;
        $this->material('p1', [['Resumo v1', 'completed']]);
        $this->analysis('forum_analysis', (int) $this->mods['f1']->cmid, null, 'Resumo do fórum');
        $this->synthesis($this->section(3), 1, 'Síntese da seção 3');
        (new \core_courseformat\local\cmactions(get_course($course)))->delete((int) $this->mods['p1']->cmid);
        (new \core_courseformat\local\cmactions(get_course($course)))->delete((int) $this->mods['f1']->cmid);
        course_delete_section(get_course($course), 3, true);
        $before = $this->digest();
        $query = $this->make(history_query::class);
        $teacher = (int) $this->teacher->id;

        $material = $query->query($course, $teacher, history_kind::MATERIAL, (int) $this->mods['p1']->cmid)['rows'];
        $forum = $query->query($course, $teacher, history_kind::FORUM, (int) $this->mods['f1']->cmid)['rows'];
        $synthesis = $query->query($course, $teacher, history_kind::SYNTHESIS, $this->section_gone())['rows'];

        $this->assertSame(['fonte removida'], array_column($material, 'source'));
        $this->assertSame(['Resumo v1'], array_column($material, 'content'), 'The text stays');
        $this->assertSame(['fonte removida'], array_column($forum, 'source'));
        $this->assertSame(['Resumo do fórum'], array_column($forum, 'content'));
        $this->assertSame(['fonte removida'], array_column($synthesis, 'source'));
        $this->assertSame(['Síntese da seção 3'], array_column($synthesis, 'content'));
        $this->assertSame($before, $this->digest(), 'The history was not changed');
        $this->assertSame(['Página 2', 'Página 3'], array_column($this->make(material_summaries_query::class)->query($course, $teacher)['rows'], 'material'));
    }

    /**
     * The id of the section that test_a_removed_source_keeps_the_history_and_says_so deleted, read from the synthesis that points to it.
     *
     * @return int
     */
    private function section_gone(): int {
        global $DB;
        return (int) $DB->get_field('local_assistente_pedagogical_synthesis', 'section_id', ['course_id' => $this->course->id], MUST_EXIST);
    }

    /**
     * Q6 and DEC-43: no aggregate query has a filter by group: a group is refused, and a teacher restricted to their own groups gets the same
     * rows as one who sees all.
     */
    public function test_the_filter_by_group_is_not_available(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $group = $generator->create_group(['courseid' => $this->course->id, 'name' => 'A']);
        $restricted = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $restricted->id]);
        assign_capability('local/assistente:view', CAP_ALLOW, $DB->get_field('role', 'id', ['shortname' => 'teacher']),
            \context_course::instance($this->course->id)->id, true);
        $this->initial('qa', 0.3, [11 => 0.4], true);
        $this->analysis('forum_analysis', (int) $this->mods['f1']->cmid, null, 'Resumo');
        $this->material('p1', [['Resumo', 'completed']]);
        $this->synthesis($this->section(1), 1, 'S1');
        $course = (int) $this->course->id;
        $calls = [
            'quiz' => fn($user, $g) => $this->make(quiz_metrics_query::class)->query($course, $user, $g),
            'forums' => fn($user, $g) => $this->make(forums_query::class)->query($course, $user, $g),
            'materials' => fn($user, $g) => $this->make(material_summaries_query::class)->query($course, $user, $g),
            'synthesis' => fn($user, $g) => $this->make(synthesis_query::class)->query($course, $user, $g),
            'history' => fn($user, $g) => $this->make(history_query::class)->query($course, $user, history_kind::FORUM, (int) $this->mods['f1']->cmid, $g),
        ];

        foreach ($calls as $name => $call) {
            foreach ([(int) $group->id, 987654, 0] as $filter) {
                try {
                    $call((int) $this->teacher->id, $filter);
                    $this->fail("$name accepted a group");
                } catch (\InvalidArgumentException) {
                    $this->addToAssertionCount(1);
                }
            }
            $this->assertEquals($call((int) $this->teacher->id, null), $call((int) $restricted->id, null), "$name: restricted teacher");
        }
    }

    /**
     * Q4: no query takes free text: the parameters are the course, the user, a group that is refused, and for the history a fixed kind and
     * a number. The history of the report also takes a version_filter (Q-020-02): a value object of one number and two instants, no text.
     */
    public function test_no_query_takes_free_text(): void {
        $classes = [quiz_metrics_query::class, forums_query::class, material_summaries_query::class, synthesis_query::class, history_query::class,
            grades_query::class, frequency_query::class, below_criteria_query::class];
        foreach ($classes as $class) {
            $parameters = (new \ReflectionMethod($class, 'query'))->getParameters();
            foreach ($parameters as $parameter) {
                $type = (string) $parameter->getType();
                $allowed = ['int', '?int', history_kind::class];
                if ($class === history_query::class) {
                    $allowed[] = '?' . \local_assistente\domain\report\version_filter::class;
                }
                $this->assertContains($type, $allowed, "$class::{$parameter->getName()} is $type");
            }
        }
        $this->assertSame(['quiz', 'forum', 'material', 'synthesis', 'report'], array_column(history_kind::cases(), 'value'));
    }

    /**
     * The backend refuses a teacher of another course, a student and a user with no role, in every query, before saying anything about the
     * assistant: the course with the assistant disabled gives them an exception, not a message.
     */
    public function test_a_course_that_is_not_theirs_is_refused(): void {
        $generator = $this->getDataGenerator();
        $other = $generator->create_course();
        $off = $generator->create_course();
        (new config_repository())->create((int) $off->id, false);
        $strangers = [$generator->create_and_enrol($other, 'editingteacher'), $generator->create_and_enrol($this->course, 'student'), $generator->create_user()];
        foreach ($strangers as $user) {
            foreach ([(int) $this->course->id, (int) $off->id] as $course) {
                foreach ([
                    fn() => $this->make(quiz_metrics_query::class)->query($course, (int) $user->id),
                    fn() => $this->make(forums_query::class)->query($course, (int) $user->id),
                    fn() => $this->make(material_summaries_query::class)->query($course, (int) $user->id),
                    fn() => $this->make(synthesis_query::class)->query($course, (int) $user->id),
                    fn() => $this->make(history_query::class)->query($course, (int) $user->id, history_kind::REPORT),
                ] as $call) {
                    try {
                        $call();
                        $this->fail("User {$user->id} was not refused");
                    } catch (required_capability_exception) {
                        $this->addToAssertionCount(1);
                    }
                }
            }
        }
    }

    /**
     * DEC-45 D3: a course with the assistant disabled, or without configuration, shows no results and says "assistente não habilitado".
     */
    public function test_a_course_without_the_assistant_enabled_shows_no_results(): void {
        $generator = $this->getDataGenerator();
        $off = $generator->create_course();
        (new config_repository())->create((int) $off->id, false);
        $none = $generator->create_course();
        $expected = ['available' => false, 'message' => 'assistente não habilitado', 'rows' => []];
        foreach ([$off, $none] as $course) {
            $teacher = $generator->create_and_enrol($course, 'editingteacher');
            foreach ([quiz_metrics_query::class, forums_query::class, material_summaries_query::class, synthesis_query::class] as $class) {
                $this->assertSame($expected, $this->make($class)->query((int) $course->id, (int) $teacher->id));
            }
            $this->assertSame($expected, $this->make(history_query::class)->query((int) $course->id, (int) $teacher->id, history_kind::REPORT));
        }
    }

    /**
     * Failures are not hidden: an invalid module, a module of another course and an id missing where one is needed are exceptions, never
     * an empty answer or a zero.
     */
    public function test_failures_are_exceptions_not_empty_answers(): void {
        $other = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_module('page', ['course' => $other->id]);
        $query = $this->make(history_query::class);
        $course = (int) $this->course->id;
        $teacher = (int) $this->teacher->id;

        $this->expectException(\InvalidArgumentException::class);
        try {
            $query->query($course, $teacher, history_kind::FORUM, 0);
        } finally {
            try {
                $query->query($course, $teacher, history_kind::FORUM, (int) $foreign->cmid);
                $this->fail('A module of another course was accepted');
            } catch (lineage_exception) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * The queries read and write nothing, and a course with nothing has empty lists.
     */
    public function test_queries_write_nothing_and_empty_courses_are_empty(): void {
        $this->initial('qa', 0.3, [11 => 0.4], true);
        $this->synthesis($this->section(1), 1, 'S1');
        $before = $this->digest();
        $teacher = (int) $this->teacher->id;
        $course = (int) $this->course->id;

        foreach ([quiz_metrics_query::class, forums_query::class, material_summaries_query::class, synthesis_query::class] as $class) {
            $this->make($class)->query($course, $teacher);
        }
        $this->make(history_query::class)->query($course, $teacher, history_kind::QUIZ, (int) $this->mods['qa']->cmid);
        $this->assertSame($before, $this->digest());

        $empty = $this->getDataGenerator()->create_course();
        (new config_repository())->create((int) $empty->id, true);
        $owner = $this->getDataGenerator()->create_and_enrol($empty, 'editingteacher');
        foreach ([quiz_metrics_query::class, forums_query::class, material_summaries_query::class] as $class) {
            $this->assertSame([], $this->make($class)->query((int) $empty->id, (int) $owner->id)['rows']);
        }
    }
}
