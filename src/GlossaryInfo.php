<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;

/**
 * Information about a glossary, excluding the entry list.
 */
class GlossaryInfo
{
    /** @var string ID of the associated glossary. */
    public $glossaryId;

    /** @var string Name of the glossary chosen during creation. */
    public $name;

    /** @var boolean True if the glossary may be used for translations, otherwise false. */
    public $ready;

    /** @var string Language code of the source terms in the glossary. */
    public $sourceLang;

    /** @var string Language code of the target terms in the glossary. */
    public $targetLang;

    /** @var DateTime DateTime when the glossary was created. */
    public $creationTime;

    /** @var int The number of source-target entry pairs in the glossary. */
    public $entryCount;

    public function __construct(
        string $glossaryId,
        string $name,
        bool $ready,
        string $sourceLang,
        string $targetLang,
        DateTime $creationTime,
        int $entryCount
    ) {
        $this->glossaryId = $glossaryId;
        $this->name = $name;
        $this->ready = $ready;
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
        $this->creationTime = $creationTime;
        $this->entryCount = $entryCount;
    }

    /**
     * @throws InvalidContentException
     */
    public static function parse(string $content): GlossaryInfo
    {
        $object = ParsingUtils::decodeJson($content);

        return ParsingUtils::guard(function () use ($object): GlossaryInfo {
            return self::parseJsonObject($object);
        });
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseList(string $content): array
    {
        $decoded = ParsingUtils::decodeJson($content);

        return ParsingUtils::guard(function () use ($decoded): array {
            $result = [];
            foreach ($decoded['glossaries'] as $object) {
                $result[] = self::parseJsonObject($object);
            }
            return $result;
        });
    }

    /**
     * @throws \Exception
     */
    private static function parseJsonObject($object): GlossaryInfo
    {
        return new GlossaryInfo(
            $object['glossary_id'],
            $object['name'] ?? null,
            $object['ready'] ?? null,
            $object['source_lang'] ?? null,
            $object['target_lang'] ?? null,
            ParsingUtils::parseTimestamp($object['creation_time'] ?? null),
            $object['entry_count'] ?? null
        );
    }
}
