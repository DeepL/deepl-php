# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Added the document translation step methods `translateDocumentUpload()`,
  `translateDocumentGetStatus()`, `translateDocumentWaitUntilDone()` and
  `translateDocumentDownload()`, replacing `uploadDocument()`,
  `getDocumentStatus()`, `waitUntilDocumentTranslationComplete()` and
  `downloadDocument()`.
- Added the `timeout` option (`TranslateDocumentOptions::TIMEOUT`, seconds) to
  `translateDocument()` and `translateDocumentWaitUntilDone()`. When it
  expires, `translateDocumentWaitUntilDone()` throws a `DeepLException` and
  `translateDocument()` a `DocumentTranslationException` with the document
  handle. Without it, both wait without limit as before.
- Added the `filename` document translation option
  (`TranslateDocumentOptions::FILENAME`), the file name sent with the upload.
  It defaults to the base name of the input file path.
- Added the `style_rule` (`TranslateDocumentOptions::STYLE_RULE`) and
  `translation_memory` (`TranslateDocumentOptions::TRANSLATION_MEMORY`)
  document translation options, replacing `style_id` and
  `translation_memory_id`.
- Added the `DocumentStatusCode` constants class for the values of
  `DocumentStatus::$status`, and `DocumentStatus::$documentId`, the
  `document_id` returned by the API, or `null` if the response does not
  include it.
- Added the `httpStatusCode` and `shouldRetry` properties to `DeepLException`.
  `httpStatusCode` is the HTTP status of the failed response, or `null` if
  there was none. `shouldRetry` is `true` for HTTP 429, any 5xx status and
  transient connection failures; it was previously only set on
  `ConnectionException`.
- Added `Translator::isFreeAccountAuthKey()` (also available on `DeepLClient`),
  replacing `isAuthKeyFreeAccount()`.
- Added the `style_profile` option (`TranslateTextOptions::STYLE_PROFILE`
  and `TranslateDocumentOptions::STYLE_PROFILE`) to translate text and
  documents with a style profile, given as an ID string or a `StyleProfile`.
- Added the `style_rule` (`TranslateTextOptions::STYLE_RULE`) and
  `translation_memory` (`TranslateTextOptions::TRANSLATION_MEMORY`) text
  translation options, taking an ID string or a `StyleRuleInfo` /
  `TranslationMemoryInfo`. They replace `style_id` and `translation_memory_id`.
- Added the constants classes `Formality`, `SentenceSplittingMode`,
  `TagHandlingMode`, `TagHandlingVersion` and `ModelType` for the values of the
  `formality`, `split_sentences`, `tag_handling`, `tag_handling_version` and
  `model_type` options. The options still accept any string.
- Added `TextResult::$tagHandlingVersion`, the `tag_handling_version` returned
  by the API, or `null` if the response does not include it.
- Added the `DeepLAdminClient` class for the Admin API. It must be created with
  an admin API key and supports managing developer keys
  (`createDeveloperKey()`, `listDeveloperKeys()`, `deactivateDeveloperKey()`,
  `renameDeveloperKey()`, `setDeveloperKeyUsageLimits()`) and retrieving usage
  analytics (`getUsageAnalytics()`, `getCustomTagUsageAnalytics()`).
  `createDeveloperKey()` takes the label as an option
  (`CreateDeveloperKeyOptions::LABEL`). All four developer key changes accept
  an `extra_body_parameters` option (`CreateDeveloperKeyOptions` and
  `DeveloperKeyOptions::EXTRA_BODY_PARAMETERS`), added last to the JSON body.
  `setDeveloperKeyUsageLimits()` changes only the limits given with the
  `DeveloperKeyUsageLimitsOptions` keys, including speech-to-speech.
  `DeveloperKey::$label`, `DeveloperKey::$usageLimits` and
  `KeyUsage::$apiKeyLabel` are `null` when the response omits them.
  New public types: `DeveloperKey`, `DeveloperKeyUsageLimits`,
  `DeveloperKeyUsageLimitsOptions`, `CreateDeveloperKeyOptions`,
  `DeveloperKeyOptions`, `AdminUsageReport`, `AdminUsageBreakdown`,
  `AdminUsageGroupBy`, `KeyUsage`, `CustomTagUsageReport`, `CustomTagUsage`,
  `CustomTagUsageBreakdown` and `CustomTagUsageAggregateBy`.
- Added language code constants for the target variants `de-CH`, `de-DE`,
  `es-ES`, `fr-CA` and `fr-FR` to `LanguageCode`.
- Added support for the `/v2/write/correct` endpoint in the `DeepLClient` class:
  `correctText()`. This applies a minimal-change spelling and grammar
  correction pass without rewriting the text for style or tone. It takes an
  optional options array; `CorrectTextOptions::EXTRA_BODY_PARAMETERS` adds
  parameters last to the request body, replacing fields with the same key.
