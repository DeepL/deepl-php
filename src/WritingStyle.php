<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values for the writing_style option of DeepLClient::rephraseText(). The option also accepts other strings.
 * @see RephraseTextOptions::WRITING_STYLE
 */
class WritingStyle
{
    /** Use the default writing style. */
    public const DEFAULT = 'default';

    /** Simple writing style. */
    public const SIMPLE = 'simple';

    /** Business writing style. */
    public const BUSINESS = 'business';

    /** Academic writing style. */
    public const ACADEMIC = 'academic';

    /** Casual writing style. */
    public const CASUAL = 'casual';

    /** Use simple style if available for the target language, otherwise the default. */
    public const PREFER_SIMPLE = 'prefer_simple';

    /** Use business style if available for the target language, otherwise the default. */
    public const PREFER_BUSINESS = 'prefer_business';

    /** Use academic style if available for the target language, otherwise the default. */
    public const PREFER_ACADEMIC = 'prefer_academic';

    /** Use casual style if available for the target language, otherwise the default. */
    public const PREFER_CASUAL = 'prefer_casual';
}
