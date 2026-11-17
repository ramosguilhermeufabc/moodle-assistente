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

namespace local_assistente\infrastructure\llm;

use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use local_assistente\infrastructure\persistence\unit_of_work;

/**
 * Real LLM adapter: one HTTP call to the Chat Completions API of the configured provider (T134; DEC-60; DEC-69; DEC-70).
 *
 * Moodle API: \core\http_client (it checks the block list of the site, curlsecurityblockedhosts and curlsecurityallowedport, also
 * on redirects, so no IP filter is written here), get_config() and \core\encryption (the key is saved by
 * admin_setting_encryptedpassword).
 *
 * Settings of the site, read at every call and with no default: llmurl (the complete endpoint), llmmodel and llmapikey.
 * - URL or model missing, or URL refused (not https, or with user or password): failure `transport`, nothing is sent.
 * - Key missing: failure `auth`, nothing is sent.
 * - The key travels only in the Authorization header. It is never logged and never put in a message.
 * - Zero Data Retention (DEC-71): while the site setting llmzdr is on (the default), every request carries provider.zdr = true and
 *   provider.data_collection = "deny" and is never repeated without them. When the administrator turns it off, the provider
 *   object is not sent at all.
 * - Redirects are not followed.
 *
 * Failures are returned, not thrown (llm_port). Nothing is logged: no prompt, no answer, no key, no personal data.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class http_adapter implements llm_port {
    /** @var int seconds before the call is given up; below the 15 minutes of the queue cycle (DEC-32 D5; Q-009-03, 03/11/2026) */
    public const TIMEOUT = 120;

    /** @var array extra options of \core\http_client (tests pass `mock`) */
    private array $httpoptions;

    /** @var result_validator */
    private result_validator $validator;

    /**
     * Constructor.
     *
     * @param array $httpoptions extra options of the HTTP client; used by tests to simulate the answers
     * @param result_validator|null $validator
     */
    public function __construct(array $httpoptions = [], ?result_validator $validator = null) {
        $this->httpoptions = $httpoptions;
        $this->validator = $validator ?? new result_validator();
    }

    /**
     * Simple check of the endpoint: https, and no user or password in it. Used when the setting is saved and before each call.
     *
     * @param string $url
     * @return bool
     */
    public static function is_acceptable_url(string $url): bool {
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    #[\Override]
    public function evaluate(llm_context $context, string $instruction): structured_result {
        unit_of_work::assert_no_active_transaction();
        $use = $context->usecase;

        $url = trim((string) get_config('local_assistente', 'llmurl'));
        $model = trim((string) get_config('local_assistente', 'llmmodel'));
        if ($url === '' || $model === '') {
            return structured_result::error($use, 'transport', 'URL or model not configured');
        }
        if (!self::is_acceptable_url($url)) {
            return structured_result::error($use, 'transport', 'URL refused');
        }
        $key = $this->read_key();
        if ($key === '') {
            return structured_result::error($use, 'auth', 'API key not configured');
        }

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $instruction . "\n\n" . self::format_directive($use)],
                ['role' => 'user', 'content' => json_encode(['use_case' => $use->value, 'data' => $context->data],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
        ];
        if (self::zdr_required()) {
            $body['provider'] = ['zdr' => true, 'data_collection' => 'deny'];
        }

        try {
            $client = new \core\http_client($this->httpoptions);
            $response = $client->request('POST', $url, [
                'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout' => self::TIMEOUT,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            $timedout = (int) ($e->getHandlerContext()['errno'] ?? 0) === 28;
            return structured_result::error($use, $timedout ? 'timeout' : 'transport', $timedout ? 'timeout' : 'network failure');
        } catch (\Throwable $e) {
            // Includes the request blocked by the block list of Moodle. The message is not kept: it may name the URL.
            return structured_result::error($use, 'transport', 'request failed');
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status > 299) {
            return structured_result::error($use, self::class_of_status($status), 'HTTP ' . $status);
        }
        return $this->convert((string) $response->getBody(), $context);
    }

    /**
     * Whether the requests ask for Zero Data Retention: the site setting llmzdr (DEC-71), on unless the administrator turned it off.
     * A setting that was never saved counts as on.
     *
     * @return bool
     */
    private static function zdr_required(): bool {
        return get_config('local_assistente', 'llmzdr') !== '0';
    }

    /**
     * The key, decrypted with the native API; empty when there is none.
     *
     * @return string
     */
    private function read_key(): string {
        $stored = (string) get_config('local_assistente', 'llmapikey');
        if ($stored === '') {
            return '';
        }
        try {
            return trim(\core\encryption::decrypt($stored));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Class of failure of an HTTP status that is not 2xx (contracts/llm-port.md, "Mapeamento de falhas").
     *
     * @param int $status
     * @return string
     */
    private static function class_of_status(int $status): string {
        return match (true) {
            in_array($status, [401, 402, 403], true) => 'auth',
            $status === 429 => 'rate_limit',
            in_array($status, [408, 524], true) => 'timeout',
            default => 'transport',
        };
    }

    /**
     * Converts the answer of Chat Completions to the structured_result (contracts/llm-port.md, DEC-70).
     *
     * @param string $body
     * @param llm_context $context
     * @return structured_result
     */
    private function convert(string $body, llm_context $context): structured_result {
        $use = $context->usecase;
        $invalid = static fn(string $why) => structured_result::error($use, 'invalid_response', $why);

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return $invalid('the body is not JSON');
        }
        if (!empty($json['error'])) {
            return $invalid('the response has an error');
        }
        $choices = $json['choices'] ?? null;
        if (!is_array($choices) || count($choices) !== 1 || !is_array($choices[0] ?? null)) {
            return $invalid('not exactly one choice');
        }
        $choice = $choices[0];
        if (($choice['finish_reason'] ?? null) !== 'stop') {
            return $invalid('the answer did not finish normally');
        }
        $message = $choice['message'] ?? null;
        if (!is_array($message) || !empty($message['refusal']) || !is_string($message['content'] ?? null)
                || trim($message['content']) === '') {
            return $invalid('the message has no text');
        }
        // Some models wrap the object in one Markdown fence (```json ... ```); only that exact wrapper is removed (DEC-70, amendment).
        $text = trim($message['content']);
        if (preg_match('/\A```(?:json)?[ \t]*\R(.*)\R```\z/s', $text, $fenced)) {
            $text = trim($fenced[1]);
        }
        $data = json_decode($text, true);
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            return $invalid('the text is not a JSON object');
        }
        return $this->validator->validate($context, ['use_case' => $use->value, 'status' => 'valid', 'data' => $data]);
    }

    /**
     * Technical sentence that tells the model the shape of the answer of the use (the fields of contracts/llm-port.md).
     *
     * @param llm_use_case $use
     * @return string
     */
    private static function format_directive(llm_use_case $use): string {
        $fields = match ($use) {
            llm_use_case::MATERIAL_SUMMARY => '"summary" (text)',
            llm_use_case::QUIZ_COVERAGE => '"coverage" (number from 0.0 to 1.0, in steps of 0.1)',
            llm_use_case::QUESTION_COMPLEXITY => '"complexity" (number from 0.0 to 1.0, in steps of 0.1)',
            llm_use_case::FORUM_ANALYSIS => '"forum_summary" (text) and "difficulties" (list of objects with "discussion_id", '
                . 'an integer taken from "discussion_ids" of the input, and "description", text)',
            llm_use_case::PEDAGOGICAL_SYNTHESIS => '"content" (text)',
        };
        return 'Reply only with one JSON object, with no other text, whose fields are exactly: ' . $fields . '.';
    }
}
