<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLClient::waitUntilTranslationMemoryJobDone() and
 * DeepLClient::exportTranslationMemoryToFilepath().
 */
class WaitOptions
{
    /**
     * Maximum time in seconds to wait for the job to finish (int or float). Unset or null waits without
     * limit.
     */
    public const TIMEOUT = 'timeout';
}
