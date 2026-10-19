<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\Fixtures;

class TranslateDocumentTest extends RealApiTestCase
{
    public function testUploadsPollsAndDownloadsATxtDocument(): void
    {
        $input = Fixtures::tempFile('example_document.txt', Fixtures::EXAMPLE_TEXT);
        $output = dirname($input) . '/output_document.txt';

        $status = $this->makeTranslator()->translateDocument($input, $output, null, 'de');

        $this->assertTrue($status->ok() && $status->done());
        $this->assertGreaterThan(0, filesize($output));
    }
}
