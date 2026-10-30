<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\Fixtures;
use DeepL\InvalidContentException;
use DeepL\NotFoundException;
use DeepL\TranslationMemoryImport;
use DeepL\TranslationMemoryInfo;
use DeepL\TranslationMemoryJob;
use DeepL\TranslationMemoryJobResult;
use DeepL\TranslationMemorySegmentsOptions;
use GuzzleHttp\Psr7\Response;

class TranslationMemoriesTest extends OfflineTestCase
{
    private const STORAGE_URL = 'https://storage.example.com/tm/upload?sig=a%2Bb&exp=1';
    private const TM = [
        'translation_memory_id' => 'tm/1',
        'name' => 'My TM',
        'source_language' => 'en',
        'target_languages' => ['de', 'fr'],
        'segment_count' => 4,
        'creation_time' => '2025-01-02T03:04:05Z',
        'updated_time' => '2025-02-03T04:05:06Z',
    ];

    private static function job(string $status, array $result = []): array
    {
        return [
            'job_id' => 'job1',
            'operation' => 'import',
            'product' => 'translation_memory',
            'creation_time' => '2025-01-02T03:04:05Z',
            'updated_time' => '2025-01-02T03:04:06Z',
            'parameters' => ['translation_memory_id' => 'tm1', 'display_name' => 'My TM'],
            'source_file' => ['content_type' => 'application/xml', 'content_length' => 42],
            'results' => [['status' => $status] + $result],
        ];
    }

    public function testSendsPagingQueryWhenListing(): void
    {
        $this->reply(['translation_memories' => [self::TM]]);
        $this->reply(['translation_memories' => []]);
        $client = $this->makeDeepLClient();

        $client->listTranslationMemories(2, 10);
        $client->listTranslationMemories();

        $this->assertRequest('GET', '/v3/translation_memories', 0, 'page=2&page_size=10');
        $this->assertRequest('GET', '/v3/translation_memories', 1);
    }

    public function testParsesTranslationMemories(): void
    {
        $this->reply(['translation_memories' => [self::TM]]);
        $this->reply(self::TM);

        $listed = $this->makeDeepLClient()->listTranslationMemories()[0];
        $fetched = $this->makeDeepLClient()->getTranslationMemory('tm/1');

        $this->assertRequest('GET', '/v3/translation_memories/tm%2F1', 1);
        foreach ([$listed, $fetched] as $info) {
            $this->assertInstanceOf(TranslationMemoryInfo::class, $info);
            $this->assertSame(['tm/1', 'en', ['de', 'fr'], 4], [
                $info->translationMemoryId,
                $info->sourceLanguage,
                $info->targetLanguages,
                $info->segmentCount,
            ]);
            $this->assertSame('2025-01-02 03:04:05', $info->creationTime->format('Y-m-d H:i:s'));
            $this->assertSame('2025-02-03 04:05:06', $info->updatedTime->format('Y-m-d H:i:s'));
        }
    }

    public function testParsesMissingOptionalFieldsOfATranslationMemory(): void
    {
        $this->reply(array_diff_key(self::TM, array_flip(['target_languages', 'segment_count', 'creation_time'])));

        $info = $this->makeDeepLClient()->getTranslationMemory('tm1');

        $this->assertSame([], $info->targetLanguages);
        $this->assertSame(0, $info->segmentCount);
        $this->assertNull($info->creationTime);
    }

    /**
     * @dataProvider provideResponsesMissingARequiredField
     */
    public function testThrowsInvalidContentExceptionWhenARequiredFieldIsMissing(callable $call, array $body): void
    {
        $this->reply($body);

        $this->expectException(InvalidContentException::class);

        $call($this->makeDeepLClient());
    }

