<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\NotFoundException;
use DeepL\SpokenTerms;
use DeepL\SpokenTermsEntries;
use DeepL\SpokenTermsOptions;

class SpokenTermsTest extends RealApiTestCase
{
    public function testSendsTheSpokenTermsRequests(): void
    {
        $client = $this->makeDeepLClient();
        $name = self::uniqueName('spokenTerms');

        $spokenTerms = $client->createSpokenTerms($name, [new SpokenTermsEntries('en', ['DeepL', 'webhook'])]);
        $deleted = false;
        try {
            $this->assertSame($name, $spokenTerms->name);
            $this->assertSame([['en', 2]], self::termLists($spokenTerms));

            $fetched = $this->retryWhileMissing(function () use ($client, $spokenTerms) {
                return $client->getSpokenTerms($spokenTerms->spokenTermsId);
            }, NotFoundException::class);
            $this->assertSame($spokenTerms->spokenTermsId, $fetched->spokenTermsId);
            $this->assertSame($name, $fetched->name);

            $ids = array_map(function (SpokenTerms $item) {
                return $item->spokenTermsId;
            }, $client->listSpokenTerms());
            $this->assertContains($spokenTerms->spokenTermsId, $ids);

            $merge = [new SpokenTermsEntries('en', ['authentication'])];
            $updated = $client->updateSpokenTerms($spokenTerms, [SpokenTermsOptions::TERM_LISTS => $merge]);
            $this->assertSame([['en', 3]], self::termLists($updated));

            $entries = $client->getSpokenTermsEntries($spokenTerms, 'en');
            $this->assertSame('en', $entries->lang);
            $terms = $entries->entries;
            sort($terms);
            $this->assertSame(['DeepL', 'authentication', 'webhook'], $terms);

            $replaced = $client->replaceSpokenTermsList($spokenTerms, new SpokenTermsEntries('en', ['API']));
            $this->assertSame(['en', 1], [$replaced->lang, $replaced->entryCount]);
            $this->assertSame(['API'], $client->getSpokenTermsEntries($spokenTerms, 'en')->entries);

            $added = $client->replaceSpokenTermsList($spokenTerms, new SpokenTermsEntries('de', ['Schnittstelle']));
            $this->assertSame(['de', 1], [$added->lang, $added->entryCount]);
            $client->deleteSpokenTermsList($spokenTerms, 'de');
            $this->assertSame([['en', 1]], self::termLists($client->getSpokenTerms($spokenTerms)));

            $client->deleteSpokenTerms($spokenTerms);
            $deleted = true;
            $this->expectException(NotFoundException::class);
            $client->getSpokenTerms($spokenTerms);
        } finally {
            if (!$deleted) {
                try {
                    $client->deleteSpokenTerms($spokenTerms);
                } catch (\Exception $exception) {
                    // Best effort: the original failure is more useful than a failed cleanup.
                }
            }
        }
    }

    private static function termLists(SpokenTerms $spokenTerms): array
    {
        $lists = array_map(function ($list) {
            return [$list->lang, $list->entryCount];
        }, $spokenTerms->termLists);
        sort($lists);
        return $lists;
    }
}
