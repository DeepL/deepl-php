<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLException;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\GlossaryNotFoundException;

class GlossariesTest extends OfflineTestCase
{
    private const GLOSSARY = [
        'glossary_id' => 'gid',
        'name' => 'My glossary',
        'ready' => true,
        'source_lang' => 'en',
        'target_lang' => 'de',
        'creation_time' => '2021-08-03T14:16:18.329Z',
        'entry_count' => 2,
    ];

    public function testSendsTsvEntriesAsJson(): void
    {
        $this->reply(self::GLOSSARY, 201);

        $this->makeTranslator()->createGlossary(
            'My glossary',
            'EN-us',
            'de-DE',
            GlossaryEntries::fromEntries(['Hello' => 'Hallo', 'Bye' => "Tsch\u{FC}ss"])
        );

        $this->assertRequest('POST', '/v2/glossaries');
        $this->assertSame([
            'name' => 'My glossary',
            'source_lang' => 'en',
            'target_lang' => 'de',
            'entries_format' => 'tsv',
            'entries' => "Hello\tHallo\nBye\tTsch\u{FC}ss",
        ], $this->jsonBody());
    }

    public function testSendsCsvEntriesAsJson(): void
    {
        $this->reply(self::GLOSSARY, 201);

        $this->makeTranslator()->createGlossaryFromCsv('My glossary', 'en-GB', 'de', "Hello,Hallo\n");

        $this->assertRequest('POST', '/v2/glossaries');
        $this->assertSame([
            'name' => 'My glossary',
            'source_lang' => 'en',
            'target_lang' => 'de',
            'entries_format' => 'csv',
            'entries' => "Hello,Hallo\n",
        ], $this->jsonBody());
    }

    public function testThrowsWhenTheNameIsEmpty(): void
    {
        $translator = $this->makeTranslator();
        $entries = GlossaryEntries::fromEntries(['Hello' => 'Hallo']);

        foreach ([
            function () use ($translator, $entries) {
                $translator->createGlossary('', 'en', 'de', $entries);
            },
            function () use ($translator) {
                $translator->createGlossaryFromCsv('', 'en', 'de', 'Hello,Hallo');
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

    public function testParsesGlossaryInfo(): void
    {
        $this->reply(self::GLOSSARY);

        $info = $this->makeTranslator()->getGlossary('gid');

        $this->assertRequest('GET', '/v2/glossaries/gid');
        $this->assertSame(2, $info->entryCount);
        $this->assertSame('2021-08-03 14:16:18', $info->creationTime->format('Y-m-d H:i:s'));
    }

    public function testParsesGlossaryList(): void
    {
        $this->reply(['glossaries' => [self::GLOSSARY, ['glossary_id' => 'other'] + self::GLOSSARY]]);

        $glossaries = $this->makeTranslator()->listGlossaries();

        $this->assertRequest('GET', '/v2/glossaries');
        $this->assertContainsOnlyInstancesOf(GlossaryInfo::class, $glossaries);
        $this->assertSame(['gid', 'other'], [$glossaries[0]->glossaryId, $glossaries[1]->glossaryId]);
    }

    public function testParsesEntriesFromTsv(): void
    {
        $this->reply("Hello\tHallo\r\nBye\tTsch\u{FC}ss\n\n");
        $info = $this->makeTranslator();

        $entries = $info->getGlossaryEntries('gid');

        $this->assertRequest('GET', '/v2/glossaries/gid/entries');
        $this->assertSame(['Hello' => 'Hallo', 'Bye' => "Tsch\u{FC}ss"], $entries->getEntries());
    }

    public function testSendsGlossaryIdOfGlossaryInfoObjects(): void
    {
        $this->reply("Hello\tHallo");
        $this->reply('', 204);
        $translator = $this->makeTranslator();
        $info = new GlossaryInfo('gid', 'n', true, 'en', 'de', new \DateTime(), 1);

        $translator->getGlossaryEntries($info);
        $translator->deleteGlossary($info);

        $this->assertRequest('GET', '/v2/glossaries/gid/entries', 0);
        $this->assertRequest('DELETE', '/v2/glossaries/gid', 1);
    }

    public function testThrowsGlossaryNotFoundWhenTheGlossaryDoesNotExist(): void
    {
        $this->reply(['message' => 'Glossary not found'], 404);

        $this->expectException(GlossaryNotFoundException::class);
        $this->expectExceptionMessage('Glossary not found, message: Glossary not found');

        $this->makeTranslator()->getGlossary('gone');
    }

    /**
     * @dataProvider provideInvalidTsv
     */
    public function testThrowsWhenTsvEntriesAreInvalid(string $tsv, string $message): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage($message);

        GlossaryEntries::fromTsv($tsv);
    }

    public static function provideInvalidTsv(): array
    {
        return [
            'no entries' => ['', 'no entries'],
            'duplicate source term' => ["K\u{FC}che\tKitchen\nK\u{FC}che\tCuisine", 'source term'],
            'more than one separator' => ["A\tB\tC", 'term separator'],
            'no separator' => ['A', 'does not contain separator'],
        ];
    }

    /**
     * @dataProvider provideInvalidEntries
     */
    public function testThrowsWhenEntriesAreInvalid(array $entries, string $message): void
    {
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage($message);

        GlossaryEntries::fromEntries($entries);
    }

    public static function provideInvalidEntries(): array
    {
        return [
            'no entries' => [[], 'no entries'],
            'tab in a term' => [['A' => "B\tC"], 'invalid characters'],
            'unicode line separator' => [['A' => "B\u{2028}C"], 'invalid characters'],
            'blank term' => [['A' => '  '], 'no non-whitespace characters'],
        ];
    }
}
