<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLClient::createStyleProfile(). An option is sent when it is set and not
 * null: an empty string clears the description or industry, and an empty array unlinks all
 * components of that kind.
 */
class CreateStyleProfileOptions
{
    /** Free-text description of the style profile; an empty string clears it. */
    public const DESCRIPTION = 'description';

    /** Industry the style profile is written for; an empty string clears it. */
    public const INDUSTRY = 'industry';

    /**
     * Glossaries to link, highest priority first, each as an ID or a GlossaryInfo or
     * MultilingualGlossaryInfo object. Replaces the linked list in full.
     */
    public const GLOSSARY_IDS = 'glossary_ids';

    /** Style rule lists to link, each as an ID or a StyleRuleInfo object. Replaces the linked list in full. */
    public const STYLE_RULE_IDS = 'style_rule_ids';

    /**
     * Translation memories to link, each as an ID or a TranslationMemoryInfo object. Replaces the
     * linked list in full.
     */
    public const TRANSLATION_MEMORY_IDS = 'translation_memory_ids';

    /** Whether the order of the glossary IDs sets their priority. */
    public const GLOSSARY_PRIORITY_ORDER_ENABLED = 'glossary_priority_order_enabled';

    /**
     * Associative array of additional parameters added to the JSON request body, after the fields
     * set by the library; a key that is already set is replaced. Values are sent as given.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
