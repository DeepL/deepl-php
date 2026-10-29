<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLAdminClient::createDeveloperKey(). Options that are not set, or null, are not sent.
 */
class CreateDeveloperKeyOptions
{
    /** Label for the new key. The API uses "DeepL API Key" if no label is given. */
    public const LABEL = 'label';

    /**
     * Associative array of additional parameters added to the JSON request body, after the fields
     * set by the library; a key that is already set is replaced. Values are sent as given.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
