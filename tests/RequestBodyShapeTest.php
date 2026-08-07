<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Pins the wire shape of the request bodies produced after the JSON migration.
 * Unlike RequestShapeTest (method/path/headers) and the validating mock
 * (spec-validity, which often allows several types), these tests assert the
 * exact JSON types the SDK sends, so a regression to the old form-encoded
 * strings would fail here.
 */
class RequestBodyShapeTest extends DeepLTestBase
{
    private const DUMMY_SERVER_URL = 'http://localhost';
    private const DUMMY_AUTH_KEY = 'test-auth-key';

    private function makeClient(string $responseBody): CapturingHttpClient
    {
        return new CapturingHttpClient($responseBody);
    }

    private function makeOptions(CapturingHttpClient $client): array
    {
        return [
            TranslatorOptions::SERVER_URL => self::DUMMY_SERVER_URL,
            TranslatorOptions::HTTP_CLIENT => $client,
        ];
    }

    private function makeCapturingTranslator(CapturingHttpClient $client): Translator
    {
        return new Translator(self::DUMMY_AUTH_KEY, $this->makeOptions($client));
    }

    private function makeCapturingDeepLClient(CapturingHttpClient $client): DeepLClient
    {
        return new DeepLClient(self::DUMMY_AUTH_KEY, $this->makeOptions($client));
    }

    private function assertJsonContentType(CapturingHttpClient $client): void
    {
        $this->assertStringContainsString(
            'application/json',
            $client->getLastRequest()->getHeaderLine('Content-Type')
        );
    }

    private static function translateResponse(): string
    {
        return json_encode([
            'translations' => [
                [
                    'text' => 'Hallo',
                    'detected_source_language' => 'EN',
                    'billed_characters' => 5,
                ],
                [
                    'text' => 'Welt',
                    'detected_source_language' => 'EN',
                    'billed_characters' => 4,
                ],
            ],
        ]);
    }

    public function testSingleTextIsSentAsJsonArray(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $this->makeCapturingTranslator($client)->translateText('Hello', null, 'de');

        $this->assertJsonContentType($client);
        $body = $client->decodeBody();
        $this->assertSame(['Hello'], $body['text']);
        // show_billed_characters must be a JSON boolean, not the old "1" string
        $this->assertSame(true, $body['show_billed_characters']);
    }

    public function testMultipleTextsArePreservedAsArray(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $this->makeCapturingTranslator($client)->translateText(['Hello', 'World'], null, 'de');

        $body = $client->decodeBody();
        $this->assertSame(['Hello', 'World'], $body['text']);
    }

    public function testBooleanOptionsAreSentAsBooleans(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $this->makeCapturingTranslator($client)->translateText('Hello', null, 'de', [
            TranslateTextOptions::PRESERVE_FORMATTING => true,
            TranslateTextOptions::OUTLINE_DETECTION => false,
        ]);

        $body = $client->decodeBody();
        $this->assertSame(true, $body['preserve_formatting']);
        $this->assertSame(false, $body['outline_detection']);
    }

    public function testSplitSentencesStaysAnEnumString(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $this->makeCapturingTranslator($client)->translateText('Hello', null, 'de', [
            TranslateTextOptions::SPLIT_SENTENCES => 'on',
        ]);

        // split_sentences is a string enum ('0'/'1'/'nonewlines'), not a boolean
        $this->assertSame('1', $client->decodeBody()['split_sentences']);
    }

    public function testTagListFromStringIsSplitIntoArray(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $this->makeCapturingTranslator($client)->translateText('Hello', null, 'de', [
            TranslateTextOptions::IGNORE_TAGS => 'a,b,c',
        ]);

        $this->assertSame(['a', 'b', 'c'], $client->decodeBody()['ignore_tags']);
    }

    public function testTagListFromArrayIsSentAsArray(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $this->makeCapturingTranslator($client)->translateText('Hello', null, 'de', [
            TranslateTextOptions::SPLITTING_TAGS => ['x', 'y'],
        ]);

        $this->assertSame(['x', 'y'], $client->decodeBody()['splitting_tags']);
    }

