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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests of the real HTTP adapter (T134; quickstart 009 Q11 to Q16; DEC-60; DEC-69). No network: the answers come from the mock
 * handler of \core\http_client, and the block list of Moodle is the real one.
 *
 * The "key" below is a made-up string for the tests; it is not a credential.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(http_adapter::class)]
final class http_adapter_test extends \advanced_testcase {
    /** @var string endpoint used by the tests */
    private const URL = 'https://openrouter.ai/api/v1/chat/completions';

    /** @var string made-up key */
    private const KEY = 'test-key-0123456789';

    /** @var string made-up model */
    private const MODEL = 'vendor/test-model';

    /**
     * Site settings of a working installation.
     *
     * @param array $override values to replace; null removes the setting
     */
    private function configure(array $override = []): void {
        $this->resetAfterTest();
        $values = ['llmurl' => self::URL, 'llmmodel' => self::MODEL, 'llmapikey' => \core\encryption::encrypt(self::KEY)];
        foreach (array_merge($values, $override) as $name => $value) {
            set_config($name, $value, 'local_assistente');
        }
        // The block list of Moodle as the default installation has it; the test site starts with it empty.
        set_config('curlsecurityblockedhosts', "127.0.0.0/8\n192.168.0.0/16\n10.0.0.0/8\n172.16.0.0/12\n0.0.0.0\nlocalhost\n169.254.169.254\n0000::1");
        set_config('curlsecurityallowedport', "443\n80");
    }

