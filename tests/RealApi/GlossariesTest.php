<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\GlossaryEntries;
use DeepL\GlossaryNotFoundException;

class GlossariesTest extends RealApiTestCase
{
    public function testSendsTheV2GlossaryRequests(): void
    {
        $translator = $this->makeTranslator();
        $name = self::uniqueName('glossary');
        $entries = GlossaryEntries::fromEntries(['Apple' => 'Apfel', 'Banana' => 'Banane']);

        $glossary = $translator->createGlossary($name, 'en', 'de', $entries);
        try {
            $this->assertSame([$name, 'en', 'de', 2], [
                $glossary->name,
                $glossary->sourceLang,
                $glossary->targetLang,
                $glossary->entryCount,
            ]);

            $fetched = $this->retryWhileMissing(function () use ($translator, $glossary) {
                return $translator->getGlossary($glossary->glossaryId);
            }, GlossaryNotFoundException::class);
            $this->assertSame($glossary->glossaryId, $fetched->glossaryId);
            $this->assertSame($name, $fetched->name);

            $ids = array_map(function ($info) {
                return $info->glossaryId;
            }, $translator->listGlossaries());
            $this->assertContains($glossary->glossaryId, $ids);

            $this->assertSame($entries->getEntries(), $translator->getGlossaryEntries($glossary)->getEntries());
        } finally {
            try {
                $translator->deleteGlossary($glossary);
            } catch (\Exception $exception) {
                // Best effort: the original failure is more useful than a failed cleanup.
            }
        }
        $this->expectException(GlossaryNotFoundException::class);
        $translator->getGlossary($glossary->glossaryId);
    }
}
