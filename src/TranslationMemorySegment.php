<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;

/**
 * A source segment of a translation memory and its translations.
 */
class TranslationMemorySegment
{
    /** @var string Unique ID assigned to the source segment. */
    public $sourceSegmentId;

    /** @var string The source text. */
    public $sourceText;

    /** @var TranslationMemoryTargetSegment[] Translations of the source text, one per target language. */
    public $targets;

    /** @var DateTime|null Timestamp when the source segment was created, or null if not provided. */
    public $creationTime;

    /** @var DateTime|null Timestamp when the source segment was last updated, or null if not provided. */
    public $updatedTime;

    /** @var DateTime|null Timestamp when the source segment was last used, or null if not provided. */
    public $lastUsedTime;

    public function __construct(
        string $sourceSegmentId,
        string $sourceText,
        array $targets,
        ?DateTime $creationTime = null,
        ?DateTime $updatedTime = null,
        ?DateTime $lastUsedTime = null
    ) {
        $this->sourceSegmentId = $sourceSegmentId;
        $this->sourceText = $sourceText;
        $this->targets = $targets;
        $this->creationTime = $creationTime;
        $this->updatedTime = $updatedTime;
        $this->lastUsedTime = $lastUsedTime;
    }

    public static function fromJson(array $json): TranslationMemorySegment
    {
        $targets = [];
        foreach ($json['targets'] ?? [] as $target) {
            $targets[] = TranslationMemoryTargetSegment::fromJson($target);
        }

        return new TranslationMemorySegment(
            $json['source_segment_id'],
            $json['source_text'],
            $targets,
            TranslationMemoryUtils::parseOptionalTimestamp($json['creation_time'] ?? null),
            TranslationMemoryUtils::parseOptionalTimestamp($json['updated_time'] ?? null),
            TranslationMemoryUtils::parseOptionalTimestamp($json['last_used_time'] ?? null)
        );
    }

    public function __toString(): string
    {
        return "TranslationMemorySegment ({$this->sourceSegmentId})";
    }
}
