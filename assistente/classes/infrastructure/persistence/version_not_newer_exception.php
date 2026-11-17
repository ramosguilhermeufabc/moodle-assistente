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

/**
 * A version was refused as the current one because its number is not greater than the number of the current version (DEC-62).
 *
 * It is the answer of a business rule, not a technical failure: it is deterministic, nothing was written and the pointer that won is
 * untouched. The caller tells it apart from a failure of the database or of the lock by this type, which no other code throws.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class version_not_newer_exception extends \RuntimeException {
    /**
     * Constructor.
     *
     * @param int $materialid
     * @param int $versionid the version that was refused
     * @param int $number number of the refused version
     * @param int $currentid the version that is current
     * @param int $currentnumber number of the current version
     */
    public function __construct(
        /** @var int the material */
        public readonly int $materialid,
        /** @var int the version that was refused */
        public readonly int $versionid,
        /** @var int number of the refused version */
        public readonly int $number,
        /** @var int the version that is current */
        public readonly int $currentid,
        /** @var int number of the current version */
        public readonly int $currentnumber
    ) {
        parent::__construct(
            "Version {$versionid} (number {$number}) cannot become current in material {$materialid}: "
            . "the current version {$currentid} has number {$currentnumber}, and a new one must be greater."
        );
    }

    /**
     * Whether the refused version is the one that is already current.
     *
     * @return bool
     */
    public function is_current(): bool {
        return $this->versionid === $this->currentid;
    }
}
