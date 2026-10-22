<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DocumentMinifier;
use DeepL\Fixtures;
use DeepL\TranslateDocumentOptions;

class DocumentMinificationTest extends OfflineTestCase
{
    private const MEDIA_SIZE = 1000000;

    /**
     * Copies a template into a temporary directory and adds one large media file to it.
     * @return array The path of the inflated copy and the media bytes added to it.
     */
    private function inflate(string $template, string $mediaPath): array
    {
        $media = random_bytes(self::MEDIA_SIZE);
        $path = Fixtures::tempDir() . '/' . $template;
        copy(Fixtures::resourcePath($template), $path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $zip->addFromString($mediaPath, $media);
        $zip->close();
        return [$path, $media];
    }

    public static function provideDocuments(): array
    {
        return [
            'docx' => ['example_document_template.docx', 'word/media/big.png'],
            'pptx' => ['example_presentation_template.pptx', 'ppt/media/big.png'],
        ];
    }

    /**
     * @dataProvider provideDocuments
     */
    public function testMinifiesMediaOutOfTheDocument(string $template, string $mediaPath): void
    {
        list($input) = $this->inflate($template, $mediaPath);
        $minifier = new DocumentMinifier(Fixtures::tempDir());

        $minified = $minifier->minifyDocument($input);

        $this->assertGreaterThan(self::MEDIA_SIZE, filesize($input));
        $this->assertLessThan(50000, filesize($minified));
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($minified));
        $this->assertSame('DeepL Media Placeholder', $zip->getFromName($mediaPath));
    }

    /**
     * @dataProvider provideDocuments
     */
    public function testDeminifyRestoresTheMedia(string $template, string $mediaPath): void
    {
        list($input, $media) = $this->inflate($template, $mediaPath);
        $output = Fixtures::tempDir() . '/restored';
        $minifier = new DocumentMinifier(Fixtures::tempDir());

        $minifier->deminifyDocument($minifier->minifyDocument($input, true), $output);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($output));
        $this->assertSame($media, $zip->getFromName($mediaPath));
    }

    public function testUploadsTheMinifiedDocumentAndRestoresTheMediaInTheResult(): void
    {
        list($input, $media) = $this->inflate('example_document_template.docx', 'word/media/big.png');
        $output = Fixtures::tempDir() . '/output.docx';
        // The service returns the uploaded document, translated; here the minified document itself.
        $minifier = new DocumentMinifier(Fixtures::tempDir());
        $minified = file_get_contents($minifier->minifyDocument($input, true));
        $this->reply(['document_id' => 'doc1', 'document_key' => 'k']);
        $this->reply(['document_id' => 'doc1', 'status' => 'done', 'billed_characters' => 1]);
        $this->mock->append(new \GuzzleHttp\Psr7\Response(200, [], $minified));

        $this->makeTranslator()->translateDocument($input, $output, 'en', 'de', [
            TranslateDocumentOptions::ENABLE_DOCUMENT_MINIFICATION => true,
        ]);

        $this->assertMultipart(['target_lang' => 'de', 'source_lang' => 'en'], 'minifiedDoc.docx');
        $this->assertLessThan(50000, $this->request()->getBody()->getSize());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($output));
        $this->assertSame($media, $zip->getFromName('word/media/big.png'));
    }

    public function testUploadsTheOriginalDocumentWhenMinificationIsOff(): void
    {
        list($input) = $this->inflate('example_document_template.docx', 'word/media/big.png');
        $this->reply(['document_id' => 'doc1', 'document_key' => 'k']);

        $this->makeTranslator()->uploadDocument($input, 'en', 'de');

        $this->assertGreaterThan(self::MEDIA_SIZE, $this->request()->getBody()->getSize());
    }

    public function testUploadsTheOriginalDocumentWhenTheOutputFormatDiffers(): void
    {
        list($input) = $this->inflate('example_document_template.docx', 'word/media/big.png');
        $output = Fixtures::tempDir() . '/output.pdf';
        $this->reply(['document_id' => 'doc1', 'document_key' => 'k']);
        $this->reply(['document_id' => 'doc1', 'status' => 'done']);
        $this->mock->append(new \GuzzleHttp\Psr7\Response(200, [], 'pdf'));

        $this->makeTranslator()->translateDocument($input, $output, 'en', 'de', [
            TranslateDocumentOptions::ENABLE_DOCUMENT_MINIFICATION => true,
            TranslateDocumentOptions::OUTPUT_FORMAT => 'pdf',
        ]);

        $this->assertMultipart(
            ['target_lang' => 'de', 'source_lang' => 'en', 'output_format' => 'pdf'],
            basename($input)
        );
        $this->assertGreaterThan(self::MEDIA_SIZE, $this->request()->getBody()->getSize());
        $this->assertSame('pdf', file_get_contents($output));
    }

    public function testUploadsUnsupportedFileTypesUnchanged(): void
    {
        $input = Fixtures::resourcePath('example_zip_template.zip');
        $output = Fixtures::tempDir() . '/output.zip';
        $this->reply(['document_id' => 'doc1', 'document_key' => 'k']);
        $this->reply(['document_id' => 'doc1', 'status' => 'done']);
        $this->mock->append(new \GuzzleHttp\Psr7\Response(200, [], 'zip'));

        $this->makeTranslator()->translateDocument($input, $output, 'en', 'de', [
            TranslateDocumentOptions::ENABLE_DOCUMENT_MINIFICATION => true,
        ]);

        $this->assertMultipart(['target_lang' => 'de', 'source_lang' => 'en'], 'example_zip_template.zip');
        $this->assertStringContainsString(file_get_contents($input), (string)$this->request()->getBody());
    }

    public function testChecksWhichFilesCanBeMinified(): void
    {
        $this->assertTrue(DocumentMinifier::canMinifyFile('a.docx'));
        $this->assertTrue(DocumentMinifier::canMinifyFile('a.pptx'));
        $this->assertFalse(DocumentMinifier::canMinifyFile('a.txt'));
        $this->assertFalse(DocumentMinifier::canMinifyFile('a.zip'));
    }
}
