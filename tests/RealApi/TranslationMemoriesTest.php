<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\Fixtures;

class TranslationMemoriesTest extends RealApiTestCase
{
    private const EXAMPLE_TMX = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<tmx version="1.4"><header creationtool="deepl-php-tests" creationtoolversion="1"' .
        ' segtype="sentence" o-tmf="plain" adminlang="en" srclang="en" datatype="plaintext"/>' .
        '<body><tu><tuv xml:lang="en"><seg>Segment number 1</seg></tuv>' .
        '<tuv xml:lang="de"><seg>Segment Nummer 1</seg></tuv></tu></body></tmx>' . "\n";

    // The import appears as a translation memory only after about a minute.
    private const JOB_TIMEOUT = 120.0;

    public function testSendsTheTranslationMemoryRequests(): void
    {
        $client = $this->makeDeepLClient();
        $name = self::uniqueName('translationMemory');
        $translationMemoryId = null;
        $created = null;
        try {
            $created = $client->createTranslationMemoryImport('example.tmx', strlen(self::EXAMPLE_TMX), null, $name);
            $this->assertNotEmpty($created->jobId);
            $this->assertNotEmpty($created->uploadUrl);

            $client->uploadTranslationMemoryFile($created, self::EXAMPLE_TMX);

            $importJob = $client->waitUntilTranslationMemoryJobDone($created->jobId, self::JOB_TIMEOUT);
            $translationMemoryId = $importJob->result()->translationMemoryId;
            $this->assertSame('import', $importJob->operation);
            $this->assertSame('completed', $importJob->status());
            $this->assertNotEmpty($translationMemoryId);

            $fetched = $client->getTranslationMemory($translationMemoryId);
            $this->assertSame([$translationMemoryId, $name], [$fetched->translationMemoryId, $fetched->name]);
            $this->assertNotEmpty($fetched->sourceLanguage);

            $listed = false;
            for ($page = 0; !$listed && $page < 20; $page++) {
                $memories = $client->listTranslationMemories($page, 100);
                if (count($memories) === 0) {
                    break;
                }
                foreach ($memories as $memory) {
                    $listed = $listed || $memory->translationMemoryId === $translationMemoryId;
                }
            }
            $this->assertTrue($listed, 'the new translation memory was not in the list');

            $segments = $client->listTranslationMemorySegments($translationMemoryId);
            $this->assertNotEmpty($segments->segments);
            $this->assertNotEmpty($segments->segments[0]->sourceSegmentId);

            $export = $client->createTranslationMemoryExport($translationMemoryId);
            $this->assertNotEmpty($export->jobId);
            $exportJob = $client->waitUntilTranslationMemoryJobDone($export->jobId, self::JOB_TIMEOUT);
            $output = Fixtures::tempDir() . '/export.tmx';
            $client->downloadTranslationMemoryExport($exportJob, $output);
            $this->assertStringContainsString('<tmx', file_get_contents($output));
        } finally {
            if ($translationMemoryId === null && $created !== null) {
                // The import may still finish after a failed or timed-out wait: wait once more to get its ID.
                try {
                    $job = $client->waitUntilTranslationMemoryJobDone($created->jobId, self::JOB_TIMEOUT);
                    $translationMemoryId = $job->result()->translationMemoryId;
                } catch (\Exception $exception) {
                    // Best effort: the original failure is more useful than a failed cleanup.
                }
            }
            if ($translationMemoryId !== null) {
                try {
                    $client->deleteTranslationMemory($translationMemoryId);
                } catch (\Exception $exception) {
                    // Best effort: the original failure is more useful than a failed cleanup.
                }
            }
        }
    }
}
