<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\DocumentHandle;
use DeepL\DocumentNotReadyException;
use DeepL\DocumentStatus;
use DeepL\DocumentStatusCode;
use DeepL\DocumentTranslationException;
use DeepL\Fixtures;
use DeepL\GlossaryInfo;
use DeepL\InvalidContentException;
use DeepL\StyleProfile;
use DeepL\StyleRuleInfo;
use DeepL\TextResult;
use DeepL\TranslateDocumentOptions;
use DeepL\TranslationMemoryInfo;
use DeepL\Translator;
use DeepL\TranslatorOptions;
use GuzzleHttp\Psr7\Response;

class TranslateDocumentTest extends OfflineTestCase
{
    private const UPLOAD_REPLY = ['document_id' => 'doc1', 'document_key' => 'key/+&='];

    public function testSendsUploadStatusAndDownloadRequests(): void
    {
        $input = Fixtures::tempFile('input.txt', 'Hello');
        $output = Fixtures::tempDir() . '/output.txt';
        $this->reply(self::UPLOAD_REPLY);
        $this->reply(['document_id' => 'doc1', 'status' => 'done', 'billed_characters' => 5]);
        $this->mock->append(new Response(200, [], 'Hallo'));

        $status = $this->makeTranslator()->translateDocument($input, $output, 'en', 'de');

        $this->assertRequestCount(3);
        $this->assertRequest('POST', '/v2/document', 0);
        $this->assertMultipart(['target_lang' => 'de', 'source_lang' => 'en'], 'input.txt', 0);
        $this->assertRequest('POST', '/v2/document/doc1', 1);
        $this->assertSame(['document_key' => 'key/+&='], $this->formBody(1));
        $this->assertRequest('POST', '/v2/document/doc1/result', 2);
        $this->assertSame(['document_key' => 'key/+&='], $this->formBody(2));
        $this->assertSame(5, $status->billedCharacters);
        $this->assertSame('Hallo', file_get_contents($output));
    }

    /**
     * @dataProvider provideOptions
     */
    public function testSendsOptionsAsMultipartFields(array $options, array $fields): void
    {
        $input = Fixtures::tempFile('input.txt', 'Hello');
        $this->reply(self::UPLOAD_REPLY);

        $this->makeTranslator()->uploadDocument($input, 'en', 'de', $options);

        $this->assertMultipart($fields + ['target_lang' => 'de', 'source_lang' => 'en'], 'input.txt');
    }

