# Upgrading to 2.0.0

Version 2.0.0 of deepl-php removes everything that was deprecated in 1.x, validates arguments
and option keys strictly, and changes the retry behaviour of functions that create resources.
Everyone upgrading from 1.21.0 must read the checklist below; code that still uses `Translator`,
the language code constants, the old document step methods or PHP 7 must change. The library now
requires PHP 8.0 or later. Upgrade to 1.21.0 first if you are on an older 1.x release, and fix
its deprecation notices; most changes below are the removals of those deprecated names.

For the full list of changes, see the [CHANGELOG](CHANGELOG.md).

## Requirements

- PHP 8.0 or later. PHP 7.3 and 7.4 are no longer supported; stay on the 1.x releases if you
  cannot upgrade PHP. `composer.json` now requires `"php": ">=8.0"`.
- No other Composer dependency or autoload change.

## Quick checklist

1. Upgrade to PHP 8.0 or later ([Requirements](#requirements)).
2. Replace `Translator` with `DeepLClient` ([section](#translator-is-removed)).
3. Replace `LanguageCode::GERMAN` and the other language constants with strings
   ([section](#language-code-constants-are-removed)).
4. Rename the document step methods ([section](#document-step-methods-are-renamed)).
5. Replace `STYLE_ID` and `TRANSLATION_MEMORY_ID` with `STYLE_RULE` and `TRANSLATION_MEMORY`
   ([section](#option-constants)).
6. Catch `\InvalidArgumentException` as well as `DeepLException`
   ([section](#argument-errors-throw-invalidargumentexception)).
7. Remove unknown keys from option arrays; they now throw
   ([section](#unknown-option-keys-throw)).
8. Check code that depends on retries of create functions
   ([section](#retries-of-functions-that-create-resources)).
9. Update calls to `updateMultilingualGlossary()` and
   `deleteMultilingualGlossaryDictionary()` ([section](#glossaries)).
10. Update uses of `Usage`, `StyleRuleInfo::$configuredRules` and translation memory helpers
    ([usage](#usage), [style rules](#style-rules), [translation memories](#translation-memories)).

## Client and options

### `Translator` is removed

`DeepLClient` no longer extends `Translator`; its methods, constructor and `VERSION` constant are
in `DeepLClient` itself. `TranslatorOptions` remains.

```php
// before
$translator = new \DeepL\Translator($authKey, [TranslatorOptions::MAX_RETRIES => 3]);
function translate(\DeepL\Translator $t) {}
echo \DeepL\Translator::VERSION;

// after
$client = new \DeepL\DeepLClient($authKey, [TranslatorOptions::MAX_RETRIES => 3]);
function translate(\DeepL\DeepLClient $t) {}
echo \DeepL\DeepLClient::VERSION;
```

Update `instanceof` checks and subclasses the same way: `class MyClient extends DeepLClient`.

### `isAuthKeyFreeAccount()` is removed

```php
// before
$free = \DeepL\Translator::isAuthKeyFreeAccount($authKey);
// after
$free = \DeepL\DeepLClient::isFreeAccountAuthKey($authKey);
```

### Unknown client options throw

The `DeepLClient` and `DeepLAdminClient` constructors throw `\InvalidArgumentException`
(`Unknown client option: <key>`) for a key that is not a `TranslatorOptions` constant. Before, a
warning was logged and the key ignored.

They also validate values before any request:

- `server_url` must be an absolute `http` or `https` URL with a host (`api.deepl.com` without a
  scheme throws).
- `proxy` must be a string without whitespace, with a host, and with a scheme of `http`, `https`,
  `socks4`, `socks4a`, `socks5` or `socks5h` (or no scheme, which means `http`).
- An empty key or a negative `max_retries` throws.

## Text translation

### Language code constants are removed

`LanguageCode` keeps only `standardizeLanguageCode()` and `removeRegionalVariant()`. Pass the
language code string. To list the supported languages, call `getSourceLanguages()`,
`getTargetLanguages()` or `getLanguagesForResource()`.

```php
// before
$client->translateText('Hello', LanguageCode::ENGLISH, LanguageCode::GERMAN);
// after
$client->translateText('Hello', 'en', 'de');
```

### Option constants

```php
// before
[TranslateTextOptions::STYLE_ID => $id, TranslateTextOptions::TRANSLATION_MEMORY_ID => $tm]
[TranslateDocumentOptions::STYLE_ID => $id, TranslateDocumentOptions::TRANSLATION_MEMORY_ID => $tm]
LanguageResource::RESOURCE_TRANSLATE_TEXT
LanguageSupport::INCLUDE_BETA

// after
[TranslateTextOptions::STYLE_RULE => $id, TranslateTextOptions::TRANSLATION_MEMORY => $tm]
[TranslateDocumentOptions::STYLE_RULE => $id, TranslateDocumentOptions::TRANSLATION_MEMORY => $tm]
LanguageResourceName::TRANSLATE_TEXT
LanguageInclude::BETA
```

`LanguageInclude::EXTERNAL` replaces `LanguageSupport::INCLUDE_EXTERNAL`; the other
`LanguageResource::RESOURCE_*` constants map to the same names without the prefix.

### `formality` and `split_sentences` are sent as given

Both are no longer lower-cased, and the `split_sentences` values `'on'`, `'default'` and `'off'`
are no longer mapped to `'1'` and `'0'`. The API rejects upper-case values and `'on'`/`'default'`.

```php
// before
[TranslateTextOptions::FORMALITY => 'More', TranslateTextOptions::SPLIT_SENTENCES => 'on']
// after
[TranslateTextOptions::FORMALITY => Formality::MORE, TranslateTextOptions::SPLIT_SENTENCES => SentenceSplittingMode::ALL]
```

Use `SentenceSplittingMode::OFF` for `'off'`.

### Empty text is rejected

`translateText()`, `rephraseText()` and `correctText()` throw `\InvalidArgumentException` for an
empty text, alone or inside the array, before any request.

### `rephraseText()` options

`RephraseTextOptions` no longer extends `TranslateTextOptions`. It defines only `WRITING_STYLE`,
`TONE` and `EXTRA_BODY_PARAMETERS`. `rephraseText()` throws for any other key.

```php
// before
[RephraseTextOptions::FORMALITY => 'more']
// after: not an option of rephraseText(); use WRITING_STYLE or TONE
[RephraseTextOptions::TONE => WritingTone::FRIENDLY]
```

Use `TranslateTextOptions::*` for translate option constants.

### Incomplete improvements

`rephraseText()` and `correctText()` throw `InvalidContentException` (a `DeepLException`) if an
improvement in the response lacks `detected_source_language` or `target_language`.

### Request body fixes

- Array values of `translateText()`, `rephraseText()` and `correctText()` with keys other than
  `0..n-1` (for example the result of `array_filter()`) are sent as JSON lists. The same applies to
  the tag list and `custom_instructions` options and the custom instructions of
  `createStyleRule()`.
- A value that cannot be JSON-encoded (invalid UTF-8, INF, NAN) throws
  `\InvalidArgumentException` instead of sending an empty body.

## Documents

### Document step methods are renamed

```php
// before
$handle = $client->uploadDocument($in, 'en', 'de');
$status = $client->getDocumentStatus($handle);
$client->waitUntilDocumentTranslationComplete($handle);
$client->downloadDocument($handle, $out);

// after
$handle = $client->translateDocumentUpload($in, 'en', 'de');
$status = $client->translateDocumentGetStatus($handle);
$client->translateDocumentWaitUntilDone($handle);
$client->translateDocumentDownload($handle, $out);
```

`translateDocument()` no longer calls subclass overrides of the old names; override the new ones.

### Document minification is removed

`TranslateDocumentOptions::ENABLE_DOCUMENT_MINIFICATION` and the classes `DocumentMinifier`,
`DocumentMinificationException` and `DocumentDeminificationException` are removed. Drop the option
and upload the document unchanged.

### Stricter checks

- `translateDocumentWaitUntilDone()` accepts only `TranslateDocumentOptions::TIMEOUT` and throws
  `\InvalidArgumentException` for other keys, such as `FORMALITY`. A negative timeout counts as
  expired: it requests the status once and throws the timeout `DeepLException` instead of
  `\InvalidArgumentException`.
- A missing input file throws `\InvalidArgumentException` in `translateDocument()` and
  `translateDocumentUpload()` (before: `DocumentTranslationException` or a transport error).
- `translateDocumentUpload()` throws `DeepLException` if the path is a directory or unreadable.
- `translateDocument()` throws `DocumentTranslationException` with a `null` handle before the
  upload if the output directory does not exist or is not writable.
- A `null` in `EXTRA_BODY_PARAMETERS` removes the field, including one the library sets.

## Glossaries

### `getMultilingualGlossaryEntries()` is removed

```php
// before
$entries = $client->getMultilingualGlossaryEntries($glossary, 'en', 'de')[0];
// after
$entries = $client->getMultilingualGlossaryDictionaryEntries($glossary, 'en', 'de');
```

### `updateMultilingualGlossary()` takes options

```php
// before
$client->updateMultilingualGlossary($glossary, 'New name', $dictionaries);
// after
$client->updateMultilingualGlossary($glossary, [
    MultilingualGlossaryOptions::NAME => 'New name',
    MultilingualGlossaryOptions::DICTIONARIES => $dictionaries,
]);
```

### `deleteMultilingualGlossaryDictionary()` signature

```php
// before
$client->deleteMultilingualGlossaryDictionary($glossary, null, 'en', 'de');
// after
$client->deleteMultilingualGlossaryDictionary($glossary, 'en', 'de');
$client->deleteMultilingualGlossaryDictionary($glossary, $dictionaryInfo);
```

### Dictionary entries are a `GlossaryEntries`

`MultilingualGlossaryDictionaryEntries::$entries` is a `GlossaryEntries`, not an array. The
constructor accepts either.

```php
// before
$pairs = $dictionary->entries;
// after
$pairs = $dictionary->entries->getEntries();
```

### Glossary not found

`GlossaryNotFoundException` is removed. A 404 on the glossary endpoints throws `NotFoundException`
(message `Not found, ...`).

```php
// before
catch (GlossaryNotFoundException $e) {}
// after
catch (NotFoundException $e) {}
```

### Empty IDs and names

Glossary and spoken terms functions throw `\InvalidArgumentException` for an empty ID (or an
object with one), and `updateMultilingualGlossaryName()`, `createMultilingualGlossary()` and
`deleteMultilingualGlossaryDictionary()` do so for an empty name, dictionary list or language.
Empty CSV content in the dictionary update and replace functions throws as well.

## Style rules

`StyleRuleInfo::$configuredRules` is the plain array the API returns (category => rule => value,
snake_case keys) instead of a `ConfiguredRules` object, which is removed. The constructor takes
this array, and categories the SDK did not know are no longer dropped.

```php
// before
$info->configuredRules->datesAndTimes
// after
$info->configuredRules['dates_and_times']
```

The custom instruction methods accept a `CustomInstruction` as well as its ID.

## Translation memories

### `importTranslationMemoryFromFilepath()` has no `$timeout`

```php
// before
$client->importTranslationMemoryFromFilepath($file, 'Legal TM', 300);
// after
$client->importTranslationMemoryFromFilepath($file, [
    ImportTranslationMemoryOptions::DISPLAY_NAME => 'Legal TM',
    ImportTranslationMemoryOptions::TIMEOUT => 300,
]);
```

A string as the second argument is still the display name.

### `$timeout` parameters are named `$options`

The parameters of `waitUntilTranslationMemoryJobDone()` and `exportTranslationMemoryToFilepath()`
are named `$options` and take seconds or `[WaitOptions::TIMEOUT => seconds]`. Named arguments need
the new name: `timeout: 300` becomes `options: 300`.

### Job status

- `TranslationMemoryJob::done()` and `TranslationMemoryJobResult::done()` are true only for
  `completed`; a `failed` or `expired` job returns false. To detect any finished job, use
  `$job->done() || !$job->ok()`.
- `TranslationMemoryJobResult::STATUS_DOWNLOADED` is removed (the API never reports it). Use
  `TranslationMemoryJobStatus::COMPLETED`.

### `TranslationMemoryJobException`

`importTranslationMemoryFromFilepath()` and `exportTranslationMemoryToFilepath()` wrap every
failure except a missing input file in `TranslationMemoryJobException` (a `DeepLException` with
`jobId` and `job`). Code that catches a subclass such as `NotFoundException` around them must catch
the new exception and inspect `getPrevious()`.

```php
try {
    $client->exportTranslationMemoryToFilepath($tm, $out);
} catch (TranslationMemoryJobException $e) {
    $cause = $e->getPrevious(); // for example a NotFoundException
}
```

## Usage

`Usage::$document` and `Usage::$teamDocument` are removed, as is their line in the string form.
`Usage::anyLimitReached()` is replaced by `anyCharacterLimitReached()`, which checks only the
`character` and `apiKeyCharacter` limits.

```php
// before
if ($usage->anyLimitReached()) {}
// after
if ($usage->anyCharacterLimitReached()) {}
```

## Errors, validation and retries

### Argument errors throw `InvalidArgumentException`

Argument checks throw PHP's `\InvalidArgumentException` instead of `DeepLException`, before any
request is sent. This covers empty keys, text, names and IDs, invalid client options, glossary
terms, `translation_memory_threshold`, admin dates and similar. `InvalidArgumentException` is not
a `DeepLException`.

```php
// before
try { $client->translateText('', null, 'de'); } catch (DeepLException $e) {}
// after
try { $client->translateText('', null, 'de'); } catch (DeepLException | \InvalidArgumentException $e) {}
```

API, I/O and wait failures still throw `DeepLException`. `translateDocument()` still wraps the
argument errors of its upload in `DocumentTranslationException`.

### Unknown option keys throw

An option key that is not a constant of the options class throws `\InvalidArgumentException`;
before, it was ignored. This applies to `translateText()`, `translateDocumentUpload()`,
`translateDocumentWaitUntilDone()`, `rephraseText()`, `correctText()`,
`getLanguagesForResource()`, `listTranslationMemorySegments()` and
`DeepLAdminClient::setDeveloperKeyUsageLimits()`. Send extra request fields with
`EXTRA_BODY_PARAMETERS`.

### Retries of functions that create resources

Functions that create a resource retry only when the request was not processed: HTTP 429, a DNS
failure, a refused connection or a connect timeout. A 5xx response, a read timeout or a reset
connection is no longer retried, and `shouldRetry` on the exception is `false` in those cases.
This covers `translateDocumentUpload()`, the `create*` glossary, spoken terms, style rule, style
profile and translation memory functions, and `createDeveloperKey()`. With a custom HTTP client
only HTTP 429 is retried. After such an error, check whether the resource exists before creating
it again.

### Cut-off response bodies are retried

A body cut off mid-read (cURL errors 18 and 92) is retried like a reset connection, except by the
create functions. If every attempt fails, `ConnectionException::$shouldRetry` is `true`.

## Types and signatures

These changes only matter if you subclass `DeepLClient` and override the methods, or call them
with unusual arguments.

- A trailing `array $options = []` was added to `createMultilingualGlossary()`,
  `createMultilingualGlossaryFromCsv()`, `replaceMultilingualGlossaryDictionary()`,
  `createStyleRule()`, `updateStyleRuleName()`, `updateStyleRuleConfiguredRules()`,
  `createStyleRuleCustomInstruction()` and `updateStyleRuleCustomInstruction()`. Calls are
  unaffected; overrides must match.
- The `$instructionId` parameter of `getStyleRuleCustomInstruction()`,
  `updateStyleRuleCustomInstruction()` and `deleteStyleRuleCustomInstruction()` is untyped, as it
  accepts a `CustomInstruction` too.
- `createTranslationMemoryImport()` takes `$options` as its third parameter: an array
  (`CreateTranslationMemoryImportOptions`) or, as before, the content type string.
- The protected `DeepLClient::checkStatusCode()` no longer has the `$usingGlossary` parameter.
- `DeepLClient::buildRephraseBodyParams()` is private.
- `GlossaryUtils` is `@internal`. Use `GlossaryEntries::fromTsv()`, `fromEntries()` and
  `convertToTsv()`.

## Internal classes

These were public by accident and are not part of the API. They changed or were removed without
notice in this release:

- `ClientUtils::checkStatusCode()` lost its `$usingGlossary` parameter.
- `HttpClientWrapper`: the destructor was removed and the constant `OPTION_RETRY_ONLY_UNPROCESSED`
  was added.
- `TranslatorOptions::isValid()` no longer returns false for unknown keys; the constructor throws
  earlier through `TranslatorOptions::checkOptionKeys()`.
- `GlossaryUtils` is marked `@internal`; see [Types and signatures](#types-and-signatures).