    public static function provideResponsesMissingARequiredField(): array
    {
        $getTm = function (DeepLClient $c) {
            $c->getTranslationMemory('tm1');
        };
        $listTms = function (DeepLClient $c) {
            $c->listTranslationMemories();
        };
        $listSegments = function (DeepLClient $c) {
            $c->listTranslationMemorySegments('tm1');
        };
        $segment = ['source_segment_id' => 's1', 'source_text' => 'Hello', 'targets' => []];
        $target = ['target_segment_id' => 't1', 'target_language' => 'de', 'target_text' => 'Hallo'];
        $cases = [
            'import without job_id' => [function (DeepLClient $c) {
                $c->createTranslationMemoryImport('a.tmx', 10);
            }, ['upload_url' => self::STORAGE_URL]],
            'import without upload_url' => [function (DeepLClient $c) {
                $c->createTranslationMemoryImport('a.tmx', 10);
            }, ['job_id' => 'job1']],
            'export without job_id' => [function (DeepLClient $c) {
                $c->createTranslationMemoryExport('tm1');
            }, ['parameters' => ['translation_memory_id' => 'tm1']]],
            'job without job_id' => [function (DeepLClient $c) {
                $c->getTranslationMemoryJob('job1');
            }, array_diff_key(self::job('completed'), ['job_id' => 0])],
        ];
        foreach (['translation_memory_id', 'name', 'source_language'] as $key) {
            $tm = array_diff_key(self::TM, [$key => 0]);
            $cases["translation memory without $key"] = [$getTm, $tm];
            $cases["listed translation memory without $key"] = [$listTms, ['translation_memories' => [$tm]]];
        }
        foreach (['source_segment_id', 'source_text'] as $key) {
            $cases["segment without $key"] = [$listSegments, ['segments' => [array_diff_key($segment, [$key => 0])]]];
        }
        foreach (['target_segment_id', 'target_language', 'target_text'] as $key) {
            $cases["target segment without $key"] =
                [$listSegments, ['segments' => [['targets' => [array_diff_key($target, [$key => 0])]] + $segment]]];
        }
        return $cases;
    }

    public function testSendsTheIdOfTranslationMemoryInfoObjects(): void
    {
        $this->reply(self::TM);
        $this->reply('', 204);
        $client = $this->makeDeepLClient();
        $info = new TranslationMemoryInfo('tm/1', 'n', 'en', [], 0);

        $client->getTranslationMemory($info);
        $client->deleteTranslationMemory($info);

        $this->assertRequest('GET', '/v3/translation_memories/tm%2F1', 0);
        $this->assertRequest('DELETE', '/v3/translation_memories/tm%2F1', 1);
    }

    public function testSendsSegmentQueryWithPercentEncodedValues(): void
    {
        $this->reply(['segments' => [], 'segment_count' => 0]);
        $this->reply(['segments' => [], 'segment_count' => 0]);
        $client = $this->makeDeepLClient();

        $client->listTranslationMemorySegments('tm1', [
            TranslationMemorySegmentsOptions::PAGE_SIZE => 5,
            TranslationMemorySegmentsOptions::PAGE_CURSOR => 'abc==',
            TranslationMemorySegmentsOptions::FILTER_TEXT => 'hello world',
            TranslationMemorySegmentsOptions::FILTER_CASE_SENSITIVE => false,
        ]);
        $client->listTranslationMemorySegments('tm1');

        $this->assertRequest(
            'GET',
            '/v3/translation_memories/tm1/segments',
            0,
            'page_size=5&page_cursor=abc%3D%3D&filter_text=hello%20world&filter_case_sensitive=false'
        );
        $this->assertRequest('GET', '/v3/translation_memories/tm1/segments', 1);
    }

    public function testParsesSegments(): void
    {
        $this->reply([
            'segment_count' => 7,
            'next_page_cursor' => 'next',
            'segments' => [[
                'source_segment_id' => 's1',
                'source_text' => 'Hello',
                'creation_time' => '2025-01-02T03:04:05Z',
                'targets' => [
                    ['target_segment_id' => 't1', 'target_language' => 'de', 'target_text' => 'Hallo',
                        'last_used_time' => '2025-03-04T05:06:07Z'],
                ],
            ]],
        ]);

        $segments = $this->makeDeepLClient()->listTranslationMemorySegments('tm1');

        $this->assertSame([7, 'next'], [$segments->segmentCount, $segments->nextPageCursor]);
        $segment = $segments->segments[0];
        $this->assertSame(['s1', 'Hello'], [$segment->sourceSegmentId, $segment->sourceText]);
        $this->assertSame('2025-01-02', $segment->creationTime->format('Y-m-d'));
        $this->assertNull($segment->updatedTime);
        $this->assertSame(['t1', 'de', 'Hallo'], [
            $segment->targets[0]->targetSegmentId,
            $segment->targets[0]->targetLanguage,
            $segment->targets[0]->targetText,
        ]);
        $this->assertSame('2025-03-04', $segment->targets[0]->lastUsedTime->format('Y-m-d'));
        $this->assertSame('de: Hallo', (string)$segment->targets[0]);
    }

