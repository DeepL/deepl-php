<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for the multilingual glossary methods of DeepLClient that send a request body:
 * createMultilingualGlossary(), createMultilingualGlossaryFromCsv(), replaceMultilingualGlossaryDictionary(),
 * replaceMultilingualGlossaryDictionaryFromCsv(), updateMultilingualGlossaryName(),
 * updateMultilingualGlossaryDictionary() and updateMultilingualGlossaryDictionaryFromCsv().
 */
class MultilingualGlossaryOptions
{
    /**
     * Associative array of additional parameters added to the JSON request body, after the fields
     * set by the library; a key that is already set is replaced. Values are sent as given.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
