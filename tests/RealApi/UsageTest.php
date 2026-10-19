<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

class UsageTest extends RealApiTestCase
{
    public function testSendsUsageRequestAndParsesTheUsage(): void
    {
        $usage = $this->makeTranslator()->getUsage();

        $this->assertStringContainsString('Usage this billing period', (string)$usage);
        $this->assertIsBool($usage->anyLimitReached());
    }
}
