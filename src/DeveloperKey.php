<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use DateTimeZone;

/**
 * Information about a developer API key, as returned by the Admin API.
 */
class DeveloperKey
{
    /** @var string Unique ID of the key. */
    public $keyId;

    /** @var string Label assigned to the key. */
    public $label;

    /** @var DateTime|null Timestamp when the key was created. */
    public $creationTime;

    /** @var DateTime|null Timestamp when the key was deactivated, or null if the key is active. */
    public $deactivatedTime;

    /** @var bool True if the key is deactivated. */
    public $isDeactivated;

    /** @var DeveloperKeyUsageLimits Usage limits configured for the key. */
    public $usageLimits;

    public function __construct(
        string $keyId,
        string $label,
        ?DateTime $creationTime,
        ?DateTime $deactivatedTime,
        bool $isDeactivated,
        DeveloperKeyUsageLimits $usageLimits
    ) {
        $this->keyId = $keyId;
        $this->label = $label;
        $this->creationTime = $creationTime;
        $this->deactivatedTime = $deactivatedTime;
        $this->isDeactivated = $isDeactivated;
        $this->usageLimits = $usageLimits;
    }

    public static function fromJson(array $json): DeveloperKey
    {
        return new DeveloperKey(
            is_string($json['key_id'] ?? null) ? $json['key_id'] : '',
            is_string($json['label'] ?? null) ? $json['label'] : '',
            ParsingUtils::parseOptionalTimestamp($json['creation_time'] ?? null, new DateTimeZone('UTC')),
            ParsingUtils::parseOptionalTimestamp($json['deactivated_time'] ?? null, new DateTimeZone('UTC')),
            ($json['is_deactivated'] ?? null) === true,
            DeveloperKeyUsageLimits::fromJson(ParsingUtils::objectOrNull($json['usage_limits'] ?? null))
        );
    }

    public function __toString(): string
    {
        return "DeveloperKey \"{$this->label}\" ({$this->keyId})";
    }
}
