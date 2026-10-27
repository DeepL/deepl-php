<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Usage limits of a developer key. A value of null means unlimited.
 */
class DeveloperKeyUsageLimits
{
    /** @var int|null Maximum number of characters per usage period, or null if unlimited. */
    public $characters;

    /** @var int|null Maximum number of speech-to-text milliseconds per usage period, or null if unlimited. */
    public $speechToTextMilliseconds;

    /** @var int|null Maximum number of speech-to-speech milliseconds per usage period, or null if unlimited. */
    public $speechToSpeechMilliseconds;

    public function __construct(
        ?int $characters = null,
        ?int $speechToTextMilliseconds = null,
        ?int $speechToSpeechMilliseconds = null
    ) {
        $this->characters = $characters;
        $this->speechToTextMilliseconds = $speechToTextMilliseconds;
        $this->speechToSpeechMilliseconds = $speechToSpeechMilliseconds;
    }

    public static function fromJson(?array $json): DeveloperKeyUsageLimits
    {
        $limit = function (string $name) use ($json): ?int {
            return is_numeric($json[$name] ?? null) ? (int)$json[$name] : null;
        };
        return new DeveloperKeyUsageLimits(
            $limit('characters'),
            $limit('speech_to_text_milliseconds'),
            $limit('speech_to_speech_milliseconds')
        );
    }
}
