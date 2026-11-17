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


namespace local_assistente\application\history;

use local_assistente\domain\source_status;
use local_assistente\infrastructure\moodle\source_record_reader;

/**
 * Tells, when the history is read, whether the Moodle sources of a result still exist (T102; spec-017, DEC-42 D3, quickstart 017 Q3 and Q6).
 *
 * "Source removed" is derived: the Moodle record that is referenced is looked for, and nothing is stored (no field, no flag). The history
 * and its references stay as they were whether the record exists or not: the analyses keep their text, the references keep their ids, and
 * nothing here writes. The current snapshot of a forum is rebuilt without the content that was deleted (DEC-38); the historical analyses keep
 * it, and this class only says that its source is gone.
 *
 * Two states, as in the contract: the record exists or it was removed (a module that is being deleted counts as removed, hypothesis CL-16).
 * There is no third state to hide a doubt in. When the record cannot be looked at (a failure of the database), the exception reaches the
 * caller; a missing permission or a hidden activity never makes a source "removed", because the lookup is of the record and not of what a
 * user may see. A reference to a record of another course contradicts the chain and throws lineage_exception, as does an unknown kind or an
 * invalid id (InvalidArgumentException); none of them is reported as removed.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_status_resolver {
    /** @var source_record_reader */
    private source_record_reader $records;

    /**
     * Constructor.
     *
     * @param source_record_reader|null $records
     */
    public function __construct(?source_record_reader $records = null) {
        $this->records = $records ?? new source_record_reader();
    }

    /**
     * The state of one Moodle record.
     *
     * @param string $type one of source_record_reader::TYPES
     * @param int $id
     * @param int $courseid course the history belongs to
     * @return source_status
     * @throws \InvalidArgumentException for an unknown kind or an id that is not positive
     * @throws lineage_exception when the record belongs to another course
     * @throws \dml_exception when the database cannot be read
     */
    public function status_of(string $type, int $id, int $courseid): source_status {
        $found = $this->records->locate($type, $id, $courseid);
        if ($found === source_record_reader::OTHER_COURSE) {
            throw new lineage_exception("The {$type} #{$id} referenced by the history of course {$courseid} belongs to another course.");
        }
        return $found === source_record_reader::PRESENT ? source_status::EXISTS : source_status::REMOVED;
    }

    /**
     * Whether the source was removed (`sourceRemoved(ref)` of the contract).
     *
     * @param string $type
     * @param int $id
     * @param int $courseid
     * @return bool
     */
    public function source_removed(string $type, int $id, int $courseid): bool {
        return $this->status_of($type, $id, $courseid) === source_status::REMOVED;
    }

    /**
     * The sources of a lineage with their state, in the same order and with the same references.
     *
     * @param lineage $lineage
     * @return array each ['type' => string, 'id' => int, 'status' => source_status]
     */
    public function resolve(lineage $lineage): array {
        $courseid = (int) $lineage->analysis->course_id;
        $resolved = [];
        foreach ($lineage->sources as $source) {
            $resolved[] = $source + ['status' => $this->status_of($source['type'], $source['id'], $courseid)];
        }
        return $resolved;
    }
}
