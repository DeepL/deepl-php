<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\InvalidContentException;

class UsageTest extends OfflineTestCase
{
    public function testAnyCharacterLimitReachedForTheAccountCharacterLimit(): void
    {
        $this->reply([
            'character_count' => 20,
            'character_limit' => 20,
            'api_key_character_count' => 5,
            'api_key_character_limit' => 1000,
        ]);

        $usage = $this->makeDeepLClient()->getUsage();

        $this->assertRequest('GET', '/v2/usage');
        $this->assertTrue($usage->anyCharacterLimitReached());
    }

    public function testAnyCharacterLimitReachedIgnoresTheSpeechMinuteLimits(): void
    {
        $this->reply([
            'character_count' => 5,
            'character_limit' => 500000,
            'speech_to_text_minutes_count' => 600,
            'speech_to_text_minutes_limit' => 600,
            'speech_to_speech_minutes_count' => 600,
            'speech_to_speech_minutes_limit' => 600,
        ]);

        $usage = $this->makeDeepLClient()->getUsage();

        $this->assertTrue($usage->speechToTextMinutes->limitReached());
        $this->assertFalse($usage->anyCharacterLimitReached());
    }

    public function testIgnoresDocumentUsageFields(): void
    {
        $this->reply([
            'character_count' => 5,
            'character_limit' => 500000,
            'document_count' => 1,
            'document_limit' => 1,
            'team_document_count' => 1,
            'team_document_limit' => 1,
        ]);

        $usage = $this->makeDeepLClient()->getUsage();

        $this->assertSame(5, $usage->character->count);
        $this->assertFalse($usage->anyCharacterLimitReached());
        $this->assertSame("Usage this billing period:\nCharacters: 5 of 500000", (string)$usage);
    }

    public function testParsesApiProFields(): void
    {
        $this->reply([
            'character_count' => 180118,
            'character_limit' => 1250000,
            'products' => [
                [
                    'product_type' => 'write',
                    'billing_unit' => 'characters',
                    'api_key_unit_count' => 0,
                    'account_unit_count' => 5643,
                    'api_key_character_count' => 0,
                    'character_count' => 5643,
                ],
                [
                    'product_type' => 'speechToText',
                    'billing_unit' => 'minutes',
                    'api_key_unit_count' => 30,
                    'account_unit_count' => 45,
                ],
            ],
            'api_key_character_count' => 636,
            'api_key_character_limit' => 1000000000000,
            'speech_to_text_milliseconds_count' => 0,
            'speech_to_text_milliseconds_limit' => 0,
            'speech_to_text_minutes_count' => 30,
            'speech_to_text_minutes_limit' => 600,
            'speech_to_speech_minutes_count' => 12,
            'speech_to_speech_minutes_limit' => 600,
            'start_time' => '2025-05-13T09:18:42Z',
            'end_time' => '2025-06-13T09:18:42Z',
        ]);

        $usage = $this->makeDeepLClient()->getUsage();

        $this->assertSame(180118, $usage->character->count);
        $this->assertSame(1250000, $usage->character->limit);
        $this->assertSame(636, $usage->apiKeyCharacter->count);
        $this->assertSame(1000000000000, $usage->apiKeyCharacter->limit);
        $this->assertSame(30, $usage->speechToTextMinutes->count);
        $this->assertSame(600, $usage->speechToTextMinutes->limit);
        $this->assertSame(12, $usage->speechToSpeechMinutes->count);
        $this->assertSame(600, $usage->speechToSpeechMinutes->limit);
        $this->assertEquals(new \DateTime('2025-05-13T09:18:42Z'), $usage->startTime);
        $this->assertEquals(new \DateTime('2025-06-13T09:18:42Z'), $usage->endTime);
        $this->assertCount(2, $usage->products);
        $this->assertSame('write', $usage->products[0]->productType);
        $this->assertSame('characters', $usage->products[0]->billingUnit);
        $this->assertSame(0, $usage->products[0]->apiKeyUnitCount);
        $this->assertSame(5643, $usage->products[0]->accountUnitCount);
        $this->assertSame('speechToText', $usage->products[1]->productType);
        $this->assertSame('minutes', $usage->products[1]->billingUnit);
        $this->assertSame(30, $usage->products[1]->apiKeyUnitCount);
        $this->assertSame(45, $usage->products[1]->accountUnitCount);
        $this->assertFalse($usage->anyCharacterLimitReached());
        $this->assertSame("Usage this billing period:\nCharacters: 180118 of 1250000", (string)$usage);
    }

