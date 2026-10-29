<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLClient::updateMultilingualGlossary(): the keys of MultilingualGlossaryOptions plus
 * NAME and DICTIONARIES. Options that are not set, or null, are not sent; without any, the body is {}.
 */
class UpdateMultilingualGlossaryOptions extends MultilingualGlossaryOptions
{
    /** New name of the glossary. */
    public const NAME = 'name';

    /**
     * MultilingualGlossaryDictionaryEntries[] to merge into the glossary: entries are added to the
     * dictionary of their language pair, which is created if the glossary has none.
     */
    public const DICTIONARIES = 'dictionaries';
}
