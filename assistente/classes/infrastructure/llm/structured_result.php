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

namespace local_assistente\infrastructure\llm;

use local_assistente\domain\llm\llm_use_case;

/**
 * Envelope returned by the LLM port (contracts/llm-port.md, "structured_result").
 *
 * - `use_case`: the use that was requested.
 * - `status`: valid or error.
 * - `data`: the payload of the use, only when valid.
 * - `error_code`: the class of the failure, only when error: timeout, transport, auth, rate_limit or invalid_response.
 *
 * A failure is a result, not an exception: the consumer turns it into a failed attempt of the queue (llm_result_adapter).
 * A result is only built by the named constructors, so an error never carries data and a valid one never carries a code.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class structured_result {
    /** @var string status of an answer that passed the validation */
    public const STATUS_VALID = 'valid';

    /** @var string status of a failure */
    public const STATUS_ERROR = 'error';

    /** @var string[] classes of failure of the port (the contract lists these five) */
    public const ERROR_CODES = ['timeout', 'transport', 'auth', 'rate_limit', 'invalid_response'];

    /**
     * Constructor; use the named constructors.
     *
     * @param llm_use_case $usecase
     * @param string $status
     * @param array|null $data
     * @param string|null $errorcode
     * @param string|null $errormessage
     */
    private function __construct(
        /** @var llm_use_case the requested use */
        public readonly llm_use_case $usecase,
        /** @var string valid or error */
        public readonly string $status,
        /** @var array|null payload, only when valid */
        public readonly ?array $data,
        /** @var string|null class of the failure, only when error */
        public readonly ?string $errorcode,
        /** @var string|null short reason for the log; never the content of the answer */
        public readonly ?string $errormessage
    ) {
    }

    /**
     * A valid answer.
     *
     * @param llm_use_case $usecase
     * @param array $data payload already validated for the use
     * @return self
     */
    public static function valid(llm_use_case $usecase, array $data): self {
        return new self($usecase, self::STATUS_VALID, $data, null, null);
    }

    /**
     * A failure.
     *
     * @param llm_use_case $usecase
     * @param string $errorcode one of ERROR_CODES
     * @param string|null $message short reason for the log
     * @return self
     * @throws \InvalidArgumentException when the code is not one of the contract
     */
    public static function error(llm_use_case $usecase, string $errorcode, ?string $message = null): self {
        if (!in_array($errorcode, self::ERROR_CODES, true)) {
            throw new \InvalidArgumentException("'{$errorcode}' is not a failure class of the LLM port.");
        }
        return new self($usecase, self::STATUS_ERROR, null, $errorcode, $message);
    }

    /**
     * Whether the answer is valid.
     *
     * @return bool
     */
    public function is_valid(): bool {
        return $this->status === self::STATUS_VALID;
    }
}
