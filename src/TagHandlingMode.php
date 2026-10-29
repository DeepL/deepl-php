<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values for the tag_handling option of text translation. The option also accepts other strings.
 * @see TranslateTextOptions::TAG_HANDLING
 */
class TagHandlingMode
{
    /** Parse the input as HTML. */
    public const HTML = 'html';

    /** Parse the input as XML. */
    public const XML = 'xml';
}
