<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLException;
use DeepL\InvalidContentException;
use DeepL\SpokenTerms;
use DeepL\SpokenTermsEntries;
use DeepL\SpokenTermsOptions;

class SpokenTermsTest extends OfflineTestCase
{
    private const INFO = [
        'spoken_terms_id' => 'a/b c',
        'name' => 'My terms',
        'creation_time' => '2026-10-23T17:15:53.434769Z',
        'term_lists' => [['lang' => 'en', 'entry_count' => 1]],
    ];

    public function testJoinsEntriesWithNewlinesAndSplitsThemOnRead(): void
    {
        $this->reply([
            'spoken_terms_id' => 'sid',
            'name' => 'My terms',
            'creation_time' => '2026-10-23T17:15:53.434769Z',
            'term_lists' => [['lang' => 'en', 'entry_count' => 2]],
        ], 201);
        $this->reply(['lang' => 'en', 'entries' => "a\nb\n"]);
        $client = $this->makeDeepLClient();

        $spokenTerms = $client->createSpokenTerms('My terms', [new SpokenTermsEntries('en', ['a', 'b'])]);
        $entries = $client->getSpokenTermsEntries($spokenTerms, 'en');

        $this->assertRequest('POST', '/v3/spoken-terms');
        $this->assertSame(
            ['name' => 'My terms', 'term_lists' => [['lang' => 'en', 'entries' => "a\nb"]]],
            $this->jsonBody()
        );
        $this->assertRequest('GET', '/v3/spoken-terms/sid/entries', 1, 'lang=en');
        $this->assertSame('en', $entries->lang);
        $this->assertSame(['a', 'b'], $entries->entries);
    }

    public function testRejectsATermContainingANewlineBeforeSending(): void
    {
        $client = $this->makeDeepLClient();
        $entries = new SpokenTermsEntries('en', ['DeepL', "web\nhook"]);

        foreach ([
            function () use ($client, $entries) {
                $client->createSpokenTerms('My terms', [$entries]);
            },
            function () use ($client, $entries) {
                $client->updateSpokenTerms('sid', [SpokenTermsOptions::TERM_LISTS => [$entries]]);
            },
            function () use ($client, $entries) {
                $client->replaceSpokenTermsList('sid', $entries);
            },
        ] as $call) {
            try {
                $call();
                $this->fail('expected a DeepLException');
            } catch (DeepLException $exception) {
                $this->assertStringContainsString('contains invalid characters', $exception->getMessage());
            }
        }
        $this->assertRequestCount(0);
    }

    public function testUrlEncodesTheSpokenTermsIdInEveryPath(): void
    {
        $this->reply(self::INFO);
        $this->reply(self::INFO);
        $this->reply('', 204);
        $this->reply(['lang' => 'en', 'entries' => 'a']);
        $this->reply(['lang' => 'en', 'entry_count' => 1]);
        $this->reply('', 204);
        $client = $this->makeDeepLClient();
        $spokenTerms = new SpokenTerms('a/b c', 'My terms', new \DateTime(), []);

        $client->getSpokenTerms('a/b c');
        $client->updateSpokenTerms($spokenTerms);
        $client->deleteSpokenTerms('a/b c');
        $client->getSpokenTermsEntries($spokenTerms, 'en');
        $client->replaceSpokenTermsList('a/b c', new SpokenTermsEntries('en', ['a']));
        $client->deleteSpokenTermsList($spokenTerms, 'en');

        $this->assertRequest('GET', '/v3/spoken-terms/a%2Fb%20c', 0);
        $this->assertRequest('PATCH', '/v3/spoken-terms/a%2Fb%20c', 1);
        $this->assertRequest('DELETE', '/v3/spoken-terms/a%2Fb%20c', 2);
        $this->assertRequest('GET', '/v3/spoken-terms/a%2Fb%20c/entries', 3, 'lang=en');
        $this->assertRequest('PUT', '/v3/spoken-terms/a%2Fb%20c/term-lists', 4);
        $this->assertRequest('DELETE', '/v3/spoken-terms/a%2Fb%20c/term-lists', 5, 'lang=en');
    }

