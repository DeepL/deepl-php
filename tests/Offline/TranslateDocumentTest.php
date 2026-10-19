<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLException;
use DeepL\DocumentHandle;
use DeepL\DocumentNotReadyException;
use DeepL\DocumentTranslationException;
use DeepL\Fixtures;
use DeepL\GlossaryInfo;
use DeepL\StyleRuleInfo;
use DeepL\TranslateDocumentOptions;
use DeepL\TranslationMemoryInfo;
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
        return [
            'formality' => [[TranslateDocumentOptions::FORMALITY => 'More'], ['formality' => 'more']],
            'glossary' => [[TranslateDocumentOptions::GLOSSARY => $glossary], ['glossary_id' => 'g1']],
            'glossary ids joined by comma' => [
                [TranslateDocumentOptions::GLOSSARY_IDS => ['g1', $glossary, 'g3']],
                ['glossary_ids' => 'g1,g1,g3'],
            ],
            'style id' => [[TranslateDocumentOptions::STYLE_ID => $style], ['style_id' => 'sid']],
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
        ];
    }

    public function testSendsBinaryFileContentUnchanged(): void
    {
        $docx = Fixtures::resourcePath('example_document_template.docx');
        $this->reply(self::UPLOAD_REPLY);

        $this->makeTranslator()->uploadDocument($docx, null, 'de');

        $this->assertMultipart(['target_lang' => 'de'], 'example_document_template.docx');
        $this->assertStringContainsString(file_get_contents($docx), (string)$this->request()->getBody());
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
        $this->assertSame(7, $translating->secondsRemaining);
        $this->assertNull($translating->billedCharacters);
        $this->assertFalse($failed->ok());
        $this->assertSame('broken', $failed->errorMessage);
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
            $this->makeTranslator(['max_retries' => 1])
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
}
