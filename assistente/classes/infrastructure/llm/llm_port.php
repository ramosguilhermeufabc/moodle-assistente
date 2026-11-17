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

use local_assistente\domain\llm\llm_context;

/**
 * The only door to the LLM: `evaluate(context, instruction) -> structured_result` (spec-009; DEC-33 D1; DEC-34 D1).
 *
 * The structure is replaceable: the simulated adapter (fake_adapter) is used by every test and while there is no real one; the
 * real adapter is a later delivery that depends on the choice of provider and model (CL-19).
 *
 * Contract of every implementation
 * - No database transaction may be open when it is called (spec-007). Implementations call
 *   unit_of_work::assert_no_active_transaction().
 * - It returns a structured_result. Failures (timeout, transport, auth, rate limit, invalid answer) are returned as results with
 *   the class of the failure; they do not throw. Every failure is a failed attempt of the queue.
 * - An answer is only returned as valid after result_validator accepted it for the requested use. Nothing is rounded or
 *   corrected.
 * - The credential of a real adapter comes only from the site configuration saved encrypted by the Moodle API, never in clear in the
 *   database and never from the Git (DEC-69, which changed DEC-34 D2). The context sent holds no identifier of a student (DEC-34 D3), enforced by guarded_llm_port.
 * - Never used for counts, averages, standard deviation, minimum, maximum, attendance or criteria (spec-009 §Regras).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface llm_port {
    /**
     * Ask the LLM.
     *
     * @param llm_context $context the use that is asked and its data
     * @param string $instruction what the model must do, with the language of the answer
     * @return structured_result
     */
    public function evaluate(llm_context $context, string $instruction): structured_result;
}
