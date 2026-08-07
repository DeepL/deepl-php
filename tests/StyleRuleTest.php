<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use Psr\Http\Client\ClientInterface;

class StyleRuleTest extends DeepLTestBase
{
    private const DEFAULT_STYLE_ID = 'dca2e053-8ae5-45e6-a0d2-881156e7f4e4';

    /**
     * @dataProvider provideHttpClient
     */
    public function testGetAllStyleRules(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $styleRules = $client->getAllStyleRules(0, 10, true);
        $this->assertIsArray($styleRules);
        $this->assertGreaterThan(0, count($styleRules));
        $this->assertEquals(self::DEFAULT_STYLE_ID, $styleRules[0]->styleId);
        $this->assertEquals('Default Style Rule', $styleRules[0]->name);
        $this->assertNotNull($styleRules[0]->creationTime);
        $this->assertNotNull($styleRules[0]->updatedTime);
        $this->assertEquals('en', $styleRules[0]->language);
        $this->assertEquals(1, $styleRules[0]->version);
        $this->assertNotNull($styleRules[0]->configuredRules);
        $this->assertNotNull($styleRules[0]->customInstructions);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testGetAllStyleRulesWithoutDetailed(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $styleRules = $client->getAllStyleRules();

        $this->assertIsArray($styleRules);
        $this->assertGreaterThan(0, count($styleRules));
        $this->assertEquals(self::DEFAULT_STYLE_ID, $styleRules[0]->styleId);
        $this->assertNull($styleRules[0]->configuredRules);
        $this->assertNull($styleRules[0]->customInstructions);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateTextWithStyleId(?ClientInterface $httpClient)
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
            [TranslateTextOptions::STYLE_ID => self::DEFAULT_STYLE_ID]
        );

        $this->assertNotNull($result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateTextWithStyleRuleInfo(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $styleRules = $client->getAllStyleRules();
        $styleRule = $styleRules[0];
        $exampleText = DeepLTestBase::EXAMPLE_TEXT['de'];

        $result = $client->translateText(
            $exampleText,
            'de',
            'en-US',
            [TranslateTextOptions::STYLE_ID => $styleRule]
        );

        $this->assertNotNull($result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateDocumentWithStyleId(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        list(, $exampleDocument, , $outputDocumentPath) = $this->tempFiles();
        $this->writeFile($exampleDocument, DeepLTestBase::EXAMPLE_TEXT['de']);

        // Default style rule language is 'en', so the target language must be en-based.
        $status = $client->translateDocument(
            $exampleDocument,
            $outputDocumentPath,
            'de',
            'en-US',
            [TranslateDocumentOptions::STYLE_ID => self::DEFAULT_STYLE_ID]
        );

        $this->assertNotNull($status);
        $this->assertEquals('done', $status->status);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateDocumentWithStyleRuleInfo(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $styleRules = $client->getAllStyleRules();
        $styleRule = $styleRules[0];
        list(, $exampleDocument, , $outputDocumentPath) = $this->tempFiles();
        $this->writeFile($exampleDocument, DeepLTestBase::EXAMPLE_TEXT['de']);

        $status = $client->translateDocument(
            $exampleDocument,
            $outputDocumentPath,
            'de',
            'en-US',
            [TranslateDocumentOptions::STYLE_ID => $styleRule]
        );

        $this->assertNotNull($status);
        $this->assertEquals('done', $status->status);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testStyleRuleValidation(?ClientInterface $httpClient)
    {
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        // createStyleRule
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->createStyleRule('', 'en');
        });
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->createStyleRule('Test', '');
        });

        // getStyleRule
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->getStyleRule('');
        });

        // updateStyleRuleName
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->updateStyleRuleName('', 'New Name');
        });
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->updateStyleRuleName('some-id', '');
        });

        // deleteStyleRule
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->deleteStyleRule('');
        });

        // updateStyleRuleConfiguredRules
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->updateStyleRuleConfiguredRules('', []);
        });

        // createStyleRuleCustomInstruction
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->createStyleRuleCustomInstruction('', 'L', 'P');
        });
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->createStyleRuleCustomInstruction('some-id', '', 'P');
        });
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->createStyleRuleCustomInstruction('some-id', 'L', '');
        });

        // getStyleRuleCustomInstruction
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->getStyleRuleCustomInstruction('', 'instr-id');
        });
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->getStyleRuleCustomInstruction('some-id', '');
        });

        // updateStyleRuleCustomInstruction
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->updateStyleRuleCustomInstruction('', 'instr-id', 'L', 'P');
        });
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->updateStyleRuleCustomInstruction('some-id', '', 'L', 'P');
        });
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->updateStyleRuleCustomInstruction('some-id', 'instr-id', '', 'P');
        });
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->updateStyleRuleCustomInstruction('some-id', 'instr-id', 'L', '');
        });

        // deleteStyleRuleCustomInstruction
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->deleteStyleRuleCustomInstruction('', 'instr-id');
        });
        $this->assertExceptionClass(DeepLException::class, function () use ($client) {
            $client->deleteStyleRuleCustomInstruction('some-id', '');
        });
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testStyleRuleCrud(?ClientInterface $httpClient)
    {
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        // Create a style rule with configuredRules and customInstructions
        $configuredRulesParam = [
            'dates_and_times' => ['calendar_era' => 'use_bc_and_ad'],
        ];
        $customInstructionsParam = [
            ['label' => 'Init Instruction', 'prompt' => 'Be concise'],
        ];
        $createdWithParams = $client->createStyleRule(
            'Test Style Rule With Params',
            'en',
            $configuredRulesParam,
            $customInstructionsParam
        );
        $this->assertNotNull($createdWithParams->styleId);
        $this->assertEquals('Test Style Rule With Params', $createdWithParams->name);
        $client->deleteStyleRule($createdWithParams);

        // Create a style rule
        $created = $client->createStyleRule('Test Style Rule', 'en');
        $this->assertNotNull($created->styleId);
        $this->assertEquals('Test Style Rule', $created->name);
        $this->assertEquals('en', $created->language);

        // Get the style rule
        $fetched = $client->getStyleRule($created->styleId);
        $this->assertEquals($created->styleId, $fetched->styleId);
        $this->assertEquals('Test Style Rule', $fetched->name);

        // Get using StyleRuleInfo object
        $fetchedByObject = $client->getStyleRule($created);
        $this->assertEquals($created->styleId, $fetchedByObject->styleId);

        // Update style rule name
        $updated = $client->updateStyleRuleName($created, 'Updated Style Rule');
        $this->assertEquals($created->styleId, $updated->styleId);
        $this->assertEquals('Updated Style Rule', $updated->name);

        // Update configured rules
        $configuredRules = [
            'dates_and_times' => ['calendar_era' => 'use_bc_and_ad'],
        ];
        $updatedRules = $client->updateStyleRuleConfiguredRules($created, $configuredRules);
        $this->assertEquals($created->styleId, $updatedRules->styleId);

        // Create a custom instruction with sourceLanguage
        $instructionWithLang = $client->createStyleRuleCustomInstruction(
            $created,
            'Test Instruction With Lang',
            'Always use formal language',
            'de'
        );
        $this->assertNotNull($instructionWithLang->id);
        $this->assertEquals('Test Instruction With Lang', $instructionWithLang->label);
        $client->deleteStyleRuleCustomInstruction($created, $instructionWithLang->id);

        // Create a custom instruction
        $instruction = $client->createStyleRuleCustomInstruction(
            $created,
            'Test Instruction',
            'Always use formal language'
        );
        $this->assertNotNull($instruction->id);
        $this->assertEquals('Test Instruction', $instruction->label);
        $this->assertEquals('Always use formal language', $instruction->prompt);

        // Get the custom instruction
        $fetchedInstruction = $client->getStyleRuleCustomInstruction($created, $instruction->id);
        $this->assertEquals($instruction->id, $fetchedInstruction->id);
        $this->assertEquals('Test Instruction', $fetchedInstruction->label);

        // Update the custom instruction with sourceLanguage
        $updatedInstruction = $client->updateStyleRuleCustomInstruction(
            $created,
            $instruction->id,
            'Updated Instruction',
            'Use very formal language',
            'de'
        );
        $this->assertEquals($instruction->id, $updatedInstruction->id);
        $this->assertEquals('Updated Instruction', $updatedInstruction->label);
        $this->assertEquals('Use very formal language', $updatedInstruction->prompt);

        // Delete the custom instruction
        $client->deleteStyleRuleCustomInstruction($created, $instruction->id);

        // Delete the style rule
        $client->deleteStyleRule($created);
    }
}