    public function testSendsImportRequestWithAllFields(): void
    {
        $this->reply(['job_id' => 'job1', 'upload_url' => self::STORAGE_URL, 'expires_at' => '2025-01-02T03:04:05Z']);

        $import = $this->makeDeepLClient()->createTranslationMemoryImport('a.tmx', 42, 'application/xml', 'My TM');

        $this->assertRequest('POST', '/v3/translation_memories/import');
        $this->assertSame([
            'source_file' => ['file_name' => 'a.tmx', 'content_length' => 42, 'content_type' => 'application/xml'],
            'parameters' => ['display_name' => 'My TM'],
        ], $this->jsonBody());
        $this->assertSame(['job1', self::STORAGE_URL], [$import->jobId, $import->uploadUrl]);
        $this->assertSame('2025-01-02', $import->expiresAt->format('Y-m-d'));
    }

    public function testSendsImportRequestWithRequiredFieldsOnly(): void
    {
        $this->reply(['job_id' => 'job1', 'upload_url' => self::STORAGE_URL]);

        $import = $this->makeDeepLClient()->createTranslationMemoryImport('a.tmx', 42);

        $this->assertSame(['source_file' => ['file_name' => 'a.tmx', 'content_length' => 42]], $this->jsonBody());
        $this->assertNull($import->expiresAt);
    }