- Added the `extra_body_parameters` option
  (`RephraseTextOptions::EXTRA_BODY_PARAMETERS`) to `rephraseText()`, added
  last to the request body and replacing fields with the same key. It was
  previously ignored.
- Added the `WritingStyle` and `WritingTone` constants classes for the values
  of the `writing_style` and `tone` rephrase options. Other strings are still
  accepted.
- Added the `TranslateDocumentOptions::OUTPUT_FORMAT` option to request the
  file format of the translated document, for example `docx`. When it differs
  from the input file's format, `ENABLE_DOCUMENT_MINIFICATION` is ignored.
- Added the `TranslateDocumentOptions::ENABLE_WATERMARK` option to add a
  "Translated by DeepL" watermark to translated `docx` and `pdf` documents.
- Added the API Pro fields of `getUsage()` to `Usage`: `apiKeyCharacter`,
  `speechToTextMinutes`, `speechToSpeechMinutes`, `startTime`, `endTime` and
  `products` (an array of the new `UsageProduct` class). They are `null` when
  the response does not include them, for example for API Free accounts.
- Added support for the Spoken Terms API (`/v3/spoken-terms`) in the
  `DeepLClient` class: `createSpokenTerms()`, `listSpokenTerms()`,
  `getSpokenTerms()`, `updateSpokenTerms()`, `getSpokenTermsEntries()`,
  `replaceSpokenTermsList()`, `deleteSpokenTerms()` and
  `deleteSpokenTermsList()`, with the new classes `SpokenTerms`,
  `SpokenTermsList`, `SpokenTermsEntries` and `SpokenTermsOptions`.
  `updateSpokenTerms()` takes the new name and the term lists to merge as the
  options `SpokenTermsOptions::NAME` and `SpokenTermsOptions::TERM_LISTS`.
  `createSpokenTerms()`, `updateSpokenTerms()` and `replaceSpokenTermsList()`
  accept `SpokenTermsOptions::EXTRA_BODY_PARAMETERS`. Terms are trimmed before
  they are checked and sent, and spoken terms IDs are URL-encoded in request
  paths.
- Added support for the style profile management API (`/v3/style_profiles`) in
  the `DeepLClient` class: `listStyleProfiles()`, `createStyleProfile()`,
  `getStyleProfile()`, `updateStyleProfile()` and `deleteStyleProfile()`, with
  the new classes `StyleProfile`, `CreateStyleProfileOptions` and
  `UpdateStyleProfileOptions` (which adds `NAME`). The options of
  `updateStyleProfile()` are optional; without them an empty update is sent.
  The glossary, style rule and translation memory ID lists take IDs or
  `GlossaryInfo` / `MultilingualGlossaryInfo`, `StyleRuleInfo` and
  `TranslationMemoryInfo` objects. Both options classes accept
  `EXTRA_BODY_PARAMETERS`. `StyleProfile::$creationTime` and `$updatedTime`
  are required: a response without a valid timestamp throws an
  `InvalidContentException`.
- Added the `LanguageResourceName` constants class for the resource values of
  `getLanguagesForResource()` and `LanguageResource::$resource`, and the
  `LanguageInclude` constants class for its include values.
- `getLanguagesForResource()` also accepts an options array as its second
  parameter, with the `include` option
  (`GetLanguagesForResourceOptions::INCLUDE`). The plain list of include values
  keeps working.
- Added `updateMultilingualGlossaryName()`,
  `updateMultilingualGlossaryDictionary()`,
  `updateMultilingualGlossaryDictionaryFromCsv()` and
  `replaceMultilingualGlossaryDictionaryFromCsv()`. They take an optional
  options array, see `MultilingualGlossaryOptions`: `EXTRA_BODY_PARAMETERS`
  adds parameters to the JSON request body.
- Added `getMultilingualGlossaryDictionaryEntries()`, returning the
  `MultilingualGlossaryDictionaryEntries` of one dictionary, or throwing
  `GlossaryNotFoundException` if the glossary has no such dictionary.
- Added the `TranslationMemoryJobStatus` constants class for the values of
  `TranslationMemoryJobResult::$status`; the `TranslationMemoryJobResult::STATUS_*`
  constants refer to it.

### Changed
- An `Authorization` header in the `headers` option (in any casing) is now
  ignored, so it can no longer replace the `DeepL-Auth-Key` authorization.
- Connection resets (`CURLE_RECV_ERROR`) and broken pipes (`CURLE_SEND_ERROR`)
  are now retried like other transient network failures. Proxy failures,
  such as a proxy that cannot be resolved (`CURLE_COULDNT_RESOLVE_PROXY`), are
  not retried.
- `translateDocument()` throws an `InvalidArgumentException` before sending any
  request if no file exists at the input file path.
- `importTranslationMemoryFromFilepath()` throws an `InvalidArgumentException`
  instead of a `DeepLException` before sending any request if no file exists
  at the input file path.
- `translateDocumentWaitUntilDone()` and `translateDocument()` cut the last
  sleep of the wait to the `timeout`, so the wait no longer runs past it.
