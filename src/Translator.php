<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Wrapper for the DeepL API for language translation.
 * Create an instance of Translator to use the DeepL API.
 */
class Translator
{
    /**
     * Library version.
     */
    public const VERSION = '1.20.0';

    /**
     * Implements all HTTP requests and retries.
     */
    protected $client;

    /**
     * Client for requests to pre-signed storage URLs, which are served by the Asset Store rather
     * than the DeepL API. Constructed without the DeepL Authorization header — and without any
     * caller-configured headers — so credentials cannot reach a third-party host by mistake.
     * @var HttpClientWrapper
     */
    protected $storageClient;

    /**
     * Whether the enable_document_minification deprecation notice was already raised in this process.
     * @var bool
     */
    private static $minificationDeprecationTriggered = false;

    /**
     * Whether the isAuthKeyFreeAccount() deprecation notice was already raised in this process.
     * @var bool
     */
    private static $isAuthKeyFreeAccountDeprecationTriggered = false;

    /**
     * Deprecation notices for options and methods already raised in this process, by key.
     * @var array<string, bool>
     */
    private static $deprecationsTriggered = [];

    /**
     * Construct a Translator object wrapping the DeepL API using your authentication key.
     * This does not connect to the API, and returns immediately.
     * @param string $authKey Authentication key as specified in your account.
     * @param array $options Additional options controlling Translator behaviour.
     * @throws DeepLException
     * @see TranslatorOptions for a list of available request options.
     */
    public function __construct(string $authKey, array $options = [])
    {
        $authKey = trim($authKey);
        if ($authKey === '') {
            throw new DeepLException('authKey must be a non-empty string');
        }
        // Validation is currently only logging warnings
        $_ = TranslatorOptions::isValid($options);

        $serverUrl = ClientUtils::resolveServerUrl(
            $options,
            self::isFreeAccountAuthKey($authKey) ? TranslatorOptions::DEFAULT_SERVER_URL_FREE
                : TranslatorOptions::DEFAULT_SERVER_URL
        );

        $headers = ClientUtils::buildHeaders($authKey, $options);

        $this->client = ClientUtils::createHttpClient($serverUrl, $headers, $options);
        // Deliberately built from scratch rather than by subtracting Authorization from the
        // headers above: translation memory files are exchanged over pre-signed Asset Store URLs
        // outside the DeepL API, and a client that never holds the auth key cannot leak it. Only
        // the User-Agent is carried over; per-request headers such as Content-Type still apply.
        $this->storageClient = ClientUtils::createHttpClient(
            $serverUrl,
            ['User-Agent' => $headers['User-Agent']],
            $options
        );
    }

    /**
     * Queries character and document usage during the current billing period.
     * @return Usage
     * @throws DeepLException
     */
    public function getUsage(): Usage
    {
        $response = $this->client->sendRequestWithBackoff('GET', '/v2/usage');
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return new Usage($content);
    }

    /**
     * Queries source languages supported by the DeepL API.
     * @return Language[] Array of Language objects containing available source languages.
     * @throws DeepLException
     */
    public function getSourceLanguages(): array
    {
        return $this->getLanguages(false);
    }

    /**
     * Queries target languages supported by the DeepL API.
     * @return Language[] Array of Language objects containing available target languages.
     * @throws DeepLException
     */
    public function getTargetLanguages(): array
    {
        return $this->getLanguages(true);
    }

