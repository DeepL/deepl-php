<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;

/**
 * Information about a spoken terms collection, excluding the entries.
 */
class SpokenTerms
{
    /** @var string ID of the spoken terms collection. */
    public $spokenTermsId;

    /** @var string Name of the spoken terms collection. */
    public $name;

    /** @var DateTime DateTime when the spoken terms collection was created. */
    public $creationTime;

    /** @var SpokenTermsList[] Term lists in this collection, one per language, with their entry counts. */
    public $termLists;

    public function __construct(string $spokenTermsId, string $name, DateTime $creationTime, array $termLists)
    {
        $this->spokenTermsId = $spokenTermsId;
        $this->name = $name;
        $this->creationTime = $creationTime;
        $this->termLists = $termLists;
    }

    /**
     * @param string|SpokenTerms $spokenTerms Spoken terms ID or SpokenTerms object.
     */
    public static function getSpokenTermsId($spokenTerms): string
    {
        return is_string($spokenTerms) ? $spokenTerms : $spokenTerms->spokenTermsId;
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseJson(string $content): SpokenTerms
    {
        $object = self::decode($content);
        return ParsingUtils::guard(function () use ($object): SpokenTerms {
            return self::parseObject($object);
        });
    }

    /**
     * @return SpokenTerms[]
     * @throws InvalidContentException
     */
    public static function parseListJson(string $content): array
    {
        $decoded = self::decode($content);
        return ParsingUtils::guard(function () use ($decoded): array {
            $result = [];
            foreach ($decoded['spoken_terms'] ?? [] as $object) {
                $result[] = self::parseObject($object);
            }
            return $result;
        });
    }

    /**
     * @throws InvalidContentException
     */
    private static function decode(string $content): array
    {
        return ParsingUtils::decodeJson($content);
    }

    /**
     * @throws \Exception
     */
    private static function parseObject(array $object): SpokenTerms
    {
        return new SpokenTerms(
            $object['spoken_terms_id'],
            $object['name'] ?? '',
            ParsingUtils::parseTimestamp($object['creation_time'] ?? null),
            SpokenTermsList::parseList($object['term_lists'] ?? [])
        );
    }
}
