<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\ConnectionException;
use DeepL\DeepLAdminClient;
use DeepL\DeepLClient;
use DeepL\DocumentHandle;
use DeepL\Fixtures;
use DeepL\TranslateDocumentOptions;
use DeepL\TranslatorOptions;

/**
 * Exercises the built-in cURL transport with the cURL functions mocked, so the request it builds is
 * asserted without any network. The other offline tests use a PSR-18 client and never reach this code.
 */
class CurlTransportTest extends OfflineTestCase
{
    /** The cURL functions are mocked in this class, so cURL is not blocked here. */
    protected function blockNetwork(): void
    {
    }

    /**
     * Runs the call against mocked cURL functions and returns the options that reached curl_setopt_array.
     */
    private function captureCurlOptions(
        callable $call,
        string $body = '{}',
        array $clientOptions = [],
        ?callable $inspect = null
    ): array {
        $captured = null;
        $this->getFunctionMock('DeepL', 'curl_setopt_array')->expects($this->once())
            ->willReturnCallback(function ($handle, array $options) use (&$captured, $inspect) {
                $captured = $options;
                if ($inspect !== null) {
                    $inspect($options);
                }
                return true;
            });
        $this->getFunctionMock('DeepL', 'curl_exec')->expects($this->once())->willReturn($body);
        $this->getFunctionMock('DeepL', 'curl_getinfo')->expects($this->once())->willReturn(200);

        $call(new DeepLClient(
            'k',
            array_replace([TranslatorOptions::SERVER_URL => 'https://api.deepl.com'], $clientOptions)
        ));

        $this->assertIsArray($captured);
        return $captured;
    }

    private function headers(array $options): array
    {
        return $options[CURLOPT_HTTPHEADER];
    }

    public function testSendsGetRequestWithHeadersProxyAndTimeout(): void
    {
        $options = $this->captureCurlOptions(function (DeepLClient $client) {
            $client->getUsage();
        }, '{"character_count":1,"character_limit":2}', [
            TranslatorOptions::PROXY => 'http://proxy.local:3128',
            TranslatorOptions::TIMEOUT => 3.5,
            TranslatorOptions::SEND_PLATFORM_INFO => false,
        ]);

        $this->assertTrue($options[CURLOPT_HTTPGET]);
        $this->assertSame('https://api.deepl.com/v2/usage', $options[CURLOPT_URL]);
        $this->assertSame('http://proxy.local:3128', $options[CURLOPT_PROXY]);
        $this->assertEquals(3500, $options[CURLOPT_TIMEOUT_MS]);
        $this->assertEquals(3.5, $options[CURLOPT_CONNECTTIMEOUT]);
        $this->assertTrue($options[CURLOPT_RETURNTRANSFER]);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
        $this->assertSame(
            ['Authorization: DeepL-Auth-Key k', 'User-Agent: deepl-php/' . DeepLClient::VERSION],
            $this->headers($options)
        );
    }

    public function testSendsNoProxyByDefault(): void
    {
        $options = $this->captureCurlOptions(function (DeepLClient $client) {
            $client->getUsage();
        }, '{"character_count":1,"character_limit":2}');

        $this->assertArrayNotHasKey(CURLOPT_PROXY, $options);
    }

    public function testSendsJsonBodyWithJsonContentType(): void
    {
        $options = $this->captureCurlOptions(function (DeepLClient $client) {
            $client->translateText('Hello', null, 'de', [\DeepL\TranslateTextOptions::FORMALITY => 'less']);
        }, '{"translations":[{"text":"Hallo","detected_source_language":"EN","billed_characters":5}]}');

        $this->assertTrue($options[CURLOPT_POST]);
        $this->assertSame(
            ['target_lang' => 'de', 'formality' => 'less', 'show_billed_characters' => true, 'text' => ['Hello']],
            json_decode($options[CURLOPT_POSTFIELDS], true)
        );
        $headers = $this->headers($options);
        $this->assertCount(3, $headers);
        $this->assertSame('Content-Type: application/json', $headers[2]);
    }

