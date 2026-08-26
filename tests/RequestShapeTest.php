<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

class RequestShapeTest extends DeepLTestBase
{
    private const DUMMY_SERVER_URL = 'http://localhost';
    private const DUMMY_AUTH_KEY = 'test-auth-key';

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

    private function assertCommonRequestHeaders(CapturingHttpClient $client): void
    {
        $this->assertEquals(1, $client->getRequestCount());
        $request = $client->getLastRequest();
        $this->assertEquals(
            'DeepL-Auth-Key ' . self::DUMMY_AUTH_KEY,
            $request->getHeaderLine('Authorization')
        );
        $this->assertNotEmpty($request->getHeaderLine('User-Agent'));
    }

    public function testTranslateTextRequestShape(): void
    {
        $body = json_encode([
            'translations' => [
                [
                    'text' => 'Hallo',
                    'detected_source_language' => 'EN',
                    'billed_characters' => 5,
                ],
            ],
        ]);
        $client = new CapturingHttpClient($body);
        $translator = $this->makeCapturingTranslator($client);

        $translator->translateText('Hello', null, 'de');

        $this->assertEquals('POST', $client->getLastRequestMethod());
        $this->assertEquals('/v2/translate', $client->getLastRequestPath());
        $this->assertCommonRequestHeaders($client);
    }

    public function testGetSourceLanguagesRequestShape(): void
    {
        $body = json_encode([
            ['lang' => 'en', 'name' => 'English', 'status' => 'stable',
                'usable_as_source' => true, 'usable_as_target' => true, 'features' => []],
        ]);
        $client = new CapturingHttpClient($body);
        $translator = $this->makeCapturingTranslator($client);

        $translator->getSourceLanguages();

        $this->assertEquals('GET', $client->getLastRequestMethod());
        $this->assertEquals('/v3/languages', $client->getLastRequestPath());
        $this->assertStringContainsString('resource=translate_text', $client->getLastRequestQuery());
        $this->assertCommonRequestHeaders($client);
    }

    public function testGetTargetLanguagesRequestShape(): void
    {
        $body = json_encode([
            ['lang' => 'de', 'name' => 'German', 'status' => 'stable', 'usable_as_source' => true,
                'usable_as_target' => true, 'features' => ['formality' => ['status' => 'stable']]],
        ]);
        $client = new CapturingHttpClient($body);
        $translator = $this->makeCapturingTranslator($client);

        $translator->getTargetLanguages();

        $this->assertEquals('GET', $client->getLastRequestMethod());
        $this->assertEquals('/v3/languages', $client->getLastRequestPath());
        $this->assertStringContainsString('resource=translate_text', $client->getLastRequestQuery());
        $this->assertCommonRequestHeaders($client);
    }

    public function testCreateGlossaryRequestShape(): void
    {
        $body = json_encode([
            'glossary_id' => 'abc',
            'name' => 'name',
            'ready' => true,
            'source_lang' => 'en',
            'target_lang' => 'de',
            'creation_time' => '2024-01-01T00:00:00Z',
            'entry_count' => 1,
        ]);
        $client = new CapturingHttpClient($body);
        $translator = $this->makeCapturingTranslator($client);

        $translator->createGlossary(
            'name',
            'en',
            'de',
            GlossaryEntries::fromEntries(['Hello' => 'Hallo'])
        );

        $this->assertEquals('POST', $client->getLastRequestMethod());
        $this->assertEquals('/v2/glossaries', $client->getLastRequestPath());
        $this->assertCommonRequestHeaders($client);
    }

    public function testCreateGlossaryFromCsvRequestShape(): void
    {
        $body = json_encode([
            'glossary_id' => 'abc',
            'name' => 'name',
            'ready' => true,
            'source_lang' => 'en',
            'target_lang' => 'de',
            'creation_time' => '2024-01-01T00:00:00Z',
            'entry_count' => 1,
        ]);
        $client = new CapturingHttpClient($body);
        $translator = $this->makeCapturingTranslator($client);

        $translator->createGlossaryFromCsv('name', 'en', 'de', "Hello,Hallo\n");

        $this->assertEquals('POST', $client->getLastRequestMethod());
        $this->assertEquals('/v2/glossaries', $client->getLastRequestPath());
        $this->assertCommonRequestHeaders($client);
    }

    public function testRephraseTextRequestShape(): void
    {
        $body = json_encode([
            'improvements' => [
                [
                    'text' => 'Hi',
                    'detected_source_language' => 'EN',
                    'target_language' => 'EN',
                ],
            ],
        ]);
        $client = new CapturingHttpClient($body);
        $deeplClient = $this->makeCapturingDeepLClient($client);

        $deeplClient->rephraseText('Hello', 'en-US');

        $this->assertEquals('POST', $client->getLastRequestMethod());
        $this->assertEquals('/v2/write/rephrase', $client->getLastRequestPath());
        $this->assertCommonRequestHeaders($client);
    }

    public function testGetLanguagesV3RequestShape(): void
    {
        $client = new CapturingHttpClient('[]');
        $deeplClient = $this->makeCapturingDeepLClient($client);

        $deeplClient->getLanguagesForResource(
            LanguageResource::RESOURCE_TRANSLATE_TEXT,
            [LanguageSupport::INCLUDE_BETA, LanguageSupport::INCLUDE_EXTERNAL]
        );

        $this->assertEquals('GET', $client->getLastRequestMethod());
        $this->assertEquals('/v3/languages', $client->getLastRequestPath());
        $query = $client->getLastRequestQuery();
        $this->assertStringContainsString('resource=translate_text', $query);
        // include must be repeated, not bracket-indexed
        $this->assertStringContainsString('include=beta', $query);
        $this->assertStringContainsString('include=external', $query);
        $this->assertStringNotContainsString('include%5B', $query);
        $this->assertCommonRequestHeaders($client);
    }

    public function testGetLanguageResourcesRequestShape(): void
    {
        $client = new CapturingHttpClient('[]');
        $deeplClient = $this->makeCapturingDeepLClient($client);

        $deeplClient->getLanguageResources();

        $this->assertEquals('GET', $client->getLastRequestMethod());
        $this->assertEquals('/v3/languages/resources', $client->getLastRequestPath());
        $this->assertCommonRequestHeaders($client);
    }

    public function testTranslateTextCannedResponseParsing(): void
    {
        $body = json_encode([
            'translations' => [
                [
                    'text' => 'Hallo',
                    'detected_source_language' => 'EN',
                    'billed_characters' => 5,
                ],
            ],
        ]);
        $client = new CapturingHttpClient($body);
        $translator = $this->makeCapturingTranslator($client);

        $result = $translator->translateText('Hello', null, 'de');

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Hallo', $result->text);
        $this->assertEquals('en', $result->detectedSourceLang);
    }

    public function testUsageCannedResponseParsing(): void
    {
        $body = json_encode([
            'character_count' => 100,
            'character_limit' => 1000,
        ]);
        $client = new CapturingHttpClient($body);
        $translator = $this->makeCapturingTranslator($client);

        $usage = $translator->getUsage();

        $this->assertNotNull($usage->character);
        $this->assertEquals(100, $usage->character->count);
        $this->assertEquals(1000, $usage->character->limit);
        $this->assertNull($usage->document);
        $this->assertNull($usage->teamDocument);
    }
}
