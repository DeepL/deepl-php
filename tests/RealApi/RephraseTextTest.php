<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\Fixtures;
use DeepL\RephraseTextResult;

class RephraseTextTest extends RealApiTestCase
{
    public function testSendsRephraseRequestAndParsesTheResult(): void
    {
        $result = $this->makeDeepLClient()->rephraseText(Fixtures::EXAMPLE_TEXT, 'en-GB');

        $this->assertInstanceOf(RephraseTextResult::class, $result);
        $this->assertIsString($result->text);
    }
}
