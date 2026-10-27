<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Options for grouping the admin usage analytics report.
 */
class AdminUsageGroupBy
{
    /** Group usage by API key. */
    public const KEY = 'key';

    /** Group usage by API key and usage date. */
    public const KEY_AND_DAY = 'key_and_day';
}
