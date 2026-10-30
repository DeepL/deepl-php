<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18Client;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Http\Client\ClientInterface;

/**
 * Internal class implementing HTTP requests.
 * @private
 */
class HttpClientWrapper
{
    private $serverUrl;
    private $headers;
    private $maxRetries;
    private $minTimeout;
    private $logger;
    private $proxy;
    private $customHttpClient;
    private $requestFactory;
    /**
     * PSR-18 client that is only used to construct the HTTP request, not to send it.
     */
    private $streamClient;
    private $streamFactory;

    /**
     * @var resource cURL handle, or null if using a custom HTTP client.
     * @see HttpClientWrapper::__construct
     */
    private $curlHandle;

    /**
     * @var bool True if the last cURL request failed before it was sent: a refused connection, a DNS
     * failure, or a timeout before the connection was established.
     */
    private $lastCurlErrorUnsent = false;

    /**
     * @var bool True if the last cURL request failed because the response body was cut off mid-read.
     */
    private $lastCurlErrorBodyCutOff = false;

    /** CURLE_HTTP2_STREAM, which PHP does not define: an HTTP/2 stream was reset, for example mid-body. */
    private const CURLE_HTTP2_STREAM = 92;

    public const OPTION_FILE = 'file';
    /** File name sent for the OPTION_FILE part; defaults to the base name of the file path. */
    public const OPTION_FILENAME = 'filename';
    public const OPTION_HEADERS = 'headers';
    public const OPTION_PARAMS = 'params';
    public const OPTION_OUTFILE = 'outfile';
    public const OPTION_JSON = 'json';
    public const OPTION_RAW_BODY = 'raw_body';
    /**
     * Retry only failures that show the request was not processed: HTTP 429, refused connections, DNS
     * failures and timeouts before the connection was established. For requests that are unsafe to repeat.
     */
    public const OPTION_RETRY_ONLY_UNPROCESSED = 'retry_only_unprocessed';

    public function __construct(
        string           $serverUrl,
        array            $headers,
        float            $timeout,
        int              $maxRetries,
        ?LoggerInterface $logger,
        ?string          $proxy,
        ?ClientInterface $customHttpClient = null
    ) {
        $this->serverUrl = $serverUrl;
        $this->maxRetries = $maxRetries;
        $this->minTimeout = $timeout;
        $this->headers = $headers;
        $this->logger = $logger;
        $this->proxy = $proxy;
        $this->customHttpClient = $customHttpClient;
        $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $this->streamClient = new Psr18Client();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        $this->curlHandle = $customHttpClient === null ? \curl_init() : null;
    }

    public function __destruct()
    {
        if ($this->customHttpClient === null) {
            if (PHP_VERSION_ID < 80000) {
                \curl_close($this->curlHandle);
            }
        }
    }

    /**
     * Makes API request retrying if necessary, and returns (as Promise) response.
     * @param string $method HTTP method, for example 'GET'.
     * @param string $url Path to endpoint excluding base server URL, or an absolute URL of a
     * service outside the DeepL API, for example a pre-signed storage URL.
     * @param array|null $options Array of options, possible arguments are given by OPTIONS_ constants.
     * @return array Status code and content.
     * @throws DeepLException
     */
    public function sendRequestWithBackoff(string $method, string $url, ?array $options = []): array
    {
        if (!self::isAbsoluteUrl($url)) {
            $url = $this->serverUrl . $url;
        }
        $headers = ClientUtils::mergeHeaders(
            $this->headers,
            $options[self::OPTION_HEADERS] ?? []
        );
        $file = $options[self::OPTION_FILE] ?? null;
        $params = $options[self::OPTION_PARAMS] ?? [];
        $this->logInfo("Request to DeepL API $method $url");
        $this->logDebug('Request details: ' . json_encode($params));
        $backoff = new BackoffTimer();
        $response = null;
        $exception = null;
        $onlyUnprocessed = !empty($options[self::OPTION_RETRY_ONLY_UNPROCESSED]);
        while ($backoff->getNumRetries() <= $this->maxRetries) {
            $outFile = null;
            if (isset($options[self::OPTION_OUTFILE])) {
                $outFile = @fopen($options[self::OPTION_OUTFILE], 'w');
                if ($outFile === false) {
                    throw new DeepLException("Failed to open output file {$options[self::OPTION_OUTFILE]} for writing");
                }
            }
            $timeout = max($this->minTimeout, $backoff->getTimeUntilDeadline());
            $response = null;
            $exception = null;
            $json = isset($options[self::OPTION_JSON]) ? $options[self::OPTION_JSON] : null;
            $rawBody = isset($options[self::OPTION_RAW_BODY]) ? $options[self::OPTION_RAW_BODY] : null;
            try {
                $response = $this->sendRequest(
                    $method,
                    $url,
                    $timeout,
                    $headers,
                    $params,
                    $file,
                    $outFile,
                    $json,
                    $rawBody,
                    $onlyUnprocessed,
                    $options[self::OPTION_FILENAME] ?? null
                );
            } catch (ConnectionException $e) {
                $exception = $e;
            } finally {
                if ($outFile) {
                    fclose($outFile);
                }
            }

            $retry = $this->shouldRetry($response, $exception, $onlyUnprocessed);
            if (!$retry || $backoff->getNumRetries() >= $this->maxRetries) {
                break;
            }

            if ($exception !== null) {
                $this->logDebug("Encountered a retryable-error: {$exception->getMessage()}");
            }

            $this->logInfo('Starting retry ' . ($backoff->getNumRetries() + 1) .
                " for request $method $url after sleeping for {$backoff->getTimeUntilDeadline()} seconds.");
            $backoff->sleepUntilDeadline();
        }

        if ($exception !== null) {
            throw $exception;
        } else {
            list($statusCode, $content) = $response;
            $this->logInfo("DeepL API response $method $url $statusCode");
            $this->logDebug("Response details: $content");
            return $response;
        }
    }

