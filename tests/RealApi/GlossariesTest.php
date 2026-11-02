<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\GlossaryEntries;
use DeepL\NotFoundException;

class GlossariesTest extends RealApiTestCase
{
    public function testSendsTheV2GlossaryRequests(): void
    {
        $deeplClient = $this->makeDeepLClient();
        $name = self::uniqueName('glossary');
        $entries = GlossaryEntries::fromEntries(['Apple' => 'Apfel', 'Banana' => 'Banane']);

        $glossary = $deeplClient->createGlossary($name, 'en', 'de', $entries);
        try {
            $this->assertSame([$name, 'en', 'de', 2], [
                $glossary->name,
                $glossary->sourceLang,
                $glossary->targetLang,
                $glossary->entryCount,
            ]);

            $fetched = $this->retryWhileMissing(function () use ($deeplClient, $glossary) {
                return $deeplClient->getGlossary($glossary->glossaryId);
            }, NotFoundException::class);
            $this->assertSame($glossary->glossaryId, $fetched->glossaryId);
            $this->assertSame($name, $fetched->name);

            $ids = array_map(function ($info) {
                return $info->glossaryId;
            }, $deeplClient->listGlossaries());
            $this->assertContains($glossary->glossaryId, $ids);

            $this->assertSame($entries->getEntries(), $deeplClient->getGlossaryEntries($glossary)->getEntries());
        } finally {
            try {
                $deeplClient->deleteGlossary($glossary);
            } catch (\Exception $exception) {
                // Best effort: the original failure is more useful than a failed cleanup.
            }
        }
        $this->expectException(NotFoundException::class);
        $deeplClient->getGlossary($glossary->glossaryId);
    }
}
