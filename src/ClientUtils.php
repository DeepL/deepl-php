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
     * Returns the server URL from the given options, or the default if unspecified, without a trailing slash.
     * @throws \InvalidArgumentException If the server URL is not an absolute http or https URL with a host.
     */
    public static function resolveServerUrl(array $options, string $defaultServerUrl): string
    {
        $serverUrl = $options[TranslatorOptions::SERVER_URL] ?? $defaultServerUrl;
        $parts = is_string($serverUrl) ? parse_url($serverUrl) : false;
        $isUsable = is_array($parts) && ($parts['host'] ?? '') !== ''
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true);
        if (!$isUsable) {
            throw new \InvalidArgumentException('If specified, ' . TranslatorOptions::SERVER_URL .
                ' option must be an absolute http or https URL with a host.');
        }
        if (substr($serverUrl, -1) === "/") { // Remove trailing slash if present
            $serverUrl = substr($serverUrl, 0, strlen($serverUrl) - 1);
        }
        return $serverUrl;
    }

    /**
     * Returns the JSON encoding of a request body.
     * @param mixed $value
     * @throws \InvalidArgumentException If the value cannot be JSON-encoded, for example invalid UTF-8 or INF/NAN.
     */
    public static function encodeJson($value): string
    {
        try {
            return json_encode($value, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Failed to JSON-encode the request body: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Header names describing the request the library builds or the response it parses; custom headers with
     * these names are dropped so the library's values are always used.
     */
    private const TECHNICAL_HEADERS = ['content-type', 'content-length', 'transfer-encoding', 'host'];

    /**
     * Returns the Authorization and User-Agent headers, merged with any custom headers in the options.
     * Header names are compared case-insensitively. A custom header replaces the default one of the same name,
     * including Authorization and User-Agent. Custom Content-Type, Content-Length, Transfer-Encoding and Host
     * headers are dropped.
     */
    public static function buildHeaders(string $authKey, array $options): array
    {
        $customHeaders = array_filter(
            $options[TranslatorOptions::HEADERS] ?? [],
            function ($name): bool {
                return !in_array(strtolower((string)$name), self::TECHNICAL_HEADERS, true);
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
     * Returns the URL-encoded query string of the parameters, with a space encoded as %20 (RFC 3986):
     * http_build_query() by default writes "+", which is meant for form bodies.
     */
    public static function buildQuery(array $params): string
    {
        return http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
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
     * @throws \InvalidArgumentException If max_retries is negative or cURL cannot use the proxy.
     */
    public static function createHttpClient(string $serverUrl, array $headers, array $options): HttpClientWrapper
    {
        $maxRetries = $options[TranslatorOptions::MAX_RETRIES] ?? TranslatorOptions::DEFAULT_MAX_RETRIES;
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException(
                'If specified, ' . TranslatorOptions::MAX_RETRIES . ' option must not be negative.'
            );
        }
        $proxy = $options[TranslatorOptions::PROXY] ?? null;
        if ($proxy !== null && !self::isUsableProxy($proxy)) {
            throw new \InvalidArgumentException(
                'If specified, ' . TranslatorOptions::PROXY . ' option must be a proxy URL with a host and an ' .
                'http, https, socks4, socks4a, socks5 or socks5h scheme, or a host[:port] used as http.'
            );
        }
        return new HttpClientWrapper(
            $serverUrl,
            $headers,
            $options[TranslatorOptions::TIMEOUT] ?? TranslatorOptions::DEFAULT_TIMEOUT,
            $maxRetries,
            $options[TranslatorOptions::LOGGER] ?? null,
            $proxy,
            $options[TranslatorOptions::HTTP_CLIENT] ?? null
        );
    }

    private const PROXY_SCHEMES = ['http', 'https', 'socks4', 'socks4a', 'socks5', 'socks5h'];

    /**
     * True if cURL can use the proxy: a URL with a host and a scheme cURL supports, or, as cURL accepts,
     * a host[:port] without a scheme, which cURL uses as http.
     * @param mixed $proxy
     */
    private static function isUsableProxy($proxy): bool
    {
        if (!is_string($proxy) || $proxy === '' || preg_match('/\s/', $proxy)) {
            return false;
        }
        $parts = parse_url(strpos($proxy, '://') === false ? "http://$proxy" : $proxy);
        return $parts !== false && ($parts['host'] ?? '') !== ''
            && in_array(strtolower($parts['scheme'] ?? ''), self::PROXY_SCHEMES, true);
    }

    /**
     * Checks the HTTP status code, and in case of failure, throws an exception with diagnostic information.
     * @throws DeepLException
     */
    public static function checkStatusCode(array $response, bool $inDocumentDownload = false): void
    {
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
            self::exceptionForStatus($statusCode, $message, $content, $inDocumentDownload),
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
        bool $inDocumentDownload
    ): DeepLException {
        switch ($statusCode) {
            case 401:
            case 403:
                return new AuthorizationException("Authorization failure, check authentication key$message");
            case 456:
                return new QuotaExceededException("Quota for this billing period has been exceeded$message");
            case 404:
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
        $libraryVersion = DeepLClient::VERSION;
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
