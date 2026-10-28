<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\AuthorizationException;
use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\DocumentHandle;
use DeepL\Fixtures;
use DeepL\GlossaryNotFoundException;
use DeepL\InvalidContentException;
use DeepL\NotFoundException;
use DeepL\QuotaExceededException;
use DeepL\TooManyRequestsException;
use DeepL\TranslatorOptions;

class ErrorsTest extends OfflineTestCase
{
    /**
     * @dataProvider provideErrors
     */
    public function testMapsTheStatusToAnException(int $status, string $class, string $message): void
    {
        $this->reply(['message' => 'the message', 'detail' => 'the detail'], $status);

        try {
            $this->makeTranslator([TranslatorOptions::MAX_RETRIES => 0])->getUsage();
            $this->fail("expected a $class");
        } catch (DeepLException $exception) {
            $this->assertSame($class, get_class($exception));
            $this->assertSame($status, $exception->httpStatusCode);
            $this->assertSame($status === 429 || $status >= 500, $exception->shouldRetry);
            $this->assertStringStartsWith($message, $exception->getMessage());
            $this->assertStringEndsWith(', message: the message, detail: the detail', $exception->getMessage());
        }
    }

    public static function provideErrors(): array
    {
        return [
            '400' => [400, DeepLException::class, 'Bad request'],
            '401' => [401, AuthorizationException::class, 'Authorization failure, check authentication key'],
            '403' => [403, AuthorizationException::class, 'Authorization failure, check authentication key'],
            '404' => [404, NotFoundException::class, 'Not found'],
            '429' => [429, TooManyRequestsException::class, 'Too many requests'],
            '456' => [456, QuotaExceededException::class, 'Quota for this billing period has been exceeded'],
            '503' => [503, DeepLException::class, 'Service unavailable'],
        ];
    }

    public function testMapsAnUnknownStatusToAGenericException(): void
    {
        $this->reply(['message' => 'too big'], 413);

        try {
            $this->makeTranslator()->getUsage();
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertSame(DeepLException::class, get_class($exception));
            $this->assertSame(413, $exception->httpStatusCode);
            $this->assertFalse($exception->shouldRetry);
            $this->assertSame(
                'Unexpected status code: 413 , message: too big, content: {"message":"too big"}.',
                $exception->getMessage()
            );
        }
    }

    public function testAppendsTheRawBodyWhenTheErrorIsNotJson(): void
    {
        $this->reply('<html>Bad gateway</html>', 400);

        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('Bad request, <html>Bad gateway</html>');

        $this->makeTranslator()->getUsage();
    }

    public function testMapsGlossaryNotFoundOnlyForGlossaryRequests(): void
    {
        $this->reply(['message' => 'unknown'], 404);
        $this->reply(['message' => 'unknown'], 404);
        $translator = $this->makeTranslator();

        try {
            $translator->getGlossary('gid');
            $this->fail('expected a GlossaryNotFoundException');
        } catch (DeepLException $exception) {
            $this->assertSame(GlossaryNotFoundException::class, get_class($exception));
        }
        try {
            $translator->getUsage();
            $this->fail('expected a NotFoundException');
        } catch (DeepLException $exception) {
            $this->assertSame(NotFoundException::class, get_class($exception));
        }
    }

    public function testThrowsOnARedirectInsteadOfParsingIt(): void
    {
        $this->reply(['character_count' => 1, 'character_limit' => 2], 302);

        try {
            $this->makeTranslator()->getUsage();
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertSame(DeepLException::class, get_class($exception));
            $this->assertSame(302, $exception->httpStatusCode);
            $this->assertStringStartsWith('Unexpected status code: 302', $exception->getMessage());
        }
    }

    /**
     * @dataProvider provideMalformedReplies
     */
    public function testThrowsInvalidContentExceptionWhenTheReplyIsMalformed(array $reply, callable $call): void
    {
        $this->reply($reply);

        $this->expectException(InvalidContentException::class);

        $call($this->makeDeepLClient());
    }

    public static function provideMalformedReplies(): array
    {
        $glossary = ['glossary_id' => 'g', 'name' => 'n', 'ready' => true, 'source_lang' => 'en',
            'target_lang' => 'de', 'entry_count' => 1];
        $styleRule = ['style_id' => 's', 'name' => 'n', 'language' => 'en', 'version' => 1];
        $created = '2024-01-01T00:00:00Z';
        return [
            'upload without document key' => [['document_id' => 'd'], function (DeepLClient $client) {
                $client->uploadDocument(Fixtures::tempFile('input.txt', 'Hello'), null, 'de');
            }],
            'document status without status' => [['document_id' => 'd'], function (DeepLClient $client) {
                $client->getDocumentStatus(new DocumentHandle('d', 'k'));
            }],
            'glossary without creation time' => [$glossary, function (DeepLClient $client) {
                $client->getGlossary('g');
            }],
            'glossary with an invalid creation time' => [
                $glossary + ['creation_time' => 'not a date'],
                function (DeepLClient $client) {
                    $client->getGlossary('g');
                },
            ],
            'glossary list without glossaries' => [['other' => []], function (DeepLClient $client) {
                $client->listGlossaries();
            }],
            'multilingual glossary without dictionaries' => [
                ['glossary_id' => 'g', 'name' => 'n', 'creation_time' => $created],
                function (DeepLClient $client) {
                    $client->getMultilingualGlossary('g');
                },
            ],
            'multilingual glossary list without glossaries' => [['other' => []], function (DeepLClient $client) {
                $client->listMultilingualGlossaries();
            }],
            'style rule without timestamps' => [$styleRule, function (DeepLClient $client) {
                $client->getStyleRule('s');
            }],
            'style rule list entry without name' => [
                ['style_rules' => [['style_id' => 's', 'creation_time' => $created, 'updated_time' => $created,
                    'language' => 'en', 'version' => 1]]],
                function (DeepLClient $client) {
                    $client->getAllStyleRules();
                },
            ],
        ];
    }
}
