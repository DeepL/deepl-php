<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLException;
use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;

class MultilingualGlossariesTest extends OfflineTestCase
{
    private const DICTIONARY = ['source_lang' => 'en', 'target_lang' => 'de', 'entry_count' => 1];
    private const GLOSSARY = [
        'glossary_id' => 'gid',
        'name' => 'My glossary',
        'creation_time' => '2021-08-03T14:16:18.329Z',
        'dictionaries' => [self::DICTIONARY, ['source_lang' => 'en', 'target_lang' => 'fr', 'entry_count' => 2]],
    ];

    private function dictionary(string $target = 'de'): MultilingualGlossaryDictionaryEntries
    {
        return new MultilingualGlossaryDictionaryEntries('en', $target, ['Hello' => 'Hallo']);
    }

    private function dictionaryBody(string $target = 'de'): array
    {
        return [
            'source_lang' => 'en',
            'target_lang' => $target,
            'entries' => "Hello\tHallo",
            'entries_format' => 'tsv',
        ];
    }

    public function testSendsDictionariesAsJson(): void
    {
        $this->reply(self::GLOSSARY, 201);

        $this->makeDeepLClient()
            ->createMultilingualGlossary('My glossary', [$this->dictionary(), $this->dictionary('fr')]);

        $this->assertRequest('POST', '/v3/glossaries');
        $this->assertSame(
            ['name' => 'My glossary', 'dictionaries' => [$this->dictionaryBody(), $this->dictionaryBody('fr')]],
            $this->jsonBody()
        );
    }

    public function testSendsCsvDictionaryAsJson(): void
    {
        $this->reply(self::GLOSSARY, 201);

        $this->makeDeepLClient()->createMultilingualGlossaryFromCsv('My glossary', 'en', 'de', "Hello,Hallo\n");

        $this->assertRequest('POST', '/v3/glossaries');
        $this->assertSame([
            'name' => 'My glossary',
            'dictionaries' => [[
                'source_lang' => 'en',
                'target_lang' => 'de',
                'entries_format' => 'csv',
                'entries' => "Hello,Hallo\n",
            ]],
        ], $this->jsonBody());
    }

    public function testThrowsWhenTheNameIsEmpty(): void
    {
        $client = $this->makeDeepLClient();

        foreach ([
            function () use ($client) {
                $client->createMultilingualGlossary('', [$this->dictionary()]);
            },
            function () use ($client) {
                $client->createMultilingualGlossaryFromCsv('', 'en', 'de', 'Hello,Hallo');
            },
        ] as $call) {
            try {
                $call();
                $this->fail('expected a DeepLException');
            } catch (DeepLException $exception) {
                $this->assertStringContainsString('glossary name must be a non-empty string', $exception->getMessage());
            }
        }
        $this->assertRequestCount(0);
    }

    public function testSendsOnlyTheNameWhenRenaming(): void
    {
        $this->reply(self::GLOSSARY);

        $this->makeDeepLClient()->updateMultilingualGlossary('gid', 'New name', null);

        $this->assertRequest('PATCH', '/v3/glossaries/gid');
        $this->assertSame(['name' => 'New name'], $this->jsonBody());
    }

    public function testSendsOnlyTheDictionariesWhenUpdatingThem(): void
    {
        $this->reply(self::GLOSSARY);
        $info = new MultilingualGlossaryInfo('gid', 'n', new \DateTime(), []);

        $this->makeDeepLClient()->updateMultilingualGlossary($info, null, [$this->dictionary()]);

        $this->assertRequest('PATCH', '/v3/glossaries/gid');
        $this->assertSame(['dictionaries' => [$this->dictionaryBody()]], $this->jsonBody());
    }

    public function testSendsNameAndDictionariesTogetherAndIgnoresEmptyDictionaries(): void
    {
        $this->reply(self::GLOSSARY);
        $this->reply(self::GLOSSARY);
        $client = $this->makeDeepLClient();

        $client->updateMultilingualGlossary('gid', 'New name', [$this->dictionary()]);
        $client->updateMultilingualGlossary('gid', 'New name', []);

        $this->assertSame(['name' => 'New name', 'dictionaries' => [$this->dictionaryBody()]], $this->jsonBody(0));
        $this->assertSame(['name' => 'New name'], $this->jsonBody(1));
    }

