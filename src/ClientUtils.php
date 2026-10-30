<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Internal helpers shared by the DeepL API clients.
 * @private
 */
class ClientUtils
{
    /**
     * Returns the server URL from the given options, or the default if unspecified.
     * @throws DeepLException
     */
    public static function resolveServerUrl(array $options, string $defaultServerUrl): string
    {
        $serverUrl = $options[TranslatorOptions::SERVER_URL] ?? $defaultServerUrl;
        if (!is_string($serverUrl) || strlen($serverUrl) == 0) {
            throw new DeepLException('If specified, ' .
                TranslatorOptions::SERVER_URL . ' option must be a non-empty string.');
        } elseif (substr($serverUrl, -1) === "/") { // Remove trailing slash if present
            $serverUrl = substr($serverUrl, 0, strlen($serverUrl) - 1);
        }
        return $serverUrl;
    }

    /**
     * Returns the Authorization and User-Agent headers, merged with any custom headers in the options.
     * Header names are compared case-insensitively. A custom User-Agent replaces the default; a custom
     * Authorization header is dropped, so the auth key is always sent.
     */
    public static function buildHeaders(string $authKey, array $options): array
    {
        $customHeaders = array_filter(
            $options[TranslatorOptions::HEADERS] ?? [],
            function ($name): bool {
                return strcasecmp((string)$name, 'Authorization') !== 0;
            },
            \ARRAY_FILTER_USE_KEY
        );
        return self::mergeHeaders(
            [
                'Authorization' => "DeepL-Auth-Key $authKey",
                'User-Agent' => self::constructUserAgentString(
                    $options[TranslatorOptions::SEND_PLATFORM_INFO] ?? true,
                    $options[TranslatorOptions::APP_INFO] ?? null
                ),
            ],
            $customHeaders
        );
    }

    /**
     * Returns the value of the header with the given name, compared case-insensitively, or null if absent.
     */
    public static function findHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string)$key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Returns the default headers replaced by the given headers, comparing header names case-insensitively.
     */
    public static function mergeHeaders(array $defaults, array $overrides): array
    {
        foreach ($overrides as $name => $value) {
            foreach (array_keys($defaults) as $existing) {
                if (strcasecmp((string)$existing, (string)$name) === 0) {
                    unset($defaults[$existing]);
                }
            }
            $defaults[$name] = $value;
        }
        return $defaults;
    }

    /**
     * Creates an HttpClientWrapper using the transport settings in the options.
     */
    public static function createHttpClient(string $serverUrl, array $headers, array $options): HttpClientWrapper
    {
        return new HttpClientWrapper(
            $serverUrl,
            $headers,
            $options[TranslatorOptions::TIMEOUT] ?? TranslatorOptions::DEFAULT_TIMEOUT,
            $options[TranslatorOptions::MAX_RETRIES] ?? TranslatorOptions::DEFAULT_MAX_RETRIES,
            $options[TranslatorOptions::LOGGER] ?? null,
            $options[TranslatorOptions::PROXY] ?? null,
            $options[TranslatorOptions::HTTP_CLIENT] ?? null
        );
    }

    /**
     * Checks the HTTP status code, and in case of failure, throws an exception with diagnostic information.
     * @throws DeepLException
     */
    public static function checkStatusCode(
        array $response,
        bool $inDocumentDownload = false,
        bool $usingGlossary = false
    ): void {
        list($statusCode, $content) = $response;

        if (200 <= $statusCode && $statusCode < 300) {
            return;
        }

        $message = '';
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            if (isset($json['message'])) {
                $message .= ", message: {$json['message']}";
            }
            if (isset($json['detail'])) {
                $message .= ", detail: {$json['detail']}";
            }
        } catch (\Exception $e) {
            // JSON parsing errors are ignored, and we fall back to the raw response
            $message = ", $content";
        }

        throw self::withStatus(
            self::exceptionForStatus($statusCode, $message, $content, $inDocumentDownload, $usingGlossary),
            $statusCode
        );
    }

    /**
     * Sets the HTTP status code of the exception, and whether a retry could succeed (HTTP 429 or 5xx).
     * @template T of DeepLException
     * @param T $exception
     * @return T
     */
    public static function withStatus(DeepLException $exception, int $statusCode): DeepLException
    {
        $exception->httpStatusCode = $statusCode;
        $exception->shouldRetry = $statusCode === 429 || $statusCode >= 500;
        return $exception;
    }

    private static function exceptionForStatus(
        int $statusCode,
        string $message,
        $content,
        bool $inDocumentDownload,
        bool $usingGlossary
    ): DeepLException {
        switch ($statusCode) {
            case 401:
            case 403:
                return new AuthorizationException("Authorization failure, check authentication key$message");
            case 456:
                return new QuotaExceededException("Quota for this billing period has been exceeded$message");
            case 404:
                if ($usingGlossary) {
                    return new GlossaryNotFoundException("Glossary not found$message");
                }
                return new NotFoundException("Not found$message");
            case 400:
                return new DeepLException("Bad request$message");
            case 429:
                return new TooManyRequestsException(
                    "Too many requests, DeepL servers are currently experiencing high load$message"
                );
            case 503:
                if ($inDocumentDownload) {
                    return new DocumentNotReadyException("Document not ready$message");
                }
                return new DeepLException("Service unavailable$message");
            default:
                return new DeepLException(
                    "Unexpected status code: $statusCode $message, content: $content."
                );
        }
    }

    private static function constructUserAgentString(bool $sendPlatformInfo, ?AppInfo $appInfo): string
    {
        $libraryVersion = Translator::VERSION;
        $libraryInfoStr = "deepl-php/$libraryVersion";
        try {
            if ($sendPlatformInfo) {
                $platformStr = php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('v') . ' ' . php_uname('m');
                $phpVersion = phpversion();
                $libraryInfoStr .= " ($platformStr) php/$phpVersion";
            }
            if (!is_null($appInfo)) {
                $libraryInfoStr .= " $appInfo->appName/$appInfo->appVersion";
            }
        } catch (\Exception $e) {
            // Do not fail request, simply send req with an incomplete user agent string
        }
        return $libraryInfoStr;
    }
}