    /**
     * Queries languages supported for glossaries by the DeepL API.
     *
     * Note: v3 reports per-language usability rather than an explicit pair list, so pairs here
     * are the cross product of usable-as-source x usable-as-target languages (excluding
     * self-pairs). This assumes glossary support is a full symmetric cross product, which holds
     * today (verified against production) but is not guaranteed by the API contract.
     *
     * @return GlossaryLanguagePair[] Array of GlossaryLanguagePair objects containing available glossary languages.
     * @throws DeepLException
     */
    public function getGlossaryLanguages(): array
    {
        $response = $this->client->sendRequestWithBackoff(
            'GET',
            '/v3/languages?' . http_build_query(['resource' => LanguageResource::RESOURCE_GLOSSARY])
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;

        $languages = LanguageSupport::parseList($content);

        $sourceLangs = [];
        $targetLangs = [];
        foreach ($languages as $language) {
            if ($language->usableAsSource) {
                $sourceLangs[] = $language->code;
            }
            if ($language->usableAsTarget) {
                $targetLangs[] = $language->code;
            }
        }

        $result = [];
        foreach ($sourceLangs as $sourceLang) {
            foreach ($targetLangs as $targetLang) {
                if ($sourceLang === $targetLang) {
                    continue;
                }
                $result[] = new GlossaryLanguagePair($sourceLang, $targetLang);
            }
        }
        return $result;
    }

    /**
     * Translates specified text string or array of text strings into the target language.
     * @param string|string[] $texts A single string or array of strings containing input texts to translate.
     * @param string|null $sourceLang Language code of input text language, or null to use auto-detection.
     * @param string $targetLang Language code of language to translate into.
     * @param array $options Translation options to apply. See \DeepL\TranslateTextOptions.
     * @return TextResult|TextResult[] A TextResult or array of TextResult objects containing translated texts.
     * @phpstan-return ($texts is array ? TextResult[] : TextResult)
     * @throws DeepLException
     * @see \DeepL\TranslateTextOptions
     */
    public function translateText($texts, ?string $sourceLang, string $targetLang, array $options = [])
    {
        $params = $this->buildBodyParams(
            $sourceLang,
            $targetLang,
            $options[TranslateTextOptions::FORMALITY] ?? null,
            $options[TranslateTextOptions::GLOSSARY] ?? null,
            $options[TranslateTextOptions::GLOSSARY_IDS] ?? null
        );
        // Always send show_billed_characters=true, remove when the API default is changed to true
        $params["show_billed_characters"] = true;
        $this->validateAndAppendTexts($params, $texts);
        $this->validateAndAppendTextOptions($params, $options);

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v2/translate',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response);

        list(, $content) = $response;

        // Deepl API responses might have invalid UTF8 sequence
        // @see https://github.com/DeepLcom/deepl-php/pull/43
        // The substitute character is a process-wide setting, so the caller's value is restored.
        $previousSubstitute = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        try {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        } finally {
            mb_substitute_character($previousSubstitute);
        }

        $decoded = ParsingUtils::decodeJson($content);

        return ParsingUtils::guard(function () use ($decoded, $texts) {
            if (!is_array($decoded['translations'])) {
                throw ParsingUtils::invalidContent('Expected a translations list in the response');
            }
            $textResults = [];
            foreach ($decoded['translations'] as $textResult) {
                $textField = $textResult['text'];
                $detectedSourceLang = $textResult['detected_source_language'];
                $billedCharacters = $textResult['billed_characters'] ?? null;
                $modelTypeUsed = $textResult['model_type_used'] ?? null;
                $tagHandlingVersion = $textResult['tag_handling_version'] ?? null;
                $textResults[] = new TextResult(
                    $textField,
                    $detectedSourceLang,
                    $billedCharacters,
                    $modelTypeUsed,
                    is_string($tagHandlingVersion) ? $tagHandlingVersion : null
                );
            }
            return is_array($texts) ? $textResults : $textResults[0];
        });
    }

    /**
     * Uploads specified document to DeepL to translate into given target language, waits for translation to complete,
     * then downloads translated document to specified output path.
     * @param string $inputFile String containing file path of document to be translated.
     * @param string $outputFile String containing file path to create translated document.
     * @param string|null $sourceLang Language code of input document, or null to use auto-detection.
     * @param string $targetLang Language code of language to translate into.
     * @param array $options Translation options to apply. See \DeepL\TranslateDocumentOptions.
     * @return DocumentStatus DocumentStatus object for the completed translation. You can use the billedCharacters
     *     property to check how many characters were billed for the document.
     * @throws DocumentTranslationException If a file already exists at the output file path, or if any error occurs
     * during document upload, translation or download. The `handle` property of the exception, if not null,
     * may be used to recover the document ID and key of an in-progress translation. This includes the expiry of the
     * TranslateDocumentOptions::TIMEOUT option.
     * @see \DeepL\TranslateDocumentOptions
     */
    public function translateDocument(
        string $inputFile,
        string $outputFile,
        ?string $sourceLang,
        string $targetLang,
        array $options = []
    ): DocumentStatus {
        $handle = null;
        if (file_exists($outputFile)) {
            throw new DocumentTranslationException("File already exists at output file path $outputFile");
        }
        try {
            $minifier = null;
            // The media removed by minification can only be reinserted into a file of the input's format.
            $outputFormat = $options[TranslateDocumentOptions::OUTPUT_FORMAT] ?? null;
            $enableMinification = $options[TranslateDocumentOptions::ENABLE_DOCUMENT_MINIFICATION] ?? false;
            if ($enableMinification && !self::$minificationDeprecationTriggered) {
                self::$minificationDeprecationTriggered = true;
                @trigger_error(
                    'The enable_document_minification option is deprecated and will be removed in the next major ' .
                    'version. The DeepL API accepts larger documents; see ' .
                    'https://developers.deepl.com/docs/resources/usage-limits.',
                    E_USER_DEPRECATED
                );
            }
            $willMinify = $enableMinification &&
                DocumentMinifier::canMinifyFile($inputFile) &&
                ($outputFormat === null ||
                    strcasecmp($outputFormat, pathinfo($inputFile, PATHINFO_EXTENSION)) === 0);
            $fileToUpload = $inputFile;
            if ($willMinify) {
                $minifier = new DocumentMinifier();
                $minifier->minifyDocument($inputFile, true);
                $fileToUpload = $minifier->getMinifiedDocFile($inputFile);
            }
            $handle = $this->translateDocumentUpload($fileToUpload, $sourceLang, $targetLang, $options);
            $status = $this->translateDocumentWaitUntilDone(
                $handle,
                [TranslateDocumentOptions::TIMEOUT => $options[TranslateDocumentOptions::TIMEOUT] ?? null]
            );
            $this->translateDocumentDownload($handle, $outputFile);
            if ($willMinify) {
                // Translated minified file is at `$outputFile`. Reinsert media (deminify) before returning
                $minifier->deminifyDocument($outputFile, $outputFile, true);
            }
            return $status;
        } catch (DeepLException $error) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            $message = 'Error occurred while translating document: ' . ($error->getMessage() ?? 'unknown error');
            throw new DocumentTranslationException($message, $error->getCode(), $error, $handle);
        }
    }

