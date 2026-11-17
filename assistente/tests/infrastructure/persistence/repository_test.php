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

namespace local_assistente\infrastructure\persistence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the base repository (quickstart 003 Q4 and Q6; quickstart 002 Q9).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(repository::class)]
#[CoversClass(schema_domains::class)]
final class repository_test extends \advanced_testcase {
    /**
     * Build a repository over a table, with the approved domains of that table.
     *
     * @param string $table logical table name
     * @return repository
     */
    private function repository_for(string $table): repository {
        return new class($table) extends repository {
            /**
             * Constructor.
             *
             * @param string $table
             */
            public function __construct(
                /** @var string table */
                private readonly string $tablename
            ) {
            }

            #[\Override]
            protected function table(): string {
                return $this->tablename;
            }
        };
    }

    /**
     * A material row.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function material(array $overrides = []): \stdClass {
        return (object) ($overrides + [
            'course_id' => 100, 'section_id' => 1, 'cmid' => 200, 'material_type' => 'pdf',
            'name' => 'Material', 'availability' => 'available', 'current_version' => null, 'created_at' => time(),
        ]);
    }

    /**
     * Insert, read by id and by natural key, list and update.
     */
    public function test_insert_find_and_update(): void {
        $this->resetAfterTest();
        $repository = $this->repository_for('local_assistente_material');

        $id = $repository->insert($this->material());
        $this->assertGreaterThan(0, $id);

        $found = $repository->find($id);
        $this->assertSame('Material', $found->name);
        $this->assertSame($id, (int) $repository->find_by(['course_id' => 100, 'cmid' => 200])->id);
        $this->assertNull($repository->find(999999));
        $this->assertNull($repository->find_by(['course_id' => 1, 'cmid' => 1]));
        $this->assertCount(1, $repository->find_all_by(['course_id' => 100]));

        // Values read from the database come back as strings; the update must accept them.
        $found->name = 'Renamed';
        $repository->update($found);
        $this->assertSame('Renamed', $repository->find($id)->name);
    }

    /**
     * Q4: violating a unique index is handled and the data is not duplicated.
     */
    public function test_unique_violation_is_handled_without_duplicating(): void {
        global $DB;
        $this->resetAfterTest();
        $repository = $this->repository_for('local_assistente_material');
        $repository->insert($this->material());

        try {
            $repository->insert($this->material(['name' => 'Same course and cmid']));
            $this->fail('The unique index (course_id, cmid) must reject the second row');
        } catch (duplicate_record_exception $e) {
            $this->assertInstanceOf(\dml_write_exception::class, $e->getPrevious());
        }

        $this->assertSame(1, $DB->count_records('local_assistente_material'));
    }

    /**
     * Q6 and 002 Q9: unknown analysis_type is rejected and nothing is written.
     */
    public function test_unknown_analysis_type_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $repository = $this->repository_for('local_assistente_analysis');

        $this->expectException(invalid_domain_value_exception::class);
        try {
            $repository->insert((object) [
                'course_id' => 1, 'processing_id' => 1, 'analysis_type' => 'unknown_type',
                'cmid' => null, 'summary' => null, 'created_at' => time(),
            ]);
        } finally {
            $this->assertSame(0, $DB->count_records('local_assistente_analysis'));
        }
    }

    /**
     * Q6 and 002 Q9: status 3 is rejected for the criteria result; 0, 1 and 2 are accepted.
     */
    public function test_status_outside_the_domain_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $repository = $this->repository_for('local_assistente_criteria_result');
        $row = static fn(int $status) => (object) [
            'analysis_id' => 1, 'student_id' => 1 + $status, 'activity_status' => $status,
            'forum_status' => 1, 'frequency_status' => 2,
        ];

        foreach ([0, 1, 2] as $status) {
            $this->assertGreaterThan(0, $repository->insert($row($status)));
        }
        $this->assertSame(3, $DB->count_records('local_assistente_criteria_result'));

        $this->expectException(invalid_domain_value_exception::class);
        $repository->insert($row(3));
    }

    /**
     * Event states are limited to the four of spec-006; null is not a state.
     *
     * @param mixed $status
     */
    #[DataProvider('invalid_event_statuses')]
    public function test_event_status_domain(mixed $status): void {
        $this->resetAfterTest();
        $repository = $this->repository_for('local_assistente_event');
        $this->expectException(invalid_domain_value_exception::class);
        $repository->insert((object) [
            'course_id' => 1, 'event_type' => 'x', 'payload' => '{}', 'created_at' => time(), 'status' => $status,
        ]);
    }

    /**
     * Values that are not event states.
     *
     * @return array
     */
    public static function invalid_event_statuses(): array {
        return [
            'unknown' => ['done'],
            'empty' => [''],
            'null' => [null],
            'case differs' => ['Pending'],
        ];
    }

    /**
     * Book is not a functional material type (DEC-33 D2).
     */
    public function test_book_is_not_a_material_type(): void {
        $this->resetAfterTest();
        $repository = $this->repository_for('local_assistente_material');
        $this->expectException(invalid_domain_value_exception::class);
        $repository->insert($this->material(['material_type' => 'book']));
    }

    /**
     * Summary status (DEC-50) and availability (DEC-51) are validated; a value outside the domain is refused.
     */
    public function test_material_domains_are_validated(): void {
        $versions = schema_domains::for_table('local_assistente_material_version');
        $this->assertSame(['pending', 'completed', 'failed'], $versions['summary_status']);
        $this->assertSame(['available', 'unavailable', 'pending'], $versions['availability']);
        $this->assertSame(
            ['available', 'unavailable', 'pending'],
            schema_domains::for_table('local_assistente_material')['availability']
        );

        $this->expectException(invalid_domain_value_exception::class);
        (new table_repository('local_assistente_material'))->insert($this->material(['availability' => 'maybe']));
    }
}
