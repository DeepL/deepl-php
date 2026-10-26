<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLClient::createStyleProfile() and DeepLClient::updateStyleProfile().
 * An option is sent when it is set and not null: an empty string clears the description or
 * industry, and an empty array unlinks all components of that kind.
 */
class StyleProfileOptions
{
    /** New name of the style profile. Only used by updateStyleProfile(). */
    public const NAME = 'name';

    /** Free-text description of the style profile; an empty string clears it. */
    public const DESCRIPTION = 'description';

    /** Industry the style profile is written for; an empty string clears it. */
    public const INDUSTRY = 'industry';

    /** IDs of the glossaries to link, highest priority first. Replaces the linked list in full. */
    public const GLOSSARY_IDS = 'glossary_ids';

    /** IDs of the style rule lists to link. Replaces the linked list in full. */
    public const STYLE_RULE_IDS = 'style_rule_ids';

    /** IDs of the translation memories to link. Replaces the linked list in full. */
    public const TRANSLATION_MEMORY_IDS = 'translation_memory_ids';

    /** Whether the order of the glossary IDs sets their priority. */
    public const GLOSSARY_PRIORITY_ORDER_ENABLED = 'glossary_priority_order_enabled';
}
