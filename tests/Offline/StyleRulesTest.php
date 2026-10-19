<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\CustomInstruction;
use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\NotFoundException;
use DeepL\StyleRuleInfo;

class StyleRulesTest extends OfflineTestCase
{
    private const STYLE_RULE = [
        'style_id' => 'sid',
        'name' => 'My rules',
        'creation_time' => '2024-05-06T07:08:09Z',
        'updated_time' => '2024-06-07T08:09:10Z',
        'language' => 'en',
        'version' => 3,
        'configured_rules' => ['dates_and_times' => ['calendar_era' => 'use_bc_and_ad']],
        'custom_instructions' => [['id' => 'i1', 'label' => 'L', 'prompt' => 'P', 'source_language' => 'de']],
    ];
    private const INSTRUCTION = ['id' => 'i1', 'label' => 'L', 'prompt' => 'P', 'source_language' => 'de'];

    /**
     * @dataProvider provideListQueries
     */
    public function testSendsListQuery(array $arguments, string $query): void
    {
        $this->reply(['style_rules' => []]);

        $this->makeDeepLClient()->getAllStyleRules(...$arguments);

        $this->assertRequest('GET', '/v3/style_rules', 0, $query);
    }

    public static function provideListQueries(): array
    {
        return [
            'nothing' => [[], ''],
            'page and size' => [[2, 10], 'page=2&page_size=10'],
            'detailed' => [[null, null, true], 'detailed=true'],
            'not detailed' => [[1, 5, false], 'page=1&page_size=5&detailed=false'],
        ];
    }

    public function testParsesStyleRulesWithConfiguredRulesAndInstructions(): void
    {
        $this->reply(['style_rules' => [self::STYLE_RULE, ['style_id' => 'plain'] + self::STYLE_RULE]]);

        $rules = $this->makeDeepLClient()->getAllStyleRules(null, null, true);

        $this->assertCount(2, $rules);
        $this->assertSame('sid', $rules[0]->styleId);
        $this->assertSame('My rules', $rules[0]->name);
        $this->assertSame('en', $rules[0]->language);
        $this->assertSame(3, $rules[0]->version);
        $this->assertSame('2024-05-06 07:08:09', $rules[0]->creationTime->format('Y-m-d H:i:s'));
        $this->assertSame('2024-06-07 08:09:10', $rules[0]->updatedTime->format('Y-m-d H:i:s'));
        $this->assertSame(['calendar_era' => 'use_bc_and_ad'], $rules[0]->configuredRules->datesAndTimes);
        $this->assertNull($rules[0]->configuredRules->numbers);
        $this->assertSame(['i1', 'L', 'P', 'de'], [
            $rules[0]->customInstructions[0]->id,
            $rules[0]->customInstructions[0]->label,
            $rules[0]->customInstructions[0]->prompt,
            $rules[0]->customInstructions[0]->sourceLanguage,
        ]);
    }

    public function testParsesStyleRulesWithoutDetails(): void
    {
        $this->reply(['style_rules' => [array_diff_key(self::STYLE_RULE, [
            'configured_rules' => 1,
            'custom_instructions' => 1,
        ])]]);

        $rules = $this->makeDeepLClient()->getAllStyleRules();

        $this->assertNull($rules[0]->configuredRules);
        $this->assertNull($rules[0]->customInstructions);
    }

    public function testSendsNameAndLanguageWhenCreating(): void
    {
        $this->reply(self::STYLE_RULE, 201);

        $this->makeDeepLClient()->createStyleRule('My rules', 'en');

        $this->assertRequest('POST', '/v3/style_rules');
        $this->assertSame(['name' => 'My rules', 'language' => 'en'], $this->jsonBody());
    }

    public function testSendsConfiguredRulesAndInstructionsWhenCreating(): void
    {
        $this->reply(self::STYLE_RULE, 201);
        $configured = ['dates_and_times' => ['calendar_era' => 'use_bc_and_ad']];
        $instructions = [['label' => 'L', 'prompt' => 'P']];

        $this->makeDeepLClient()->createStyleRule('My rules', 'en', $configured, $instructions);

        $this->assertSame([
            'name' => 'My rules',
            'language' => 'en',
            'configured_rules' => $configured,
            'custom_instructions' => $instructions,
        ], $this->jsonBody());
    }

