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

namespace local_assistente\application\privacy;

/**
 * The erasure of some students was not completed in this call (T142; DEC-58). The Privacy API of Moodle passes the message to the data
 * protection officer, so it has only fixed text and counts, never an identifier, a name, a text or the message of the cause.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class erasure_pending_exception extends \RuntimeException {
    /**
     * Constructor.
     *
     * @param int $deferred students whose erasure failed for lack of a lock and has a retry scheduled
     * @param int $incomplete students whose erasure needs an action: the name is unavailable or the failure is not transient
     * @param \Throwable|null $previous the cause, kept out of the message
     */
    public function __construct(private int $deferred, private int $incomplete, ?\Throwable $previous = null) {
        parent::__construct(
            "The erasure of personal data of the teaching assistant is not complete: {$deferred} student(s) with a retry scheduled, "
            . "{$incomplete} student(s) that need an action (see Site administration > Server > Tasks > Ad hoc tasks).",
            0,
            $previous
        );
    }

    /**
     * Students with a retry scheduled.
     *
     * @return int
     */
    public function deferred(): int {
        return $this->deferred;
    }

    /**
     * Students that need an action.
     *
     * @return int
     */
    public function incomplete(): int {
        return $this->incomplete;
    }
}
