<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLClient::getLanguagesForResource().
 */
class GetLanguagesForResourceOptions
{
    /**
     * List of include values, see {@see LanguageInclude}, sent as repeated include parameters. By default only
     * stable languages and features are returned.
     */
    public const INCLUDE = 'include';
}
