<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Exception thrown if an error occurs in importTranslationMemoryFromFilepath() or
 * exportTranslationMemoryToFilepath(). The previous exception is the cause; if it is a DeepLException,
 * its httpStatusCode and shouldRetry are copied.
 * @see DeepLClient::importTranslationMemoryFromFilepath()
 * @see DeepLClient::exportTranslationMemoryToFilepath()
 */
class TranslationMemoryJobException extends DeepLException
{
    /**
     * ID of the import or export job, set as soon as the job was created; null if the failure occurred before.
     * @var string|null
     */
    public $jobId;

    /**
     * The last job status the helper fetched; null if the failure occurred before the first status check.
     * @var TranslationMemoryJob|null
     */
    public $job;

    public function __construct(
        $message = "",
        $code = 0,
        $previous = null,
        ?string $jobId = null,
        ?TranslationMemoryJob $job = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->jobId = $jobId;
        $this->job = $job;
    }
}
