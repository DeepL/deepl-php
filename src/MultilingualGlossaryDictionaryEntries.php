<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Stores the entries of a dictionary, for a single source-target language pair.
 */
class MultilingualGlossaryDictionaryEntries
{
    /** @var string Language code of the source terms in the dictionary. */
    public $sourceLang;

    /** @var string Language code of the target terms in the dictionary. */
    public $targetLang;

    /** @var array Associative array storing the dictionary entries as source-target entry pairs. */
    public $entries;

    /**
     * @param string $sourceLang
     * @param string $targetLang
     * @param array $entries Source-target entry pairs. Both terms are trimmed of surrounding whitespace, then
     *  validated: they must not be empty or contain control characters.
     * @throws DeepLException If the entries are empty or a term is invalid.
     */
    public function __construct(string $sourceLang, string $targetLang, array $entries)
    {
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
        $this->entries = self::trimAndValidate($entries);
    }

    /**
     * @throws DeepLException If the entries are empty or a term is invalid.
     */
    private static function trimAndValidate(array $entries): array
    {
        $trimmed = [];
        foreach ($entries as $source => $target) {
            $source = trim((string)$source);
            $target = trim((string)$target);
            GlossaryUtils::validateGlossaryTerm($source);
            GlossaryUtils::validateGlossaryTerm($target);
            if (array_key_exists($source, $trimmed)) {
                throw new DeepLException("Entries duplicate source term \"$source\"");
            }
            $trimmed[$source] = $target;
        }
        GlossaryUtils::validateEntries($trimmed);
        return $trimmed;
    }

    /**
     * @throws DeepLException
     */
    public static function parseJsonList(string $content): array
    {
        $object = ParsingUtils::decodeJson($content);

        return ParsingUtils::guard(function () use ($object): array {
            return self::parseList($object['dictionaries']);
        });
    }

    /**
     * @throws DeepLException
     */
    public static function parseList(array $list): array
    {
        return ParsingUtils::guard(function () use ($list): array {
            $result = [];
            foreach ($list as $object) {
                $result[] = self::parseObject($object);
            }
            return $result;
        });
    }

    /**
     * Parses a dictionary returned by the API. The entries are read as returned, without the validation
     * applied to entries sent to the API; a dictionary may have no entries.
     * @throws DeepLException
     */
    public static function parseObject($object): MultilingualGlossaryDictionaryEntries
    {
        return ParsingUtils::guard(function () use ($object): MultilingualGlossaryDictionaryEntries {
            $entriesFormat = $object['entries_format'];
            if ($entriesFormat !== 'tsv') {
                throw new DeepLException("Unsupported entries_format: $entriesFormat");
            }
            $entries = [];
            foreach (preg_split('/\r\n|\n|\r/', (string)$object['entries']) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $terms = explode("\t", $line, 2);
                if (count($terms) < 2) {
                    throw ParsingUtils::invalidContent("Glossary entry without a tab separator: $line");
                }
                $entries[$terms[0]] = $terms[1];
            }
            $result = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
            $result->sourceLang = (string)$object['source_lang'];
            $result->targetLang = (string)$object['target_lang'];
            $result->entries = $entries;
            return $result;
        });
    }

    /**
     * Returns the dictionary as sent to the API. The entries are trimmed and validated again, as they may have
     * been changed after construction or come from the API.
     * @throws DeepLException If the entries are empty or a term is invalid.
     */
    public function toObject(): array
    {
        return [
            "source_lang" => $this->sourceLang,
            "target_lang" => $this->targetLang,
            "entries" => GlossaryUtils::convertToTsv(self::trimAndValidate((array)$this->entries)),
            "entries_format" => "tsv",
        ];
    }

    public function convertToTsv(): string
    {
        return GlossaryUtils::convertToTsv($this->entries);
    }
}
