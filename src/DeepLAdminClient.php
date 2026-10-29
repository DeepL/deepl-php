<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use DateTimeInterface;
use DateTimeZone;

/**
 * Client for the DeepL Admin API, used to manage developer keys and retrieve usage analytics for your
 * organization. It must be created with an admin API key; admin keys cannot be used for translation.
 */
class DeepLAdminClient
{
    /**
     * Implements all HTTP requests and retries.
     * @var HttpClientWrapper
     */
    private $client;

    /**
     * Construct a DeepLAdminClient using your admin API key.
     * This does not connect to the API, and returns immediately.
     * @param string $adminKey Admin API key as found in your DeepL account.
     * @param array $options Additional options controlling client behaviour, see TranslatorOptions. The
     * server URL defaults to TranslatorOptions::DEFAULT_SERVER_URL for all admin keys.
     * @throws DeepLException If the admin key is empty.
     * @see TranslatorOptions for a list of available request options.
     */
    public function __construct(string $adminKey, array $options = [])
    {
        if (trim($adminKey) === '') {
            throw new DeepLException('adminKey must be a non-empty string');
        }
        // Validation is currently only logging warnings
        $_ = TranslatorOptions::isValid($options);

        $serverUrl = ClientUtils::resolveServerUrl($options, TranslatorOptions::DEFAULT_SERVER_URL);
        $headers = ClientUtils::buildHeaders(trim($adminKey), $options);
        $this->client = ClientUtils::createHttpClient($serverUrl, $headers, $options);
    }

    /**
     * Retrieves usage statistics for the organization within the given date range.
     * Dates are UTC calendar days, and both the start and the end day are included. The end date must be after the
     * start date, and at most 366 days after it.
     * @param DateTimeInterface|string $startDate Start date of the report, as a "YYYY-MM-DD" string such as
     * "2025-09-29", or as a DateTimeInterface, of which the UTC date of that moment is used:
     * 2026-10-20T00:00+02:00 is sent as 2026-10-19. The caller's object is not modified.
     * @param DateTimeInterface|string $endDate End date of the report, in the same format as $startDate.
     * @param string|null $groupBy Group the usage by key or by key and day, see AdminUsageGroupBy. The value
     * is sent to the API as given.
     * @return AdminUsageReport
     * @throws DeepLException If a date is invalid, or if the request fails.
     */
    public function getUsageAnalytics($startDate, $endDate, ?string $groupBy = null): AdminUsageReport
    {
        $params = self::dateRangeParams($startDate, $endDate);
        if ($groupBy !== null) {
            $params['group_by'] = $groupBy;
        }
        $content = $this->sendRequest('GET', '/v2/admin/analytics?' . http_build_query($params));
        return self::parse($content, function (array $json): AdminUsageReport {
            self::requireObject($json, 'Usage report');
            return AdminUsageReport::fromJson($json);
        });
    }

    /**
     * Retrieves usage statistics per custom tag within the given date range. Results are paginated; pass the
     * nextPage value of a report as $page to retrieve the following page.
     * Dates are UTC calendar days, and both the start and the end day are included. The end date must be after
     * the start date, and at most 366 days after it.
     * @param DateTimeInterface|string $startDate Start date of the report, as a "YYYY-MM-DD" string such as
     * "2025-09-29", or as a DateTimeInterface, of which the UTC date of that moment is used:
     * 2026-10-20T00:00+02:00 is sent as 2026-10-19. The caller's object is not modified.
     * @param DateTimeInterface|string $endDate End date of the report, in the same format as $startDate.
     * @param string|null $aggregateBy Aggregate the usage over the whole period or by day, see
     * CustomTagUsageAggregateBy. The value is sent to the API as given.
     * @param int|null $page Page number to retrieve.
     * @return CustomTagUsageReport
     * @throws DeepLException If a date is invalid, or if the request fails.
     */
    public function getCustomTagUsageAnalytics(
        $startDate,
        $endDate,
        ?string $aggregateBy = null,
        ?int $page = null
    ): CustomTagUsageReport {
        $params = self::dateRangeParams($startDate, $endDate);
        if ($aggregateBy !== null) {
            $params['aggregate_by'] = $aggregateBy;
        }
        if ($page !== null) {
            $params['page'] = (string)$page;
        }
        $content = $this->sendRequest('GET', '/v2/admin/analytics/custom-tags?' . http_build_query($params));
        return self::parse($content, function (array $json): CustomTagUsageReport {
            self::requireObject($json, 'Custom tag usage report');
            return CustomTagUsageReport::fromJson($json);
        });
    }

