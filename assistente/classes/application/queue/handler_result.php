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

namespace local_assistente\application\queue;

/**
 * Outcome of a handler: success, or failure with a class and a reason (contracts/queue-processing.md).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class handler_result {
    /**
     * Constructor; use the named constructors.
     *
     * @param bool $success
     * @param attempt_failure|null $failure
     * @param string|null $message the result of a success, or the reason of a failure
     */
    private function __construct(
        /** @var bool whether the attempt succeeded */
        public readonly bool $success,
        /** @var attempt_failure|null class of the failure */
        public readonly ?attempt_failure $failure,
        /** @var string|null result or reason */
        public readonly ?string $message
    ) {
    }

    /**
     * The handler finished and persisted its results.
     *
     * @param string|null $response a short result, kept in the attempt and in the event
     * @return self
     */
    public static function success(?string $response = null): self {
        return new self(true, null, $response);
    }

    /**
     * The handler could not finish.
     *
     * @param attempt_failure $failure
     * @param string $reason
     * @return self
     */
    public static function failure(attempt_failure $failure, string $reason): self {
        return new self(false, $failure, $reason);
    }

    /**
     * Text stored in the response of the attempt.
     *
     * @return string|null
     */
    public function response(): ?string {
        return $this->success ? $this->message : $this->failure->format((string) $this->message);
    }
}
