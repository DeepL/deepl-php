<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use DateTimeZone;

/**
 * Admin custom tag usage analytics report for a date range.
 */
class CustomTagUsageReport
{
    /** @var string|null Aggregation used for the report, as returned by the API, for example "day". */
    public $aggregateBy;

    /** @var DateTime|null Start of the report period. */
    public $startDate;

    /** @var DateTime|null End of the report period. */
    public $endDate;

    /** @var int|null Page number to request the next page, or null if this is the last page. */
    public $nextPage;

    /** @var CustomTagUsage[] Usage entries per custom tag. */
    public $usage;

    public function __construct(
        ?string $aggregateBy,
        ?DateTime $startDate,
        ?DateTime $endDate,
        ?int $nextPage,
        array $usage
    ) {
        $this->aggregateBy = $aggregateBy;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->nextPage = $nextPage;
        $this->usage = $usage;
    }

    public static function fromJson(array $json): CustomTagUsageReport
    {
        $report = is_array($json['custom_tag_usage_report'] ?? null) ? $json['custom_tag_usage_report'] : [];
        return new CustomTagUsageReport(
            is_string($report['aggregate_by'] ?? null) ? $report['aggregate_by'] : null,
            ParsingUtils::parseOptionalTimestamp($report['start_date'] ?? null, new DateTimeZone('UTC')),
            ParsingUtils::parseOptionalTimestamp($report['end_date'] ?? null, new DateTimeZone('UTC')),
            is_numeric($report['next_page'] ?? null) ? (int)$report['next_page'] : null,
            ParsingUtils::mapObjects($report['usage'] ?? null, [CustomTagUsage::class, 'fromJson'])
        );
    }
}
