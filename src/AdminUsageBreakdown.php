<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Usage totals in an admin usage analytics report.
 */
class AdminUsageBreakdown
{
    /** @var int Total number of characters used. */
    public $totalCharacters;

    /** @var int Number of characters used for text translation. */
    public $textTranslationCharacters;

    /** @var int Number of characters used for document translation. */
    public $documentTranslationCharacters;

    /** @var int Number of characters used for text improvement. */
    public $textImprovementCharacters;

    /** @var float Number of speech-to-text minutes used. */
    public $speechToTextMinutes;

    /** @var float Number of speech-to-speech minutes used. */
    public $speechToSpeechMinutes;

    public function __construct(
        int $totalCharacters,
        int $textTranslationCharacters,
        int $documentTranslationCharacters,
        int $textImprovementCharacters,
        float $speechToTextMinutes,
        float $speechToSpeechMinutes = 0.0
    ) {
        $this->totalCharacters = $totalCharacters;
        $this->textTranslationCharacters = $textTranslationCharacters;
        $this->documentTranslationCharacters = $documentTranslationCharacters;
        $this->textImprovementCharacters = $textImprovementCharacters;
        $this->speechToTextMinutes = $speechToTextMinutes;
        $this->speechToSpeechMinutes = $speechToSpeechMinutes;
    }

    public static function fromJson(?array $json): AdminUsageBreakdown
    {
        return new AdminUsageBreakdown(
            (int)ParsingUtils::numberOrZero($json['total_characters'] ?? null),
            (int)ParsingUtils::numberOrZero($json['text_translation_characters'] ?? null),
            (int)ParsingUtils::numberOrZero($json['document_translation_characters'] ?? null),
            (int)ParsingUtils::numberOrZero($json['text_improvement_characters'] ?? null),
            (float)ParsingUtils::numberOrZero($json['speech_to_text_minutes'] ?? null),
            (float)ParsingUtils::numberOrZero($json['speech_to_speech_minutes'] ?? null)
        );
    }
}