    /**
     * Creates a developer key in the organization. To avoid creating duplicate keys, the request is only
     * retried if it was not processed: after HTTP 429, or a connection failure before the request was sent.
     * With a custom HTTP client, only HTTP 429 is retried.
     * @param array $options Request options, see CreateDeveloperKeyOptions: LABEL and EXTRA_BODY_PARAMETERS.
     * @return DeveloperKey The created key.
     * @throws DeepLException
     * @see CreateDeveloperKeyOptions
     */
    public function createDeveloperKey(array $options = []): DeveloperKey
    {
        self::checkOptions($options, [CreateDeveloperKeyOptions::LABEL]);
        $params = [];
        if (isset($options[CreateDeveloperKeyOptions::LABEL])) {
            $params['label'] = (string)$options[CreateDeveloperKeyOptions::LABEL];
        }
        return $this->sendDeveloperKeyRequest(
            'POST',
            '/v2/admin/developer-keys',
            $params,
            $options,
            [HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true]
        );
    }

    /**
     * Retrieves all developer keys in the organization, including deactivated ones.
     * @return DeveloperKey[]
     * @throws DeepLException
     */
    public function listDeveloperKeys(): array
    {
        $content = $this->sendRequest('GET', '/v2/admin/developer-keys');
        return self::parse($content, function (array $json): array {
            if ($json !== [] && !self::isList($json)) {
                throw new DeepLException('Developer keys response is not a list');
            }
            $withKeyId = array_filter($json, function ($entry): bool {
                return is_array($entry) && self::hasKeyId($entry);
            });
            return ParsingUtils::mapObjects($withKeyId, [DeveloperKey::class, 'fromJson']);
        });
    }

    /**
     * Deactivates a developer key. Deactivation is permanent.
     * @param string|DeveloperKey $key Key ID or DeveloperKey to deactivate.
     * @param array $options Request options, see DeveloperKeyOptions: only EXTRA_BODY_PARAMETERS.
     * @return DeveloperKey The deactivated key.
     * @throws DeepLException
     */
    public function deactivateDeveloperKey($key, array $options = []): DeveloperKey
    {
        self::checkOptions($options, []);
        return $this->sendDeveloperKeyRequest(
            'PUT',
            '/v2/admin/developer-keys/deactivate',
            ['key_id' => self::keyId($key)],
            $options
        );
    }

    /**
     * Changes the label of a developer key.
     * @param string|DeveloperKey $key Key ID or DeveloperKey to rename.
     * @param string $label New label for the key.
     * @param array $options Request options, see DeveloperKeyOptions: only EXTRA_BODY_PARAMETERS.
     * @return DeveloperKey The renamed key.
     * @throws DeepLException
     */
    public function renameDeveloperKey($key, string $label, array $options = []): DeveloperKey
    {
        self::checkOptions($options, []);
        $keyId = self::keyId($key);
        return $this->sendDeveloperKeyRequest(
            'PUT',
            '/v2/admin/developer-keys/label',
            ['key_id' => $keyId, 'label' => $label],
            $options
        );
    }

    /**
     * Changes the monthly usage limits of a developer key. Only the limits given in $limits are changed;
     * omitted limits keep their current value. A limit of null means unlimited and 0 blocks that usage.
     * @param string|DeveloperKey $key Key ID or DeveloperKey to modify.
     * @param array $limits Usage limits to change, keyed by the DeveloperKeyUsageLimitsOptions constants. Each
     * value is an int or null. The limits are sent to the API as given.
     * @param array $options Request options, see DeveloperKeyOptions: only EXTRA_BODY_PARAMETERS.
     * @return DeveloperKey The modified key.
     * @throws DeepLException
     * @see DeveloperKeyUsageLimitsOptions
     */
    public function setDeveloperKeyUsageLimits($key, array $limits, array $options = []): DeveloperKey
    {
        self::checkOptions($options, []);
        $keyId = self::keyId($key);
        return $this->sendDeveloperKeyRequest(
            'PUT',
            '/v2/admin/developer-keys/limits',
            ['key_id' => $keyId] + $limits,
            $options
        );
    }

