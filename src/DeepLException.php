<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Base exception for all exceptions thrown by this library.
 */
class DeepLException extends \Exception
{
    /**
     * @var int|null HTTP status code of the failed response, or null if the error did not come from an HTTP
     * response.
     */
    public $httpStatusCode = null;

    /**
     * @var bool True if the error is due to a transient condition and a retry could succeed: HTTP 429, any 5xx
     * status, and transient connection failures. False otherwise.
     */
    public $shouldRetry = false;
}
