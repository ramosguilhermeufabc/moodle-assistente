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

use local_assistente\application\course_config_service;
use local_assistente\application\history\lineage;
use local_assistente\application\history\source_status_resolver;
use local_assistente\domain\criteria\assistant_state;
use local_assistente\domain\criteria_status;
use local_assistente\domain\result_state;
use local_assistente\domain\source_status;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the presentation of the results of the queries (T107; quickstart 018 Q9 to Q12; DEC-02, DEC-24, DEC-35 D2, DEC-36 D2,
 * DEC-39 D3, DEC-40, DEC-42 D3, DEC-45 D3). Synthetic data only.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(presentation::class)]
final class presentation_test extends \advanced_testcase {
    /** @var presentation the presentation with the Portuguese strings of the plugin */
    private presentation $view;

    /**
     * The Portuguese pack of the plugin, so the labels are those of the contract whatever the language of the test site.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $strings = $this->pack('pt_br');
        $this->view = new presentation(',', fn(string $key): string => $strings[$key], fn(int $time): string => gmdate('d/m/Y', $time));
    }

    /**
     * The strings of a language pack of the plugin.
     *
     * @param string $lang
     * @return array
     */
    private function pack(string $lang): array {
        $string = [];
        include(__DIR__ . "/../../../lang/{$lang}/local_assistente.php");
        return $string;
    }

    /**
     * Q11: 66.666 is shown "66,66%" and every value is truncated, never rounded, so a value that rounding would take up stays down.
     */
    public function test_percentages_are_truncated_to_two_decimals(): void {
        $cases = [
            [66.666, '66,66%'], ['66.666', '66,66%'], [66.669, '66,66%'], [33.333, '33,33%'], [0.999, '0,99%'], [99.999, '99,99%'],
            [0.005, '0,00%'], [0.004, '0,00%'], [12.5, '12,50%'], [50, '50,00%'], [1 / 3 * 100, '33,33%'], [2 / 3 * 100, '66,66%'],
            [0.29 * 100, '29,00%'], [0.57 * 100, '57,00%'], [99.995, '99,99%'],
        ];
        foreach ($cases as [$value, $expected]) {
            $this->assertSame($expected, $this->view->percentage($value), var_export($value, true));
        }
    }

    /**
     * The limits and the zero of the domain: 0 and 100 are shown as they are, and minus zero is zero.
     */
    public function test_zero_and_the_limits(): void {
        $this->assertSame('0,00%', $this->view->percentage(0));
        $this->assertSame('0,00%', $this->view->percentage(0.0));
        $this->assertSame('0,00%', $this->view->percentage(-0.0));
        $this->assertSame('0,00%', $this->view->percentage('0.000'));
        $this->assertSame('100,00%', $this->view->percentage(100));
        $this->assertSame('100,00%', $this->view->percentage(100.0));
        $this->assertSame('0,01%', $this->view->percentage(0.01));
        $this->assertSame('99,99%', $this->view->percentage(99.999));
    }

