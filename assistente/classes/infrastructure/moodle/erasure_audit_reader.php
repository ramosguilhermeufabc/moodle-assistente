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

namespace local_assistente\infrastructure\moodle;

use local_assistente\domain\privacy\erasure_event_fact;
use local_assistente\domain\privacy\erasure_key;
use local_assistente\event\student_erasure_completed;
use local_assistente\event\student_erasure_deferred;
use local_assistente\event\student_erasure_stopped;

/**
 * Reads the audit events of the erasures of a student from the log of Moodle (T142; DEC-58). It only reads: the events are created by
 * application/privacy/erasure_audit and never here.
 *
 * Moodle API: get_log_manager()->get_readers('\core\log\sql_reader') and sql_reader::get_events_select(), which restores the events of
 * the log by the identifier of the row in the log (ascending in time). The filter names only the standard fields of an event (`eventname`
 * and `relateduserid`). An event whose class no longer exists is not restored and does not appear. When no log store is enabled there is
 * no reader: the answer is empty and available() is false, which callers treat like a log that was purged.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class erasure_audit_reader {
    /** @var array<class-string, string> event class => type of the fact */
    private const TYPES = [
        student_erasure_deferred::class => erasure_event_fact::DEFERRED,
        student_erasure_completed::class => erasure_event_fact::COMPLETED,
        student_erasure_stopped::class => erasure_event_fact::STOPPED,
    ];

    /**
     * Whether a log store can be read.
     *
     * @return bool
     */
    public function available(): bool {
        return $this->reader() !== null;
    }

    /**
     * The audit events of the erasures of a student, in the order of the log.
     *
     * @param int $userid
     * @return erasure_event_fact[]
     */
    public function events_of(int $userid): array {
        $reader = $this->reader();
        if ($reader === null) {
            return [];
        }
        $params = ['userid' => $userid];
        $names = [];
        foreach (array_keys(self::TYPES) as $i => $class) {
            $params["name{$i}"] = '\\' . $class;
            $names[] = ":name{$i}";
        }
        $events = $reader->get_events_select('relateduserid = :userid AND eventname IN (' . implode(',', $names) . ')', $params, 'id ASC', 0, 0);
        $facts = [];
        foreach ($events as $id => $event) {
            $type = self::TYPES[get_class($event)] ?? null;
            $other = is_array($event->other) ? $event->other : [];
            if ($type === null || !isset($other['task'], $other['courseids'])) {
                continue;
            }
            $facts[] = new erasure_event_fact(
                (int) $id,
                $type,
                (int) $other['task'],
                array_map('intval', (array) $other['courseids']),
                (string) ($other['detail'] ?? '')
            );
        }
        usort($facts, static fn(erasure_event_fact $a, erasure_event_fact $b): int => $a->id <=> $b->id);
        return $facts;
    }

    /**
     * Whether the log already has an event of that type for a case and a task.
     *
     * @param string $type erasure_event_fact::DEFERRED, COMPLETED or STOPPED
     * @param int $task
     * @param erasure_key $key
     * @return bool
     */
    public function has(string $type, int $task, erasure_key $key): bool {
        foreach ($this->events_of($key->userid()) as $fact) {
            if ($fact->type === $type && $fact->task === $task && $key->has_courses($fact->courses)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The first log store that can be queried.
     *
     * @return \core\log\sql_reader|null
     */
    private function reader(): ?\core\log\sql_reader {
        foreach (get_log_manager()->get_readers('\core\log\sql_reader') as $reader) {
            return $reader;
        }
        return null;
    }
}