- `translateText()` accepts the `glossary` and `glossary_ids` options without a
  source language; the API detects the source language. Document translation
  still requires `sourceLang` with a glossary.
- The `max_retries` option now counts retries after the first attempt, as
  documented: `max_retries => 5` (the default) sends a request at most 6
  times, and `0` disables retries. Previously it counted attempts, so the
  default made only 4 retries and `max_retries => 1` made none.
- DNS resolution failures (`CURLE_COULDNT_RESOLVE_HOST`) are now retried like
  other transient network failures. `createDeveloperKey()` retries them too, as such a request was not
  sent.
- The User-Agent platform information no longer includes the cURL version, and
  has a space between the OS version and the machine type:
  `deepl-php/<version> (<os>) php/<version>`.
- `extra_body_parameters` values are sent as given in JSON request bodies
  (text translation): booleans, numbers, arrays and objects keep their JSON
  type. Previously every value was converted to a string, so `true` was sent as
  `"1"` and arrays failed. In document uploads (a multipart form), booleans are
  now sent as `true`/`false` and arrays and objects as JSON. Objects with
  `__toString()` that are not `JsonSerializable` are still sent as their
  string, and a value that cannot be JSON-encoded throws a `DeepLException`.
- Target languages `en` and `pt` are no longer rejected client-side; the API
  translates them to `en-US` and `pt-PT` respectively.
- `getSourceLanguages()`, `getTargetLanguages()`, and `getGlossaryLanguages()`
  now call `/v3/languages` internally instead of the deprecated
  `/v2/languages` and `/v2/glossary-language-pairs` endpoints. The returned
  lists may include additional languages. No change to public method
  signatures or return types.

### Deprecated
- Deprecated `Translator`; construct a `DeepLClient` instead, which extends
  it. The methods of `Translator` move into `DeepLClient` in the next major
  version.
- Deprecated `GlossaryNotFoundException`; catch its parent
  `NotFoundException` instead. Glossary requests still throw it until the next
  major version.
- Deprecated `Translator::uploadDocument()`, `getDocumentStatus()`,
  `waitUntilDocumentTranslationComplete()` and `downloadDocument()`; use
  `translateDocumentUpload()`, `translateDocumentGetStatus()`,
  `translateDocumentWaitUntilDone()` and `translateDocumentDownload()`. They
  keep working, raise a silenced `E_USER_DEPRECATED` notice once per process,
  and will be removed in the next major version.
- Deprecated the document translation options
  `TranslateDocumentOptions::STYLE_ID` and
  `TranslateDocumentOptions::TRANSLATION_MEMORY_ID`; use `STYLE_RULE` and
  `TRANSLATION_MEMORY` instead. They keep working, raise a silenced
  `E_USER_DEPRECATED` notice once per process, and will be removed in the
  next major version.
- Deprecated the text translation options `TranslateTextOptions::STYLE_ID`
  (`style_id`) and `TranslateTextOptions::TRANSLATION_MEMORY_ID`
  (`translation_memory_id`); use `STYLE_RULE` and `TRANSLATION_MEMORY` instead.
  They keep working, raise a silenced `E_USER_DEPRECATED` notice once per
  process, and will be removed in the next major version.
- Deprecated the `split_sentences` aliases `'on'`, `'default'` and `'off'`;
  use the `SentenceSplittingMode` values `'1'`, `'0'` and `'nonewlines'`. The
  aliases keep working and raise a silenced `E_USER_DEPRECATED` notice once per
  process.
- Deprecated `Translator::isAuthKeyFreeAccount()`; use
  `isFreeAccountAuthKey()` instead. It keeps working and raises a silenced
  `E_USER_DEPRECATED` notice once per process.
- Deprecated the `LanguageCode` constants (for example `LanguageCode::GERMAN`).
  Pass the language code string instead, for example `'de'`, or use
  `getSourceLanguages()` / `getTargetLanguages()`. The constants keep working
  and will be removed in the next major version.
