<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLClient;
use DeepL\Fixtures;
use DeepL\Translator;
use DeepL\TranslatorOptions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use phpmock\environment\SleepEnvironmentBuilder;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Base class of the offline suite. Requests go to a Guzzle MockHandler through the SDK's own
 * HTTP_CLIENT option, so no socket is ever opened; each test queues its replies and asserts the
 * exact outgoing requests.
 */
abstract class OfflineTestCase extends TestCase
{
    use PHPMock;

    /** @var MockHandler */
    protected $mock;
    /** @var array */
    protected $history = [];
    /** @var \phpmock\environment\MockEnvironment|null */
    private $clock = null;

    /**
     * php-mock ignores a function mock that is defined after the first unmocked call of that
     * function in the namespace, so every mock is defined up front, for every test class.
     * See https://github.com/php-mock/php-mock-phpunit#restrictions
     */
    public static function setUpBeforeClass(): void
    {
        $functions = ['curl_exec', 'curl_getinfo', 'curl_setopt_array', 'curl_version', 'usleep', 'sleep', 'microtime'];
        foreach ($functions as $function) {
            if (!function_exists("DeepL\\$function")) {
                self::defineFunctionMock('DeepL', $function);
            }
        }
    }

    protected function setUp(): void
    {
        $this->mock = new MockHandler();
        $this->history = [];
        $this->blockNetwork();
    }

    /**
     * The built-in cURL transport would reach the network: a test that uses it fails. Only the test
     * of that transport itself replaces this with mocked cURL functions.
     */
    protected function blockNetwork(): void
    {
        $this->getFunctionMock('DeepL', 'curl_exec')->expects($this->never());
    }

    protected function tearDown(): void
    {
        if ($this->clock !== null) {
            $this->clock->disable();
            $this->clock = null;
        }
        Fixtures::removeTempDirs();
        $this->assertSame(0, $this->mock->count(), 'declared responses left unsent');
    }

    /**
     * Makes sleep(), usleep() and microtime() of the SDK run on a fake clock, so polling and backoff
     * tests finish immediately.
     */
    protected function useFakeClock(): void
    {
        $builder = new SleepEnvironmentBuilder();
        $builder->addNamespace('DeepL')->setTimestamp(1700000000);
        $this->clock = $builder->build();
        $this->clock->enable();
    }

    protected function makeTranslator(array $options = [], string $authKey = 'k'): Translator
    {
        return new Translator($authKey, $this->clientOptions($options));
    }

    protected function makeDeepLClient(array $options = []): DeepLClient
    {
        return new DeepLClient('k', $this->clientOptions($options));
    }

    private function clientOptions(array $options): array
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        return array_replace([
            TranslatorOptions::SERVER_URL => 'https://api.deepl.com',
            TranslatorOptions::HTTP_CLIENT => new Client(['handler' => $stack]),
        ], $options);
    }

    /** Queues a JSON reply. */
    protected function reply($data, int $status = 200): void
    {
        $this->mock->append(new Response($status, [], is_string($data) ? $data : json_encode($data)));
    }

    protected function request(int $index = 0): RequestInterface
    {
        $this->assertArrayHasKey($index, $this->history, "request #$index was not sent");
        return $this->history[$index]['request'];
    }

    protected function assertRequestCount(int $count): void
    {
        $this->assertCount($count, $this->history);
    }

    /**
     * Asserts method, path and the exact query string of a request sent to https://api.deepl.com.
     */
    protected function assertRequest(string $method, string $path, int $index = 0, string $query = ''): void
    {
        $request = $this->request($index);
        $this->assertSame($method, $request->getMethod());
        $this->assertSame('api.deepl.com', $request->getUri()->getHost());
        $this->assertSame($path, $request->getUri()->getPath());
        $this->assertSame($query, $request->getUri()->getQuery());
        $this->assertSame('DeepL-Auth-Key k', $request->getHeaderLine('Authorization'));
    }

    /** Decoded JSON body of a request, also checking the Content-Type header. */
    protected function jsonBody(int $index = 0): array
    {
        $request = $this->request($index);
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $decoded = json_decode((string)$request->getBody(), true);
        $this->assertIsArray($decoded);
        return $decoded;
    }

    /** Decoded form body of a request. Not parse_str(), which mangles keys like "a[0].b". */
    protected function formBody(int $index = 0): array
    {
        $request = $this->request($index);
        $this->assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        return Query::parse((string)$request->getBody());
    }

    /**
     * Asserts the multipart fields of a request: every field by name and value, plus the number of
     * parts, so an unexpected extra part fails. The part count includes the file part.
     * @param array $fields Expected text fields, name => value.
     */
    protected function assertMultipart(array $fields, string $fileName, int $index = 0): void
    {
        $request = $this->request($index);
        $this->assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        $body = (string)$request->getBody();
        foreach ($fields as $name => $value) {
            $this->assertStringContainsString("name=\"$name\"\r\n\r\n$value\r\n", $body);
        }
        $this->assertStringContainsString("name=\"file\"; filename=\"$fileName\"", $body);
        $this->assertSame(count($fields) + 1, substr_count($body, 'Content-Disposition: form-data;'));
    }
}
