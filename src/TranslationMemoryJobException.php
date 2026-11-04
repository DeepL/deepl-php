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
     * The job, once it was created: the last status fetched, or a job with only its ID and operation if no
     * status was fetched yet. Null if the failure occurred before the job was created.
     * @var TranslationMemoryJob|null
     */
    public $job;

    public function __construct($message = "", $code = 0, $previous = null, ?TranslationMemoryJob $job = null)
    {
        parent::__construct($message, $code, $previous);
        $this->job = $job;
    }
}
