<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryDictionaryEntries;

class MultilingualGlossariesTest extends RealApiTestCase
{
    public function testSendsTheV3GlossaryRequests(): void
    {
        $client = $this->makeDeepLClient();
        $name = self::uniqueName('glossary-v3');
        $enDe = new MultilingualGlossaryDictionaryEntries('en', 'de', ['Apple' => 'Apfel']);
        $enFr = new MultilingualGlossaryDictionaryEntries('en', 'fr', ['Apple' => 'Pomme']);

        $glossary = $client->createMultilingualGlossary($name, [$enDe, $enFr]);
        try {
            $this->assertSame($name, $glossary->name);
            $pairs = array_map(function ($dictionary) {
                return "$dictionary->sourceLang-$dictionary->targetLang";
            }, $glossary->dictionaries);
            sort($pairs);
            $this->assertSame(['en-de', 'en-fr'], $pairs);

            $fetched = $this->retryWhileMissing(function () use ($client, $glossary) {
                return $client->getMultilingualGlossary($glossary->glossaryId);
            }, GlossaryNotFoundException::class);
            $this->assertSame($glossary->glossaryId, $fetched->glossaryId);
            $this->assertSame($name, $fetched->name);

            $ids = array_map(function ($info) {
                return $info->glossaryId;
            }, $client->listMultilingualGlossaries());
            $this->assertContains($glossary->glossaryId, $ids);

            $dictionaries = $client->getMultilingualGlossaryEntries($glossary, 'en', 'de');
            $this->assertSame(['Apple' => 'Apfel'], $dictionaries[0]->entries);

            $renamed = $client->updateMultilingualGlossary($glossary, "$name-renamed", null);
            $this->assertSame("$name-renamed", $renamed->name);

            $replaced = $client->replaceMultilingualGlossaryDictionary(
                $glossary,
                new MultilingualGlossaryDictionaryEntries('en', 'de', ['Cherry' => 'Kirsche'])
            );
            $this->assertSame(['en', 'de', 1], [$replaced->sourceLang, $replaced->targetLang, $replaced->entryCount]);

            $client->deleteMultilingualGlossaryDictionary($glossary, null, 'en', 'fr');
        } finally {
            try {
                $client->deleteMultilingualGlossary($glossary);
            } catch (\Exception $exception) {
                // Best effort: the original failure is more useful than a failed cleanup.
            }
        }
    }
}
