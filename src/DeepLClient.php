<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

class DeepLClient extends Translator
{

    public function __construct(string $authKey, array $options = [])
    {
        parent::__construct($authKey, $options);
    }

    /**
     * Rephrases the given text using DeepL's API.
     * @param string|string[] $texts A single string or array of strings to rephrase
     * @param string|null $targetLang Optional target language code
     * @param array $options Rephrase options to apply. See \DeepL\DeepLClientOptions.
     * @return RephraseTextResult|RephraseTextResult[] A RephraseTextResult or array of
     * RephraseTextResult objects containing rephrased texts
     * @throws DeepLException
     * @see \DeepL\DeepLClientOptions
     */
    public function rephraseText($texts, ?string $targetLang = null, array $options = [])
    {
        $params = $this->buildRephraseBodyParams(
            $targetLang,
            $options[RephraseTextOptions::WRITING_STYLE] ?? null,
            $options[RephraseTextOptions::TONE] ?? null
        );
        $this->validateAndAppendTexts($params, $texts);

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v2/write/rephrase',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;

        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        $improvements = isset($json['improvements']) && is_array($json['improvements']) ? $json['improvements'] : [];
        $output = [];
        foreach ($improvements as $improvement) {
            $text = $improvement['text'] ?? '';
            $detectedSourceLanguage = $improvement['detected_source_language'] ?? '';
            $targetLanguage = $improvement['target_language'] ?? '';
            $output[] = new RephraseTextResult($text, $detectedSourceLanguage, $targetLanguage);
        }

        return is_array($texts) ? $output : $output[0];
    }

    /**
     * Creates a new glossary on DeepL server with given name with all the specified
     * dictionaries, each with their own language pair and entries.
     * @param string $name User-defined name to assign to the glossary.
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries A list of MultilingualGlossaryDictionaryEntries
     *      which each contains entries for a particular language pair
     * @return MultilingualGlossaryInfo Details about the created glossary.
     * @throws DeepLException
     */
    public function createMultilingualGlossary(
        string $name,
        array $dictionaries
    ): MultilingualGlossaryInfo {

        if (strlen($name) === 0) {
            throw new DeepLException('glossary name must be a non-empty string');
        }

        $params = [
            'name' => $name,
            'dictionaries' => array_map(function (MultilingualGlossaryDictionaryEntries $entries) {
                return $entries->toObject();
            }, $dictionaries),
        ];

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/glossaries',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseJson($content);
    }

    /**
     * Creates a new glossary on DeepL server with given name, languages, and entries.
     * @param string $name User-defined name to assign to the glossary.
     * @param string $sourceLang Language code of the glossary source terms.
     * @param string $targetLang Language code of the glossary target terms.
     * @param string $csvContent String containing CSV content.
     * @return MultilingualGlossaryInfo Details about the created glossary.
     * @throws DeepLException
     */
    public function createMultilingualGlossaryFromCsv(
        string $name,
        string $sourceLang,
        string $targetLang,
        string $csvContent
    ): MultilingualGlossaryInfo {

        if (strlen($name) === 0) {
            throw new DeepLException('glossary name must be a non-empty string');
        }

        $params = [
            'name' => $name,
            'dictionaries' => [
                [
                    'source_lang' => $sourceLang,
                    'target_lang' => $targetLang,
                    'entries_format' => 'csv',
                    'entries' => $csvContent,
                ]
            ]
        ];

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/glossaries',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseJson($content);
    }

