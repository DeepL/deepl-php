<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\Fixtures;
use DeepL\RephraseTextResult;

class CorrectTextTest extends RealApiTestCase
{
    public function testSendsCorrectRequestAndParsesTheResult(): void
    {
        $result = $this->makeDeepLClient()->correctText(Fixtures::EXAMPLE_TEXT);

        $this->assertInstanceOf(RephraseTextResult::class, $result);
        $this->assertIsString($result->text);
        $this->assertSame('en', strtolower($result->detectedSourceLanguage));
    }
}