    public function testSendsTheDictionaryWhenReplacingIt(): void
    {
        $this->reply(self::DICTIONARY + ['entries' => '']);

        $result = $this->makeDeepLClient()->replaceMultilingualGlossaryDictionary('gid', $this->dictionary());

        $this->assertRequest('PUT', '/v3/glossaries/gid/dictionaries');
        $this->assertSame($this->dictionaryBody(), $this->jsonBody());
        $this->assertInstanceOf(MultilingualGlossaryDictionaryInfo::class, $result);
        $this->assertSame(1, $result->entryCount);
    }

    public function testParsesGlossaryWithDictionaries(): void
    {
        $this->reply(self::GLOSSARY);

        $info = $this->makeDeepLClient()->getMultilingualGlossary('gid');

        $this->assertRequest('GET', '/v3/glossaries/gid');
        $this->assertSame('2021-08-03 14:16:18', $info->creationTime->format('Y-m-d H:i:s'));
        $this->assertCount(2, $info->dictionaries);
        $this->assertSame(['en', 'fr', 2], [
            $info->dictionaries[1]->sourceLang,
            $info->dictionaries[1]->targetLang,
            $info->dictionaries[1]->entryCount,
        ]);
    }

    public function testParsesGlossaryList(): void
    {
        $this->reply(['glossaries' => [self::GLOSSARY, ['glossary_id' => 'other'] + self::GLOSSARY]]);

        $glossaries = $this->makeDeepLClient()->listMultilingualGlossaries();

        $this->assertRequest('GET', '/v3/glossaries');
        $this->assertSame(['gid', 'other'], [$glossaries[0]->glossaryId, $glossaries[1]->glossaryId]);
    }

    public function testSendsLanguagePairInTheEntriesQueryAndParsesTsv(): void
    {
        $this->reply(['dictionaries' => [
            array_replace($this->dictionaryBody(), ['entries' => "Hello\tHallo\nBye\tTsch\u{FC}ss"]),
        ]]);

        $dictionaries = $this->makeDeepLClient()->getMultilingualGlossaryEntries('gid', 'en', 'de');

        $this->assertRequest('GET', '/v3/glossaries/gid/entries', 0, 'source_lang=en&target_lang=de');
        $this->assertSame(['Hello' => 'Hallo', 'Bye' => "Tsch\u{FC}ss"], $dictionaries[0]->entries);
    }

    public function testThrowsWhenTheEntriesFormatIsNotTsv(): void
    {
        $this->reply(['dictionaries' => [array_replace($this->dictionaryBody(), ['entries_format' => 'csv'])]]);

        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('Unsupported entries_format: csv');

        $this->makeDeepLClient()->getMultilingualGlossaryEntries('gid', 'en', 'de');
    }

    public function testSendsDeleteForTheGlossaryAndForADictionaryByObjectOrLanguages(): void
    {
        $this->reply('', 204);
        $this->reply('', 204);
        $this->reply('', 204);
        $client = $this->makeDeepLClient();

        $client->deleteMultilingualGlossary('gid');
        $client->deleteMultilingualGlossaryDictionary('gid', new MultilingualGlossaryDictionaryInfo('en', 'de', 1));
        $client->deleteMultilingualGlossaryDictionary('gid', null, 'en', 'fr');

        $this->assertRequest('DELETE', '/v3/glossaries/gid', 0);
        $this->assertRequest('DELETE', '/v3/glossaries/gid/dictionaries', 1, 'source_lang=en&target_lang=de');
        $this->assertRequest('DELETE', '/v3/glossaries/gid/dictionaries', 2, 'source_lang=en&target_lang=fr');
    }

    public function testThrowsWhenNoDictionaryOrLanguagesAreGivenForDeletion(): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('must provide dictionary or both source_lang and target_lang');

        $this->makeDeepLClient()->deleteMultilingualGlossaryDictionary('gid', null, 'en');
    }

    public function testThrowsGlossaryNotFoundWhenTheGlossaryDoesNotExist(): void
    {
        $this->reply(['message' => 'Glossary not found'], 404);

        $this->expectException(GlossaryNotFoundException::class);

        $this->makeDeepLClient()->getMultilingualGlossary('gone');
    }

    /**
     * @dataProvider provideInvalidEntries
     */
    public function testThrowsWhenDictionaryEntriesAreInvalid(array $entries, string $message): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage($message);

        new MultilingualGlossaryDictionaryEntries('de', 'en', $entries);
    }

    public static function provideInvalidEntries(): array
    {
        return [
            'no entries' => [[], 'no entries'],
            'tab in a term' => [['A' => "B\tC"], 'invalid characters'],
            'unicode line separator' => [['A' => "B\u{2028}C"], 'invalid characters'],
        ];
    }
}
