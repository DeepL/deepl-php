<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use Exception;
use JsonException;

/**
 * Internal helpers shared by the translation memory classes.
 * @private
 */
class TranslationMemoryUtils
{
    /**
     * Decodes the given JSON content to an associative array.
     * @param string $content JSON content returned by the API.
     * @return array Decoded JSON object.
     * @throws InvalidContentException
     */
    public static function decodeJson(string $content): array
    {
        try {
            return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
    }

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
