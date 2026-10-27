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
     * Returns the Authorization and User-Agent headers, overridden by any custom headers in the options.
     */
    public static function buildHeaders(string $authKey, array $options): array
    {
        return array_replace(
            [
                'Authorization' => "DeepL-Auth-Key $authKey",
                'User-Agent' => self::constructUserAgentString(
                    $options[TranslatorOptions::SEND_PLATFORM_INFO] ?? true,
                    $options[TranslatorOptions::APP_INFO] ?? null
                ),
            ],
            $options[TranslatorOptions::HEADERS] ?? []
        );
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

        if (200 <= $statusCode && $statusCode < 400) {
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

        switch ($statusCode) {
            case 403:
                throw new AuthorizationException("Authorization failure, check authentication key$message");
            case 456:
                throw new QuotaExceededException("Quota for this billing period has been exceeded$message");
            case 404:
                if ($usingGlossary) {
                    throw new GlossaryNotFoundException("Glossary not found$message");
                }
                throw new NotFoundException("Not found$message");
            case 400:
                throw new DeepLException("Bad request$message");
            case 429:
                throw new TooManyRequestsException(
                    "Too many requests, DeepL servers are currently experiencing high load$message"
                );
            case 503:
                if ($inDocumentDownload) {
                    throw new DocumentNotReadyException("Document not ready$message");
                } else {
                    throw new DeepLException("Service unavailable$message");
                }
                break; // break required by phpcs although it is unnecessary
            default:
                throw new DeepLException(
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
                $platformStr = php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('v') . php_uname('m');
                $phpVersion = phpversion();
                $libraryInfoStr .= " ($platformStr) php/$phpVersion";
                $curlVer = curl_version()['version'];
                $libraryInfoStr .= " curl/$curlVer";
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
