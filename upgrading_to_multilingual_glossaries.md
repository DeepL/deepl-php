# Migration Documentation for Newest Glossary Functionality

## 1. Overview of Changes

The newest version of the Glossary APIs is the `/v3` endpoints, which introduce enhanced functionality:

- **Support for Multilingual Glossaries**: The v3 endpoints allow for the creation of glossaries with multiple language
  pairs, enhancing flexibility and usability.
- **Editing Capabilities**: Users can now edit existing glossaries.

To support these new v3 APIs, we have created new methods to interact with these new multilingual glossaries. Users are
encouraged to transition to the new to take full advantage of these new features. The `v2` methods for monolingual 
glossaries (e.g., `createGlossary()`, `getGlossary()`, etc.) remain available but users are encouraged to update to
use the new functions.

## 2. Endpoint Changes

| Monolingual glossary methods   | Multilingual glossary methods                | Changes Summary                                                                                                                                                                      |
|--------------------------------|----------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `createGlossary()`        | `createMultilingualGlossary()`          | Accepts an array of `MultilingualGlossaryDictionaryEntries` for multi-lingual support and now returns a `MultilingualGlossaryInfo` object.                                             |
| `createGlossaryFromCsv()` | `createMultilingualGlossaryFromCsv()`   | Similar functionality, but now returns a `MultilingualGlossaryInfo` object                                                                                                           |
| `getGlossary()`           | `getMultilingualGlossary()`             | Similar functionality, but now returns `MultilingualGlossaryInfo`. Also can accept a `MultilingualGlossaryInfo` object as the glossary parameter instead of a `GlossaryInfo` object. |
| `listGlossaries()`        | `listMultilingualGlossaries()`          | Similar functionality, but now returns an array of `MultilingualGlossaryInfo` objects.                                                                                                 |
| `getGlossaryEntries()`    | `getMultilingualGlossaryDictionaryEntries()` | Requires specifying source and target languages. Returns a `MultilingualGlossaryDictionaryEntries` object.                                              |
| `deleteGlossary()`        | `deleteMultilingualGlossary()`          | Similar functionality, but now can accept a `MultilingualGlossaryInfo` object instead of a `GlossaryInfo` object when specifying the glossary.                                       |

## 3. Model Changes

V2 glossaries are monolingual and the previous glossary objects could only have entries for one language pair (
`SourceLanguageCode` and `TargetLanguageCode`). Now we introduce the concept of "glossary dictionaries", where a
glossary dictionary specifies its own `SourceLang`, `TargetLang`, and has its own entries.

- **Glossary Information**:
  - **v2**: `GlossaryInfo` supports only mono-lingual glossaries, containing fields such as `SourceLang`,
    `TargetLang`, and `EntryCount`.
  - **v3**: `MultilingualGlossaryInfo` supports multi-lingual glossaries and includes an array of
    `MultilingualGlossaryDictionaryInfo`, which provides details about each glossary dictionary, each with its own
    `SourceLang`, `TargetLang`, and `EntryCount`.

- **Glossary Entries**:
  - **v3**: Introduces `MultilingualGlossaryDictionaryEntries`, which encapsulates a glossary dictionary with source and target languages along with its entries.

## 4. Code Examples

### Create a glossary

```php
// monolingual glossary example
$entries = GlossaryEntries::fromEntries(['artist' => 'Maler', 'prize' => 'Gewinn']);
$myGlossary = $deeplClient->createGlossary('My glossary', 'en', 'de', $entries);

// multilingual glossary example
$entries = GlossaryEntries::fromEntries(['artist' => 'Maler', 'prize' => 'Gewinn']);
$myGlossaryDict = new MultilingualGlossaryDictionaryEntries('en', 'de', $entries);
$myGlossary = $deeplClient->createMultilingualGlossary('My glossary', [$myGlossaryDict]);
```

### Get a glossary

```php
// monolingual glossary example
$glossaryId = '559192ed-8e23-...';
$myGlossary = $deeplClient->getGlossary($glossaryId); // GlossaryInfo object

// multilingual glossary example
$glossaryId = '559192ed-8e23-...';
$myGlossary = $deeplClient->getMultilingualGlossary($glossaryId); // MultilingualGlossaryInfo object
```

### Get glossary entries

```php
// monolingual glossary example
$glossaryId = '559192ed-8e23-...';
$entries = $deeplClient->getGlossaryEntries($myGlossary);
print_r($entries->getEntries()); // Array ( [artist] => Maler, [prize] => Gewinn)

// multilingual glossary example
$glossaryId = '559192ed-8e23-...';
$glossaryDict = $deeplClient->getMultilingualGlossaryDictionaryEntries($myGlossary, "en", "de");
print_r($glossaryDict->entries); // Array ( [artist] => Maler, [prize] => Gewinn)
```

### List and delete glossaries

```php
// monolingual glossary example
$glossaries = $deeplClient->listGlossaries();
foreach ($glossaries as $glossary) {
    if ($glossary->name === 'Old glossary') {
        $deeplClient->deleteGlossary($glossary);
    }
}
  
// multilingual glossary example
$glossaries = $deeplClient->listMultilingualGlossaries();
foreach ($glossaries as $glossary) {
    if ($glossary->name === 'Old glossary') {
        $deeplClient->deleteMultilingualGlossary($glossary);
    }
}
```

## 5. New Multilingual Glossary Methods

In addition to introducing multilingual glossaries, we introduce several new methods that enhance the functionality for
managing glossaries. Below are the details for each new method:

### Update Multilingual Glossary Dictionary

- **Method**:
  - `updateMultilingualGlossary($glossary, ?string $name, ?array $dictionaries): MultilingualGlossaryInfo`
- **Description**: Updates a glossary or glossary dictionary with new entries or name.
- **Parameters:**
  - `string|MultilingualGlossaryInfo $glossary`: Glossary ID or `MultilingualGlossaryInfo` of glossary to update.
  - `string|null $name`: Optional, new name for glossary.
  - `array|null $dictionaries`: Optional, array of `MultilingualGlossaryDictionaryEntries` to update or add to glossary.
- **Returns**: `MultilingualGlossaryInfo` containing details about the updated glossary.
- **Example**:

```php
$entries = GlossaryEntries::fromEntries(['artist' => 'Maler', 'hello' => 'guten tag']);
$myGlossaryDict = new MultilingualGlossaryDictionaryEntries('en', 'de', $entries);
$myGlossary = $deeplClient->createMultilingualGlossary('My glossary', [$myGlossaryDict]);

$newEntries = GlossaryEntries::fromEntries(['hello' => 'hallo', 'prize' => 'Gewinn']);
$myUpdatedGlossaryDict = new MultilingualGlossaryDictionaryEntries('en', 'de', $newEntries);
$myUpdatedGlossary = $deeplClient->updateMultilingualGlossary(
    $myGlossary, 'My updated glossary', [$myUpdatedGlossaryDict]);
print_r($myUpdatedGlossary->dictionaries[0]->getEntries()); // Array ( [artist] => Maler, [hello] => hallo, [prize] => Gewinn)
print_r($myUpdatedGlossary->name); // 'My updated glossary'
```

### Replace a Multilingual Glossary Dictionary

- **Method**:
  - `replaceMultilingualGlossaryDictionary($glossary, MultilingualGlossaryDictionaryEntries $dictionaryEntries): MultilingualGlossaryDictionaryInfo`
- **Description**: This method replaces the existing glossary dictionary with a new set of entries.
- **Parameters:**
  - `string|MultilingualGlossaryInfo $glossary`: Glossary ID or `MultilingualGlossaryInfo` of glossary to update.
  - `MultilingualGlossaryDictionaryEntries $dictionaryEntries`: Replacement dictionary with entries.
- **Returns**: `MultilingualGlossaryDictionaryInfo` containing information about the replaced glossary dictionary.
- **Note**: Ensure that the new dictionary entries are complete and valid, as this method will completely overwrite the
  existing entries. It will also create a new glossary dictionary if one did not exist for the given language pair.
- **Example**:
  ```php
  $entries = GlossaryEntries::fromEntries(['artist' => 'Maler', 'hello' => 'guten tag']);
  $myGlossaryDict = new MultilingualGlossaryDictionaryEntries('en', 'de', $entries);
  $myGlossary = $deeplClient->createMultilingualGlossary('My glossary', [$myGlossaryDict]);

  $newEntries = GlossaryEntries::fromEntries(['hello' => 'hallo', 'prize' => 'Gewinn']);
  $myUpdatedGlossaryDict = new MultilingualGlossaryDictionaryEntries('en', 'de', $newEntries);
  $myUpdatedGlossary = $deeplClient->replaceMultilingualGlossaryDictionary(
      $myGlossary, $myUpdatedGlossaryDict);
  print_r($myUpdatedGlossary->getEntries()); // Array ( [hello] => hallo, [prize] => Gewinn)
  ```

### Delete a Multilingual Glossary Dictionary

- **Method**:
  - `deleteMultilingualGlossaryDictionary($glossary, ?MultilingualGlossaryDictionaryInfo $dictionary, ?string $sourceLang = null, ?string $targetLang = null): void`
- **Description**: This method deletes a specified glossary dictionary from a given glossary.
- **Parameters**:
    * `string|MultilingualGlossaryInfo $glossary`: Glossary ID or `MultilingualGlossaryInfo` of glossary to be deleted.
    * `MultilingualGlossaryDictionaryInfo|null $dictionary`: Optional parameter of the dictionary to delete. Either the `MultilingualGlossaryDictionaryInfo` or both the sourceLang and targetLang can be provided to identify the dictionary.
    * `string|null $sourceLang`: Optional parameter representing the source language of the dictionary
    * `string|null $targetLang`: Optional parameter representing the target language of the dictionary.
- **Returns**: Void
- **Migration Note**: Ensure that your application logic correctly identifies the dictionary to delete. If using
  `sourceLang` and `targetLang`, both must be provided to specify the dictionary.

- **Example**:
  ```php
  $entriesEnde = GlossaryEntries::fromEntries(['hello' => 'hallo']);
  $entriesDeen = GlossaryEntries::fromEntries(['hallo' => 'hello']);
  $glossaryDictEnde = new MultilingualGlossaryDictionaryEntries('en', 'de', $entriesEnde);
  $glossaryDictDeen = new MultilingualGlossaryDictionaryEntries('de', 'en', $entriesDeen);
  $glossaryInfo = $deeplClient->createMultilingualGlossary('My glossary', [$entriesEnde, $entriesDeen]);

  // Delete via specifying the glossary dictionary
  $deeplClient->deleteMultilingualGlossaryDictionary($glossaryInfo, $glossaryInfo->dictionaries[0], null, null);

  // Delete via specifying the language pair
  $deeplClient->deleteMultilingualGlossaryDictionary($glossaryInfo, null, "de", "en");
  ```
