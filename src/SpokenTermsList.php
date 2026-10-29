<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

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
        return self::parseObject(ParsingUtils::decodeJson($content));
    }

    /**
     * @param mixed $list
     * @return SpokenTermsList[]
     * @throws InvalidContentException
     */
    public static function parseList($list): array
    {
        if (!is_array($list)) {
            throw ParsingUtils::invalidContent('Expected term_lists to be a list in the response');
        }
        return array_values(array_map([self::class, 'parseObject'], $list));
    }

    /**
     * @param mixed $object
     * @throws InvalidContentException
     */
    private static function parseObject($object): SpokenTermsList
    {
        if (!is_array($object)) {
            throw ParsingUtils::invalidContent('Expected a term list object in the response');
        }
        $lang = $object['lang'] ?? '';
        if (!is_string($lang)) {
            throw ParsingUtils::invalidContent('Expected lang to be a string in the response');
        }
        return new SpokenTermsList($lang, (int)ParsingUtils::numberOrZero($object['entry_count'] ?? 0));
    }
}
