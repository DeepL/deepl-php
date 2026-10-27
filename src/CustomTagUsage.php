<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use DateTimeZone;

/**
 * Usage of a single custom tag.
 */
class CustomTagUsage
{
    /** @var string The custom tag. */
    public $customTag;

    /** @var CustomTagUsageBreakdown Usage of the custom tag. */
    public $breakdown;

    /** @var DateTime|null Day of the usage, present when aggregated by day. */
    public $usageDate;

    public function __construct(string $customTag, CustomTagUsageBreakdown $breakdown, ?DateTime $usageDate = null)
    {
        $this->customTag = $customTag;
        $this->breakdown = $breakdown;
        $this->usageDate = $usageDate;
    }

    public static function fromJson(array $json): CustomTagUsage
    {
        return new CustomTagUsage(
            is_string($json['custom_tag'] ?? null) ? $json['custom_tag'] : '',
            CustomTagUsageBreakdown::fromJson(ParsingUtils::objectOrNull($json['breakdown'] ?? null)),
            ParsingUtils::parseOptionalTimestamp($json['usage_date'] ?? null, new DateTimeZone('UTC'))
        );
    }
}