    public static function provideOptions(): array
    {
        $glossary = new GlossaryInfo('g1', 'name', true, 'en', 'de', new \DateTime(), 1);
        $style = new StyleRuleInfo('sid', 'name', new \DateTime(), new \DateTime(), 'en', 1);
        $tm = new TranslationMemoryInfo('tmid', 'name', 'en', ['de'], 1);
        $profile = new StyleProfile('spid', 'name', null, null, [], [], [], false, new \DateTime(), new \DateTime());
        return [
            'formality' => [[TranslateDocumentOptions::FORMALITY => 'More'], ['formality' => 'more']],
            'glossary' => [[TranslateDocumentOptions::GLOSSARY => $glossary], ['glossary_id' => 'g1']],
            'glossary ids joined by comma' => [
                [TranslateDocumentOptions::GLOSSARY_IDS => ['g1', $glossary, 'g3']],
                ['glossary_ids' => 'g1,g1,g3'],
            ],
            'output format' => [[TranslateDocumentOptions::OUTPUT_FORMAT => 'docx'], ['output_format' => 'docx']],
            'watermark enabled' => [
                [TranslateDocumentOptions::ENABLE_WATERMARK => true],
                ['enable_watermark' => 'true'],
            ],
            'watermark disabled' => [
                [TranslateDocumentOptions::ENABLE_WATERMARK => false],
                ['enable_watermark' => 'false'],
            ],
            'style id' => [[TranslateDocumentOptions::STYLE_ID => $style], ['style_id' => 'sid']],
            'style rule' => [[TranslateDocumentOptions::STYLE_RULE => 'sid'], ['style_id' => 'sid']],
            'style rule from object' => [[TranslateDocumentOptions::STYLE_RULE => $style], ['style_id' => 'sid']],
            'translation memory' => [
                [TranslateDocumentOptions::TRANSLATION_MEMORY => $tm],
                ['translation_memory_id' => 'tmid'],
            ],
            'style profile id' => [
                [TranslateDocumentOptions::STYLE_PROFILE => 'spid'],
                ['style_profile_id' => 'spid'],
            ],
            'style profile id from object' => [
                [TranslateDocumentOptions::STYLE_PROFILE => $profile],
                ['style_profile_id' => 'spid'],
            ],
            'translation memory with threshold as string' => [
                [
                    TranslateDocumentOptions::TRANSLATION_MEMORY_ID => $tm,
                    TranslateDocumentOptions::TRANSLATION_MEMORY_THRESHOLD => 75,
                ],
                ['translation_memory_id' => 'tmid', 'translation_memory_threshold' => '75'],
            ],
            'extra body parameters' => [
                [TranslateDocumentOptions::EXTRA_BODY_PARAMETERS => ['custom' => 1, 'target_lang' => 'fr']],
                ['custom' => '1', 'target_lang' => 'fr'],
            ],
            'extra body parameter object with __toString sent as its string' => [
                [TranslateDocumentOptions::EXTRA_BODY_PARAMETERS => ['context' => new TextResult('ctx', 'EN', 3)]],
                ['context' => 'ctx'],
            ],
            'extra body parameters of other JSON types' => [
                [TranslateDocumentOptions::EXTRA_BODY_PARAMETERS => [
                    'flag_on' => true, 'flag_off' => false, 'list' => ['a', 'b'],
                ]],
                ['flag_on' => 'true', 'flag_off' => 'false', 'list' => '["a","b"]'],
            ],
        ];
    }

    public function testStepMethodsSendUploadStatusAndDownloadRequests(): void
    {
        $output = Fixtures::tempDir() . '/output.txt';
        $this->reply(self::UPLOAD_REPLY);
        $this->reply(['document_id' => 'doc1', 'status' => 'translating']);
        $this->reply(['document_id' => 'doc1', 'status' => 'done', 'billed_characters' => 5]);
        $this->mock->append(new Response(200, [], 'Hallo'));
        $translator = $this->makeTranslator();

        $handle = $translator->translateDocumentUpload(Fixtures::tempFile('input.txt', 'Hello'), 'en', 'de');
        $status = $translator->translateDocumentGetStatus($handle);
        $done = $translator->translateDocumentWaitUntilDone($handle);
        $translator->translateDocumentDownload($handle, $output);

        $this->assertRequest('POST', '/v2/document', 0);
        $this->assertMultipart(['target_lang' => 'de', 'source_lang' => 'en'], 'input.txt', 0);
        foreach ([1, 2] as $index) {
            $this->assertRequest('POST', '/v2/document/doc1', $index);
            $this->assertSame(['document_key' => 'key/+&='], $this->formBody($index));
        }
        $this->assertRequest('POST', '/v2/document/doc1/result', 3);
        $this->assertSame(['document_key' => 'key/+&='], $this->formBody(3));
        $this->assertSame(DocumentStatusCode::TRANSLATING, $status->status);
        $this->assertSame(5, $done->billedCharacters);
        $this->assertSame('Hallo', file_get_contents($output));
    }

