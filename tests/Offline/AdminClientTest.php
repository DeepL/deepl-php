<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DeepL\AdminUsageGroupBy;
use DeepL\AuthorizationException;
use DeepL\ConnectionException;
use DeepL\CustomTagUsageAggregateBy;
use DeepL\DeepLAdminClient;
use DeepL\DeepLException;
use DeepL\DeveloperKey;
use DeepL\DeveloperKeyUsageLimitsOptions;
use DeepL\InvalidContentException;
use DeepL\TranslatorOptions;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;

class AdminClientTest extends OfflineTestCase
{
    private const KEY_ID = 'ca7d5694-96eb-4263-a9a4-7f7e4211529e:20c2abcf-4c3c-4cd6-8ae8-8bd2a7d4da38';
    private const DEVELOPER_KEY_JSON = [
        'key_id' => self::KEY_ID,
        'label' => 'developer key prod',
        'creation_time' => '2025-07-08T08:15:29.362Z',
        'deactivated_time' => null,
        'is_deactivated' => false,
        'usage_limits' => [
            'characters' => 5000,
            'speech_to_text_milliseconds' => null,
            'speech_to_speech_milliseconds' => 60000,
        ],
    ];

    /**
     * @dataProvider provideBlankAdminKeys
     */
    public function testRejectsABlankAdminKey(string $adminKey): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('adminKey');
        new DeepLAdminClient($adminKey);
    }

    public static function provideBlankAdminKeys(): array
    {
        return [[''], ['  ']];
    }

    public function testDefaultsToTheProServerUrlForAnyKey(): void
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        $options = [TranslatorOptions::HTTP_CLIENT => new Client(['handler' => $stack])];
        $client = new DeepLAdminClient('free-key:fx', $options);
        $this->reply([]);

        $client->getDeveloperKeys();

        $this->assertSame('https://api.deepl.com/v2/admin/developer-keys', (string)$this->request()->getUri());
    }

    public function testGetUsageAnalytics(): void
    {
        $this->reply(['usage_report' => [
            'total_usage' => [
                'total_characters' => 9619,
                'text_translation_characters' => 4892,
                'document_translation_characters' => 0,
                'text_improvement_characters' => 4727,
                'speech_to_text_minutes' => 107.46,
                'speech_to_speech_minutes' => 3.5,
            ],
            'group_by' => 'keyAndDay',
            'start_date' => '2025-09-29T00:00:00',
            'end_date' => '2025-10-01T00:00:00',
            'key_and_day_usages' => [[
                'api_key' => 'dc88****3a2c',
                'api_key_label' => 'Staging API Key',
                'usage_date' => '2025-09-29T00:00:00Z',
                'usage' => [
                    'total_characters' => 315,
                    'text_translation_characters' => 159,
                    'document_translation_characters' => 0,
                    'text_improvement_characters' => 156,
                    'speech_to_text_minutes' => 11.94,
                ],
            ]],
        ]]);

        $defaultTimeZone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            $report = $this->makeAdminClient()->getUsageAnalytics(
                new DateTime('2025-09-29 00:30', new DateTimeZone('Europe/Berlin')),
                '2025-10-01',
                AdminUsageGroupBy::KEY_AND_DAY
            );
        } finally {
            date_default_timezone_set($defaultTimeZone);
        }

        $this->assertRequest(
            'GET',
            '/v2/admin/analytics',
            0,
            'start_date=2025-09-28&end_date=2025-10-01&group_by=key_and_day'
        );
        $this->assertSame(9619, $report->totalUsage->totalCharacters);
        $this->assertSame(107.46, $report->totalUsage->speechToTextMinutes);
        $this->assertSame(3.5, $report->totalUsage->speechToSpeechMinutes);
        $this->assertEquals(new DateTime('2025-09-29', new DateTimeZone('UTC')), $report->startDate);
        $this->assertEquals(new DateTime('2025-10-01', new DateTimeZone('UTC')), $report->endDate);
        $this->assertSame([], $report->keyUsages);
        $usage = $report->keyAndDayUsages[0];
        $this->assertSame('Staging API Key', $usage->apiKeyLabel);
        $this->assertEquals(new DateTime('2025-09-29', new DateTimeZone('UTC')), $usage->usageDate);
        $this->assertSame(156, $usage->usage->textImprovementCharacters);
        $this->assertSame(0.0, $usage->usage->speechToSpeechMinutes);
    }

    public function testGetCustomTagUsageAnalytics(): void
    {
        $this->reply(['custom_tag_usage_report' => [
            'aggregate_by' => 'day',
            'start_date' => '2026-05-03T00:00:00',
            'end_date' => '2026-05-15T00:00:00',
            'next_page' => 3,
            'usage' => [[
                'custom_tag' => 'example-custom-tag',
                'usage_date' => '2026-05-04T00:00:00Z',
                'breakdown' => [
                    'total_characters' => 380,
                    'text_translation_characters' => 380,
                    'text_improvement_characters' => 0,
                    'speech_to_text_minutes' => 12.5,
                    'speech_to_speech_minutes' => 4.2,
                ],
            ]],
        ]]);

        $report = $this->makeAdminClient()->getCustomTagUsageAnalytics(
            new DateTime('2026-05-02 23:30', new DateTimeZone('America/New_York')),
            new DateTimeImmutable('2026-05-15'),
            CustomTagUsageAggregateBy::DAY,
            2
        );

        $this->assertRequest(
            'GET',
            '/v2/admin/analytics/custom-tags',
            0,
            'start_date=2026-05-03&end_date=2026-05-15&aggregate_by=day&page=2'
        );
        $this->assertSame('day', $report->aggregateBy);
        $this->assertEquals(new DateTime('2026-05-03', new DateTimeZone('UTC')), $report->startDate);
        $this->assertSame(3, $report->nextPage);
        $this->assertSame('example-custom-tag', $report->usage[0]->customTag);
        $this->assertSame(4.2, $report->usage[0]->breakdown->speechToSpeechMinutes);
        $this->assertEquals(new DateTime('2026-05-04', new DateTimeZone('UTC')), $report->usage[0]->usageDate);
    }

    /**
     * @dataProvider provideDateInputs
     * @param DateTimeInterface|string $input
     */
    public function testSendsTheUtcDateOfDateInputs($input, string $expected): void
    {
        $this->reply([]);
        $before = $input instanceof DateTimeInterface ? clone $input : $input;

        $this->makeAdminClient()->getUsageAnalytics($input, '2027-01-01');

        $this->assertRequest('GET', '/v2/admin/analytics', 0, "start_date=$expected&end_date=2027-01-01");
        $this->assertEquals($before, $input);
    }

    public static function provideDateInputs(): array
    {
        return [
            'string' => ['2026-10-20', '2026-10-20'],
            'UTC DateTime' => [new DateTime('2026-10-20 00:00', new DateTimeZone('UTC')), '2026-10-20'],
            'DateTimeImmutable' => [new DateTimeImmutable('2026-10-20 12:00', new DateTimeZone('UTC')), '2026-10-20'],
            'positive offset near midnight' => [new DateTime('2026-10-20T00:00+02:00'), '2026-10-19'],
            'negative offset near midnight' => [new DateTime('2026-10-19T23:30-05:00'), '2026-10-20'],
            'default-zone value (UTC here)' => [new DateTime('2026-10-20 00:30'), '2026-10-20'],
        ];
    }

    /**
     * @dataProvider provideInvalidDates
     */
    public function testRejectsInvalidDatesBeforeSending($startDate, $endDate, string $message): void
    {
        $client = $this->makeAdminClient();
        foreach (['getUsageAnalytics', 'getCustomTagUsageAnalytics'] as $method) {
            try {
                $client->$method($startDate, $endDate);
                $this->fail("expected a DeepLException from $method");
            } catch (DeepLException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
        $this->assertRequestCount(0);
    }

    public static function provideInvalidDates(): array
    {
        return [
            'impossible date' => ['2026-02-30', '2026-07-01', 'startDate'],
            'no zero padding' => ['2026-06-01', '2026-7-1', 'endDate'],
            'timestamp string' => ['2026-06-01T00:00:00', '2026-07-01', 'startDate'],
            'relative date' => ['yesterday', '2026-07-01', 'startDate'],
            'not a date' => [20260601, '2026-07-01', 'startDate'],
        ];
    }

    public function testLeavesDateRangeValidationToTheApi(): void
    {
        $this->reply(['message' => 'Bad request. Reason: start_date must be before end_date'], 400);

        try {
            $this->makeAdminClient()->getUsageAnalytics('2026-06-01', '2026-06-01');
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString('start_date must be before end_date', $exception->getMessage());
        }
        $this->assertRequest('GET', '/v2/admin/analytics', 0, 'start_date=2026-06-01&end_date=2026-06-01');
    }

    public function testCreateDeveloperKey(): void
    {
        $this->reply(self::DEVELOPER_KEY_JSON);
        $this->reply(self::DEVELOPER_KEY_JSON);
        $client = $this->makeAdminClient();

        $key = $client->createDeveloperKey('developer key prod');
        $client->createDeveloperKey();

        $this->assertRequest('POST', '/v2/admin/developer-keys');
        $this->assertSame(['label' => 'developer key prod'], $this->jsonBody(0));
        $this->assertSame('{}', (string)$this->request(1)->getBody());
        $this->assertSame(self::KEY_ID, $key->keyId);
        $this->assertSame('developer key prod', $key->label);
        $this->assertEquals(new DateTime('2025-07-08T08:15:29.362Z'), $key->creationTime);
        $this->assertNull($key->deactivatedTime);
        $this->assertFalse($key->isDeactivated);
        $this->assertSame(5000, $key->usageLimits->characters);
        $this->assertNull($key->usageLimits->speechToTextMilliseconds);
        $this->assertSame(60000, $key->usageLimits->speechToSpeechMilliseconds);
    }

    public function testRetriesKeyCreationAfterTooManyRequests(): void
    {
        $this->useFakeClock();
        $this->reply(['message' => 'slow down'], 429);
        $this->reply(self::DEVELOPER_KEY_JSON);

        $this->makeAdminClient()->createDeveloperKey();

        $this->assertRequestCount(2);
    }

    public function testDoesNotRetryKeyCreationAfterAServerError(): void
    {
        $this->reply(['message' => 'unavailable'], 503);

        try {
            $this->makeAdminClient()->createDeveloperKey();
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertRequestCount(1);
        }
    }

    public function testDoesNotRetryKeyCreationAfterAConnectionErrorOfACustomHttpClient(): void
    {
        $this->mock->append(new ConnectException('reset', new Request('POST', 'https://api.deepl.com')));

        try {
            $this->makeAdminClient()->createDeveloperKey();
            $this->fail('expected a ConnectionException');
        } catch (ConnectionException $exception) {
            $this->assertRequestCount(1);
        }
    }

    public function testRetriesOtherAdminRequestsAfterAServerError(): void
    {
        $this->useFakeClock();
        $this->reply(['message' => 'unavailable'], 503);
        $this->reply(self::DEVELOPER_KEY_JSON);

        $this->makeAdminClient()->deactivateDeveloperKey(self::KEY_ID);

        $this->assertRequestCount(2);
    }

    public function testSendsTheTrimmedAdminKey(): void
    {
        $this->reply([]);

        (new DeepLAdminClient(" admin-key\n", $this->clientOptions([])))->getDeveloperKeys();

        $this->assertSame(
            'DeepL-Auth-Key admin-key',
            $this->request(0)->getHeaderLine('Authorization')
        );
    }

    public function testGetDeveloperKeys(): void
    {
        $deactivated = array_replace(
            self::DEVELOPER_KEY_JSON,
            ['is_deactivated' => true, 'deactivated_time' => '2025-07-09T08:15:29.362Z']
        );
        $this->reply([self::DEVELOPER_KEY_JSON, $deactivated]);

        $keys = $this->makeAdminClient()->getDeveloperKeys();

        $this->assertRequest('GET', '/v2/admin/developer-keys');
        $this->assertSame([false, true], array_map(function (DeveloperKey $key) {
            return $key->isDeactivated;
        }, $keys));
        $this->assertEquals(new DateTime('2025-07-09T08:15:29.362Z'), $keys[1]->deactivatedTime);
    }

    public function testDeactivateAndRenameDeveloperKey(): void
    {
        $this->reply(self::DEVELOPER_KEY_JSON);
        $this->reply(self::DEVELOPER_KEY_JSON);
        $client = $this->makeAdminClient();

        $client->deactivateDeveloperKey(DeveloperKey::fromJson(self::DEVELOPER_KEY_JSON));
        $client->renameDeveloperKey(self::KEY_ID, 'renamed');

        $this->assertRequest('PUT', '/v2/admin/developer-keys/deactivate', 0);
        $this->assertSame(['key_id' => self::KEY_ID], $this->jsonBody(0));
        $this->assertRequest('PUT', '/v2/admin/developer-keys/label', 1);
        $this->assertSame(['key_id' => self::KEY_ID, 'label' => 'renamed'], $this->jsonBody(1));
    }

    /**
     * @dataProvider provideUsageLimitUpdates
     */
    public function testSendsOnlyTheGivenUsageLimits(array $limits): void
    {
        $this->reply(self::DEVELOPER_KEY_JSON);

        $this->makeAdminClient()->setDeveloperKeyUsageLimits(self::KEY_ID, $limits);

        $this->assertRequest('PUT', '/v2/admin/developer-keys/limits');
        $this->assertSame(['key_id' => self::KEY_ID] + $limits, $this->jsonBody());
    }

    public static function provideUsageLimitUpdates(): array
    {
        return [
            'block characters' => [[DeveloperKeyUsageLimitsOptions::CHARACTERS => 0]],
            'remove speech-to-text limit' => [[DeveloperKeyUsageLimitsOptions::SPEECH_TO_TEXT_MILLISECONDS => null]],
            'all limits' => [[
                DeveloperKeyUsageLimitsOptions::CHARACTERS => 1000,
                DeveloperKeyUsageLimitsOptions::SPEECH_TO_TEXT_MILLISECONDS => 60000,
                DeveloperKeyUsageLimitsOptions::SPEECH_TO_SPEECH_MILLISECONDS => null,
            ]],
        ];
    }

    /**
     * @dataProvider provideInvalidArguments
     */
    public function testRejectsInvalidArgumentsBeforeSending(callable $call, string $message): void
    {
        try {
            $call($this->makeAdminClient());
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
        $this->assertRequestCount(0);
    }

    public static function provideInvalidArguments(): array
    {
        return [
            'empty key ID' => [function (DeepLAdminClient $client) {
                $client->deactivateDeveloperKey('');
            }, 'key must be'],
        ];
    }

    /**
     * @dataProvider provideUnparseableResponses
     */
    public function testThrowsInvalidContentExceptionForUnparseableResponses(string $body, callable $call): void
    {
        $this->reply($body);

        $this->expectException(InvalidContentException::class);
        $call($this->makeAdminClient());
    }

    public static function provideUnparseableResponses(): array
    {
        $create = function (DeepLAdminClient $client) {
            $client->createDeveloperKey();
        };
        $list = function (DeepLAdminClient $client) {
            $client->getDeveloperKeys();
        };
        return [
            'null' => ['null', $create],
            'string' => ['"text"', $list],
        ];
    }

    public function testPassesLabelsAndLimitsThroughToTheApi(): void
    {
        $this->reply(['message' => 'Bad request. Reason: invalid limit'], 400);
        $this->reply(self::DEVELOPER_KEY_JSON);
        $client = $this->makeAdminClient();

        try {
            $client->setDeveloperKeyUsageLimits(self::KEY_ID, ['minutes' => '5']);
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString('invalid limit', $exception->getMessage());
        }
        $client->renameDeveloperKey(self::KEY_ID, '');

        $this->assertSame(['key_id' => self::KEY_ID, 'minutes' => '5'], $this->jsonBody(0));
        $this->assertSame(['key_id' => self::KEY_ID, 'label' => ''], $this->jsonBody(1));
    }

    public function testOnlyJsonTrueMarksAKeyDeactivated(): void
    {
        foreach ([true, 'true', 'false', 1, null] as $value) {
            $key = DeveloperKey::fromJson(array_replace(self::DEVELOPER_KEY_JSON, ['is_deactivated' => $value]));
            $this->assertSame($value === true, $key->isDeactivated);
        }
    }

    public function testThrowsForSingleKeyResponsesWithoutAKeyId(): void
    {
        foreach ([['label' => 'no id'], ['key_id' => ''], ['key_id' => 5], []] as $body) {
            $this->reply($body);
            try {
                $this->makeAdminClient()->createDeveloperKey();
                $this->fail('expected a DeepLException');
            } catch (DeepLException $exception) {
                $this->assertStringContainsString('missing key_id', $exception->getMessage());
            }
        }
    }

    public function testThrowsForAnalyticsResponsesThatAreNotObjects(): void
    {
        $this->reply([1, 2]);
        $this->reply([1, 2]);
        $this->reply(['label' => 'not a list']);
        $client = $this->makeAdminClient();

        foreach ([
            ['Usage report response is not a JSON object', function () use ($client) {
                $client->getUsageAnalytics('2025-09-29', '2025-10-01');
            }],
            ['Custom tag usage report response is not a JSON object', function () use ($client) {
                $client->getCustomTagUsageAnalytics('2025-09-29', '2025-10-01');
            }],
            ['Developer keys response is not a list', function () use ($client) {
                $client->getDeveloperKeys();
            }],
        ] as [$message, $call]) {
            try {
                $call();
                $this->fail('expected a DeepLException');
            } catch (DeepLException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testToleratesMalformedResponseValues(): void
    {
        $this->reply([
            ['key_id' => self::KEY_ID, 'label' => 'kept', 'creation_time' => 'not a timestamp',
                'usage_limits' => 'none', 'is_deactivated' => false],
            1,
            ['label' => 'no id'],
            ['key_id' => '', 'label' => 'empty id'],
        ]);
        $this->reply(['usage_report' => [
            'total_usage' => ['total_characters' => 'many', 'text_translation_characters' => 7],
            'start_date' => ['2025-09-29'],
            'end_date' => '2025-10-01T00:00:00',
            'key_usages' => [1, ['api_key' => 'dc88****3a2c', 'api_key_label' => 'kept'], []],
            'key_and_day_usages' => 'none',
        ]]);
        $this->reply(['custom_tag_usage_report' => [
            'next_page' => 'soon',
            'usage' => ['text', ['custom_tag' => 'kept', 'breakdown' => 5, 'usage_date' => 'not a date']],
        ]]);
        $client = $this->makeAdminClient();

        $keys = $client->getDeveloperKeys();
        $report = $client->getUsageAnalytics('2025-09-29', '2025-10-01');
        $tagReport = $client->getCustomTagUsageAnalytics('2025-09-29', '2025-10-01');

        $this->assertCount(1, $keys);
        $this->assertSame('kept', $keys[0]->label);
        $this->assertNull($keys[0]->creationTime);
        $this->assertNull($keys[0]->usageLimits->characters);
        $this->assertSame(0, $report->totalUsage->totalCharacters);
        $this->assertSame(7, $report->totalUsage->textTranslationCharacters);
        $this->assertNull($report->startDate);
        $this->assertEquals(new DateTime('2025-10-01', new DateTimeZone('UTC')), $report->endDate);
        $this->assertCount(2, $report->keyUsages);
        $this->assertSame('kept', $report->keyUsages[0]->apiKeyLabel);
        $this->assertSame([], $report->keyAndDayUsages);
        $this->assertNull($tagReport->nextPage);
        $this->assertCount(1, $tagReport->usage);
        $this->assertSame('kept', $tagReport->usage[0]->customTag);
        $this->assertNull($tagReport->usage[0]->usageDate);
        $this->assertSame(0, $tagReport->usage[0]->breakdown->totalCharacters);
    }

    public function testForbiddenThrowsAuthorizationException(): void
    {
        $this->reply(['message' => 'Forbidden'], 403);

        $this->expectException(AuthorizationException::class);
        $this->makeAdminClient()->getDeveloperKeys();
    }
}
