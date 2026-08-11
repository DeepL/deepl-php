<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;

/**
 * A newly created translation memory import job. The TMX file must be uploaded to the upload URL
 * before it expires; processing starts automatically once the upload is detected.
 */
class TranslationMemoryImport
{
    /** @var string Unique ID assigned to the import job. */
    public $jobId;

    /** @var string URL to upload the TMX file to. */
    public $uploadUrl;

    /** @var DateTime|null Timestamp after which the upload URL is no longer valid. */
    public $expiresAt;

    public function __construct(string $jobId, string $uploadUrl, ?DateTime $expiresAt = null)
    {
        $this->jobId = $jobId;
        $this->uploadUrl = $uploadUrl;
        $this->expiresAt = $expiresAt;
    }

    public static function fromJson(array $json): TranslationMemoryImport
    {
        return new TranslationMemoryImport(
            $json['job_id'],
            $json['upload_url'],
            TranslationMemoryUtils::parseOptionalTimestamp($json['expires_at'] ?? null)
        );
    }

    /**
     * @throws InvalidContentException
     */
    public static function parse(string $content): TranslationMemoryImport
    {
        return self::fromJson(TranslationMemoryUtils::decodeJson($content));
    }

    public function __toString(): string
    {
        return "TranslationMemoryImport ({$this->jobId})";
    }
}