    public function testEncodesTheDocumentIdInStatusAndDownloadPaths(): void
    {
        $handle = new DocumentHandle('a/b c?', 'k');
        $this->reply(['status' => 'done']);
        $this->mock->append(new Response(200, [], 'Hallo'));

        $this->makeTranslator()->translateDocumentGetStatus($handle);
        $this->makeTranslator()->translateDocumentDownload($handle, Fixtures::tempDir() . '/output.txt');

        $this->assertRequest('POST', '/v2/document/a%2Fb%20c%3F', 0);
        $this->assertRequest('POST', '/v2/document/a%2Fb%20c%3F/result', 1);
    }

    public function testSendsTheFilenameOption(): void
    {
        $this->reply(self::UPLOAD_REPLY);

        $this->makeTranslator()->translateDocumentUpload(
            Fixtures::tempFile('input.txt', 'Hello'),
            null,
            'de',
            [TranslateDocumentOptions::FILENAME => 'report.html']
        );

        $this->assertMultipart(['target_lang' => 'de'], 'report.html');
    }

    public function testThrowsInvalidContentWhenTheUploadResponseHasNoStringIds(): void
    {
        $this->reply(['document_id' => 123, 'document_key' => 'k']);

        $this->expectException(InvalidContentException::class);

        $this->makeTranslator()->translateDocumentUpload(Fixtures::tempFile('input.txt', 'Hello'), null, 'de');
    }

    public function testWaitThrowsWhenTheTimeoutExpires(): void
    {
        $this->useFakeClock();
        $this->reply(['document_id' => 'doc1', 'status' => 'queued']);
        $this->reply(['document_id' => 'doc1', 'status' => 'translating']);

        try {
            $this->makeTranslator()->translateDocumentWaitUntilDone(
                new DocumentHandle('doc1', 'k'),
                [TranslateDocumentOptions::TIMEOUT => 4.5]
            );
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString('Timeout of 4.5s exceeded', $exception->getMessage());
        }
        $this->assertRequestCount(2);
    }

    public function testTranslateDocumentThrowsWithTheHandleWhenTheTimeoutExpires(): void
    {
        $this->useFakeClock();
        $output = Fixtures::tempDir() . '/output.txt';
        $this->reply(self::UPLOAD_REPLY);
        $this->reply(['document_id' => 'doc1', 'status' => 'translating']);

        try {
            $this->makeTranslator()->translateDocument(
                Fixtures::tempFile('input.txt', 'Hello'),
                $output,
                'en',
                'de',
                [TranslateDocumentOptions::TIMEOUT => 0]
            );
            $this->fail('expected a DocumentTranslationException');
        } catch (DocumentTranslationException $exception) {
            $this->assertStringContainsString('Timeout of 0s exceeded', $exception->getMessage());
            $this->assertSame('doc1', $exception->handle->documentId);
        }
        $this->assertMultipart(['target_lang' => 'de', 'source_lang' => 'en'], 'input.txt');
        $this->assertFileDoesNotExist($output);
    }

    public function testTranslateDocumentSendsWatermarkWithOutputFormat(): void
    {
        $input = Fixtures::tempFile('input.txt', 'Hello');
        $output = Fixtures::tempDir() . '/output.docx';
        $this->reply(self::UPLOAD_REPLY);
        $this->reply(['document_id' => 'doc1', 'status' => 'done']);
        $this->mock->append(new Response(200, [], 'docx'));

        $this->makeTranslator()->translateDocument($input, $output, null, 'de', [
            TranslateDocumentOptions::OUTPUT_FORMAT => 'docx',
            TranslateDocumentOptions::ENABLE_WATERMARK => true,
        ]);

        $this->assertMultipart(
            ['target_lang' => 'de', 'output_format' => 'docx', 'enable_watermark' => 'true'],
            'input.txt'
        );
    }

    public function testOmitsWatermarkWhenUnset(): void
    {
        $this->reply(self::UPLOAD_REPLY);

        $this->makeTranslator()->uploadDocument(Fixtures::tempFile('input.txt', 'Hello'), null, 'de');

        $this->assertStringNotContainsString('enable_watermark', (string)$this->request()->getBody());
    }

