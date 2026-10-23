<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

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
}
