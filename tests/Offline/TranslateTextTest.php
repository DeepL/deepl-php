<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\Formality;
use DeepL\GlossaryInfo;
use DeepL\InvalidContentException;
use DeepL\ModelType;
use DeepL\MultilingualGlossaryInfo;
use DeepL\SentenceSplittingMode;
use DeepL\StyleProfile;
use DeepL\StyleRuleInfo;
use DeepL\TagHandlingMode;
use DeepL\TagHandlingVersion;
use DeepL\TextResult;
use DeepL\TranslateTextOptions;
use DeepL\TranslationMemoryInfo;

class TranslateTextTest extends OfflineTestCase
{
    private const REPLY = [
        'translations' => [['text' => 'Hallo', 'detected_source_language' => 'EN', 'billed_characters' => 5]],
    ];

    public function testSendsTextAsJsonArray(): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->translateText('Hello', null, 'de');

        $this->assertRequest('POST', '/v2/translate');
        $this->assertSame(
            ['target_lang' => 'de', 'show_billed_characters' => true, 'text' => ['Hello']],
            $this->jsonBody()
        );
    }

    public function testSendsMultipleTextsInOrder(): void
    {
        $this->reply(['translations' => [
            ['text' => 'Hallo', 'detected_source_language' => 'EN', 'billed_characters' => 5],
            ['text' => 'Welt', 'detected_source_language' => 'EN', 'billed_characters' => 5],
        ]]);

        $results = $this->makeDeepLClient()->translateText(['Hello', 'World'], 'en', 'de');

        $this->assertSame(
            ['target_lang' => 'de', 'source_lang' => 'en', 'show_billed_characters' => true,
                'text' => ['Hello', 'World']],
            $this->jsonBody()
        );
    }

    public function testSendsTextsWithNonSequentialKeysAsAJsonList(): void
    {
        $this->reply(['translations' => [
            ['text' => 'Hallo', 'detected_source_language' => 'EN', 'billed_characters' => 5],
            ['text' => 'Welt', 'detected_source_language' => 'EN', 'billed_characters' => 5],
        ]]);

        $this->makeDeepLClient()->translateText([0 => 'Hello', 2 => 'World'], 'en', 'de');

        $this->assertSame(
            ['target_lang' => 'de', 'source_lang' => 'en', 'show_billed_characters' => true,
                'text' => ['Hello', 'World']],
            $this->jsonBody()
        );
        $this->assertStringContainsString('"text":["Hello","World"]', (string)$this->request()->getBody());
    }

    public function testSendsStyleProfileWithoutSourceLanguage(): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->translateText('Hello', null, 'de', [
            TranslateTextOptions::STYLE_PROFILE => 'spid',
        ]);

        $this->assertSame(
            [
                'target_lang' => 'de',
                'show_billed_characters' => true,
                'text' => ['Hello'],
                'style_profile_id' => 'spid',
            ],
            $this->jsonBody()
        );
    }

    /**
     * @dataProvider provideGlossaryOptions
     */
    public function testSendsGlossaryWithoutSourceLanguage(array $options, array $expectedFields): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->translateText('Hello', null, 'de', $options);

        $this->assertSame(
            ['target_lang' => 'de'] + $expectedFields + ['show_billed_characters' => true, 'text' => ['Hello']],
            $this->jsonBody()
        );
    }

    public static function provideGlossaryOptions(): array
    {
        return [
            'glossary' => [[TranslateTextOptions::GLOSSARY => 'gid'], ['glossary_id' => 'gid']],
            'glossary_ids' => [[TranslateTextOptions::GLOSSARY_IDS => ['gid']], ['glossary_ids' => ['gid']]],
        ];
    }

    /**
     * @dataProvider provideOptions
     */
    public function testSendsOption(array $options, array $expectedBody): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->translateText('Hello', 'en', 'de', $options);

        $this->assertRequest('POST', '/v2/translate');
        $this->assertSame($expectedBody, $this->jsonBody());
    }

    public function testFormalitySentAsGiven(): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->translateText('Hello', 'en', 'de', [TranslateTextOptions::FORMALITY => 'LESS']);

        $this->assertSame('LESS', $this->jsonBody()['formality']);
    }

    public function testSplitSentencesSentAsGiven(): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->translateText(
            'Hello',
            'en',
            'de',
            [TranslateTextOptions::SPLIT_SENTENCES => 'NoNewlines']
        );

        $this->assertSame('NoNewlines', $this->jsonBody()['split_sentences']);
    }

    public static function provideOptions(): array
    {
        $base = ['target_lang' => 'de', 'source_lang' => 'en'];
        $glossary = new GlossaryInfo('gid', 'name', true, 'en', 'de', new \DateTime(), 1);
        $multilingual = new MultilingualGlossaryInfo('mid', 'name', new \DateTime(), []);
        $style = new StyleRuleInfo('sid', 'name', new \DateTime(), new \DateTime(), 'en', 1);
        $tm = new TranslationMemoryInfo('tmid', 'name', 'en', ['de'], 1);
        $profile = new StyleProfile('spid', 'name', null, null, [], [], [], false, new \DateTime(), new \DateTime());
        $tail = ['show_billed_characters' => true, 'text' => ['Hello']];
        return [
            'formality constant' => [
                [TranslateTextOptions::FORMALITY => Formality::PREFER_LESS],
                $base + ['formality' => 'prefer_less'] + $tail,
            ],
            'split_sentences former alias off sent unchanged' => [
                [TranslateTextOptions::SPLIT_SENTENCES => 'off'],
                $base + $tail + ['split_sentences' => 'off'],
            ],
            'split_sentences nonewlines' => [
                [TranslateTextOptions::SPLIT_SENTENCES => 'nonewlines'],
                $base + $tail + ['split_sentences' => 'nonewlines'],
            ],
            'split_sentences constants' => [
                [TranslateTextOptions::SPLIT_SENTENCES => SentenceSplittingMode::NO_NEWLINES],
                $base + $tail + ['split_sentences' => 'nonewlines'],
            ],
            'split_sentences constant off' => [
                [TranslateTextOptions::SPLIT_SENTENCES => SentenceSplittingMode::OFF],
                $base + $tail + ['split_sentences' => '0'],
            ],
            'booleans' => [
                [TranslateTextOptions::PRESERVE_FORMATTING => true, TranslateTextOptions::OUTLINE_DETECTION => false],
                $base + $tail + ['preserve_formatting' => true, 'outline_detection' => false],
            ],
            'tag handling' => [
                [TranslateTextOptions::TAG_HANDLING => 'xml', TranslateTextOptions::TAG_HANDLING_VERSION => 'v2'],
                $base + $tail + ['tag_handling' => 'xml', 'tag_handling_version' => 'v2'],
            ],
            'tag handling constants' => [
                [
                    TranslateTextOptions::TAG_HANDLING => TagHandlingMode::HTML,
                    TranslateTextOptions::TAG_HANDLING_VERSION => TagHandlingVersion::V1,
                ],
                $base + $tail + ['tag_handling' => 'html', 'tag_handling_version' => 'v1'],
            ],
            'tag lists from strings' => [
                [
                    TranslateTextOptions::NON_SPLITTING_TAGS => 'a,b',
                    TranslateTextOptions::SPLITTING_TAGS => 'c',
                    TranslateTextOptions::IGNORE_TAGS => '',
                ],
                $base + $tail + ['non_splitting_tags' => ['a', 'b'], 'splitting_tags' => ['c'], 'ignore_tags' => []],
            ],
            'tag lists from arrays' => [
                [TranslateTextOptions::IGNORE_TAGS => ['x', 'y']],
                $base + $tail + ['ignore_tags' => ['x', 'y']],
            ],
            'tag lists from arrays with non-sequential keys' => [
                [
                    TranslateTextOptions::NON_SPLITTING_TAGS => [1 => 'a'],
                    TranslateTextOptions::SPLITTING_TAGS => [2 => 'b', 5 => 'c'],
                    TranslateTextOptions::IGNORE_TAGS => [3 => 'x'],
                ],
                $base + $tail + ['non_splitting_tags' => ['a'], 'splitting_tags' => ['b', 'c'], 'ignore_tags' => ['x']],
            ],
            'context and model type' => [
                [TranslateTextOptions::CONTEXT => 'ctx', TranslateTextOptions::MODEL_TYPE => 'latency_optimized'],
                $base + $tail + ['context' => 'ctx', 'model_type' => 'latency_optimized'],
            ],
            'model type constant' => [
                [TranslateTextOptions::MODEL_TYPE => ModelType::PREFER_QUALITY_OPTIMIZED],
                $base + $tail + ['model_type' => 'prefer_quality_optimized'],
            ],
            'custom instructions' => [
                [TranslateTextOptions::CUSTOM_INSTRUCTIONS => ['Be brief']],
                $base + $tail + ['custom_instructions' => ['Be brief']],
            ],
            'custom instructions with non-sequential keys' => [
                [TranslateTextOptions::CUSTOM_INSTRUCTIONS => [1 => 'Be brief', 4 => 'Be formal']],
                $base + $tail + ['custom_instructions' => ['Be brief', 'Be formal']],
            ],
            'style rule from string' => [
                [TranslateTextOptions::STYLE_RULE => 'sid'],
                $base + $tail + ['style_id' => 'sid'],
            ],
            'style rule from object' => [
                [TranslateTextOptions::STYLE_RULE => $style],
                $base + $tail + ['style_id' => 'sid'],
            ],
            'style profile id from string' => [
                [TranslateTextOptions::STYLE_PROFILE => 'spid'],
                $base + $tail + ['style_profile_id' => 'spid'],
            ],
            'style profile id from object' => [
                [TranslateTextOptions::STYLE_PROFILE => $profile],
                $base + $tail + ['style_profile_id' => 'spid'],
            ],
            'translation memory from object' => [
                [TranslateTextOptions::TRANSLATION_MEMORY => $tm],
                $base + $tail + ['translation_memory_id' => 'tmid'],
            ],
            'translation memory option from string with threshold' => [
                [
                    TranslateTextOptions::TRANSLATION_MEMORY => 'tmid',
                    TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD => 80,
                ],
                $base + $tail + ['translation_memory_id' => 'tmid', 'translation_memory_threshold' => 80],
            ],
            'translation memory option from object' => [
                [TranslateTextOptions::TRANSLATION_MEMORY => $tm],
                $base + $tail + ['translation_memory_id' => 'tmid'],
            ],
            'glossary id' => [
                [TranslateTextOptions::GLOSSARY => 'gid'],
                $base + ['glossary_id' => 'gid'] + $tail,
            ],
            'glossary object' => [
                [TranslateTextOptions::GLOSSARY => $glossary],
                $base + ['glossary_id' => 'gid'] + $tail,
            ],
            'glossary ids from strings and objects' => [
                [TranslateTextOptions::GLOSSARY_IDS => ['a', $glossary, $multilingual]],
                $base + ['glossary_ids' => ['a', 'gid', 'mid']] + $tail,
            ],
            'extra body parameters override and add' => [
                [TranslateTextOptions::EXTRA_BODY_PARAMETERS => ['target_lang' => 'fr', 'new_param' => 7]],
                ['target_lang' => 'fr', 'source_lang' => 'en', 'show_billed_characters' => true,
                    'text' => ['Hello'], 'new_param' => 7],
            ],
            'extra body parameter object with __toString sent as its string' => [
                [TranslateTextOptions::EXTRA_BODY_PARAMETERS => ['context' => new TextResult('ctx', 'EN', 3)]],
                $base + $tail + ['context' => 'ctx'],
            ],
            'extra body parameters keep their JSON type' => [
                [TranslateTextOptions::EXTRA_BODY_PARAMETERS => [
                    'flag_on' => true, 'flag_off' => false, 'list' => ['a', 'b'], 'object' => ['k' => 1.5],
                ]],
                $base + $tail + [
                    'flag_on' => true, 'flag_off' => false, 'list' => ['a', 'b'], 'object' => ['k' => 1.5],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideLanguageCodes
     */
    public function testSendsStandardizedLanguageCodes(?string $source, string $target, array $expected): void
    {
        $this->reply(self::REPLY);

        $this->makeDeepLClient()->translateText('Hello', $source, $target);

        $this->assertSame($expected + ['show_billed_characters' => true, 'text' => ['Hello']], $this->jsonBody());
    }

    public static function provideLanguageCodes(): array
    {
        return [
            'mixed case' => ['EN-us', 'de-de', ['target_lang' => 'de-DE', 'source_lang' => 'en-US']],
            'bare target' => [null, 'EN', ['target_lang' => 'en']],
            'bare pt target' => [null, 'pt', ['target_lang' => 'pt']],
        ];
    }

    /**
     * @dataProvider provideUnknownOptionCalls
     */
    public function testRejectsAnUnknownOptionKeyBeforeSending(callable $call, string $message): void
    {
        try {
            $call($this->makeDeepLClient());
            $this->fail('expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
        $this->assertRequestCount(0);
    }

    public static function provideUnknownOptionCalls(): array
    {
        return [
            'translateText' => [
                function (DeepLClient $client) {
                    $client->translateText('a', null, 'de', ['no_such_option' => 1]);
                },
                'Unknown translate text option: no_such_option',
            ],
            'rephraseText with a translate option' => [
                function (DeepLClient $client) {
                    $client->rephraseText('a', 'de', ['formality' => 'more']);
                },
                'Unknown rephrase text option: formality',
            ],
            'correctText' => [
                function (DeepLClient $client) {
                    $client->correctText('a', null, ['no_such_option' => 1]);
                },
                'Unknown correct text option: no_such_option',
            ],
        ];
    }

    /**
     * @dataProvider provideRejectedArguments
     */
    public function testThrowsWhenArgumentsAreInvalid(
        $texts,
        ?string $source,
        array $options,
        string $message,
        string $target = 'de'
    ): void {
        try {
            $this->makeDeepLClient()->translateText($texts, $source, $target, $options);
            $this->fail('expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
        $this->assertRequestCount(0);
    }

    public static function provideRejectedArguments(): array
    {
        $glossary = new GlossaryInfo('gid', 'name', true, 'en', 'de', new \DateTime(), 1);
        return [
            'empty target language' => ['a', null, [], 'langCode must be a non-empty string', ''],
            'empty source language' => ['a', '', [], 'langCode must be a non-empty string'],
            'an empty text' => ['', null, [], 'text must not be empty'],
            'an empty text inside the texts' => [['a', ''], null, [], 'text must not be empty'],
            'a number instead of text' => [42, null, [], 'texts parameter'],
            'a number inside the texts' => [[42], null, [], 'texts parameter'],
            'glossary together with glossary_ids' => [
                'Hello',
                'en',
                [TranslateTextOptions::GLOSSARY => $glossary, TranslateTextOptions::GLOSSARY_IDS => ['a']],
                'cannot be used together',
            ],
            'more than five glossary_ids' => [
                'Hello', 'en', [TranslateTextOptions::GLOSSARY_IDS => ['1', '2', '3', '4', '5', '6']],
                'maximum of 5',
            ],
            'glossary_ids entry of the wrong type' => [
                'Hello', 'en', [TranslateTextOptions::GLOSSARY_IDS => [42]], 'Each glossary_ids entry',
            ],
            'style_rule of the wrong type' => [
                'Hello', 'en', [TranslateTextOptions::STYLE_RULE => 5], 'style_rule must be',
            ],
            'style_profile_id of the wrong type' => [
                'Hello', 'en', [TranslateTextOptions::STYLE_PROFILE => 5], 'style_profile must be',
            ],
            'translation_memory of the wrong type' => [
                'Hello', 'en', [TranslateTextOptions::TRANSLATION_MEMORY => 5], 'translation_memory must be',
            ],
            'threshold without translation memory' => [
                'Hello',
                'en',
                [TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD => 80],
                'requires translation_memory',
            ],
            'threshold above 100' => [
                'Hello', 'en',
                [
                    TranslateTextOptions::TRANSLATION_MEMORY => 'tm',
                    TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD => 101,
                ],
                'between 0 and 100',
            ],
        ];
    }

    public function testRejectsATextThatCannotBeJsonEncodedWithoutRequest(): void
    {
        try {
            $this->makeDeepLClient()->translateText("\xB1", null, 'de');
            $this->fail('expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('Failed to JSON-encode', $exception->getMessage());
        }
        $this->assertRequestCount(0);
    }

    public function testParsesTranslationResults(): void
    {
        $this->reply(['translations' => [[
            'text' => 'Hallo',
            'detected_source_language' => 'EN-GB',
            'billed_characters' => 5,
            'model_type_used' => 'quality_optimized',
        ]]]);

        $result = $this->makeDeepLClient()->translateText('Hello', null, 'de');

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('en-GB', $result->detectedSourceLang);
        $this->assertSame(5, $result->billedCharacters);
        $this->assertSame('quality_optimized', $result->modelTypeUsed);
    }

    public function testParsesMissingBilledCharactersAsNull(): void
    {
        $this->reply(['translations' => [['text' => 'Hallo', 'detected_source_language' => 'EN']]]);

        $result = $this->makeDeepLClient()->translateText('Hello', null, 'de');

        $this->assertNull($result->billedCharacters);
        $this->assertSame('Hallo', $result->text);
    }

    public function testParsesTagHandlingVersion(): void
    {
        $this->reply(['translations' => [[
            'text' => 'Hallo',
            'detected_source_language' => 'EN',
            'billed_characters' => 5,
            'tag_handling_version' => 'v2',
        ]]]);

        $result = $this->makeDeepLClient()->translateText('Hello', null, 'de');

        $this->assertSame(TagHandlingVersion::V2, $result->tagHandlingVersion);
    }

    public function testParsesMissingOrMalformedTagHandlingVersionAsNull(): void
    {
        $this->reply(self::REPLY);
        $this->reply(['translations' => [[
            'text' => 'Hallo',
            'detected_source_language' => 'EN',
            'billed_characters' => 5,
            'tag_handling_version' => ['v2'],
        ]]]);
        $deeplClient = $this->makeDeepLClient();

        $this->assertNull($deeplClient->translateText('Hello', null, 'de')->tagHandlingVersion);
        $malformed = $deeplClient->translateText('Hello', null, 'de');
        $this->assertNull($malformed->tagHandlingVersion);
        $this->assertSame('Hallo', $malformed->text);
        $this->assertSame(5, $malformed->billedCharacters);
    }

    public function testParsesMissingModelTypeAsNull(): void
    {
        $this->reply(self::REPLY);

        $this->assertNull($this->makeDeepLClient()->translateText('Hello', null, 'de')->modelTypeUsed);
    }

    public function testParsesInvalidUtf8AsReplacementCharacter(): void
    {
        $this->reply("{\"translations\":[{\"text\":\"a\xFFb\",\"detected_source_language\":\"EN\"," .
            "\"billed_characters\":1}]}");

        $result = $this->makeDeepLClient()->translateText('ab', null, 'fr');

        $this->assertSame("a\u{FFFD}b", $result->text);
    }

    public function testRestoresTheCallersSubstituteCharacter(): void
    {
        $previous = mb_substitute_character();
        mb_substitute_character(0x3F);
        try {
            $this->reply(self::REPLY);

            $this->makeDeepLClient()->translateText('Hello', null, 'de');

            $this->assertSame(0x3F, mb_substitute_character());
        } finally {
            mb_substitute_character($previous);
        }
    }

    /**
     * @dataProvider provideMalformedReplies
     */
    public function testThrowsInvalidContentExceptionWhenTheReplyIsMalformed($reply, $texts): void
    {
        $this->reply($reply);

        $this->expectException(InvalidContentException::class);

        $this->makeDeepLClient()->translateText($texts, null, 'de');
    }

    public static function provideMalformedReplies(): array
    {
        return [
            'no translations' => [['other' => 1], 'Hello'],
            'empty translations for a single text' => [['translations' => []], 'Hello'],
            'translation without text' => [
                ['translations' => [['detected_source_language' => 'EN', 'billed_characters' => 5]]],
                ['Hello'],
            ],
            'text of the wrong type' => [
                ['translations' => [['text' => ['x'], 'detected_source_language' => 'EN', 'billed_characters' => 5]]],
                'Hello',
            ],
        ];
    }

    public function testThrowsWhenTheReplyIsNotJson(): void
    {
        $this->reply('<html>');

        $this->expectException(InvalidContentException::class);

        $this->makeDeepLClient()->translateText('Hello', null, 'de');
    }
}
