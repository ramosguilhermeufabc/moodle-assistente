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

namespace local_assistente\domain\event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the event types, their payload schema and the identity of an occurrence (DEC-08, DEC-10, DEC-21; P-01).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(occurrence_identity::class)]
#[CoversClass(event_type::class)]
#[CoversClass(identity_strategy::class)]
final class occurrence_identity_test extends \basic_testcase {
    /**
     * The same occurrence has the same identity; another object has another one.
     */
    public function test_same_occurrence_same_identity(): void {
        $a = occurrence_identity::of(event_type::QUIZ_PUBLISHED, ['cmid' => 10]);

        $this->assertSame($a, occurrence_identity::of(event_type::QUIZ_PUBLISHED, ['cmid' => 10]));
        $this->assertNotSame($a, occurrence_identity::of(event_type::QUIZ_PUBLISHED, ['cmid' => 11]));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $a);
    }

    /**
     * The type is part of the identity: the same numbers under another type are another occurrence.
     */
    public function test_type_is_part_of_the_identity(): void {
        $this->assertNotSame(
            occurrence_identity::of(event_type::FORUM_POST_CREATED, ['postid' => 5]),
            occurrence_identity::of(event_type::FORUM_POST_DELETED, ['postid' => 5])
        );
    }

    /**
     * Quickstart 006 Q8: two legitimate edits of the same post, in different seconds, are different occurrences;
     * the same edit received twice is one. Limitation: two edits in the same second collapse.
     */
    public function test_two_legitimate_edits_differ_but_the_same_second_collapses(): void {
        $base = ['cmid' => 1, 'discussionid' => 2, 'postid' => 3];
        $first = occurrence_identity::of(event_type::FORUM_POST_UPDATED, $base + ['occurred_at' => 1000]);
        $second = occurrence_identity::of(event_type::FORUM_POST_UPDATED, $base + ['occurred_at' => 1005]);
        $sameagain = occurrence_identity::of(event_type::FORUM_POST_UPDATED, $base + ['occurred_at' => 1000]);

        $this->assertNotSame($first, $second);
        $this->assertSame($first, $sameagain, 'The same delivery twice is one occurrence');
        $this->assertStringContainsString('same second', event_type::FORUM_POST_UPDATED->limitation());
    }

    /**
     * A message created is recognised by the post alone, whatever else the payload says.
     */
    public function test_once_per_object_uses_only_the_object(): void {
        $this->assertSame(
            occurrence_identity::of(event_type::FORUM_POST_CREATED, ['cmid' => 1, 'discussionid' => 2, 'postid' => 9]),
            occurrence_identity::of(event_type::FORUM_POST_CREATED, ['cmid' => 7, 'discussionid' => 8, 'postid' => 9])
        );
    }

    /**
     * A missing identity field is an error, not a weaker identity.
     */
    public function test_missing_identity_field_is_an_error(): void {
        $this->expectException(\InvalidArgumentException::class);
        occurrence_identity::of(event_type::FORUM_POST_UPDATED, ['postid' => 3]);
    }

    /**
     * The lock name is stable, and scoped by course and type.
     */
    public function test_lock_resource(): void {
        $identity = occurrence_identity::of(event_type::QUIZ_PUBLISHED, ['cmid' => 10]);
        $lock = occurrence_identity::lock_resource(2, event_type::QUIZ_PUBLISHED, $identity);

        $this->assertSame($lock, occurrence_identity::lock_resource(2, event_type::QUIZ_PUBLISHED, $identity));
        $this->assertNotSame($lock, occurrence_identity::lock_resource(3, event_type::QUIZ_PUBLISHED, $identity));
        $this->assertNotSame($lock, occurrence_identity::lock_resource(2, event_type::QUIZ_CLOSED, $identity));
    }

    /**
     * Every type is well formed: required fields, identity fields inside the schema, a limitation, a value that fits the column.
     *
     * @return array
     */
    public static function all_types(): array {
        return array_combine(
            array_map(static fn(event_type $type) => $type->value, event_type::cases()),
            array_map(static fn(event_type $type) => [$type], event_type::cases())
        );
    }

    /**
     * The catalog entry of a type is consistent.
     *
     * @param event_type $type
     */
    #[DataProvider('all_types')]
    public function test_every_type_is_well_formed(event_type $type): void {
        $this->assertNotEmpty($type->fields());
        $this->assertNotEmpty($type->identity_fields());
        $this->assertSame([], array_diff($type->identity_fields(), array_keys($type->fields())));
        $this->assertNotSame('', $type->limitation());
        $this->assertLessThanOrEqual(100, strlen($type->value), 'event_type is char(100)');
        $this->assertNotContains(occurrence_identity::PAYLOAD_KEY, array_keys($type->fields()), 'The key is reserved');
        $this->assertSame(array_keys($type->fields()), array_keys($type->normalize_payload(self::sample($type))));
    }

    /**
     * A valid payload for a type.
     *
     * @param event_type $type
     * @return array
     */
    private static function sample(event_type $type): array {
        $payload = [];
        foreach ($type->fields() as $name => $kind) {
            $payload[$name] = $kind === 'int' ? 1 : 'x';
        }
        return $payload;
    }

    /**
     * Strategies of the catalog (DEC-31, DEC-38, DEC-41 D3).
     */
    public function test_strategies(): void {
        $this->assertSame(identity_strategy::ONCE_PER_OBJECT, event_type::QUIZ_PUBLISHED->strategy());
        $this->assertSame(identity_strategy::ONCE_PER_OBJECT, event_type::FORUM_POST_CREATED->strategy());
        $this->assertSame(identity_strategy::OCCURRENCE, event_type::FORUM_POST_UPDATED->strategy());
        $this->assertSame(identity_strategy::OCCURRENCE, event_type::GRADE_CHANGED->strategy());
        $this->assertSame(identity_strategy::COALESCE_PENDING, event_type::SYNTHESIS_REQUESTED->strategy());
        // The request to apply the criteria of a student (T093, DEC-55): one pending request for each student.
        $this->assertSame(identity_strategy::COALESCE_PENDING, event_type::CRITERIA_CHECK_REQUESTED->strategy());
        $this->assertSame(['userid' => 'int'], event_type::CRITERIA_CHECK_REQUESTED->fields());
        $this->assertSame(['userid'], event_type::CRITERIA_CHECK_REQUESTED->identity_fields());
        // The request for the whole course (T141, DEC-55 D2): one pending request for each course.
        $this->assertSame(identity_strategy::COALESCE_PENDING, event_type::COURSE_CRITERIA_CHECK_REQUESTED->strategy());
        $this->assertSame(['course_id' => 'int'], event_type::COURSE_CRITERIA_CHECK_REQUESTED->fields());
        $this->assertSame(['course_id'], event_type::COURSE_CRITERIA_CHECK_REQUESTED->identity_fields());
    }

    /**
     * The request of the complete rebuild of a forum (CL-47): only the forum, one pending request for each forum, and an identity that
     * is neither the one of another forum nor the one of a deletion.
     */
    public function test_forum_rebuild_requested(): void {
        $type = event_type::FORUM_REBUILD_REQUESTED;
        $this->assertSame('forum_rebuild_requested', $type->value);
        $this->assertSame(identity_strategy::COALESCE_PENDING, $type->strategy());
        $this->assertSame(['cmid' => 'int'], $type->fields());
        $this->assertSame(['cmid'], $type->identity_fields());
        $this->assertSame(['cmid' => 7], $type->normalize_payload(['cmid' => '7']));
        $this->assertSame(
            occurrence_identity::of($type, ['cmid' => 7]),
            occurrence_identity::of($type, ['cmid' => 7, 'ignored' => 1])
        );
        $this->assertNotSame(occurrence_identity::of($type, ['cmid' => 7]), occurrence_identity::of($type, ['cmid' => 8]));
        $this->assertStringContainsString('pending', $type->limitation());
    }

    /**
     * The request has no discussion or message, and nothing else is accepted or missing.
     *
     * @return array
     */
    public static function invalid_rebuild_payloads(): array {
        return [
            'no forum' => [[]],
            'a discussion' => [['cmid' => 1, 'discussionid' => 2]],
            'a message' => [['cmid' => 1, 'postid' => 2]],
            'a text forum' => [['cmid' => 'abc']],
        ];
    }

    /**
     * @param array $payload
     */
    #[DataProvider('invalid_rebuild_payloads')]
    public function test_forum_rebuild_requested_refuses_an_invalid_payload(array $payload): void {
        $this->expectException(\InvalidArgumentException::class);
        event_type::FORUM_REBUILD_REQUESTED->normalize_payload($payload);
    }

    /**
     * The rebuild request is not the deletion of the same forum: another type, so another identity and another lock.
     */
    public function test_forum_rebuild_requested_is_not_a_deletion(): void {
        $this->assertNotSame(
            occurrence_identity::of(event_type::FORUM_REBUILD_REQUESTED, ['cmid' => 5]),
            occurrence_identity::of(event_type::FORUM_DISCUSSION_DELETED, ['cmid' => 5, 'discussionid' => 5])
        );
        $this->assertSame(identity_strategy::ONCE_PER_OBJECT, event_type::FORUM_DISCUSSION_DELETED->strategy());
        $this->assertSame(identity_strategy::ONCE_PER_OBJECT, event_type::FORUM_POST_DELETED->strategy());
    }

    /**
     * The publication payload carries the cmid (R-006-01).
     */
    public function test_publication_payload_has_the_cmid(): void {
        $this->assertSame(['cmid' => 'int'], event_type::QUIZ_PUBLISHED->fields());
    }

    /**
     * Normalising: numeric strings become integers; missing, unknown, mistyped and empty fields are rejected.
     */
    public function test_payload_normalisation(): void {
        $this->assertSame(['cmid' => 12], event_type::QUIZ_PUBLISHED->normalize_payload(['cmid' => '12']));

        foreach ([
            'missing' => [],
            'unknown field' => ['cmid' => 1, 'extra' => 2],
            'reserved key' => ['cmid' => 1, 'identity' => 'x'],
            'not an integer' => ['cmid' => 'abc'],
            'float' => ['cmid' => 1.5],
            'null' => ['cmid' => null],
        ] as $label => $payload) {
            try {
                event_type::QUIZ_PUBLISHED->normalize_payload($payload);
                $this->fail("{$label} must be rejected");
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        event_type::MATERIAL_CHANGED->normalize_payload(['cmid' => 1, 'moodle_event' => '', 'occurred_at' => 5]);
    }
}
