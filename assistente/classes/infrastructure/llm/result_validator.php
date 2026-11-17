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

/**
 * Validates what the LLM returned, before anything is persisted (contracts/llm-port.md; spec-009 §Regras; AT-049).
 *
 * The raw answer is the decoded envelope: `use_case`, `status`, and `data` or `error_code`. An answer that does not decode (not an
 * array), lacks a required field, answers another use than the requested one, or is outside the range or step is turned into a
 * failure of class invalid_response. The validator never rounds or corrects a value.
 *
 * Rules by use (payload minimum of the contract)
 * - material_summary: `summary`, a non-empty text.
 * - quiz_coverage: `coverage`, a number from 0.0 to 1.0 in steps of 0.1.
 * - question_complexity: `complexity`, same rule.
 * - forum_analysis: `forum_summary`, a non-empty text; `difficulties`, a list (possibly empty) of `discussion_id` and `description`,
 *   the id belonging to the discussions of the context and the description non-empty.
 * - pedagogical_synthesis: `content`, a non-empty text (the detailed format is still open in Q-016-02/03).
 *
 * Only the fields of the contract are kept in the validated data.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class result_validator {
    /**
     * Validate a raw answer.
     *
     * @param llm_context $context the request, whose use is the one that must be answered
     * @param mixed $raw the decoded answer of the provider
     * @return structured_result valid with the normalised payload, or an error
     */
    public function validate(llm_context $context, mixed $raw): structured_result {
        $use = $context->usecase;
        if (!is_array($raw)) {
            return $this->invalid($use, 'the answer is not an object');
        }
        if (($raw['use_case'] ?? null) !== $use->value) {
            return $this->invalid($use, 'the answer is for another use than the requested one');
        }

        $status = $raw['status'] ?? null;
        if ($status === structured_result::STATUS_ERROR) {
            $code = $raw['error_code'] ?? null;
            if (!is_string($code) || !in_array($code, structured_result::ERROR_CODES, true)) {
                return $this->invalid($use, 'the error has no valid error_code');
            }
            return structured_result::error($use, $code, 'reported by the provider');
        }
        if ($status !== structured_result::STATUS_VALID) {
            return $this->invalid($use, 'the status is neither valid nor error');
        }

        $data = $raw['data'] ?? null;
        if (!is_array($data)) {
            return $this->invalid($use, 'the data is missing');
        }

        $normalised = match ($use) {
            llm_use_case::MATERIAL_SUMMARY => $this->text_field($data, 'summary'),
            llm_use_case::QUIZ_COVERAGE => $this->tenth_field($data, 'coverage'),
            llm_use_case::QUESTION_COMPLEXITY => $this->tenth_field($data, 'complexity'),
            llm_use_case::FORUM_ANALYSIS => $this->forum($data, $context),
            llm_use_case::PEDAGOGICAL_SYNTHESIS => $this->text_field($data, 'content'),
        };
        return $normalised === null
            ? $this->invalid($use, 'the data does not fit the contract of the use')
            : structured_result::valid($use, $normalised);
    }

    /**
     * An invalid answer.
     *
     * @param llm_use_case $use
     * @param string $reason short reason for the log; it never includes the content of the answer
     * @return structured_result
     */
    private function invalid(llm_use_case $use, string $reason): structured_result {
        return structured_result::error($use, 'invalid_response', $reason);
    }

    /**
     * A required non-empty text field.
     *
     * @param array $data
     * @param string $field
     * @return array|null the payload with only that field; null when invalid
     */
    private function text_field(array $data, string $field): ?array {
        $value = $data[$field] ?? null;
        return (is_string($value) && trim($value) !== '') ? [$field => $value] : null;
    }

    /**
     * A required number from 0.0 to 1.0 in steps of 0.1. Strings, booleans and NaN are not accepted.
     *
     * @param array $data
     * @param string $field
     * @return array|null the payload with only that field; null when invalid
     */
    private function tenth_field(array $data, string $field): ?array {
        $value = $data[$field] ?? null;
        if ((!is_int($value) && !is_float($value)) || is_nan((float) $value) || is_infinite((float) $value)) {
            return null;
        }
        if ($value < 0 || $value > 1) {
            return null;
        }
        $tenths = $value * 10;
        if (abs($tenths - round($tenths)) > 1e-9) {
            return null;
        }
        return [$field => round((float) $value, 1)];
    }

    /**
     * The forum analysis payload.
     *
     * @param array $data
     * @param llm_context $context
     * @return array|null null when invalid
     */
    private function forum(array $data, llm_context $context): ?array {
        $summary = $this->text_field($data, 'forum_summary');
        $difficulties = $data['difficulties'] ?? null;
        if ($summary === null || !is_array($difficulties) || !array_is_list($difficulties)) {
            return null;
        }

        $allowed = $context->discussion_ids();
        $clean = [];
        foreach ($difficulties as $difficulty) {
            if (!is_array($difficulty)) {
                return null;
            }
            $discussionid = $difficulty['discussion_id'] ?? null;
            $description = $difficulty['description'] ?? null;
            if (!is_int($discussionid) || !in_array($discussionid, $allowed, true)) {
                return null;
            }
            if (!is_string($description) || trim($description) === '') {
                return null;
            }
            $clean[] = ['discussion_id' => $discussionid, 'description' => $description];
        }
        return ['forum_summary' => $summary['forum_summary'], 'difficulties' => $clean];
    }
}
