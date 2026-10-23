<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use JsonException;

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
        return self::parseObject(self::decode($content));
    }

    /**
     * @return SpokenTerms[]
     * @throws InvalidContentException
     */
    public static function parseListJson(string $content): array
    {
        $result = [];
        foreach (self::decode($content)['spoken_terms'] ?? [] as $object) {
            $result[] = self::parseObject($object);
        }
        return $result;
    }

    /**
     * @throws InvalidContentException
     */
    private static function decode(string $content): array
    {
        try {
            return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
    }

    /**
     * @throws \Exception
     */
    private static function parseObject(array $object): SpokenTerms
    {
        return new SpokenTerms(
            $object['spoken_terms_id'],
            $object['name'] ?? '',
            new DateTime($object['creation_time']),
            SpokenTermsList::parseList($object['term_lists'] ?? [])
        );
    }
}