    /**
     * Updates or creates a glossary dictionary with given entries for the
     * source and target languages. Either updates entries if they exist for
     * the given language pair, or adds new ones to the dictionary if not.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to update.
     * @param string|null $name Optional, new name for glossary.
     * @param array|null $dictionaries Optional, array of MultilingualGlossaryDictionaryEntries to update or add to
     *  glossary.
     * @return MultilingualGlossaryInfo Info about the updated glossary.
     * @throws DeepLException
     */
    public function updateMultilingualGlossary(
        $glossary,
        ?string $name,
        ?array $dictionaries
    ): MultilingualGlossaryInfo {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $params = [];
        if (isset($name)) {
            $params['name'] = $name;
        }
        if (isset($dictionaries) and count($dictionaries) > 0) {
            $params['dictionaries'] = array_map(function (MultilingualGlossaryDictionaryEntries $entries) {
                return $entries->toObject();
            }, $dictionaries);
        }

        $response = $this->client->sendRequestWithBackoff(
            'PATCH',
            "/v3/glossaries/$glossaryId",
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseJson($content);
    }

    /**
     * Replaces a glossary dictionary with given entries for the
     * source and target languages. Either replaces dictionary if one exists for
     * the given language pair, or adds new dictionary if not.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  update.
     * @param MultilingualGlossaryDictionaryEntries $dictionaryEntries Replacement dictionary with entries.
     * @return MultilingualGlossaryDictionaryInfo Info about the dictionary.
     * @throws DeepLException
     */
    public function replaceMultilingualGlossaryDictionary(
        $glossary,
        MultilingualGlossaryDictionaryEntries $dictionaryEntries
    ): MultilingualGlossaryDictionaryInfo {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $params = $dictionaryEntries->toObject();

        $response = $this->client->sendRequestWithBackoff(
            'PUT',
            "/v3/glossaries/$glossaryId/dictionaries",
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryDictionaryInfo::parseJson($content);
    }

    /**
     * Gets information about an existing glossary.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to get
     *  info.
     * @return MultilingualGlossaryInfo MultilingualGlossaryInfo containing details about the glossary.
     * @throws DeepLException
     */
    public function getMultilingualGlossary($glossary): MultilingualGlossaryInfo
    {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $response = $this->client->sendRequestWithBackoff('GET', "/v3/glossaries/$glossaryId");
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseJson($content);
    }

    /**
     * Gets information about all existing glossaries.
     * @return MultilingualGlossaryInfo[] Array of MultilingualGlossaryInfos containing details about all existing
     *  glossaries.
     * @throws DeepLException
     */
    public function listMultilingualGlossaries(): array
    {
        $response = $this->client->sendRequestWithBackoff('GET', '/v3/glossaries');
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseListJson($content);
    }

    /**
     * Retrieves the dictionary entries for a given source and target language in the given glossary.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  retrieve entries of.
     * @param string $sourceLang Language code of the glossary source terms.
     * @param string $targetLang Language code of the glossary target terms.
     * @return MultilingualGlossaryDictionaryEntries[] The entries stored in the dictionary.
     * @throws DeepLException
     */
    public function getMultilingualGlossaryEntries($glossary, string $sourceLang, string $targetLang): array
    {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $url = "/v3/glossaries/$glossaryId/entries?source_lang=$sourceLang&target_lang=$targetLang";
        $response = $this->client->sendRequestWithBackoff('GET', $url);
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryDictionaryEntries::parseJsonList($content);
    }

    /**
     * Deletes the glossary with the given glossary ID or MultilingualGlossaryInfo.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  be deleted.
     * @throws DeepLException
     */
    public function deleteMultilingualGlossary($glossary): void
    {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $response = $this->client->sendRequestWithBackoff('DELETE', "/v3/glossaries/$glossaryId");
        $this->checkStatusCode($response, false, true);
    }

    /**
     * Deletes specified glossary dictionary.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  be deleted.
     * @param MultilingualGlossaryDictionaryInfo|null $dictionary The dictionary to delete. Either the
     * MultilingualGlossaryDictionaryInfo or both the source_lang and target_lang
     * can be provided to identify the dictionary.
     * @param string|null $sourceLang Optional parameter representing the source language of the dictionary
     * @param string|null $targetLang Optional parameter representing the target language of the dictionary.
     * @throws DeepLException
     */
    public function deleteMultilingualGlossaryDictionary(
        $glossary,
        ?MultilingualGlossaryDictionaryInfo $dictionary,
        ?string $sourceLang = null,
        ?string $targetLang = null
    ): void {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);

        if (is_null($dictionary)) {
            if (is_null($sourceLang) or is_null($targetLang)) {
                throw new DeepLException('must provide dictionary or both source_lang and target_lang');
            }
        } else {
            $sourceLang = $dictionary->sourceLang;
            $targetLang = $dictionary->targetLang;
        }

        $url = "/v3/glossaries/$glossaryId/dictionaries?source_lang=$sourceLang&target_lang=$targetLang";
        $response = $this->client->sendRequestWithBackoff('DELETE', $url);
        $this->checkStatusCode($response, false, true);
    }

    /**
     * Validates and prepares HTTP parameters for rephrase requests.
     * @param string|string[] $texts Text(s) to rephrase
     * @param string|null $targetLang Target language code, or null to use default
     * @param string|null $style Writing style option, or null if not specified
     * @param string|null $tone Tone option, or null if not specified
     * @return array Associative array of HTTP parameters
     * @throws DeepLException
     */
    public function buildRephraseBodyParams(
        ?string $targetLang = null,
        ?string $style = null,
        ?string $tone = null
    ): array {
        if ($targetLang !== null) {
            $targetLang = LanguageCode::standardizeLanguageCode($targetLang);
            if ($targetLang === 'en') {
                throw new DeepLException('targetLang="en" is deprecated, please use "en-GB" or "en-US" instead.');
            } elseif ($targetLang === 'pt') {
                throw new DeepLException('targetLang="pt" is deprecated, please use "pt-PT" or "pt-BR" instead.');
            }
            $params['target_lang'] = $targetLang;
        }

        if ($style !== null) {
            $params['writing_style'] = $style;
        }

        if ($tone !== null) {
            $params['tone'] = $tone;
        }

        return $params;
    }

    /**
     * Retrieves a list of StyleRuleInfo for all available style rules.
     * @param int|null $page Page number for pagination, 0-indexed (optional).
     * @param int|null $pageSize Number of items per page (optional).
     * @param bool|null $detailed Whether to include detailed configuration rules (optional).
     * @return StyleRuleInfo[] List of StyleRuleInfo objects for all available style rules.
     * @throws DeepLException
     */
    public function getAllStyleRules(
        ?int $page = null,
        ?int $pageSize = null,
        ?bool $detailed = null
    ): array {
        $params = [];
        if ($page !== null) {
            $params['page'] = (string)$page;
        }
        if ($pageSize !== null) {
            $params['page_size'] = (string)$pageSize;
        }
        if ($detailed !== null) {
            $params['detailed'] = $detailed ? 'true' : 'false';
        }

        $queryString = '';
        if (!empty($params)) {
            $queryString = '?' . http_build_query($params);
        }

        $response = $this->client->sendRequestWithBackoff('GET', "/v3/style_rules$queryString");
        $this->checkStatusCode($response);
        list(, $content) = $response;

        return StyleRuleInfo::parseList($content);
    }

    /**
     * Creates a new style rule on DeepL server.
     * @param string $name User-defined name to assign to the style rule.
     * @param string $language Language code for the style rule.
     * @param array|null $configuredRules Optional configured rules to apply.
     * @param array|null $customInstructions Optional custom instructions to include.
     * @return StyleRuleInfo Details about the created style rule.
     * @throws DeepLException
     */
    public function createStyleRule(
        string $name,
        string $language,
        ?array $configuredRules = null,
        ?array $customInstructions = null
    ): StyleRuleInfo {
        if (strlen($name) === 0) {
            throw new DeepLException('name must be a non-empty string');
        }
        if (strlen($language) === 0) {
            throw new DeepLException('language must be a non-empty string');
        }

        $params = [
            'name' => $name,
            'language' => $language,
        ];
        if ($configuredRules !== null) {
            $params['configured_rules'] = empty($configuredRules) ? (object)$configuredRules : $configuredRules;
        }
        if ($customInstructions !== null) {
            $params['custom_instructions'] = $customInstructions;
        }

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/style_rules',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
        return StyleRuleInfo::fromJson($json);
    }

    /**
     * Gets information about an existing style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule to retrieve.
     * @return StyleRuleInfo StyleRuleInfo containing details about the style rule.
     * @throws DeepLException
     * @throws NotFoundException If the style rule is not found.
     */
    public function getStyleRule($styleRule): StyleRuleInfo
    {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new DeepLException('styleId must be a non-empty string');
        }
        $response = $this->client->sendRequestWithBackoff('GET', "/v3/style_rules/" . rawurlencode($styleId));
        $this->checkStatusCode($response);
        list(, $content) = $response;
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
        return StyleRuleInfo::fromJson($json);
    }

