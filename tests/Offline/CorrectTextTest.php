<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLException;
use DeepL\InvalidContentException;
use DeepL\RephraseTextResult;

class CorrectTextTest extends OfflineTestCase
{
    private const REPLY = ['improvements' => [
        ['text' => 'Fixed', 'detected_source_language' => 'en', 'target_language' => 'en-US'],
    ]];

    public function testSendsTextAndTargetLanguageAsJson(): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->correctText('Helo', 'EN-us');

        $this->assertRequest('POST', '/v2/write/correct');
        $this->assertSame(['target_lang' => 'en-US', 'text' => ['Helo']], $this->jsonBody());
    }

    public function testSendsOnlyTheTextWithoutTargetLanguage(): void
    {
        $this->reply(['improvements' => [
            ['text' => 'Hello', 'detected_source_language' => 'en', 'target_language' => 'en-US'],
            ['text' => 'World', 'detected_source_language' => 'en', 'target_language' => 'en-US'],
        ]]);

        $this->makeDeepLClient()->correctText(['Helo', 'Wrold']);

        $this->assertSame(['text' => ['Helo', 'Wrold']], $this->jsonBody());
    }

    public function testParsesResults(): void
    {
        $this->reply(self::REPLY);

        $result = $this->makeDeepLClient()->correctText('Helo');

        $this->assertInstanceOf(RephraseTextResult::class, $result);
        $this->assertSame(['en', 'en-US'], [
            $result->detectedSourceLanguage,
            $result->targetLanguage,
        ]);
    }

    public function testThrowsWhenTheTextIsEmpty(): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('text must not be empty');

        $this->makeDeepLClient()->correctText('');
    }

    public function malformedResponses(): array
    {
        return [
            'improvements missing' => ['Helo', []],
            'more improvements than texts' => ['Helo', ['improvements' => [['text' => 'a'], ['text' => 'b']]]],
            'improvement without text' => [['Helo'], ['improvements' => [['target_language' => 'en-US']]]],
        ];
    }

    /**
     * @dataProvider malformedResponses
     * @param string|string[] $texts
     */
    public function testThrowsInvalidContentExceptionOnAMalformedResponse($texts, array $body): void
    {
        $this->reply($body);

        $this->expectException(InvalidContentException::class);
        $this->makeDeepLClient()->correctText($texts);
    }
}
