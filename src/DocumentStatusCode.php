<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values of DocumentStatus::$status. The API may add further values, which DocumentStatus keeps as plain strings.
 * @see DocumentStatus::$status
 */
class DocumentStatusCode
{
    /** Document translation has not yet started, but will begin soon. */
    public const QUEUED = 'queued';

    /** Document translation is in progress. */
    public const TRANSLATING = 'translating';

    /** Document translation completed successfully, and the translated document may be downloaded. */
    public const DONE = 'done';

    /** An error occurred during document translation. */
    public const ERROR = 'error';
}