    /**
     * Updates the name of an existing style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule to update.
     * @param string $name New name for the style rule.
     * @return StyleRuleInfo Updated StyleRuleInfo.
     * @throws DeepLException
     * @throws NotFoundException If the style rule is not found.
     */
    public function updateStyleRuleName($styleRule, string $name): StyleRuleInfo
    {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new DeepLException('styleId must be a non-empty string');
        }
        if (strlen($name) === 0) {
            throw new DeepLException('name must be a non-empty string');
        }
        $params = ['name' => $name];

        $response = $this->client->sendRequestWithBackoff(
            'PATCH',
            "/v3/style_rules/" . rawurlencode($styleId),
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
        return StyleRuleInfo::fromJson($json);
    }

    /**
     * Deletes the style rule with the given style rule ID or StyleRuleInfo.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule to delete.
     * @throws DeepLException
     * @throws NotFoundException If the style rule is not found.
     */
    public function deleteStyleRule($styleRule): void
    {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new DeepLException('styleId must be a non-empty string');
        }
        $response = $this->client->sendRequestWithBackoff('DELETE', "/v3/style_rules/" . rawurlencode($styleId));
        $this->checkStatusCode($response);
    }

    /**
     * Replaces the configured rules for a style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule to update.
     * @param array $configuredRules The new configured rules to set.
     * @return StyleRuleInfo Updated StyleRuleInfo.
     * @throws DeepLException
     * @throws NotFoundException If the style rule is not found.
     */
    public function updateStyleRuleConfiguredRules($styleRule, array $configuredRules): StyleRuleInfo
    {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new DeepLException('styleId must be a non-empty string');
        }
        $params = empty($configuredRules) ? (object)$configuredRules : $configuredRules;

        $response = $this->client->sendRequestWithBackoff(
            'PUT',
            "/v3/style_rules/" . rawurlencode($styleId) . "/configured_rules",
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
        return StyleRuleInfo::fromJson($json);
    }

    /**
     * Creates a custom instruction for a style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule.
     * @param string $label Label for the custom instruction.
     * @param string $prompt Prompt text for the custom instruction.
     * @param string|null $sourceLanguage Optional source language code.
     * @return CustomInstruction The created custom instruction.
     * @throws DeepLException
     * @throws NotFoundException If the style rule is not found.
     */
    public function createStyleRuleCustomInstruction(
        $styleRule,
        string $label,
        string $prompt,
        ?string $sourceLanguage = null
    ): CustomInstruction {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new DeepLException('styleId must be a non-empty string');
        }
        if (strlen($label) === 0) {
            throw new DeepLException('label must be a non-empty string');
        }
        if (strlen($prompt) === 0) {
            throw new DeepLException('prompt must be a non-empty string');
        }
        $params = [
            'label' => $label,
            'prompt' => $prompt,
        ];
        if ($sourceLanguage !== null) {
            $params['source_language'] = $sourceLanguage;
        }

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            "/v3/style_rules/" . rawurlencode($styleId) . "/custom_instructions",
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
        return CustomInstruction::fromJson($json);
    }

    /**
     * Gets a custom instruction for a style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule.
     * @param string $instructionId ID of the custom instruction to retrieve.
     * @return CustomInstruction The custom instruction.
     * @throws DeepLException
     * @throws NotFoundException If the style rule or custom instruction is not found.
     */
    public function getStyleRuleCustomInstruction($styleRule, string $instructionId): CustomInstruction
    {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new DeepLException('styleId must be a non-empty string');
        }
        if (strlen($instructionId) === 0) {
            throw new DeepLException('instructionId must be a non-empty string');
        }
        $response = $this->client->sendRequestWithBackoff(
            'GET',
            "/v3/style_rules/" . rawurlencode($styleId) . "/custom_instructions/" . rawurlencode($instructionId)
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
        return CustomInstruction::fromJson($json);
    }

    /**
     * Updates a custom instruction for a style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule.
     * @param string $instructionId ID of the custom instruction to update.
     * @param string $label New label for the custom instruction.
     * @param string $prompt New prompt text for the custom instruction.
     * @param string|null $sourceLanguage Optional source language code.
     * @return CustomInstruction The updated custom instruction.
     * @throws DeepLException
     * @throws NotFoundException If the style rule or custom instruction is not found.
     */
    public function updateStyleRuleCustomInstruction(
        $styleRule,
        string $instructionId,
        string $label,
        string $prompt,
        ?string $sourceLanguage = null
    ): CustomInstruction {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new DeepLException('styleId must be a non-empty string');
        }
        if (strlen($instructionId) === 0) {
            throw new DeepLException('instructionId must be a non-empty string');
        }
        if (strlen($label) === 0) {
            throw new DeepLException('label must be a non-empty string');
        }
        if (strlen($prompt) === 0) {
            throw new DeepLException('prompt must be a non-empty string');
        }
        $params = [
            'label' => $label,
            'prompt' => $prompt,
        ];
        if ($sourceLanguage !== null) {
            $params['source_language'] = $sourceLanguage;
        }

        $response = $this->client->sendRequestWithBackoff(
            'PUT',
            "/v3/style_rules/" . rawurlencode($styleId) . "/custom_instructions/" . rawurlencode($instructionId),
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
        return CustomInstruction::fromJson($json);
    }

    /**
     * Deletes a custom instruction from a style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule.
     * @param string $instructionId ID of the custom instruction to delete.
     * @throws DeepLException
     * @throws NotFoundException If the style rule or custom instruction is not found.
     */
    public function deleteStyleRuleCustomInstruction($styleRule, string $instructionId): void
    {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new DeepLException('styleId must be a non-empty string');
        }
        if (strlen($instructionId) === 0) {
            throw new DeepLException('instructionId must be a non-empty string');
        }
        $response = $this->client->sendRequestWithBackoff(
            'DELETE',
            "/v3/style_rules/" . rawurlencode($styleId) . "/custom_instructions/" . rawurlencode($instructionId)
        );
        $this->checkStatusCode($response);
    }

    /**
     * Queries languages supported by the DeepL API for the given resource. Returns each
     * language with its per-feature support information.
     * @param string $resource Resource to query support for, see the RESOURCE_* constants on
     *  {@see LanguageResource}.
     * @param string[]|null $include Optional list of include values, for example
     *  {@see LanguageSupport::INCLUDE_BETA} or {@see LanguageSupport::INCLUDE_EXTERNAL}. By default only
     *  stable languages and features are returned.
     * @return LanguageSupport[] Array of LanguageSupport objects containing per-feature support information.
     * @throws DeepLException
     */
    public function getLanguagesForResource(string $resource, ?array $include = null): array
    {
        if (strlen($resource) === 0) {
            throw new DeepLException('resource must be a non-empty string');
        }

        $queryString = '?' . http_build_query(['resource' => $resource]);
        if ($include !== null) {
            // include is repeated as include=<value>; http_build_query would index it as include[0]=
            foreach ($include as $value) {
                $queryString .= '&include=' . urlencode($value);
            }
        }

        $response = $this->client->sendRequestWithBackoff('GET', "/v3/languages$queryString");
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return LanguageSupport::parseList($content);
    }

    /**
     * Queries the resources supported by the DeepL API, along with their features and
     * whether each feature requires support from the source/target language.
     * @return LanguageResource[] Array of LanguageResource objects, one per supported resource.
     * @throws DeepLException
     */
    public function getLanguageResources(): array
    {
        $response = $this->client->sendRequestWithBackoff('GET', '/v3/languages/resources');
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return LanguageResource::parseList($content);
    }

    /**
     * Retrieves a list of available translation memories. The maximum number of translation memories
     * returned is controlled by pageSize (max 25).
     * @param int|null $page Page number for pagination, 0-indexed (optional).
     * @param int|null $pageSize Number of items per page (optional).
     * @return TranslationMemoryInfo[] List of TranslationMemoryInfo objects for all available translation memories.
     * @throws DeepLException
     */
    public function listTranslationMemories(
        ?int $page = null,
        ?int $pageSize = null
    ): array {
        $queryParams = [];
        if ($page !== null) {
            $queryParams['page'] = $page;
        }
        if ($pageSize !== null) {
            $queryParams['page_size'] = $pageSize;
        }
        $queryString = empty($queryParams) ? '' : '?' . http_build_query($queryParams);

        $response = $this->client->sendRequestWithBackoff('GET', "/v3/translation_memories$queryString");
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return TranslationMemoryInfo::parseList($content);
    }

    /**
     * Retrieves a single translation memory by ID.
     * @param string|TranslationMemoryInfo $translationMemory Translation memory ID or
     *  TranslationMemoryInfo of the translation memory to retrieve.
     * @return TranslationMemoryInfo TranslationMemoryInfo containing details about the translation memory.
     * @throws DeepLException
     * @throws NotFoundException If the translation memory is not found.
     */
    public function getTranslationMemory($translationMemory): TranslationMemoryInfo
    {
        $translationMemoryId = $this->getTranslationMemoryId($translationMemory);
        $response = $this->client->sendRequestWithBackoff(
            'GET',
            "/v3/translation_memories/" . rawurlencode($translationMemoryId)
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return TranslationMemoryInfo::parse($content);
    }

    /**
     * Retrieves one page of the segments of a translation memory.
     *
     * Pagination is cursor-based: omit the page cursor on the first call, then pass the previous
     * response's nextPageCursor to fetch the next page. A null nextPageCursor means the last page
     * has been returned. Note that segmentCount is the translation-memory total and is not reduced
     * by the filter text.
     * @param string|TranslationMemoryInfo $translationMemory Translation memory ID or
     *  TranslationMemoryInfo of the translation memory to retrieve segments of.
     * @param array $options Pagination and filtering options. See \DeepL\TranslationMemorySegmentsOptions.
     * @return TranslationMemorySegments One page of segments.
     * @throws DeepLException
     * @throws NotFoundException If the translation memory is not found.
     * @see \DeepL\TranslationMemorySegmentsOptions
     */
    public function listTranslationMemorySegments($translationMemory, array $options = []): TranslationMemorySegments
    {
        $translationMemoryId = $this->getTranslationMemoryId($translationMemory);

        $queryParams = [];
        if (isset($options[TranslationMemorySegmentsOptions::PAGE_SIZE])) {
            $queryParams['page_size'] = $options[TranslationMemorySegmentsOptions::PAGE_SIZE];
        }
        if (isset($options[TranslationMemorySegmentsOptions::PAGE_CURSOR])) {
            $queryParams['page_cursor'] = $options[TranslationMemorySegmentsOptions::PAGE_CURSOR];
        }
        if (isset($options[TranslationMemorySegmentsOptions::FILTER_TEXT])) {
            $queryParams['filter_text'] = $options[TranslationMemorySegmentsOptions::FILTER_TEXT];
        }
        if (isset($options[TranslationMemorySegmentsOptions::FILTER_CASE_SENSITIVE])) {
            $queryParams['filter_case_sensitive'] =
                $options[TranslationMemorySegmentsOptions::FILTER_CASE_SENSITIVE] ? 'true' : 'false';
        }
        // PHP_QUERY_RFC3986 percent-encodes spaces in filter_text as %20. The default,
        // PHP_QUERY_RFC1738, encodes them as "+" — correct for a form body, but not for
        // a URI query string.
        $queryString = empty($queryParams)
            ? ''
            : '?' . http_build_query($queryParams, '', '&', \PHP_QUERY_RFC3986);

        $response = $this->client->sendRequestWithBackoff(
            'GET',
            "/v3/translation_memories/" . rawurlencode($translationMemoryId) . "/segments$queryString"
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return TranslationMemorySegments::parse($content);
    }

    /**
     * Deletes the translation memory with the given ID or TranslationMemoryInfo.
     * @param string|TranslationMemoryInfo $translationMemory Translation memory ID or
     *  TranslationMemoryInfo of the translation memory to be deleted.
     * @throws DeepLException
     * @throws NotFoundException If the translation memory is not found.
     */
    public function deleteTranslationMemory($translationMemory): void
    {
        $translationMemoryId = $this->getTranslationMemoryId($translationMemory);
        $response = $this->client->sendRequestWithBackoff(
            'DELETE',
            "/v3/translation_memories/" . rawurlencode($translationMemoryId)
        );
        $this->checkStatusCode($response);
    }

    /**
     * Creates an import job for a new translation memory.
     *
     * The job only declares the file; upload the TMX file itself to the returned upload URL with
     * uploadTranslationMemoryFile(), then poll getTranslationMemoryJob() for the outcome. Use
     * importTranslationMemoryFromFilepath() to do all three steps at once.
     * @param string $fileName Name of the TMX file to import, for example 'legal.tmx'.
     * @param int $contentLength Size of the TMX file in bytes.
     * @param string|null $contentType Optional MIME type of the file, defaults to 'application/xml'.
     * @param string|null $displayName Optional name for the resulting translation memory, defaults
     *  to the file name.
     * @return TranslationMemoryImport The job ID and upload URL.
     * @throws DeepLException
     */
    public function createTranslationMemoryImport(
        string $fileName,
        int $contentLength,
        ?string $contentType = null,
        ?string $displayName = null
    ): TranslationMemoryImport {
        if (strlen($fileName) === 0) {
            throw new DeepLException('fileName must be a non-empty string');
        }
        if ($contentLength <= 0) {
            throw new DeepLException('contentLength must be greater than 0');
        }

        $sourceFile = [
            'file_name' => $fileName,
            'content_length' => $contentLength,
        ];
        if ($contentType !== null) {
            $sourceFile['content_type'] = $contentType;
        }
        $params = ['source_file' => $sourceFile];
        if ($displayName !== null) {
            $params['parameters'] = ['display_name' => $displayName];
        }

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/translation_memories/import',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return TranslationMemoryImport::parse($content);
    }

    /**
     * Uploads a TMX file to the upload URL of an import job, which starts processing.
     *
     * Note that the upload URL is a pre-signed storage URL outside the DeepL API, so the
     * authentication key is not sent with this request.
     * @param string|TranslationMemoryImport $translationMemoryImport The import returned by
     *  createTranslationMemoryImport(), or its upload URL.
     * @param string $fileContent Content of the TMX file.
     * @param string $contentType MIME type of the file, which must match the content type declared
     *  when the import job was created. Defaults to 'application/xml'.
     * @throws DeepLException
     */
    public function uploadTranslationMemoryFile(
        $translationMemoryImport,
        string $fileContent,
        string $contentType = 'application/xml'
    ): void {
        $uploadUrl = is_string($translationMemoryImport)
            ? $translationMemoryImport
            : $translationMemoryImport->uploadUrl;
        if (strlen($uploadUrl) === 0) {
            throw new DeepLException('uploadUrl must be a non-empty string');
        }

        $response = $this->storageClient->sendRequestWithBackoff(
            'PUT',
            $uploadUrl,
            [
                HttpClientWrapper::OPTION_RAW_BODY => $fileContent,
                HttpClientWrapper::OPTION_HEADERS => ['Content-Type' => $contentType],
            ]
        );
        $this->checkAssetStatusCode($response, 'uploading translation memory file');
    }

    /**
     * Creates an export job for a translation memory.
     *
     * Poll getTranslationMemoryJob() for the download URL of the exported TMX file. Use
     * exportTranslationMemoryToFilepath() to do both steps and write the file at once.
     * @param string|TranslationMemoryInfo $translationMemory Translation memory ID or
     *  TranslationMemoryInfo of the translation memory to export.
     * @return TranslationMemoryExport The job ID, and whether the API reused a previously completed
     *  export.
     * @throws DeepLException
     * @throws NotFoundException If the translation memory is not found.
     */
    public function createTranslationMemoryExport($translationMemory): TranslationMemoryExport
    {
        $translationMemoryId = $this->getTranslationMemoryId($translationMemory);
        $response = $this->client->sendRequestWithBackoff(
            'POST',
            "/v3/translation_memories/" . rawurlencode($translationMemoryId) . "/export"
        );
        $this->checkStatusCode($response);
        list($statusCode, $content) = $response;
        // 200 means the API reused a previously completed export, 202 that it started a new one.
        return TranslationMemoryExport::parse($content, $statusCode === 200);
    }

    /**
     * Retrieves the status of a translation memory import or export job.
     * @param string|TranslationMemoryJob $job Job ID or TranslationMemoryJob of the job to query.
     * @return TranslationMemoryJob The current status of the job.
     * @throws DeepLException
     * @throws NotFoundException If the job is not found.
     */
    public function getTranslationMemoryJob($job): TranslationMemoryJob
    {
        $jobId = TranslationMemoryJob::getJobId($job);
        if (strlen($jobId) === 0) {
            throw new DeepLException('jobId must be a non-empty string');
        }
        $response = $this->client->sendRequestWithBackoff(
            'GET',
            "/v3/translation_memories/jobs/" . rawurlencode($jobId)
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return TranslationMemoryJob::parse($content);
    }

    /**
     * Returns when the given translation memory job finishes, or throws an exception if there was
     * an error communicating with the DeepL API or the job failed.
     *
     * Note that an import job keeps reporting 'awaiting_input' for a while after its file has been
     * uploaded, because the API detects the upload asynchronously. That status is therefore polled
     * through like any other non-terminal one. A job whose file is never uploaded does not finish
     * on its own, so pass $timeout when that is a possibility.
     * @param string|TranslationMemoryJob $job Job ID or TranslationMemoryJob of the job to wait for.
     * @param float|null $timeout Optional maximum time in seconds to wait before throwing an
     *  exception. Note that this is not accurate to the second, as the job is only polled every
     *  5 seconds.
     * @return TranslationMemoryJob The job once it has finished.
     * @throws DeepLException
     */
    public function waitUntilTranslationMemoryJobDone($job, ?float $timeout = null): TranslationMemoryJob
    {
        $jobId = TranslationMemoryJob::getJobId($job);
        $status = $this->getTranslationMemoryJob($jobId);
        $startTime = microtime(true);
        while (!$status->done()) {
            if ($timeout !== null && microtime(true) - $startTime > $timeout) {
                throw new DeepLException("Manual timeout of {$timeout}s exceeded for translation memory job");
            }
            // The job duration is not reported by the API, so just poll equidistantly
            $secs = 5.0;
            usleep($secs * 1000000);
            $this->client->logInfo("Rechecking translation memory job status after sleeping for $secs seconds.");
            $status = $this->getTranslationMemoryJob($jobId);
        }
        if (!$status->ok()) {
            $result = $status->result();
            throw new DeepLException(($result === null ? null : $result->errorMessage) ?? 'unknown error');
        }
        return $status;
    }

    /**
     * Downloads the TMX file of a completed export job to the specified output file path.
     *
     * Note that the download URL is a pre-signed storage URL outside the DeepL API, so the
     * authentication key is not sent with this request.
     * @param TranslationMemoryJob $job Completed export job carrying the download URL.
     * @param string $outputFile String containing file path to create the exported TMX file.
     * @throws DeepLException
     */
    public function downloadTranslationMemoryExport(TranslationMemoryJob $job, string $outputFile): void
    {
        $result = $job->result();
        $downloadUrl = $result === null ? null : $result->downloadUrl;
        if ($downloadUrl === null || strlen($downloadUrl) === 0) {
            throw new DeepLException(
                'translation memory export job has no download URL; it may not have completed yet'
            );
        }
        if (file_exists($outputFile)) {
            throw new DeepLException("File already exists at output file path $outputFile");
        }

        try {
            $response = $this->storageClient->sendRequestWithBackoff(
                'GET',
                $downloadUrl,
                [
                    HttpClientWrapper::OPTION_OUTFILE => $outputFile,
                ]
            );
            // With OPTION_OUTFILE the cURL path streams the body straight to the file and
            // returns true rather than the content, so on a non-2xx response the storage
            // service's error detail is in the file. Read it back for the error message,
            // otherwise the exception would just report "content: 1". Only on failure: the
            // file holds the whole export on success, and reading it would defeat streaming
            // it to disk in the first place.
            list($statusCode) = $response;
            $errorDetail = null;
            if (($statusCode < 200 || $statusCode >= 300) && file_exists($outputFile)) {
                $errorDetail = file_get_contents($outputFile) ?: null;
            }
            $this->checkAssetStatusCode($response, 'downloading translation memory export', $errorDetail);
        } catch (DeepLException $error) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            throw $error;
        }
    }

    /**
     * Imports a TMX file as a new translation memory: creates the import job, uploads the file, and
     * waits for processing to finish.
     * @param string $inputFile String containing file path of the TMX file to import.
     * @param string|null $displayName Optional name for the resulting translation memory, defaults
     *  to the file name.
     * @param float|null $timeout Optional maximum time in seconds to wait for the import to finish.
     *  Note that this is not accurate to the second, as the job is only polled every 5 seconds.
     * @return TranslationMemoryJob The completed import job; its result carries the ID of the new
     *  translation memory.
     * @throws DeepLException
     */
    public function importTranslationMemoryFromFilepath(
        string $inputFile,
        ?string $displayName = null,
        ?float $timeout = null
    ): TranslationMemoryJob {
        if (!file_exists($inputFile)) {
            throw new DeepLException("File does not exist at input file path $inputFile");
        }
        $fileContent = file_get_contents($inputFile);
        if ($fileContent === false) {
            throw new DeepLException("Failed reading input file $inputFile");
        }

        $created = $this->createTranslationMemoryImport(
            basename($inputFile),
            strlen($fileContent),
            null,
            $displayName
        );
        $this->uploadTranslationMemoryFile($created, $fileContent);
        return $this->waitUntilTranslationMemoryJobDone($created->jobId, $timeout);
    }

    /**
     * Exports a translation memory to a TMX file: creates the export job, waits for it to finish,
     * and writes the result to the specified output file path.
     * @param string|TranslationMemoryInfo $translationMemory Translation memory ID or
     *  TranslationMemoryInfo of the translation memory to export.
     * @param string $outputFile String containing file path to create the exported TMX file.
     * @param float|null $timeout Optional maximum time in seconds to wait for the export to finish.
     *  Note that this is not accurate to the second, as the job is only polled every 5 seconds.
     * @return TranslationMemoryJob The completed export job.
     * @throws DeepLException
     */
    public function exportTranslationMemoryToFilepath(
        $translationMemory,
        string $outputFile,
        ?float $timeout = null
    ): TranslationMemoryJob {
        $created = $this->createTranslationMemoryExport($translationMemory);
        $job = $this->waitUntilTranslationMemoryJobDone($created->jobId, $timeout);
        $this->downloadTranslationMemoryExport($job, $outputFile);
        return $job;
    }

    /**
     * Extracts and validates the translation memory ID of the given argument.
     * @param string|TranslationMemoryInfo $translationMemory Translation memory ID or
     *  TranslationMemoryInfo.
     * @throws DeepLException
     */
    private function getTranslationMemoryId($translationMemory): string
    {
        $translationMemoryId = TranslationMemoryInfo::getTranslationMemoryId($translationMemory);
        if (strlen($translationMemoryId) === 0) {
            throw new DeepLException('translationMemoryId must be a non-empty string');
        }
        return $translationMemoryId;
    }

    /**
     * Checks the HTTP status code of a request to a pre-signed storage URL. These requests are not
     * answered by the DeepL API, so the API error format does not apply.
     * @param array $response Status code and content, as returned by the HTTP client.
     * @param string $description Description of the attempted action, included in the message.
     * @param string|null $detailOverride Error detail to report instead of the response content,
     * used when the body was streamed to a file rather than returned.
     * @throws DeepLException
     */
    private function checkAssetStatusCode(
        array $response,
        string $description,
        ?string $detailOverride = null
    ): void {
        list($statusCode, $content) = $response;
        if (200 <= $statusCode && $statusCode < 300) {
            return;
        }
        $detail = $detailOverride ?? (is_string($content) ? $content : '');
        throw new DeepLException("Error $description, HTTP status: $statusCode, content: $detail");
    }
}