    public function testSendsEmptyConfiguredRulesAsJsonObject(): void
    {
        $this->reply(self::STYLE_RULE, 201);
        $this->reply(self::STYLE_RULE);
        $client = $this->makeDeepLClient();

        $client->createStyleRule('My rules', 'en', []);
        $client->updateStyleRuleConfiguredRules('sid', []);

        $this->assertStringContainsString('"configured_rules":{}', (string)$this->request(0)->getBody());
        $this->assertSame('{}', (string)$this->request(1)->getBody());
    }

    public function testSendsTheIdOfStyleRuleInfoObjectsAndEncodesIds(): void
    {
        $this->reply(self::STYLE_RULE);
        $this->reply(self::STYLE_RULE);
        $this->reply('', 204);
        $client = $this->makeDeepLClient();
        $info = new StyleRuleInfo('a/b c', 'n', new \DateTime(), new \DateTime(), 'en', 1);

        $client->getStyleRule($info);
        $client->updateStyleRuleName('a/b c', 'New');
        $client->deleteStyleRule($info);

        $this->assertRequest('GET', '/v3/style_rules/a%2Fb%20c', 0);
        $this->assertRequest('PATCH', '/v3/style_rules/a%2Fb%20c', 1);
        $this->assertSame(['name' => 'New'], $this->jsonBody(1));
        $this->assertRequest('DELETE', '/v3/style_rules/a%2Fb%20c', 2);
    }

    public function testSendsConfiguredRulesWithPut(): void
    {
        $this->reply(self::STYLE_RULE);
        $rules = ['numbers' => ['number_format' => 'use_comma']];

        $this->makeDeepLClient()->updateStyleRuleConfiguredRules('sid', $rules);

        $this->assertRequest('PUT', '/v3/style_rules/sid/configured_rules');
        $this->assertSame($rules, $this->jsonBody());
    }

    public function testSendsCustomInstructionRequests(): void
    {
        $this->reply(self::INSTRUCTION, 201);
        $this->reply(self::INSTRUCTION, 201);
        $this->reply(self::INSTRUCTION);
        $this->reply(self::INSTRUCTION);
        $this->reply(self::INSTRUCTION);
        $this->reply('', 204);
        $client = $this->makeDeepLClient();

        $client->createStyleRuleCustomInstruction('sid', 'L', 'P');
        $client->createStyleRuleCustomInstruction('sid', 'L', 'P', 'de');
        $client->getStyleRuleCustomInstruction('sid', 'i/1');
        $client->updateStyleRuleCustomInstruction('sid', 'i1', 'L2', 'P2');
        $client->updateStyleRuleCustomInstruction('sid', 'i1', 'L2', 'P2', 'fr');
        $client->deleteStyleRuleCustomInstruction('sid', 'i1');

        $this->assertRequest('POST', '/v3/style_rules/sid/custom_instructions', 0);
        $this->assertSame(['label' => 'L', 'prompt' => 'P'], $this->jsonBody(0));
        $this->assertRequest('POST', '/v3/style_rules/sid/custom_instructions', 1);
        $this->assertSame(['label' => 'L', 'prompt' => 'P', 'source_language' => 'de'], $this->jsonBody(1));
        $this->assertRequest('GET', '/v3/style_rules/sid/custom_instructions/i%2F1', 2);
        $this->assertRequest('PUT', '/v3/style_rules/sid/custom_instructions/i1', 3);
        $this->assertSame(['label' => 'L2', 'prompt' => 'P2'], $this->jsonBody(3));
        $this->assertRequest('PUT', '/v3/style_rules/sid/custom_instructions/i1', 4);
        $this->assertSame(['label' => 'L2', 'prompt' => 'P2', 'source_language' => 'fr'], $this->jsonBody(4));
        $this->assertRequest('DELETE', '/v3/style_rules/sid/custom_instructions/i1', 5);
    }

