<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Helper functions for language codes. To retrieve the currently supported languages, use the languages API.
 * @see DeepLClient::getLanguagesForResource()
 */
class LanguageCode
{
    /**
     * Changes the upper- and lower-casing of the given language code to match ISO 639-1 with an optional regional code
     * from ISO 3166-1.
     * @param string $langCode String containing language code to standardize.
     * @return string String containing the standardized language code.
     * @throws \InvalidArgumentException If language code is an empty string.
     */
    public static function standardizeLanguageCode(string $langCode): string
    {
        if (strlen($langCode) === 0) {
            throw new \InvalidArgumentException('langCode must be a non-empty string');
        }

        $exploded = explode('-', $langCode, 2);

        if (isset($exploded[1])) {
            return strtolower($exploded[0]) . '-' . strtoupper($exploded[1]);
        } else {
            return strtolower($exploded[0]);
        }
    }

    /**
     * Removes the regional variant (if any) from a language code, for example inputs 'en' and 'en-US' both return 'en'.
     * @param string $langCode String containing language code to convert.
     * @return string String containing language code without a regional variant.
     * @throws DeepLException
     */
    public static function removeRegionalVariant(string $langCode): string
    {
        if (strlen($langCode) === 0) {
            throw new \InvalidArgumentException('langCode must be a non-empty string');
        }

        $exploded = explode('-', $langCode, 2);
        return strtolower($exploded[0]);
    }
}
