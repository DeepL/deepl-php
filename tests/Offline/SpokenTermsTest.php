<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\SpokenTermsEntries;

class SpokenTermsTest extends OfflineTestCase
{
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
}
