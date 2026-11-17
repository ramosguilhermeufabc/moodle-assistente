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
 * Tests of the configuration aggregate repository (DEC-40 D1 and D3, DEC-45 D3).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(config_repository::class)]
#[CoversClass(criteria_repository::class)]
final class config_repository_test extends \advanced_testcase {
    /**
     * A configuration is created disabled and found by course; a course has one configuration only.
     */
    public function test_configuration_is_created_disabled_and_is_unique_per_course(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new config_repository();

        $this->assertNull($repository->find_by_course($course->id), 'No configuration yet');
        $config = $repository->create($course->id, now: 1900000000);

        $this->assertSame('0', (string) $config->enabled, 'Disabled by default (DEC-45 D3)');
        $this->assertSame(1900000000, (int) $config->created_at);
        $this->assertSame((int) $config->id, (int) $repository->find_by_course($course->id)->id);

        try {
            $repository->create($course->id);
            $this->fail('A second configuration for the same course must be rejected');
        } catch (duplicate_record_exception $e) {
            $this->assertSame(1, $DB->count_records('local_assistente_config', ['course_id' => $course->id]));
        }
    }

    /**
     * Enabling and disabling change only the flag, and can be repeated (enable, disable, enable).
     */
    public function test_enabling_and_disabling_keep_the_row_and_the_criteria(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new config_repository();
        $config = $repository->create($course->id);
        $repository->insert_criteria((int) $config->id, 60, 1, 40);

        $repository->set_enabled((int) $config->id, true);
        $this->assertSame('1', (string) $repository->find((int) $config->id)->enabled);
        $repository->set_enabled((int) $config->id, false);
        $this->assertSame('0', (string) $repository->find((int) $config->id)->enabled);
        $repository->set_enabled((int) $config->id, true);
        $this->assertSame('1', (string) $repository->find((int) $config->id)->enabled);

        $this->assertSame(1, $DB->count_records('local_assistente_config'), 'Disabling does not delete');
        $this->assertNotNull($repository->find_criteria((int) $config->id), 'Disabling keeps the criteria');
    }

    /**
     * The repository has no operation that deletes a configuration or its criteria.
     */
    public function test_there_is_no_delete_operation(): void {
        foreach ([config_repository::class, criteria_repository::class] as $class) {
            $methods = array_map(static fn($method) => $method->getName(), (new \ReflectionClass($class))->getMethods());
            $this->assertSame([], preg_grep('/delete|remove|purge/i', $methods), $class);
        }
    }

    /**
     * Valid criteria, including the limits 0 and 100 and three decimals.
     */
    public function test_valid_criteria_are_stored(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new config_repository();
        $config = $repository->create($course->id);

        $criteria = $repository->insert_criteria((int) $config->id, '33.333', 0, 100);

        $this->assertEqualsWithDelta(33.333, (float) $criteria->activity_criterion, 0.0001);
        $this->assertSame('0', (string) $criteria->forum_criterion);
        $this->assertEqualsWithDelta(100.0, (float) $criteria->frequency_criterion, 0.0001);
        $this->assertSame((int) $criteria->id, (int) $repository->find_criteria((int) $config->id)->id);
        $this->assertNull($repository->find_criteria(987654));
    }

    /**
     * Values outside the domain.
     *
     * @return array
     */
    public static function invalid_criteria(): array {
        return [
            'activity above 100' => [100.5, 1, 40],
            'activity negative' => [-1, 1, 40],
            'activity four decimals' => [60.1234, 1, 40],
            'frequency above 100' => [60, 1, 101],
            'frequency negative' => [60, 1, -0.5],
            'frequency four decimals' => [60, 1, '40.0001'],
            'forum 2' => [60, 2, 40],
            'forum negative' => [60, -1, 40],
        ];
    }

    /**
     * Invalid criteria are rejected and nothing is written.
     *
     * @param float|int|string $activity
     * @param int $forum
     * @param float|int|string $frequency
     */
    #[DataProvider('invalid_criteria')]
    public function test_invalid_criteria_are_rejected(float|int|string $activity, int $forum, float|int|string $frequency): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new config_repository();
        $config = $repository->create($course->id);

        try {
            $repository->insert_criteria((int) $config->id, $activity, $forum, $frequency);
            $this->fail('The criteria must be rejected');
        } catch (invalid_domain_value_exception $e) {
            $this->assertSame(0, $DB->count_records('local_assistente_criteria'));
        }
    }

    /**
     * A configuration has one set of criteria.
     */
    public function test_criteria_are_unique_per_configuration(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new config_repository();
        $config = $repository->create($course->id);
        $repository->insert_criteria((int) $config->id, 60, 1, 40);

        $this->expectException(duplicate_record_exception::class);
        try {
            $repository->insert_criteria((int) $config->id, 50, 0, 30);
        } finally {
            $this->assertSame(1, $DB->count_records('local_assistente_criteria'));
        }
    }

    /**
     * The criteria can be replaced (the application decides who may do it); invalid values are still rejected.
     */
    public function test_criteria_can_be_replaced_and_still_validated(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new config_repository();
        $config = $repository->create($course->id);
        $repository->insert_criteria((int) $config->id, 60, 1, 40);

        $changed = $repository->update_criteria((int) $config->id, 50, 0, '35.5');
        $this->assertEqualsWithDelta(50.0, (float) $changed->activity_criterion, 0.0001);
        $this->assertSame('0', (string) $changed->forum_criterion);
        $this->assertEqualsWithDelta(35.5, (float) $changed->frequency_criterion, 0.0001);

        try {
            $repository->update_criteria((int) $config->id, 150, 0, 35);
            $this->fail('An invalid value must be rejected also on update');
        } catch (invalid_domain_value_exception $e) {
            $this->assertEqualsWithDelta(50.0, (float) $repository->find_criteria((int) $config->id)->activity_criterion, 0.0001);
        }
    }

    /**
     * Replacing the criteria of a configuration that has none is a programming error.
     */
    public function test_updating_missing_criteria_is_an_error(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repository = new config_repository();
        $config = $repository->create($course->id);

        $this->expectException(\coding_exception::class);
        $repository->update_criteria((int) $config->id, 50, 0, 30);
    }
}