    /**
     * Sends a HTTP request. Note that in the case of a custom HTTP client, some of these options are
     * ignored in favor of whatever is set in the client (e.g. timeouts and proxy). If we fall back to cURL,
     * those options are respected.
     * @param string $method HTTP method to use.
     * @param string $url Absolute URL to query.
     * @param float $timeout Time to wait before triggering timeout, in seconds.
     * @param array $headers Array of headers to include in request.
     * @param array $params Array of parameters to include in body.
     * @param string|null $filePath If not null, path to file to upload with request.
     * @param resource|null $outFile If not null, file to write output to.
     * @param string|null $json If not null, JSON content to include in body.
     * @param string|null $rawBody If not null, raw content to include in body, sent as-is.
     * @param bool $freshConnection If true, cURL sends the request on a new connection.
     * @param string|null $fileName File name sent for the uploaded file, or null for the path's base name.
     * @return array Array where the first element is the HTTP status code and the second element is the response body.
     * @throws ConnectionException
     */
    private function sendRequest(
        string $method,
        string $url,
        float $timeout,
        array $headers,
        array $params,
        ?string $filePath,
        $outFile,
        ?string $json,
        ?string $rawBody = null,
        bool $freshConnection = false,
        ?string $fileName = null
    ): array {
        if ($this->customHttpClient !== null) {
            return $this->sendCustomHttpRequest(
                $method,
                $url,
                $headers,
                $params,
                $filePath,
                $outFile,
                $json,
                $rawBody,
                $fileName
            );
        } else {
            return $this->sendCurlRequest(
                $method,
                $url,
                $timeout,
                $headers,
                $params,
                $filePath,
                $outFile,
                $json,
                $rawBody,
                $freshConnection,
                $fileName
            );
        }
    }

