<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for the DeepLClient methods that send a style rule request body: createStyleRule(),
 * updateStyleRuleName(), updateStyleRuleConfiguredRules(), createStyleRuleCustomInstruction() and
 * updateStyleRuleCustomInstruction().
 */
class StyleRuleOptions
{
    /**
     * Associative array of additional parameters added to the JSON request body, after the fields
     * set by the library; a key that is already set is replaced. Values are sent as given. For
     * updateStyleRuleConfiguredRules() they are further keys of the configured rules object.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
