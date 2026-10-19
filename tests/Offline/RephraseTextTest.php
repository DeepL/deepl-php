<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\RephraseTextOptions;
use DeepL\RephraseTextResult;

class RephraseTextTest extends OfflineTestCase
{
    private const REPLY = ['improvements' => [
        ['text' => 'Better', 'detected_source_language' => 'en', 'target_language' => 'en-GB'],
    ]];

    public function testSendsTextTargetLanguageAndOptionsAsJson(): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->rephraseText('Hello', 'EN-gb', [
            RephraseTextOptions::WRITING_STYLE => 'business',
            RephraseTextOptions::TONE => 'friendly',
        ]);

        $this->assertRequest('POST', '/v2/write/rephrase');
        $this->assertSame(
            ['target_lang' => 'en-GB', 'writing_style' => 'business', 'tone' => 'friendly', 'text' => ['Hello']],
            $this->jsonBody()
        );
    }

    public function testSendsOnlyTheTextWhenNoTargetLanguageOrOptionIsGiven(): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->rephraseText('Hello');

        $this->assertSame(['text' => ['Hello']], $this->jsonBody());
    }

    public function testSendsABareTargetLanguage(): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->rephraseText('Hello', 'EN');

        $this->assertSame(['target_lang' => 'en', 'text' => ['Hello']], $this->jsonBody());
    }

    public function testParsesOneResultPerText(): void
    {
        $this->reply(['improvements' => [
            ['text' => 'One', 'detected_source_language' => 'en', 'target_language' => 'en-US'],
            ['text' => 'Two', 'detected_source_language' => 'de', 'target_language' => 'en-US'],
        ]]);

        $results = $this->makeDeepLClient()->rephraseText(['a', 'b'], 'en-US');

        $this->assertSame(['a', 'b'], $this->jsonBody()['text']);
        $this->assertContainsOnlyInstancesOf(RephraseTextResult::class, $results);
        $this->assertSame(['One', 'en', 'en-US'], [
            $results[0]->text,
            $results[0]->detectedSourceLanguage,
            $results[0]->targetLanguage,
        ]);
        $this->assertSame('de', $results[1]->detectedSourceLanguage);
    }

    public function testParsesASingleResultForASingleText(): void
    {
        $this->reply(self::REPLY);

        $result = $this->makeDeepLClient()->rephraseText('Hello', 'en-GB');

        $this->assertInstanceOf(RephraseTextResult::class, $result);
        $this->assertSame('Better', $result->text);
    }
}
