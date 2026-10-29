<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLException;
use DeepL\GetLanguagesForResourceOptions;
use DeepL\GlossaryLanguagePair;
use DeepL\LanguageFeature;
use DeepL\LanguageInclude;
use DeepL\LanguageResource;
use DeepL\LanguageResourceName;
use DeepL\LanguageSupport;

class LanguagesTest extends OfflineTestCase
{
    private const LANGUAGES = [
        [
            'lang' => 'de',
            'name' => 'German',
            'status' => 'stable',
            'usable_as_source' => true,
            'usable_as_target' => false,
            'features' => ['tag_handling' => ['status' => 'stable']],
        ],
        [
            'lang' => 'en-US',
            'name' => 'English (American)',
            'status' => 'stable',
            'usable_as_source' => false,
            'usable_as_target' => true,
            'features' => ['formality' => ['status' => 'stable']],
        ],
        [
            'lang' => 'fr',
            'name' => 'French',
            'status' => 'stable',
            'usable_as_source' => true,
            'usable_as_target' => true,
            'features' => [],
        ],
    ];

    public function testSplitsSourceLanguagesAndUpperCasesTheCodes(): void
    {
        $this->reply(self::LANGUAGES);

        $languages = $this->makeTranslator()->getSourceLanguages();

        $this->assertRequest('GET', '/v3/languages', 0, 'resource=translate_text');
        $this->assertSame(['DE', 'FR'], [$languages[0]->code, $languages[1]->code]);
        $this->assertNull($languages[0]->supportsFormality);
    }

    public function testSplitsTargetLanguagesAndParsesFormalitySupport(): void
    {
        $this->reply(self::LANGUAGES);

        $languages = $this->makeTranslator()->getTargetLanguages();

        $this->assertRequest('GET', '/v3/languages', 0, 'resource=translate_text');
        $this->assertSame(['EN-US', 'FR'], [$languages[0]->code, $languages[1]->code]);
        $this->assertTrue($languages[0]->supportsFormality);
        $this->assertFalse($languages[1]->supportsFormality);
    }

    public function testBuildsGlossaryLanguagePairsAsCrossProductWithoutSelfPairs(): void
    {
        $this->reply(self::LANGUAGES);

        $pairs = $this->makeTranslator()->getGlossaryLanguages();

        $this->assertRequest('GET', '/v3/languages', 0, 'resource=glossary');
        $this->assertContainsOnlyInstancesOf(GlossaryLanguagePair::class, $pairs);
        $this->assertSame(
            ['de->en-US', 'de->fr', 'fr->en-US'],
            array_map(function ($pair) {
                return "$pair->sourceLang->$pair->targetLang";
            }, $pairs)
        );
    }

    public function testSendsTheResourceAndRepeatedIncludeParameters(): void
    {
        $this->reply(self::LANGUAGES);

        $this->makeDeepLClient()->getLanguagesForResource(
            LanguageResource::RESOURCE_WRITE,
            [LanguageSupport::INCLUDE_BETA, LanguageSupport::INCLUDE_EXTERNAL]
        );

        $this->assertRequest('GET', '/v3/languages', 0, 'resource=write&include=beta&include=external');
    }

    public function testSendsTheLanguageResourceNameAndLanguageIncludeValues(): void
    {
        $this->reply(self::LANGUAGES);

        $this->makeDeepLClient()->getLanguagesForResource(
            LanguageResourceName::TRANSLATION_MEMORY,
            [LanguageInclude::BETA, LanguageInclude::EXTERNAL]
        );

        $this->assertRequest('GET', '/v3/languages', 0, 'resource=translation_memory&include=beta&include=external');
    }

    public function testSendsTheIncludeOptionOfAnOptionsArray(): void
    {
        $this->reply(self::LANGUAGES);

        $this->makeDeepLClient()->getLanguagesForResource(
            LanguageResourceName::GLOSSARY,
            [GetLanguagesForResourceOptions::INCLUDE => [LanguageInclude::BETA, LanguageInclude::EXTERNAL]]
        );

        $this->assertRequest('GET', '/v3/languages', 0, 'resource=glossary&include=beta&include=external');
    }

    public function testThrowsWhenTheIncludeOptionIsNotAnArray(): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('include option must be an array of strings');

        $this->makeDeepLClient()->getLanguagesForResource(
            LanguageResourceName::GLOSSARY,
            [GetLanguagesForResourceOptions::INCLUDE => LanguageInclude::BETA]
        );
    }

    public function testParsesLanguageSupportAndFeatures(): void
    {
        $this->reply(self::LANGUAGES);

        $languages = $this->makeDeepLClient()->getLanguagesForResource(LanguageResource::RESOURCE_TRANSLATE_TEXT);

        $this->assertRequest('GET', '/v3/languages', 0, 'resource=translate_text');
        $this->assertSame('de', $languages[0]->code);
        $this->assertTrue($languages[0]->usableAsSource);
        $this->assertFalse($languages[0]->usableAsTarget);
        $this->assertTrue($languages[0]->supports(LanguageFeature::FEATURE_TAG_HANDLING));
        $this->assertFalse($languages[0]->supports(LanguageFeature::FEATURE_FORMALITY));
        $this->assertSame([], $languages[2]->features);
    }

    public function testParsesAnEmptyFeaturesObject(): void
    {
        $this->reply('[{"lang":"de","name":"German","status":"stable","usable_as_source":1,'
            . '"usable_as_target":0,"features":{}}]');

        $languages = $this->makeDeepLClient()->getLanguagesForResource('translate_text');

        $this->assertSame([], $languages[0]->features);
        $this->assertTrue($languages[0]->usableAsSource);
        $this->assertFalse($languages[0]->usableAsTarget);
    }

    public function testThrowsWhenTheResourceIsEmpty(): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('resource must be a non-empty string');

        $this->makeDeepLClient()->getLanguagesForResource('');
    }

    public function testParsesLanguageResources(): void
    {
        $this->reply([
            [
                'name' => 'translate_text',
                'features' => [
                    ['name' => 'glossary', 'needs_source_support' => true, 'needs_target_support' => true],
                    ['name' => 'formality', 'needs_target_support' => true],
                ],
            ],
            ['name' => 'glossary', 'features' => []],
        ]);

        $resources = $this->makeDeepLClient()->getLanguageResources();

        $this->assertRequest('GET', '/v3/languages/resources');
        $this->assertSame('translate_text', $resources[0]->resource);
        $this->assertTrue($resources[0]->supports(LanguageFeature::FEATURE_GLOSSARY));
        $this->assertFalse($resources[0]->supports(LanguageFeature::FEATURE_TONE));
        $this->assertTrue($resources[0]->features['glossary']->needsSourceSupport);
        $this->assertFalse($resources[0]->features['formality']->needsSourceSupport);
        $this->assertTrue($resources[0]->features['formality']->needsTargetSupport);
        $this->assertSame([], $resources[1]->features);
    }
}
