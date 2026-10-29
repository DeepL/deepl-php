<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Describes a feature supported by a DeepL resource, as returned by
 * {@see DeepLClient::getLanguageResources()}.
 */
class ResourceFeature
{
    /** @var string Feature name, for example 'glossary' or 'formality'. */
    public $name;

    /** @var bool True if the feature requires the source language to support it; false if the API omits it. */
    public $needsSourceSupport;

    /** @var bool True if the feature requires the target language to support it; false if the API omits it. */
    public $needsTargetSupport;

    public function __construct(
        string $name,
        ?bool $needsSourceSupport = null,
        ?bool $needsTargetSupport = null
    ) {
        $this->name = $name;
        $this->needsSourceSupport = $needsSourceSupport ?? false;
        $this->needsTargetSupport = $needsTargetSupport ?? false;
    }

    /**
     * @throws InvalidContentException If the name is missing or not a string.
     */
    public static function fromJson(array $json): ResourceFeature
    {
        if (!is_string($json['name'] ?? null)) {
            throw ParsingUtils::invalidContent('Expected a string name in each resource feature of the response');
        }
        return new ResourceFeature(
            $json['name'],
            $json['needs_source_support'] ?? false,
            $json['needs_target_support'] ?? false
        );
    }
}
