<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values for the split_sentences option of text translation. The option also accepts other strings.
 * @see TranslateTextOptions::SPLIT_SENTENCES
 */
class SentenceSplittingMode
{
    /** Split sentences using both newlines and punctuation; the API default. */
    public const ALL = '1';

    /** Do not split sentences. */
    public const OFF = '0';

    /** Split sentences using punctuation but not newlines. */
    public const NO_NEWLINES = 'nonewlines';
}
