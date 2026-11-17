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

namespace local_assistente\event;

use local_assistente\domain\privacy\erasure_key;

/**
 * What the three audit events of an erasure share (T142; DEC-58): they belong to the system context, point to the student through
 * `relateduserid` and carry only the courses of the case, the task that is its generation and a fixed code. Never a name, a text, the
 * message of an exception or the name of a lock.
 *
 * Only application/privacy/erasure_audit creates them.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class student_erasure_event extends \core\event\base {
    /** @var string[] reasons an erasure was deferred or stopped */
    public const REASONS = ['lock_unavailable', 'event_set_unstable', 'names_unavailable', 'attempts_exhausted', 'unexpected_error'];

    /**
     * The event of a case.
     *
     * @param erasure_key $key
     * @param int $task the task that is the generation of the case
     * @param string $detail the reason, or the way the case was closed
     * @param int $remaining attempts left, when it applies
     * @return static
     */
    public static function for_case(erasure_key $key, int $task, string $detail, int $remaining = 0): static {
        return static::create([
            'context' => \context_system::instance(),
            'relateduserid' => $key->userid(),
            'other' => ['courseids' => $key->courses(), 'task' => $task, 'detail' => $detail, 'remaining' => $remaining],
        ]);
    }

    #[\Override]
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (empty($this->relateduserid)) {
            throw new \coding_exception('The related user is required.');
        }
        foreach (['courseids', 'task', 'detail', 'remaining'] as $field) {
            if (!is_array($this->other) || !array_key_exists($field, $this->other)) {
                throw new \coding_exception("The field '{$field}' of other is required.");
            }
        }
    }

    #[\Override]
    public function get_description() {
        return "The erasure of the user with id '{$this->relateduserid}' in the courses " . implode(',', (array) $this->other['courseids'])
            . " has the event '{$this->eventname}' for the task {$this->other['task']} ({$this->other['detail']}).";
    }
}
