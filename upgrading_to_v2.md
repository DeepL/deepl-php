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
- `psr/http-client-implementation` and `psr/http-factory-implementation` are required as `^1.0`
  instead of `*`. Both PSR interfaces only have a 1.x major, so any PSR-18 client and PSR-17
  factory you use with 1.21.0, such as Guzzle 7, still works. No other Composer dependency or
  autoload change.

## Quick checklist

1. Upgrade to PHP 8.0 or later ([Requirements](#requirements)).
2. Replace `Translator` with `DeepLClient`, and `isAuthKeyFreeAccount()` with `isFreeAccountAuthKey()`
   ([section](#translator-is-removed)).
3. Replace `LanguageCode::GERMAN` and the other language constants with strings
   ([section](#language-code-constants-are-removed)).
4. Rename the document step methods, and drop the document minification option and classes
   ([documents](#document-step-methods-are-renamed)).
5. Replace the removed option and resource constants (`STYLE_ID`, `TRANSLATION_MEMORY_ID`,
   `LanguageResource::RESOURCE_*`, `LanguageSupport::INCLUDE_*`, translate constants on
   `RephraseTextOptions`) ([options](#option-constants), [rephrase](#rephrasetext-options)).
6. Replace removed glossary APIs: `getMultilingualGlossaryEntries()`,
   `GlossaryNotFoundException`, and the changed `updateMultilingualGlossary()` and
   `deleteMultilingualGlossaryDictionary()` ([section](#glossaries)).
7. Catch `\InvalidArgumentException` as well as `DeepLException`, and instead of `\TypeError`
   for objects without an ID ([section](#argument-errors-throw-invalidargumentexception)).
8. Remove unknown keys from option arrays and send `formality` and `split_sentences` in lower case
   ([unknown keys](#unknown-option-keys-throw), [values](#formality-and-split_sentences-are-sent-as-given)).
9. Check code that depends on retries of create functions or of proxy errors
   ([create functions](#retries-of-functions-that-create-resources), [proxies](#proxy-failures)).
10. Update uses of `Usage`, `StyleRuleInfo::$configuredRules` and the translation memory helpers
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
  `socks4`, `socks4a`, `socks5` or `socks5h` (or no scheme, which means `http`); an empty string
  throws. Before, an empty string meant no proxy, a non-string value raised a `TypeError`, and
  other such values were passed to cURL so that every request failed with a
  `ConnectionException`. Remove an empty `proxy` option or pass a valid proxy URL.
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
  expired: it requests the status once and throws the timeout `DeepLException`. Before, it threw
  a `DeepLException` (`timeout must be a non-negative number of seconds`) before any request.
- A missing input file throws `\InvalidArgumentException` in `translateDocument()`,
  `translateDocumentUpload()` and `importTranslationMemoryFromFilepath()` (before:
  `DocumentTranslationException`, a transport error, or `DeepLException`).
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

Two further parameters were renamed to `$options`, so PHP 8 named arguments break too:
`importTranslationMemoryFromFilepath($f, displayName: 'TM')` becomes
`importTranslationMemoryFromFilepath($f, options: 'TM')` (or pass the options array), and
`createTranslationMemoryImport($n, $len, contentType: 'text/csv')` becomes
`createTranslationMemoryImport($n, $len, options: 'text/csv')`.

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
try {
    $client->translateText('Hello', null, 'de',
        [TranslateTextOptions::GLOSSARY => 'a', TranslateTextOptions::GLOSSARY_IDS => ['b']]);
} catch (DeepLException $e) {}
// after
try {
    $client->translateText('Hello', null, 'de',
        [TranslateTextOptions::GLOSSARY => 'a', TranslateTextOptions::GLOSSARY_IDS => ['b']]);
} catch (DeepLException | \InvalidArgumentException $e) {}
```

API, I/O and wait failures still throw `DeepLException`. `translateDocument()` still wraps the
argument errors of its upload in `DocumentTranslationException`.

### Objects without an ID throw `InvalidArgumentException`

The style rule functions that take a `StyleRuleInfo`, and the translation memory functions that
take a `TranslationMemoryInfo` or `TranslationMemoryJob`, throw `\InvalidArgumentException` for an
object, or `null`, without an ID, before any request. Before, they threw `\TypeError`. The
`GLOSSARY`, `GLOSSARY_IDS`, `STYLE_RULE`, `STYLE_PROFILE` and `TRANSLATION_MEMORY` options of the
translate and document functions throw it for an empty ID or an object without an ID instead of
sending it; so does a `GLOSSARY_IDS` entry or a `STYLE_RULE`, `STYLE_PROFILE` or
`TRANSLATION_MEMORY` value that is neither an ID nor its object.
`exportTranslationMemoryToFilepath()` wraps it in `TranslationMemoryJobException`.

```php
// before
catch (\TypeError $e) {}
// after
catch (\InvalidArgumentException $e) {}
```

### Empty language codes throw

An empty language code throws `\InvalidArgumentException` before any request instead of being
sent to the API: the languages of `MultilingualGlossaryDictionaryEntries` (when it is created and
when it is sent), of the multilingual glossary CSV functions and of
`getMultilingualGlossaryDictionaryEntries()`; the `lang` of `SpokenTermsEntries`,
`getSpokenTermsEntries()` and `deleteSpokenTermsList()`; the custom instruction source language of
`createStyleRule()`, `createStyleRuleCustomInstruction()` and `updateStyleRuleCustomInstruction()`
(`null` still means not set). A spoken terms response without `lang` throws
`InvalidContentException`.

### Fewer type checks

Some arguments are no longer type-checked by the library; pass the documented types.

- `translateText()`, `rephraseText()` and `correctText()` send a text that is not a string as
  given; only an empty string is rejected.
- `translation_memory_threshold` is only checked to be between 0 and 100, so `'80'` is sent.
- `translateDocumentWaitUntilDone()` and `translateDocument()` no longer reject a `timeout` that
  is not a number before the request; PHP raises its own error when it is used.
- `getLanguagesForResource()` no longer checks that `INCLUDE` is an array.
- The style profile ID lists convert an entry that is not an ID or one of the expected objects to
  a string instead of throwing.
- `getUsageAnalytics()` and `getCustomTagUsageAnalytics()` raise PHP's `TypeError` for a date that
  is neither a `DateTimeInterface` nor a string.

### Unknown option keys throw

An option key that is not a constant of the options class throws `\InvalidArgumentException`;
before, it was ignored. This applies to `translateText()`, `translateDocumentUpload()`,
`translateDocumentWaitUntilDone()`, `rephraseText()`, `correctText()`,
`getLanguagesForResource()`, `listTranslationMemorySegments()` and
`DeepLAdminClient::setDeveloperKeyUsageLimits()`. Send extra request fields with
`EXTRA_BODY_PARAMETERS`.

### Retries of functions that create resources

Functions that create a resource retry only when the request was not processed: HTTP 429, or a
failure while connecting, before any byte of the request was written (a DNS failure of the host or
proxy, a refused connection, a reset by the proxy before it answered, a timeout while connecting,
in the TLS handshake or in the proxy tunnel). A 5xx response, a read timeout or a reset connection
is no longer retried, and `shouldRetry` on the exception is `false` in those cases.
This covers `translateDocumentUpload()`, the `create*` glossary, spoken terms, style rule, style
profile and translation memory functions. `createDeveloperKey()` already retried only unprocessed
requests in 1.21.0; its `shouldRetry` is now `false` after a 5xx, a timeout after the request was
written or a reset connection, and it is now also retried after a timeout in the TLS handshake or
the proxy tunnel and after a proxy that reset or closed the connection before answering. With a
custom HTTP client only HTTP 429 is retried. After such an error, check whether the resource exists
before creating it again.

### Cut-off response bodies are retried

A body cut off mid-read (cURL errors 18 and 92) is retried like a reset connection, except by the
create functions. If every attempt fails, `ConnectionException::$shouldRetry` is `true`.

### Proxy failures

A failure on the way to a proxy is retried like one on a direct connection, and an error answer
from the proxy is not. A proxy whose name cannot be resolved is now retried. A non-2xx answer to
`CONNECT`, 407 included, is no longer retried, and its `ConnectionException` has `shouldRetry`
`false`; fix the proxy setting instead of retrying. A reset or other socket failure during the TLS
handshake is retried like other connection failures; a TLS error (alert, wrong version,
certificate) is still not retried.

## Types and signatures

Unless noted, these changes only matter if you subclass `DeepLClient` and override the methods,
or call them with unusual arguments.

- A trailing `?array $options = null` was added to `createMultilingualGlossary()`,
  `createMultilingualGlossaryFromCsv()`, `replaceMultilingualGlossaryDictionary()`,
  `createStyleRule()`, `updateStyleRuleName()`, `updateStyleRuleConfiguredRules()`,
  `createStyleRuleCustomInstruction()` and `updateStyleRuleCustomInstruction()`. Calls are
  unaffected; overrides must match.
- Every optional `$options` parameter of `DeepLClient` and `DeepLAdminClient`, the constructors'
  included, and the `$contentType` of `uploadTranslationMemoryFile()` accept `null`, meaning not
  given. Overrides must declare `?array $options = null` (`?string $contentType = null`).
- The `$instructionId` parameter of `getStyleRuleCustomInstruction()`,
  `updateStyleRuleCustomInstruction()` and `deleteStyleRuleCustomInstruction()` is untyped, as it
  accepts a `CustomInstruction` too.
- `createTranslationMemoryImport()` takes `$options` as its third parameter: an array
  (`CreateTranslationMemoryImportOptions`) or, as before, the content type string.
- The protected `DeepLClient::checkStatusCode()` no longer has the `$usingGlossary` parameter.
- `DeepLClient::buildRephraseBodyParams()` was public and is now private; any direct call fails.
  Call `rephraseText()` instead.
- `GlossaryUtils` is `@internal`. Use `GlossaryEntries::fromTsv()`, `fromEntries()` and
  `convertToTsv()`.

## Helper classes

- `ClientUtils::checkStatusCode()` no longer has the third parameter `$usingGlossary`.
- `HttpClientWrapper` no longer has a destructor.
- `TranslatorOptions::isValid()` no longer returns `false` or logs a warning for an unknown option
  key, because the constructors throw for it first (through `TranslatorOptions::checkOptionKeys()`).
- `GlossaryUtils` is marked `@internal`; see [Types and signatures](#types-and-signatures).
