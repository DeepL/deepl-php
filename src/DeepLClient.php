<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Client for the DeepL API.
 */
class DeepLClient
{
    /**
     * Library version.
     */
    public const VERSION = '1.21.0';

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
     * Construct a DeepLClient object wrapping the DeepL API using your authentication key.
     * This does not connect to the API, and returns immediately.
     * @param string $authKey Authentication key as specified in your account.
     * @param array $options Additional options controlling DeepLClient behaviour.
     * @throws \InvalidArgumentException If the auth key is empty or an option is invalid, including an unknown
     * option key.
     * @see TranslatorOptions for a list of available request options.
     */
    public function __construct(string $authKey, array $options = [])
    {
        $authKey = trim($authKey);
        if ($authKey === '') {
            throw new \InvalidArgumentException('authKey must be a non-empty string');
        }
        TranslatorOptions::checkOptionKeys($options);
        // Logs a warning for options that are ignored with a custom HTTP client
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
            ['User-Agent' => ClientUtils::findHeader($headers, 'User-Agent')],
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
            '/v3/languages?' . ClientUtils::buildQuery(['resource' => LanguageResourceName::GLOSSARY])
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
     * @throws \InvalidArgumentException If a text is empty or an argument or option is invalid, checked before
     * any request is sent.
     * @throws DeepLException
     * @see \DeepL\TranslateTextOptions
     */
    public function translateText($texts, ?string $sourceLang, string $targetLang, array $options = [])
    {
        self::checkOptionKeys($options, TranslateTextOptions::class, 'translate text');
        $params = $this->buildBodyParams(
            $sourceLang,
            $targetLang,
            $options[TranslateTextOptions::FORMALITY] ?? null,
            $options[TranslateTextOptions::GLOSSARY] ?? null,
            $options[TranslateTextOptions::GLOSSARY_IDS] ?? null,
            false
        );
        // Always send show_billed_characters=true, remove when the API default is changed to true
        $params["show_billed_characters"] = true;
        $this->validateAndAppendTexts($params, $texts);
        $this->validateAndAppendTextOptions($params, $options);

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v2/translate',
            [HttpClientWrapper::OPTION_JSON => ClientUtils::encodeJson($params)]
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
     * The upload is only retried if it was not processed: after HTTP 429, or a connection failure before
     * the request was sent. With a custom HTTP client, only HTTP 429 is retried. Status checks and the download
     * use the full retry set.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
     * @param string $inputFile String containing file path of document to be translated.
     * @param string $outputFile String containing file path to create translated document.
     * @param string|null $sourceLang Language code of input document, or null to use auto-detection.
     * @param string $targetLang Language code of language to translate into.
     * @param array $options Translation options to apply. See \DeepL\TranslateDocumentOptions.
     * @return DocumentStatus DocumentStatus object for the completed translation. You can use the billedCharacters
     *     property to check how many characters were billed for the document.
     * @throws \InvalidArgumentException If no file exists at the input file path, checked before any request is
     * sent.
     * @throws DocumentTranslationException If a file already exists at the output file path, or its directory
     * does not exist or is not writable, checked before any request is sent with a null `handle`, or if any error
     * occurs during document upload, translation or download. The `handle` property of the exception, if not null,
     * may be used to recover the document ID and key of an in-progress translation. This includes the expiry of the
     * TranslateDocumentOptions::TIMEOUT option. The exception carries the httpStatusCode and shouldRetry of the
     * DeepLException that caused it, which is also available via getPrevious().
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
        if (!file_exists($inputFile)) {
            throw new \InvalidArgumentException("File does not exist at input file path $inputFile");
        }
        if (file_exists($outputFile)) {
            throw new DocumentTranslationException("File already exists at output file path $outputFile");
        }
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir) || !is_writable($outputDir)) {
            throw new DocumentTranslationException(
                "Failed to open output file $outputFile for writing: the directory does not exist or is not writable"
            );
        }
        try {
            $handle = $this->translateDocumentUpload($inputFile, $sourceLang, $targetLang, $options);
            $status = $this->translateDocumentWaitUntilDone(
                $handle,
                [TranslateDocumentOptions::TIMEOUT => $options[TranslateDocumentOptions::TIMEOUT] ?? null]
            );
            $this->translateDocumentDownload($handle, $outputFile);
            return $status;
        } catch (\Throwable $error) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            $message = 'Error occurred while translating document: ' . ($error->getMessage() ?: 'unknown error');
            $exception = new DocumentTranslationException($message, $error->getCode(), $error, $handle);
            if ($error instanceof DeepLException) {
                $exception->httpStatusCode = $error->httpStatusCode;
                $exception->shouldRetry = $error->shouldRetry;
            }
            throw $exception;
        }
    }

    /**
     * Uploads specified document to DeepL to translate into target language, and returns handle associated with the
     * document. Use translateDocumentWaitUntilDone() and translateDocumentDownload() to complete the translation.
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
     * @param string $inputFile String containing file path of document to be translated.
     * @param string|null $sourceLang Language code of input document, or null to use auto-detection.
     * @param string $targetLang Language code of language to translate into.
     * @param array $options Translation options to apply. See \DeepL\TranslateDocumentOptions.
     * @return DocumentHandle Handle associated with the in-progress document translation.
     * @throws \InvalidArgumentException If no file exists at the input file path, checked before any request is
     * sent.
     * @throws DeepLException If the input path is a directory or not readable, checked before any request is
     * sent, or if the request fails.
     */
    public function translateDocumentUpload(
        string $inputFile,
        ?string $sourceLang,
        string $targetLang,
        array $options = []
    ): DocumentHandle {
        self::checkOptionKeys($options, TranslateDocumentOptions::class, 'translate document');
        if (!file_exists($inputFile)) {
            throw new \InvalidArgumentException("File does not exist at input file path $inputFile");
        }
        if (is_dir($inputFile) || !is_readable($inputFile)) {
            throw new DeepLException("Failed to read input file $inputFile: the file is a directory or not readable");
        }
        $params = $this->buildBodyParams(
            $sourceLang,
            $targetLang,
            $options[TranslateDocumentOptions::FORMALITY] ?? null,
            $options[TranslateDocumentOptions::GLOSSARY] ?? null,
            $options[TranslateDocumentOptions::GLOSSARY_IDS] ?? null,
            true
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
                HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true,
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
            // The cURL transport streams the body to the file and returns true instead of the content, so
            // on failure the API's error message is in the file.
            list($statusCode, $content) = $response;
            if (($statusCode < 200 || $statusCode >= 300) && file_exists($outputFile)) {
                $errorBody = @file_get_contents($outputFile);
                if ($errorBody !== false) {
                    $content = $errorBody;
                }
            }
            $this->checkStatusCode([$statusCode, $content], true);
        } catch (DeepLException $error) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            throw $error;
        }
    }

    /**
     * Returns when the given document translation completes, or throws an exception if there was an error
     * communicating with the DeepL API, the document translation failed, or the timeout expired.
     * @param DocumentHandle $handle Handle to the document translation.
     * @param array $options Wait options: only TranslateDocumentOptions::TIMEOUT, the maximum time to wait in
     *     seconds (float). Unset or null waits without limit. Zero or negative: the status is requested once and the
     *     wait times out if the translation is not done. The status is polled every 5 seconds; the last sleep is
     *     cut short to the deadline, so the wait does not run past the timeout.
     * @return DocumentStatus DocumentStatus object for the completed translation. You can use the billedCharacters
     *     property to check how many characters were billed for the document.
     * @throws \InvalidArgumentException If an option key other than TIMEOUT is given, checked before any request
     *     is sent.
     * @throws DeepLException If a request fails, the translation fails or the timeout expires.
     */
    public function translateDocumentWaitUntilDone(DocumentHandle $handle, array $options = []): DocumentStatus
    {
        foreach (array_keys($options) as $key) {
            if ($key !== TranslateDocumentOptions::TIMEOUT) {
                throw new \InvalidArgumentException("Unknown translate document wait option: $key");
            }
        }
        $timeout = $options[TranslateDocumentOptions::TIMEOUT] ?? null;
        $startTime = microtime(true);
        $status = $this->translateDocumentGetStatus($handle);
        while (!$status->done() && $status->ok()) {
            $elapsed = microtime(true) - $startTime;
            if ($timeout !== null && $elapsed >= $timeout) {
                throw new DeepLException("Timeout of {$timeout}s exceeded while waiting for document translation");
            }
            // secondsRemaining is currently unreliable, so just poll equidistantly
            $secs = 5.0;
            if ($timeout !== null) {
                $secs = max(0.0, min($secs, $timeout - $elapsed));
            }
            usleep((int)round($secs * 1000000));
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
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
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
            throw new \InvalidArgumentException('glossary name must be a non-empty string');
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
            [
                HttpClientWrapper::OPTION_JSON => ClientUtils::encodeJson($params),
                HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true,
            ]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return GlossaryInfo::parse($content);
    }

    /**
     * Creates a new glossary on DeepL server with given name, languages, and entries.
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
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
            throw new \InvalidArgumentException('glossary name must be a non-empty string');
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
            [
                HttpClientWrapper::OPTION_JSON => ClientUtils::encodeJson($params),
                HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true,
            ]
        );
        $this->checkStatusCode($response);
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
        $this->checkStatusCode($response);
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
        $this->checkStatusCode($response);
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
        $this->checkStatusCode($response);
        list(, $content) = $response;
        try {
            return GlossaryEntries::fromTsv($content);
        } catch (\InvalidArgumentException $error) {
            throw ParsingUtils::invalidContent($error->getMessage(), $error);
        }
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
        $this->checkStatusCode($response);
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
            '/v3/languages?' . ClientUtils::buildQuery(['resource' => LanguageResourceName::TRANSLATE_TEXT])
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
            return array_values($tagList);
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
     * @param bool $glossaryRequiresSourceLang True to reject a glossary or glossary_ids without sourceLang
     *  (document translation); text translation lets the API detect the source language.
     * @return array Associative array of HTTP parameters.
     * @throws DeepLException
     * @throws \InvalidArgumentException If an ID is empty or an object has none.
     */
    private function buildBodyParams(
        ?string $sourceLang,
        string $targetLang,
        ?string $formality,
        $glossary,
        ?array $glossaryIds,
        bool $glossaryRequiresSourceLang
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
            $params['formality'] = $formality;
        }
        if (isset($glossary)) {
            if ($glossaryRequiresSourceLang && !isset($sourceLang)) {
                throw new \InvalidArgumentException('sourceLang is required if using a glossary');
            }
            $glossary = self::glossaryReferenceId($glossary);
            $params['glossary_id'] = $glossary;
        }
        if (isset($glossaryIds)) {
            if (isset($glossary)) {
                throw new \InvalidArgumentException(
                    'The glossary_ids option cannot be used together with the singular glossary option'
                );
            }
            if ($glossaryRequiresSourceLang && !isset($sourceLang)) {
                throw new \InvalidArgumentException('sourceLang is required if using glossary_ids');
            }
            if (count($glossaryIds) > 5) {
                throw new \InvalidArgumentException('A maximum of 5 glossary IDs can be specified in glossary_ids');
            }
            $ids = array_map(
                function ($glossaryId) {
                    return self::glossaryReferenceId($glossaryId);
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
     * @param string|GlossaryInfo|MultilingualGlossaryInfo $glossary
     * @throws \InvalidArgumentException If the glossary ID is empty or the object has none.
     */
    private static function glossaryReferenceId($glossary): string
    {
        $glossaryId = is_string($glossary) ? $glossary : ($glossary->glossaryId ?? null);
        if (!is_string($glossaryId) || $glossaryId === '') {
            throw new \InvalidArgumentException('glossaryId must be a non-empty string');
        }
        return $glossaryId;
    }

    /**
     * Validates and appends texts to HTTP request parameters.
     * @param array $params Parameters for HTTP request.
     * @param string|string[] $texts User-supplied texts to be checked.
     * @throws \InvalidArgumentException If a text is empty.
     */
    protected function validateAndAppendTexts(array &$params, $texts)
    {
        foreach (is_array($texts) ? $texts : [$texts] as $text) {
            if ($text === '') {
                throw new \InvalidArgumentException('text must not be empty');
            }
        }
        $params['text'] = is_array($texts) ? array_values($texts) : [$texts];
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
            $params[TranslateTextOptions::SPLIT_SENTENCES] = $options[TranslateTextOptions::SPLIT_SENTENCES];
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
        $this->appendStyleRuleAndTranslationMemoryOptions($params, $options);
        if (isset($options[TranslateTextOptions::CUSTOM_INSTRUCTIONS])) {
            $customInstructions = $options[TranslateTextOptions::CUSTOM_INSTRUCTIONS];
            $params[TranslateTextOptions::CUSTOM_INSTRUCTIONS] =
                is_array($customInstructions) ? array_values($customInstructions) : $customInstructions;
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
     * @throws \InvalidArgumentException If an ID is empty or an object has none.
     */
    private function appendStyleRuleAndTranslationMemoryOptions(array &$params, ?array $options): void
    {
        if ($options === null) {
            return;
        }
        $styleRule = $options[TranslateTextOptions::STYLE_RULE] ?? null;
        if (isset($styleRule)) {
            $params['style_id'] = StyleRuleInfo::getStyleId($styleRule);
        }
        if (isset($options[TranslateTextOptions::STYLE_PROFILE])) {
            $styleProfile = $options[TranslateTextOptions::STYLE_PROFILE];
            $params['style_profile_id'] = StyleProfile::getStyleProfileId($styleProfile);
        }
        $tm = $options[TranslateTextOptions::TRANSLATION_MEMORY] ?? null;
        if (isset($tm)) {
            $params['translation_memory_id'] = TranslationMemoryInfo::getTranslationMemoryId($tm);
        }
        if (isset($options[TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD])) {
            if (!isset($tm)) {
                throw new \InvalidArgumentException('translation_memory_threshold requires translation_memory');
            }
            $threshold = $options[TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD];
            if ($threshold < 0 || $threshold > 100) {
                throw new \InvalidArgumentException('translation_memory_threshold must be between 0 and 100');
            }
            $params['translation_memory_threshold'] = $threshold;
        }
    }

    /**
     * Adds extra body parameters to the params array. Extra parameters can override existing keys.
     * Values are kept as given, for a JSON body, null included. For a form body, a null value removes the key,
     * including a parameter of the same name set by the library; booleans become "true"/"false", arrays and
     * objects their JSON encoding, and other values strings. Objects with __toString that are not
     * JsonSerializable are sent as their string in both cases.
     */
    private function applyExtraBodyParameters(array &$params, ?array $extraParams, bool $formBody = false): void
    {
        if ($extraParams !== null) {
            foreach ($extraParams as $key => $value) {
                if ($formBody && $value === null) {
                    unset($params[$key]);
                } elseif (self::isStringableObject($value)) {
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
     * @throws \InvalidArgumentException If an array or object cannot be JSON-encoded.
     */
    private static function toFormValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value);
            if ($encoded === false) {
                throw new \InvalidArgumentException(
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
    protected function checkStatusCode(array $response, bool $inDocumentDownload = false)
    {
        ClientUtils::checkStatusCode($response, $inDocumentDownload);
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
     * Rephrases the given text using DeepL's API.
     * @param string|string[] $texts A single string or array of strings to rephrase
     * @param string|null $targetLang Optional target language code
     * @param array $options Rephrase options to apply, see \DeepL\RephraseTextOptions: WRITING_STYLE, TONE
     *  and EXTRA_BODY_PARAMETERS.
     * @return RephraseTextResult|RephraseTextResult[] A RephraseTextResult or array of
     * RephraseTextResult objects containing rephrased texts
     * @throws \InvalidArgumentException If a text is empty, checked before any request is sent.
     * @throws DeepLException
     * @see \DeepL\RephraseTextOptions
     */
    public function rephraseText($texts, ?string $targetLang = null, array $options = [])
    {
        self::checkOptionKeys($options, RephraseTextOptions::class, 'rephrase text');
        $params = $this->buildRephraseBodyParams(
            $targetLang,
            $options[RephraseTextOptions::WRITING_STYLE] ?? null,
            $options[RephraseTextOptions::TONE] ?? null
        );
        $this->validateAndAppendTexts($params, $texts);
        return $this->sendWriteRequest(
            '/v2/write/rephrase',
            $params,
            $texts,
            $options[RephraseTextOptions::EXTRA_BODY_PARAMETERS] ?? null
        );
    }

    /**
     * Corrects spelling and grammar in the given text using DeepL's API.
     * @param string|string[] $texts A single string or array of strings to correct
     * @param string|null $targetLang Optional target language code
     * @param array $options Correct options to apply, see \DeepL\CorrectTextOptions: only EXTRA_BODY_PARAMETERS.
     * @return RephraseTextResult|RephraseTextResult[] A RephraseTextResult or array of
     * RephraseTextResult objects containing corrected texts
     * @throws \InvalidArgumentException If a text is empty, checked before any request is sent.
     * @throws DeepLException If the request fails.
     */
    public function correctText($texts, ?string $targetLang = null, array $options = [])
    {
        self::checkOptionKeys($options, CorrectTextOptions::class, 'correct text');
        $params = [];
        if ($targetLang !== null) {
            $params['target_lang'] = LanguageCode::standardizeLanguageCode($targetLang);
        }
        $this->validateAndAppendTexts($params, $texts);
        return $this->sendWriteRequest(
            '/v2/write/correct',
            $params,
            $texts,
            $options[CorrectTextOptions::EXTRA_BODY_PARAMETERS] ?? null
        );
    }

    /**
     * Sends a rephrase or correct request, with the extra body parameters added last, and parses one
     * RephraseTextResult per input text.
     * @param string|string[] $texts
     * @return RephraseTextResult|RephraseTextResult[]
     * @throws DeepLException
     */
    private function sendWriteRequest(string $path, array $params, $texts, ?array $extraBodyParameters)
    {
        foreach ($extraBodyParameters ?? [] as $key => $value) {
            $params[$key] = $value;
        }
        $response = $this->client->sendRequestWithBackoff(
            'POST',
            $path,
            [HttpClientWrapper::OPTION_JSON => ClientUtils::encodeJson($params)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;

        $json = ParsingUtils::decodeJson($content);
        $improvements = $json['improvements'] ?? null;
        if (!is_array($improvements) || array_values($improvements) !== $improvements) {
            throw ParsingUtils::invalidContent('Expected an improvements list in the response');
        }
        $expected = is_array($texts) ? count($texts) : 1;
        if (count($improvements) !== $expected) {
            throw ParsingUtils::invalidContent(
                'Expected ' . $expected . ' improvements in the response, got ' . count($improvements)
            );
        }
        $output = [];
        foreach ($improvements as $improvement) {
            if (!is_array($improvement) || !is_string($improvement['text'] ?? null)) {
                throw ParsingUtils::invalidContent('Expected a text in every improvement in the response');
            }
            $detectedSourceLanguage = $improvement['detected_source_language'] ?? '';
            $targetLanguage = $improvement['target_language'] ?? '';
            // A missing or invalid language is a response problem, not an argument error
            $output[] = ParsingUtils::guard(function () use ($improvement, $detectedSourceLanguage, $targetLanguage) {
                return new RephraseTextResult($improvement['text'], $detectedSourceLanguage, $targetLanguage);
            });
        }

        return is_array($texts) ? $output : $output[0];
    }

    /**
     * Creates a new glossary on DeepL server with given name with all the specified
     * dictionaries, each with their own language pair and entries.
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
     * @param string $name User-defined name to assign to the glossary.
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries A list of MultilingualGlossaryDictionaryEntries
     *      which each contains entries for a particular language pair
     * @param array $options Request options, see \DeepL\MultilingualGlossaryOptions: only EXTRA_BODY_PARAMETERS.
     * @return MultilingualGlossaryInfo Details about the created glossary.
     * @throws DeepLException
     */
    public function createMultilingualGlossary(
        string $name,
        array $dictionaries,
        array $options = []
    ): MultilingualGlossaryInfo {
        self::checkMultilingualGlossaryOptions($options, []);
        if (strlen($name) === 0) {
            throw new \InvalidArgumentException('glossary name must be a non-empty string');
        }
        if (count($dictionaries) === 0) {
            throw new \InvalidArgumentException('dictionaries must not be empty');
        }

        $params = [
            'name' => $name,
            'dictionaries' => self::multilingualDictionariesToObjects($dictionaries),
        ];
        return $this->createMultilingualGlossaryWithParams($params, $options);
    }

    /**
     * Creates a new glossary on DeepL server with given name, languages, and entries.
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
     * @param string $name User-defined name to assign to the glossary.
     * @param string $sourceLang Language code of the glossary source terms.
     * @param string $targetLang Language code of the glossary target terms.
     * @param string $csvContent String containing CSV content.
     * @param array $options Request options, see \DeepL\MultilingualGlossaryOptions: only EXTRA_BODY_PARAMETERS.
     * @return MultilingualGlossaryInfo Details about the created glossary.
     * @throws \InvalidArgumentException If a language code is empty.
     * @throws DeepLException
     */
    public function createMultilingualGlossaryFromCsv(
        string $name,
        string $sourceLang,
        string $targetLang,
        string $csvContent,
        array $options = []
    ): MultilingualGlossaryInfo {
        self::checkMultilingualGlossaryOptions($options, []);
        if (strlen($name) === 0) {
            throw new \InvalidArgumentException('glossary name must be a non-empty string');
        }

        $params = [
            'name' => $name,
            'dictionaries' => [self::csvDictionaryObject($sourceLang, $targetLang, $csvContent)],
        ];
        return $this->createMultilingualGlossaryWithParams($params, $options);
    }

    /**
     * @throws DeepLException
     */
    private function createMultilingualGlossaryWithParams(array $params, array $options): MultilingualGlossaryInfo
    {
        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/glossaries',
            [
                HttpClientWrapper::OPTION_JSON => self::multilingualGlossaryJsonBody($params, $options),
                HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true,
            ]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseJson($content);
    }

    /**
     * Updates or creates a glossary dictionary with given entries for the
     * source and target languages. Either updates entries if they exist for
     * the given language pair, or adds new ones to the dictionary if not.
     * Without a name and dictionaries, the body is {}.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to update.
     * @param array $options Request options, see \DeepL\MultilingualGlossaryOptions: NAME (new name for the
     *  glossary), DICTIONARIES (MultilingualGlossaryDictionaryEntries[] to update or add) and EXTRA_BODY_PARAMETERS.
     * @return MultilingualGlossaryInfo Info about the updated glossary.
     * @throws DeepLException
     */
    public function updateMultilingualGlossary($glossary, array $options = []): MultilingualGlossaryInfo
    {
        self::checkMultilingualGlossaryOptions(
            $options,
            [MultilingualGlossaryOptions::NAME, MultilingualGlossaryOptions::DICTIONARIES]
        );
        $params = [];
        $name = $options[MultilingualGlossaryOptions::NAME] ?? null;
        if ($name !== null) {
            $params['name'] = $name;
        }
        $dictionaries = $options[MultilingualGlossaryOptions::DICTIONARIES] ?? null;
        if ($dictionaries !== null && count($dictionaries) > 0) {
            $params['dictionaries'] = self::multilingualDictionariesToObjects($dictionaries);
        }
        return $this->patchMultilingualGlossary($glossary, $params, $options);
    }

    /**
     * Renames a glossary.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to update.
     * @param string $name New name for the glossary.
     * @param array $options Request options, see \DeepL\MultilingualGlossaryOptions: only EXTRA_BODY_PARAMETERS.
     * @return MultilingualGlossaryInfo Info about the updated glossary.
     * @throws DeepLException
     */
    public function updateMultilingualGlossaryName(
        $glossary,
        string $name,
        array $options = []
    ): MultilingualGlossaryInfo {
        self::checkMultilingualGlossaryOptions($options, []);
        if ($name === '') {
            throw new \InvalidArgumentException('glossary name must be a non-empty string');
        }
        return $this->patchMultilingualGlossary($glossary, ['name' => $name], $options);
    }

    /**
     * Merges entries into the glossary dictionary of their language pair: new source terms are added and
     * the target term of an existing source term is replaced. The dictionary is added if the glossary has
     * none for that language pair.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to update.
     * @param MultilingualGlossaryDictionaryEntries $dictionaryEntries Language pair and entries to merge.
     * @param array $options Request options, see \DeepL\MultilingualGlossaryOptions: only EXTRA_BODY_PARAMETERS.
     * @return MultilingualGlossaryInfo Info about the updated glossary.
     * @throws DeepLException
     */
    public function updateMultilingualGlossaryDictionary(
        $glossary,
        MultilingualGlossaryDictionaryEntries $dictionaryEntries,
        array $options = []
    ): MultilingualGlossaryInfo {
        self::checkMultilingualGlossaryOptions($options, []);
        $params = ['dictionaries' => [$dictionaryEntries->toObject()]];
        return $this->patchMultilingualGlossary($glossary, $params, $options);
    }

    /**
     * Merges CSV entries into the glossary dictionary of the given language pair, like
     * updateMultilingualGlossaryDictionary().
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to update.
     * @param string $sourceLang Language code of the source terms.
     * @param string $targetLang Language code of the target terms.
     * @param string $csvContent String containing CSV content.
     * @param array $options Request options, see \DeepL\MultilingualGlossaryOptions: only EXTRA_BODY_PARAMETERS.
     * @return MultilingualGlossaryInfo Info about the updated glossary.
     * @throws \InvalidArgumentException If a language code or the CSV content is empty, checked before any request
     *  is sent.
     * @throws DeepLException
     */
    public function updateMultilingualGlossaryDictionaryFromCsv(
        $glossary,
        string $sourceLang,
        string $targetLang,
        string $csvContent,
        array $options = []
    ): MultilingualGlossaryInfo {
        self::checkMultilingualGlossaryOptions($options, []);
        if (trim($csvContent) === '') {
            throw new \InvalidArgumentException('csvContent must be a non-empty string');
        }
        $params = ['dictionaries' => [self::csvDictionaryObject($sourceLang, $targetLang, $csvContent)]];
        return $this->patchMultilingualGlossary($glossary, $params, $options);
    }

    /**
     * @param string|MultilingualGlossaryInfo $glossary
     * @throws DeepLException
     */
    private function patchMultilingualGlossary($glossary, array $params, array $options): MultilingualGlossaryInfo
    {
        $response = $this->client->sendRequestWithBackoff(
            'PATCH',
            self::multilingualGlossaryPath($glossary),
            [HttpClientWrapper::OPTION_JSON => self::multilingualGlossaryJsonBody($params, $options)]
        );
        $this->checkStatusCode($response);
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
     * @param array $options Request options, see \DeepL\MultilingualGlossaryOptions: only EXTRA_BODY_PARAMETERS.
     * @return MultilingualGlossaryDictionaryInfo Info about the dictionary.
     * @throws DeepLException
     */
    public function replaceMultilingualGlossaryDictionary(
        $glossary,
        MultilingualGlossaryDictionaryEntries $dictionaryEntries,
        array $options = []
    ): MultilingualGlossaryDictionaryInfo {
        self::checkMultilingualGlossaryOptions($options, []);
        return $this->putMultilingualGlossaryDictionary($glossary, $dictionaryEntries->toObject(), $options);
    }

    /**
     * Replaces a glossary dictionary with the given CSV entries for the source and target languages, like
     * replaceMultilingualGlossaryDictionary().
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  update.
     * @param string $sourceLang Language code of the source terms.
     * @param string $targetLang Language code of the target terms.
     * @param string $csvContent String containing CSV content.
     * @param array $options Request options, see \DeepL\MultilingualGlossaryOptions: only EXTRA_BODY_PARAMETERS.
     * @return MultilingualGlossaryDictionaryInfo Info about the dictionary.
     * @throws \InvalidArgumentException If a language code or the CSV content is empty, checked before any request
     *  is sent.
     * @throws DeepLException
     */
    public function replaceMultilingualGlossaryDictionaryFromCsv(
        $glossary,
        string $sourceLang,
        string $targetLang,
        string $csvContent,
        array $options = []
    ): MultilingualGlossaryDictionaryInfo {
        if (trim($csvContent) === '') {
            throw new \InvalidArgumentException('csvContent must be a non-empty string');
        }
        $params = self::csvDictionaryObject($sourceLang, $targetLang, $csvContent);
        return $this->putMultilingualGlossaryDictionary($glossary, $params, $options);
    }

    /**
     * @param string|MultilingualGlossaryInfo $glossary
     * @throws DeepLException
     */
    private function putMultilingualGlossaryDictionary(
        $glossary,
        array $params,
        array $options
    ): MultilingualGlossaryDictionaryInfo {
        self::checkMultilingualGlossaryOptions($options, []);
        $response = $this->client->sendRequestWithBackoff(
            'PUT',
            self::multilingualGlossaryPath($glossary) . '/dictionaries',
            [HttpClientWrapper::OPTION_JSON => self::multilingualGlossaryJsonBody($params, $options)]
        );
        $this->checkStatusCode($response);
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
        $response = $this->client->sendRequestWithBackoff('GET', self::multilingualGlossaryPath($glossary));
        $this->checkStatusCode($response);
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
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseListJson($content);
    }

    /**
     * Retrieves the entries of the dictionary for the given source and target language in the given glossary.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  retrieve entries of.
     * @param string $sourceLang Language code of the glossary source terms.
     * @param string $targetLang Language code of the glossary target terms.
     * @return MultilingualGlossaryDictionaryEntries The language pair and entries of the dictionary.
     * @throws NotFoundException If the glossary or the dictionary does not exist.
     * @throws \InvalidArgumentException If a language code is empty.
     * @throws DeepLException
     */
    public function getMultilingualGlossaryDictionaryEntries(
        $glossary,
        string $sourceLang,
        string $targetLang
    ): MultilingualGlossaryDictionaryEntries {
        $dictionaries = $this->requestMultilingualGlossaryEntries($glossary, $sourceLang, $targetLang);
        if (count($dictionaries) === 0) {
            throw new NotFoundException('Glossary dictionary not found');
        }
        return $dictionaries[0];
    }

    /**
     * @param string|MultilingualGlossaryInfo $glossary
     * @return MultilingualGlossaryDictionaryEntries[]
     * @throws DeepLException
     */
    private function requestMultilingualGlossaryEntries($glossary, string $sourceLang, string $targetLang): array
    {
        ClientUtils::checkLanguageCode($sourceLang, 'sourceLang');
        ClientUtils::checkLanguageCode($targetLang, 'targetLang');
        $query = ClientUtils::buildQuery(['source_lang' => $sourceLang, 'target_lang' => $targetLang]);
        $url = self::multilingualGlossaryPath($glossary) . "/entries?$query";
        $response = $this->client->sendRequestWithBackoff('GET', $url);
        $this->checkStatusCode($response);
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
        $response = $this->client->sendRequestWithBackoff('DELETE', self::multilingualGlossaryPath($glossary));
        $this->checkStatusCode($response);
    }

    /**
     * Deletes the glossary dictionary of the given language pair.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of the glossary
     *  containing the dictionary.
     * @param string|MultilingualGlossaryDictionaryInfo $sourceLang Language code of the dictionary's source terms,
     *  or the MultilingualGlossaryDictionaryInfo of the dictionary, which identifies both languages.
     * @param string|null $targetLang Language code of the dictionary's target terms; required with a source
     *  language code, not allowed with a MultilingualGlossaryDictionaryInfo.
     * @throws DeepLException
     */
    public function deleteMultilingualGlossaryDictionary($glossary, $sourceLang, ?string $targetLang = null): void
    {
        if ($sourceLang instanceof MultilingualGlossaryDictionaryInfo) {
            if ($targetLang !== null) {
                throw new \InvalidArgumentException(
                    'targetLang must not be given with a MultilingualGlossaryDictionaryInfo'
                );
            }
            $targetLang = $sourceLang->targetLang;
            $sourceLang = $sourceLang->sourceLang;
        } elseif (!is_string($sourceLang) || $targetLang === null) {
            throw new \InvalidArgumentException(
                'must provide a MultilingualGlossaryDictionaryInfo or both sourceLang and targetLang'
            );
        }
        if ($sourceLang === '' || $targetLang === '') {
            throw new \InvalidArgumentException('sourceLang and targetLang must be non-empty strings');
        }

        $query = ClientUtils::buildQuery(['source_lang' => $sourceLang, 'target_lang' => $targetLang]);
        $url = self::multilingualGlossaryPath($glossary) . "/dictionaries?$query";
        $response = $this->client->sendRequestWithBackoff('DELETE', $url);
        $this->checkStatusCode($response);
    }

    /**
     * @param string|MultilingualGlossaryInfo $glossary
     */
    private static function multilingualGlossaryPath($glossary): string
    {
        return '/v3/glossaries/' . rawurlencode(MultilingualGlossaryInfo::getGlossaryId($glossary));
    }

    /**
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     */
    private static function multilingualDictionariesToObjects(array $dictionaries): array
    {
        return array_values(array_map(function (MultilingualGlossaryDictionaryEntries $entries) {
            return $entries->toObject();
        }, $dictionaries));
    }

    private static function csvDictionaryObject(string $sourceLang, string $targetLang, string $csvContent): array
    {
        ClientUtils::checkLanguageCode($sourceLang, 'sourceLang');
        ClientUtils::checkLanguageCode($targetLang, 'targetLang');
        return [
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'entries_format' => 'csv',
            'entries' => $csvContent,
        ];
    }

    /**
     * @param string $optionsClass Class whose constants are the option keys the method accepts.
     * @throws \InvalidArgumentException If an option key is not one of them.
     */
    private static function checkOptionKeys(array $options, string $optionsClass, string $what): void
    {
        $allowed = array_values((new \ReflectionClass($optionsClass))->getConstants());
        foreach (array_keys($options) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException("Unknown $what option: $key");
            }
        }
    }

    /**
     * @param string[] $allowed Option keys the method accepts besides EXTRA_BODY_PARAMETERS.
     * @throws \InvalidArgumentException If an option key is unknown to the method.
     */
    private static function checkMultilingualGlossaryOptions(array $options, array $allowed): void
    {
        $allowed[] = MultilingualGlossaryOptions::EXTRA_BODY_PARAMETERS;
        foreach (array_keys($options) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException("Unknown multilingual glossary option: $key");
            }
        }
    }

    /**
     * Encodes the JSON body of a multilingual glossary request, with the extra body parameters added last.
     * An empty body encodes as {}.
     */
    private static function multilingualGlossaryJsonBody(array $params, array $options): string
    {
        foreach ($options[MultilingualGlossaryOptions::EXTRA_BODY_PARAMETERS] ?? [] as $key => $value) {
            $params[$key] = $value;
        }
        return ClientUtils::encodeJson((object)$params);
    }

    /**
     * Creates a spoken terms collection with the given name and term lists.
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
     * @param string $name User-defined name to assign to the collection.
     * @param SpokenTermsEntries[] $termLists Term lists to add, one per language.
     * @param array $options Request options, see \DeepL\SpokenTermsOptions: only EXTRA_BODY_PARAMETERS.
     * @return SpokenTerms Details about the created collection.
     * @throws DeepLException
     */
    public function createSpokenTerms(string $name, array $termLists, array $options = []): SpokenTerms
    {
        self::checkSpokenTermsOptions($options, []);
        $params = ['name' => $name, 'term_lists' => self::spokenTermsListsToObjects($termLists)];
        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/spoken-terms',
            [
                HttpClientWrapper::OPTION_JSON => self::spokenTermsJsonBody($params, $options),
                HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true,
            ]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return SpokenTerms::parseJson($content);
    }

    /**
     * Gets information about all spoken terms collections.
     * @return SpokenTerms[] Details about all collections, without their entries.
     * @throws DeepLException
     */
    public function listSpokenTerms(): array
    {
        $response = $this->client->sendRequestWithBackoff('GET', '/v3/spoken-terms');
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return SpokenTerms::parseListJson($content);
    }

    /**
     * Gets information about a spoken terms collection.
     * @param string|SpokenTerms $spokenTerms Spoken terms ID or SpokenTerms object.
     * @return SpokenTerms Details about the collection, without its entries.
     * @throws DeepLException
     */
    public function getSpokenTerms($spokenTerms): SpokenTerms
    {
        $response = $this->client->sendRequestWithBackoff('GET', self::spokenTermsPath($spokenTerms));
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return SpokenTerms::parseJson($content);
    }

    /**
     * Renames a spoken terms collection and/or merges terms into its term lists. A term list for a
     * language not yet in the collection is added. Options that are not set, or null, are not sent;
     * without any, the body is {}.
     * @param string|SpokenTerms $spokenTerms Spoken terms ID or SpokenTerms object.
     * @param array $options Fields to change, see \DeepL\SpokenTermsOptions: NAME (new name), TERM_LISTS
     *  (SpokenTermsEntries[] to merge) and EXTRA_BODY_PARAMETERS.
     * @return SpokenTerms Details about the updated collection.
     * @throws DeepLException
     */
    public function updateSpokenTerms($spokenTerms, array $options = []): SpokenTerms
    {
        self::checkSpokenTermsOptions($options, [SpokenTermsOptions::NAME, SpokenTermsOptions::TERM_LISTS]);
        $params = [];
        if (isset($options[SpokenTermsOptions::NAME])) {
            $params['name'] = (string)$options[SpokenTermsOptions::NAME];
        }
        if (isset($options[SpokenTermsOptions::TERM_LISTS])) {
            $params['term_lists'] = self::spokenTermsListsToObjects($options[SpokenTermsOptions::TERM_LISTS]);
        }
        $response = $this->client->sendRequestWithBackoff(
            'PATCH',
            self::spokenTermsPath($spokenTerms),
            [HttpClientWrapper::OPTION_JSON => self::spokenTermsJsonBody($params, $options)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return SpokenTerms::parseJson($content);
    }

    /**
     * Retrieves the terms of one language in a spoken terms collection.
     * @param string|SpokenTerms $spokenTerms Spoken terms ID or SpokenTerms object.
     * @param string $lang Language code of the term list, e.g. "en".
     * @return SpokenTermsEntries The terms of the list.
     * @throws \InvalidArgumentException If the language code is empty.
     * @throws DeepLException
     */
    public function getSpokenTermsEntries($spokenTerms, string $lang): SpokenTermsEntries
    {
        ClientUtils::checkLanguageCode($lang, 'lang');
        $query = ClientUtils::buildQuery(['lang' => $lang]);
        $url = self::spokenTermsPath($spokenTerms) . "/entries?$query";
        $response = $this->client->sendRequestWithBackoff('GET', $url);
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return SpokenTermsEntries::parseJson($content);
    }

    /**
     * Replaces the term list of one language in a spoken terms collection, or adds it if the
     * collection has no list for that language.
     * @param string|SpokenTerms $spokenTerms Spoken terms ID or SpokenTerms object.
     * @param SpokenTermsEntries $entries Language and terms of the replacement list.
     * @param array $options Request options, see \DeepL\SpokenTermsOptions: only EXTRA_BODY_PARAMETERS.
     * @return SpokenTermsList Details about the term list.
     * @throws DeepLException
     */
    public function replaceSpokenTermsList(
        $spokenTerms,
        SpokenTermsEntries $entries,
        array $options = []
    ): SpokenTermsList {
        self::checkSpokenTermsOptions($options, []);
        $params = self::spokenTermsEntriesToObject($entries);
        $response = $this->client->sendRequestWithBackoff(
            'PUT',
            self::spokenTermsPath($spokenTerms) . '/term-lists',
            [HttpClientWrapper::OPTION_JSON => self::spokenTermsJsonBody($params, $options)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return SpokenTermsList::parseJson($content);
    }

    /**
     * Deletes a spoken terms collection.
     * @param string|SpokenTerms $spokenTerms Spoken terms ID or SpokenTerms object.
     * @throws DeepLException
     */
    public function deleteSpokenTerms($spokenTerms): void
    {
        $response = $this->client->sendRequestWithBackoff('DELETE', self::spokenTermsPath($spokenTerms));
        $this->checkStatusCode($response);
    }

    /**
     * Deletes the term list of one language from a spoken terms collection.
     * @param string|SpokenTerms $spokenTerms Spoken terms ID or SpokenTerms object.
     * @param string $lang Language code of the term list to delete, e.g. "en".
     * @throws \InvalidArgumentException If the language code is empty.
     * @throws DeepLException
     */
    public function deleteSpokenTermsList($spokenTerms, string $lang): void
    {
        ClientUtils::checkLanguageCode($lang, 'lang');
        $query = ClientUtils::buildQuery(['lang' => $lang]);
        $url = self::spokenTermsPath($spokenTerms) . "/term-lists?$query";
        $response = $this->client->sendRequestWithBackoff('DELETE', $url);
        $this->checkStatusCode($response);
    }

    /**
     * @param string|SpokenTerms $spokenTerms
     */
    private static function spokenTermsPath($spokenTerms): string
    {
        return '/v3/spoken-terms/' . rawurlencode(SpokenTerms::getSpokenTermsId($spokenTerms));
    }

    /**
     * @param string[] $allowed Option keys the method accepts besides EXTRA_BODY_PARAMETERS.
     * @throws \InvalidArgumentException If an option key is unknown to the method.
     */
    private static function checkSpokenTermsOptions(array $options, array $allowed): void
    {
        $allowed[] = SpokenTermsOptions::EXTRA_BODY_PARAMETERS;
        foreach (array_keys($options) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException("Unknown spoken terms option: $key");
            }
        }
    }

    /**
     * Encodes the JSON body of a spoken terms request, with the extra body parameters added last. An
     * empty body encodes as {}.
     */
    private static function spokenTermsJsonBody(array $params, array $options): string
    {
        foreach ($options[SpokenTermsOptions::EXTRA_BODY_PARAMETERS] ?? [] as $key => $value) {
            $params[$key] = $value;
        }
        return ClientUtils::encodeJson((object)$params);
    }

    /**
     * @param SpokenTermsEntries[] $termLists
     * @throws DeepLException
     */
    private static function spokenTermsListsToObjects(array $termLists): array
    {
        return array_values(array_map(function (SpokenTermsEntries $entries) {
            return self::spokenTermsEntriesToObject($entries);
        }, $termLists));
    }

    /**
     * Trims every term and checks it like a glossary term before the terms are joined with newlines:
     * a term containing a newline would otherwise be sent as several terms.
     * @throws DeepLException
     */
    private static function spokenTermsEntriesToObject(SpokenTermsEntries $entries): array
    {
        ClientUtils::checkLanguageCode($entries->lang, 'lang');
        $terms = [];
        foreach ($entries->entries as $term) {
            $trimmed = trim($term);
            GlossaryUtils::validateGlossaryTerm($trimmed);
            $terms[] = $trimmed;
        }
        return (new SpokenTermsEntries($entries->lang, $terms))->toObject();
    }

    /**
     * Validates and prepares HTTP parameters for rephrase requests.
     * @param string|null $targetLang Target language code, or null to use default
     * @param string|null $style Writing style option, or null if not specified
     * @param string|null $tone Tone option, or null if not specified
     * @return array Associative array of HTTP parameters
     * @throws DeepLException
     */
    private function buildRephraseBodyParams(
        ?string $targetLang = null,
        ?string $style = null,
        ?string $tone = null
    ): array {
        $params = [];
        if ($targetLang !== null) {
            $targetLang = LanguageCode::standardizeLanguageCode($targetLang);
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
            $queryString = '?' . ClientUtils::buildQuery($params);
        }

        $response = $this->client->sendRequestWithBackoff('GET', "/v3/style_rules$queryString");
        $this->checkStatusCode($response);
        list(, $content) = $response;

        return StyleRuleInfo::parseList($content);
    }

    /**
     * Creates a new style rule on DeepL server.
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
     * @param string $name User-defined name to assign to the style rule.
     * @param string $language Language code for the style rule.
     * @param array|null $configuredRules Optional configured rules to apply.
     * @param array|null $customInstructions List of custom instruction objects (`label`, `prompt`, optional
     *     `source_language`); keys are ignored.
     * @param array $options Request options, see \DeepL\CreateStyleRuleOptions: only EXTRA_BODY_PARAMETERS.
     * @return StyleRuleInfo Details about the created style rule.
     * @throws \InvalidArgumentException If a custom instruction has an empty source_language.
     * @throws DeepLException
     */
    public function createStyleRule(
        string $name,
        string $language,
        ?array $configuredRules = null,
        ?array $customInstructions = null,
        array $options = []
    ): StyleRuleInfo {
        self::checkStyleRuleOptions($options);
        if (strlen($name) === 0) {
            throw new \InvalidArgumentException('name must be a non-empty string');
        }
        if (strlen($language) === 0) {
            throw new \InvalidArgumentException('language must be a non-empty string');
        }

        $params = [
            'name' => $name,
            'language' => $language,
        ];
        if ($configuredRules !== null) {
            $params['configured_rules'] = empty($configuredRules) ? (object)$configuredRules : $configuredRules;
        }
        if ($customInstructions !== null) {
            foreach ($customInstructions as $instruction) {
                if (is_array($instruction)) {
                    ClientUtils::checkLanguageCode($instruction['source_language'] ?? null, 'source_language', true);
                }
            }
            $params['custom_instructions'] = array_values($customInstructions);
        }

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/style_rules',
            [
                HttpClientWrapper::OPTION_JSON => self::styleRuleJsonBody($params, $options),
                HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true,
            ]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return StyleRuleInfo::fromJson(ParsingUtils::decodeJson($content));
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
            throw new \InvalidArgumentException('styleId must be a non-empty string');
        }
        $response = $this->client->sendRequestWithBackoff('GET', "/v3/style_rules/" . rawurlencode($styleId));
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return StyleRuleInfo::fromJson(ParsingUtils::decodeJson($content));
    }

    /**
     * Updates the name of an existing style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule to update.
     * @param string $name New name for the style rule.
     * @param array $options Request options, see \DeepL\UpdateStyleRuleNameOptions: only EXTRA_BODY_PARAMETERS.
     * @return StyleRuleInfo Updated StyleRuleInfo.
     * @throws DeepLException
     * @throws NotFoundException If the style rule is not found.
     */
    public function updateStyleRuleName($styleRule, string $name, array $options = []): StyleRuleInfo
    {
        self::checkStyleRuleOptions($options);
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new \InvalidArgumentException('styleId must be a non-empty string');
        }
        if (strlen($name) === 0) {
            throw new \InvalidArgumentException('name must be a non-empty string');
        }
        $params = ['name' => $name];

        $response = $this->client->sendRequestWithBackoff(
            'PATCH',
            "/v3/style_rules/" . rawurlencode($styleId),
            [HttpClientWrapper::OPTION_JSON => self::styleRuleJsonBody($params, $options)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return StyleRuleInfo::fromJson(ParsingUtils::decodeJson($content));
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
            throw new \InvalidArgumentException('styleId must be a non-empty string');
        }
        $response = $this->client->sendRequestWithBackoff('DELETE', "/v3/style_rules/" . rawurlencode($styleId));
        $this->checkStatusCode($response);
    }

    /**
     * Replaces the configured rules for a style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule to update.
     * @param array $configuredRules The new configured rules to set.
     * @param array $options Request options, see \DeepL\UpdateStyleRuleConfiguredRulesOptions: only
     *  EXTRA_BODY_PARAMETERS, added as further keys of the configured rules object.
     * @return StyleRuleInfo Updated StyleRuleInfo.
     * @throws DeepLException
     * @throws NotFoundException If the style rule is not found.
     */
    public function updateStyleRuleConfiguredRules(
        $styleRule,
        array $configuredRules,
        array $options = []
    ): StyleRuleInfo {
        self::checkStyleRuleOptions($options);
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new \InvalidArgumentException('styleId must be a non-empty string');
        }
        $response = $this->client->sendRequestWithBackoff(
            'PUT',
            "/v3/style_rules/" . rawurlencode($styleId) . "/configured_rules",
            [HttpClientWrapper::OPTION_JSON => self::styleRuleJsonBody($configuredRules, $options)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return StyleRuleInfo::fromJson(ParsingUtils::decodeJson($content));
    }

    /**
     * Creates a custom instruction for a style rule.
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule.
     * @param string $label Label for the custom instruction.
     * @param string $prompt Prompt text for the custom instruction.
     * @param string|null $sourceLanguage Optional source language code.
     * @param array $options Request options, see \DeepL\CreateStyleRuleCustomInstructionOptions: only
     *  EXTRA_BODY_PARAMETERS.
     * @return CustomInstruction The created custom instruction.
     * @throws \InvalidArgumentException If sourceLanguage is an empty string.
     * @throws DeepLException
     * @throws NotFoundException If the style rule is not found.
     */
    public function createStyleRuleCustomInstruction(
        $styleRule,
        string $label,
        string $prompt,
        ?string $sourceLanguage = null,
        array $options = []
    ): CustomInstruction {
        self::checkStyleRuleOptions($options);
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new \InvalidArgumentException('styleId must be a non-empty string');
        }
        if (strlen($label) === 0) {
            throw new \InvalidArgumentException('label must be a non-empty string');
        }
        if (strlen($prompt) === 0) {
            throw new \InvalidArgumentException('prompt must be a non-empty string');
        }
        ClientUtils::checkLanguageCode($sourceLanguage, 'sourceLanguage', true);
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
            [
                HttpClientWrapper::OPTION_JSON => self::styleRuleJsonBody($params, $options),
                HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true,
            ]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return CustomInstruction::fromJson(ParsingUtils::decodeJson($content));
    }

    /**
     * Gets a custom instruction for a style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule.
     * @param string|CustomInstruction $instructionId ID or CustomInstruction of the custom instruction to retrieve.
     * @return CustomInstruction The custom instruction.
     * @throws DeepLException
     * @throws NotFoundException If the style rule or custom instruction is not found.
     */
    public function getStyleRuleCustomInstruction($styleRule, $instructionId): CustomInstruction
    {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new \InvalidArgumentException('styleId must be a non-empty string');
        }
        $instructionId = self::getCustomInstructionId($instructionId);
        $response = $this->client->sendRequestWithBackoff(
            'GET',
            "/v3/style_rules/" . rawurlencode($styleId) . "/custom_instructions/" . rawurlencode($instructionId)
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return CustomInstruction::fromJson(ParsingUtils::decodeJson($content));
    }

    /**
     * Updates a custom instruction for a style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule.
     * @param string|CustomInstruction $instructionId ID or CustomInstruction of the custom instruction to update.
     * @param string $label New label for the custom instruction.
     * @param string $prompt New prompt text for the custom instruction.
     * @param string|null $sourceLanguage Optional source language code.
     * @param array $options Request options, see \DeepL\UpdateStyleRuleCustomInstructionOptions: only
     *  EXTRA_BODY_PARAMETERS.
     * @return CustomInstruction The updated custom instruction.
     * @throws \InvalidArgumentException If sourceLanguage is an empty string.
     * @throws DeepLException
     * @throws NotFoundException If the style rule or custom instruction is not found.
     */
    public function updateStyleRuleCustomInstruction(
        $styleRule,
        $instructionId,
        string $label,
        string $prompt,
        ?string $sourceLanguage = null,
        array $options = []
    ): CustomInstruction {
        self::checkStyleRuleOptions($options);
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new \InvalidArgumentException('styleId must be a non-empty string');
        }
        $instructionId = self::getCustomInstructionId($instructionId);
        if (strlen($label) === 0) {
            throw new \InvalidArgumentException('label must be a non-empty string');
        }
        if (strlen($prompt) === 0) {
            throw new \InvalidArgumentException('prompt must be a non-empty string');
        }
        ClientUtils::checkLanguageCode($sourceLanguage, 'sourceLanguage', true);
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
            [HttpClientWrapper::OPTION_JSON => self::styleRuleJsonBody($params, $options)]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return CustomInstruction::fromJson(ParsingUtils::decodeJson($content));
    }

    /**
     * Deletes a custom instruction from a style rule.
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule.
     * @param string|CustomInstruction $instructionId ID or CustomInstruction of the custom instruction to delete.
     * @throws DeepLException
     * @throws NotFoundException If the style rule or custom instruction is not found.
     */
    public function deleteStyleRuleCustomInstruction($styleRule, $instructionId): void
    {
        $styleId = StyleRuleInfo::getStyleId($styleRule);
        if (strlen($styleId) === 0) {
            throw new \InvalidArgumentException('styleId must be a non-empty string');
        }
        $instructionId = self::getCustomInstructionId($instructionId);
        $response = $this->client->sendRequestWithBackoff(
            'DELETE',
            "/v3/style_rules/" . rawurlencode($styleId) . "/custom_instructions/" . rawurlencode($instructionId)
        );
        $this->checkStatusCode($response);
    }

    /**
     * @param string|CustomInstruction $instruction
     * @throws DeepLException
     */
    private static function getCustomInstructionId($instruction): string
    {
        if ($instruction instanceof CustomInstruction) {
            $instruction = $instruction->id;
        }
        if (!is_string($instruction) || strlen($instruction) === 0) {
            throw new \InvalidArgumentException(
                'instructionId must be a non-empty string or a CustomInstruction with an id'
            );
        }
        return $instruction;
    }

    /**
     * @throws \InvalidArgumentException If an option key is unknown.
     */
    private static function checkStyleRuleOptions(array $options): void
    {
        foreach (array_keys($options) as $key) {
            // All five style rule options classes use this key.
            if ($key !== CreateStyleRuleOptions::EXTRA_BODY_PARAMETERS) {
                throw new \InvalidArgumentException("Unknown style rule option: $key");
            }
        }
    }

    /**
     * Encodes the JSON body of a style rule request, with the extra body parameters added last. An empty
     * body encodes as {}.
     */
    private static function styleRuleJsonBody(array $params, array $options): string
    {
        // All five style rule options classes use this key.
        foreach ($options[CreateStyleRuleOptions::EXTRA_BODY_PARAMETERS] ?? [] as $key => $value) {
            $params[$key] = $value;
        }
        return ClientUtils::encodeJson((object)$params);
    }

    /**
     * Retrieves one page of style profiles.
     * @param int|null $page Page number for pagination, 0-indexed, or null for the API default (0).
     * @param int|null $pageSize Number of items per page, or null for the API default (10). Values
     *  above 100 are reduced to 100 by the API.
     * @return StyleProfile[] The style profiles on the requested page.
     * @throws DeepLException
     */
    public function listStyleProfiles(?int $page = null, ?int $pageSize = null): array
    {
        $params = [];
        if ($page !== null) {
            $params['page'] = (string)$page;
        }
        if ($pageSize !== null) {
            $params['page_size'] = (string)$pageSize;
        }
        $queryString = empty($params) ? '' : '?' . ClientUtils::buildQuery($params);

        $response = $this->client->sendRequestWithBackoff('GET', "/v3/style_profiles$queryString");
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return StyleProfile::parseList($content);
    }

    /**
     * Creates a style profile.
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
     * @param string $name Name of the style profile.
     * @param array $options Optional fields, see \DeepL\CreateStyleProfileOptions. Options that are
     *  null are not sent. If any of the linked component IDs cannot be used, no style profile is created.
     * @return StyleProfile Details about the created style profile.
     * @throws DeepLException
     */
    public function createStyleProfile(string $name, array $options = []): StyleProfile
    {
        $params = self::buildStyleProfileBody(['name' => $name], $options, [
            CreateStyleProfileOptions::DESCRIPTION,
            CreateStyleProfileOptions::INDUSTRY,
        ]);
        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/style_profiles',
            [
                HttpClientWrapper::OPTION_JSON => $params,
                HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true,
            ]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return StyleProfile::parseJson($content);
    }

    /**
     * Gets information about a style profile.
     * @param string|StyleProfile $styleProfile Style profile ID or StyleProfile object.
     * @return StyleProfile Details about the style profile.
     * @throws DeepLException
     * @throws NotFoundException If the style profile is not found.
     */
    public function getStyleProfile($styleProfile): StyleProfile
    {
        $styleProfileId = StyleProfile::getStyleProfileId($styleProfile);
        $response = $this->client->sendRequestWithBackoff(
            'GET',
            '/v3/style_profiles/' . rawurlencode($styleProfileId)
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return StyleProfile::parseJson($content);
    }

    /**
     * Updates a style profile. Options that are not set, or null, are not changed. An empty string
     * clears the description or industry; an ID list replaces the linked list in full, and an empty
     * array unlinks all components of that kind. Without options, an empty update ({}) is sent.
     * @param string|StyleProfile $styleProfile Style profile ID or StyleProfile object.
     * @param array $options Fields to change, see \DeepL\UpdateStyleProfileOptions.
     * @return StyleProfile Details about the updated style profile.
     * @throws DeepLException
     * @throws NotFoundException If the style profile is not found.
     */
    public function updateStyleProfile($styleProfile, array $options = []): StyleProfile
    {
        $styleProfileId = StyleProfile::getStyleProfileId($styleProfile);
        $params = self::buildStyleProfileBody([], $options, [
            UpdateStyleProfileOptions::NAME,
            UpdateStyleProfileOptions::DESCRIPTION,
            UpdateStyleProfileOptions::INDUSTRY,
        ]);
        $response = $this->client->sendRequestWithBackoff(
            'PATCH',
            '/v3/style_profiles/' . rawurlencode($styleProfileId),
            [HttpClientWrapper::OPTION_JSON => $params]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;
        return StyleProfile::parseJson($content);
    }

    /**
     * Deletes a style profile.
     * @param string|StyleProfile $styleProfile Style profile ID or StyleProfile object.
     * @throws DeepLException
     * @throws NotFoundException If the style profile is not found.
     */
    public function deleteStyleProfile($styleProfile): void
    {
        $styleProfileId = StyleProfile::getStyleProfileId($styleProfile);
        $response = $this->client->sendRequestWithBackoff(
            'DELETE',
            '/v3/style_profiles/' . rawurlencode($styleProfileId)
        );
        $this->checkStatusCode($response);
    }

    /**
     * Encodes the JSON body of a style profile request: $params, then the options that are set, then
     * the extra body parameters. An empty body encodes as {}.
     * @param string[] $stringKeys The string option keys the method accepts.
     * @throws \InvalidArgumentException If an option key is unknown to the method, or an ID list entry is neither
     *  an ID nor an object of the expected resource class.
     */
    private static function buildStyleProfileBody(array $params, array $options, array $stringKeys): string
    {
        $idListClasses = [
            CreateStyleProfileOptions::GLOSSARY_IDS => [GlossaryInfo::class, MultilingualGlossaryInfo::class],
            CreateStyleProfileOptions::STYLE_RULE_IDS => [StyleRuleInfo::class],
            CreateStyleProfileOptions::TRANSLATION_MEMORY_IDS => [TranslationMemoryInfo::class],
        ];
        $boolKey = CreateStyleProfileOptions::GLOSSARY_PRIORITY_ORDER_ENABLED;
        $extraKey = CreateStyleProfileOptions::EXTRA_BODY_PARAMETERS;

        foreach ($options as $key => $value) {
            $isIdList = isset($idListClasses[$key]);
            if (!$isIdList && !in_array($key, array_merge($stringKeys, [$boolKey, $extraKey]), true)) {
                throw new \InvalidArgumentException("Unknown style profile option: $key");
            }
            if (!isset($value) || $key === $extraKey) {
                continue;
            }
            if ($isIdList) {
                $params[$key] = array_values(array_map(function ($ref) use ($key, $idListClasses) {
                    return self::styleProfileComponentId($ref, $key, $idListClasses[$key]);
                }, (array)$value));
            } elseif ($key === $boolKey) {
                $params[$key] = (bool)$value;
            } else {
                $params[$key] = (string)$value;
            }
        }
        foreach ($options[$extraKey] ?? [] as $key => $value) {
            $params[$key] = $value;
        }
        return ClientUtils::encodeJson((object)$params);
    }

    /**
     * @param mixed $ref An ID, or an object of one of $classes.
     * @param string[] $classes Resource classes accepted for the option.
     * @throws \InvalidArgumentException If $ref is an object of another class.
     */
    private static function styleProfileComponentId($ref, string $option, array $classes): string
    {
        if (!is_object($ref)) {
            return (string)$ref;
        }
        $idProperties = [
            GlossaryInfo::class => 'glossaryId',
            MultilingualGlossaryInfo::class => 'glossaryId',
            StyleRuleInfo::class => 'styleId',
            TranslationMemoryInfo::class => 'translationMemoryId',
        ];
        foreach ($classes as $class) {
            if ($ref instanceof $class) {
                return $ref->{$idProperties[$class]};
            }
        }
        $names = array_map(function (string $class): string {
            return substr($class, strlen(__NAMESPACE__) + 1);
        }, $classes);
        throw new \InvalidArgumentException("$option entries must be IDs or " . implode(' or ', $names) . ' objects');
    }

    /**
     * Queries languages supported by the DeepL API for the given resource. Returns each
     * language with its per-feature support information.
     * @param string $resource Resource to query support for, see {@see LanguageResourceName}.
     * @param array|null $include Options array, see {@see GetLanguagesForResourceOptions}, or a plain list of
     *  include values, for example [{@see LanguageInclude::BETA}, {@see LanguageInclude::EXTERNAL}]. By default
     *  only stable languages and features are returned.
     * @return LanguageSupport[] Array of LanguageSupport objects containing per-feature support information.
     * @throws DeepLException
     */
    public function getLanguagesForResource(string $resource, ?array $include = null): array
    {
        if (strlen($resource) === 0) {
            throw new \InvalidArgumentException('resource must be a non-empty string');
        }

        // The parameter takes an options array or, as released first, the plain list of include values
        if ($include !== null && $include !== array_values($include)) {
            self::checkOptionKeys($include, GetLanguagesForResourceOptions::class, 'languages');
            $include = $include[GetLanguagesForResourceOptions::INCLUDE] ?? null;
            if ($include !== null && !is_array($include)) {
                throw new \InvalidArgumentException('include option must be an array of strings');
            }
        }

        $queryString = '?' . ClientUtils::buildQuery(['resource' => $resource]);
        if ($include !== null) {
            // include is repeated as include=<value>; buildQuery() would index it as include[0]=
            foreach ($include as $value) {
                $queryString .= '&include=' . rawurlencode($value);
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
        $queryString = empty($queryParams) ? '' : '?' . ClientUtils::buildQuery($queryParams);

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
        self::checkOptionKeys($options, TranslationMemorySegmentsOptions::class, 'translation memory segments');
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
        $queryString = empty($queryParams)
            ? ''
            : '?' . ClientUtils::buildQuery($queryParams);

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
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
     * @param string $fileName Name of the TMX file to import, for example 'legal.tmx'.
     * @param int $contentLength Size of the TMX file in bytes.
     * @param array|string|null $options Request options, see \DeepL\CreateTranslationMemoryImportOptions:
     *  CONTENT_TYPE, DISPLAY_NAME and EXTRA_BODY_PARAMETERS. A string is the MIME type of the file, as
     *  CONTENT_TYPE; the API defaults it to 'application/xml'.
     * @param string|null $displayName Optional name for the resulting translation memory, defaults
     *  to the file name. Only with a string or null $options; with an array, use DISPLAY_NAME.
     * @return TranslationMemoryImport The job ID and upload URL.
     * @throws DeepLException
     */
    public function createTranslationMemoryImport(
        string $fileName,
        int $contentLength,
        $options = null,
        ?string $displayName = null
    ): TranslationMemoryImport {
        $options = self::translationMemoryOptions(
            $options,
            CreateTranslationMemoryImportOptions::CONTENT_TYPE,
            CreateTranslationMemoryImportOptions::DISPLAY_NAME,
            $displayName,
            [CreateTranslationMemoryImportOptions::CONTENT_TYPE, CreateTranslationMemoryImportOptions::DISPLAY_NAME]
        );
        $contentType = $options[CreateTranslationMemoryImportOptions::CONTENT_TYPE] ?? null;
        $displayName = $options[CreateTranslationMemoryImportOptions::DISPLAY_NAME] ?? null;
        if (strlen($fileName) === 0) {
            throw new \InvalidArgumentException('fileName must be a non-empty string');
        }
        if ($contentLength <= 0) {
            throw new \InvalidArgumentException('contentLength must be greater than 0');
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
        foreach ($options[CreateTranslationMemoryImportOptions::EXTRA_BODY_PARAMETERS] ?? [] as $key => $value) {
            $params[$key] = $value;
        }

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/translation_memories/import',
            [
                HttpClientWrapper::OPTION_JSON => ClientUtils::encodeJson($params),
                HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true,
            ]
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
            throw new \InvalidArgumentException('uploadUrl must be a non-empty string');
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
     * To avoid creating duplicates, the request is only retried if it was not processed: after HTTP 429,
     * or a connection failure before the request was sent. With a custom HTTP client, only HTTP 429 is retried.
     * A failed request throws an exception whose shouldRetry is true only for these failures and for a
     * response body cut off mid-read.
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
            "/v3/translation_memories/" . rawurlencode($translationMemoryId) . "/export",
            [HttpClientWrapper::OPTION_RETRY_ONLY_UNPROCESSED => true]
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
            throw new \InvalidArgumentException('jobId must be a non-empty string');
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
     * Returns when the given translation memory job completes, or throws an exception if there was
     * an error communicating with the DeepL API, the job failed or expired, or the timeout expired.
     *
     * The job is polled every 5 seconds until its status is 'completed', which returns the job, or
     * 'failed' or 'expired', which throw a DeepLException with the API's error message.
     * Any other status, including one this library does not know, keeps polling. Note that an
     * import job keeps reporting 'awaiting_input' for a while after its file has been uploaded,
     * because the API detects the upload asynchronously. A job whose file is never uploaded does
     * not finish on its own, so pass a timeout when that is a possibility.
     * @param string|TranslationMemoryJob $job Job ID or TranslationMemoryJob of the job to wait for.
     * @param array|float|null $options Wait options, see \DeepL\WaitOptions: only TIMEOUT; or the maximum
     *  time in seconds to wait, as a number. Unset waits without limit. The last sleep between polls is
     *  shortened so the wait does not run past it.
     * @return TranslationMemoryJob The completed job.
     * @throws DeepLException
     */
    public function waitUntilTranslationMemoryJobDone($job, $options = null): TranslationMemoryJob
    {
        $timeout = self::translationMemoryTimeout($options);
        $latest = null;
        return $this->pollTranslationMemoryJob(TranslationMemoryJob::getJobId($job), $timeout, $latest);
    }

    /**
     * Polls the job until it is done; $latest is set to each status fetched, so a caller can report it
     * on failure.
     * @throws DeepLException
     */
    private function pollTranslationMemoryJob(
        string $jobId,
        ?float $timeout,
        ?TranslationMemoryJob &$latest
    ): TranslationMemoryJob {
        $deadline = $timeout === null ? null : microtime(true) + $timeout;
        $status = $latest = $this->getTranslationMemoryJob($jobId);
        while (!$status->done() && $status->ok()) {
            // The job duration is not reported by the API, so just poll equidistantly
            $secs = 5.0;
            if ($deadline !== null) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    throw new DeepLException("Manual timeout of {$timeout}s exceeded for translation memory job");
                }
                $secs = min($secs, $remaining);
            }
            usleep((int)round($secs * 1000000));
            $this->client->logInfo("Rechecking translation memory job status after sleeping for $secs seconds.");
            $status = $latest = $this->getTranslationMemoryJob($jobId);
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
     * @param array|string|null $options Request options, see \DeepL\ImportTranslationMemoryOptions: CONTENT_TYPE
     *  (used for the import job and the upload, default 'application/xml'), DISPLAY_NAME, TIMEOUT and
     *  EXTRA_BODY_PARAMETERS. A string is the name for the resulting translation memory, as DISPLAY_NAME; it
     *  defaults to the file name.
     * @return TranslationMemoryJob The completed import job; its result carries the ID of the new
     *  translation memory.
     * @throws \InvalidArgumentException If no file exists at the input file path, before any request is
     *  sent; not wrapped.
     * @throws TranslationMemoryJobException For every other failure, with the cause as the previous exception:
     *  an invalid option, a third argument or an empty or unreadable input file before any request (jobId and
     *  job null), or a failure of the job creation, the upload or the wait; jobId is set once the job was
     *  created, job once its status was fetched.
     */
    public function importTranslationMemoryFromFilepath(string $inputFile, $options = null): TranslationMemoryJob
    {
        $jobId = null;
        $job = null;
        try {
            // The released third parameter $timeout was removed; PHP would silently ignore a third argument.
            if (func_num_args() > 2) {
                throw new \InvalidArgumentException('pass all options in the options array, not as further arguments');
            }
            $options = self::translationMemoryOptions(
                $options,
                ImportTranslationMemoryOptions::DISPLAY_NAME,
                ImportTranslationMemoryOptions::TIMEOUT,
                null,
                [
                    ImportTranslationMemoryOptions::CONTENT_TYPE,
                    ImportTranslationMemoryOptions::DISPLAY_NAME,
                    ImportTranslationMemoryOptions::TIMEOUT,
                ]
            );
            $timeout = self::translationMemoryTimeout($options[ImportTranslationMemoryOptions::TIMEOUT] ?? null);
            unset($options[ImportTranslationMemoryOptions::TIMEOUT]);
        } catch (\Throwable $error) {
            throw self::translationMemoryJobException($error, null, null);
        }
        if (!file_exists($inputFile)) {
            throw new \InvalidArgumentException("File does not exist at input file path $inputFile");
        }
        try {
            if (is_dir($inputFile) || !is_readable($inputFile)) {
                throw new DeepLException("Failed reading input file $inputFile: the file is not readable");
            }
            error_clear_last();
            // Suppressed so error handlers that turn warnings into exceptions see the DeepLException
            $fileContent = @file_get_contents($inputFile);
            if ($fileContent === false) {
                $error = error_get_last();
                $cause = $error === null
                    ? null
                    : new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
                throw new DeepLException("Failed reading input file $inputFile", 0, $cause);
            }

            $created = $this->createTranslationMemoryImport(basename($inputFile), strlen($fileContent), $options);
            $jobId = $created->jobId;
            $this->uploadTranslationMemoryFile(
                $created,
                $fileContent,
                $options[ImportTranslationMemoryOptions::CONTENT_TYPE] ?? 'application/xml'
            );
            return $this->pollTranslationMemoryJob($jobId, $timeout, $job);
        } catch (\Throwable $error) {
            throw self::translationMemoryJobException($error, $jobId, $job);
        }
    }

    /**
     * Exports a translation memory to a TMX file: creates the export job, waits for it to finish,
     * and writes the result to the specified output file path.
     * @param string|TranslationMemoryInfo $translationMemory Translation memory ID or
     *  TranslationMemoryInfo of the translation memory to export.
     * @param string $outputFile String containing file path to create the exported TMX file.
     * @param array|float|null $options Wait options, see \DeepL\WaitOptions: only TIMEOUT; or the maximum
     *  time in seconds to wait for the export to finish, as a number. Unset waits without limit.
     * @return TranslationMemoryJob The completed export job.
     * @throws TranslationMemoryJobException For every failure, with the cause as the previous exception: if a
     *  file already exists at the output file path or its directory does not exist or is not writable, checked
     *  before the export job is created (jobId and job null), or a failure of the job creation, the wait or the
     *  download; jobId is set once the job was created, job once its status was fetched.
     */
    public function exportTranslationMemoryToFilepath(
        $translationMemory,
        string $outputFile,
        $options = null
    ): TranslationMemoryJob {
        $jobId = null;
        $job = null;
        try {
            $timeout = self::translationMemoryTimeout($options);
            if (file_exists($outputFile)) {
                throw new DeepLException("File already exists at output file path $outputFile");
            }
            $outputDir = dirname($outputFile);
            if (!is_dir($outputDir) || !is_writable($outputDir)) {
                throw new DeepLException(
                    "Failed to open output file $outputFile for writing: the directory does not exist or is not " .
                    'writable'
                );
            }
            $jobId = $this->createTranslationMemoryExport($translationMemory)->jobId;
            $completed = $this->pollTranslationMemoryJob($jobId, $timeout, $job);
            $this->downloadTranslationMemoryExport($completed, $outputFile);
            return $completed;
        } catch (\Throwable $error) {
            throw self::translationMemoryJobException($error, $jobId, $job);
        }
    }

    /**
     * Wraps a failure of a translation memory file helper, copying httpStatusCode and shouldRetry from a
     * DeepLException cause.
     */
    private static function translationMemoryJobException(
        \Throwable $error,
        ?string $jobId,
        ?TranslationMemoryJob $job
    ): TranslationMemoryJobException {
        $exception = new TranslationMemoryJobException(
            $error->getMessage() ?: 'unknown error',
            $error->getCode(),
            $error,
            $jobId,
            $job
        );
        if ($error instanceof DeepLException) {
            $exception->httpStatusCode = $error->httpStatusCode;
            $exception->shouldRetry = $error->shouldRetry;
        }
        return $exception;
    }

    /**
     * Returns the options array of a translation memory method whose options parameter also takes the
     * released positional form: a string for $scalarKey, then $second for $secondKey.
     * @param array|string|null $options
     * @param mixed $second
     * @param string[] $allowed Option keys the method accepts besides EXTRA_BODY_PARAMETERS.
     * @throws DeepLException
     */
    private static function translationMemoryOptions(
        $options,
        string $scalarKey,
        string $secondKey,
        $second,
        array $allowed
    ): array {
        if (!is_array($options)) {
            if ($options !== null && !is_string($options)) {
                throw new \InvalidArgumentException('options must be an array, a string or null');
            }
            return array_filter([$scalarKey => $options, $secondKey => $second], function ($value) {
                return $value !== null;
            });
        }
        if ($second !== null) {
            throw new \InvalidArgumentException('pass all options in the options array, not as further arguments');
        }
        // Both import options classes use this key.
        $allowed[] = CreateTranslationMemoryImportOptions::EXTRA_BODY_PARAMETERS;
        foreach (array_keys($options) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException("Unknown translation memory option: $key");
            }
        }
        return $options;
    }

    /**
     * Returns the timeout in seconds of a wait helper, from a number or a WaitOptions array.
     * @param array|float|int|null $timeout
     * @throws DeepLException
     */
    private static function translationMemoryTimeout($timeout): ?float
    {
        if (is_array($timeout)) {
            foreach (array_keys($timeout) as $key) {
                if ($key !== WaitOptions::TIMEOUT) {
                    throw new \InvalidArgumentException("Unknown translation memory wait option: $key");
                }
            }
            $timeout = $timeout[WaitOptions::TIMEOUT] ?? null;
        }
        if ($timeout === null) {
            return null;
        }
        if (!is_numeric($timeout)) {
            throw new \InvalidArgumentException('timeout must be a number of seconds');
        }
        return (float)$timeout;
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
            throw new \InvalidArgumentException('translationMemoryId must be a non-empty string');
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
        throw ClientUtils::withStatus(
            new DeepLException("Error $description, HTTP status: $statusCode, content: $detail"),
            $statusCode
        );
    }
}
