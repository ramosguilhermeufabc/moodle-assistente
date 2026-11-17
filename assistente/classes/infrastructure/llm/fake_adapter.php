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
use local_assistente\domain\llm\llm_use_case;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * Simulated LLM adapter: the one used by every test and, until the real adapter exists, by the plugin (DEC-33 D1; DEC-48 D1).
 *
 * It goes through the same validation as a real adapter would (result_validator), so a test that scripts a bad answer exercises
 * the real rejection path. It never calls anything external and never reads a credential.
 *
 * Scripting: each call to evaluate() consumes the next scripted behaviour, in order; when none is left it answers with a default
 * valid payload for the use. The default texts start with "[SIMULADO]" so that simulated content cannot be mistaken for the
 * output of a real model.
 *
 * Scripted behaviours
 * - respond_valid(): a valid answer built for whatever use is asked.
 * - respond_with(): a raw answer, exactly as a provider would send it, validated before it is returned.
 * - fail_with(): a failure of one of the five classes of the port (timeout, transport, auth, rate_limit, invalid_response).
 * - crash_with(): an exception, to test that the caller survives it.
 *
 * Every request is kept in $requests for the tests to inspect what would have been sent.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fake_adapter implements llm_port {
    /** @var array[] requests received: each with the context and the instruction */
    public array $requests = [];

    /** @var \Closure[] scripted behaviours; each receives the context and returns a structured_result or throws */
    private array $script = [];

    /** @var result_validator */
    private result_validator $validator;

    /**
     * Constructor.
     *
     * @param result_validator|null $validator
     */
    public function __construct(?result_validator $validator = null) {
        $this->validator = $validator ?? new result_validator();
    }

    /**
     * Script a valid answer with the given data (the use is the one asked at that call).
     *
     * @param array $data the payload of the use
     * @return self
     */
    public function respond_valid(array $data): self {
        return $this->respond_with_callback(static fn(llm_context $context) => [
            'use_case' => $context->usecase->value, 'status' => 'valid', 'data' => $data,
        ]);
    }

    /**
     * Script a raw answer. It is validated as any answer from a provider.
     *
     * @param mixed $raw
     * @return self
     */
    public function respond_with(mixed $raw): self {
        return $this->respond_with_callback(static fn() => $raw);
    }

    /**
     * Script a raw answer built from the context.
     *
     * @param \Closure $build receives the llm_context and returns the raw answer
     * @return self
     */
    public function respond_with_callback(\Closure $build): self {
        $this->script[] = fn(llm_context $context) => $this->validator->validate($context, $build($context));
        return $this;
    }

    /**
     * Script a failure of the port.
     *
     * @param string $errorcode one of structured_result::ERROR_CODES
     * @param string $message short reason
     * @return self
     */
    public function fail_with(string $errorcode, string $message = 'simulated failure'): self {
        if (!in_array($errorcode, structured_result::ERROR_CODES, true)) {
            throw new \InvalidArgumentException("'{$errorcode}' is not a failure class of the LLM port.");
        }
        $this->script[] = static fn(llm_context $context) => structured_result::error($context->usecase, $errorcode, $message);
        return $this;
    }

    /**
     * Script an exception.
     *
     * @param \Throwable $exception
     * @return self
     */
    public function crash_with(\Throwable $exception): self {
        $this->script[] = static function () use ($exception) {
            throw $exception;
        };
        return $this;
    }

    /**
     * Number of calls received.
     *
     * @return int
     */
    public function calls(): int {
        return count($this->requests);
    }

    #[\Override]
    public function evaluate(llm_context $context, string $instruction): structured_result {
        unit_of_work::assert_no_active_transaction();
        $this->requests[] = ['context' => $context, 'instruction' => $instruction];

        $behaviour = array_shift($this->script);
        if ($behaviour !== null) {
            return $behaviour($context);
        }
        return $this->validator->validate($context, [
            'use_case' => $context->usecase->value,
            'status' => 'valid',
            'data' => self::default_data($context->usecase, $context),
        ]);
    }

    /**
     * Default payload of a use, marked as simulated.
     *
     * @param llm_use_case $use
     * @param llm_context $context
     * @return array
     */
    private static function default_data(llm_use_case $use, llm_context $context): array {
        return match ($use) {
            llm_use_case::MATERIAL_SUMMARY => ['summary' => '[SIMULADO] Resumo do material.'],
            llm_use_case::QUIZ_COVERAGE => ['coverage' => 0.5],
            llm_use_case::QUESTION_COMPLEXITY => ['complexity' => 0.5],
            llm_use_case::FORUM_ANALYSIS => ['forum_summary' => '[SIMULADO] Resumo do fórum.', 'difficulties' => []],
            llm_use_case::PEDAGOGICAL_SYNTHESIS => ['content' => '[SIMULADO] Síntese pedagógica.'],
        };
    }
}
