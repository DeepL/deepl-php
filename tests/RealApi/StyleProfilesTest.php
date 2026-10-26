<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\NotFoundException;
use DeepL\StyleProfileOptions;

class StyleProfilesTest extends RealApiTestCase
{
    public function testSendsTheStyleProfileRequests(): void
    {
        $client = $this->makeDeepLClient();
        $name = self::uniqueName('styleProfile');
        $styleRule = $client->createStyleRule(self::uniqueName('styleProfileRule'), 'de');
        $profile = null;
        try {
            $profile = $client->createStyleProfile($name, [
                StyleProfileOptions::DESCRIPTION => 'Created by an SDK test',
                StyleProfileOptions::STYLE_RULE_IDS => [$styleRule->styleId],
            ]);
            $this->assertSame($name, $profile->name);
            $this->assertSame('Created by an SDK test', $profile->description);
            $this->assertSame([$styleRule->styleId], $profile->styleRuleIds);
            $this->assertSame([[], []], [$profile->glossaryIds, $profile->translationMemoryIds]);

            $fetched = $this->retryWhileMissing(function () use ($client, $profile) {
                return $client->getStyleProfile($profile->styleProfileId);
            }, NotFoundException::class);
            $this->assertSame(
                [$profile->styleProfileId, $name, 'Created by an SDK test', [$styleRule->styleId]],
                [$fetched->styleProfileId, $fetched->name, $fetched->description, $fetched->styleRuleIds]
            );

            $listed = false;
            for ($page = 0; !$listed && $page < 20; $page++) {
                $profiles = $client->listStyleProfiles($page, 100);
                if (count($profiles) === 0) {
                    break;
                }
                foreach ($profiles as $item) {
                    $listed = $listed || $item->styleProfileId === $profile->styleProfileId;
                }
            }
            $this->assertTrue($listed, 'the new style profile was not in the list');

            $updated = $client->updateStyleProfile($profile, [
                StyleProfileOptions::NAME => "$name-renamed",
                StyleProfileOptions::INDUSTRY => 'Legal',
                StyleProfileOptions::DESCRIPTION => '',
                StyleProfileOptions::STYLE_RULE_IDS => [],
            ]);
            $this->assertSame("$name-renamed", $updated->name);
            $this->assertSame('Legal', $updated->industry);
            $this->assertNull($updated->description);
            $this->assertSame([], $updated->styleRuleIds);

            $client->deleteStyleProfile($profile);
            try {
                $client->getStyleProfile($profile);
                $this->fail('expected a NotFoundException after delete');
            } catch (NotFoundException $exception) {
                $profile = null;
            }
        } finally {
            // Best effort, not-found included: the original failure is more useful than a failed cleanup.
            if ($profile !== null) {
                try {
                    $client->deleteStyleProfile($profile);
                } catch (\Exception $exception) {
                }
            }
            try {
                $client->deleteStyleRule($styleRule);
            } catch (\Exception $exception) {
            }
        }
    }
}
