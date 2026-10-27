<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Options for aggregating the custom tag usage analytics report.
 */
class CustomTagUsageAggregateBy
{
    /** Aggregate usage over the entire date range. */
    public const PERIOD = 'period';

    /** Aggregate usage by individual day. */
    public const DAY = 'day';
}
