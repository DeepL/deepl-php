<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Describes the support level for a single feature of a language, as returned by
 * {@see DeepLClient::getLanguagesForResource()}.
 */
class LanguageFeature
{
    /** Feature: automatic source-language detection. */
    public const FEATURE_AUTO_DETECTION = 'auto_detection';
    /** Feature: formality control. */
    public const FEATURE_FORMALITY = 'formality';
    /** Feature: glossary support. */
    public const FEATURE_GLOSSARY = 'glossary';
    /** Feature: style rules. */
    public const FEATURE_STYLE_RULES = 'style_rules';
    /** Feature: tag handling. */
    public const FEATURE_TAG_HANDLING = 'tag_handling';
    /** Feature: translation memory. */
    public const FEATURE_TRANSLATION_MEMORY = 'translation_memory';
    /** Feature: tone control (write). */
    public const FEATURE_TONE = 'tone';
    /** Feature: writing style (write). */
    public const FEATURE_WRITING_STYLE = 'writing_style';
    /** Feature: transcription (voice). */
    public const FEATURE_TRANSCRIPTION = 'transcription';
    /** Feature: translated speech (voice). */
    public const FEATURE_TRANSLATED_SPEECH = 'translated_speech';
    /** Feature: spoken terms (voice). */
    public const FEATURE_SPOKEN_TERMS = 'spoken_terms';

    /** @var string Support status of the feature, for example 'stable' or 'beta'. */
    public $status;

    public function __construct(string $status)
    {
        $this->status = $status;
    }

    /**
     * @throws InvalidContentException If the status is missing or not a string.
     */
    public static function fromJson(array $json): LanguageFeature
    {
        if (!is_string($json['status'] ?? null)) {
            throw ParsingUtils::invalidContent('Expected a string status in each language feature of the response');
        }
        return new LanguageFeature($json['status']);
    }
}
