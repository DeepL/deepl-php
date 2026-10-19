<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\RealApi;

use DeepL\DeepLClient;
use DeepL\Fixtures;
use DeepL\Translator;
use DeepL\TranslatorOptions;
use PHPUnit\Framework\TestCase;

/**
 * Base class of the real-API suite: one request per endpoint, sent with the default cURL transport
 * to the server named by DEEPL_SERVER_URL (the SDK default if unset), authenticated with DEEPL_AUTH_KEY.
 */
abstract class RealApiTestCase extends TestCase
{
    /** @var string|null */
    private static $runId = null;

    /** A missing key fails the suite: the real-API tests never skip. */
    public static function setUpBeforeClass(): void
    {
        if (!getenv('DEEPL_AUTH_KEY')) {
            self::fail('DEEPL_AUTH_KEY must be set to run the real-API tests');
        }
    }

    protected function tearDown(): void
    {
        Fixtures::removeTempDirs();
    }

    protected function clientOptions(): array
    {
        $serverUrl = getenv('DEEPL_SERVER_URL');
        return $serverUrl ? [TranslatorOptions::SERVER_URL => $serverUrl] : [];
    }

    protected function makeTranslator(): Translator
    {
        return new Translator(getenv('DEEPL_AUTH_KEY'), $this->clientOptions());
    }

    protected function makeDeepLClient(): DeepLClient
    {
        return new DeepLClient(getenv('DEEPL_AUTH_KEY'), $this->clientOptions());
    }

    /**
     * Name for a resource created by this test run, e.g. sdktest-php-202610151530-k3f9qz-glossary.
     */
    protected static function uniqueName(string $label): string
    {
        if (self::$runId === null) {
            $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $random = '';
            foreach (str_split(random_bytes(6)) as $byte) {
                $random .= $alphabet[ord($byte) % 36];
            }
            self::$runId = gmdate('YmdHi') . '-' . $random;
        }
        return 'sdktest-php-' . self::$runId . '-' . $label;
    }

    /**
     * Calls $fetch until it stops throwing the given exception class: a resource that was just created
     * can be briefly invisible on the API.
     * @return mixed
     */
    protected function retryWhileMissing(callable $fetch, string $exceptionClass)
    {
        for ($attempt = 0;; $attempt++) {
            try {
                return $fetch();
            } catch (\Exception $exception) {
                if (!($exception instanceof $exceptionClass) || $attempt >= 9) {
                    throw $exception;
                }
                sleep(1);
            }
        }
    }
}
