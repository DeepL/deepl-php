<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * A translation memory export job.
 */
class TranslationMemoryExport
{
    /** @var string Unique ID assigned to the export job. */
    public $jobId;

    /** @var string|null ID of the exported translation memory, or null if not provided. */
    public $translationMemoryId;

    /**
     * @var bool True if the API reused a previously completed export instead of starting a new one.
     */
    public $reusedExisting;

    public function __construct(string $jobId, ?string $translationMemoryId, bool $reusedExisting)
    {
        $this->jobId = $jobId;
        $this->translationMemoryId = $translationMemoryId;
        $this->reusedExisting = $reusedExisting;
    }

    public static function fromJson(array $json, bool $reusedExisting): TranslationMemoryExport
    {
        return new TranslationMemoryExport(
            $json['job_id'],
            $json['parameters']['translation_memory_id'] ?? null,
            $reusedExisting
        );
    }

    /**
     * Parses the given JSON content to a TranslationMemoryExport. The reusedExisting flag reflects
     * whether the API answered 200 (reused a completed export) rather than 202 (started a new one).
     * @throws InvalidContentException
     */
    public static function parse(string $content, bool $reusedExisting): TranslationMemoryExport
    {
        return self::fromJson(TranslationMemoryUtils::decodeJson($content), $reusedExisting);
    }

    public function __toString(): string
    {
        return "TranslationMemoryExport ({$this->jobId})";
    }
}