    /**
     * What is outside 0 to 100, not finite or not a number is not a percentage of any contract and is refused, not shown clipped or as zero.
     */
    public function test_values_outside_the_domain_are_refused(): void {
        foreach ([-0.01, -1, -66.666, 100.01, 101, NAN, INF, -INF, 'abc', ''] as $bad) {
            try {
                $this->view->percentage($bad);
                $this->fail('Accepted ' . var_export($bad, true));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * The decimal separator is a parameter; the default is the comma of the contract.
     */
    public function test_the_decimal_separator(): void {
        $this->assertSame('66,66%', (new presentation())->percentage(66.666), 'Default');
        $this->assertSame('66.66%', (new presentation('.'))->percentage(66.666));
    }

    /**
     * Q10 and DEC-24: no data and technical error are never zero; a real zero is zero.
     */
    public function test_states_are_never_shown_as_zero(): void {
        $this->assertSame('66,66%', $this->view->value(result_state::VALID, 66.666));
        $this->assertSame('0,00%', $this->view->value(result_state::VALID, 0.0), 'A real zero stays zero');
        $this->assertSame('sem dados', $this->view->value(result_state::NO_DATA, 0.0), 'The schema keeps 0 with no data');
        $this->assertSame('sem dados', $this->view->value(result_state::NO_DATA, null));
        $this->assertSame('erro técnico', $this->view->value(result_state::ERROR, 0.0));
        $this->assertSame('erro técnico', $this->view->value(result_state::ERROR, null));
        $this->expectException(\InvalidArgumentException::class);
        $this->view->value(result_state::VALID, null);
    }

    /**
     * Every state of the enum has a label, so a state added later fails here instead of being shown wrong.
     */
    public function test_every_state_has_a_text(): void {
        foreach (result_state::cases() as $state) {
            $text = $this->view->value($state, 10.0);
            $this->assertNotSame('', $text);
            $this->assertSame($state === result_state::VALID, str_ends_with($text, '%'), $state->value);
        }
    }

    /**
     * Q10: the status of a criterion; 2 is "sem dados", not "abaixo".
     */
    public function test_the_status_of_a_criterion(): void {
        $this->assertSame('atende', $this->view->criteria(criteria_status::MEETS));
        $this->assertSame('abaixo', $this->view->criteria(criteria_status::BELOW));
        $this->assertSame('sem dados', $this->view->criteria(criteria_status::from(2)));
    }

    /**
     * Q9 and DEC-35 D2: "sem materiais" only for a coverage of 0.0 of an analysis that had no materials; the 0.0 of an analysis with
     * materials is a real zero. The coverage is on the scale 0.0 to 1.0 of spec-010, not a percentage (0.7 is "0,7", not "0,70%").
     */
    public function test_no_materials_only_when_there_were_none(): void {
        $this->assertSame('sem materiais', $this->view->coverage(result_state::VALID, 0.0, false));
        $this->assertSame('sem materiais', $this->view->coverage(result_state::VALID, 0, false));
        $this->assertSame('sem materiais', $this->view->coverage(result_state::VALID, '0.0', false));
        $this->assertSame('0,0', $this->view->coverage(result_state::VALID, 0.0, true), 'A real zero with materials');
        $this->assertSame('0,0', $this->view->coverage(result_state::VALID, 0.04, false), 'Not exactly 0.0');
        $this->assertSame('0,7', $this->view->coverage(result_state::VALID, 0.7, true));
        $this->assertSame('0,7', $this->view->coverage(result_state::VALID, 0.7, false), 'Only zero means "sem materiais"');
        $this->assertSame('1,0', $this->view->coverage(result_state::VALID, 1.0, true));
        $this->assertSame('sem dados', $this->view->coverage(result_state::NO_DATA, 0.0, false), 'The state comes first');
        $this->assertSame('erro técnico', $this->view->coverage(result_state::ERROR, 0.0, false), 'The state comes first');
        $this->assertSame('sem dados', $this->view->coverage(result_state::NO_DATA, 0.0, true));
    }

    /**
     * The scale 0.0 to 1.0 of the coverage and of the complexity: one decimal, truncated, never rounded, with the separator; outside the
     * scale, not finite or not a number is refused, not shown clipped or as zero.
     */
    public function test_the_scale_of_the_coverage_and_the_complexity(): void {
        foreach ([[0.0, '0,0'], [0.1, '0,1'], [0.3, '0,3'], [0.7, '0,7'], [0.99, '0,9'], [1.0, '1,0'], ['0.5', '0,5'], [0.1 + 0.2, '0,3']] as [$value, $expected]) {
            $this->assertSame($expected, $this->view->scale($value), var_export($value, true));
        }
        $this->assertSame('0.7', (new presentation('.'))->scale(0.7));
        foreach ([-0.1, 1.01, 2, 70, NAN, INF, 'abc', ''] as $bad) {
            try {
                $this->view->scale($bad);
                $this->fail('Accepted ' . var_export($bad, true));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('0,4', $this->view->complexity(result_state::VALID, 0.4));
        $this->assertSame('0,0', $this->view->complexity(result_state::VALID, 0.0), 'A real zero is zero');
        $this->assertSame('sem dados', $this->view->complexity(result_state::NO_DATA, 0.0));
        $this->assertSame('erro técnico', $this->view->complexity(result_state::ERROR, null));
    }

    /**
     * DEC-39 D3: the access to the course is shown next to the result and never in its place, whatever the state.
     */
    public function test_the_access_is_complementary_evidence(): void {
        $this->assertNull($this->view->course_access(null), 'Unknown shows nothing');
        $this->assertNull($this->view->course_access(null, 1700000000));
        $this->assertSame('não acessou o curso', $this->view->course_access(false));
        $this->assertSame('não acessou o curso', $this->view->course_access(false, 1700000000), 'No access has no last access');
        $this->assertSame('acessou o curso', $this->view->course_access(true));
        $this->assertSame('acessou o curso (último acesso: 14/11/2023)', $this->view->course_access(true, 1700000000));

        $this->assertSame(['main' => 'sem dados', 'complement' => 'acessou o curso'], $this->view->result(result_state::NO_DATA, 0.0, true));
        $this->assertSame(['main' => '0,00%', 'complement' => 'não acessou o curso'], $this->view->result(result_state::VALID, 0.0, false));
        $this->assertSame(['main' => '50,00%', 'complement' => null], $this->view->result(result_state::VALID, 50.0));
        $this->assertSame(['main' => 'erro técnico', 'complement' => null], $this->view->result(result_state::ERROR, null));
    }

    /**
     * Q12 and DEC-42 D3: with the resolver of T102 and a real removal, the removed source is marked and the one that exists is not; the
     * history is not changed.
     */
    public function test_removed_sources_come_from_the_resolver(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $kept = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $gone = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        (new \core_courseformat\local\cmactions(get_course($course->id)))->delete((int) $gone->cmid);
        $lineage = new lineage((object) ['course_id' => $course->id], new \stdClass(), new \stdClass(), [], [], null, [], [
            ['type' => 'course_module', 'id' => (int) $kept->cmid],
            ['type' => 'course_module', 'id' => (int) $gone->cmid],
        ]);
        $resolved = (new source_status_resolver())->resolve($lineage);
        $before = $DB->count_records('course_modules');

        $marked = $this->view->annotate_sources($resolved);

        $this->assertSame([null, 'fonte removida'], array_column($marked, 'note'));
        $this->assertSame([(int) $kept->cmid, (int) $gone->cmid], array_column($marked, 'id'));
        $this->assertSame(source_status::EXISTS, $marked[0]['status']);
        $this->assertSame(source_status::REMOVED, $marked[1]['status']);
        $this->assertNull($this->view->source_note(source_status::EXISTS));
        $this->assertSame('fonte removida', $this->view->source_note(source_status::REMOVED));
        $this->assertSame($before, $DB->count_records('course_modules'));
    }

    /**
     * DEC-45 D3 with the real configuration service of T040: a course without configuration and one disabled say "assistente não
     * habilitado", show no results and do not even run the query; the enabled one shows them.
     */
    public function test_a_course_without_the_assistant_enabled_shows_no_results(): void {
        $this->setAdminUser();
        $service = new course_config_service();
        $none = $this->getDataGenerator()->create_course();
        $off = $this->getDataGenerator()->create_course();
        $on = $this->getDataGenerator()->create_course();
        (new config_repository())->create((int) $off->id, false);
        (new config_repository())->create((int) $on->id, true);
        $runs = 0;
        $query = function () use (&$runs): array {
            $runs++;
            return [['name' => 'Ana']];
        };

        $withoutconfig = $this->view->results($service->state((int) $none->id), $query);
        $disabled = $this->view->results($service->state((int) $off->id), $query);
        $this->assertSame(['available' => false, 'message' => 'assistente não habilitado', 'rows' => []], $withoutconfig);
        $this->assertSame($withoutconfig, $disabled, 'The two cases behave the same');
        $this->assertSame(0, $runs, 'The query is not run');

        $enabled = $this->view->results($service->state((int) $on->id), $query);
        $this->assertSame(['available' => true, 'message' => null, 'rows' => [['name' => 'Ana']]], $enabled);
        $this->assertSame(1, $runs);
        foreach ([assistant_state::NOT_CONFIGURED, assistant_state::DISABLED] as $state) {
            $this->assertSame('assistente não habilitado', $this->view->unavailable($state));
        }
        $this->assertNull($this->view->unavailable(assistant_state::ENABLED));
    }

    /**
     * DEC-02: names from A to Z, without difference of case or accent, Amanda before Carlos; the same names go by id and then by position.
     */
    public function test_names_are_ordered_from_a_to_z_with_a_stable_tie_break(): void {
        $rows = [
            ['name' => 'Carlos', 'id' => 4], ['name' => 'álvaro', 'id' => 3], ['name' => 'Amanda', 'id' => 2], ['name' => 'alice', 'id' => 1],
            ['name' => 'Zélia', 'id' => 6], ['name' => 'Bruno', 'id' => 5],
        ];

        $names = array_column($this->view->sort_by_name($rows), 'name');

        $this->assertSame(['alice', 'álvaro', 'Amanda', 'Bruno', 'Carlos', 'Zélia'], $names);
        $same = [['name' => 'Ana', 'id' => 9], ['name' => 'Ana', 'id' => 3], ['name' => 'ANA', 'id' => 7], ['name' => 'Ana']];
        $this->assertSame([0, 3, 9, 7], array_map(fn($r) => $r['id'] ?? 0, $this->view->sort_by_name($same)), 'The exact text first (Ana before ANA), then the id');
        $nodid = [['name' => 'Ana', 'tag' => 'first'], ['name' => 'Ana', 'tag' => 'second'], ['name' => 'Ana', 'tag' => 'third']];
        $this->assertSame(['first', 'second', 'third'], array_column($this->view->sort_by_name($nodid), 'tag'), 'Then by position');
        $this->assertSame(['x'], array_column($this->view->sort_by_name([['name' => 'x']]), 'name'));
        $this->assertSame([], $this->view->sort_by_name([]));
    }

    /**
     * The order does not depend on the order of the input.
     */
    public function test_the_order_is_deterministic(): void {
        $rows = [];
        foreach (['Ana', 'ana', 'Ánna', 'Bia', 'Bia', 'Caio', 'Álvaro', 'Zé'] as $i => $name) {
            $rows[] = ['name' => $name, 'id' => 100 + $i, 'state' => result_state::VALID, 'value' => 10.0 * ($i % 3)];
        }
        $byname = array_column($this->view->sort_by_name($rows), 'id');
        $byvalue = array_column($this->view->sort_by_value($rows), 'id');
        mt_srand(7);
        for ($i = 0; $i < 20; $i++) {
            $shuffled = $rows;
            shuffle($shuffled);
            $this->assertSame($byname, array_column($this->view->sort_by_name($shuffled), 'id'));
            $this->assertSame($byvalue, array_column($this->view->sort_by_value($shuffled), 'id'));
        }
    }

    /**
     * DEC-02: ordered by the value shown, with the tie broken by name; values that are shown the same are a tie; no data and error come last.
     */
    public function test_ordering_by_the_value_shown(): void {
        $row = fn(string $name, result_state $state, ?float $value, int $id) => ['name' => $name, 'state' => $state, 'value' => $value, 'id' => $id];
        $rows = [
            $row('Carlos', result_state::VALID, 66.661, 1), $row('Amanda', result_state::VALID, 66.669, 2), $row('Bia', result_state::VALID, 90.0, 3),
            $row('Dora', result_state::NO_DATA, 0.0, 4), $row('Eva', result_state::VALID, 0.0, 5), $row('Ana', result_state::ERROR, null, 6),
            $row('Caio', result_state::NO_DATA, 0.0, 7),
        ];

        $ascending = array_column($this->view->sort_by_value($rows), 'name');
        $descending = array_column($this->view->sort_by_value($rows, true), 'name');

        $this->assertSame(['Eva', 'Amanda', 'Carlos', 'Bia', 'Ana', 'Caio', 'Dora'], $ascending, '66,66% twice: Amanda before Carlos');
        $this->assertSame(['Bia', 'Amanda', 'Carlos', 'Eva', 'Ana', 'Caio', 'Dora'], $descending, 'Without value last in both directions');
    }

    /**
     * The labels exist in both language packs, the Portuguese ones are those of the contract, and the default translator reads the strings.
     */
    public function test_the_labels_exist_in_both_languages(): void {
        $en = $this->pack('en');
        $pt = $this->pack('pt_br');
        foreach (presentation::STRINGS as $key) {
            $this->assertArrayHasKey($key, $en, $key);
            $this->assertArrayHasKey($key, $pt, $key);
        }
        $this->assertSame(
            ['sem dados', 'erro técnico', 'sem materiais', 'fonte removida', 'assistente não habilitado'],
            array_map(fn($key) => $pt[$key], ['query:no_data', 'query:error', 'query:no_materials', 'query:source_removed', 'query:not_enabled'])
        );
        $default = new presentation();
        $this->assertSame('66,66%', $default->percentage(66.666));
        $this->assertNotSame('', $default->value(result_state::NO_DATA, null), 'The default translator answers');
    }
}