    public function testThrowsWhenTheImportFileIsInvalid(): void
    {
        $client = $this->makeDeepLClient();

        foreach ([['', 10, 'fileName'], ['a.tmx', 0, 'contentLength']] as list($name, $length, $message)) {
            try {
                $client->createTranslationMemoryImport($name, $length);
                $this->fail('expected a DeepLException');
            } catch (DeepLException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function testUploadsToTheStorageUrlWithoutCredentials(): void
    {
        $this->reply('', 200);
        $this->reply('', 200);
        $client = $this->makeDeepLClient(['headers' => ['X-Custom' => 'secret']]);

        $client->uploadTranslationMemoryFile(new TranslationMemoryImport('job1', self::STORAGE_URL), '<tmx/>');
        $client->uploadTranslationMemoryFile(self::STORAGE_URL, '<tmx/>', 'text/xml');

        foreach ([0 => 'application/xml', 1 => 'text/xml'] as $index => $contentType) {
            $request = $this->request($index);
            $this->assertSame('PUT', $request->getMethod());
            $this->assertSame('storage.example.com', $request->getUri()->getHost());
            $this->assertSame('/tm/upload', $request->getUri()->getPath());
            $this->assertSame('sig=a%2Bb&exp=1', $request->getUri()->getQuery());
            $this->assertSame($contentType, $request->getHeaderLine('Content-Type'));
            $this->assertSame('<tmx/>', (string)$request->getBody());
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertFalse($request->hasHeader('X-Custom'));
            $this->assertStringStartsWith('deepl-php/', $request->getHeaderLine('User-Agent'));
        }
    }

    public function testSendsACustomUserAgentInAnyCaseToTheStorageUrl(): void
    {
        $this->reply('', 200);

        $this->makeDeepLClient(['headers' => ['user-agent' => 'my-client']])
            ->uploadTranslationMemoryFile(self::STORAGE_URL, '<tmx/>');

        $this->assertSame(['my-client'], $this->request()->getHeader('User-Agent'));
    }

    public function testThrowsWithTheStatusAndContentWhenTheUploadFails(): void
    {
        $this->reply('<Error>denied</Error>', 403);

        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage(
            'Error uploading translation memory file, HTTP status: 403, content: <Error>denied</Error>'
        );

        $this->makeDeepLClient()->uploadTranslationMemoryFile(self::STORAGE_URL, '<tmx/>');
    }

    public function testThrowsWhenTheUploadUrlIsEmpty(): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('uploadUrl must be a non-empty string');

        $this->makeDeepLClient()->uploadTranslationMemoryFile('', '<tmx/>');
    }

    public function testParsesJob(): void
    {
        $this->reply(self::job('awaiting_input', [
            'status_metadata' => ['required_action' => 'upload_file'],
            'translation_memory_id' => 'tm1',
            'skipped_segment_count' => 2,
        ]));

        $job = $this->makeDeepLClient()->getTranslationMemoryJob('job1');

        $this->assertRequest('GET', '/v3/translation_memories/jobs/job1');
        $this->assertSame(['job1', 'tm1', 'My TM'], [
            $job->jobId,
            $job->translationMemoryId,
            $job->displayName,
        ]);
        $this->assertSame(['application/xml', 42], [$job->sourceContentType, $job->sourceContentLength]);
        $this->assertSame(TranslationMemoryJobResult::STATUS_AWAITING_INPUT, $job->status());
        $this->assertSame('upload_file', $job->result()->requiredAction);
        $this->assertSame(2, $job->result()->skippedSegmentCount);
        $this->assertFalse($job->done());
        $this->assertTrue($job->ok());
    }

    /**
     * @dataProvider provideJobStatuses
     */
    public function testDerivesDoneAndOkFromTheJobStatus(string $status, bool $done, bool $ok): void
    {
        $job = TranslationMemoryJob::fromJson(self::job($status));

        $this->assertSame($done, $job->done());
        $this->assertSame($ok, $job->ok());
    }

    public static function provideJobStatuses(): array
    {
        return [
            'awaiting_input' => ['awaiting_input', false, true],
            'processing' => ['processing', false, true],
            'completed' => ['completed', true, true],
            'downloaded' => ['downloaded', true, true],
            'failed' => ['failed', true, false],
            'expired' => ['expired', true, false],
        ];
    }

    public function testPollsEveryFiveSecondsThroughNonTerminalStatuses(): void
    {
        $this->useFakeClock();
        $this->reply(self::job('awaiting_input'));
        $this->reply(self::job('processing'));
        $this->reply(self::job('completed', ['translation_memory_id' => 'tm1']));
        $logger = new TestLogger();

        $job = $this->makeDeepLClient(['logger' => $logger])->waitUntilTranslationMemoryJobDone('job1', 60);

        $this->assertRequestCount(3);
        $this->assertRequest('GET', '/v3/translation_memories/jobs/job1', 2);
        $this->assertSame('tm1', $job->result()->translationMemoryId);
        $this->assertSame(2, substr_count(
            $logger->content,
            'Rechecking translation memory job status after sleeping for 5 seconds.'
        ));
    }

    public function testThrowsWhenWaitingForTheJobExceedsTheTimeout(): void
    {
        $this->useFakeClock();
        foreach ([1, 2, 3] as $_) {
            $this->reply(self::job('processing'));
        }

        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('Manual timeout of 7s exceeded');

        $this->makeDeepLClient()->waitUntilTranslationMemoryJobDone('job1', 7.0);
    }

    public function testThrowsTheErrorMessageWhenTheJobFails(): void
    {
        $this->reply(self::job('failed', ['error' => ['message' => 'invalid TMX']]));

        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('invalid TMX');

        $this->makeDeepLClient()->waitUntilTranslationMemoryJobDone('job1');
    }

    public function testThrowsUnknownErrorWhenTheJobFailsWithoutMessage(): void
    {
        $this->reply(self::job('expired'));

        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('unknown error');

        $this->makeDeepLClient()->waitUntilTranslationMemoryJobDone('job1');
    }

    /**
     * @dataProvider provideExportStatuses
     */
    public function testParsesWhetherTheExportWasReused(int $status, bool $reused): void
    {
        $this->reply(['job_id' => 'job2', 'parameters' => ['translation_memory_id' => 'tm/1']], $status);

        $export = $this->makeDeepLClient()->createTranslationMemoryExport('tm/1');

        $this->assertRequest('POST', '/v3/translation_memories/tm%2F1/export');
        $this->assertSame(
            ['job2', 'tm/1', $reused],
            [$export->jobId, $export->translationMemoryId, $export->reusedExisting]
        );
    }

    public static function provideExportStatuses(): array
    {
        return [
            'reused completed export' => [200, true],
            'new export' => [202, false],
        ];
    }

    public function testDownloadsTheExportFromTheStorageUrlWithoutCredentials(): void
    {
        $output = Fixtures::tempDir() . '/export.tmx';
        $this->mock->append(new Response(200, [], '<tmx/>'));
        $job = TranslationMemoryJob::fromJson(self::job('completed', ['download_url' => self::STORAGE_URL]));

        $this->makeDeepLClient()->downloadTranslationMemoryExport($job, $output);

        $request = $this->request();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('storage.example.com', $request->getUri()->getHost());
        $this->assertSame('sig=a%2Bb&exp=1', $request->getUri()->getQuery());
        $this->assertFalse($request->hasHeader('Authorization'));
        $this->assertSame('<tmx/>', file_get_contents($output));
    }

    public function testThrowsWithTheStorageErrorAndRemovesTheFileWhenTheDownloadFails(): void
    {
        $output = Fixtures::tempDir() . '/export.tmx';
        $this->mock->append(new Response(403, [], '<Error>expired link</Error>'));
        $job = TranslationMemoryJob::fromJson(self::job('completed', ['download_url' => self::STORAGE_URL]));

        try {
            $this->makeDeepLClient()->downloadTranslationMemoryExport($job, $output);
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertSame(
                'Error downloading translation memory export, HTTP status: 403, content: <Error>expired link</Error>',
                $exception->getMessage()
            );
        }
        $this->assertFileDoesNotExist($output);
    }

    public function testThrowsWhenTheExportCannotBeWritten(): void
    {
        FullDiskStream::register();
        $this->mock->append(new Response(200, [], '<tmx/>'));
        $job = TranslationMemoryJob::fromJson(self::job('completed', ['download_url' => self::STORAGE_URL]));

        try {
            $this->makeDeepLClient()->downloadTranslationMemoryExport($job, FullDiskStream::PROTOCOL . '://e.tmx');
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString('Failed to write the response', $exception->getMessage());
        } finally {
            FullDiskStream::unregister();
        }
    }

    public function testThrowsWithoutRequestWhenTheExportCannotBeDownloaded(): void
    {
        $client = $this->makeDeepLClient();
        $existing = Fixtures::tempFile('export.tmx', 'old');
        $withUrl = TranslationMemoryJob::fromJson(self::job('completed', ['download_url' => self::STORAGE_URL]));

        foreach ([
            [
                TranslationMemoryJob::fromJson(self::job('processing')),
                Fixtures::tempDir() . '/a.tmx',
                'no download URL',
            ],
            [$withUrl, $existing, 'File already exists'],
        ] as list($job, $output, $message)) {
            try {
                $client->downloadTranslationMemoryExport($job, $output);
                $this->fail('expected a DeepLException');
            } catch (DeepLException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
        $this->assertRequestCount(0);
        $this->assertSame('old', file_get_contents($existing));
    }

    public function testImportsAFileByCreatingUploadingAndWaiting(): void
    {
        $this->reply(['job_id' => 'job1', 'upload_url' => self::STORAGE_URL]);
        $this->reply('', 200);
        $this->reply(self::job('completed', ['translation_memory_id' => 'tm1']));
        $file = Fixtures::tempFile('example.tmx', '<tmx>hello</tmx>');

        $job = $this->makeDeepLClient()->importTranslationMemoryFromFilepath($file, 'My TM');

        $this->assertRequest('POST', '/v3/translation_memories/import', 0);
        $this->assertSame([
            'source_file' => ['file_name' => 'example.tmx', 'content_length' => 16],
            'parameters' => ['display_name' => 'My TM'],
        ], $this->jsonBody(0));
        $this->assertSame('PUT', $this->request(1)->getMethod());
        $this->assertSame('<tmx>hello</tmx>', (string)$this->request(1)->getBody());
        $this->assertRequest('GET', '/v3/translation_memories/jobs/job1', 2);
        $this->assertSame('tm1', $job->result()->translationMemoryId);
    }

    public function testThrowsWhenTheImportFileDoesNotExist(): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('File does not exist at input file path');

        $this->makeDeepLClient()->importTranslationMemoryFromFilepath(Fixtures::tempDir() . '/missing.tmx');
    }

    public function testExportsToAFileByCreatingWaitingAndDownloading(): void
    {
        $output = Fixtures::tempDir() . '/export.tmx';
        $this->reply(['job_id' => 'job2', 'parameters' => ['translation_memory_id' => 'tm1']], 202);
        $this->reply(self::job('completed', ['download_url' => self::STORAGE_URL]));
        $this->mock->append(new Response(200, [], '<tmx/>'));

        $this->makeDeepLClient()->exportTranslationMemoryToFilepath('tm1', $output);

        $this->assertRequest('POST', '/v3/translation_memories/tm1/export', 0);
        $this->assertRequest('GET', '/v3/translation_memories/jobs/job2', 1);
        $this->assertSame('storage.example.com', $this->request(2)->getUri()->getHost());
        $this->assertSame('<tmx/>', file_get_contents($output));
    }

    public function testThrowsNotFoundWhenTheTranslationMemoryDoesNotExist(): void
    {
        $this->reply(['message' => 'not here'], 404);

        $this->expectException(NotFoundException::class);

        $this->makeDeepLClient()->getTranslationMemory('gone');
    }

    public function testThrowsWhenTheTranslationMemoryIdIsEmpty(): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('translationMemoryId must be a non-empty string');

        $this->makeDeepLClient()->getTranslationMemory('');
    }

    public function testThrowsWithoutRequestWhenTheExportDirectoryDoesNotExist(): void
    {
        $output = Fixtures::tempDir() . '/missing/export.tmx';
        $job = TranslationMemoryJob::fromJson(self::job('completed', ['download_url' => self::STORAGE_URL]));

        try {
            $this->makeDeepLClient()->downloadTranslationMemoryExport($job, $output);
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString('Failed to open output file', $exception->getMessage());
        }
        $this->assertRequestCount(0);
    }
}
