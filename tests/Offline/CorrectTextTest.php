<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\CorrectTextOptions;
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

    /**
     * @dataProvider provideEmptyTexts
     */
    public function testThrowsWhenATextIsEmpty($texts): void
    {
        try {
            $this->makeDeepLClient()->correctText($texts);
            $this->fail('expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('text must not be empty', $exception->getMessage());
        }
        $this->assertRequestCount(0);
    }

    public static function provideEmptyTexts(): array
    {
        return ['a single empty text' => [''], 'an empty text inside the texts' => [['a', '']]];
    }

    public function testAddsExtraBodyParametersLastReplacingFields(): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->correctText('Helo', 'en-US', [
            CorrectTextOptions::EXTRA_BODY_PARAMETERS => ['target_lang' => 'de', 'beta' => 1],
        ]);

        $this->assertSame(['target_lang' => 'de', 'text' => ['Helo'], 'beta' => 1], $this->jsonBody());
    }

    public function malformedResponses(): array
    {
        return [
            'improvements missing' => ['Helo', []],
            'more improvements than texts' => ['Helo', ['improvements' => [['text' => 'a'], ['text' => 'b']]]],
            'improvement without text' => [['Helo'], ['improvements' => [['target_language' => 'en-US']]]],
            'improvement without target_language' => [
                'Helo',
                ['improvements' => [['text' => 'Hello', 'detected_source_language' => 'en']]],
            ],
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
