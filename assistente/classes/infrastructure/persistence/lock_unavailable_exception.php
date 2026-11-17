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
 * The lock of a resource could not be acquired within the timeout.
 *
 * Callers treat it as a transient failure of the unit (the work is not started). It names the resource whose lock did not come
 * (resource()), so that a caller can tell which lock was busy without reading the message.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lock_unavailable_exception extends \RuntimeException {
    /**
     * Constructor.
     *
     * @param string $message
     * @param string $resource the resource whose lock did not come; empty when not known
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = '', private readonly string $resource = '', int $code = 0, ?\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * The resource whose lock did not come.
     *
     * @return string empty when not known
     */
    public function resource(): string {
        return $this->resource;
    }
}
