<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

/**
 * Information about the term list of one language in a spoken terms collection, excluding the entries.
 */
class SpokenTermsList
{
    /** @var string Language code of the terms in the list. */
    public $lang;

    /** @var int The number of terms in the list. */
    public $entryCount;

    public function __construct(string $lang, int $entryCount)
    {
        $this->lang = $lang;
        $this->entryCount = $entryCount;
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseJson(string $content): SpokenTermsList
    {
        try {
            $object = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        return self::parseObject($object);
    }

    /**
     * @return SpokenTermsList[]
     */
    public static function parseList(array $list): array
    {
        $result = [];
        foreach ($list as $object) {
            $result[] = self::parseObject($object);
        }
        return $result;
    }

    private static function parseObject($object): SpokenTermsList
    {
        return new SpokenTermsList($object['lang'] ?? '', $object['entry_count'] ?? 0);
    }
}
