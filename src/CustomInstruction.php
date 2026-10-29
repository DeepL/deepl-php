<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Custom instruction for a style rule.
 */
class CustomInstruction
{
    /** @var string Unique ID assigned to the custom instruction, always set on instructions returned by the API. */
    public $id;

    /** @var string Label for the custom instruction. */
    public $label;

    /** @var string Prompt text for the custom instruction. */
    public $prompt;

    /** @var string|null Optional source language code for the custom instruction. */
    public $sourceLanguage;

    public function __construct(
        string $label,
        string $prompt,
        ?string $sourceLanguage = null,
        ?string $id = null
    ) {
        $this->id = $id;
        $this->label = $label;
        $this->prompt = $prompt;
        $this->sourceLanguage = $sourceLanguage;
    }

    /**
     * @throws InvalidContentException If a required field (id, label, prompt) is missing or invalid.
     */
    public static function fromJson(array $json): CustomInstruction
    {
        return ParsingUtils::guard(function () use ($json): CustomInstruction {
            if (!is_string($json['id'] ?? null)) {
                throw ParsingUtils::invalidContent('Expected a custom instruction id in the response');
            }
            return new CustomInstruction(
                $json['label'],
                $json['prompt'],
                $json['source_language'] ?? null,
                $json['id']
            );
        });
    }
}
