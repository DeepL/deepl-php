<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use Psr\Http\Client\ClientInterface;

class TranslationMemoryTest extends DeepLTestBase
{
    private const DEFAULT_TM_ID = 'a74d88fb-ed2a-4943-a664-a4512398b994';
    private const UNKNOWN_ID = '00000000-0000-0000-0000-000000000000';

    private const EXAMPLE_TMX = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<tmx version="1.4"><body>' .
        '<tu><tuv xml:lang="de"><seg>Hallo</seg></tuv>' .
        '<tuv xml:lang="en"><seg>Hello</seg></tuv></tu>' .
        '</body></tmx>' . "\n";

    /**
     * Writes an example TMX file into a fresh temporary directory and returns its path.
     */
    private function tempTmxFile(): string
    {
        list($tempDir) = $this->tempFiles();
        $tmxFile = $tempDir . 'example.tmx';
        $this->writeFile($tmxFile, self::EXAMPLE_TMX);
        return $tmxFile;
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testListTranslationMemories(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $translationMemories = $client->listTranslationMemories(0, 10);
        $this->assertIsArray($translationMemories);
        $this->assertGreaterThan(0, count($translationMemories));
        $this->assertNotEmpty($translationMemories[0]->translationMemoryId);
        $this->assertNotEmpty($translationMemories[0]->name);
        $this->assertNotEmpty($translationMemories[0]->sourceLanguage);
        $this->assertIsArray($translationMemories[0]->targetLanguages);
        $this->assertIsInt($translationMemories[0]->segmentCount);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateTextWithTranslationMemoryId(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        // Note: this test may use the mock server that will not translate the text,
        // therefore we do not check the translated result.
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $exampleText = DeepLTestBase::EXAMPLE_TEXT['de'];

        $result = $client->translateText(
            $exampleText,
            'de',
            'en-US',
            [TranslateTextOptions::TRANSLATION_MEMORY_ID => self::DEFAULT_TM_ID]
        );

        $this->assertNotNull($result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateTextWithTranslationMemoryIdAndThreshold(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        // Note: this test may use the mock server that will not translate the text,
        // therefore we do not check the translated result.
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $exampleText = DeepLTestBase::EXAMPLE_TEXT['de'];

        $result = $client->translateText(
            $exampleText,
            'de',
            'en-US',
            [
                TranslateTextOptions::TRANSLATION_MEMORY_ID => self::DEFAULT_TM_ID,
                TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD => 80,
            ]
        );

        $this->assertNotNull($result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateDocumentWithTranslationMemoryId(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        list(, $exampleDocument, , $outputDocumentPath) = $this->tempFiles();
        $this->writeFile($exampleDocument, DeepLTestBase::EXAMPLE_TEXT['de']);

        // Default translation memory supports target languages en, es and fr.
        $status = $client->translateDocument(
            $exampleDocument,
            $outputDocumentPath,
            'de',
            'en-US',
            [TranslateDocumentOptions::TRANSLATION_MEMORY_ID => self::DEFAULT_TM_ID]
        );

        $this->assertNotNull($status);
        $this->assertEquals('done', $status->status);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testTranslateDocumentWithTranslationMemoryIdAndThreshold(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        list(, $exampleDocument, , $outputDocumentPath) = $this->tempFiles();
        $this->writeFile($exampleDocument, DeepLTestBase::EXAMPLE_TEXT['de']);

        $status = $client->translateDocument(
            $exampleDocument,
            $outputDocumentPath,
            'de',
            'en-US',
            [
                TranslateDocumentOptions::TRANSLATION_MEMORY_ID => self::DEFAULT_TM_ID,
                TranslateDocumentOptions::TRANSLATION_MEMORY_THRESHOLD => 80,
            ]
        );

        $this->assertNotNull($status);
        $this->assertEquals('done', $status->status);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testGetTranslationMemory(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $translationMemory = $client->getTranslationMemory(self::DEFAULT_TM_ID);

        $this->assertEquals(self::DEFAULT_TM_ID, $translationMemory->translationMemoryId);
        $this->assertNotEmpty($translationMemory->name);
        $this->assertNotEmpty($translationMemory->sourceLanguage);
        $this->assertIsArray($translationMemory->targetLanguages);
        $this->assertIsInt($translationMemory->segmentCount);
        $this->assertNotNull($translationMemory->creationTime);
        $this->assertNotNull($translationMemory->updatedTime);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testGetTranslationMemoryWithTranslationMemoryInfo(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $listed = $client->listTranslationMemories()[0];

        $translationMemory = $client->getTranslationMemory($listed);

        $this->assertEquals($listed->translationMemoryId, $translationMemory->translationMemoryId);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testGetTranslationMemoryWithUnknownId(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $this->assertExceptionClass(NotFoundException::class, function () use ($client) {
            $client->getTranslationMemory(self::UNKNOWN_ID);
        });
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testListTranslationMemorySegments(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $page = $client->listTranslationMemorySegments(self::DEFAULT_TM_ID);

        $this->assertGreaterThan(0, count($page->segments));
        $this->assertGreaterThan(0, $page->segmentCount);
        $segment = $page->segments[0];
        $this->assertNotEmpty($segment->sourceSegmentId);
        $this->assertNotEmpty($segment->sourceText);
        $this->assertGreaterThan(0, count($segment->targets));
        $this->assertNotEmpty($segment->targets[0]->targetLanguage);
        $this->assertNotEmpty($segment->targets[0]->targetText);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testListTranslationMemorySegmentsPagination(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $first = $client->listTranslationMemorySegments(
            self::DEFAULT_TM_ID,
            [TranslationMemorySegmentsOptions::PAGE_SIZE => 5]
        );
        $this->assertCount(5, $first->segments);
        $this->assertNotNull($first->nextPageCursor);

        $second = $client->listTranslationMemorySegments(
            self::DEFAULT_TM_ID,
            [
                TranslationMemorySegmentsOptions::PAGE_SIZE => 5,
                TranslationMemorySegmentsOptions::PAGE_CURSOR => $first->nextPageCursor,
            ]
        );
        $this->assertGreaterThan(0, count($second->segments));

        $segmentIds = function (array $segments): array {
            return array_map(function (TranslationMemorySegment $segment): string {
                return $segment->sourceSegmentId;
            }, $segments);
        };
        $this->assertEmpty(array_intersect($segmentIds($first->segments), $segmentIds($second->segments)));
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testListTranslationMemorySegmentsFilter(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $unfiltered = $client->listTranslationMemorySegments(self::DEFAULT_TM_ID);
        $filtered = $client->listTranslationMemorySegments(
            self::DEFAULT_TM_ID,
            [TranslationMemorySegmentsOptions::FILTER_TEXT => 'Nummer 7']
        );

        $this->assertLessThan(count($unfiltered->segments), count($filtered->segments));
        // segmentCount is translation-memory-level metadata and is unaffected by the filter
        $this->assertEquals($unfiltered->segmentCount, $filtered->segmentCount);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testImportTranslationMemoryFromFilepath(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $job = $client->importTranslationMemoryFromFilepath($this->tempTmxFile(), 'Imported TM');

        $this->assertEquals('import', $job->operation);
        $this->assertEquals('translation_memory', $job->product);
        $this->assertEquals(TranslationMemoryJobResult::STATUS_COMPLETED, $job->status());
        $translationMemoryId = $job->result()->translationMemoryId;
        $this->assertNotEmpty($translationMemoryId);

        $imported = $client->getTranslationMemory($translationMemoryId);
        $this->assertEquals('Imported TM', $imported->name);

        $client->deleteTranslationMemory($imported);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testCreateTranslationMemoryImportAwaitsUpload(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $created = $client->createTranslationMemoryImport('example.tmx', 1024, null, 'Awaiting Upload TM');

        $this->assertNotEmpty($created->jobId);
        $this->assertNotEmpty($created->uploadUrl);

        $job = $client->getTranslationMemoryJob($created->jobId);
        $this->assertEquals(TranslationMemoryJobResult::STATUS_AWAITING_INPUT, $job->status());
        $this->assertNotEmpty($job->result()->requiredAction);
        $this->assertFalse($job->done());
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testWaitUntilTranslationMemoryJobDonePollsThroughAwaitingInput(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        // Make the job report its non-terminal status once before it completes
        $this->sessionTmJobProcessingPolls = 1;
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $fileContent = self::EXAMPLE_TMX;

        $created = $client->createTranslationMemoryImport(
            'example.tmx',
            strlen($fileContent),
            null,
            'Awaiting Input TM'
        );
        $client->uploadTranslationMemoryFile($created, $fileContent);

        // The API detects the upload asynchronously, so the job keeps reporting 'awaiting_input'
        // for a while after it was uploaded. Waiting must poll through that status, not throw.
        $startTime = microtime(true);
        $job = $client->waitUntilTranslationMemoryJobDone($created->jobId, 60);
        $elapsed = microtime(true) - $startTime;

        $this->assertEquals(TranslationMemoryJobResult::STATUS_COMPLETED, $job->status());
        $this->assertTrue($job->done());
        // The first poll answered 'awaiting_input', so the loop slept once before polling again
        $this->assertGreaterThanOrEqual(4.5, $elapsed);
        $client->deleteTranslationMemory($job->result()->translationMemoryId);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testCreateTranslationMemoryImportRejectsInvalidFile(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $this->assertExceptionContains('fileName', function () use ($client) {
            $client->createTranslationMemoryImport('', 1024);
        });
        $this->assertExceptionContains('contentLength', function () use ($client) {
            $client->createTranslationMemoryImport('example.tmx', 0);
        });
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testExportTranslationMemoryToFilepath(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $tmxFile = $this->tempTmxFile();
        $outputFile = dirname($tmxFile) . '/exported.tmx';
        $imported = $client->importTranslationMemoryFromFilepath($tmxFile);
        $translationMemoryId = $imported->result()->translationMemoryId;

        $job = $client->exportTranslationMemoryToFilepath($translationMemoryId, $outputFile);

        $this->assertEquals('export', $job->operation);
        $this->assertEquals(TranslationMemoryJobResult::STATUS_COMPLETED, $job->status());
        $this->assertStringContainsString('<tmx', $this->readFile($outputFile));

        $client->deleteTranslationMemory($translationMemoryId);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testCreateTranslationMemoryExportReusesCompletedJob(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $imported = $client->importTranslationMemoryFromFilepath($this->tempTmxFile());
        $translationMemoryId = $imported->result()->translationMemoryId;

        $created = $client->createTranslationMemoryExport($translationMemoryId);
        $this->assertFalse($created->reusedExisting);
        $this->assertEquals($translationMemoryId, $created->translationMemoryId);
        $client->waitUntilTranslationMemoryJobDone($created->jobId);

        $reused = $client->createTranslationMemoryExport($translationMemoryId);
        $this->assertTrue($reused->reusedExisting);
        $this->assertEquals($created->jobId, $reused->jobId);

        $client->deleteTranslationMemory($translationMemoryId);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testGetTranslationMemoryJobWithUnknownId(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);

        $this->assertExceptionClass(NotFoundException::class, function () use ($client) {
            $client->getTranslationMemoryJob(self::UNKNOWN_ID);
        });
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testDeleteTranslationMemory(?ClientInterface $httpClient)
    {
        $this->needsMockServer();
        $client = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $imported = $client->importTranslationMemoryFromFilepath($this->tempTmxFile());
        $translationMemoryId = $imported->result()->translationMemoryId;

        $client->deleteTranslationMemory($translationMemoryId);

        $this->assertExceptionClass(NotFoundException::class, function () use ($client, $translationMemoryId) {
            $client->getTranslationMemory($translationMemoryId);
        });
    }
}