    public function testParsesCustomInstruction(): void
    {
        $this->reply(['id' => 'i1', 'label' => 'L', 'prompt' => 'P']);

        $instruction = $this->makeDeepLClient()->getStyleRuleCustomInstruction('sid', 'i1');

        $this->assertInstanceOf(CustomInstruction::class, $instruction);
        $this->assertSame(['i1', 'L', 'P'], [$instruction->id, $instruction->label, $instruction->prompt]);
        $this->assertNull($instruction->sourceLanguage);
    }

    public function testThrowsNotFoundWhenTheStyleRuleDoesNotExist(): void
    {
        $this->reply(['message' => 'unknown style'], 404);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Not found, message: unknown style');

        $this->makeDeepLClient()->getStyleRule('gone');
    }

    /**
     * @dataProvider provideEmptyArguments
     */
    public function testThrowsWhenAnArgumentIsEmpty(callable $call, string $message): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage($message);

        $call($this->makeDeepLClient());
    }

    public static function provideEmptyArguments(): array
    {
        return [
            'create without name' => [function (DeepLClient $c) {
                $c->createStyleRule('', 'en');
            }, 'name must be a non-empty string'],
            'create without language' => [function (DeepLClient $c) {
                $c->createStyleRule('n', '');
            }, 'language must be a non-empty string'],
            'get without id' => [function (DeepLClient $c) {
                $c->getStyleRule('');
            }, 'styleId must be a non-empty string'],
            'rename without id' => [function (DeepLClient $c) {
                $c->updateStyleRuleName('', 'n');
            }, 'styleId must be a non-empty string'],
            'rename without name' => [function (DeepLClient $c) {
                $c->updateStyleRuleName('sid', '');
            }, 'name must be a non-empty string'],
            'delete without id' => [function (DeepLClient $c) {
                $c->deleteStyleRule('');
            }, 'styleId must be a non-empty string'],
            'configured rules without id' => [function (DeepLClient $c) {
                $c->updateStyleRuleConfiguredRules('', []);
            }, 'styleId must be a non-empty string'],
            'create instruction without id' => [function (DeepLClient $c) {
                $c->createStyleRuleCustomInstruction('', 'L', 'P');
            }, 'styleId must be a non-empty string'],
            'create instruction without label' => [function (DeepLClient $c) {
                $c->createStyleRuleCustomInstruction('sid', '', 'P');
            }, 'label must be a non-empty string'],
            'create instruction without prompt' => [function (DeepLClient $c) {
                $c->createStyleRuleCustomInstruction('sid', 'L', '');
            }, 'prompt must be a non-empty string'],
            'get instruction without style id' => [function (DeepLClient $c) {
                $c->getStyleRuleCustomInstruction('', 'i1');
            }, 'styleId must be a non-empty string'],
            'get instruction without id' => [function (DeepLClient $c) {
                $c->getStyleRuleCustomInstruction('sid', '');
            }, 'instructionId must be a non-empty string'],
            'update instruction without style id' => [function (DeepLClient $c) {
                $c->updateStyleRuleCustomInstruction('', 'i1', 'L', 'P');
            }, 'styleId must be a non-empty string'],
            'update instruction without id' => [function (DeepLClient $c) {
                $c->updateStyleRuleCustomInstruction('sid', '', 'L', 'P');
            }, 'instructionId must be a non-empty string'],
            'update instruction without label' => [function (DeepLClient $c) {
                $c->updateStyleRuleCustomInstruction('sid', 'i1', '', 'P');
            }, 'label must be a non-empty string'],
            'update instruction without prompt' => [function (DeepLClient $c) {
                $c->updateStyleRuleCustomInstruction('sid', 'i1', 'L', '');
            }, 'prompt must be a non-empty string'],
            'delete instruction without style id' => [function (DeepLClient $c) {
                $c->deleteStyleRuleCustomInstruction('', 'i1');
            }, 'styleId must be a non-empty string'],
            'delete instruction without id' => [function (DeepLClient $c) {
                $c->deleteStyleRuleCustomInstruction('sid', '');
            }, 'instructionId must be a non-empty string'],
        ];
    }
}