    /**
     * Creates a PSR-7 compliant HTTP request with the given arguments.
     * @param string $method HTTP method to use
     * @param string $uri The URI for the request
     * @param array $headers Array of headers for the request
     * @param StreamInterface $body body to be used for the request.
     * @return RequestInterface HTTP request object
     */
    private function createHttpRequest(string $method, string $url, array $headers, StreamInterface $body)
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $header_key => $header_val) {
            $request = $request->withHeader($header_key, $header_val);
        }
        $request = $request->withBody($body);
        return $request;
    }

    /**
     * Sends a HTTP request using the custom HTTP client.
     * @param string $method HTTP method to use.
     * @param string $url Absolute URL to query.
     * @param array $headers Array of headers to include in request.
     * @param array $params Array of parameters to include in body.
     * @param string|null $filePath If not null, path to file to upload with request.
     * @param resource|null $outFile If not null, file to write output to.
     * @param string|null $json If not null, JSON content to include in body.
     * @param string|null $rawBody If not null, raw content to include in body, sent as-is.
     * @param string|null $fileName File name sent for the uploaded file, or null for the path's base name.
     * @return array Array where the first element is the HTTP status code and the second element is the response body.
     * @throws ConnectionException
     */
    private function sendCustomHttpRequest(
        string $method,
        string $url,
        array $headers,
        array $params,
        ?string $filePath,
        $outFile,
        ?string $json,
        ?string $rawBody = null,
        ?string $fileName = null
    ): array {
        $body = null;
        if ($filePath !== null) {
            $builder = new MultipartStreamBuilder($this->streamClient);
            $fileHandle = @fopen($filePath, 'r');
            if ($fileHandle === false) {
                throw new DeepLException("Failed to open input file $filePath for reading");
            }
            $builder->addResource('file', $fileHandle, ['filename' => $fileName ?? basename($filePath)]);
            foreach ($params as $param_name => $value) {
                $builder->addResource($param_name, $value);
            }
            $body = $builder->build();
            $boundary = $builder->getBoundary();
            $headers['Content-Type'] = "multipart/form-data; boundary=\"$boundary\"";
        } elseif (count($params) > 0) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $body = $this->streamFactory->createStream(
                $this->urlEncodeWithRepeatedParams($params)
            );
        } elseif (isset($json)) {
            $headers['Content-Type'] = 'application/json';
            $body = $this->streamFactory->createStream($json);
        } elseif (isset($rawBody)) {
            // The Content-Type of a raw body is specified by the caller
            $body = $this->streamFactory->createStream($rawBody);
        } else {
            $body = $this->streamFactory->createStream('');
        }
        $request = $this->createHttpRequest($method, $url, $headers, $body);
        try {
            $response = $this->customHttpClient->sendRequest($request);
            $response_data = (string) $response->getBody();
            if ($outFile) {
                $written = @fwrite($outFile, $response_data);
                if ($written !== strlen($response_data)) {
                    throw new DeepLException('Failed to write the response to the output file');
                }
            }
            return [$response->getStatusCode(), $response_data];
        } catch (RequestExceptionInterface $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), null, false);
        } catch (ClientExceptionInterface $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), null, true);
        }
    }

    /**
     * Sends a HTTP request using cURL
     * @param string $method HTTP method to use.
     * @param string $url Absolute URL to query.
     * @param float $timeout Time to wait before triggering timeout, in seconds.
     * @param array $headers Array of headers to include in request.
     * @param array $params Array of parameters to include in body.
     * @param string|null $filePath If not null, path to file to upload with request.
     * @param resource|null $outFile If not null, file to write output to.
     * @param string|null $json If not null, JSON content to include in body.
     * @param string|null $rawBody If not null, raw content to include in body, sent as-is.
     * @param bool $freshConnection If true, the request is sent on a new connection.
     * @param string|null $fileName File name sent for the uploaded file, or null for the path's base name.
     * @return array Array where the first element is the HTTP status code and the second element is the response body.
     * @throws ConnectionException
     */
    private function sendCurlRequest(
        string $method,
        string $url,
        float $timeout,
        array $headers,
        array $params,
        ?string $filePath,
        $outFile,
        ?string $json,
        ?string $rawBody = null,
        bool $freshConnection = false,
        ?string $fileName = null
    ): array {
        $curlOptions = [];
        $curlOptions[\CURLOPT_HEADER] = false;

        switch ($method) {
            case "POST":
                $curlOptions[\CURLOPT_POST] = true;
                break;
            case "GET":
                $curlOptions[\CURLOPT_HTTPGET] = true;
                break;
            default:
                $curlOptions[\CURLOPT_CUSTOMREQUEST] = $method;
                break;
        }

        $curlOptions[\CURLOPT_URL] = $url;
        $curlOptions[\CURLOPT_CONNECTTIMEOUT] = $timeout;
        $curlOptions[\CURLOPT_TIMEOUT_MS] = $timeout * 1000;

        if ($this->proxy !== null) {
            $curlOptions[\CURLOPT_PROXY] = $this->proxy;
        }

        // Convert headers from an associative array to an array of "key: value" elements
        $curlOptions[\CURLOPT_HTTPHEADER] = \array_map(function (string $key, string $value): string {
            return "$key: $value";
        }, array_keys($headers), array_values($headers));

        if ($filePath !== null) {
            // If a file is to be uploaded, add it to the list of body parameters
            $params['file'] = \curl_file_create($filePath, null, $fileName ?? basename($filePath));
            $curlOptions[\CURLOPT_POSTFIELDS] = $params;
        } elseif (count($params) > 0) {
            // If there are repeated parameters, passing the parameters directly to cURL will index the repeated
            // parameters which is not what we need, so instead we encode the parameters without indexes.
            // This case only occurs if no file is uploaded.
            $curlOptions[\CURLOPT_POSTFIELDS] = $this->urlEncodeWithRepeatedParams($params);
        } elseif (!is_null($json)) {
            $curlOptions[\CURLOPT_POSTFIELDS] = $json;
            $curlOptions[\CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        } elseif (!is_null($rawBody)) {
            // The Content-Type of a raw body is specified by the caller
            $curlOptions[\CURLOPT_POSTFIELDS] = $rawBody;
        }

        if ($outFile) {
            // Stream response content to specified file
            $curlOptions[\CURLOPT_FILE] = $outFile;
        } else {
            // Return response content as function result
            $curlOptions[\CURLOPT_RETURNTRANSFER] = true;
        }

        if ($freshConnection) {
            // libcurl resends a request silently when a reused keep-alive connection was closed.
            // A request that must not be sent twice therefore never reuses a connection.
            $curlOptions[\CURLOPT_FRESH_CONNECT] = true;
        }

        \curl_reset($this->curlHandle);

        // The next curl calls are unqualified so that we can mock them, see
        // https://github.com/php-mock/php-mock-phpunit#restrictions
        curl_setopt_array($this->curlHandle, $curlOptions);

        $this->lastCurlErrorUnsent = false;
        $this->lastCurlErrorBodyCutOff = false;
        $result = curl_exec($this->curlHandle);
        if ($result !== false) {
            $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
            return [$statusCode, $result];
        } else {
            $errorMessage = curl_error($this->curlHandle);
            $errorCode = curl_errno($this->curlHandle);
            switch ($errorCode) {
                case \CURLE_UNSUPPORTED_PROTOCOL:
                case \CURLE_URL_MALFORMAT:
                case \CURLE_URL_MALFORMAT_USER:
                    $shouldRetry = false;
                    $errorMessage = "Invalid server URL. $errorMessage";
                    break;
                case \CURLE_OPERATION_TIMEOUTED:
                case \CURLE_COULDNT_CONNECT:
                case \CURLE_GOT_NOTHING:
                case \CURLE_SEND_ERROR: // includes broken pipes
                case \CURLE_RECV_ERROR: // includes connection resets
                case \CURLE_COULDNT_RESOLVE_HOST:
                    $shouldRetry = true;
                    break;
                case \CURLE_PARTIAL_FILE: // body shorter than Content-Length, or a chunked body ended early
                case self::CURLE_HTTP2_STREAM: // HTTP/2 stream reset, for example mid-body
                    // Transient, but not retried automatically: the request was processed, and a streamed
                    // body may already be partly written to the output file.
                    $shouldRetry = true;
                    $this->lastCurlErrorBodyCutOff = true;
                    break;
                default:
                    $shouldRetry = false;
                    break;
            }
            $this->lastCurlErrorUnsent = in_array(
                $errorCode,
                [\CURLE_COULDNT_CONNECT, \CURLE_COULDNT_RESOLVE_HOST],
                true
            ) || ($errorCode === \CURLE_OPERATION_TIMEDOUT
                && (float)curl_getinfo($this->curlHandle, CURLINFO_CONNECT_TIME) == 0.0);
            throw new ConnectionException($errorMessage, $errorCode, null, $shouldRetry);
        }
    }

    /**
     * Returns true if the given URL is absolute, and therefore must not be appended to the
     * configured server URL.
     */
    private static function isAbsoluteUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//i', $url) === 1;
    }

    private function shouldRetry(?array $response, ?ConnectionException $exception, bool $onlyUnprocessed): bool
    {
        if ($exception !== null) {
            if ($this->customHttpClient === null && $this->lastCurlErrorBodyCutOff) {
                return false;
            }
            return $exception->shouldRetry && (!$onlyUnprocessed || $this->isUnsentRequestError($exception));
        }
        list($statusCode, ) = $response;

        // Retry on Too-Many-Requests error and internal errors
        return $statusCode === 429 || (!$onlyUnprocessed && $statusCode >= 500);
    }

    /**
     * Returns true if the connection error happened before the request was sent: a refused connection,
     * a DNS failure, or a timeout before the connection was established. Only cURL tells this; errors of
     * a custom HTTP client are treated as possibly sent.
     */
    private function isUnsentRequestError(ConnectionException $exception): bool
    {
        return $this->customHttpClient === null && $this->lastCurlErrorUnsent;
    }

    public function logDebug(string $message): void
    {
        if ($this->logger) {
            $this->logger->debug($message);
        }
    }

    public function logInfo(string $message): void
    {
        if ($this->logger) {
            $this->logger->info($message);
        }
    }

    private static function urlEncodeWithRepeatedParams(?array $params): string
    {
        $params = $params ?? [];
        $fields = [];
        foreach ($params as $key => $value) {
            $name = \urlencode($key);
            if (is_array($value)) {
                $fields[] = implode(
                    '&',
                    array_map(
                        function (string $textElement) use ($name): string {
                            return $name . '=' . \urlencode($textElement);
                        },
                        $value
                    )
                );
            } elseif (is_null($value)) {
                // Parameters with null value are skipped
            } else {
                $fields[] = $name . '=' . \urlencode($value);
            }
        }

        return implode("&", $fields);
    }
}
