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

namespace local_assistente;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * Schema tests: install.xml must match the spec-002 inventory exactly (21 tables, 206 items).
 *
 * Scenarios Q1-Q5 of the spec-002 quickstart (gate G-002).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class install_schema_test extends \advanced_testcase {
    /**
     * Inventory of the spec-002: table => [fields, keys, indexes, unique indexes].
     * Totals: 21 tables, 128 fields, 60 keys, 18 indexes (206 items).
     *
     * @return array
     */
    private static function inventory(): array {
        return [
            'local_assistente_config' => [4, 2, 0, 0],
            'local_assistente_criteria' => [5, 2, 0, 0],
            'local_assistente_event' => [8, 2, 3, 0],
            'local_assistente_processing' => [5, 2, 1, 0],
            'local_assistente_processing_attempt' => [7, 2, 2, 1],
            'local_assistente_processing_context' => [4, 2, 0, 0],
            'local_assistente_material' => [9, 5, 1, 1],
            'local_assistente_material_version' => [8, 2, 1, 1],
            'local_assistente_analysis' => [7, 4, 1, 0],
            'local_assistente_questionnaire_analysis' => [3, 2, 0, 0],
            'local_assistente_questionnaire_question' => [5, 3, 1, 1],
            'local_assistente_question_result' => [9, 4, 1, 1],
            'local_assistente_questionnaire_metric' => [6, 2, 0, 0],
            'local_assistente_forum_difficulty' => [4, 3, 0, 0],
            'local_assistente_performance' => [5, 4, 1, 1],
            'local_assistente_frequency' => [7, 4, 1, 1],
            'local_assistente_frequency_activity' => [5, 3, 1, 1],
            'local_assistente_criteria_result' => [6, 3, 1, 1],
            'local_assistente_pedagogical_synthesis' => [8, 4, 1, 1],
            'local_assistente_report' => [8, 3, 1, 0],
            'local_assistente_report_version' => [5, 2, 1, 1],
        ];
    }

    /**
     * Load the install.xml structure.
     *
     * @return \xmldb_structure
     */
    private function load_structure(): \xmldb_structure {
        global $CFG;
        $file = new \xmldb_file($CFG->dirroot . '/local/assistente/db/install.xml');
        $this->assertTrue($file->fileExists(), 'db/install.xml must exist');
        $this->assertTrue($file->loadXMLStructure(), 'db/install.xml must be loadable');
        return $file->getStructure();
    }

    /**
     * Q1: exactly the 21 tables exist after installing the plugin.
     */
    public function test_exactly_21_tables_are_created(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        $expected = array_keys(self::inventory());
        $this->assertCount(21, $expected);
        foreach ($expected as $table) {
            $this->assertTrue($dbman->table_exists($table), "Table {$table} must exist");
        }

        // No table of the plugin outside the inventory (names without the Moodle prefix).
        $found = array_filter($DB->get_tables(false), static fn($name) => str_starts_with($name, 'local_assistente_'));
        $this->assertEqualsCanonicalizing($expected, array_values($found));
    }

    /**
     * Q2: the counts of fields, keys and indexes per table and in total, and the id of each table, match the inventory.
     *
     * Scope: compares install.xml (parsed by XMLDB) with the inventory. It does not read the physical schema of the database; the physical
     * check against install.xml belongs to scenario I-16 of the CL-07 matrix (correction 5).
     */
    #[TestDox('XML against the inventory: counts of fields, keys and indexes (physical schema: I-16)')]
    public function test_field_counts_and_totals_match_inventory(): void {
        $structure = $this->load_structure();
        $totals = [0, 0, 0];
        $names = [];

        foreach ($structure->getTables() as $table) {
            $names[] = $table->getName();
            $this->assertArrayHasKey($table->getName(), self::inventory(), "Table {$table->getName()} is not in the inventory");
            [$fields, $keys, $indexes] = self::inventory()[$table->getName()];
            $this->assertCount($fields, $table->getFields(), "Fields of {$table->getName()}");
            $this->assertCount($keys, $table->getKeys(), "Keys of {$table->getName()}");
            $this->assertCount($indexes, $table->getIndexes(), "Indexes of {$table->getName()}");

            $primary = $table->getField('id');
            $this->assertNotNull($primary, "{$table->getName()} must have id");
            $this->assertTrue($primary->getSequence(), "{$table->getName()}.id must be a sequence");
            $this->assertTrue($primary->getNotNull());

            $totals[0] += count($table->getFields());
            $totals[1] += count($table->getKeys());
            $totals[2] += count($table->getIndexes());
        }

        $this->assertEqualsCanonicalizing(array_keys(self::inventory()), $names);
        $this->assertSame([128, 60, 18], $totals);
    }

    /**
     * Q3: unique indexes exist exactly where the inventory defines them.
     *
     * Scope: compares install.xml with the inventory; it does not read the physical indexes of the database (scenario I-16, correction 5).
     */
    #[TestDox('XML against the inventory: unique indexes exactly where defined (physical schema: I-16)')]
    public function test_unique_indexes_exactly_where_defined(): void {
        $structure = $this->load_structure();
        foreach ($structure->getTables() as $table) {
            $unique = 0;
            foreach ($table->getIndexes() as $index) {
                $unique += $index->getUnique() ? 1 : 0;
            }
            $this->assertSame(self::inventory()[$table->getName()][3], $unique, "Unique indexes of {$table->getName()}");
        }
    }

    /**
     * Q4: a duplicate in a unique index is rejected by the database.
     */
    public function test_duplicate_in_unique_index_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();

        $row = (object) [
            'course_id' => 100,
            'section_id' => 1,
            'cmid' => 200,
            'material_type' => 'pdf',
            'name' => 'Material',
            'availability' => 'available',
            'current_version' => null,
            'created_at' => time(),
        ];
        $DB->insert_record('local_assistente_material', $row);

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_assistente_material', clone $row);
    }

    /**
     * I-02 (CR-02; matrix of the CL-07): the two circular pairs, material/material_version and report/report_version, are accepted by the
     * database that the installation created. The pointer `current_version` is a nullable column of the table that was created, so the row
     * is written first with no pointer, then its version, then the pointer is set to that version.
     *
     * Scope: only the order of writing, on the real tables. It does not fix what happens when a version is removed while it is pointed at
     * (that is the semantics of the exclusion, CL-37) and it does not compare the physical schema with install.xml (that is I-16).
     */
    #[TestDox('I-02: the circular pairs can be written in order (pointer null, version, pointer)')]
    public function test_circular_pairs_can_be_written_in_order(): void {
        global $DB;
        $this->resetAfterTest();

        // The columns that the installation created in the database, not the XML.
        foreach (['local_assistente_material', 'local_assistente_report'] as $table) {
            $column = $DB->get_columns($table)['current_version'] ?? null;
            $this->assertNotNull($column, "{$table}.current_version must exist in the database");
            $this->assertFalse($column->not_null, "{$table}.current_version must accept NULL, so that the row can be written first");
        }

        // Material: row with no pointer, then the version, then the pointer.
        $materialid = $DB->insert_record('local_assistente_material', (object) [
            'course_id' => 100, 'section_id' => 1, 'cmid' => 7601, 'material_type' => 'pdf', 'name' => 'I-02',
            'availability' => 'available', 'current_version' => null, 'created_at' => time(),
        ]);
        $this->assertNull($DB->get_field('local_assistente_material', 'current_version', ['id' => $materialid]));
        $versionid = $DB->insert_record('local_assistente_material_version', (object) [
            'material_id' => $materialid, 'version' => 1, 'content_hash' => 'hash-i02', 'summary' => 'summary',
            'summary_status' => 'completed', 'availability' => 'available', 'created_at' => time(),
        ]);
        $DB->set_field('local_assistente_material', 'current_version', $versionid, ['id' => $materialid]);
        $this->assertSame($versionid, (int) $DB->get_field('local_assistente_material', 'current_version', ['id' => $materialid]));
        $this->assertSame($materialid, (int) $DB->get_field('local_assistente_material_version', 'material_id', ['id' => $versionid]));

        // Report: the same order.
        $reportid = $DB->insert_record('local_assistente_report', (object) ['course_id' => 101, 'created_at' => time(), 'current_version' => null]);
        $this->assertNull($DB->get_field('local_assistente_report', 'current_version', ['id' => $reportid]));
        $reportversionid = $DB->insert_record('local_assistente_report_version', (object) [
            'report_id' => $reportid, 'version' => 1, 'snapshot_json' => '{"type":"periodic","generated_at":1,"blocks":[]}', 'created_at' => time(),
        ]);
        $DB->set_field('local_assistente_report', 'current_version', $reportversionid, ['id' => $reportid]);
        $this->assertSame($reportversionid, (int) $DB->get_field('local_assistente_report', 'current_version', ['id' => $reportid]));
        $this->assertSame($reportid, (int) $DB->get_field('local_assistente_report_version', 'report_id', ['id' => $reportversionid]));
    }

    /**
     * Q5: there are no fields or indexes outside the inventory; also, the removed
     * indicators of DEC-29 do not exist.
     *
     * Scope: inspects install.xml (parsed by XMLDB); it does not read the physical schema of the database (scenario I-16, correction 5).
     */
    #[TestDox('XML against the inventory: nothing outside it, and the removed indicators are absent (physical schema: I-16)')]
    public function test_no_fields_or_indexes_outside_inventory(): void {
        $structure = $this->load_structure();

        foreach (['local_assistente_material_version', 'local_assistente_report_version'] as $name) {
            $this->assertNull($structure->getTable($name)->getField('is_current'), "{$name}.is_current was removed (DEC-29)");
        }
        foreach (['local_assistente_material', 'local_assistente_report'] as $name) {
            $this->assertNotNull($structure->getTable($name)->getField('current_version'), "{$name}.current_version (DEC-29)");
            $this->assertFalse($structure->getTable($name)->getField('current_version')->getNotNull());
        }
        $hash = $structure->getTable('local_assistente_material_version')->getField('content_hash');
        $this->assertNotNull($hash, 'material_version.content_hash (CL-45)');
        $this->assertTrue($hash->getNotNull());
        $this->assertSame(64, (int) $hash->getLength());
        foreach (['local_assistente_material', 'local_assistente_material_version'] as $name) {
            $availability = $structure->getTable($name)->getField('availability');
            $this->assertSame(XMLDB_TYPE_CHAR, $availability->getType(), "{$name}.availability is char (DEC-51)");
            $this->assertSame(20, (int) $availability->getLength());
        }
        $synthesis = $structure->getTable('local_assistente_pedagogical_synthesis');
        foreach (['course_id', 'section_id', 'version', 'input_fingerprint'] as $field) {
            $this->assertNotNull($synthesis->getField($field), "pedagogical_synthesis.{$field} (DEC-14, DEC-19)");
            $this->assertTrue($synthesis->getField($field)->getNotNull());
        }
        $this->assertSame(64, (int) $synthesis->getField('input_fingerprint')->getLength());
    }
}
