<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * One page of translation memory segments.
 */
class TranslationMemorySegments
{
    /** @var TranslationMemorySegment[] The segments in this page. */
    public $segments;

    /**
     * @var int Total number of segments stored in the translation memory. This is
     * translation-memory-level metadata and is not reduced by a text filter.
     */
    public $segmentCount;

    /**
     * @var string|null Opaque cursor to pass as page_cursor to fetch the next page, or null if this
     * is the last page.
     */
    public $nextPageCursor;

    public function __construct(array $segments, int $segmentCount, ?string $nextPageCursor = null)
    {
        $this->segments = $segments;
        $this->segmentCount = $segmentCount;
        $this->nextPageCursor = $nextPageCursor;
    }

    public static function fromJson(array $json): TranslationMemorySegments
    {
        $segments = [];
        foreach ($json['segments'] ?? [] as $segment) {
            $segments[] = TranslationMemorySegment::fromJson($segment);
        }

        return new TranslationMemorySegments(
            $segments,
            $json['segment_count'] ?? 0,
            $json['next_page_cursor'] ?? null
        );
    }

    /**
     * @throws InvalidContentException
     */
    public static function parse(string $content): TranslationMemorySegments
    {
        return self::fromJson(TranslationMemoryUtils::decodeJson($content));
    }
}
