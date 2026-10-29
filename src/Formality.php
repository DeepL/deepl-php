<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values for the formality option of text and document translation. The option also accepts other strings.
 * @see TranslateTextOptions::FORMALITY
 */
class Formality
{
    /** Use the default formality. */
    public const DEFAULT = 'default';

    /** Use informal language. */
    public const LESS = 'less';

    /** Use formal, more polite language. */
    public const MORE = 'more';

    /** Use informal language if available for the target language, otherwise the default. */
    public const PREFER_LESS = 'prefer_less';

    /** Use formal, more polite language if available for the target language, otherwise the default. */
    public const PREFER_MORE = 'prefer_more';
}
