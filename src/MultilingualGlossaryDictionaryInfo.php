<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Information about a single dictionary in a multilingual glossary, excluding
 * the entry list.
 */
class MultilingualGlossaryDictionaryInfo
{
    /** @var string Language code of the source terms in the dictionary. */
    public $sourceLang;

    /** @var string Language code of the target terms in the dictionary. */
    public $targetLang;

    /** @var int The number of source-target entry pairs in the dictionary. */
    public $entryCount;

    public function __construct(
        string $sourceLang,
        string $targetLang,
        int $entryCount
    ) {
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
        $this->entryCount = $entryCount;
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseJson(string $content): MultilingualGlossaryDictionaryInfo
    {
        $object = ParsingUtils::decodeJson($content);

        return ParsingUtils::guard(function () use ($object): MultilingualGlossaryDictionaryInfo {
            return self::parseObject($object);
        });
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseList(array $list): array
    {
        return ParsingUtils::guard(function () use ($list): array {
            $result = [];
            foreach ($list as $object) {
                $result[] = self::parseObject($object);
            }
            return $result;
        });
    }

    /**
     * @throws \Exception
     */
    private static function parseObject($object): MultilingualGlossaryDictionaryInfo
    {
        return new MultilingualGlossaryDictionaryInfo(
            $object['source_lang'] ?? null,
            $object['target_lang'] ?? null,
            $object['entry_count'] ?? null
        );
    }
}
