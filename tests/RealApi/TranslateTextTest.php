<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\Fixtures;
use DeepL\TextResult;

class TranslateTextTest extends RealApiTestCase
{
    public function testSendsTranslateRequestAndParsesTheResult(): void
    {
        $result = $this->makeTranslator()->translateText(Fixtures::EXAMPLE_TEXT, null, 'de');

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertIsString($result->text);
        $this->assertNotEmpty($result->detectedSourceLang);
        $this->assertGreaterThan(0, $result->billedCharacters);
    }
}
