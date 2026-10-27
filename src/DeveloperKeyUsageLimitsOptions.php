<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Keys of the usage limits array passed to DeepLAdminClient::setDeveloperKeyUsageLimits(). Only the limits
 * present in the array are changed; omitted limits keep their current value. A value of null removes the
 * limit (unlimited usage) and 0 blocks that usage.
 * @see DeepLAdminClient::setDeveloperKeyUsageLimits()
 */
class DeveloperKeyUsageLimitsOptions
{
    /**
     * Maximum number of characters per usage period across text translation, document translation and text
     * improvement.
     */
    public const CHARACTERS = 'characters';

    /** Maximum number of speech-to-text milliseconds per usage period. */
    public const SPEECH_TO_TEXT_MILLISECONDS = 'speech_to_text_milliseconds';

    /** Maximum number of speech-to-speech milliseconds per usage period. */
    public const SPEECH_TO_SPEECH_MILLISECONDS = 'speech_to_speech_milliseconds';
}
