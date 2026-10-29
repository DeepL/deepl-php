<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

/**
 * Information about a language supported by the DeepL API for a given resource,
 * as returned by {@see DeepLClient::getLanguagesForResource()}.
 */
class LanguageSupport
{
    /** @deprecated Use LanguageInclude::BETA instead. */
    public const INCLUDE_BETA = LanguageInclude::BETA;
    /** @deprecated Use LanguageInclude::EXTERNAL instead. */
    public const INCLUDE_EXTERNAL = LanguageInclude::EXTERNAL;

    /** @var string BCP-47 language code, for example 'de', 'en-US', 'pt-BR', 'zh-Hans'. */
    public $code;

    /** @var string Name of the language in English. */
    public $name;

    /** @var string Support status of the language, for example 'stable' or 'beta'. */
    public $status;

    /** @var bool True if the language can be used as a source language for the requested resource. */
    public $usableAsSource;

    /** @var bool True if the language can be used as a target language for the requested resource. */
    public $usableAsTarget;

    /** @var LanguageFeature[] Map of feature name to LanguageFeature describing per-feature support
     * for this language and resource. Empty array if no optional features are supported. */
    public $features;

    public function __construct(
        string $code,
        string $name,
        string $status,
        bool $usableAsSource,
        bool $usableAsTarget,
        array $features
    ) {
        $this->code = $code;
        $this->name = $name;
        $this->status = $status;
        $this->usableAsSource = $usableAsSource;
        $this->usableAsTarget = $usableAsTarget;
        $this->features = $features;
    }

    /**
     * Returns true if this language supports the given feature for the requested resource.
     *
     * @param string $feature One of the LanguageFeature::FEATURE_* constants, for example
     *                         LanguageFeature::FEATURE_FORMALITY.
     */
    public function supports(string $feature): bool
    {
        return isset($this->features[$feature]);
    }

    public static function fromJson(array $json): LanguageSupport
    {
        $features = [];
        if (isset($json['features']) && is_array($json['features'])) {
            foreach ($json['features'] as $name => $value) {
                $features[$name] = LanguageFeature::fromJson($value);
            }
        }
        return new LanguageSupport(
            $json['lang'],
            $json['name'],
            $json['status'],
            (bool)$json['usable_as_source'],
            (bool)$json['usable_as_target'],
            $features
        );
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

    public function __toString(): string
    {
        return "$this->name ($this->code)";
    }
}
