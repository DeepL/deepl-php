<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values for the tone option of DeepLClient::rephraseText(). The option also accepts other strings.
 * @see RephraseTextOptions::TONE
 */
class WritingTone
{
    /** Use the default tone. */
    public const DEFAULT = 'default';

    /** Enthusiastic tone. */
    public const ENTHUSIASTIC = 'enthusiastic';

    /** Friendly tone. */
    public const FRIENDLY = 'friendly';

    /** Confident tone. */
    public const CONFIDENT = 'confident';

    /** Diplomatic tone. */
    public const DIPLOMATIC = 'diplomatic';

    /** Use enthusiastic tone if available for the target language, otherwise the default. */
    public const PREFER_ENTHUSIASTIC = 'prefer_enthusiastic';

    /** Use friendly tone if available for the target language, otherwise the default. */
    public const PREFER_FRIENDLY = 'prefer_friendly';

    /** Use confident tone if available for the target language, otherwise the default. */
    public const PREFER_CONFIDENT = 'prefer_confident';

    /** Use diplomatic tone if available for the target language, otherwise the default. */
    public const PREFER_DIPLOMATIC = 'prefer_diplomatic';
}
