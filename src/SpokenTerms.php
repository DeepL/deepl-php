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
            $list = $decoded['spoken_terms'] ?? [];
            if (!is_array($list)) {
                throw ParsingUtils::invalidContent('Expected spoken_terms to be a list in the response');
            }
            return array_values(array_map([self::class, 'parseObject'], $list));
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
     * @param mixed $object
     * @throws InvalidContentException
     */
    private static function parseObject($object): SpokenTerms
    {
        if (!is_array($object)) {
            throw ParsingUtils::invalidContent('Expected a spoken terms object in the response');
        }
        $id = $object['spoken_terms_id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw ParsingUtils::invalidContent('Expected a spoken_terms_id string in the response');
        }
        $name = $object['name'] ?? '';
        if (!is_string($name)) {
            throw ParsingUtils::invalidContent('Expected name to be a string in the response');
        }
        return new SpokenTerms(
            $id,
            $name,
            ParsingUtils::parseTimestamp($object['creation_time'] ?? null),
            SpokenTermsList::parseList($object['term_lists'] ?? [])
        );
    }
}
