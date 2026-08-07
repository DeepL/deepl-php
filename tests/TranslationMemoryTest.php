<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use Psr\Http\Client\ClientInterface;

class TranslationMemoryTest extends DeepLTestBase
{
    private const DEFAULT_TM_ID = 'a74d88fb-ed2a-4943-a664-a4512398b994';

    /**
     * @dataProvider provideHttpClient
     */
    public function testListTranslationMemories(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $translationMemories = $client->listTranslationMemories(0, 10);
        $this->assertIsArray($translationMemories);
        $this->assertGreaterThan(0, count($translationMemories));
        $this->assertNotEmpty($translationMemories[0]->translationMemoryId);
        $this->assertNotEmpty($translationMemories[0]->name);
        $this->assertNotEmpty($translationMemories[0]->sourceLanguage);
        $this->assertIsArray($translationMemories[0]->targetLanguages);
        $this->assertIsInt($translationMemories[0]->segmentCount);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateTextWithTranslationMemoryId(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        // Note: this test may use the mock server that will not translate the text,
        // therefore we do not check the translated result.
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $exampleText = DeepLTestBase::EXAMPLE_TEXT['de'];

        $result = $client->translateText(
            $exampleText,
            'de',
            'en-US',
            [TranslateTextOptions::TRANSLATION_MEMORY_ID => self::DEFAULT_TM_ID]
        );

        $this->assertNotNull($result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateTextWithTranslationMemoryIdAndThreshold(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        // Note: this test may use the mock server that will not translate the text,
        // therefore we do not check the translated result.
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $exampleText = DeepLTestBase::EXAMPLE_TEXT['de'];

        $result = $client->translateText(
            $exampleText,
            'de',
            'en-US',
            [
                TranslateTextOptions::TRANSLATION_MEMORY_ID => self::DEFAULT_TM_ID,
                TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD => 80,
            ]
        );

        $this->assertNotNull($result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateDocumentWithTranslationMemoryId(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        list(, $exampleDocument, , $outputDocumentPath) = $this->tempFiles();
        $this->writeFile($exampleDocument, DeepLTestBase::EXAMPLE_TEXT['de']);

        // Default translation memory supports target languages en, es and fr.
        $status = $client->translateDocument(
            $exampleDocument,
            $outputDocumentPath,
            'de',
            'en-US',
            [TranslateDocumentOptions::TRANSLATION_MEMORY_ID => self::DEFAULT_TM_ID]
        );

        $this->assertNotNull($status);
        $this->assertEquals('done', $status->status);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateDocumentWithTranslationMemoryIdAndThreshold(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        list(, $exampleDocument, , $outputDocumentPath) = $this->tempFiles();
        $this->writeFile($exampleDocument, DeepLTestBase::EXAMPLE_TEXT['de']);

        $status = $client->translateDocument(
            $exampleDocument,
            $outputDocumentPath,
            'de',
            'en-US',
            [
                TranslateDocumentOptions::TRANSLATION_MEMORY_ID => self::DEFAULT_TM_ID,
                TranslateDocumentOptions::TRANSLATION_MEMORY_THRESHOLD => 80,
            ]
        );

        $this->assertNotNull($status);
        $this->assertEquals('done', $status->status);
    }
}
