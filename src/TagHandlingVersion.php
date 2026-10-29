<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values for the tag_handling_version option of text translation, and of TextResult::$tagHandlingVersion.
 * The option also accepts other strings, and the result keeps any string the API returns.
 * @see TranslateTextOptions::TAG_HANDLING_VERSION
 */
class TagHandlingVersion
{
    /** The original tag handling algorithm. */
    public const V1 = 'v1';

    /** The improved tag handling algorithm. */
    public const V2 = 'v2';
}
