<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

/**
 * Information about a single DeepL resource and the features it supports, as
 * returned by {@see DeepLClient::getLanguageResources()}.
 */
class LanguageResource
{
    /** @deprecated Use LanguageResourceName::TRANSLATE_TEXT instead. */
    public const RESOURCE_TRANSLATE_TEXT = LanguageResourceName::TRANSLATE_TEXT;
    /** @deprecated Use LanguageResourceName::TRANSLATE_DOCUMENT instead. */
    public const RESOURCE_TRANSLATE_DOCUMENT = LanguageResourceName::TRANSLATE_DOCUMENT;
    /** @deprecated Use LanguageResourceName::GLOSSARY instead. */
    public const RESOURCE_GLOSSARY = LanguageResourceName::GLOSSARY;
    /** @deprecated Use LanguageResourceName::VOICE instead. */
    public const RESOURCE_VOICE = LanguageResourceName::VOICE;
    /** @deprecated Use LanguageResourceName::WRITE instead. */
    public const RESOURCE_WRITE = LanguageResourceName::WRITE;
    /** @deprecated Use LanguageResourceName::STYLE_RULES instead. */
    public const RESOURCE_STYLE_RULES = LanguageResourceName::STYLE_RULES;
    /** @deprecated Use LanguageResourceName::TRANSLATION_MEMORY instead. */
    public const RESOURCE_TRANSLATION_MEMORY = LanguageResourceName::TRANSLATION_MEMORY;

    /** @var string Name of the resource, for example 'translate_text'; see {@see LanguageResourceName}. */
    public $resource;

    /** @var ResourceFeature[] Map of feature name to ResourceFeature describing each feature
     * supported by the resource, including whether it requires source/target language support. */
    public $features;

    public function __construct(string $resource, array $features)
    {
        $this->resource = $resource;
        $this->features = $features;
    }

    /**
     * Returns true if this resource exposes the given feature.
     *
     * @param string $feature One of the LanguageFeature::FEATURE_* constants.
     */
    public function supports(string $feature): bool
    {
        return isset($this->features[$feature]);
    }

    public static function fromJson(array $json): LanguageResource
    {
        $features = [];
        if (isset($json['features']) && is_array($json['features'])) {
            foreach ($json['features'] as $value) {
                $features[$value['name']] = ResourceFeature::fromJson($value);
            }
        }
        return new LanguageResource($json['name'], $features);
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseList(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        $result = [];
        foreach ($decoded as $object) {
            $result[] = self::fromJson($object);
        }
        return $result;
    }
}
