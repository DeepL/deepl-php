<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values for the resource parameter of DeepLClient::getLanguagesForResource(), and of
 * LanguageResource::$resource. The parameter also accepts other strings.
 */
class LanguageResourceName
{
    /** Text translation. */
    public const TRANSLATE_TEXT = 'translate_text';

    /** Document translation. */
    public const TRANSLATE_DOCUMENT = 'translate_document';

    /** Glossaries. */
    public const GLOSSARY = 'glossary';

    /** Voice. */
    public const VOICE = 'voice';

    /** Write (rephrase). */
    public const WRITE = 'write';

    /** Style rules. */
    public const STYLE_RULES = 'style_rules';

    /** Translation memories. */
    public const TRANSLATION_MEMORY = 'translation_memory';
}
