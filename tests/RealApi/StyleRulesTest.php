<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

class StyleRulesTest extends RealApiTestCase
{
    public function testSendsTheStyleRuleRequests(): void
    {
        $client = $this->makeDeepLClient();
        $name = self::uniqueName('styleRule');
        $rules = ['dates_and_times' => ['calendar_era' => 'use_bc_and_ad']];

        $styleRule = $client->createStyleRule($name, 'en', $rules, [
            ['label' => 'Init', 'prompt' => 'Be formal', 'source_language' => 'de'],
        ]);
        try {
            $this->assertSame([$name, 'en'], [$styleRule->name, $styleRule->language]);
            $this->assertInstanceOf(\DateTime::class, $styleRule->creationTime);

            $fetched = $client->getStyleRule($styleRule);
            $this->assertSame([$styleRule->styleId, $name], [$fetched->styleId, $fetched->name]);

            $listed = false;
            for ($page = 0; !$listed && $page < 20; $page++) {
                $styleRules = $client->getAllStyleRules($page, 25, true);
                if (count($styleRules) === 0) {
                    break;
                }
                foreach ($styleRules as $rule) {
                    $listed = $listed || $rule->styleId === $styleRule->styleId;
                }
            }
            $this->assertTrue($listed, 'the new style rule was not in the list');

            $renamed = $client->updateStyleRuleName($styleRule, "$name-renamed");
            $this->assertSame("$name-renamed", $renamed->name);

            $configured = $client->updateStyleRuleConfiguredRules($styleRule, $rules);
            $this->assertSame($styleRule->styleId, $configured->styleId);

            $instruction = $client->createStyleRuleCustomInstruction($styleRule, 'Label', 'Prompt', 'de');
            $this->assertNotEmpty($instruction->id);
            $this->assertSame('Label', $instruction->label);

            $fetchedInstruction = $client->getStyleRuleCustomInstruction($styleRule, $instruction->id);
            $this->assertSame('Prompt', $fetchedInstruction->prompt);

            $updatedInstruction = $client->updateStyleRuleCustomInstruction(
                $styleRule,
                $instruction->id,
                'New Label',
                'New Prompt',
                'en'
            );
            $this->assertSame('New Label', $updatedInstruction->label);

            $client->deleteStyleRuleCustomInstruction($styleRule, $instruction->id);
        } finally {
            try {
                $client->deleteStyleRule($styleRule);
            } catch (\Exception $exception) {
                // Best effort: the original failure is more useful than a failed cleanup.
            }
        }
    }
}