    public function testSendsBinaryFileContentUnchanged(): void
    {
        $docx = Fixtures::resourcePath('example_document_template.docx');
        $this->reply(self::UPLOAD_REPLY);

        $this->makeTranslator()->uploadDocument($docx, null, 'de');

        $this->assertMultipart(['target_lang' => 'de'], 'example_document_template.docx');
        $this->assertStringContainsString(file_get_contents($docx), (string)$this->request()->getBody());
    }

    /**
     * @dataProvider provideGlossaryOptions
     */
    public function testRejectsGlossaryWithoutSourceLanguage(array $options, string $message): void
    {
        try {
            $this->makeTranslator()->translateDocumentUpload(
                Fixtures::tempFile('input.txt', 'Hello'),
                null,
                'de',
                $options
            );
            $this->fail('Expected DeepLException');
        } catch (DeepLException $e) {
            $this->assertSame($message, $e->getMessage());
        }
        $this->assertRequestCount(0);
    }

    public static function provideGlossaryOptions(): array
    {
        return [
            'glossary' => [
                [TranslateDocumentOptions::GLOSSARY => 'gid'],
                'sourceLang is required if using a glossary',
            ],
            'glossary_ids' => [
                [TranslateDocumentOptions::GLOSSARY_IDS => ['gid']],
                'sourceLang is required if using glossary_ids',
            ],
        ];
    }

    public function testThrowsWhenThresholdHasNoTranslationMemory(): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('requires translation_memory_id');

