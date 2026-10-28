<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Exception thrown when a connection error occurs while accessing the DeepL API.
 */
class ConnectionException extends DeepLException
{
    public function __construct(string $message, int $code, $previous, bool $shouldRetry)
    {
        parent::__construct($message, $code, $previous);
        $this->shouldRetry = $shouldRetry;
    }
}
