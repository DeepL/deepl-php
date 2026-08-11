<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;

/**
 * A target-language translation attached to a translation memory source segment.
 */
class TranslationMemoryTargetSegment
{
    /** @var string Unique ID assigned to the target segment. */
    public $targetSegmentId;

    /** @var string Target language code of the translation. */
    public $targetLanguage;

    /** @var string The translated text. */
    public $targetText;

    /** @var DateTime|null Timestamp when the target segment was created, or null if not provided. */
    public $creationTime;

    /** @var DateTime|null Timestamp when the target segment was last updated, or null if not provided. */
    public $updatedTime;

    /** @var DateTime|null Timestamp when the target segment was last used, or null if not provided. */
    public $lastUsedTime;

    public function __construct(
        string $targetSegmentId,
        string $targetLanguage,
        string $targetText,
        ?DateTime $creationTime = null,
        ?DateTime $updatedTime = null,
        ?DateTime $lastUsedTime = null
    ) {
        $this->targetSegmentId = $targetSegmentId;
        $this->targetLanguage = $targetLanguage;
        $this->targetText = $targetText;
        $this->creationTime = $creationTime;
        $this->updatedTime = $updatedTime;
        $this->lastUsedTime = $lastUsedTime;
    }

    public static function fromJson(array $json): TranslationMemoryTargetSegment
    {
        return new TranslationMemoryTargetSegment(
            $json['target_segment_id'],
            $json['target_language'],
            $json['target_text'],
            TranslationMemoryUtils::parseOptionalTimestamp($json['creation_time'] ?? null),
            TranslationMemoryUtils::parseOptionalTimestamp($json['updated_time'] ?? null),
            TranslationMemoryUtils::parseOptionalTimestamp($json['last_used_time'] ?? null)
        );
    }

    public function __toString(): string
    {
        return "{$this->targetLanguage}: {$this->targetText}";
    }
}
