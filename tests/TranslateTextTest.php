<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use \Psr\Http\Client\ClientInterface;

class TranslateTextTest extends DeepLTestBase
{
    /**
     * @dataProvider provideHttpClient
     */
    public function testExampleText(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        foreach (DeepLTestBase::EXAMPLE_TEXT as $langCode => $exampleText) {
            $sourceLang = \DeepL\LanguageCode::removeRegionalVariant($langCode);
            $result = $translator->translateText($exampleText, $sourceLang, 'en-US');
            $this->assertStringContainsStringIgnoringCase('proton', $result->text, "LangCode: $langCode");
            $this->assertEquals(mb_strlen($exampleText), $result->billedCharacters);
        }
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testMultipleText(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = [DeepLTestBase::EXAMPLE_TEXT['fr'], DeepLTestBase::EXAMPLE_TEXT['en']];
        $result = $translator->translateText($input, null, 'de');
        $this->assertEquals(DeepLTestBase::EXAMPLE_TEXT['de'], $result[0]->text);
        $this->assertEquals('fr', $result[0]->detectedSourceLang);
        $this->assertEquals(mb_strlen($input[0]), $result[0]->billedCharacters);
        $this->assertEquals(DeepLTestBase::EXAMPLE_TEXT['de'], $result[1]->text);
        $this->assertEquals('en', $result[1]->detectedSourceLang);
        $this->assertEquals(mb_strlen($input[1]), $result[1]->billedCharacters);
    }
    /**
     * @dataProvider provideHttpClientAndModelType
     */
    public function testModelType(?ClientInterface $httpClient, string $modelTypeArgName)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = [DeepLTestBase::EXAMPLE_TEXT['en']];
        $result = $translator->translateText(
            $input,
            null,
            'de',
            [TranslateTextOptions::MODEL_TYPE => $modelTypeArgName]
        );
        $this->assertEquals(DeepLTestBase::EXAMPLE_TEXT['de'], $result[0]->text);
        $this->assertEquals(mb_strlen($input[0]), $result[0]->billedCharacters);
        $this->assertNotNull($result[0]->modelTypeUsed);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testMixedDirectionText(?ClientInterface $httpClient)
    {
        $this->needsRealServer();
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $options = [
            TranslateTextOptions::TAG_HANDLING => 'xml',
            TranslateTextOptions::IGNORE_TAGS => 'ignore',
        ];

        $ar_ignore_part = "<ignore>يجب تجاهل هذا الجزء.</ignore>";
        $en_sentence_with_ar_ignore_part =
            "<p>This is a <b>short</b> <i>sentence</i>.</p> $ar_ignore_part This is another sentence.";

        $en_ignore_part = "<ignore>This part should be ignored.</ignore>";
        $ar_sentence_with_en_ignore_part = "<p>هذه <i>جملة</i> <b>قصيرة</b>. $en_ignore_part هذه جملة أخرى.</p>";

        $en_result = $translator->translateText($en_sentence_with_ar_ignore_part, null, 'en-US', $options);
        $this->assertStringContainsString($ar_ignore_part, $en_result);
        $ar_result = $translator->translateText($ar_sentence_with_en_ignore_part, null, 'ar', $options);
        $this->assertStringContainsString($en_ignore_part, $ar_result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testHandlingResponseWithInvalidUtf8(?ClientInterface $httpClient)
    {
        $this->needsRealServer();
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = 'Portal<span></span>';
        $result = $translator->translateText($input, 'en', 'fr', [
            TranslateTextOptions::TAG_HANDLING => 'xml',
            TranslateTextOptions::IGNORE_TAGS => 'notranslate',
        ]);
        $this->assertInstanceOf(TextResult::class, $result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTagHandlingVersion(?ClientInterface $httpClient)
    {
        $this->needsRealServer();
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $text = '<p>Hello world</p>';

        $resultV1 = $translator->translateText($text, 'en', 'de', [
            TranslateTextOptions::TAG_HANDLING => 'html',
            TranslateTextOptions::TAG_HANDLING_VERSION => 'v1',
        ]);
        $this->assertInstanceOf(TextResult::class, $resultV1);
        $this->assertNotNull($resultV1->text);
        $this->assertNotEmpty($resultV1->text);

        $resultV2 = $translator->translateText($text, 'en', 'de', [
            TranslateTextOptions::TAG_HANDLING => 'html',
            TranslateTextOptions::TAG_HANDLING_VERSION => 'v2',
        ]);
        $this->assertInstanceOf(TextResult::class, $resultV2);
        $this->assertNotNull($resultV2->text);
        $this->assertNotEmpty($resultV2->text);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testLangCodeMixedCase(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $result = $translator->translateText(DeepLTestBase::EXAMPLE_TEXT['en'], 'en', 'de');
        $this->assertEquals(DeepLTestBase::EXAMPLE_TEXT['de'], $result->text);
        $this->assertEquals('en', $result->detectedSourceLang);

        $result = $translator->translateText(DeepLTestBase::EXAMPLE_TEXT['en'], 'eN', 'De');
        $this->assertEquals(DeepLTestBase::EXAMPLE_TEXT['de'], $result->text);
        $this->assertEquals('en', $result->detectedSourceLang);
    }

    public static function bareTargetLang(): array
    {
        return [['en'], ['pt']];
    }

    /**
     * @dataProvider bareTargetLang
     */
    public function testBareTargetLangIsAccepted(string $targetLang)
    {
        $translator = $this->makeTranslator();
        $result = $translator->translateText(DeepLTestBase::EXAMPLE_TEXT['de'], null, $targetLang);
        $this->assertNotEmpty($result->text);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testInvalidSourceLanguage(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $this->expectExceptionMessage('source_lang');
        $translator->translateText(DeepLTestBase::EXAMPLE_TEXT['de'], 'xx', 'en-US');
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testInvalidTargetLanguage(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $this->expectExceptionMessage('target_lang');
        $translator->translateText(DeepLTestBase::EXAMPLE_TEXT['de'], null, 'xx');
    }

    public static function invalidTextParameters(): array
    {
        return [[42], [[42]]];
    }

    /**
     * @dataProvider invalidTextParameters
     * @param string|string[] $texts
     * @throws DeepLException
     */
    public function testInvalidText($texts)
    {
        $translator = $this->makeTranslator();
        $this->expectExceptionMessage('texts parameter');
        $translator->translateText($texts, null, 'de');
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateWithRetries(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $this->session429Count = 2;
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $timeBefore = microtime(true);
        $translator->translateText(DeepLTestBase::EXAMPLE_TEXT['en'], null, 'de');
        $timeAfter = microtime(true);
        // Elapsed time should be at least 1 second
        $this->assertGreaterThan(1.0, $timeAfter - $timeBefore);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testEmptyText(?ClientInterface $httpClient)
    {
        $this->needsRealServer();
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $this->assertEquals('', $translator->translateText('', null, 'de')->text);
        $extractTextFromResult = function ($res) {
            return $res->text;
        };
        $this->assertEquals([''], \array_map($extractTextFromResult, $translator->translateText([''], null, 'de')));
        $this->assertEquals(
            ['', ''],
            \array_map($extractTextFromResult, $translator->translateText(['', ''], null, 'de'))
        );
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testFormality(?ClientInterface $httpClient)
    {
        $this->needsRealServer();
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = 'How are you?';
        $formal = 'Ihnen'; // Wie geht es Ihnen?
        $informal = 'dir'; // Wie geht es dir?

        // Default formality is automatic, so the output may be either formal or informal
        $defaultResult = $translator->translateText($input, null, 'de')->text;
        $this->assertTrue(
            str_contains($defaultResult, $formal) || str_contains($defaultResult, $informal),
            "Expected default formality result to contain either '$formal' or '$informal', got: $defaultResult"
        );
        $this->assertStringContainsString($informal, $translator->translateText(
            $input,
            null,
            'de',
            [TranslateTextOptions::FORMALITY => 'less']
        )->text);
        $defaultExplicitResult = $translator->translateText(
            $input,
            null,
            'de',
            [TranslateTextOptions::FORMALITY => 'default']
        )->text;
        $this->assertTrue(
            str_contains($defaultExplicitResult, $formal) || str_contains($defaultExplicitResult, $informal)
        );
        $this->assertStringContainsString($formal, $translator->translateText(
            $input,
            null,
            'de',
            [TranslateTextOptions::FORMALITY => 'more']
        )->text);

        // Case-insensitive tests
        $this->assertStringContainsString($informal, $translator->translateText(
            $input,
            null,
            'de',
            [TranslateTextOptions::FORMALITY => 'Less']
        )->text);
        $this->assertStringContainsString($formal, $translator->translateText(
            $input,
            null,
            'de',
            [TranslateTextOptions::FORMALITY => 'MORE']
        )->text);

        // prefer_* tests
        $this->assertStringContainsString($informal, $translator->translateText(
            $input,
            null,
            'de',
            [TranslateTextOptions::FORMALITY => 'prefer_less']
        )->text);
        $this->assertStringContainsString($formal, $translator->translateText(
            $input,
            null,
            'de',
            [TranslateTextOptions::FORMALITY => 'prefer_more']
        )->text);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testInvalidFormality(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = 'How are you?';
        $this->expectExceptionMessage('formality');
        $translator->translateText($input, null, 'de', [TranslateTextOptions::FORMALITY => 'invalid']);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testPreserveFormatting(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = DeepLTestBase::EXAMPLE_TEXT['en'];
        $translator->translateText($input, null, 'de', [TranslateTextOptions::PRESERVE_FORMATTING => false]);
        $translator->translateText($input, null, 'de', [TranslateTextOptions::PRESERVE_FORMATTING => true]);
        // Add a dummy assertion to avoid warnings
        $this->assertTrue(true);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testSplitSentences(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = "The firm said it had been\nconducting an internal investigation.";
        $translator->translateText($input, null, 'de', [TranslateTextOptions::SPLIT_SENTENCES => 'off']);
        $translator->translateText($input, null, 'de', [TranslateTextOptions::SPLIT_SENTENCES => 'on']);
        $translator->translateText($input, null, 'de', [TranslateTextOptions::SPLIT_SENTENCES => 'nonewlines']);
        $translator->translateText($input, null, 'de', [TranslateTextOptions::SPLIT_SENTENCES => 'default']);

        // Invalid sentence splitting modes are rejected
        $this->expectExceptionMessage('split_sentences');
        $translator->translateText($input, null, 'de', [TranslateTextOptions::SPLIT_SENTENCES => 'invalid']);
    }

    /**
     * @dataProvider provideHttpClient
     * @doesNotPerformAssertions
     */
    public function testContext(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        // In German, "scharf" can mean:
        // - spicy/hot when referring to food, or
        // - sharp when referring to other objects such as a knife (Messer).
        $input = 'Das ist scharf!';
        $translator->translateText($input, 'de', 'en-US');
        // Result: "That is hot!"

        $translator->translateText($input, 'de', 'en-US', [TranslateTextOptions::CONTEXT => 'Das ist ein Messer.']);
        // Result: "That is sharp!"
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTagHandlingBasic(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = "<!DOCTYPE html>\n<html>\n<body>\n<p>This is an example sentence.</p>\n</body>\n</html>";
        // Note: this test may use the mock server that will not translate the text,
        // therefore we do not check the translated result.
        $translator->translateText($input, null, 'de', [TranslateTextOptions::TAG_HANDLING => 'xml']);
        $translator->translateText($input, null, 'de', [TranslateTextOptions::TAG_HANDLING => 'html']);
        // Add a dummy assertion to avoid warnings
        $this->assertTrue(true);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTagHandlingXML(?ClientInterface $httpClient)
    {
        $this->needsRealServer();
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $text = "
            <document>
                <meta>
                    <title>A document's title</title>
                </meta>
                <content>
                    <par>
                        <span>This is a sentence split</span>
                        <span>across two &lt;span&gt; tags that should be treated as one.
                        </span>
                    </par>
                    <par>Here is a sentence. Followed by a second one.</par>
                    <raw>This sentence will not be translated.</raw>
                </content>
            </document>";

        $result = $translator->translateText(
            $text,
            null,
            'de',
            [TranslateTextOptions::TAG_HANDLING => 'xml',
                TranslateTextOptions::OUTLINE_DETECTION => false,
                TranslateTextOptions::NON_SPLITTING_TAGS => 'span',
                TranslateTextOptions::SPLITTING_TAGS => ['title', 'par'],
                TranslateTextOptions::IGNORE_TAGS => ['raw'],
            ]
        );

        $this->assertStringContainsString('<raw>This sentence will not be translated.</raw>', $result->text);
        $this->assertMatchesRegularExpression('#<title>.*Der Titel.*</title>#', $result->text);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTagHandlingHTML(?ClientInterface $httpClient)
    {
        $this->needsRealServer();
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $text = '
            <!DOCTYPE html>
            <html>
               <body>
                   <h1>My First Heading</h1>
                   <p translate="no">My first paragraph.</p>
               </body>
            </html>';

        $result = $translator->translateText($text, null, 'de', [TranslateTextOptions::TAG_HANDLING => 'html']);

        $this->assertStringContainsString('<h1>Meine erste Überschrift</h1>', $result->text);
        $this->assertStringContainsString('<p translate="no">My first paragraph.</p>', $result->text);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testExtraBodyParams(?ClientInterface $httpClient)
    {
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $extra = ['target_lang' => 'FR', 'debug' => '1'];
        $result = $translator->translateText(
            DeepLTestBase::EXAMPLE_TEXT['en'],
            null,
            'de',
            [TranslateTextOptions::EXTRA_BODY_PARAMETERS => $extra]
        );

        $this->assertEquals(DeepLTestBase::EXAMPLE_TEXT['fr'], $result->text);
        $this->assertEquals('en', $result->detectedSourceLang);
        $this->assertEquals(mb_strlen(DeepLTestBase::EXAMPLE_TEXT['en']), $result->billedCharacters);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testCustomInstructions(?ClientInterface $httpClient)
    {
        $this->needsRealServer();
        $translator = $this->makeTranslator([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = "I am testing if custom instructions are working correctly.";
        $customInstructions = ['Use informal language', 'Be concise'];
        $resultWithCustomInstructions = $translator->translateText($input, null, 'de', [
            TranslateTextOptions::CUSTOM_INSTRUCTIONS => $customInstructions
        ]);

        $resultWithoutCustomInstructions = $translator->translateText($input, null, 'de');
        $this->assertNotEquals($resultWithoutCustomInstructions->text, $resultWithCustomInstructions->text);
    }
}
