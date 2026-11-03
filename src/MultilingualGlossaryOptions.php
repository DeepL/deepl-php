<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for the DeepLClient methods that create or change a multilingual glossary:
 * createMultilingualGlossary(), createMultilingualGlossaryFromCsv(), updateMultilingualGlossary(),
 * updateMultilingualGlossaryName(), updateMultilingualGlossaryDictionary(),
 * updateMultilingualGlossaryDictionaryFromCsv(), replaceMultilingualGlossaryDictionary() and
 * replaceMultilingualGlossaryDictionaryFromCsv(). Every method accepts EXTRA_BODY_PARAMETERS; NAME and
 * DICTIONARIES are accepted by updateMultilingualGlossary() only.
 */
class MultilingualGlossaryOptions
{
    /** New name for the glossary (string). Only for updateMultilingualGlossary(). */
    public const NAME = 'name';

    /**
     * MultilingualGlossaryDictionaryEntries[] to merge into the glossary, like
     * updateMultilingualGlossaryDictionary() does for one. Only for updateMultilingualGlossary(); an empty array
     * sends no dictionaries.
     */
    public const DICTIONARIES = 'dictionaries';

    /**
     * Associative array of additional parameters added to the JSON request body, after the fields
     * set by the library; a key that is already set is replaced. Values are sent as given.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