    /**
     * An answer of the Chat Completions API with the given text (DEC-70).
     *
     * @param string|null $text text of the assistant
     * @param array $override fields of the top level to replace
     * @return Response
     */
    private static function api_response(?string $text, array $override = []): Response {
        $body = array_merge([
            'id' => 'gen-1', 'object' => 'chat.completion', 'created' => 1, 'model' => self::MODEL,
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => $text]]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
        ], $override);
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    /**
     * Runs one call.
     *
     * @param MockHandler $mock
     * @param llm_use_case $use
     * @param array $data
     * @return structured_result
     */
    private function call(MockHandler $mock, llm_use_case $use = llm_use_case::MATERIAL_SUMMARY, array $data = ['text' => 'x']): structured_result {
        return (new http_adapter(['mock' => $mock]))->evaluate(new llm_context($use, $data), 'Do it in Portuguese.');
    }

    /**
     * Q11: URL or model missing: nothing is sent and the failure is transport.
     */
    public function test_url_or_model_missing_is_transport_without_request(): void {
        foreach (['llmurl', 'llmmodel'] as $missing) {
            foreach ([null, '', '   '] as $value) {
                $this->configure([$missing => $value]);
                $mock = new MockHandler([self::api_response('{"summary":"x"}')]);
                $result = $this->call($mock);
                $this->assertFalse($result->is_valid());
                $this->assertSame('transport', $result->errorcode);
                $this->assertNull($mock->getLastRequest());
                $this->assertCount(1, $mock);
            }
        }
    }

    /**
     * Key missing (or not readable): auth, nothing is sent.
     */
    public function test_key_missing_is_auth_without_request(): void {
        foreach ([null, '', 'not-an-encrypted-value'] as $value) {
            $this->configure(['llmapikey' => $value]);
            $mock = new MockHandler([self::api_response('{"summary":"x"}')]);
            $result = $this->call($mock);
            $this->assertSame('auth', $result->errorcode);
            $this->assertNull($mock->getLastRequest());
        }
    }

    /**
     * @return array URLs that must be refused
     */
    public static function refused_urls(): array {
        return [
            'http' => ['http://openrouter.ai/api/v1/chat/completions'],
            'user and password' => ['https://user:secret@openrouter.ai/api/v1/chat/completions'],
            'user only' => ['https://user@openrouter.ai/api/v1/chat/completions'],
            'no scheme' => ['openrouter.ai/api/v1/chat/completions'],
            'loopback' => ['https://127.0.0.1/api'],
            'localhost' => ['https://localhost/api'],
            'private 10' => ['https://10.0.0.5/api'],
            'private 192.168' => ['https://192.168.1.10/api'],
            'private 172.16' => ['https://172.16.0.1/api'],
            'cloud metadata' => ['https://169.254.169.254/latest/meta-data'],
            'port outside the allowed ones' => ['https://openrouter.ai:8443/api'],
        ];
    }

    /**
     * Q12: a refused URL: transport, no request, and the key is not sent.
     *
     * @param string $url
     */
    #[DataProvider('refused_urls')]
    public function test_refused_url_sends_nothing(string $url): void {
        $this->configure(['llmurl' => $url]);
        $mock = new MockHandler([self::api_response('{"summary":"x"}')]);
        $result = $this->call($mock);
        // Moodle reports a blocked destination with debugging(); that is its own check at work.
        $this->resetDebugging();
        $this->assertSame('transport', $result->errorcode);
        $this->assertNull($mock->getLastRequest());
        $this->assertCount(1, $mock);
    }

    /**
     * The simple check of the URL, used also when the setting is saved.
     */
    public function test_is_acceptable_url(): void {
        $this->assertTrue(http_adapter::is_acceptable_url(self::URL));
        $this->assertFalse(http_adapter::is_acceptable_url('http://openrouter.ai/x'));
        $this->assertFalse(http_adapter::is_acceptable_url('https://u:p@openrouter.ai/x'));
        $this->assertFalse(http_adapter::is_acceptable_url(''));
        $this->assertFalse(http_adapter::is_acceptable_url('https://'));
    }

    /**
     * Q12: a redirect is not followed.
     */
    public function test_redirect_is_not_followed(): void {
        $this->configure();
        $mock = new MockHandler([
            new Response(302, ['Location' => 'https://elsewhere.example/steal']),
            self::api_response('{"summary":"x"}'),
        ]);
        $result = $this->call($mock);
        $this->assertSame('transport', $result->errorcode);
        $this->assertCount(1, $mock);
    }

    /**
     * Q13: what is sent.
     */
    public function test_request_content(): void {
        $this->configure();
        $mock = new MockHandler([self::api_response('{"summary":"Resumo."}')]);
        $this->call($mock, llm_use_case::MATERIAL_SUMMARY, ['text' => 'conteudo do material']);

        $request = $mock->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::URL, (string) $request->getUri());
        $this->assertSame('Bearer ' . self::KEY, $request->getHeaderLine('Authorization'));
        $body = (string) $request->getBody();
        $this->assertStringNotContainsString(self::KEY, $body);
        $this->assertStringNotContainsString(self::KEY, (string) $request->getUri());
        $json = json_decode($body, true);
        $this->assertSame(self::MODEL, $json['model']);
        $this->assertTrue($json['provider']['zdr']);
        $this->assertSame('deny', $json['provider']['data_collection']);
        $this->assertCount(2, $json['messages']);
        $this->assertSame('system', $json['messages'][0]['role']);
        $this->assertStringStartsWith('Do it in Portuguese.', $json['messages'][0]['content']);
        $this->assertStringContainsString('"summary"', $json['messages'][0]['content']);
        $this->assertSame('user', $json['messages'][1]['role']);
        $this->assertSame(['use_case' => 'material_summary', 'data' => ['text' => 'conteudo do material']],
            json_decode($json['messages'][1]['content'], true));
        $this->assertArrayNotHasKey('instructions', $json);
        $this->assertArrayNotHasKey('input', $json);
        $this->assertArrayNotHasKey('stream', $json);
        $this->assertArrayNotHasKey('tools', $json);
    }

    /**
     * DEC-71: ZDR is a site setting. On (or never saved) the provider object is sent; off, it is not sent at all, and the call is
     * still made once.
     */
    public function test_zdr_is_a_site_setting(): void {
        foreach ([null, '1'] as $value) {
            $this->configure(['llmzdr' => $value]);
            $mock = new MockHandler([self::api_response('{"summary":"x"}')]);
            $this->call($mock);
            $json = json_decode((string) $mock->getLastRequest()->getBody(), true);
            $this->assertSame(['zdr' => true, 'data_collection' => 'deny'], $json['provider'], 'setting: ' . var_export($value, true));
        }

        $this->configure(['llmzdr' => '0']);
        $mock = new MockHandler([self::api_response('{"summary":"Resumo."}')]);
        $result = $this->call($mock);
        $this->assertTrue($result->is_valid());
        $json = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertArrayNotHasKey('provider', $json);
        $this->assertStringNotContainsString('zdr', (string) $mock->getLastRequest()->getBody());
        $this->assertStringNotContainsString(self::KEY, (string) $mock->getLastRequest()->getBody());
        $this->assertSame(self::MODEL, $json['model']);
    }

    /**
     * With ZDR on, an HTTP error is never retried without the parameters; with it off there is also a single call.
     */
    public function test_zdr_on_is_never_relaxed_after_a_failure(): void {
        $this->configure(['llmzdr' => '1']);
        $mock = new MockHandler([new Response(404, [], '{"error":{"message":"No endpoints found matching your data policy"}}'),
            self::api_response('{"summary":"x"}')]);
        $result = $this->call($mock);
        $this->assertSame('transport', $result->errorcode);
        $this->assertCount(1, $mock);
        $this->assertTrue(json_decode((string) $mock->getLastRequest()->getBody(), true)['provider']['zdr']);
    }

    /**
     * Settings are read at every call.
     */
    public function test_settings_are_read_at_every_call(): void {
        $this->configure();
        $mock = new MockHandler([self::api_response('{"summary":"a"}'), self::api_response('{"summary":"b"}')]);
        $adapter = new http_adapter(['mock' => $mock]);
        $context = new llm_context(llm_use_case::MATERIAL_SUMMARY, ['text' => 'x']);
        $adapter->evaluate($context, 'i');
        set_config('llmmodel', 'other/model', 'local_assistente');
        $adapter->evaluate($context, 'i');
        $this->assertSame('other/model', json_decode((string) $mock->getLastRequest()->getBody(), true)['model']);
    }

    /**
     * @return array a valid text for each use, and the data of the context
     */
    public static function valid_answers(): array {
        return [
            'material_summary' => [llm_use_case::MATERIAL_SUMMARY, ['text' => 'x'], '{"summary":"Resumo."}', ['summary' => 'Resumo.']],
            'quiz_coverage' => [llm_use_case::QUIZ_COVERAGE, ['text' => 'x'], '{"coverage":0.7}', ['coverage' => 0.7]],
            'question_complexity' => [llm_use_case::QUESTION_COMPLEXITY, ['text' => 'x'], '{"complexity":0.3}', ['complexity' => 0.3]],
            'forum_analysis' => [llm_use_case::FORUM_ANALYSIS, ['discussion_ids' => [5]],
                '{"forum_summary":"Resumo.","difficulties":[{"discussion_id":5,"description":"Duvida."}]}',
                ['forum_summary' => 'Resumo.', 'difficulties' => [['discussion_id' => 5, 'description' => 'Duvida.']]]],
            'pedagogical_synthesis' => [llm_use_case::PEDAGOGICAL_SYNTHESIS, ['text' => 'x'], '{"content":"Sintese."}', ['content' => 'Sintese.']],
        ];
    }

    /**
     * Q14: a valid answer for each of the five uses.
     *
     * @param llm_use_case $use
     * @param array $contextdata
     * @param string $text
     * @param array $expected
     */
    #[DataProvider('valid_answers')]
    public function test_valid_answer_for_each_use(llm_use_case $use, array $contextdata, string $text, array $expected): void {
        $this->configure();
        $result = $this->call(new MockHandler([self::api_response($text)]), $use, $contextdata);
        $this->assertTrue($result->is_valid(), (string) $result->errormessage);
        $this->assertSame($use, $result->usecase);
        $this->assertSame($expected, $result->data);
    }

    /**
     * DEC-70 (amendment): one Markdown fence around the object is accepted; nothing else around it is.
     */
    public function test_one_markdown_fence_around_the_object_is_accepted(): void {
        $this->configure();
        $object = '{"summary":"Resumo."}';
        foreach (["```json\n{$object}\n```", "```\n{$object}\n```", "  ```json\r\n{$object}\r\n```\n"] as $text) {
            $result = $this->call(new MockHandler([self::api_response($text)]));
            $this->assertTrue($result->is_valid(), json_encode($text));
            $this->assertSame(['summary' => 'Resumo.'], $result->data);
        }
        foreach (["Here it is:\n```json\n{$object}\n```", "```json\n{$object}\n```\nDone.", "```json\n{$object}", "{$object}\n```", "```json\n```"] as $text) {
            $result = $this->call(new MockHandler([self::api_response($text)]));
            $this->assertSame('invalid_response', $result->errorcode, json_encode($text));
        }
    }

    /**
     * @return array answers that must be invalid_response
     */
    public static function invalid_answers(): array {
        $choice = static fn(array $override) => ['choices' => [['index' => 0, 'finish_reason' => 'stop',
            'message' => array_merge(['role' => 'assistant', 'content' => '{"summary":"x"}'], $override)]]];
        $text = '{"summary":"x"}';
        return [
            'not json' => [new Response(200, [], 'not json')],
            'empty body' => [new Response(200, [], '')],
            'finish_reason length' => [self::api_response($text, ['choices' => [['index' => 0, 'finish_reason' => 'length',
                'message' => ['role' => 'assistant', 'content' => $text]]]])],
            'finish_reason missing' => [self::api_response($text, ['choices' => [['index' => 0,
                'message' => ['role' => 'assistant', 'content' => $text]]]])],
            'error not null' => [self::api_response($text, ['error' => ['code' => 500, 'message' => 'boom']])],
            'no choices' => [self::api_response($text, ['choices' => []])],
            'choices missing' => [self::api_response($text, ['choices' => null])],
            'two choices' => [self::api_response($text, ['choices' => array_merge($choice([])['choices'], $choice([])['choices'])])],
            'no message' => [self::api_response($text, ['choices' => [['index' => 0, 'finish_reason' => 'stop']]])],
            'refusal' => [self::api_response($text, $choice(['content' => null, 'refusal' => 'no']))],
            'content null' => [self::api_response(null)],
            'content is a list' => [self::api_response($text, $choice(['content' => [['type' => 'text', 'text' => $text]]]))],
            'empty text' => [self::api_response('')],
            'blank text' => [self::api_response("  \n")],
            'text is not json' => [self::api_response('Here is the summary')],
            'text is a list' => [self::api_response('["a"]')],
            'text is a scalar' => [self::api_response('"a"')],
            'wrong fields' => [self::api_response('{"coverage":0.5}')],
            'empty summary' => [self::api_response('{"summary":""}')],
        ];
    }

    /**
     * Q15: an answer that cannot be used is invalid_response.
     *
     * @param Response $response
     */
    #[DataProvider('invalid_answers')]
    public function test_invalid_answer(Response $response): void {
        $this->configure();
        $result = $this->call(new MockHandler([$response]));
        $this->assertFalse($result->is_valid());
        $this->assertSame('invalid_response', $result->errorcode);
        $this->assertNull($result->data);
    }

    /**
     * An answer out of the range of the validator is rejected, not corrected.
     */
    public function test_values_out_of_range_are_rejected(): void {
        $this->configure();
        foreach (['{"coverage":1.5}', '{"coverage":0.55}', '{"coverage":"0.5"}'] as $text) {
            $result = $this->call(new MockHandler([self::api_response($text)]), llm_use_case::QUIZ_COVERAGE);
            $this->assertSame('invalid_response', $result->errorcode, $text);
        }
        $result = $this->call(new MockHandler([self::api_response(
            '{"forum_summary":"r","difficulties":[{"discussion_id":99,"description":"d"}]}')]),
            llm_use_case::FORUM_ANALYSIS, ['discussion_ids' => [5]]);
        $this->assertSame('invalid_response', $result->errorcode);
    }

    /**
     * @return array HTTP status and expected class
     */
    public static function statuses(): array {
        return [
            '401' => [401, 'auth'], '402' => [402, 'auth'], '403' => [403, 'auth'],
            '429' => [429, 'rate_limit'],
            '408' => [408, 'timeout'], '524' => [524, 'timeout'],
            '400' => [400, 'transport'], '404' => [404, 'transport'], '413' => [413, 'transport'], '422' => [422, 'transport'],
            '500' => [500, 'transport'], '502' => [502, 'transport'], '503' => [503, 'transport'], '529' => [529, 'transport'],
            '301' => [301, 'transport'],
        ];
    }

    /**
     * Q16: HTTP status to the class of the contract; one request only (never repeated without the ZDR parameters).
     *
     * @param int $status
     * @param string $class
     */
    #[DataProvider('statuses')]
    public function test_http_status_mapping(int $status, string $class): void {
        $this->configure();
        $mock = new MockHandler([new Response($status, [], '{"error":{"message":"no zdr endpoint"}}'), self::api_response('{"summary":"x"}')]);
        $result = $this->call($mock);
        $this->assertFalse($result->is_valid());
        $this->assertSame($class, $result->errorcode);
        $this->assertCount(1, $mock);
        $json = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertTrue($json['provider']['zdr']);
        $this->assertSame('deny', $json['provider']['data_collection']);
    }

    /**
     * Timeout of the client is timeout; other network failures are transport. The timeout is below the cycle of 15 minutes.
     */
    public function test_network_failures(): void {
        $this->configure();
        $request = new Request('POST', self::URL);
        $timeout = new ConnectException('cURL error 28: Operation timed out', $request, null, ['errno' => 28]);
        $this->assertSame('timeout', $this->call(new MockHandler([$timeout]))->errorcode);
        $dns = new ConnectException('cURL error 6: Could not resolve host', $request, null, ['errno' => 6]);
        $this->assertSame('transport', $this->call(new MockHandler([$dns]))->errorcode);
        $this->assertSame(120, http_adapter::TIMEOUT);
        $this->assertLessThan(15 * 60, http_adapter::TIMEOUT);
    }

    /**
     * The key never appears in a message of failure, nor does the URL.
     */
    public function test_key_never_in_a_failure_message(): void {
        $this->configure();
        $request = new Request('POST', self::URL);
        $answers = [
            new Response(401, [], 'bad key ' . self::KEY), new Response(500, [], self::KEY), new Response(200, [], self::KEY),
            new ConnectException('failed ' . self::KEY . ' ' . self::URL, $request, null, ['errno' => 7]),
            new \RuntimeException('boom ' . self::KEY),
        ];
        foreach ($answers as $answer) {
            $result = $this->call(new MockHandler([$answer]));
            $this->assertFalse($result->is_valid());
            $this->assertStringNotContainsString(self::KEY, (string) $result->errormessage);
            $this->assertStringNotContainsString('openrouter', (string) $result->errormessage);
        }
    }

    /**
     * The adapter refuses to run inside a database transaction (llm_port contract).
     */
    public function test_refuses_to_run_in_a_transaction(): void {
        global $DB;
        $this->configure();
        $transaction = $DB->start_delegated_transaction();
        try {
            $this->call(new MockHandler([self::api_response('{"summary":"x"}')]));
            $this->fail('An exception was expected.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e);
        } finally {
            $transaction->allow_commit();
        }
    }
}
