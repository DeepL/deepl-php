<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DateTimeImmutable;
use DateTimeZone;
use DeepL\AdminUsageGroupBy;
use DeepL\CustomTagUsageAggregateBy;
use DeepL\DeepLAdminClient;
use DeepL\DeveloperKey;
use DeepL\DeveloperKeyUsageLimitsOptions;
use DeepL\NotFoundException;

/**
 * Admin API tests, authenticated with the admin key in DEEPL_ADMIN_KEY instead of DEEPL_AUTH_KEY.
 */
class AdminClientTest extends RealApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!getenv('DEEPL_ADMIN_KEY')) {
            self::fail('DEEPL_ADMIN_KEY must be set to run the Admin API tests');
        }
    }

    private function makeAdminClient(): DeepLAdminClient
    {
        return new DeepLAdminClient(getenv('DEEPL_ADMIN_KEY'), $this->clientOptions());
    }

    /**
     * @return DateTimeImmutable[] The last 7 days, as start and end date.
     */
    private static function lastWeek(): array
    {
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        return [$today->modify('-7 days'), $today];
    }

    public function testGetUsageAnalytics(): void
    {
        [$startDate, $endDate] = self::lastWeek();

        $report = $this->makeAdminClient()->getUsageAnalytics($startDate, $endDate, AdminUsageGroupBy::KEY_AND_DAY);

        $this->assertGreaterThanOrEqual(0, $report->totalUsage->totalCharacters);
        $this->assertNotNull($report->startDate);
        $this->assertNotNull($report->endDate);
        $this->assertSame('keyAndDay', $report->groupBy);
        $this->assertIsArray($report->keyAndDayUsages);
    }

    public function testGetCustomTagUsageAnalytics(): void
    {
        [$startDate, $endDate] = self::lastWeek();

        $report = $this->makeAdminClient()->getCustomTagUsageAnalytics(
            $startDate,
            $endDate,
            CustomTagUsageAggregateBy::DAY
        );

        $this->assertNotNull($report->startDate);
        $this->assertNotNull($report->endDate);
        $this->assertSame('day', $report->aggregateBy);
        $this->assertIsArray($report->usage);
    }

    private static function isListed(DeepLAdminClient $client, string $keyId): bool
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            foreach ($client->getDeveloperKeys() as $listedKey) {
                if ($listedKey->keyId === $keyId) {
                    return true;
                }
            }
            if ($attempt < 5) {
                sleep(1);
            }
        }
        return false;
    }

    public function testDeveloperKeyLifecycle(): void
    {
        $client = $this->makeAdminClient();
        $label = self::uniqueName('admin-key');
        $key = $client->createDeveloperKey($label);
        $deactivationAttempted = false;
        try {
            $this->assertSame($label, $key->label);
            $this->assertFalse($key->isDeactivated);

            $this->assertTrue(self::isListed($client, $key->keyId), 'The new key is not listed after 5 attempts');

            $renamed = $client->renameDeveloperKey($key, $label . '-renamed');
            $this->assertSame($label . '-renamed', $renamed->label);

            $limited = $client->setDeveloperKeyUsageLimits($key, [
                DeveloperKeyUsageLimitsOptions::CHARACTERS => 5000,
                DeveloperKeyUsageLimitsOptions::SPEECH_TO_TEXT_MILLISECONDS => null,
            ]);
            $this->assertSame(5000, $limited->usageLimits->characters);
            $this->assertNull($limited->usageLimits->speechToTextMilliseconds);

            $deactivationAttempted = true;
            $deactivated = $client->deactivateDeveloperKey($key);
            $this->assertTrue($deactivated->isDeactivated);
            $this->assertNotNull($deactivated->deactivatedTime);
        } finally {
            if (!$deactivationAttempted) {
                try {
                    $client->deactivateDeveloperKey($key);
                } catch (NotFoundException $exception) {
                    // The key is already gone
                }
            }
        }
    }
}
