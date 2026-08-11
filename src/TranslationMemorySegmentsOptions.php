<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Options that can be specified when listing the segments of a translation memory.
 * @see DeepLClient::listTranslationMemorySegments()
 */
class TranslationMemorySegmentsOptions
{
    /** Maximum number of segments per page, between 1 and 100, defaults to 50. */
    public const PAGE_SIZE = 'page_size';

    /** Cursor from a previous response, omit on the first call. */
    public const PAGE_CURSOR = 'page_cursor';

    /** Substring filter matched against source and target text, at least 2 characters. */
    public const FILTER_TEXT = 'filter_text';

    /** Set to true to make the filter text case-sensitive, default is false. */
    public const FILTER_CASE_SENSITIVE = 'filter_case_sensitive';
}
