<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

class UsageTest extends OfflineTestCase
{
    public function testParsesCharacterAndDocumentLimits(): void
    {
        $this->reply([
            'character_count' => 20,
            'character_limit' => 20,
            'document_count' => 0,
            'document_limit' => 1,
        ]);

        $usage = $this->makeTranslator()->getUsage();

        $this->assertRequest('GET', '/v2/usage');
        $this->assertTrue($usage->character->limitReached());
        $this->assertFalse($usage->document->limitReached());
        $this->assertNull($usage->teamDocument);
        $this->assertTrue($usage->anyLimitReached());
        $this->assertSame("Usage this billing period:\nCharacters: 20 of 20\nDocuments: 0 of 1", (string)$usage);
    }

    public function testParsesTeamDocumentLimitWithoutOtherLimits(): void
    {
        $this->reply(['team_document_count' => 1, 'team_document_limit' => 1]);

        $usage = $this->makeTranslator()->getUsage();

        $this->assertNull($usage->character);
        $this->assertNull($usage->document);
        $this->assertTrue($usage->teamDocument->limitReached());
        $this->assertTrue($usage->anyLimitReached());
        $this->assertSame("Usage this billing period:\nTeam documents: 1 of 1", (string)$usage);
    }

    public function testReportsNoLimitReachedBelowTheLimits(): void
    {
        $this->reply(['character_count' => 0, 'character_limit' => 20]);

        $this->assertFalse($this->makeTranslator()->getUsage()->anyLimitReached());
    }
}
