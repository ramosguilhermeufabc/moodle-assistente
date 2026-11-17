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

/**
 * Identity of an event occurrence, by event type (DEC-08, DEC-10, DEC-21; P-01).
 *
 * The identity is a hash of the type and of the identity fields declared by the type. It is stored inside the payload,
 * because the schema has no column for it and P-01 forbids adding one before the need is demonstrated. There is no universal
 * identifier: the limitation of each type is event_type::limitation().
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class occurrence_identity {
    /** @var string name of the payload key that holds the identity */
    public const PAYLOAD_KEY = 'identity';

    /**
     * Identity of an occurrence.
     *
     * @param event_type $type
     * @param array $payload the payload, valid for the type (extra keys such as the identity itself are ignored)
     * @return string 40 hexadecimal characters
     * @throws \InvalidArgumentException when an identity field is missing
     */
    public static function of(event_type $type, array $payload): string {
        $parts = [$type->value];
        foreach ($type->identity_fields() as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new \InvalidArgumentException("The identity of {$type->value} needs the field '{$field}'.");
            }
            $parts[] = $field . '=' . $payload[$field];
        }
        return sha1(implode('|', $parts));
    }

    /**
     * Name of the lock that serialises the receipt of one occurrence in a course.
     *
     * @param int $courseid
     * @param event_type $type
     * @param string $identity
     * @return string
     */
    public static function lock_resource(int $courseid, event_type $type, string $identity): string {
        return 'event_identity_' . sha1($courseid . '|' . $type->value . '|' . $identity);
    }
}
