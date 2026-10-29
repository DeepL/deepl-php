<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values for the include option of DeepLClient::getLanguagesForResource(). The option also accepts other
 * strings.
 * @see GetLanguagesForResourceOptions::INCLUDE
 */
class LanguageInclude
{
    /** Also return beta languages and features. */
    public const BETA = 'beta';

    /** Also return third-party-backed languages and features. */
    public const EXTERNAL = 'external';
}