    public function testTranslationMemoryThresholdIsSentAsInteger(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $this->makeCapturingTranslator($client)->translateText('Hello', null, 'de', [
            TranslateTextOptions::TRANSLATION_MEMORY_ID => 'tm-1234',
            TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD => 50,
        ]);

        $this->assertSame(50, $client->decodeBody()['translation_memory_threshold']);
    }

    public function testGetLanguagesSendsTypeAsQueryParamWithEmptyBody(): void
    {
        $client = $this->makeClient(json_encode([['language' => 'EN', 'name' => 'English']]));
        $this->makeCapturingTranslator($client)->getSourceLanguages();

        $this->assertStringContainsString('type=source', $client->getLastRequestQuery());
        $this->assertSame('', $client->getLastRequestBody());
    }

    public function testCreateGlossarySendsJsonWithStringEntries(): void
    {
        $client = $this->makeClient(json_encode([
            'glossary_id' => 'abc',
            'name' => 'name',
            'ready' => true,
            'source_lang' => 'en',
            'target_lang' => 'de',
            'creation_time' => '2024-01-01T00:00:00Z',
            'entry_count' => 1,
        ]));
        $this->makeCapturingTranslator($client)->createGlossary(
            'name',
            'en',
            'de',
            GlossaryEntries::fromEntries(['Hello' => 'Hallo'])
        );

        $this->assertJsonContentType($client);
        $body = $client->decodeBody();
        $this->assertSame('tsv', $body['entries_format']);
        $this->assertIsString($body['entries']);
        $this->assertStringContainsString('Hallo', $body['entries']);
    }

    public function testGlossaryIdsAreSentAsArray(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $this->makeCapturingTranslator($client)->translateText('Hello', 'en', 'de', [
            TranslateTextOptions::GLOSSARY_IDS => ['g1', 'g2', 'g3'],
        ]);

        $body = $client->decodeBody();
        // The JSON translate body must send glossary_ids as an array (per the OpenAPI spec),
        // not a comma-separated string.
        $this->assertSame(['g1', 'g2', 'g3'], $body['glossary_ids']);
        $this->assertArrayNotHasKey('glossary_id', $body);
    }

    public function testGlossaryIdsAcceptGlossaryInfoObjects(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $glossary = new GlossaryInfo('g-info-1', 'name', true, 'en', 'de', new \DateTime(), 1);
        $this->makeCapturingTranslator($client)->translateText('Hello', 'en', 'de', [
            TranslateTextOptions::GLOSSARY_IDS => [$glossary, 'g-str-2'],
        ]);

        $this->assertSame(['g-info-1', 'g-str-2'], $client->decodeBody()['glossary_ids']);
    }

    public function testGlossaryIdsRequireSourceLang(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $translator = $this->makeCapturingTranslator($client);
        $this->assertExceptionContains('sourceLang is required', function () use ($translator) {
            $translator->translateText('Hello', null, 'de', [
                TranslateTextOptions::GLOSSARY_IDS => ['g1', 'g2'],
            ]);
        });
    }

    public function testGlossaryIdsCannotBeCombinedWithSingularGlossary(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $translator = $this->makeCapturingTranslator($client);
        $this->assertExceptionContains('cannot be used together', function () use ($translator) {
            $translator->translateText('Hello', 'en', 'de', [
                TranslateTextOptions::GLOSSARY => 'g1',
                TranslateTextOptions::GLOSSARY_IDS => ['g2', 'g3'],
            ]);
        });
    }

    public function testGlossaryIdsRejectMoreThanFive(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $translator = $this->makeCapturingTranslator($client);
        $this->assertExceptionContains('maximum of 5', function () use ($translator) {
            $translator->translateText('Hello', 'en', 'de', [
                TranslateTextOptions::GLOSSARY_IDS => ['g1', 'g2', 'g3', 'g4', 'g5', 'g6'],
            ]);
        });
    }

