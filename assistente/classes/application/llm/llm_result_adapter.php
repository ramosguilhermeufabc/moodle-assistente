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

namespace local_assistente\application\llm;

use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\handler_result;
use local_assistente\infrastructure\llm\structured_result;

/**
 * Turns a failed LLM result into the failure of the attempt of the queue (DEC-32 D3 and D4; plan 009 Q-009-03).
 *
 * Every failure of the port is a failed attempt, subject to the limit of three. Its class (timeout, transport, auth, rate_limit,
 * invalid_response) is recorded in the response of the attempt as the prefix of the text; no state is created.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class llm_result_adapter {
    /**
     * The failure of the attempt that corresponds to a failed result.
     *
     * @param structured_result $result an error result
     * @return handler_result
     * @throws \InvalidArgumentException when the result is valid
     */
    public static function failure_of(structured_result $result): handler_result {
        if ($result->is_valid()) {
            throw new \InvalidArgumentException('A valid result is not a failure.');
        }
        return handler_result::failure(
            attempt_failure::from($result->errorcode),
            $result->errormessage ?? 'the LLM call failed'
        );
    }
}
