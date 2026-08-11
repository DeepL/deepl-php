<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;

/**
 * Status of a translation memory import or export job.
 */
class TranslationMemoryJob
{
    /** @var string Unique ID assigned to the job. */
    public $jobId;

    /** @var string Operation the job performs, either 'import' or 'export'. */
    public $operation;

    /** @var string Product the job belongs to, for example 'translation_memory'. */
    public $product;

    /** @var TranslationMemoryJobResult[] Results of the job; the API returns exactly one. */
    public $results;

    /** @var DateTime|null Timestamp when the job was created, or null if not provided. */
    public $creationTime;

    /** @var DateTime|null Timestamp when the job was last updated, or null if not provided. */
    public $updatedTime;

    /** @var string|null Translation memory an export job reads from. */
    public $translationMemoryId;

    /** @var string|null Display name an import job assigns to the new translation memory. */
    public $displayName;

    /** @var string|null MIME type declared for the file of an import job. */
    public $sourceContentType;

    /** @var int|null Size in bytes declared for the file of an import job. */
    public $sourceContentLength;

    public function __construct(
        string $jobId,
        string $operation,
        string $product,
        array $results,
        ?DateTime $creationTime = null,
        ?DateTime $updatedTime = null,
        ?string $translationMemoryId = null,
        ?string $displayName = null,
        ?string $sourceContentType = null,
        ?int $sourceContentLength = null
    ) {
        $this->jobId = $jobId;
        $this->operation = $operation;
        $this->product = $product;
        $this->results = $results;
        $this->creationTime = $creationTime;
        $this->updatedTime = $updatedTime;
        $this->translationMemoryId = $translationMemoryId;
        $this->displayName = $displayName;
        $this->sourceContentType = $sourceContentType;
        $this->sourceContentLength = $sourceContentLength;
    }

    /**
     * @param string|TranslationMemoryJob $job Job ID or TranslationMemoryJob.
     */
    public static function getJobId($job): string
    {
        return is_string($job) ? $job : $job->jobId;
    }

    public static function fromJson(array $json): TranslationMemoryJob
    {
        $results = [];
        foreach ($json['results'] ?? [] as $result) {
            $results[] = TranslationMemoryJobResult::fromJson($result);
        }

        return new TranslationMemoryJob(
            $json['job_id'],
            $json['operation'] ?? '',
            $json['product'] ?? 'translation_memory',
            $results,
            TranslationMemoryUtils::parseOptionalTimestamp($json['creation_time'] ?? null),
            TranslationMemoryUtils::parseOptionalTimestamp($json['updated_time'] ?? null),
            $json['parameters']['translation_memory_id'] ?? null,
            $json['parameters']['display_name'] ?? null,
            $json['source_file']['content_type'] ?? null,
            $json['source_file']['content_length'] ?? null
        );
    }

    /**
     * @throws InvalidContentException
     */
    public static function parse(string $content): TranslationMemoryJob
    {
        return self::fromJson(TranslationMemoryUtils::decodeJson($content));
    }

    /**
     * @return TranslationMemoryJobResult|null The single result of the job, or null if the API
     * returned none.
     */
    public function result(): ?TranslationMemoryJobResult
    {
        return count($this->results) > 0 ? $this->results[0] : null;
    }

    /**
     * @return string|null The status of the job, or null if the API returned no result.
     * @see TranslationMemoryJobResult for the possible status values.
     */
    public function status(): ?string
    {
        $result = $this->result();
        return $result === null ? null : $result->status;
    }

    /**
     * @return bool True if the job has finished, successfully or not, otherwise false.
     */
    public function done(): bool
    {
        $result = $this->result();
        return $result !== null && $result->done();
    }

    /**
     * @return bool False if the job failed or expired, otherwise true.
     * Note that while the job is in progress, this returns true.
     */
    public function ok(): bool
    {
        $result = $this->result();
        return $result === null || $result->ok();
    }

    public function __toString(): string
    {
        return "TranslationMemoryJob ({$this->jobId})";
    }
}
