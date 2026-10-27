<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use DateTimeZone;

/**
 * Admin usage analytics report for a date range.
 */
class AdminUsageReport
{
    /** @var AdminUsageBreakdown Usage totals over the whole date range. */
    public $totalUsage;

    /** @var DateTime|null Start of the report period. */
    public $startDate;

    /** @var DateTime|null End of the report period. */
    public $endDate;

    /** @var string|null Grouping used for the report, as returned by the API, for example "keyAndDay". */
    public $groupBy;

    /** @var KeyUsage[] Usage per key, empty unless grouped by key. */
    public $keyUsages;

    /** @var KeyUsage[] Usage per key and day, empty unless grouped by key and day. */
    public $keyAndDayUsages;

    public function __construct(
        AdminUsageBreakdown $totalUsage,
        ?DateTime $startDate,
        ?DateTime $endDate,
        ?string $groupBy = null,
        array $keyUsages = [],
        array $keyAndDayUsages = []
    ) {
        $this->totalUsage = $totalUsage;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->groupBy = $groupBy;
        $this->keyUsages = $keyUsages;
        $this->keyAndDayUsages = $keyAndDayUsages;
    }

    public static function fromJson(array $json): AdminUsageReport
    {
        $report = is_array($json['usage_report'] ?? null) ? $json['usage_report'] : [];
        return new AdminUsageReport(
            AdminUsageBreakdown::fromJson(ParsingUtils::objectOrNull($report['total_usage'] ?? null)),
            ParsingUtils::parseOptionalTimestamp($report['start_date'] ?? null, new DateTimeZone('UTC')),
            ParsingUtils::parseOptionalTimestamp($report['end_date'] ?? null, new DateTimeZone('UTC')),
            is_string($report['group_by'] ?? null) ? $report['group_by'] : null,
            ParsingUtils::mapObjects($report['key_usages'] ?? null, [KeyUsage::class, 'fromJson']),
            ParsingUtils::mapObjects($report['key_and_day_usages'] ?? null, [KeyUsage::class, 'fromJson'])
        );
    }
}
