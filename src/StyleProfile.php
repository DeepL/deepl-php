<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;

/**
 * Information about a style profile: a named set of linked glossaries, style rule lists and
 * translation memories.
 */
class StyleProfile
{
    /** @var string Unique ID assigned to the style profile. */
    public $styleProfileId;

    /** @var string Name of the style profile. */
    public $name;

    /** @var string|null Description of the style profile, or null if it has none. */
    public $description;

    /** @var string|null Industry the style profile is written for, or null if it has none. */
    public $industry;

    /** @var string[] IDs of the linked glossaries, highest priority first. */
    public $glossaryIds;

    /** @var string[] IDs of the linked style rule lists. */
    public $styleRuleIds;

    /** @var string[] IDs of the linked translation memories. */
    public $translationMemoryIds;

    /** @var bool Whether the order of $glossaryIds sets their priority. */
    public $glossaryPriorityOrderEnabled;

    /** @var DateTime Time the style profile was created. */
    public $creationTime;

    /** @var DateTime Time the style profile was last updated. */
    public $updatedTime;

    public function __construct(
        string $styleProfileId,
        string $name,
        ?string $description,
        ?string $industry,
        array $glossaryIds,
        array $styleRuleIds,
        array $translationMemoryIds,
        bool $glossaryPriorityOrderEnabled,
        DateTime $creationTime,
        DateTime $updatedTime
    ) {
        $this->styleProfileId = $styleProfileId;
        $this->name = $name;
        $this->description = $description;
        $this->industry = $industry;
        $this->glossaryIds = $glossaryIds;
        $this->styleRuleIds = $styleRuleIds;
        $this->translationMemoryIds = $translationMemoryIds;
        $this->glossaryPriorityOrderEnabled = $glossaryPriorityOrderEnabled;
        $this->creationTime = $creationTime;
        $this->updatedTime = $updatedTime;
    }

    /**
     * @param string|StyleProfile $styleProfile Style profile ID or StyleProfile object.
     * @throws DeepLException If the ID is empty.
     */
    public static function getStyleProfileId($styleProfile): string
    {
        $styleProfileId = is_string($styleProfile) ? $styleProfile : $styleProfile->styleProfileId;
        if (strlen($styleProfileId) === 0) {
            throw new DeepLException('styleProfileId must be a non-empty string');
        }
        return $styleProfileId;
    }

    /**
     * @throws InvalidContentException If a timestamp is absent or unparseable.
     */
    public static function fromJson(array $json): StyleProfile
    {
        return new StyleProfile(
            self::stringOrNull($json, 'style_profile_id') ?? '',
            self::stringOrNull($json, 'name') ?? '',
            self::stringOrNull($json, 'description'),
            self::stringOrNull($json, 'industry'),
            self::idList($json, 'glossary_ids'),
            self::idList($json, 'style_rule_ids'),
            self::idList($json, 'translation_memory_ids'),
            ($json['glossary_priority_order_enabled'] ?? false) === true,
            ParsingUtils::parseTimestamp($json['creation_time'] ?? null),
            ParsingUtils::parseTimestamp($json['updated_time'] ?? null)
        );
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseJson(string $content): StyleProfile
    {
        return self::fromJson(ParsingUtils::decodeJson($content));
    }

    /**
     * @return StyleProfile[]
     * @throws InvalidContentException
     */
    public static function parseList(string $content): array
    {
        return ParsingUtils::mapObjects(
            ParsingUtils::decodeJson($content)['style_profiles'] ?? [],
            [self::class, 'fromJson']
        );
    }

    private static function stringOrNull(array $json, string $key): ?string
    {
        return isset($json[$key]) && is_string($json[$key]) ? $json[$key] : null;
    }

    /** @return string[] */
    private static function idList(array $json, string $key): array
    {
        if (!isset($json[$key]) || !is_array($json[$key])) {
            return [];
        }
        return array_values(array_filter($json[$key], 'is_string'));
    }

    public function __toString(): string
    {
        return "StyleProfile \"{$this->name}\" ({$this->styleProfileId})";
    }
}
