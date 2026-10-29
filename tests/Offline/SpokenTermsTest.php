<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLException;
use DeepL\InvalidContentException;
use DeepL\SpokenTermsEntries;

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
                $client->updateSpokenTerms('sid', null, [$entries]);
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