    public function testUpdateSendsOnlyTheOptionsThatAreSet(): void
    {
        $this->reply(self::INFO);
        $this->reply(self::INFO);
        $this->reply(self::INFO);
        $client = $this->makeDeepLClient();

        $client->updateSpokenTerms('sid');
        $client->updateSpokenTerms('sid', [SpokenTermsOptions::NAME => 'New name']);
        $client->updateSpokenTerms('sid', [
            SpokenTermsOptions::NAME => null,
            SpokenTermsOptions::TERM_LISTS => [new SpokenTermsEntries('en', ['a', 'b'])],
        ]);

        $this->assertSame('{}', (string)$this->request(0)->getBody());
        $this->assertSame(['name' => 'New name'], $this->jsonBody(1));
        $this->assertSame(['term_lists' => [['lang' => 'en', 'entries' => "a\nb"]]], $this->jsonBody(2));
    }

    public function testRejectsAnUnknownOptionBeforeSending(): void
    {
        $client = $this->makeDeepLClient();

        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('Unknown spoken terms option: name');
        try {
            $client->createSpokenTerms('My terms', [], [SpokenTermsOptions::NAME => 'x']);
        } finally {
            $this->assertRequestCount(0);
        }
    }

    public function testAddsExtraBodyParametersLast(): void
    {
        $this->reply(self::INFO, 201);
        $this->reply(self::INFO);
        $this->reply(['lang' => 'en', 'entry_count' => 1]);
        $client = $this->makeDeepLClient();
        $extra = [SpokenTermsOptions::EXTRA_BODY_PARAMETERS => ['name' => 'Override', 'flag' => true]];
        $entries = new SpokenTermsEntries('en', ['a']);

        $client->createSpokenTerms('My terms', [$entries], $extra);
        $client->updateSpokenTerms('sid', [SpokenTermsOptions::NAME => 'New name'] + $extra);
        $client->replaceSpokenTermsList('sid', $entries, $extra);

        $termLists = [['lang' => 'en', 'entries' => 'a']];
        $this->assertSame(['name' => 'Override', 'term_lists' => $termLists, 'flag' => true], $this->jsonBody(0));
        $this->assertSame(['name' => 'Override', 'flag' => true], $this->jsonBody(1));
        $this->assertSame(['lang' => 'en', 'entries' => 'a', 'name' => 'Override', 'flag' => true], $this->jsonBody(2));
    }

    public function testSendsTrimmedTerms(): void
    {
        $this->reply(self::INFO, 201);
        $this->reply(self::INFO);
        $this->reply(['lang' => 'en', 'entry_count' => 2]);
        $client = $this->makeDeepLClient();
        $entries = new SpokenTermsEntries('en', [' DeepL ', "webhook\t"]);

        $client->createSpokenTerms('My terms', [$entries]);
        $client->updateSpokenTerms('sid', [SpokenTermsOptions::TERM_LISTS => [$entries]]);
        $client->replaceSpokenTermsList('sid', $entries);

        $expected = ['lang' => 'en', 'entries' => "DeepL\nwebhook"];
        $this->assertSame([$expected], $this->jsonBody(0)['term_lists']);
        $this->assertSame([$expected], $this->jsonBody(1)['term_lists']);
        $this->assertSame($expected, $this->jsonBody(2));
    }

    /**
     * @dataProvider malformedResponses
     */
    public function testThrowsInvalidContentExceptionOnAMalformedResponse(string $method, $body): void
    {
        $this->reply($body);
        $client = $this->makeDeepLClient();

        $this->expectException(InvalidContentException::class);
        switch ($method) {
            case 'get':
                $client->getSpokenTerms('sid');
                break;
            case 'entries':
                $client->getSpokenTermsEntries('sid', 'en');
                break;
            case 'replace':
                $client->replaceSpokenTermsList('sid', new SpokenTermsEntries('en', ['a']));
                break;
        }
    }

    public static function malformedResponses(): array
    {
        $info = self::INFO;
        return [
            'id is not a string' => ['get', ['spoken_terms_id' => 5] + $info],
            'term list is not an object' => ['get', ['term_lists' => ['en']] + $info],
            'entries is not a string' => ['entries', ['lang' => 'en', 'entries' => ['a']]],
            'entries body is not an object' => ['entries', '"a"'],
            'term list body is not an object' => ['replace', '"a"'],
        ];
    }
}
