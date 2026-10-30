<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values of TranslationMemoryJobResult::$status. The API may add further values, which
 * TranslationMemoryJobResult keeps as plain strings.
 * @see TranslationMemoryJobResult::$status
 */
class TranslationMemoryJobStatus
{
    /** The job is waiting for the caller, for example to upload the TMX file of an import. */
    public const AWAITING_INPUT = 'awaiting_input';

    /** The job is being processed. */
    public const PROCESSING = 'processing';

    /** The job completed successfully. */
    public const COMPLETED = 'completed';

    /** The job failed. */
    public const FAILED = 'failed';

    /** The job expired before it was completed. */
    public const EXPIRED = 'expired';
}