    public function testSendsFormBodyAsEncodedString(): void
    {
        $options = $this->captureCurlOptions(function (DeepLClient $client) {
            $client->getDocumentStatus(new DocumentHandle('doc1', 'key/+&='));
        }, '{"status":"done"}');

        $this->assertTrue($options[CURLOPT_POST]);
        $this->assertSame('https://api.deepl.com/v2/document/doc1', $options[CURLOPT_URL]);
        $this->assertSame('document_key=key%2F%2B%26%3D', $options[CURLOPT_POSTFIELDS]);
        $this->assertCount(2, $this->headers($options));
    }

    public function testSendsMultipartBodyWithTheFile(): void
    {
        $file = Fixtures::tempFile('input.txt', 'Hello');

        $options = $this->captureCurlOptions(function (DeepLClient $client) use ($file) {
            $client->uploadDocument($file, 'en', 'de');
        }, '{"document_id":"d","document_key":"k"}');

        $this->assertTrue($options[CURLOPT_POST]);
        $fields = $options[CURLOPT_POSTFIELDS];
        $this->assertSame(['target_lang', 'source_lang', 'file'], array_keys($fields));
        $this->assertSame(['de', 'en'], [$fields['target_lang'], $fields['source_lang']]);
        $this->assertInstanceOf(\CURLFile::class, $fields['file']);
        $this->assertSame($file, $fields['file']->getFilename());
    }

    public function testSendsTheFilenameOptionAsThePostedFileName(): void
    {
        $options = $this->captureCurlOptions(function (DeepLClient $client) {
            $client->translateDocumentUpload(
                Fixtures::tempFile('input.txt', 'Hello'),
                null,
                'de',
                [TranslateDocumentOptions::FILENAME => 'report.html']
            );
        }, '{"document_id":"d","document_key":"k"}');

        $this->assertSame('report.html', $options[CURLOPT_POSTFIELDS]['file']->getPostFilename());
    }

    public function testSendsRawPutBodyWithoutAuthorization(): void
    {
        $options = $this->captureCurlOptions(function (DeepLClient $client) {
            $client->uploadTranslationMemoryFile('https://storage.example.com/up?sig=1', '<tmx/>');
        }, '');

        $this->assertSame('PUT', $options[CURLOPT_CUSTOMREQUEST]);
        $this->assertSame('https://storage.example.com/up?sig=1', $options[CURLOPT_URL]);
        $this->assertSame('<tmx/>', $options[CURLOPT_POSTFIELDS]);
        $headers = $this->headers($options);
        $this->assertCount(2, $headers);
        $this->assertStringStartsWith('User-Agent: deepl-php/', $headers[0]);
        $this->assertSame('Content-Type: application/xml', $headers[1]);
    }

    public function testSendsDeleteWithoutBody(): void
    {
        $options = $this->captureCurlOptions(function (DeepLClient $client) {
            $client->deleteGlossary('gid');
        }, '');

        $this->assertSame('DELETE', $options[CURLOPT_CUSTOMREQUEST]);
        $this->assertSame('https://api.deepl.com/v2/glossaries/gid', $options[CURLOPT_URL]);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
    }

    public function testStreamsDownloadsToTheOutputFile(): void
    {
        $output = Fixtures::tempDir() . '/output.txt';

        $options = $this->captureCurlOptions(function (DeepLClient $client) use ($output) {
            $client->downloadDocument(new DocumentHandle('doc1', 'k'), $output);
        }, '1', [], function (array $options) {
            $this->assertTrue(is_resource($options[CURLOPT_FILE]));
        });

        $this->assertArrayNotHasKey(CURLOPT_RETURNTRANSFER, $options);
    }

