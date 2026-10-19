<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\AuthorizationException;
use DeepL\DeepLException;
use DeepL\GlossaryNotFoundException;
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
            $this->makeTranslator([TranslatorOptions::MAX_RETRIES => 1])->getUsage();
            $this->fail("expected a $class");
        } catch (DeepLException $exception) {
            $this->assertSame($class, get_class($exception));
            $this->assertStringStartsWith($message, $exception->getMessage());
            $this->assertStringEndsWith(', message: the message, detail: the detail', $exception->getMessage());
        }
    }

    public static function provideErrors(): array
    {
        return [
            '400' => [400, DeepLException::class, 'Bad request'],
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
}
