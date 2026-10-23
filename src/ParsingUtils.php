<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use Exception;

/**
 * Internal helpers for parsing API responses.
 * @private
 */
class ParsingUtils
{
    /**
     * Converts an optional API timestamp to a DateTime.
     * @param string|null $timestamp Timestamp as returned by the API, or null if it was absent.
     * @return DateTime|null The parsed timestamp, or null if it was absent or unparseable.
     */
    public static function parseOptionalTimestamp(?string $timestamp): ?DateTime
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }
        try {
            return new DateTime($timestamp);
        } catch (Exception $exception) {
            return null;
        }
    }
}