    public function testThrowsWithTheApiMessageWhenTheStreamedDownloadFails(): void
    {
        $output = Fixtures::tempDir() . '/output.txt';
        $this->getFunctionMock('DeepL', 'curl_setopt_array')->expects($this->once())
            ->willReturnCallback(function ($handle, array $options) {
                fwrite($options[CURLOPT_FILE], '{"message":"Invalid document key"}');
                return true;
            });
        // With CURLOPT_FILE, curl_exec() returns true instead of the body.
        $this->getFunctionMock('DeepL', 'curl_exec')->expects($this->once())->willReturn(true);
        $this->getFunctionMock('DeepL', 'curl_getinfo')->expects($this->once())->willReturn(400);

        try {
            (new DeepLClient('k'))->translateDocumentDownload(new DocumentHandle('doc1', 'k'), $output);
            $this->fail('expected a DeepLException');
        } catch (\DeepL\DeepLException $exception) {
            $this->assertSame('Bad request, message: Invalid document key', $exception->getMessage());
            $this->assertSame(400, $exception->httpStatusCode);
        }
        $this->assertFileDoesNotExist($output);
    }

    public function testSendsACustomHeaderInAnotherCaseInsteadOfTheDefault(): void
    {
        $options = $this->captureCurlOptions(function (DeepLClient $client) {
            $client->getUsage();
        }, '{"character_count":1,"character_limit":2}', [
            TranslatorOptions::HEADERS => ['user-agent' => 'my-client', 'x-custom' => 'value'],
        ]);

        $this->assertSame(
            ['Authorization: DeepL-Auth-Key k', 'user-agent: my-client', 'x-custom: value'],
            $this->headers($options)
        );
    }

    /**
     * @dataProvider provideDnsErrors
     */
    public function testRetriesDnsFailures(int $errorCode): void
    {
        $this->useFakeClock();
        $this->failCurlWith([$errorCode], $attempts, '{"character_count":1,"character_limit":2}');

        (new DeepLClient('k', [TranslatorOptions::SERVER_URL => 'https://api.deepl.com']))->getUsage();

        $this->assertCount(2, $attempts);
    }

    /**
     * @dataProvider provideConnectionResetErrors
     */
    public function testRetriesConnectionResetsAndBrokenPipes(int $errorCode): void
    {
        $this->useFakeClock();
        $this->failCurlWith([$errorCode], $attempts, '{"character_count":1,"character_limit":2}');

        (new DeepLClient('k', [TranslatorOptions::SERVER_URL => 'https://api.deepl.com']))->getUsage();

        $this->assertCount(2, $attempts);
    }

    public static function provideConnectionResetErrors(): array
    {
        return ['connection reset' => [CURLE_RECV_ERROR], 'broken pipe' => [CURLE_SEND_ERROR]];
    }

    /**
     * @dataProvider provideBodyCutOffErrors
     */
    public function testReportsABodyCutOffMidReadAsTransientWithoutRetrying(int $errorCode): void
    {
        $this->useFakeClock();
        $this->failCurlWith([$errorCode], $attempts);

        try {
            (new DeepLClient('k', [TranslatorOptions::SERVER_URL => 'https://api.deepl.com']))->getUsage();
            $this->fail('expected a ConnectionException');
        } catch (ConnectionException $exception) {
            $this->assertSame($errorCode, $exception->getCode());
            $this->assertTrue($exception->shouldRetry);
            $this->assertCount(1, $attempts);
        }
    }

    public static function provideBodyCutOffErrors(): array
    {
        return ['partial body' => [CURLE_PARTIAL_FILE], 'HTTP/2 stream reset' => [92]];
    }

    public function testDoesNotRetryAProxyResolutionFailure(): void
    {
        $this->useFakeClock();
        $this->failCurlWith([CURLE_COULDNT_RESOLVE_PROXY], $attempts);

        try {
            (new DeepLClient('k', [TranslatorOptions::SERVER_URL => 'https://api.deepl.com']))->getUsage();
            $this->fail('expected a ConnectionException');
        } catch (ConnectionException $exception) {
            $this->assertSame(CURLE_COULDNT_RESOLVE_PROXY, $exception->getCode());
            $this->assertFalse($exception->shouldRetry);
            $this->assertCount(1, $attempts);
        }
    }

