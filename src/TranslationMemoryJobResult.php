<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;

/**
 * The outcome of a translation memory import or export job.
 */
class TranslationMemoryJobResult
{
    /** The job is waiting for the caller, for example to upload the TMX file of an import. */
    public const STATUS_AWAITING_INPUT = 'awaiting_input';

    /** The job is being processed. */
    public const STATUS_PROCESSING = 'processing';

    /** The job completed successfully. */
    public const STATUS_COMPLETED = 'completed';

    /** The exported TMX file of the job was downloaded. */
    public const STATUS_DOWNLOADED = 'downloaded';

    /** The job failed. */
    public const STATUS_FAILED = 'failed';

    /** The job expired before it was completed. */
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var string The job status, one of the STATUS_* constants of this class.
     */
    public $status;

    /** @var string|null Action the caller must take, set while the job is waiting on the caller. */
    public $requiredAction;

    /** @var string|null Download URL of the exported TMX file, set once an export completes. */
    public $downloadUrl;

    /** @var DateTime|null Timestamp after which the download URL is no longer valid. */
    public $expiresAt;

    /** @var string|null Error description, set when the job failed. */
    public $errorMessage;

    /** @var string|null ID of the translation memory created by a completed import. */
    public $translationMemoryId;

    /** @var int|null Number of segments an import skipped. */
    public $skippedSegmentCount;

    public function __construct(
        string $status,
        ?string $requiredAction = null,
        ?string $downloadUrl = null,
        ?DateTime $expiresAt = null,
        ?string $errorMessage = null,
        ?string $translationMemoryId = null,
        ?int $skippedSegmentCount = null
    ) {
        $this->status = $status;
        $this->requiredAction = $requiredAction;
        $this->downloadUrl = $downloadUrl;
        $this->expiresAt = $expiresAt;
        $this->errorMessage = $errorMessage;
        $this->translationMemoryId = $translationMemoryId;
        $this->skippedSegmentCount = $skippedSegmentCount;
    }

    public static function fromJson(array $json): TranslationMemoryJobResult
    {
        return new TranslationMemoryJobResult(
            $json['status'] ?? '',
            $json['status_metadata']['required_action'] ?? null,
            $json['download_url'] ?? null,
            TranslationMemoryUtils::parseOptionalTimestamp($json['expires_at'] ?? null),
            $json['error']['message'] ?? null,
            $json['translation_memory_id'] ?? null,
            $json['skipped_segment_count'] ?? null
        );
    }

    /**
     * @return bool True if the job has finished, successfully or not, otherwise false.
     */
    public function done(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_DOWNLOADED,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
        ], true);
    }

    /**
     * @return bool False if the job failed or expired, otherwise true.
     * Note that while the job is in progress, this returns true.
     */
    public function ok(): bool
    {
        return !in_array($this->status, [self::STATUS_FAILED, self::STATUS_EXPIRED], true);
    }

    public function __toString(): string
    {
        return "TranslationMemoryJobResult ({$this->status})";
    }
}