    public function testGlossaryIdsRejectInvalidElementType(): void
    {
        $client = $this->makeClient(self::translateResponse());
        $translator = $this->makeCapturingTranslator($client);
        // A glossary_ids entry that is neither a string nor a glossary object
        // must raise a clear error instead of a property-access warning.
        $this->assertExceptionContains('glossary_ids entry', function () use ($translator) {
            $translator->translateText('Hello', 'en', 'de', [
                TranslateTextOptions::GLOSSARY_IDS => ['g1', 123],
            ]);
        });
    }

    private static function uploadDocumentResponse(): string
    {
        return json_encode([
            'document_id' => 'doc-123',
            'document_key' => 'key-456',
        ]);
    }

    private function uploadCapturedDocument(CapturingHttpClient $client, array $options): void
    {
        list(, $exampleDocument) = $this->tempFiles();
        $this->makeCapturingTranslator($client)->uploadDocument($exampleDocument, 'en', 'de', $options);
    }

    public function testDocumentStyleIdIsSentInMultipartBody(): void
    {
        $client = $this->makeClient(self::uploadDocumentResponse());
        $this->uploadCapturedDocument($client, [
            TranslateDocumentOptions::STYLE_ID => 'style-abc',
        ]);

        $body = $client->getLastRequestBody();
        $contentType = $client->getLastRequest()->getHeaderLine('Content-Type');
        $this->assertStringContainsString('multipart/form-data', $contentType);
        $this->assertStringContainsString('name="style_id"', $body);
        $this->assertStringContainsString('style-abc', $body);
    }

    public function testDocumentStyleIdAcceptsStyleRuleInfoObject(): void
    {
        $client = $this->makeClient(self::uploadDocumentResponse());
        $styleRule = new StyleRuleInfo('style-obj', 'name', new \DateTime(), new \DateTime(), 'de', 1, null, null);
        $this->uploadCapturedDocument($client, [
            TranslateDocumentOptions::STYLE_ID => $styleRule,
        ]);

        $this->assertStringContainsString('style-obj', $client->getLastRequestBody());
    }

    public function testDocumentTranslationMemoryIdAndThresholdAreSentInMultipartBody(): void
    {
        $client = $this->makeClient(self::uploadDocumentResponse());
        $this->uploadCapturedDocument($client, [
            TranslateDocumentOptions::TRANSLATION_MEMORY_ID => 'tm-789',
            TranslateDocumentOptions::TRANSLATION_MEMORY_THRESHOLD => 75,
        ]);

        $body = $client->getLastRequestBody();
        $this->assertStringContainsString('name="translation_memory_id"', $body);
        $this->assertStringContainsString('tm-789', $body);
        $this->assertStringContainsString('name="translation_memory_threshold"', $body);
        $this->assertStringContainsString('75', $body);
    }

    public function testDocumentTranslationMemoryThresholdRequiresId(): void
    {
        $client = $this->makeClient(self::uploadDocumentResponse());
        $expectedError = 'translation_memory_threshold requires translation_memory_id';
        $this->assertExceptionContains($expectedError, function () use ($client) {
            $this->uploadCapturedDocument($client, [
                TranslateDocumentOptions::TRANSLATION_MEMORY_THRESHOLD => 75,
            ]);
        });
    }

    public function testDocumentGlossaryIdsAreSentAsCommaSeparatedString(): void
    {
        $client = $this->makeClient(self::uploadDocumentResponse());
        $this->uploadCapturedDocument($client, [
            TranslateDocumentOptions::GLOSSARY_IDS => ['g1', 'g2', 'g3'],
        ]);

        $body = $client->getLastRequestBody();
        $this->assertStringContainsString('name="glossary_ids"', $body);
        $this->assertStringContainsString('g1,g2,g3', $body);
        $this->assertStringNotContainsString('name="glossary_id"', $body);
    }

    public function testRephraseSendsTextAsJsonArray(): void
    {
        $client = $this->makeClient(json_encode([
            'improvements' => [
                [
                    'text' => 'Hi',
                    'detected_source_language' => 'EN',
                    'target_language' => 'EN',
                ],
            ],
        ]));
        $this->makeCapturingDeepLClient($client)->rephraseText('Hello', 'en-US');

        $this->assertJsonContentType($client);
        $this->assertSame(['Hello'], $client->decodeBody()['text']);
    }
}