- Deprecated document minification: the
  `TranslateDocumentOptions::ENABLE_DOCUMENT_MINIFICATION` option and the
  `DocumentMinifier`, `DocumentMinificationException` and
  `DocumentDeminificationException` classes. They keep working and will be
  removed in the next major version; the DeepL API accepts larger documents
  (see https://developers.deepl.com/docs/resources/usage-limits).
  `translateDocument()` raises a silenced `E_USER_DEPRECATED` notice, once per
  process, when the option is set.
- Deprecated `DeepLClient::buildRephraseBodyParams()` and the `GlossaryUtils`
  class, which are internal. They keep working; in the next major version
  `buildRephraseBodyParams()` becomes private and `GlossaryUtils` is removed
  from the public API. Use `GlossaryEntries::fromTsv()`,
  `GlossaryEntries::fromEntries()` and `GlossaryEntries::convertToTsv()`
  instead of `GlossaryUtils`.
- Deprecated the `LanguageResource::RESOURCE_*` constants; use the
  `LanguageResourceName` constants instead. Deprecated
  `LanguageSupport::INCLUDE_BETA` and `INCLUDE_EXTERNAL`; use
  `LanguageInclude::BETA` and `EXTERNAL` instead. They keep working and will be
  removed in the next major version.
- Deprecated `getMultilingualGlossaryEntries()`, which returns the dictionary
  in a one-element array; use `getMultilingualGlossaryDictionaryEntries()`. It
  keeps working and raises a silenced `E_USER_DEPRECATED` notice once per
  process.

### Fixed
- Fixed a `User-Agent` header spelled in another case in the `headers` option,
  such as `user-agent`, raising an undefined-key warning on construction and
  failing translation memory uploads and export downloads. The custom
  User-Agent is now sent to the storage URLs as well.
- A negative `max_retries` option now throws a `DeepLException` on construction.
  Previously no request was sent and the call failed with a `TypeError`.
- Query string values encode a space as `%20` instead of `+`, for example the
  `lang` of `getSpokenTermsEntries()` and the `group_by` of
  `getUsageAnalytics()`.
- `translateDocument()` wraps every failure in a `DocumentTranslationException`,
  including an unreadable input file and exceptions that are not a
  `DeepLException`, and copies `httpStatusCode` and `shouldRetry` from a
  `DeepLException` cause.
- `translateDocumentDownload()` throws the API's error message on a failed
  download with the built-in cURL transport, instead of `content: 1`.
- `translateDocumentDownload()` and `downloadTranslationMemoryExport()` throw a
  `DeepLException` with a custom HTTP client when the output file cannot be
  fully written, instead of leaving a truncated file and returning.
- `MultilingualGlossaryDictionaryEntries` trims and validates its entries when
  they are sent, so a dictionary whose `entries` were changed after
  construction, or one returned by the API, is no longer sent untrimmed,
  unvalidated or empty.
- Fixed `waitUntilTranslationMemoryJobDone()` (and so
  `importTranslationMemoryFromFilepath()` and
  `exportTranslationMemoryToFilepath()`) returning a job whose status is
  `downloaded` or a status this library does not know; it now polls until the
  status is `completed`, `failed` or `expired`.
- Fixed `waitUntilTranslationMemoryJobDone()` running up to 5 seconds past its
  timeout; the last sleep is now shortened to the deadline.
- Fixed `importTranslationMemoryFromFilepath()` emitting a PHP warning for an
  input file that exists but cannot be read, which error handlers that turn
  warnings into exceptions (Laravel, Symfony) raised as `ErrorException`. It
  now raises only a `DeepLException`, before any request.
- `translateDocument()` calls `uploadDocument()`, `getDocumentStatus()`,
  `waitUntilDocumentTranslationComplete()` and `downloadDocument()` again when
  a subclass overrides them.
- Fixed `getUsage()` raising a raw `TypeError` for a malformed response. A
  response body that is not a JSON object now raises
  `InvalidContentException`, and a usage type whose `*_count` or `*_limit` is
  missing, `null` or not numeric is left `null` instead.
- Fixed `translateDocumentUpload()` (and the deprecated `uploadDocument()`)
  accepting a non-string `document_id` or `document_key` in the response, for
  example a number; it now raises `InvalidContentException`.
- Fixed the document ID not being URL-encoded in the request paths of
  `translateDocumentGetStatus()` and `translateDocumentDownload()` (and the
  deprecated `getDocumentStatus()` and `downloadDocument()`).
- Fixed `translateText()` raising `InvalidContentException` when a translation
  has no `billed_characters`; `TextResult::$billedCharacters` is now `null` in
  that case.
- Fixed HTTP 401 raising a generic `DeepLException`; it now raises
  `AuthorizationException`, like 403.
- Fixed 3xx responses being parsed as successful results; only 2xx statuses
  are successful, other statuses raise a `DeepLException`.
- Fixed the authentication key not being trimmed: surrounding whitespace was
  sent in the Authorization header and hid the `:fx` suffix of free keys, and
  a whitespace-only key was accepted. The key is now trimmed, and an empty key
  after trimming throws a `DeepLException`.
- Fixed custom `headers` with a different letter case (for example
  `user-agent`) being sent in addition to the default header instead of
  replacing it.
- Fixed malformed API responses raising PHP warnings, a `TypeError` or a raw
  `\Exception` (for example a missing field or an invalid timestamp) in
  `translateText()`, `uploadDocument()`, `getDocumentStatus()`, the glossary,
  multilingual glossary, spoken terms and style rule methods. They now raise
  `InvalidContentException`. `translateText()` with a single text no longer
  returns `null` when the response has no translations.
- Fixed `translateText()` changing the process-wide
  `mb_substitute_character()` setting; the caller's value is now restored.
- Fixed the README entry of the document minification option: it is named
  `enable_document_minification` and supports `docx` as well as `pptx`. The
  README no longer states a 30 MB document size limit.
- Fixed `downloadDocument()` and `downloadTranslationMemoryExport()` returning
  without an error and without writing a file when the directory of the output
  file does not exist. They now throw a `DeepLException` before sending a
  request.
- Fixed `rephraseText()` throwing a `TypeError` when called without a target
  language and without options.
- Fixed `rephraseText()` and `correctText()` accepting malformed responses: a
  response without an `improvements` list returned `null` for a single text,
  fewer improvements than texts returned a short array, and an improvement
  without `text` returned an empty text. These now throw an
  `InvalidContentException`.
- Fixed `ResourceFeature::$needsSourceSupport` and `$needsTargetSupport`
  (from `getLanguageResources()`) being `null` when the API omits the flag;
  they are now `false`, as the API defines.
- Fixed malformed responses of `getSourceLanguages()`, `getTargetLanguages()`,
  `getGlossaryLanguages()`, `getLanguagesForResource()` and
  `getLanguageResources()` raising PHP warnings or a `TypeError` (for example a
  missing `lang`, `name` or `status`, a feature that is not an object, or a
  body that is not a list), or silently treating a missing `usable_as_source`
  or `usable_as_target` as `false`. They now raise `InvalidContentException`.
- Fixed the `LanguageCode::ENGLISH` and `LanguageCode::PORTUGUESE` doc comments,
  which said these codes may only be used as source languages.
- Fixed the glossary ID not being URL-encoded in the request paths of the
  multilingual glossary methods, and the source and target language not being
  URL-encoded in the query of `getMultilingualGlossaryEntries()` and
  `deleteMultilingualGlossaryDictionary()`.
- Fixed `updateMultilingualGlossary()` sending `[]` instead of `{}` as the
  request body when neither a name nor dictionaries are given.
- Fixed `MultilingualGlossaryDictionaryEntries` validating terms after trimming
  them but storing and sending them untrimmed, so a leading or trailing tab or
  newline corrupted the entries sent to the API. Terms are now trimmed, then
  validated, and a source term that is a duplicate after trimming is rejected.
- Fixed `getMultilingualGlossaryEntries()` failing on a dictionary without
  entries, and on returned terms that do not pass the validation applied to
  entries sent to the API. Returned entries are now read as they are.
- Fixed the style rule and custom instruction methods raising a `TypeError`
  when the response body is valid JSON but not an object (for example a string
  or `null`). They now raise `InvalidContentException`.
- Fixed `CustomInstruction::$id` being `null` when a returned custom instruction
  has no `id`, both from the custom instruction methods and in
  `StyleRuleInfo::$customInstructions`. A missing or non-string `id` now raises
  `InvalidContentException`.
- Fixed the translation memory methods raising an undefined-key warning and a
  `TypeError` when a response lacks a required field (`translation_memory_id`,
  `name` or `source_language` of a translation memory; `job_id` of a job,
  import or export; `upload_url` of an import; the IDs, language and text of a
  segment or target segment). They now raise `InvalidContentException`.

## [1.20.0] - 2026-08-11
### Added
- Added support for using multiple glossaries in text and document translation
  via the `TranslateTextOptions::GLOSSARY_IDS` and
  `TranslateDocumentOptions::GLOSSARY_IDS` options (up to 5 glossary IDs).
- Added support for style rules in document translation via the
  `TranslateDocumentOptions::STYLE_ID` option.
- Added support for translation memories in document translation via the
  `TranslateDocumentOptions::TRANSLATION_MEMORY_ID` and
  `TranslateDocumentOptions::TRANSLATION_MEMORY_THRESHOLD` options.
- Added support for the translation memory management endpoints in the
  `DeepLClient` class: `getTranslationMemory()`,
  `listTranslationMemorySegments()`, `deleteTranslationMemory()`,
  `createTranslationMemoryImport()`, `uploadTranslationMemoryFile()`,
  `createTranslationMemoryExport()`, `getTranslationMemoryJob()`,
  `waitUntilTranslationMemoryJobDone()`, `downloadTranslationMemoryExport()`,
  `importTranslationMemoryFromFilepath()` and
  `exportTranslationMemoryToFilepath()`.
  `waitUntilTranslationMemoryJobDone()` polls through the `'awaiting_input'`
  status, which an import job keeps reporting for a while after its file has
  been uploaded, and accepts an optional timeout in seconds, as do
  `importTranslationMemoryFromFilepath()` and
  `exportTranslationMemoryToFilepath()`.
- Added new model classes `TranslationMemorySegment`,
  `TranslationMemoryTargetSegment`, `TranslationMemorySegments`,
  `TranslationMemorySegmentsOptions`, `TranslationMemoryImport`,
  `TranslationMemoryExport`, `TranslationMemoryJob` and
  `TranslationMemoryJobResult`.
- Added the `creationTime` and `updatedTime` properties to
  `TranslationMemoryInfo`.

### Changed
- Requests to the pre-signed storage URLs used for translation memory import and export now
  go through a separate HTTP client that is constructed without the DeepL `Authorization`
  header, instead of stripping it per request. Headers supplied via `TranslatorOptions::HEADERS`
  are no longer sent to those URLs either — only the `User-Agent` and the per-request
  `Content-Type`. Proxy settings are unaffected.

## [1.19.0] - 2026-06-24
### Added
- Added support for the `/v3/languages` and `/v3/languages/resources` endpoints
  in the `DeepLClient` class: `getLanguagesForResource()` and `getLanguageResources()`.
  These return richer per-resource, per-feature language support than the v2
  language endpoint, including newer languages and features.
- Added new model classes `LanguageSupport`, `LanguageResource`, `LanguageFeature`,
  and `ResourceFeature` to represent the v3 language response shape.

### Changed
- Migrated request bodies for `/v2/translate`, `/v2/glossaries`, and
  `/v2/write/rephrase` to `application/json` with correctly-typed fields
  (e.g. `text` is sent as an array, `show_billed_characters` as a boolean,
  tag lists as arrays, `translation_memory_threshold` as an integer).
- Send `type` as a query parameter on `GET /v2/languages` instead of in the
  request body.
- Enabled OpenAPI request and response validation for the mock-server CI jobs.
- As a consequence of the above, values passed via `extraBodyParameters` are sent with
  their JSON type rather than always as strings. The API does not coerce, so a string
  given for a field it types as a boolean, number or array (for example `'0'` for
  `outline_detection`) is rejected; pass the matching type instead.

## [1.18.0] - 2026-04-09
### Added
- Added support for translation memories in text translation via
  `TranslateTextOptions::TRANSLATION_MEMORY_ID` and
  `TranslateTextOptions::TRANSLATION_MEMORY_THRESHOLD`.
- Added `listTranslationMemories()` method to the `DeepLClient` class for
  listing available translation memories.
- Added new model class `TranslationMemoryInfo` to represent translation memory data.

### Fixed
- Set explicit image for `gitlab release` CI job to avoid missing `$HOME` issues.

## [1.17.0] - 2026-03-26
### Added
- Added new language constants from January 2026 API release of 81 new languages.
- Added PHP 8.4 and 8.5 to CI test matrix.
- Added support for style rules CRUD endpoints in the `DeepLClient` class:
  `createStyleRule()`, `getStyleRule()`, `updateStyleRuleName()`,
  `updateStyleRuleConfiguredRules()`, and `deleteStyleRule()`.
- Added support for style rule custom instruction CRUD endpoints in the
  `DeepLClient` class: `createStyleRuleCustomInstruction()`,
  `getStyleRuleCustomInstruction()`, `updateStyleRuleCustomInstruction()`,
  and `deleteStyleRuleCustomInstruction()`.
  Please refer to the README for usage instructions.

### Changed
- Updated formality tests to accept either formal or informal output when using default formality,
  since the default formality is automatic.
- Updated `testModelType()` to just check if the `model_type_used` is populated if the `model_type` is specified in the request
- Updated `testMixedDirectionText()` to add a missing `</p>` tag
- Improved `NotFoundException` error message by removing the misleading "check server_url" suggestion.

### Fixed
- Skip `curl_close` call for PHP 8+.

## [1.16.0] - 2025-12-10
### Added
- Added `tag_handling_version` parameter to `translateText()` specify which version of the tag handling algorithm to use. Options are `v1` and `v2`.

## [1.15.0] - 2025-12-03
### Added
- Added `custom_instructions` parameter to `translateText()` to customize translation
  behavior with up to 10 instructions (max 300 characters each). Only supported for
  target languages: `de`, `en`, `es`, `fr`, `it`, `ja`, `ko`, `zh` and their variants.
  Note: using the `custom_instructions` parameter will use the `quality_optimized`
  model type as the default. Requests combining `custom_instructions` and the
  `latency_optimized` model type will be rejected.

## [1.14.0] - 2025-11-12
### Added
- Added support for the `GET /v3/style_rules` endpoint in the client library, the
  implementation can be found in the `DeepLClient` class. Please refer to the
  README for usage instructions
- Added `style_id` option to `translateText()` which allows text translation with
  style rules.
- Added new model classes: `StyleRuleInfo`, `ConfiguredRules`, and `CustomInstruction`
  to support style rules functionality.

## [1.13.0] - 2025-04-25
### Added
- Added `extraRequestParameters` option to text and document translation methods to pass arbitrary parameters in the request body. This can be used to access beta features or override built-in parameters (such as `target_lang`, `source_lang`, etc.).

## [1.12.0] - 2025-04-25
### Added
- Added support for the /v3 Multilingual Glossary APIs in the client library
  while providing backwards compatability for the previous /v2 Glossary
  endpoints. Please refer to the README or
  [upgrading_to_multilingual_glossaries.md](upgrading_to_multilingual_glossaries.md)
  for usage instructions.

## [1.11.1] - 2025-01-17
### Fixed
- Fixed `DeepLClientOptions` wrongly inheriting from `TranslateTextOptions`,
  when it should be `TranslatorOptions`.
  * Thanks to [VincentLanglet](https://github.com/VincentLanglet) for the
    [report](https://github.com/DeepLcom/deepl-php/issues/59)

## [1.11.0] - 2025-01-16
### Added
- Added support for the Write API in the client library, the implementation
  can be found in the `DeepLClient` class. Please refer to the README for usage
  instructions.

### Changed
- The main functionality of the library is now also exposed via the `DeepLClient`
  class. Please change your code to use this over the `Translator` class whenever
  convenient.

## [1.10.1] - 2024-11-29
### Fixed
- Fixed the `TextResult` constructor to be compatible with pre-1.10 versions, to
  facilitate mocking
  * Thanks to [VincentLanglet](https://github.com/VincentLanglet) for
    [#56](https://github.com/DeepLcom/deepl-php/pull/56)
- Fixed a bug when generating the platform information with `php_uname`.
  * Thanks to [jochensengier](https://github.com/jochensengier) for
    [#57](https://github.com/DeepLcom/deepl-php/pull/57) and
    [that-guy-iain](https://github.com/that-guy-iain) +
    [jimwins](https://github.com/jimwins) for the
    [report](https://github.com/DeepLcom/deepl-php/issues/54).

## [1.10.0] - 2024-11-15
### Added
- Added `MODEL_TYPE` option to `translateText()` to use models with higher
  translation quality (available for some language pairs), or better latency.
  Options are `'quality_optimized'`, `'latency_optimized'`, and  `'prefer_quality_optimized'`
- Added the `$modelTypeUsed` field to `translateText()` response, that
  indicates the translation model used when the `MODEL_TYPE` option is
  specified.

## [1.9.0] - 2024-09-17
### Added
- Added `$billedCharacters` to the translate text response.

## [1.8.0] - 2024-06-24
### Added
- Added document minification as a feature before document translation, to allow
  translation of large `docx` or `pptx` files. For more info check the README.

## [1.7.2] - 2024-04-24
### Fixed
- Added a workaround for rare cases that the DeepL API responds with invalid
  UTF-8 sequences. In these cases the
  [replacement character](https://en.wikipedia.org/wiki/Replacement_character)
  "�" (U+FFFD) will replace invalid sequences.
  * Thanks to [VincentLanglet](https://github.com/VincentLanglet) for
    [#43](https://github.com/DeepLcom/deepl-php/pull/43)

## [1.7.1] - 2024-02-28
### Fixed
- Update `VERSION` values to 1.7.1

## [1.7.0] - 2024-02-27
### Added
- New language available: Arabic (`'ar'`). Add language code constants and tests.
  Arabic is currently supported only for text translation; document translation
  support for Arabic is coming soon.

  Note: older library versions also support the new language, this update only adds new code constants.

### Fixed
- Improve type of `translateText` function in `Translator`
  * Thanks to [VincentLanglet](https://github.com/VincentLanglet) for [#41](https://github.com/DeepLcom/deepl-php/pull/41)

## [1.6.0] - 2023-11-03
### Added
- Add optional `context` parameter for text translation, that specifies
  additional context to influence translations, that is not translated itself.

### Changed
- Added notice in Readme that starting in 2024 the library will drop support for PHP versions that are officially end-of-life.

## [1.5.1] - 2023-09-11
### Fixed
- Add `.gitattributes` file to exclude irrelevant files from package download.
  * Thanks to [VincentLanglet](https://github.com/VincentLanglet) for [#30](https://github.com/DeepLcom/deepl-php/pull/30)
- Internal CI improvements.

## [1.5.0] - 2023-06-26
### Added
- Allow users to supply their own custom HTTP client to the `Translator` object, in order to configure timeouts, security features etc more granularly.
  * Thanks to [VincentLanglet](https://github.com/VincentLanglet) for the good input and work in [#22](https://github.com/DeepLcom/deepl-php/pull/22)
- Add curl version to the platform info in the user-agent header (will not be added if the user opts out).

### Fixed
- Allow users to translate empty strings without throwing an error.
  * Thanks to [VincentLanglet](https://github.com/VincentLanglet) for the work in [#24](https://github.com/DeepLcom/deepl-php/pull/24)
- Catch any exception thrown when computing the user-agent header and continue without failing the request.

## [1.4.0] - 2023-05-24
### Added
- Script to check our source code for license headers and a step for them in the CI.
- Added platform and node version information to the user-agent string that is sent with API calls, along with an opt-out.
- Add method for applications that use this library to identify themselves in API requests they make.

### Fixed
- Fix getUsage request to be a HTTP GET request, not POST.
- Changed document translation to poll the server every 5 seconds. This should greatly reduce observed document translation processing time.

## [1.3.0] - 2023-01-26
### Added
- New languages available: Korean (`'ko'`) and Norwegian (bokmål) (`'nb'`). Add language code constants and tests.

  Note: older library versions also support the new languages, this update only adds new code constants.

## [1.2.1] - 2023-01-25
### Fixed
- Also send options in API requests even if they are default values.

## [1.2.0] - 2022-11-11
### Changed
- Added dependency on `psr/log`. As this package forms a PHP Standard
  Recommendation, we don't consider it to break backward-compatibility.

### Fixed
- Change the type of the `TranslatorOptions::LOGGER` option to
  `Psr\Log\LoggerInterface`, to correctly support PSR-3 loggers.
  * Pull request [#12](https://github.com/DeepLcom/deepl-php/pull/12)
    thanks to [Schleuse](https://github.com/Schleuse).

## [1.1.0] - 2022-09-28
### Added
- Add new formality options: `'prefer_less'` and `'prefer_more'`.

### Changed
- Requests resulting in `503 Service Unavailable` errors are now retried.
  Attempting to download a document before translation is completed will now
  wait and retry (up to 5 times by default), rather than throwing an exception.

## [1.0.0] - 2022-09-08
Stable release.

### Added
- Add glossary management support.
- New language available: Ukrainian (`'uk'`). Add language code constant and tests.

  Note: older library versions also support new languages, this update only adds new code constant.
- Add proxy support.

## [0.4.1] - 2022-08-12
### Changed
- Update contributing guidelines, we can now accept Pull Requests.

### Fixed
- Fix GitLab CI config.
- Fix a typo in the readme.
  * Pull request [#5](https://github.com/DeepLcom/deepl-php/pull/5)
    thanks to [MartkCz](https://github.com/MartkCz).

## [0.4.0] - 2022-05-24
### Added
- Add support for document translation.

## [0.3.0] - 2022-05-18
### Added
- New languages available: Indonesian (`'id'`) and Turkish (`'tr'`). Add language code constants and tests.

  Note: older library versions also support the new languages, this update only adds new code constants.

## [0.2.0] - 2022-05-02
### Changed
- Remove `final` keyword from class declarations to facilitate testing.

## [0.1.1] - 2022-04-28
### Fixed
- Added minimum supported PHP version to composer.json.
- Fix cURL client issue: do not round timeouts to whole seconds.
- Fix cURL client issue: consider empty response a retryable-error.
- Check for and reject invalid `server_url` option.

## [0.1.0] - 2022-04-22
Initial version.

[Unreleased]: https://github.com/DeepL/deepl-php/compare/v1.20.0...HEAD
[1.20.0]: https://github.com/DeepL/deepl-php/compare/v1.19.0...v1.20.0
[1.19.0]: https://github.com/DeepL/deepl-php/compare/v1.18.0...v1.19.0
[1.18.0]: https://github.com/DeepL/deepl-php/compare/v1.17.0...v1.18.0
[1.17.0]: https://github.com/DeepL/deepl-php/compare/v1.16.0...v1.17.0
[1.16.0]: https://github.com/DeepL/deepl-php/compare/v1.15.0...v1.16.0
[1.15.0]: https://github.com/DeepL/deepl-php/compare/v1.14.0...v1.15.0
[1.14.0]: https://github.com/DeepL/deepl-php/compare/v1.13.0...v1.14.0
[1.13.0]: https://github.com/DeepL/deepl-php/compare/v1.12.0...v1.13.0
[1.12.0]: https://github.com/DeepL/deepl-php/compare/v1.11.1...v1.12.0
[1.11.1]: https://github.com/DeepL/deepl-php/compare/v1.11.0...v1.11.1
[1.11.0]: https://github.com/DeepL/deepl-php/compare/v1.10.1...v1.11.0
[1.10.1]: https://github.com/DeepL/deepl-php/compare/v1.10.0...v1.10.1
[1.10.0]: https://github.com/DeepL/deepl-php/compare/v1.9.0...v1.10.0
[1.9.0]: https://github.com/DeepL/deepl-php/compare/v1.8.0...v1.9.0
[1.8.0]: https://github.com/DeepL/deepl-php/compare/v1.7.2...v1.8.0
[1.7.2]: https://github.com/DeepL/deepl-php/compare/v1.7.1...v1.7.2
[1.7.1]: https://github.com/DeepL/deepl-php/compare/v1.7.0...v1.7.1
[1.7.0]: https://github.com/DeepL/deepl-php/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/DeepL/deepl-php/compare/v1.5.1...v1.6.0
[1.5.1]: https://github.com/DeepL/deepl-php/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/DeepL/deepl-php/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/DeepL/deepl-php/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/DeepL/deepl-php/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/DeepL/deepl-php/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/DeepL/deepl-php/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/DeepL/deepl-php/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/DeepL/deepl-php/compare/v0.4.1...v1.0.0
[0.4.1]: https://github.com/DeepL/deepl-php/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/DeepL/deepl-php/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/DeepL/deepl-php/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/DeepL/deepl-php/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/DeepL/deepl-php/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/DeepL/deepl-php/releases/tag/v0.1.0