    public function testLeavesApiProFieldsNullForFreeResponse(): void
    {
        $this->reply(['character_count' => 5, 'character_limit' => 500000]);

        $usage = $this->makeDeepLClient()->getUsage();

        $this->assertSame(5, $usage->character->count);
        $this->assertSame(500000, $usage->character->limit);
        $this->assertNull($usage->apiKeyCharacter);
        $this->assertNull($usage->speechToTextMinutes);
        $this->assertNull($usage->speechToSpeechMinutes);
        $this->assertNull($usage->products);
        $this->assertNull($usage->startTime);
        $this->assertNull($usage->endTime);
        $this->assertSame("Usage this billing period:\nCharacters: 5 of 500000", (string)$usage);
    }

    public function testIgnoresUnparseableBillingPeriodAndIncompleteProducts(): void
    {
        $this->reply([
            'character_count' => 20,
            'character_limit' => 500000,
            'start_time' => 'not a timestamp',
            'end_time' => '',
            'products' => [['product_type' => 'translate'], [], 'not an object'],
        ]);

        $usage = $this->makeDeepLClient()->getUsage();

        $this->assertSame(20, $usage->character->count);
        $this->assertSame(500000, $usage->character->limit);
        $this->assertNull($usage->startTime);
        $this->assertNull($usage->endTime);
        $this->assertCount(2, $usage->products);
        $this->assertSame('translate', $usage->products[0]->productType);
        $this->assertNull($usage->products[0]->billingUnit);
        $this->assertNull($usage->products[0]->apiKeyUnitCount);
        $this->assertNull($usage->products[0]->accountUnitCount);
        $this->assertNull($usage->products[1]->productType);
        $this->assertNull($usage->products[1]->billingUnit);
        $this->assertNull($usage->products[1]->apiKeyUnitCount);
        $this->assertNull($usage->products[1]->accountUnitCount);
    }

    public function testReportsNoLimitReachedBelowTheLimits(): void
    {
        $this->reply(['character_count' => 0, 'character_limit' => 20]);

        $this->assertFalse($this->makeDeepLClient()->getUsage()->anyCharacterLimitReached());
    }

    public function nonObjectBodies(): array
    {
        return [
            'null' => ['null'],
            'string' => ['"usage"'],
            'number' => ['42'],
        ];
    }

    /**
     * @dataProvider nonObjectBodies
     */
    public function testThrowsInvalidContentExceptionForNonObjectBody(string $body): void
    {
        $this->reply($body);

        $this->expectException(InvalidContentException::class);
        $this->makeDeepLClient()->getUsage();
    }

    public function testSkipsUsageDetailsWithMissingOrNonNumericValues(): void
    {
        $this->reply([
            'character_count' => null,
            'character_limit' => 500000,
            'api_key_character_count' => 636,
            'api_key_character_limit' => 1000,
            'speech_to_text_minutes_count' => true,
            'speech_to_text_minutes_limit' => 600,
            'speech_to_speech_minutes_count' => 12,
            'speech_to_speech_minutes_limit' => null,
        ]);

        $usage = $this->makeDeepLClient()->getUsage();

        $this->assertNull($usage->character);
        $this->assertSame(636, $usage->apiKeyCharacter->count);
        $this->assertSame(1000, $usage->apiKeyCharacter->limit);
        $this->assertNull($usage->speechToTextMinutes);
        $this->assertNull($usage->speechToSpeechMinutes);
        $this->assertFalse($usage->anyCharacterLimitReached());
    }

    public function testAnyCharacterLimitReachedForThePerKeyCharacterLimit(): void
    {
        $this->reply([
            'character_count' => 5,
            'character_limit' => 500000,
            'api_key_character_count' => 1000,
            'api_key_character_limit' => 1000,
            'speech_to_text_minutes_count' => 0,
            'speech_to_text_minutes_limit' => 0,
        ]);

        $usage = $this->makeDeepLClient()->getUsage();

        $this->assertTrue($usage->anyCharacterLimitReached());
    }
}
