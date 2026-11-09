<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLClient::createTranslationMemoryImport().
 */
class CreateTranslationMemoryImportOptions
{
    /**
     * MIME type of the TMX file (string), sent as source_file.content_type. Unset, the API assumes
     * 'application/xml'.
     */
    public const CONTENT_TYPE = 'content_type';

    /** Name for the resulting translation memory (string), defaults to the file name. */
    public const DISPLAY_NAME = 'display_name';

    /**
     * Associative array of additional parameters added to the JSON request body, after the fields set
     * by the library; a key that is already set is replaced. Values are sent as given.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
