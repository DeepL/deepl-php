<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLClient::updateStyleProfile(): the keys of CreateStyleProfileOptions plus
 * NAME. Options that are not set, or null, are not sent and leave the field unchanged.
 */
class UpdateStyleProfileOptions extends CreateStyleProfileOptions
{
    /** New name of the style profile. */
    public const NAME = 'name';
}
