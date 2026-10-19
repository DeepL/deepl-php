<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\LanguageResource;

class LanguagesTest extends RealApiTestCase
{
    public function testSendsLanguagesRequestAndParsesSourceLanguages(): void
    {
        $languages = $this->makeTranslator()->getSourceLanguages();

        $codes = array_map(function ($language) {
            return $language->code;
        }, $languages);
        $this->assertContains('EN', $codes);
        foreach ($languages as $language) {
            $this->assertNotSame('', $language->name);
            $this->assertNull($language->supportsFormality);
        }
    }

    public function testSendsLanguageResourcesRequestAndParsesTheResources(): void
    {
        $resources = $this->makeDeepLClient()->getLanguageResources();

        $this->assertNotEmpty($resources);
        $byName = [];
        foreach ($resources as $resource) {
            $byName[$resource->resource] = $resource;
            foreach ($resource->features as $name => $feature) {
                $this->assertSame($name, $feature->name);
            }
        }
        $this->assertArrayHasKey(LanguageResource::RESOURCE_TRANSLATE_TEXT, $byName);
        $glossary = $byName[LanguageResource::RESOURCE_TRANSLATE_TEXT]->features['glossary'];
        $this->assertTrue($glossary->needsSourceSupport);
        $this->assertTrue($glossary->needsTargetSupport);
    }
}