    /**
     * Sends a JSON body of $params with the extra body parameters of $options added last. An empty body
     * encodes as {}.
     * @throws DeepLException
     */
    private function sendDeveloperKeyRequest(
        string $method,
        string $url,
        array $params,
        array $options,
        array $requestOptions = []
    ): DeveloperKey {
        foreach ($options[DeveloperKeyOptions::EXTRA_BODY_PARAMETERS] ?? [] as $key => $value) {
            $params[$key] = $value;
        }
        $requestOptions[HttpClientWrapper::OPTION_JSON] = json_encode((object)$params);
        $content = $this->sendRequest($method, $url, $requestOptions);
        return self::parse($content, function (array $json): DeveloperKey {
            if (!self::hasKeyId($json)) {
                throw new DeepLException('Developer key response is missing key_id');
            }
            return DeveloperKey::fromJson($json);
        });
    }

    /**
     * @param string[] $allowed Option keys the method accepts besides EXTRA_BODY_PARAMETERS.
     * @throws DeepLException If an option key is unknown to the method.
     */
    private static function checkOptions(array $options, array $allowed): void
    {
        $allowed[] = DeveloperKeyOptions::EXTRA_BODY_PARAMETERS;
        foreach (array_keys($options) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new DeepLException("Unknown developer key option: $key");
            }
        }
    }

    /**
     * @throws DeepLException
     */
    private function sendRequest(string $method, string $url, array $options = []): string
    {
        $response = $this->client->sendRequestWithBackoff($method, $url, $options);
        ClientUtils::checkStatusCode($response);
        list(, $content) = $response;
        return $content;
    }

    /**
     * @return mixed The result of $fromJson.
     * @throws DeepLException
     */
    private static function parse(string $content, callable $fromJson)
    {
        $json = ParsingUtils::decodeJson($content);
        try {
            return $fromJson($json);
        } catch (\TypeError $error) {
            throw ParsingUtils::invalidContent('Unexpected response content: ' . $error->getMessage(), $error);
        }
    }

    /**
     * @param DateTimeInterface|string $startDate
     * @param DateTimeInterface|string $endDate
     * @throws DeepLException
     */
    private static function dateRangeParams($startDate, $endDate): array
    {
        $start = self::formatDate($startDate, 'startDate');
        $end = self::formatDate($endDate, 'endDate');
        return ['start_date' => $start, 'end_date' => $end];
    }

    /**
     * @return DateTime|false
     */
    private static function parseDate(string $date)
    {
        return DateTime::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
    }

    /**
     * @param string|DeveloperKey $key
     * @throws DeepLException
     */
    private static function keyId($key): string
    {
        $keyId = $key instanceof DeveloperKey ? $key->keyId : $key;
        if (!is_string($keyId) || $keyId === '') {
            throw new DeepLException('key must be a non-empty key ID or a DeveloperKey');
        }
        return $keyId;
    }

    private static function hasKeyId(array $json): bool
    {
        return is_string($json['key_id'] ?? null) && $json['key_id'] !== '';
    }

    private static function isList(array $json): bool
    {
        return array_keys($json) === range(0, count($json) - 1);
    }

    /**
     * @throws DeepLException
     */
    private static function requireObject(array $json, string $what): void
    {
        if ($json !== [] && self::isList($json)) {
            throw new DeepLException("$what response is not a JSON object");
        }
    }

    /**
     * @param DateTimeInterface|string $date
     * @throws DeepLException
     */
    private static function formatDate($date, string $name): string
    {
        if ($date instanceof DateTimeInterface) {
            return (new DateTime('@' . $date->getTimestamp()))->format('Y-m-d');
        }
        if (is_string($date)) {
            $parsed = self::parseDate($date);
            if ($parsed !== false && $parsed->format('Y-m-d') === $date) {
                return $date;
            }
            $actual = "\"$date\"";
        } else {
            $actual = gettype($date);
        }
        throw new DeepLException(
            "$name must be a DateTimeInterface or a date string in the format YYYY-MM-DD, got $actual"
        );
    }
}
