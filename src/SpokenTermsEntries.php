<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * The terms of one language in a spoken terms collection.
 */
class SpokenTermsEntries
{
    /** @var string Language code of the terms, e.g. "en". */
    public $lang;

    /** @var string[] The terms, one string per term. */
    public $entries;

    /**
     * @param string $lang Language code of the terms, e.g. "en".
     * @param string[] $entries The terms, one string per term.
     */
    public function __construct(string $lang, array $entries)
    {
        $this->lang = $lang;
        $this->entries = $entries;
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseJson(string $content): SpokenTermsEntries
    {
        $object = ParsingUtils::decodeJson($content);
        $lang = $object['lang'] ?? '';
        $entries = $object['entries'] ?? '';
        if (!is_string($lang) || !is_string($entries)) {
            throw ParsingUtils::invalidContent('Expected lang and entries to be strings in the response');
        }
        $terms = array_values(array_filter(explode("\n", $entries), function (string $line) {
            return $line !== '';
        }));
        return new SpokenTermsEntries($lang, $terms);
    }

    /** Request body of this term list: the API takes the terms as one newline-separated string. */
    public function toObject(): array
    {
        return [
            'lang' => $this->lang,
            'entries' => implode("\n", $this->entries),
        ];
    }
}