    /**
     * Uploads specified document to DeepL to translate into target language, and returns handle associated with the
     * document.
     * @param string $inputFile String containing file path of document to be translated.
     * @param string|null $sourceLang Language code of input document, or null to use auto-detection.
     * @param string $targetLang Language code of language to translate into.
     * @param array $options Translation options to apply. See \DeepL\TranslateDocumentOptions.
     * @return DocumentHandle Handle associated with the in-progress document translation.
     * @throws DeepLException
     * @deprecated Use translateDocumentUpload() instead; uploadDocument() will be removed in the next major version.
     */
    public function uploadDocument(
        string $inputFile,
        ?string $sourceLang,
        string $targetLang,
        array $options = []
    ): DocumentHandle {
        self::triggerDeprecation(
            'uploadDocument',
            'Translator::uploadDocument() is deprecated, use Translator::translateDocumentUpload() instead.'
        );
        return $this->translateDocumentUpload($inputFile, $sourceLang, $targetLang, $options);
    }

    /**
     * Uploads specified document to DeepL to translate into target language, and returns handle associated with the
     * document. Use translateDocumentWaitUntilDone() and translateDocumentDownload() to complete the translation.
     * @param string $inputFile String containing file path of document to be translated.
     * @param string|null $sourceLang Language code of input document, or null to use auto-detection.
     * @param string $targetLang Language code of language to translate into.
     * @param array $options Translation options to apply. See \DeepL\TranslateDocumentOptions.
     * @return DocumentHandle Handle associated with the in-progress document translation.
     * @throws DeepLException
     */
    public function translateDocumentUpload(
        string $inputFile,
        ?string $sourceLang,
        string $targetLang,
        array $options = []
    ): DocumentHandle {
        if (isset($options[TranslateDocumentOptions::STYLE_ID])) {
            self::triggerDeprecation(
                'document:' . TranslateDocumentOptions::STYLE_ID,
                'TranslateDocumentOptions::STYLE_ID is deprecated, use TranslateDocumentOptions::STYLE_RULE instead.'
            );
        }
        if (isset($options[TranslateDocumentOptions::TRANSLATION_MEMORY_ID])) {
            self::triggerDeprecation(
                'document:' . TranslateDocumentOptions::TRANSLATION_MEMORY_ID,
                'TranslateDocumentOptions::TRANSLATION_MEMORY_ID is deprecated, use ' .
                'TranslateDocumentOptions::TRANSLATION_MEMORY instead.'
            );
        }
        $params = $this->buildBodyParams(
            $sourceLang,
            $targetLang,
            $options[TranslateDocumentOptions::FORMALITY] ?? null,
            $options[TranslateDocumentOptions::GLOSSARY] ?? null,
            $options[TranslateDocumentOptions::GLOSSARY_IDS] ?? null
        );

        if (isset($options[TranslateDocumentOptions::OUTPUT_FORMAT])) {
            $params['output_format'] = $options[TranslateDocumentOptions::OUTPUT_FORMAT];
        }
        if (isset($options[TranslateDocumentOptions::ENABLE_WATERMARK])) {
            $params['enable_watermark'] = $options[TranslateDocumentOptions::ENABLE_WATERMARK] ? 'true' : 'false';
        }
        $this->appendStyleRuleAndTranslationMemoryOptions($params, $options);
        // The document request body is sent as multipart/form-data, where every field must be a
        // string. Unlike the JSON-encoded text request, the threshold must therefore be
        // stringified, and glossary_ids must be sent as a comma-separated string.
        if (isset($params['translation_memory_threshold'])) {
            $params['translation_memory_threshold'] = (string)$params['translation_memory_threshold'];
        }
        if (isset($params['glossary_ids'])) {
            $params['glossary_ids'] = implode(',', $params['glossary_ids']);
        }

        $this->applyExtraBodyParameters(
            $params,
            $options[TranslateDocumentOptions::EXTRA_BODY_PARAMETERS] ?? null,
            true
        );

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v2/document',
            [
                HttpClientWrapper::OPTION_PARAMS => $params,
                HttpClientWrapper::OPTION_FILE => $inputFile,
                HttpClientWrapper::OPTION_FILENAME =>
                    $options[TranslateDocumentOptions::FILENAME] ?? basename($inputFile),
            ]
        );
        $this->checkStatusCode($response);

        list(, $content) = $response;
        $json = ParsingUtils::decodeJson($content);

