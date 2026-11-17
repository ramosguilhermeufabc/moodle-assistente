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

use local_assistente\domain\llm\llm_context;
use local_assistente\infrastructure\llm\llm_port;
use local_assistente\infrastructure\llm\structured_result;

/**
 * A port that checks every request with the context guard before it reaches the adapter (DEC-34 D3).
 *
 * It makes the privacy rule hold for every consumer and every adapter, simulated or real, without each one having to
 * remember it. A request that identifies a student is not sent: the guard throws, and the caller's attempt fails.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guarded_llm_port implements llm_port {
    /**
     * Constructor.
     *
     * @param llm_port $inner the adapter
     * @param context_guard|null $guard
     * @param string[] $forbiddenliterals names of the course participants, for instance, that must not appear in a request
     */
    public function __construct(
        /** @var llm_port the adapter that receives the clean requests */
        private readonly llm_port $inner,
        /** @var context_guard|null guard; the default one when null */
        private readonly ?context_guard $guard = null,
        /** @var string[] literals that must not appear */
        private readonly array $forbiddenliterals = []
    ) {
    }

    /**
     * The same port, also refusing the given literals (typically the names of the participants of a course).
     *
     * @param string[] $literals
     * @return self
     */
    public function forbidding(array $literals): self {
        return new self($this->inner, $this->guard, array_merge($this->forbiddenliterals, $literals));
    }

    #[\Override]
    public function evaluate(llm_context $context, string $instruction): structured_result {
        ($this->guard ?? new context_guard())->assert_clean($context, $instruction, $this->forbiddenliterals);
        return $this->inner->evaluate($context, $instruction);
    }
}
