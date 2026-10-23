<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use PHPUnit\Framework\TestCase;

class UsageTest extends TestCase
{
    private const PRO_RESPONSE = <<<'JSON'
{
  "character_count": 5947223,
  "character_limit": 1000000000000,
  "products": [
    {
      "product_type": "translate",
      "billing_unit": "characters",
      "api_key_unit_count": 636,
      "account_unit_count": 5941580,
      "api_key_character_count": 636,
      "character_count": 5941580
    },
    {
      "product_type": "speechToText",
      "billing_unit": "minutes",
      "api_key_unit_count": 30,
      "account_unit_count": 30,
      "api_key_character_count": 0,
      "character_count": 0
    }
  ],
  "api_key_character_count": 636,
  "api_key_character_limit": 1000000000000,
  "speech_to_text_milliseconds_count": 0,
  "speech_to_text_milliseconds_limit": 0,
  "speech_to_text_minutes_count": 30,
  "speech_to_text_minutes_limit": 600,
  "speech_to_speech_minutes_count": 12,
  "speech_to_speech_minutes_limit": 600,
  "start_time": "2025-05-13T09:18:42Z",
  "end_time": "2025-06-13T09:18:42Z"
}
JSON;

    public function testProAccountFields()
    {
        $usage = new Usage(self::PRO_RESPONSE);

        $this->assertEquals(636, $usage->apiKeyCharacter->count);
        $this->assertEquals(30, $usage->speechToTextMinutes->count);
        $this->assertEquals(600, $usage->speechToSpeechMinutes->limit);
        $this->assertEquals(0, $usage->speechToTextMilliseconds->limit);
        $this->assertNull($usage->document);
        $this->assertEquals('2025-05-13T09:18:42Z', $usage->startTime);
        $this->assertEquals('speechToText', $usage->products[1]->productType);
        $this->assertEquals('minutes', $usage->products[1]->billingUnit);
        $this->assertEquals(30, $usage->products[1]->apiKeyUnitCount);
        // Milliseconds are reported with a limit of 0, they must not count as a reached limit.
        $this->assertFalse($usage->anyLimitReached());
        $this->assertStringContainsString('Speech-to-text minutes: 30 of 600', strval($usage));
    }

    public function testSpeechLimitReached()
    {
        $json = str_replace('"speech_to_speech_minutes_count": 12', '"speech_to_speech_minutes_count": 600', self::PRO_RESPONSE);
        $this->assertNotEquals($json, self::PRO_RESPONSE);
        $this->assertTrue((new Usage($json))->anyLimitReached());
    }

    public function testApiKeyAndAccountLimitReached()
    {
        $usage = new Usage(
            '{"character_count":500001,"character_limit":500000,"api_key_character_count":500001,"api_key_character_limit":500000}'
        );

        $this->assertTrue($usage->character->limitReached());
        $this->assertTrue($usage->apiKeyCharacter->limitReached());
        $this->assertTrue($usage->anyLimitReached());
    }

    public function testFreeAccountResponseUnchanged()
    {
        $usage = new Usage('{"character_count":180,"character_limit":500000}');

        $this->assertEquals(180, $usage->character->count);
        $this->assertNull($usage->apiKeyCharacter);
        $this->assertNull($usage->speechToTextMinutes);
        $this->assertSame([], $usage->products);
        $this->assertNull($usage->startTime);
        $this->assertFalse($usage->anyLimitReached());
    }
}
