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
 * Class of the failure of an attempt, recorded in the attempt (plan 009 Q-009-03; DEC-32 D3 and D4).
 *
 * Every class counts the same: it is a failed attempt, subject to the limit of three. The class creates no state; it is
 * written as the prefix of the response of the failed attempt ("rate_limit: ...") so that it can be read back.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum attempt_failure: string {
    /** The external call timed out. */
    case TIMEOUT = 'timeout';
    /** The external call failed in transport (connection, DNS, HTTP). */
    case TRANSPORT = 'transport';
    /** The external service refused the credential. */
    case AUTH = 'auth';
    /** The external service limited the rate. */
    case RATE_LIMIT = 'rate_limit';
    /** The response did not pass the validation of the contract. */
    case INVALID_RESPONSE = 'invalid_response';
    /** Any other failure of the handler, including an unexpected exception. */
    case TECHNICAL = 'technical';

    /**
     * Text to store in the response of the attempt.
     *
     * @param string $message
     * @return string
     */
    public function format(string $message): string {
        return $this->value . ': ' . $message;
    }

    /**
     * Class recorded in the response of an attempt.
     *
     * @param string|null $response
     * @return self|null null when the response does not start with a known class
     */
    public static function from_response(?string $response): ?self {
        if ($response === null || !str_contains($response, ':')) {
            return null;
        }
        return self::tryFrom(strstr($response, ':', true));
    }
}
