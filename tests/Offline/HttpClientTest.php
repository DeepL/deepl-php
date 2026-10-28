<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\ConnectionException;
use DeepL\DeepLException;
use DeepL\TooManyRequestsException;
use DeepL\TranslatorOptions;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class HttpClientTest extends OfflineTestCase
{
    private const USAGE = ['character_count' => 1, 'character_limit' => 2];

    /**
     * @dataProvider provideRetriedStatuses
     */
    public function testRetriesTheRequestOnTheseStatuses(int $status): void
    {
        $this->useFakeClock();
        $this->reply(['message' => 'try later'], $status);
        $this->reply(self::USAGE);
        $logger = new TestLogger();

        $this->makeTranslator([TranslatorOptions::LOGGER => $logger])->getUsage();

        $this->assertRequestCount(2);
        $this->assertRequest('GET', '/v2/usage', 0);
        $this->assertRequest('GET', '/v2/usage', 1);
        $this->assertStringContainsString(
            'Starting retry 1 for request GET https://api.deepl.com/v2/usage after sleeping for',
            $logger->content
        );
    }

    public static function provideRetriedStatuses(): array
    {
        return [[429], [500], [502], [503]];
    }

    /**
     * @dataProvider provideNotRetriedStatuses
     */
    public function testDoesNotRetryTheRequestOnTheseStatuses(int $status): void
    {
        $this->reply(['message' => 'no'], $status);

        try {
            $this->makeTranslator()->getUsage();
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertRequestCount(1);
        }
    }

    public static function provideNotRetriedStatuses(): array
    {
        return [[400], [403], [404], [456]];
    }

    public function testRetriesMaxRetriesTimesAfterTheFirstAttempt(): void
    {
        $this->useFakeClock();
        foreach ([1, 2, 3, 4] as $_) {
            $this->reply('', 429);
        }

        $this->expectException(TooManyRequestsException::class);

        try {
            $this->makeTranslator([TranslatorOptions::MAX_RETRIES => 3])->getUsage();
        } finally {
            $this->assertRequestCount(4);
        }
    }

    public function testSendsOneRequestWhenMaxRetriesIsZero(): void
    {
        $this->reply('', 429);

        $this->expectException(TooManyRequestsException::class);

        try {
            $this->makeTranslator([TranslatorOptions::MAX_RETRIES => 0])->getUsage();
        } finally {
            $this->assertRequestCount(1);
        }
    }

    public function testRetriesWhenTheConnectionFails(): void
    {
        $this->useFakeClock();
        $this->mock->append(new ConnectException('connection refused', new Request('GET', 'https://api.deepl.com')));
        $this->reply(self::USAGE);

        $this->makeTranslator()->getUsage();

        $this->assertRequestCount(2);
    }

    public function testThrowsConnectionExceptionWhenEveryAttemptFailsToConnect(): void
    {
        $this->useFakeClock();
        foreach ([1, 2, 3] as $_) {
            $this->mock->append(
                new ConnectException('connection refused', new Request('GET', 'https://api.deepl.com'))
            );
        }

        try {
            $this->makeTranslator([TranslatorOptions::MAX_RETRIES => 2])->getUsage();
            $this->fail('expected a ConnectionException');
        } catch (ConnectionException $exception) {
            $this->assertStringContainsString('connection refused', $exception->getMessage());
            $this->assertTrue($exception->shouldRetry);
        }
        $this->assertRequestCount(3);
    }

    public function testDoesNotRetryWhenTheRequestIsRejected(): void
    {
        $this->mock->append(new RequestException('invalid request', new Request('GET', 'https://api.deepl.com')));

        try {
            $this->makeTranslator()->getUsage();
            $this->fail('expected a ConnectionException');
        } catch (ConnectionException $exception) {
            $this->assertFalse($exception->shouldRetry);
        }
        $this->assertRequestCount(1);
    }
}
