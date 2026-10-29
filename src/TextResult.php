<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Holds the result of a text translation request.
 */
class TextResult
{
    /**
     * @var string String containing the translated text.
     */
    public $text;

    /**
     * @var string Language code of the detected source language.
     * @see LanguageCode
     */
    public $detectedSourceLang;

    /**
     * @var int|null Number of characters billed for this text, or null if the response did not include it.
     */
    public $billedCharacters;

    /**
     * @var string|null Model type used for the translation.
     * @see TranslateTextOptions::MODEL_TYPE
     */
    public $modelTypeUsed;

    /**
     * @var string|null Version of the tag handling algorithm used, or null if the response did not include it.
     * @see TagHandlingVersion
     */
    public $tagHandlingVersion;

    /**
     * @throws DeepLException
     */
    public function __construct(
        string $text,
        string $detectedSourceLang,
        ?int $billedCharacters,
        ?string $modelTypeUsed = null,
        ?string $tagHandlingVersion = null
    ) {
        $this->text = $text;
        $this->detectedSourceLang = LanguageCode::standardizeLanguageCode($detectedSourceLang);
        $this->billedCharacters = $billedCharacters;
        $this->modelTypeUsed = $modelTypeUsed;
        $this->tagHandlingVersion = $tagHandlingVersion;
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
