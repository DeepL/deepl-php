<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use DateTimeZone;

/**
 * Usage of a single API key in an admin usage analytics report.
 */
class KeyUsage
{
    /** @var string Masked API key. */
    public $apiKey;

    /** @var string|null Label of the API key, or null if the response does not include it. */
    public $apiKeyLabel;

    /** @var AdminUsageBreakdown Usage of the API key. */
    public $usage;

    /** @var DateTime|null Day of the usage, present when grouped by key and day. */
    public $usageDate;

    public function __construct(
        string $apiKey,
        ?string $apiKeyLabel,
        AdminUsageBreakdown $usage,
        ?DateTime $usageDate = null
    ) {
        $this->apiKey = $apiKey;
        $this->apiKeyLabel = $apiKeyLabel;
        $this->usage = $usage;
        $this->usageDate = $usageDate;
    }

    public static function fromJson(array $json): KeyUsage
    {
        return new KeyUsage(
            is_string($json['api_key'] ?? null) ? $json['api_key'] : '',
            is_string($json['api_key_label'] ?? null) ? $json['api_key_label'] : null,
            AdminUsageBreakdown::fromJson(ParsingUtils::objectOrNull($json['usage'] ?? null)),
            ParsingUtils::parseOptionalTimestamp($json['usage_date'] ?? null, new DateTimeZone('UTC'))
        );
    }
}