        return ParsingUtils::guard(function () use ($json): DocumentHandle {
            if (!is_string($json['document_id'] ?? null) || !is_string($json['document_key'] ?? null)) {
                throw ParsingUtils::invalidContent('Expected document_id and document_key strings in the response');
            }
            return new DocumentHandle($json['document_id'], $json['document_key']);
        });
    }

    /**
     * Retrieves the status of the document translation associated with the given document handle.
     * @param DocumentHandle $handle Document handle associated with document.
     * @return DocumentStatus The document translation status.
     * @throws DeepLException
     * @deprecated Use translateDocumentGetStatus() instead; getDocumentStatus() will be removed in the next major
     *     version.
     */
    public function getDocumentStatus(DocumentHandle $handle): DocumentStatus
    {
        self::triggerDeprecation(
            'getDocumentStatus',
            'Translator::getDocumentStatus() is deprecated, use Translator::translateDocumentGetStatus() instead.'
        );
        return $this->translateDocumentGetStatus($handle);
    }

    /**
     * Retrieves the status of the document translation associated with the given document handle.
     * @param DocumentHandle $handle Document handle associated with document.
     * @return DocumentStatus The document translation status.
     * @throws DeepLException
     */
    public function translateDocumentGetStatus(DocumentHandle $handle): DocumentStatus
    {
        $documentId = rawurlencode($handle->documentId);
        $response = $this->client->sendRequestWithBackoff(
            'POST',
            "/v2/document/$documentId",
            [HttpClientWrapper::OPTION_PARAMS => ['document_key' => $handle->documentKey]]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return new DocumentStatus($content);
    }

    /**
     * Downloads the translated document associated with the given document handle to the specified output file path.
     * @param DocumentHandle $handle Document handle associated with document.
     * @param string $outputFile String containing file path to create translated document.
     * @throws DeepLException
     * @deprecated Use translateDocumentDownload() instead; downloadDocument() will be removed in the next major
     *     version.
     */
    public function downloadDocument(DocumentHandle $handle, string $outputFile): void
    {
        self::triggerDeprecation(
            'downloadDocument',
            'Translator::downloadDocument() is deprecated, use Translator::translateDocumentDownload() instead.'
        );
        $this->translateDocumentDownload($handle, $outputFile);
    }

    /**
     * Downloads the translated document associated with the given document handle to the specified output file path.
     * @param DocumentHandle $handle Document handle associated with document.
     * @param string $outputFile String containing file path to create translated document.
     * @throws DeepLException
     */
    public function translateDocumentDownload(DocumentHandle $handle, string $outputFile): void
    {
        if (file_exists($outputFile)) {
            throw new DeepLException("File already exists at output file path $outputFile");
        }
        try {
            $documentId = rawurlencode($handle->documentId);
            $response = $this->client->sendRequestWithBackoff(
                'POST',
                "/v2/document/$documentId/result",
                [
                    HttpClientWrapper::OPTION_PARAMS => ['document_key' => $handle->documentKey],
                    HttpClientWrapper::OPTION_OUTFILE => $outputFile,
                ]
            );
            $this->checkStatusCode($response, true);
        } catch (DeepLException $error) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            throw $error;
        }
    }

    /**
     * Returns when the given document translation completes, or throws an exception if there was an error
     * communicating with the DeepL API or the document translation failed.
     * @param DocumentHandle $handle Handle to the document translation.
     * @return DocumentStatus DocumentStatus object for the completed translation. You can use the billedCharacters
     *     property to check how many characters were billed for the document.
     * @throws DeepLException
     * @deprecated Use translateDocumentWaitUntilDone() instead; waitUntilDocumentTranslationComplete() will be
     *     removed in the next major version.
     */
    public function waitUntilDocumentTranslationComplete(DocumentHandle $handle): DocumentStatus
    {
        self::triggerDeprecation(
            'waitUntilDocumentTranslationComplete',
            'Translator::waitUntilDocumentTranslationComplete() is deprecated, use ' .
            'Translator::translateDocumentWaitUntilDone() instead.'
        );
        return $this->translateDocumentWaitUntilDone($handle);
    }

    /**
     * Returns when the given document translation completes, or throws an exception if there was an error
     * communicating with the DeepL API, the document translation failed, or the timeout expired.
     * @param DocumentHandle $handle Handle to the document translation.
     * @param array $options Wait options: TranslateDocumentOptions::TIMEOUT, the maximum time to wait in seconds
     *     (float). Unset or null waits without limit. The status is polled every 5 seconds, so the wait may exceed
     *     the timeout by up to that interval.
     * @return DocumentStatus DocumentStatus object for the completed translation. You can use the billedCharacters
     *     property to check how many characters were billed for the document.
     * @throws DeepLException
     */
    public function translateDocumentWaitUntilDone(DocumentHandle $handle, array $options = []): DocumentStatus
    {
        $timeout = $options[TranslateDocumentOptions::TIMEOUT] ?? null;
        if ($timeout !== null && (!is_numeric($timeout) || $timeout < 0)) {
            throw new DeepLException('timeout must be a non-negative number of seconds');
        }
        $startTime = microtime(true);
        $status = $this->translateDocumentGetStatus($handle);
        while (!$status->done() && $status->ok()) {
            if ($timeout !== null && microtime(true) - $startTime >= $timeout) {
                throw new DeepLException("Timeout of {$timeout}s exceeded while waiting for document translation");
            }
            // secondsRemaining is currently unreliable, so just poll equidistantly
            $secs = 5.0;
            usleep($secs * 1000000);
            $this->client->logInfo("Rechecking document translation status after sleeping for $secs seconds.");
            $status = $this->translateDocumentGetStatus($handle);
        }
        if (!$status->ok()) {
            throw new DeepLException($status->errorMessage ?? 'unknown error');
        }
        return $status;
    }

    /**
     * Creates a new glossary on DeepL server with given name, languages, and entries.
     * @param string $name User-defined name to assign to the glossary.
     * @param string $sourceLang Language code of the glossary source terms.
     * @param string $targetLang Language code of the glossary target terms.
     * @param GlossaryEntries $entries The source- & target-term pairs to add to the glossary.
     * @return GlossaryInfo Details about the created glossary.
     * @throws DeepLException
     */
    public function createGlossary(
        string $name,
        string $sourceLang,
        string $targetLang,
        GlossaryEntries $entries
    ): GlossaryInfo {
        // Glossaries are only supported for base language types
        $sourceLang = LanguageCode::removeRegionalVariant($sourceLang);
        $targetLang = LanguageCode::removeRegionalVariant($targetLang);

        if (strlen($name) === 0) {
            throw new DeepLException('glossary name must be a non-empty string');
        }

        $params = [
            'name' => $name,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'entries_format' => 'tsv',
            'entries' => $entries->convertToTsv(),
        ];

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v2/glossaries',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return GlossaryInfo::parse($content);
    }

    /**
     * Creates a new glossary on DeepL server with given name, languages, and entries.
     * @param string $name User-defined name to assign to the glossary.
     * @param string $sourceLang Language code of the glossary source terms.
     * @param string $targetLang Language code of the glossary target terms.
     * @param string $csvContent String containing CSV content.
     * @return GlossaryInfo Details about the created glossary.
     * @throws DeepLException
     */
    public function createGlossaryFromCsv(
        string $name,
        string $sourceLang,
        string $targetLang,
        string $csvContent
    ): GlossaryInfo {
        // Glossaries are only supported for base language types
        $sourceLang = LanguageCode::removeRegionalVariant($sourceLang);
        $targetLang = LanguageCode::removeRegionalVariant($targetLang);

        if (strlen($name) === 0) {
            throw new DeepLException('glossary name must be a non-empty string');
        }

        $params = [
            'name' => $name,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'entries_format' => 'csv',
            'entries' => $csvContent,
        ];

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v2/glossaries',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return GlossaryInfo::parse($content);
    }

    /**
     * Gets information about an existing glossary.
     * @param string $glossaryId Glossary ID of the glossary.
     * @return GlossaryInfo GlossaryInfo containing details about the glossary.
     * @throws DeepLException
     */
    public function getGlossary(string $glossaryId): GlossaryInfo
    {
        $response = $this->client->sendRequestWithBackoff('GET', "/v2/glossaries/$glossaryId");
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return GlossaryInfo::parse($content);
    }

    /**
     * Gets information about all existing glossaries.
     * @return GlossaryInfo[] Array of GlossaryInfos containing details about all existing glossaries.
     * @throws DeepLException
     */
    public function listGlossaries(): array
    {
        $response = $this->client->sendRequestWithBackoff('GET', '/v2/glossaries');
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return GlossaryInfo::parseList($content);
    }

    /**
     * Retrieves the entries stored with the glossary with the given glossary ID or GlossaryInfo.
     * @param string|GlossaryInfo $glossary Glossary ID or GlossaryInfo of glossary to retrieve entries of.
     * @return GlossaryEntries The entries stored in the glossary.
     * @throws DeepLException
     */
    public function getGlossaryEntries($glossary): GlossaryEntries
    {
        $glossaryId = is_string($glossary) ? $glossary : $glossary->glossaryId;
        $response = $this->client->sendRequestWithBackoff('GET', "/v2/glossaries/$glossaryId/entries");
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return GlossaryEntries::fromTsv($content);
    }

    /**
     * Deletes the glossary with the given glossary ID or GlossaryInfo.
     * @param string|GlossaryInfo $glossary Glossary ID or GlossaryInfo of glossary to be deleted.
     * @throws DeepLException
     */
    public function deleteGlossary($glossary): void
    {
        $glossaryId = is_string($glossary) ? $glossary : $glossary->glossaryId;
        $response = $this->client->sendRequestWithBackoff('DELETE', "/v2/glossaries/$glossaryId");
        $this->checkStatusCode($response, false, true);
    }

    /**
     * Queries source or target languages supported by the DeepL API.
     * @param bool $target Query target languages if true, source languages otherwise.
     * @return Language[] Array of Language objects containing available languages.
     * @throws DeepLException
     */
    private function getLanguages(bool $target): array
    {
        $response = $this->client->sendRequestWithBackoff(
            'GET',
            '/v3/languages?' . http_build_query(['resource' => LanguageResource::RESOURCE_TRANSLATE_TEXT])
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;

        $languages = LanguageSupport::parseList($content);

        $result = [];
        foreach ($languages as $language) {
            if ($target ? !$language->usableAsTarget : !$language->usableAsSource) {
                continue;
            }
            $supportsFormality = $target ? $language->supports(LanguageFeature::FEATURE_FORMALITY) : null;
            $result[] = new Language($language->name, strtoupper($language->code), $supportsFormality);
        }
        return $result;
    }

    /**
     * Converts the given TagList to an array of tag strings.
     * @param string[]|string $tagList List of tags as an array or comma-separated string.
     * @return string[] Tags as an array of strings.
     */
    private function toTagList($tagList): array
    {
        if (is_string($tagList)) {
            return $tagList === '' ? [] : explode(',', $tagList);
        } else {
            return $tagList;
        }
    }

    /**
     * Validates and prepares HTTP parameters for arguments common to text and document translation.
     * @param string|null $sourceLang Source language code, or null to use auto-detection.
     * @param string $targetLang Target language code.
     * @param string|null $formality Formality option, or null if not specified.
     * @param string|GlossaryInfo|MultilingualGlossaryInfo|null $glossary Glossary ID, GlossaryInfo,
     *  MultilingualGlossaryInfo, or null if not specified.
     * @param (string|GlossaryInfo|MultilingualGlossaryInfo)[]|null $glossaryIds Array of up to 5 glossary IDs,
     *  GlossaryInfos, or MultilingualGlossaryInfos, or null if not specified.
     * @return array Associative array of HTTP parameters.
     * @throws DeepLException
     */
    private function buildBodyParams(
        ?string $sourceLang,
        string $targetLang,
        ?string $formality,
        $glossary,
        ?array $glossaryIds = null
    ): array {
        $targetLang = LanguageCode::standardizeLanguageCode($targetLang);
        if (isset($sourceLang)) {
            $sourceLang = LanguageCode::standardizeLanguageCode($sourceLang);
        }

        $params = ['target_lang' => $targetLang];
        if (isset($sourceLang)) {
            $params['source_lang'] = $sourceLang;
        }
        if (isset($formality)) {
            $formality_str = strtolower($formality);
            $params['formality'] = $formality_str;
        }
        if (isset($glossary)) {
            if (!isset($sourceLang)) {
                throw new DeepLException('sourceLang is required if using a glossary');
            }
            if (!is_string($glossary)) {
                $glossary = $glossary->glossaryId;
            }
            $params['glossary_id'] = $glossary;
        }
        if (isset($glossaryIds)) {
            if (isset($glossary)) {
                throw new DeepLException(
                    'The glossary_ids option cannot be used together with the singular glossary option'
                );
            }
            if (!isset($sourceLang)) {
                throw new DeepLException('sourceLang is required if using glossary_ids');
            }
            if (count($glossaryIds) > 5) {
                throw new DeepLException('A maximum of 5 glossary IDs can be specified in glossary_ids');
            }
            $ids = array_map(
                function ($glossaryId) {
                    if (is_string($glossaryId)) {
                        return $glossaryId;
                    }
                    if (is_object($glossaryId) && isset($glossaryId->glossaryId)) {
                        return $glossaryId->glossaryId;
                    }
                    throw new DeepLException(
                        'Each glossary_ids entry must be a glossary ID string or a ' .
                        'GlossaryInfo/MultilingualGlossaryInfo object'
                    );
                },
                $glossaryIds
            );
            // The API expects glossary_ids as an array (a JSON array in the JSON translate
            // body). The multipart/form-data document request stringifies it in translateDocumentUpload.
            $params['glossary_ids'] = array_values($ids);
        }
        return $params;
    }

    /**
     * Validates and appends texts to HTTP request parameters.
     * @param array $params Parameters for HTTP request.
     * @param string|string[] $texts User-supplied texts to be checked.
     * @throws DeepLException
     */
    protected function validateAndAppendTexts(array &$params, $texts)
    {
        if (is_array($texts)) {
            foreach ($texts as $text) {
                if (!is_string($text)) {
                    throw new DeepLException(
                        'texts parameter must be a string or array of strings',
                    );
                }
            }
            $params['text'] = $texts;
        } else {
            if (!is_string($texts)) {
                throw new DeepLException(
                    'texts parameter must be a string or array of strings',
                );
            }
            $params['text'] = [$texts];
        }
    }

    /**
     * Validates and appends text options to HTTP request parameters.
     * @param array $params Parameters for HTTP request.
     * @param array|null $options Options for translate text request.
     * Note the formality and glossary options are handled separately, because these options overlap with document
     * translation.
     * @throws DeepLException
     */
    private function validateAndAppendTextOptions(array &$params, ?array $options): void
    {
        if ($options === null) {
            return;
        }
        if (isset($options[TranslateTextOptions::SPLIT_SENTENCES])) {
            // Lower-cased as in released versions: the API rejects upper-case values.
            $splitSentences = strtolower($options[TranslateTextOptions::SPLIT_SENTENCES]);
            $aliases = ['on' => SentenceSplittingMode::ALL, 'default' => SentenceSplittingMode::ALL,
                'off' => SentenceSplittingMode::OFF];
            if (isset($aliases[$splitSentences])) {
                self::triggerDeprecation(
                    'split_sentences',
                    "The split_sentences values 'on', 'default' and 'off' are deprecated, use the " .
                    "SentenceSplittingMode values '1', '0' or 'nonewlines' instead."
                );
                $splitSentences = $aliases[$splitSentences];
            }
            $params[TranslateTextOptions::SPLIT_SENTENCES] = $splitSentences;
        }
        if (isset($options[TranslateTextOptions::PRESERVE_FORMATTING])) {
            $params[TranslateTextOptions::PRESERVE_FORMATTING] =
                (bool)$options[TranslateTextOptions::PRESERVE_FORMATTING];
        }
        if (isset($options[TranslateTextOptions::TAG_HANDLING])) {
            $params[TranslateTextOptions::TAG_HANDLING] = $options[TranslateTextOptions::TAG_HANDLING];
        }
        if (isset($options[TranslateTextOptions::TAG_HANDLING_VERSION])) {
            $params[TranslateTextOptions::TAG_HANDLING_VERSION] = $options[TranslateTextOptions::TAG_HANDLING_VERSION];
        }
        if (isset($options[TranslateTextOptions::OUTLINE_DETECTION])) {
            $params[TranslateTextOptions::OUTLINE_DETECTION] =
                (bool)$options[TranslateTextOptions::OUTLINE_DETECTION];
        }
        if (isset($options[TranslateTextOptions::CONTEXT])) {
            $params[TranslateTextOptions::CONTEXT] = $options[TranslateTextOptions::CONTEXT];
        }
        if (isset($options[TranslateTextOptions::MODEL_TYPE])) {
            $params[TranslateTextOptions::MODEL_TYPE] = $options[TranslateTextOptions::MODEL_TYPE];
        }
        if (isset($options[TranslateTextOptions::NON_SPLITTING_TAGS])) {
            $params[TranslateTextOptions::NON_SPLITTING_TAGS] =
                $this->toTagList($options[TranslateTextOptions::NON_SPLITTING_TAGS]);
        }
        if (isset($options[TranslateTextOptions::SPLITTING_TAGS])) {
            $params[TranslateTextOptions::SPLITTING_TAGS] =
                $this->toTagList($options[TranslateTextOptions::SPLITTING_TAGS]);
        }
        if (isset($options[TranslateTextOptions::IGNORE_TAGS])) {
            $params[TranslateTextOptions::IGNORE_TAGS] =
                $this->toTagList($options[TranslateTextOptions::IGNORE_TAGS]);
        }
        if (isset($options[TranslateTextOptions::STYLE_ID])) {
            self::triggerDeprecation(
                TranslateTextOptions::STYLE_ID,
                'TranslateTextOptions::STYLE_ID is deprecated, use TranslateTextOptions::STYLE_RULE instead.'
            );
        }
        if (isset($options[TranslateTextOptions::TRANSLATION_MEMORY_ID])) {
            self::triggerDeprecation(
                TranslateTextOptions::TRANSLATION_MEMORY_ID,
                'TranslateTextOptions::TRANSLATION_MEMORY_ID is deprecated, use ' .
                'TranslateTextOptions::TRANSLATION_MEMORY instead.'
            );
        }
        $this->appendStyleRuleAndTranslationMemoryOptions($params, $options);
        if (isset($options[TranslateTextOptions::CUSTOM_INSTRUCTIONS])) {
            $params[TranslateTextOptions::CUSTOM_INSTRUCTIONS] = $options[TranslateTextOptions::CUSTOM_INSTRUCTIONS];
        }
        $this->applyExtraBodyParameters(
            $params,
            $options[TranslateTextOptions::EXTRA_BODY_PARAMETERS] ?? null
        );
    }

    /**
     * Validates and appends the style-rule, style-profile and translation-memory options to HTTP request parameters.
     * These options share the same constant values between text and document translation, so this
     * helper is used by both the text and document request builders.
     * @param array $params Parameters for HTTP request.
     * @param array|null $options Options for the translate request.
     * @throws DeepLException
     */
    private function appendStyleRuleAndTranslationMemoryOptions(array &$params, ?array $options): void
    {
        if ($options === null) {
            return;
        }
        $styleRuleKey = isset($options[TranslateTextOptions::STYLE_RULE]) ? TranslateTextOptions::STYLE_RULE
            : TranslateTextOptions::STYLE_ID;
        $styleRule = $options[$styleRuleKey] ?? null;
        if (isset($styleRule)) {
            if (is_string($styleRule)) {
                $params['style_id'] = $styleRule;
            } elseif ($styleRule instanceof StyleRuleInfo) {
                $params['style_id'] = $styleRule->styleId;
            } else {
                throw new DeepLException("$styleRuleKey must be a string or StyleRuleInfo object");
            }
        }
        if (isset($options[TranslateTextOptions::STYLE_PROFILE])) {
            $styleProfile = $options[TranslateTextOptions::STYLE_PROFILE];
            if (is_string($styleProfile)) {
                $params['style_profile_id'] = $styleProfile;
            } elseif ($styleProfile instanceof StyleProfile) {
                $params['style_profile_id'] = $styleProfile->styleProfileId;
            } else {
                throw new DeepLException('style_profile must be a string or StyleProfile object');
            }
        }
        $tmKey = isset($options[TranslateTextOptions::TRANSLATION_MEMORY]) ? TranslateTextOptions::TRANSLATION_MEMORY
            : TranslateTextOptions::TRANSLATION_MEMORY_ID;
        $tm = $options[$tmKey] ?? null;
        if (isset($tm)) {
            if (is_string($tm)) {
                $params['translation_memory_id'] = $tm;
            } elseif ($tm instanceof TranslationMemoryInfo) {
                $params['translation_memory_id'] = $tm->translationMemoryId;
            } else {
                throw new DeepLException("$tmKey must be a string or TranslationMemoryInfo object");
            }
        }
        if (isset($options[TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD])) {
            if (!isset($tm)) {
                throw new DeepLException("translation_memory_threshold requires $tmKey");
            }
            $threshold = $options[TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD];
            if (!is_int($threshold) || $threshold < 0 || $threshold > 100) {
                throw new DeepLException('translation_memory_threshold must be an integer between 0 and 100');
            }
            $params['translation_memory_threshold'] = $threshold;
        }
    }

    private static function triggerDeprecation(string $key, string $message): void
    {
        if (!isset(self::$deprecationsTriggered[$key])) {
            self::$deprecationsTriggered[$key] = true;
            @trigger_error($message, E_USER_DEPRECATED);
        }
    }

    /**
     * Adds extra body parameters to the params array. Extra parameters can override existing keys.
     * Values are kept as given, for a JSON body. For a form body, booleans become "true"/"false", arrays and
     * objects their JSON encoding, and other values strings. Objects with __toString that are not
     * JsonSerializable are sent as their string in both cases.
     */
    private function applyExtraBodyParameters(array &$params, ?array $extraParams, bool $formBody = false): void
    {
        if ($extraParams !== null) {
            foreach ($extraParams as $key => $value) {
                if (self::isStringableObject($value)) {
                    // Objects with __toString were sent as their string before values kept their JSON type
                    $params[$key] = (string)$value;
                } else {
                    $params[$key] = $formBody ? self::toFormValue($value) : $value;
                }
            }
        }
    }

    /**
     * @param mixed $value
     */
    private static function isStringableObject($value): bool
    {
        return is_object($value) && method_exists($value, '__toString') && !($value instanceof \JsonSerializable);
    }

    /**
     * @param mixed $value
     * @throws DeepLException
     */
    private static function toFormValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value);
            if ($encoded === false) {
                throw new DeepLException(
                    'Failed to JSON-encode an extra_body_parameters value: ' . json_last_error_msg()
                );
            }
            return $encoded;
        }
        return (string)$value;
    }

    /**
     * Checks the HTTP status code, and in case of failure, throws an exception with diagnostic information.
     * @throws DeepLException
     */
    protected function checkStatusCode(array $response, bool $inDocumentDownload = false, bool $usingGlossary = false)
    {
        ClientUtils::checkStatusCode($response, $inDocumentDownload, $usingGlossary);
    }

    /**
     * Returns true if the specified DeepL Authentication Key is associated with a free account,
     * otherwise false.
     * @param string $authKey The authentication key to check.
     * @return bool True if the key is associated with a free account, otherwise false.
     */
    public static function isFreeAccountAuthKey(string $authKey): bool
    {
        return substr(trim($authKey), -3) === ':fx';
    }

    /**
     * Returns true if the specified DeepL Authentication Key is associated with a free account,
     * otherwise false.
     * @param string $authKey The authentication key to check.
     * @return bool True if the key is associated with a free account, otherwise false.
     * @deprecated Use isFreeAccountAuthKey() instead.
     */
    public static function isAuthKeyFreeAccount(string $authKey): bool
    {
        if (!self::$isAuthKeyFreeAccountDeprecationTriggered) {
            self::$isAuthKeyFreeAccountDeprecationTriggered = true;
            @trigger_error(
                'Translator::isAuthKeyFreeAccount() is deprecated, use Translator::isFreeAccountAuthKey() instead.',
                E_USER_DEPRECATED
            );
        }
        return substr($authKey, -3) === ':fx';
    }
}