    /**
     * @dataProvider provideDnsErrors
     */
    public function testRetriesKeyCreationAfterADnsFailure(int $errorCode): void
    {
        $this->useFakeClock();
        $this->failCurlWith([$errorCode], $attempts, '{"key_id": "id"}');

        $key = $this->makeCurlAdminClient()->createDeveloperKey();

        $this->assertSame('id', $key->keyId);
        $this->assertCount(2, $attempts);
    }

    public static function provideDnsErrors(): array
    {
        return ['host' => [CURLE_COULDNT_RESOLVE_HOST]];
    }

    /**
     * Lets curl_exec fail with the given cURL error codes in turn, then succeed, and collects the
     * options of each attempt.
     */
    private function failCurlWith(
        array $errorCodes,
        ?array &$attempts,
        string $body = '{}',
        float $connectTime = 0.1
    ): void {
        $attempts = [];
        $this->getFunctionMock('DeepL', 'curl_setopt_array')->expects($this->any())
            ->willReturnCallback(function ($handle, array $options) use (&$attempts) {
                $attempts[] = $options;
                return true;
            });
        $results = array_merge(array_fill(0, count($errorCodes), false), [$body]);
        $this->getFunctionMock('DeepL', 'curl_exec')->expects($this->any())->willReturnOnConsecutiveCalls(...$results);
        $this->getFunctionMock('DeepL', 'curl_errno')->expects($this->any())
            ->willReturnOnConsecutiveCalls(...$errorCodes);
        $this->getFunctionMock('DeepL', 'curl_error')->expects($this->any())->willReturn('failed');
        $this->getFunctionMock('DeepL', 'curl_getinfo')->expects($this->any())
            ->willReturnCallback(function ($handle, int $option) use ($connectTime) {
                return $option === CURLINFO_CONNECT_TIME ? $connectTime : 200;
            });
    }

    private function makeCurlAdminClient(): DeepLAdminClient
    {
        return new DeepLAdminClient('k', [TranslatorOptions::SERVER_URL => 'https://api.deepl.com']);
    }

    public function testRetriesKeyCreationOnAFreshConnectionAfterAFailedConnect(): void
    {
        $this->useFakeClock();
        $this->failCurlWith([CURLE_COULDNT_CONNECT], $attempts, '{"key_id": "id"}');

        $key = $this->makeCurlAdminClient()->createDeveloperKey();

        $this->assertSame('id', $key->keyId);
        $this->assertCount(2, $attempts);
        foreach ($attempts as $options) {
            $this->assertTrue($options[CURLOPT_FRESH_CONNECT]);
        }
    }

    public function testRetriesKeyCreationAfterATimeoutBeforeConnecting(): void
    {
        $this->useFakeClock();
        $this->failCurlWith([CURLE_OPERATION_TIMEDOUT], $attempts, '{"key_id": "id"}', 0.0);

        $key = $this->makeCurlAdminClient()->createDeveloperKey();

        $this->assertSame('id', $key->keyId);
        $this->assertCount(2, $attempts);
    }

    /**
     * @dataProvider providePossiblySentErrors
     */
    public function testDoesNotRetryKeyCreationAfterAPossiblySentRequest(int $errorCode): void
    {
        $this->useFakeClock();
        $this->failCurlWith([$errorCode], $attempts);

        try {
            $this->makeCurlAdminClient()->createDeveloperKey();
            $this->fail('expected a ConnectionException');
        } catch (ConnectionException $exception) {
            $this->assertSame($errorCode, $exception->getCode());
            $this->assertCount(1, $attempts);
        }
    }

    public static function providePossiblySentErrors(): array
    {
        return [
            'timeout after connecting' => [CURLE_OPERATION_TIMEDOUT],
            'empty reply' => [CURLE_GOT_NOTHING],
            'connection reset' => [CURLE_RECV_ERROR],
            'broken pipe' => [CURLE_SEND_ERROR],
        ];
    }

    public function testReusesConnectionsForOtherRequests(): void
    {
        $options = $this->captureCurlOptions(function (DeepLClient $client) {
            $client->getUsage();
        }, '{"character_count":1,"character_limit":2}');

        $this->assertArrayNotHasKey(CURLOPT_FRESH_CONNECT, $options);
    }
}
