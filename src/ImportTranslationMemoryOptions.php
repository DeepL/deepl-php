<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLClient::importTranslationMemoryFromFilepath().
 */
class ImportTranslationMemoryOptions
{
    /**
     * MIME type of the TMX file (string), sent as source_file.content_type. Unset, 'application/xml'.
     * Used for the import job and the upload.
     */
    public const CONTENT_TYPE = 'content_type';

    /** Name for the resulting translation memory (string), defaults to the file name. */
    public const DISPLAY_NAME = 'display_name';

    /**
     * Maximum time in seconds to wait for the job to finish (int or float). Unset or null waits without
     * limit.
     */
    public const TIMEOUT = 'timeout';

    /**
     * Associative array of additional parameters added to the JSON body of the import request, after
     * the fields set by the library; a key that is already set is replaced. Values are sent as given.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