        $this->makeTranslator()->uploadDocument(
            Fixtures::tempFile('input.txt', 'Hello'),
            'en',
            'de',
            [TranslateDocumentOptions::TRANSLATION_MEMORY_THRESHOLD => 75]
        );
    }

    public function testRetriesUploadOnTooManyRequests(): void
    {
        $this->useFakeClock();
        $this->reply('', 429);
        $this->reply(self::UPLOAD_REPLY);

        $handle = $this->makeTranslator()->uploadDocument(Fixtures::tempFile('input.txt', 'Hello'), 'en', 'de');

        $this->assertRequestCount(2);
        $this->assertMultipart(['target_lang' => 'de', 'source_lang' => 'en'], 'input.txt', 1);
        $this->assertSame('doc1', $handle->documentId);
        $this->assertSame('key/+&=', $handle->documentKey);
    }

    public function testParsesStatus(): void
    {
        $this->reply(['document_id' => 'doc1', 'status' => 'translating', 'seconds_remaining' => 7]);
        $this->reply(['document_id' => 'doc1', 'status' => 'error', 'error_message' => 'broken']);

        $translating = $this->makeTranslator()->getDocumentStatus(new DocumentHandle('doc1', 'k'));
        $failed = $this->makeTranslator()->getDocumentStatus(new DocumentHandle('doc1', 'k'));

        $this->assertFalse($translating->done());
        $this->assertTrue($translating->ok());
        $this->assertSame('doc1', $translating->documentId);
        $this->assertSame(DocumentStatusCode::ERROR, $failed->status);
        $this->assertSame(7, $translating->secondsRemaining);
        $this->assertNull($translating->billedCharacters);
        $this->assertFalse($failed->ok());
        $this->assertSame('broken', $failed->errorMessage);
    }

    public function testParsesAMalformedDocumentIdAsNull(): void
    {
        $this->reply(['document_id' => 5, 'status' => 'done', 'billed_characters' => 3]);

        $status = $this->makeTranslator()->translateDocumentGetStatus(new DocumentHandle('doc1', 'k'));

        $this->assertNull($status->documentId);
        $this->assertTrue($status->done());
        $this->assertSame(3, $status->billedCharacters);
    }

    public function testPollsEveryFiveSecondsUntilDone(): void
    {
        $this->useFakeClock();
        $this->reply(['document_id' => 'doc1', 'status' => 'queued']);
        $this->reply(['document_id' => 'doc1', 'status' => 'translating']);
        $this->reply(['document_id' => 'doc1', 'status' => 'done', 'billed_characters' => 3]);
        $logger = new TestLogger();

        $status = $this->makeTranslator([TranslatorOptions::LOGGER => $logger])
            ->waitUntilDocumentTranslationComplete(new DocumentHandle('doc1', 'k'));

        $this->assertRequestCount(3);
        $this->assertRequest('POST', '/v2/document/doc1', 2);
        $this->assertTrue($status->done());
        $this->assertSame(
            2,
            substr_count($logger->content, 'Rechecking document translation status after sleeping for 5 seconds.')
        );
    }

    public function testThrowsWhenTheDocumentStatusIsError(): void
    {
        $this->reply(['document_id' => 'doc1', 'status' => 'error', 'error_message' => 'broken']);

        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('broken');

        $this->makeTranslator()->waitUntilDocumentTranslationComplete(new DocumentHandle('doc1', 'k'));
    }

    public function testThrowsWithTheHandleWhenTranslationFails(): void
    {
        $output = Fixtures::tempDir() . '/output.txt';
        $this->reply(self::UPLOAD_REPLY);
        $this->reply(['document_id' => 'doc1', 'status' => 'error', 'error_message' => 'broken']);

        try {
            $this->makeTranslator()->translateDocument(Fixtures::tempFile('input.txt', 'Hello'), $output, 'en', 'de');
            $this->fail('expected a DocumentTranslationException');
        } catch (DocumentTranslationException $exception) {
            $this->assertStringContainsString('broken', $exception->getMessage());
            $this->assertSame('doc1', $exception->handle->documentId);
            $this->assertSame('key/+&=', $exception->handle->documentKey);
        }
        $this->assertFileDoesNotExist($output);
    }

    public function testThrowsWithoutRequestWhenTheOutputFileExists(): void
    {
        $output = Fixtures::tempFile('output.txt', 'old');

        try {
            $this->makeTranslator()->translateDocument(Fixtures::tempFile('input.txt', 'Hello'), $output, 'en', 'de');
            $this->fail('expected a DocumentTranslationException');
        } catch (DocumentTranslationException $exception) {
            $this->assertStringContainsString('File already exists', $exception->getMessage());
        }
        $this->assertRequestCount(0);
        $this->assertSame('old', file_get_contents($output));
    }

    public function testThrowsAndRemovesTheFileWhenTheDocumentIsNotReady(): void
    {
        $output = Fixtures::tempDir() . '/output.txt';
        $this->reply(['message' => 'still translating'], 503);

        try {
            $this->makeTranslator(['max_retries' => 0])
                ->downloadDocument(new DocumentHandle('doc1', 'k'), $output);
            $this->fail('expected a DocumentNotReadyException');
        } catch (DocumentNotReadyException $exception) {
            $this->assertStringContainsString('still translating', $exception->getMessage());
        }
        $this->assertFileDoesNotExist($output);
    }

    public function testThrowsWhenTheDownloadTargetExists(): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('File already exists');

        $this->makeTranslator()->downloadDocument(
            new DocumentHandle('doc1', 'k'),
            Fixtures::tempFile('output.txt', 'old')
        );
    }

    public function testThrowsWithoutRequestWhenTheDownloadDirectoryDoesNotExist(): void
    {
        $output = Fixtures::tempDir() . '/missing/output.txt';

        try {
            $this->makeTranslator()->downloadDocument(new DocumentHandle('doc1', 'k'), $output);
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString('Failed to open output file', $exception->getMessage());
        }
        $this->assertRequestCount(0);
    }

    public function testThrowsDocumentTranslationExceptionWithoutRequestWhenTheInputFileDoesNotExist(): void
    {
        $input = Fixtures::tempDir() . '/missing.txt';
        $output = Fixtures::tempDir() . '/output.txt';
        // The PSR-18 client and the built-in cURL transport, whose curl_exec() must not be called.
        foreach ([$this->makeTranslator(), new Translator('k')] as $translator) {
            try {
                $translator->translateDocument($input, $output, 'en', 'de');
                $this->fail('expected a DocumentTranslationException');
            } catch (DocumentTranslationException $exception) {
                $this->assertSame("File does not exist at input file path $input", $exception->getMessage());
                $this->assertNull($exception->handle);
            }
        }
        $this->assertRequestCount(0);
    }

    public function testWrapsAnUnreadableInputFileInDocumentTranslationException(): void
    {
        $input = Fixtures::tempFile('input.txt', 'Hello');
        chmod($input, 0);
        if (is_readable($input)) {
            $this->markTestSkipped('file permissions do not apply to this user');
        }

        try {
            $this->makeTranslator()->translateDocument($input, Fixtures::tempDir() . '/output.txt', 'en', 'de');
            $this->fail('expected a DocumentTranslationException');
        } catch (DocumentTranslationException $exception) {
            $this->assertStringContainsString('Failed to read input file', $exception->getMessage());
        }
        $this->assertRequestCount(0);
    }

    public function testWrapsANonDeepLExceptionInDocumentTranslationException(): void
    {
        $client = new class ('k', $this->clientOptions([])) extends DeepLClient {
            public function uploadDocument(
                string $inputFile,
                ?string $sourceLang,
                string $targetLang,
                array $options = []
            ): DocumentHandle {
                throw new \RuntimeException('disk gone');
            }
        };

        try {
            $client->translateDocument(Fixtures::tempFile('in.txt', 'Hello'), Fixtures::tempDir() . '/o', 'en', 'de');
            $this->fail('expected a DocumentTranslationException');
        } catch (DocumentTranslationException $exception) {
            $this->assertStringContainsString('disk gone', $exception->getMessage());
            $this->assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }
    }

    /**
     * @dataProvider provideStatusOfTheCause
     */
    public function testCopiesTheHttpStatusAndRetryabilityOfTheCause(int $status, bool $shouldRetry): void
    {
        $this->reply(['message' => 'failed'], $status);

        try {
            $this->makeTranslator([TranslatorOptions::MAX_RETRIES => 0])->translateDocument(
                Fixtures::tempFile('input.txt', 'Hello'),
                Fixtures::tempDir() . '/output.txt',
                'en',
                'de'
            );
            $this->fail('expected a DocumentTranslationException');
        } catch (DocumentTranslationException $exception) {
            $this->assertSame($status, $exception->httpStatusCode);
            $this->assertSame($shouldRetry, $exception->shouldRetry);
        }
    }

    public function provideStatusOfTheCause(): array
    {
        return ['quota exceeded' => [456, false], 'service unavailable' => [503, true]];
    }

    public function testCutsTheLastSleepOfTheWaitToTheTimeout(): void
    {
        $this->useFakeClock();
        $this->reply(['document_id' => 'doc1', 'status' => 'queued']);
        $this->reply(['document_id' => 'doc1', 'status' => 'translating']);
        $this->reply(['document_id' => 'doc1', 'status' => 'translating']);
        $logger = new TestLogger();
        $start = \DeepL\microtime(true);

        try {
            $this->makeTranslator([TranslatorOptions::LOGGER => $logger])->translateDocumentWaitUntilDone(
                new DocumentHandle('doc1', 'k'),
                [TranslateDocumentOptions::TIMEOUT => 7]
            );
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString('Timeout of 7s exceeded', $exception->getMessage());
        }
        $this->assertRequestCount(3);
        $this->assertStringContainsString('after sleeping for 2 seconds', $logger->content);
        $this->assertEquals(7, \DeepL\microtime(true) - $start);
    }

    public function testThrowsTheTimeoutErrorWhenTheDeadlinePassesWhileComputingTheSleep(): void
    {
        // The wait helper's own clock reads: start, then just before the deadline, then past it.
        // Reading the clock twice per loop made the sleep negative (ValueError from usleep() in PHP 8);
        // usleep() is mocked, so the test asserts the exact int it receives instead.
        $times = [1000.0, 1000.9, 1001.1];
        $this->getFunctionMock('DeepL', 'microtime')->expects($this->any())
            ->willReturnCallback(function () use (&$times) {
                foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                    if (($frame['class'] ?? '') === Translator::class) {
                        if ($frame['function'] !== 'translateDocumentWaitUntilDone') {
                            break;
                        }
                        return count($times) > 1 ? array_shift($times) : $times[0];
                    }
                }
                return 1000.0;
            });
        $this->getFunctionMock('DeepL', 'usleep')->expects($this->once())->with($this->identicalTo(100000));
        $this->reply(['document_id' => 'doc1', 'status' => 'translating']);
        $this->reply(['document_id' => 'doc1', 'status' => 'translating']);

        try {
            $this->makeTranslator()->translateDocumentWaitUntilDone(
                new DocumentHandle('doc1', 'k'),
                [TranslateDocumentOptions::TIMEOUT => 1]
            );
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString('Timeout of 1s exceeded', $exception->getMessage());
        }
        $this->assertRequestCount(2);
    }

    public function testThrowsAndKeepsNoFileWhenTheDownloadCannotBeWritten(): void
    {
        FullDiskStream::register();
        $this->mock->append(new Response(200, [], 'Hallo'));

        try {
            $this->makeTranslator()->translateDocumentDownload(
                new DocumentHandle('doc1', 'k'),
                FullDiskStream::PROTOCOL . '://output.txt'
            );
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertStringContainsString('Failed to write the response', $exception->getMessage());
        } finally {
            FullDiskStream::unregister();
        }
    }

    public function testTranslateDocumentCallsOverriddenUploadDocument(): void
    {
        $client = new class ('k', $this->clientOptions([])) extends DeepLClient {
            public $calls = 0;

            public function uploadDocument(
                string $inputFile,
                ?string $sourceLang,
                string $targetLang,
                array $options = []
            ): DocumentHandle {
                $this->calls++;
                return parent::uploadDocument($inputFile, $sourceLang, $targetLang, $options);
            }
        };

        $this->translateDocumentWith($client);

        $this->assertSame(1, $client->calls);
    }

    public function testTranslateDocumentCallsOverriddenGetDocumentStatus(): void
    {
        $client = new class ('k', $this->clientOptions([])) extends DeepLClient {
            public $calls = 0;

            public function getDocumentStatus(DocumentHandle $handle): DocumentStatus
            {
                $this->calls++;
                return parent::getDocumentStatus($handle);
            }
        };

        $this->translateDocumentWith($client);

        $this->assertSame(1, $client->calls);
    }

    public function testTranslateDocumentCallsOverriddenDownloadDocument(): void
    {
        $client = new class ('k', $this->clientOptions([])) extends DeepLClient {
            public $calls = 0;

            public function downloadDocument(DocumentHandle $handle, string $outputFile): void
            {
                $this->calls++;
                parent::downloadDocument($handle, $outputFile);
            }
        };

        $this->translateDocumentWith($client);

        $this->assertSame(1, $client->calls);
    }

    private function translateDocumentWith(DeepLClient $client): void
    {
        $output = Fixtures::tempDir() . '/output.txt';
        $this->reply(self::UPLOAD_REPLY);
        $this->reply(['document_id' => 'doc1', 'status' => 'done', 'billed_characters' => 5]);
        $this->mock->append(new Response(200, [], 'Hallo'));

        $client->translateDocument(Fixtures::tempFile('input.txt', 'Hello'), $output, 'en', 'de');

        $this->assertRequestCount(3);
        $this->assertSame('Hallo', file_get_contents($output));
    }
}
