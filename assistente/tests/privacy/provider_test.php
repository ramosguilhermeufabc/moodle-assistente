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


namespace local_assistente\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_assistente\application\privacy\erasure_pending_exception;
use local_assistente\application\privacy\student_data_eraser;
use local_assistente\domain\event\erased_subject;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\lock_unavailable_exception;
use local_assistente\infrastructure\persistence\unit_of_work;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the privacy provider (T104; quickstart 017 Q7, Q8 and Q9; DEC-42 D1, DEC-56, DEC-57). A synthetic world of two courses and two
 * students, with rows in every table that holds something of a student; the provider is exercised through the contracts of the Privacy API
 * of Moodle 5.2.4 and, for the erasure, also through the real queue, the real locks and database triggers that fail in the middle.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
#[CoversClass(student_data_eraser::class)]
#[CoversClass(\local_assistente\infrastructure\persistence\history_redaction_writer::class)]
#[CoversClass(\local_assistente\infrastructure\persistence\student_data_repository::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass[] the courses A, B and C (C has nothing of the plugin) */
    private array $courses = [];

    /** @var \stdClass Maria Souza */
    private \stdClass $maria;

    /** @var \stdClass João Lima */
    private \stdClass $joao;

    /** @var event_repository */
    private event_repository $events;

    /** @var int[] ids that the world created, by course id and kind */
    private array $made = [];

    /** @var string[] lines that the provider recorded */
    private array $traced = [];

    /**
     * Two students, three courses, and the data of both students in A and B.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $factory = unit_of_work::lock_factory();
        $first = $factory->get_lock('probe_reentrancy', 1);
        $second = $first ? $factory->get_lock('probe_reentrancy', 1) : false;
        $first && $first->release();
        $second && $second->release();
        if ($second) {
            $this->markTestSkipped('The lock backend is re-entrant in one process; exclusion is validated with separate processes (CL-02).');
        }
        $this->maria = $this->getDataGenerator()->create_user(['firstname' => 'Maria', 'lastname' => 'Souza']);
        $this->joao = $this->getDataGenerator()->create_user(['firstname' => 'João', 'lastname' => 'Lima']);
        foreach (['A', 'B', 'C'] as $name) {
            $this->courses[$name] = $this->getDataGenerator()->create_course(['shortname' => "P104{$name}"]);
        }
        $this->events = new event_repository();
        $this->populate($this->courses['A']);
        $this->populate($this->courses['B']);
        $this->traced = [];
        provider::use_tracer(function (string $line) {
            $this->traced[] = $line;
        });
    }

    public function tearDown(): void {
        provider::use_tracer(null);
        parent::tearDown();
    }

    /**
     * Insert a row, filling every required column that the test does not care about.
     *
     * @param string $table
     * @param array $values
     * @return int the id of the row
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
     * An event with its processing, one attempt and, optionally, a snapshot, and an analysis of that processing.
     *
     * @param \stdClass $course
     * @param event_type $type
     * @param array $payload
     * @param string $state processed, failure or pending
     * @param array|null $context the snapshot, as an array
     * @param string|null $analysis type of the analysis to make, if any
     * @param string $response the response of the attempt (and of the event when it failed)
     * @return array{event: int, processing: int|null, analysis: int|null, attempt: int|null}
     */
    private function chain(\stdClass $course, event_type $type, array $payload, string $state, ?array $context, ?string $analysis, string $response = 'ok'): array {
        global $DB;
        $event = $this->events->create_event((int) $course->id, $type, $payload, time());
        $out = ['event' => (int) $event->id, 'processing' => null, 'analysis' => null, 'attempt' => null];
        if ($state === 'pending') {
            return $out;
        }
        $attempt = $this->events->begin_attempt((int) $event->id, time());
        $processing = $this->events->find_processing((int) $event->id);
        $out['processing'] = (int) $processing->id;
        $out['attempt'] = (int) $attempt->id;
        if ($context !== null) {
            $this->events->save_context((int) $processing->id, json_encode($context + ['course_id' => (int) $course->id], JSON_UNESCAPED_UNICODE), time());
        }
        if ($analysis !== null) {
            $out['analysis'] = $this->row('local_assistente_analysis', [
                'course_id' => $course->id, 'processing_id' => $processing->id, 'analysis_type' => $analysis, 'cmid' => null, 'summary' => null, 'created_at' => time(),
            ]);
        }
        if ($state === 'failure') {
            $this->events->fail_attempt((int) $attempt->id, time(), $response);
            for ($i = 0; $i < 2; $i++) {
                $again = $this->events->begin_attempt((int) $event->id, time());
                $this->events->fail_attempt((int) $again->id, time(), $response);
            }
        } else {
            $this->events->complete_attempt((int) $attempt->id, time(), $response);
        }
        return $out;
    }

    /**
     * The data of both students in a course, in every table that can hold something of a student.
     *
     * @param \stdClass $course
     */
    private function populate(\stdClass $course): void {
        global $DB;
        $c = (int) $course->id;
        $m = (int) $this->maria->id;
        $j = (int) $this->joao->id;
        $made = [];
        // Performance of each student, with an individual snapshot.
        foreach ([$m, $j] as $u) {
            $g = $this->chain($course, event_type::GRADE_CHANGED, ['userid' => $u, 'itemid' => 9, 'occurred_at' => $u * 1000 + $c], 'processed',
                ['format' => 1, 'kind' => 'grade_performance', 'student_id' => $u, 'course_total_percent' => 80.5, 'activities_with_grade' => 2], 'grade_performance');
            $this->row('local_assistente_performance', ['analysis_id' => $g['analysis'], 'student_id' => $u, 'activity_id' => 5, 'grade' => 80.5]);
            $made['grade'][$u] = $g;
        }
        // A pending grade event of Maria.
        $made['pending'] = $this->chain($course, event_type::GRADE_CHANGED, ['userid' => $m, 'itemid' => 10, 'occurred_at' => $m * 1000 + $c + 1], 'pending', null, null);
        // Frequency of the course: one snapshot with the entries of both students.
        $f = $this->chain($course, event_type::REFERENCE_DATE_REACHED, ['cmid' => 5, 'reference_date' => 1000 + $c], 'processed', [
            'format' => 1, 'kind' => 'frequency', 'calculated_at' => 5,
            'course_access' => [
                ['student_id' => $m, 'accessed' => true, 'last_access' => 1700000000],
                ['student_id' => $j, 'accessed' => true, 'last_access' => 1700000100],
            ],
            'activities_without_date' => [7],
        ], 'frequency');
        foreach ([$m, $j] as $u) {
            $fid = $this->row('local_assistente_frequency', ['analysis_id' => $f['analysis'], 'student_id' => $u, 'section_id' => 3, 'total_activities' => 2, 'participating_activities' => 1, 'percentage' => 50]);
            $this->row('local_assistente_frequency_activity', ['frequency_id' => $fid, 'activity_id' => 5, 'participated' => 1, 'reference_date' => 1000]);
        }
        $made['frequency'] = $f;
        // Criteria: individual of Maria, and of the whole course with both.
        $ci = $this->chain($course, event_type::CRITERIA_CHECK_REQUESTED, ['userid' => $m], 'processed', [
            'format' => 1, 'kind' => 'criteria_check', 'student_id' => $m, 'evaluated_at' => 5, 'limits' => ['activity_criterion' => 60.0],
            'activity_value' => 80.5, 'frequency_value' => 50.0, 'forums_due' => [4], 'forums_missing' => [], 'performance_analysis_id' => 1, 'frequency_analysis_id' => 2,
        ], 'criteria_check');
        $this->row('local_assistente_criteria_result', ['analysis_id' => $ci['analysis'], 'student_id' => $m, 'activity_status' => 1, 'forum_status' => 1, 'frequency_status' => 1]);
        $cc = $this->chain($course, event_type::COURSE_CRITERIA_CHECK_REQUESTED, ['course_id' => $c], 'processed', [
            'format' => 1, 'kind' => 'criteria_check', 'scope' => 'course', 'evaluated_at' => 5, 'limits' => ['activity_criterion' => 60.0],
            'students' => [
                ['student_id' => $m, 'activity_value' => 80.5, 'frequency_value' => 50.0, 'forums_due' => [], 'forums_missing' => []],
                ['student_id' => $j, 'activity_value' => 70.0, 'frequency_value' => 40.0, 'forums_due' => [], 'forums_missing' => []],
            ],
        ], 'criteria_check');
        foreach ([$m, $j] as $u) {
            $this->row('local_assistente_criteria_result', ['analysis_id' => $cc['analysis'], 'student_id' => $u, 'activity_status' => 0, 'forum_status' => 2, 'frequency_status' => 1]);
        }
        $made['criteria_individual'] = $ci;
        $made['criteria_course'] = $cc;
        // Quiz attempt event of Maria and of João.
        foreach ([$m, $j] as $u) {
            $made['quiz'][$u] = $this->chain($course, event_type::QUIZ_ATTEMPT_SUBMITTED, ['cmid' => 6, 'attemptid' => 100 + $u + $c, 'userid' => $u], 'processed', null, null);
        }
        // A failed event of each student whose message carries the identifier, and one that does not.
        foreach ([$m, $j] as $u) {
            $made['failed'][$u] = $this->chain($course, event_type::GRADE_CHANGED, ['userid' => $u, 'itemid' => 11, 'occurred_at' => $u * 1000 + $c + 2], 'failure', null, null,
                "technical: local_assistente\\infrastructure\\persistence\\invalid_domain_value_exception: The student {$u} does not exist.");
        }
        $made['plain_failure'] = $this->chain($course, event_type::SYNTHESIS_REQUESTED, ['section_id' => 77], 'failure', null, null, 'technical: the section is not valid (section_invalid)');
        // Forum analysis with a summary and a difficulty that name the students.
        $forum = $this->chain($course, event_type::FORUM_POST_CREATED, ['cmid' => 8, 'discussionid' => 9, 'postid' => 10], 'processed',
            ['format' => 1, 'section_id' => 1, 'materials' => [['material_id' => 1, 'name' => 'Texto de Maria Souza', 'summary' => 'Resumo do material que cita Maria Souza', 'eligible' => true, 'version_id' => 1]]],
            'forum_analysis');
        $DB->set_field('local_assistente_analysis', 'summary', 'A aluna Maria Souza e o colega João Lima discutiram o prazo.', ['id' => $forum['analysis']]);
        $difficulty = $this->row('local_assistente_forum_difficulty', ['analysis_id' => $forum['analysis'], 'discussion_id' => 9, 'description' => 'Dúvida de MARIA sobre o prazo; Sôuza não entendeu.']);
        $made['forum'] = $forum + ['difficulty' => $difficulty];
        // A synthesis, a material version and a report version with the name.
        $syn = $this->chain($course, event_type::SYNTHESIS_REQUESTED, ['section_id' => 3], 'processed', null, 'section_synthesis');
        $made['synthesis'] = $syn + ['row' => $this->row('local_assistente_pedagogical_synthesis', [
            'analysis_id' => $syn['analysis'], 'course_id' => $c, 'section_id' => 3, 'version' => 1, 'content' => 'Maria Souza foi a que mais participou. Souza, Maria e Mariana.', 'input_fingerprint' => str_repeat('a', 64),
        ])];
        $material = $this->row('local_assistente_material', ['course_id' => $c, 'section_id' => 3, 'cmid' => 50 + $c]);
        $made['material_version'] = $this->row('local_assistente_material_version', ['material_id' => $material, 'version' => 1, 'summary' => 'Material usado por Maria Souza', 'content_hash' => str_repeat('b', 64)]);
        $report = $this->row('local_assistente_report', ['course_id' => $c]);
        $made['report_version'] = $this->row('local_assistente_report_version', [
            'report_id' => $report, 'version' => 1, 'snapshot_json' => '{"analysis_ids":[1],"nota":"Sôuza, Maria e João Lima","versao":2}',
        ]);
        $this->made[$c] = $made;
    }

    /**
     * A digest of every table of the plugin, to tell later that nothing changed.
     *
     * @return string
     */
    private function digest(): string {
        global $DB;
        $all = [];
        foreach (\local_assistente\infrastructure\persistence\course_erasure_repository::TABLES as $table) {
            $all[$table] = array_values($DB->get_records($table, null, 'id ASC'));
        }
        return md5(json_encode($all));
    }

    /**
     * The counts of rows of every table of the plugin.
     *
     * @return array<string, int>
     */
    private function counts(): array {
        global $DB;
        $counts = [];
        foreach (\local_assistente\infrastructure\persistence\course_erasure_repository::TABLES as $table) {
            $counts[$table] = $DB->count_records($table);
        }
        return $counts;
    }

    /**
     * An approved list of contexts, as the privacy manager hands it to the provider.
     *
     * @param \stdClass $user
     * @param \stdClass[] $courses
     * @return approved_contextlist
     */
    private function approved(\stdClass $user, array $courses): approved_contextlist {
        return new approved_contextlist($user, 'local_assistente', array_map(fn($c) => \context_course::instance($c->id)->id, $courses));
    }

    /**
     * The decoded snapshot of a processing.
     *
     * @param int $processing
     * @return array
     */
    private function snapshot_of(int $processing): array {
        return json_decode($this->events->find_context($processing)->context_json, true);
    }

    /**
     * The payload of an event.
     *
     * @param int $eventid
     * @return array
     */
    private function payload(int $eventid): array {
        global $DB;
        return json_decode($DB->get_field('local_assistente_event', 'payload', ['id' => $eventid]), true);
    }

    /**
     * T137: OpenRouter is declared as the external location, with what is sent; the ZDR policy and the operational metadata are
     * described in both languages without claiming that zero retention was validated; no identifier of a student is declared.
     */
    public function test_openrouter_is_declared_as_the_external_location(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_assistente'));
        $locations = array_values(array_filter($collection->get_collection(),
            fn($item) => $item instanceof \core_privacy\local\metadata\types\external_location));
        $this->assertCount(1, $locations);
        $this->assertSame('openrouter', $locations[0]->get_name());
        $this->assertSame(['model', 'context', 'instruction'], array_keys($locations[0]->get_privacy_fields()));

        $text = get_string('privacy:metadata:openrouter', 'local_assistente');
        $this->assertStringContainsString('OpenRouter', $text);
        $this->assertStringContainsString('Zero Data Retention', $text);
        $this->assertStringContainsString('not been validated', $text);
        $this->assertStringContainsString('operational metadata', $text);
        $this->assertStringContainsString('can turn this request off', $text);
        $portuguese = $this->portuguese()['privacy:metadata:openrouter'];
        $this->assertStringContainsString('não foi validado', $portuguese);
        $this->assertStringContainsString('metadados operacionais', $portuguese);
        $this->assertStringContainsString('pode desligar esse pedido', $portuguese);
        $this->assertDoesNotMatchRegularExpression('/zero retention (is|was) (guaranteed|validated)|retenção zero (é|foi) (garantida|validada)/i', $text . $portuguese);
        foreach (['student_id', 'userid', 'email', 'name'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $locations[0]->get_privacy_fields());
        }
    }

    /**
     * The metadata is complete: the manager of Moodle accepts the plugin as compliant, every table and field that holds personal data has its
     * string in both languages, the tables are those of the schema, and the language model is declared as an external location.
     */
    public function test_the_metadata_is_complete(): void {
        global $DB;
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('local_assistente'));
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_assistente'));
        $items = $collection->get_collection();
        $names = array_map(fn($i) => $i->get_name(), $items);
        $this->assertContains('openrouter', $names);
        foreach (provider::TABLES as $table => $fields) {
            $this->assertContains($table, $names);
            $this->assertTrue($DB->get_manager()->table_exists($table), $table);
            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $DB->get_columns($table), "{$table}.{$field}");
            }
        }
        $strings = get_string_manager();
        foreach ($items as $item) {
            $this->assertTrue($strings->string_exists($item->get_summary(), 'local_assistente'), $item->get_summary());
            foreach ($item->get_privacy_fields() as $string) {
                $this->assertTrue($strings->string_exists($string, 'local_assistente'), $string);
            }
        }
        // The same strings exist in Portuguese.
        $portuguese = $this->portuguese();
        foreach ($items as $item) {
            $this->assertArrayHasKey($item->get_summary(), $portuguese);
            foreach ($item->get_privacy_fields() as $field) {
                $this->assertArrayHasKey($field, $portuguese);
            }
        }
    }

    /**
     * The strings of the Portuguese language file.
     *
     * @return array
     */
    private function portuguese(): array {
        $string = [];
        include(__DIR__ . '/../../lang/pt_br/local_assistente.php');
        return $string;
    }

    /**
     * The contexts of a student are the courses where the plugin holds something of the student, and no other; a user with nothing has none; a
     * user who has only a pending event is included, because the event names the user.
     */
    public function test_the_contexts_of_a_student(): void {
        $own = fn(\stdClass $user) => array_map('intval', provider::get_contexts_for_userid((int) $user->id)->get_contextids());
        $a = \context_course::instance($this->courses['A']->id)->id;
        $b = \context_course::instance($this->courses['B']->id)->id;
        $only = $this->getDataGenerator()->create_user();
        $this->events->create_event((int) $this->courses['C']->id, event_type::GRADE_CHANGED, ['userid' => (int) $only->id, 'itemid' => 1, 'occurred_at' => 1], time());

        $this->assertEqualsCanonicalizing([$a, $b], $own($this->maria));
        $this->assertEqualsCanonicalizing([$a, $b], $own($this->joao));
        $this->assertSame([\context_course::instance($this->courses['C']->id)->id], $own($only));
        $this->assertSame([], $own($this->getDataGenerator()->create_user()));
    }

    /**
     * The users of a course context are the students with something in it; other levels have none.
     */
    public function test_the_users_in_a_context(): void {
        $list = function (\context $context) {
            $userlist = new userlist($context, 'local_assistente');
            provider::get_users_in_context($userlist);
            $ids = array_map('intval', $userlist->get_userids());
            sort($ids);
            return $ids;
        };
        $expected = [(int) $this->maria->id, (int) $this->joao->id];
        sort($expected);

        $this->assertSame($expected, $list(\context_course::instance($this->courses['A']->id)));
        $this->assertSame([], $list(\context_course::instance($this->courses['C']->id)));
        $this->assertSame([], $list(\context_system::instance()));
    }

    /**
     * Q7: the export brings, in each course context asked, the individual results of the student and the entries of the student in the
     * snapshots, and nothing of the other student, of another course, or of the texts.
     */
    public function test_the_export_has_the_individual_data_by_course(): void {
        $a = \context_course::instance($this->courses['A']->id);
        $c = \context_course::instance($this->courses['C']->id);

        $this->export_context_data_for_user((int) $this->maria->id, $a, 'local_assistente');

        $writer = writer::with_context($a);
        $this->assertTrue($writer->has_any_data());
        $path = [get_string('privacy:path', 'local_assistente')];
        $performance = $writer->get_data(array_merge($path, [get_string('privacy:path:performance', 'local_assistente')]));
        $frequency = $writer->get_data(array_merge($path, [get_string('privacy:path:frequency', 'local_assistente')]));
        $criteria = $writer->get_data(array_merge($path, [get_string('privacy:path:criteria', 'local_assistente')]));
        $snapshots = $writer->get_data(array_merge($path, [get_string('privacy:path:snapshots', 'local_assistente')]));
        $this->assertCount(1, $performance->records);
        $this->assertEquals(80.5, $performance->records[0]['grade']);
        $this->assertCount(1, $frequency->records);
        $this->assertCount(1, $frequency->records[0]['activities']);
        $this->assertCount(2, $criteria->records, 'The individual and the course analysis');
        $json = json_encode([$performance, $frequency, $criteria, $snapshots]);
        $this->assertStringNotContainsString('"student_id":' . $this->joao->id, $json);
        $this->assertStringNotContainsString('Resumo do material', $json, 'Texts are not exported');
        $kinds = array_column($snapshots->records, 'kind');
        $this->assertEqualsCanonicalizing(['grade_performance', 'frequency', 'criteria_check', 'criteria_check'], $kinds);
        $this->assertFalse(writer::with_context($c)->has_any_data(), 'A course with nothing exports nothing');
        $this->assertFalse(writer::with_context($a)->get_data(['Outro caminho']) !== [], 'No other path');
    }

    /**
     * The export of a user with nothing is empty, and the export of all the contexts has one block for each course.
     */
    public function test_export_for_a_user_without_data_and_for_all_contexts(): void {
        $nobody = $this->getDataGenerator()->create_user();
        $this->export_all_data_for_user((int) $nobody->id, 'local_assistente');
        $this->assertFalse(writer::with_context(\context_course::instance($this->courses['A']->id))->has_any_data());

        $this->export_all_data_for_user((int) $this->maria->id, 'local_assistente');

        $this->assertTrue(writer::with_context(\context_course::instance($this->courses['A']->id))->has_any_data());
        $this->assertTrue(writer::with_context(\context_course::instance($this->courses['B']->id))->has_any_data());
    }

    /**
     * Q8: the deletion for the contexts asked deletes the individual results, neutralizes the events, takes the entries out of the snapshots,
     * redacts the name and neutralizes the identifier in messages, in those courses only, and keeps the ids, versions, events, attempts and
     * analyses and everything of the other student.
     */
    public function test_deleting_a_student_in_one_course_changes_that_course_only(): void {
        global $DB;
        $m = (int) $this->maria->id;
        $j = (int) $this->joao->id;
        $a = $this->made[(int) $this->courses['A']->id];
        $b = $this->made[(int) $this->courses['B']->id];
        $counts = $this->counts();

        provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A']]));

        // Individual results of Maria in A are gone; in B, and João's, are not.
        $this->assertSame(0, $DB->count_records_sql('SELECT COUNT(1) FROM {local_assistente_performance} p JOIN {local_assistente_analysis} a ON a.id = p.analysis_id WHERE p.student_id = ? AND a.course_id = ?', [$m, $this->courses['A']->id]));
        $this->assertSame(1, $DB->count_records_sql('SELECT COUNT(1) FROM {local_assistente_performance} p JOIN {local_assistente_analysis} a ON a.id = p.analysis_id WHERE p.student_id = ? AND a.course_id = ?', [$m, $this->courses['B']->id]));
        $this->assertSame(2, $DB->count_records('local_assistente_performance', ['student_id' => $j]));
        // Ids, versions, events, attempts and analyses are all there; only the individual rows went.
        $after = $this->counts();
        foreach ($after as $table => $n) {
            if (!in_array($table, ['local_assistente_performance', 'local_assistente_frequency', 'local_assistente_frequency_activity', 'local_assistente_criteria_result'], true)) {
                $this->assertSame($counts[$table], $n, $table);
            }
        }
        // Events: those of Maria in A are neutral, the pending one included; in B and João's are not.
        $this->assertSame(0, $this->payload($a['grade'][$m]['event'])['userid']);
        $this->assertSame(0, $this->payload($a['pending']['event'])['userid']);
        $this->assertSame(erased_subject::IDENTITY, $this->payload($a['grade'][$m]['event'])['identity']);
        $this->assertSame($m, $this->payload($b['grade'][$m]['event'])['userid']);
        $this->assertSame($j, $this->payload($a['grade'][$j]['event'])['userid']);
        $this->assertSame(0, $this->payload($a['quiz'][$m]['event'])['userid']);
        $this->assertSame(100 + $m + $this->courses['A']->id, $this->payload($a['quiz'][$m]['event'])['attemptid'], 'The Moodle attempt stays');
        // Texts: redacted in A, intact in B; João's name stays.
        $this->assertSame('A aluna [removido] e o colega João Lima discutiram o prazo.', $DB->get_field('local_assistente_analysis', 'summary', ['id' => $a['forum']['analysis']]));
        $this->assertStringContainsString('Maria Souza', $DB->get_field('local_assistente_analysis', 'summary', ['id' => $b['forum']['analysis']]));
        $this->assertSame('Dúvida de [removido] sobre o prazo; [removido] não entendeu.', $DB->get_field('local_assistente_forum_difficulty', 'description', ['id' => $a['forum']['difficulty']]));
        $this->assertSame('[removido] foi a que mais participou. [removido], [removido] e Mariana.', $DB->get_field('local_assistente_pedagogical_synthesis', 'content', ['id' => $a['synthesis']['row']]));
        $this->assertSame('Material usado por [removido]', $DB->get_field('local_assistente_material_version', 'summary', ['id' => $a['material_version']]));
        $report = json_decode($DB->get_field('local_assistente_report_version', 'snapshot_json', ['id' => $a['report_version']]), true);
        $this->assertSame(['analysis_ids' => [1], 'nota' => '[removido], [removido] e João Lima', 'versao' => 2], $report);
        $this->assertSame('Material usado por Maria Souza', $DB->get_field('local_assistente_material_version', 'summary', ['id' => $b['material_version']]));
        // The version and the links stay.
        $this->assertSame(1, (int) $DB->get_field('local_assistente_pedagogical_synthesis', 'version', ['id' => $a['synthesis']['row']]));
        $this->assertSame(str_repeat('a', 64), $DB->get_field('local_assistente_pedagogical_synthesis', 'input_fingerprint', ['id' => $a['synthesis']['row']]));
        $this->assertCount(1, $this->traced);
        $this->assertStringNotContainsString((string) $m, preg_replace('/courses \d+/', '', $this->traced[0]), 'The line of counts does not name the student');
    }

    /**
     * Deleting in both courses leaves nothing of the student and everything of the other.
     */
    public function test_deleting_in_every_course_leaves_nothing_individual(): void {
        global $DB;
        $m = (int) $this->maria->id;

        provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A'], $this->courses['B'], $this->courses['C']]));

        $this->assertSame(0, $DB->count_records('local_assistente_performance', ['student_id' => $m]));
        $this->assertSame(0, $DB->count_records('local_assistente_frequency', ['student_id' => $m]));
        $this->assertSame(0, $DB->count_records('local_assistente_criteria_result', ['student_id' => $m]));
        $this->assertSame([], (new \local_assistente\infrastructure\persistence\student_data_repository())->find_events($m));
        $this->assertSame([], (new \local_assistente\infrastructure\persistence\student_data_repository())->course_ids_with_data($m));
        $this->assertSame(2, $DB->count_records('local_assistente_performance', ['student_id' => $this->joao->id]));
        foreach ($DB->get_records('local_assistente_pedagogical_synthesis') as $row) {
            $this->assertStringNotContainsString('Maria Souza', $row->content);
        }
        $this->assertDoesNotMatchRegularExpression('/"student_id":' . $m . '\b/', implode('', $DB->get_fieldset_select('local_assistente_processing_context', 'context_json', '1 = 1')));
    }

    /**
     * Only the entries of the student leave the snapshots: the list of the other student keeps its entry, the individual snapshots lose the
     * identifier and the individual values and keep their shape, and a snapshot that holds a shared text is not touched.
     */
    public function test_the_snapshots_lose_only_the_entries_of_the_student(): void {
        $a = $this->made[(int) $this->courses['A']->id];
        $m = (int) $this->maria->id;
        $j = (int) $this->joao->id;
        $material = $this->snapshot_of($a['forum']['processing']);
        $before = $this->snapshot_of($a['grade'][$j]['processing']);

        provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A']]));

        $frequency = $this->snapshot_of($a['frequency']['processing']);
        $this->assertSame([$j], array_column($frequency['course_access'], 'student_id'));
        $this->assertSame([7], $frequency['activities_without_date']);
        $this->assertSame(['format', 'kind', 'calculated_at', 'course_access', 'activities_without_date', 'course_id'], array_keys($frequency), 'The shape and the order stay');
        $performance = $this->snapshot_of($a['grade'][$m]['processing']);
        $this->assertSame(['format' => 1, 'kind' => 'grade_performance', 'course_id' => (int) $this->courses['A']->id], $performance);
        $criteria = $this->snapshot_of($a['criteria_individual']['processing']);
        $this->assertSame(['format', 'kind', 'evaluated_at', 'limits', 'course_id'], array_keys($criteria));
        $course = $this->snapshot_of($a['criteria_course']['processing']);
        $this->assertSame([$j], array_column($course['students'], 'student_id'));
        $this->assertEquals(70.0, $course['students'][0]['activity_value']);
        $this->assertSame($before, $this->snapshot_of($a['grade'][$j]['processing']), 'The snapshot of the other student is untouched');
        $this->assertSame($material, $this->snapshot_of($a['forum']['processing']), 'A shared snapshot is not redacted: the name stays in it (DEC-56 does not list it)');
        $this->assertStringContainsString('Maria Souza', $this->snapshot_of($a['forum']['processing'])['materials'][0]['summary']);
    }

    /**
     * The identifier of the student is neutralized in the messages that carry it, and only there: the number goes to 0, the rest of the message,
     * the messages of other students, the messages without a student, and the ids of events and attempts stay.
     */
    public function test_messages_with_and_without_the_identifier(): void {
        global $DB;
        $a = $this->made[(int) $this->courses['A']->id];
        $b = $this->made[(int) $this->courses['B']->id];
        $m = (int) $this->maria->id;
        $j = (int) $this->joao->id;
        $plain = $DB->get_field('local_assistente_event', 'response', ['id' => $a['plain_failure']['event']]);

        provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A']]));

        $expected = 'technical: local_assistente\infrastructure\persistence\invalid_domain_value_exception: The student 0 does not exist.';
        $this->assertSame($expected, $DB->get_field('local_assistente_event', 'response', ['id' => $a['failed'][$m]['event']]));
        foreach ($this->events->find_attempts($a['failed'][$m]['processing']) as $attempt) {
            $this->assertSame($expected, $attempt->response);
        }
        $this->assertStringContainsString("The student {$j} does not exist.", $DB->get_field('local_assistente_event', 'response', ['id' => $a['failed'][$j]['event']]));
        $this->assertStringContainsString("The student {$m} does not exist.", $DB->get_field('local_assistente_event', 'response', ['id' => $b['failed'][$m]['event']]), 'Another course');
        $this->assertSame($plain, $DB->get_field('local_assistente_event', 'response', ['id' => $a['plain_failure']['event']]));
        $this->assertSame('failure', $DB->get_field('local_assistente_event', 'status', ['id' => $a['failed'][$m]['event']]));
        $this->assertCount(3, $this->events->find_attempts($a['failed'][$m]['processing']), 'The attempts stay');
    }

    /**
     * Deleting for all the users of a context deletes every student of that course and no other course, and a context that has nothing is a
     * no-op; the other levels of context hold nothing of the plugin.
     */
    public function test_deleting_for_a_context(): void {
        global $DB;
        $before = $this->digest();

        provider::delete_data_for_all_users_in_context(\context_course::instance($this->courses['C']->id));
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame($before, $this->digest(), 'A context with nothing changes nothing');

        provider::delete_data_for_all_users_in_context(\context_course::instance($this->courses['A']->id));

        $this->assertSame(2, $DB->count_records('local_assistente_performance'), 'Only the results of course B remain');
        $this->assertSame(2, $DB->count_records_sql('SELECT COUNT(1) FROM {local_assistente_performance} p JOIN {local_assistente_analysis} a ON a.id = p.analysis_id WHERE a.course_id = ?', [$this->courses['B']->id]));
        $this->assertCount(2, $this->traced, 'One line for each student');
    }

    /**
     * Deleting for a list of users deletes those users in that context and nobody else.
     */
    public function test_deleting_for_a_list_of_users(): void {
        global $DB;
        $context = \context_course::instance($this->courses['A']->id);
        $userlist = new approved_userlist($context, 'local_assistente', [(int) $this->joao->id]);

        provider::delete_data_for_users($userlist);

        $this->assertSame(1, $DB->count_records_sql('SELECT COUNT(1) FROM {local_assistente_performance} p JOIN {local_assistente_analysis} a ON a.id = p.analysis_id WHERE p.student_id = ? AND a.course_id = ?', [$this->joao->id, $this->courses['B']->id]));
        $this->assertSame(0, $DB->count_records_sql('SELECT COUNT(1) FROM {local_assistente_performance} p JOIN {local_assistente_analysis} a ON a.id = p.analysis_id WHERE p.student_id = ? AND a.course_id = ?', [$this->joao->id, $this->courses['A']->id]));
        $this->assertSame(1, $DB->count_records_sql('SELECT COUNT(1) FROM {local_assistente_performance} p JOIN {local_assistente_analysis} a ON a.id = p.analysis_id WHERE p.student_id = ? AND a.course_id = ?', [$this->maria->id, $this->courses['A']->id]));
        provider::delete_data_for_users(new approved_userlist(\context_system::instance(), 'local_assistente', [(int) $this->maria->id]));
        $this->assertSame(1, $DB->count_records_sql('SELECT COUNT(1) FROM {local_assistente_performance} p JOIN {local_assistente_analysis} a ON a.id = p.analysis_id WHERE p.student_id = ? AND a.course_id = ?', [$this->maria->id, $this->courses['A']->id]), 'A context that is not a course holds nothing');
    }

    /**
     * Repeating the deletion changes nothing and finds nothing.
     */
    public function test_repeating_the_deletion_changes_nothing(): void {
        provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A'], $this->courses['B']]));
        $after = $this->digest();
        $this->traced = [];

        provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A'], $this->courses['B']]));

        $this->assertSame($after, $this->digest());
        $this->assertCount(1, $this->traced);
        $this->assertStringContainsString('events neutralized 0, individual rows 0, snapshots 0, texts redacted 0, messages 0', $this->traced[0]);
    }

    /**
     * Everything or nothing: a failure in the middle (a trigger refuses the update of a synthesis, which comes after the deletion of the
     * results, the neutralization of the events and the work on the snapshots) undoes all of it, and the request works when repeated.
     */
    public function test_a_failure_in_the_middle_undoes_everything(): void {
        global $DB;
        $before = $this->digest();
        $table = $DB->get_prefix() . 'local_assistente_pedagogical_synthesis';
        try {
            // One statement only (Moodle refuses several).
            $DB->execute("CREATE TRIGGER t104_refuse BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused'");
        } catch (\dml_exception $e) {
            $this->markTestSkipped('The test user cannot create triggers: ' . $e->getMessage());
        }
        try {
            provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A'], $this->courses['B']]));
            $failed = false;
        } catch (\dml_exception) {
            $failed = true;
        } finally {
            $DB->execute('DROP TRIGGER IF EXISTS t104_refuse');
        }

        $this->assertTrue($failed, 'The trigger must have refused the update of the synthesis');
        $this->assertSame($before, $this->digest(), 'Nothing changed: rows, events, snapshots, texts');
        $this->assertSame([], $this->traced, 'And nothing was recorded');

        provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A'], $this->courses['B']]));
        $this->assertSame(0, $DB->count_records('local_assistente_performance', ['student_id' => $this->maria->id]));
    }

    /**
     * Compatibility with the synchronization of the events: the pending events of the student are neutralized and the queue finishes them as
     * events of an erased subject, with the three handlers; a lock held by a running handler makes the request fail with nothing changed.
     */
    public function test_compatibility_with_the_queue_and_the_locks(): void {
        global $DB;
        $a = $this->made[(int) $this->courses['A']->id];
        $held = unit_of_work::lock_factory()->get_lock(unit_of_work::event_resource($a['pending']['event']), 1);
        $this->assertNotFalse($held);
        $before = $this->digest();

        try {
            provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A']]));
            $this->fail('The deletion went on over a locked event');
        } catch (erasure_pending_exception $e) {
            // The request is told, in fixed words and counts, that a retry was scheduled (T142); nothing was changed.
            $this->assertSame([1, 0], [$e->deferred(), $e->incomplete()]);
            $this->assertInstanceOf(lock_unavailable_exception::class, $e->getPrevious());
            $this->assertStringNotContainsString('Maria', $e->getMessage());
            $this->assertStringNotContainsString((string) $this->maria->id, $e->getMessage());
            $this->assertSame($before, $this->digest());
            $tasks = \core\task\manager::get_adhoc_tasks(\local_assistente\task\retry_student_erasure::class);
            $this->assertCount(1, $tasks);
            $this->assertSame(\local_assistente\application\privacy\erasure_retry_scheduler::ATTEMPTS, reset($tasks)->get_attempts_available());
        } finally {
            $held->release();
        }

        (new \local_assistente\infrastructure\persistence\config_repository())->create($this->courses['A']->id, true);
        provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A']]));
        (new \local_assistente\application\queue\queue_processor(null, \local_assistente\application\queue\handler_registry::default()))->run_cycle();

        $this->assertSame('processed', $DB->get_field('local_assistente_event', 'status', ['id' => $a['pending']['event']]));
        $attempts = $this->events->find_attempts((int) $this->events->find_processing($a['pending']['event'])->id);
        $this->assertSame(erased_subject::RESPONSE, $attempts[0]->response);
        $this->assertSame(0, $DB->count_records_sql('SELECT COUNT(1) FROM {local_assistente_performance} p JOIN {local_assistente_analysis} a ON a.id = p.analysis_id WHERE p.student_id = ? AND a.course_id = ?', [$this->maria->id, $this->courses['A']->id]));
    }

    /**
     * The history stays readable after the deletion: the lineage of the analyses of the student is still reconstructed, without the user.
     */
    public function test_the_lineage_survives_the_deletion(): void {
        $a = $this->made[(int) $this->courses['A']->id];
        $reader = new \local_assistente\application\history\lineage_reader();

        provider::delete_data_for_user($this->approved($this->maria, [$this->courses['A']]));

        $lineage = $reader->trace_analysis($a['grade'][$this->maria->id]['analysis']);
        $this->assertNotNull($lineage);
        $this->assertNotContains('user', array_column($lineage->sources, 'type'));
        $this->assertNotEmpty($lineage->attempts);
        $this->assertSame([$a['grade'][$this->maria->id]['analysis']], array_map(fn($x) => (int) $x->id, $reader->history($a['grade'][$this->maria->id]['analysis'])));
        $other = $reader->history($a['grade'][$this->joao->id]['analysis']);
        $this->assertSame([$a['grade'][$this->joao->id]['analysis']], array_map(fn($x) => (int) $x->id, $other));
    }
}
