<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\DeepLException;
use DeepL\NotFoundException;
use DeepL\StyleProfile;
use DeepL\StyleProfileOptions;

class StyleProfilesTest extends OfflineTestCase
{
    private const PROFILE = [
        'style_profile_id' => 'f8e3a1b2-7c4d-4e5f-9a6b-1c2d3e4f5a6b',
        'name' => 'Legal, German',
        'description' => 'Contract wording for the German market.',
        'industry' => 'Legal',
        'glossary_ids' => ['4d0a2e1c-6b93-4f7a-8d15-2c6e9a3b7f81'],
        'style_rule_ids' => ['bd0a38f3-1831-440b-a8dd-2c702e2325ab'],
        'translation_memory_ids' => ['a74d88fb-ed2a-4943-a664-a4512398b994'],
        'glossary_priority_order_enabled' => true,
        'creation_time' => '2021-08-03T14:16:18.329Z',
        'updated_time' => '2021-08-04T09:30:00.000Z',
    ];

    public function testListSendsPagingOnlyWhenSet(): void
    {
        $this->reply(['style_profiles' => [self::PROFILE], 'total_count' => 1]);
        $this->reply(['style_profiles' => [], 'total_count' => 1]);
        $this->reply(['total_count' => 0]);
        $client = $this->makeDeepLClient();

        $profiles = $client->listStyleProfiles();
        $this->assertSame([], $client->listStyleProfiles(2, 100));
        $this->assertSame([], $client->listStyleProfiles(null, 5));

        $this->assertRequest('GET', '/v3/style_profiles');
        $this->assertRequest('GET', '/v3/style_profiles', 1, 'page=2&page_size=100');
        $this->assertRequest('GET', '/v3/style_profiles', 2, 'page_size=5');
        $this->assertCount(1, $profiles);
        $this->assertSame(self::PROFILE['style_profile_id'], $profiles[0]->styleProfileId);
    }

    public function testCreateWithOnlyANameSendsOnlyTheName(): void
    {
        $this->reply(self::PROFILE, 201);
        $client = $this->makeDeepLClient();

        $client->createStyleProfile('Legal, German');

        $this->assertRequest('POST', '/v3/style_profiles');
        $this->assertSame('{"name":"Legal, German"}', (string)$this->request()->getBody());
    }

    public function testCreateWithEveryOptionSendsAllFields(): void
    {
        $this->reply(self::PROFILE, 201);
        $client = $this->makeDeepLClient();

        $client->createStyleProfile('Legal, German', [
            StyleProfileOptions::DESCRIPTION => 'Contract wording',
            StyleProfileOptions::INDUSTRY => 'Legal',
            StyleProfileOptions::GLOSSARY_IDS => [3 => 'g2', 1 => 'g1'],
            StyleProfileOptions::STYLE_RULE_IDS => ['s1'],
            StyleProfileOptions::TRANSLATION_MEMORY_IDS => ['t1', 't2'],
            StyleProfileOptions::GLOSSARY_PRIORITY_ORDER_ENABLED => true,
        ]);

        $this->assertSame([
            'name' => 'Legal, German',
            'description' => 'Contract wording',
            'industry' => 'Legal',
            'glossary_ids' => ['g2', 'g1'],
            'style_rule_ids' => ['s1'],
            'translation_memory_ids' => ['t1', 't2'],
            'glossary_priority_order_enabled' => true,
        ], $this->jsonBody());
    }

    public function testUpdateSendsOnlyTheSetFieldsIncludingEmptyValues(): void
    {
        $this->reply(self::PROFILE);
        $this->reply(self::PROFILE);
        $client = $this->makeDeepLClient();

        $client->updateStyleProfile('pid', [
            StyleProfileOptions::NAME => 'New name',
            StyleProfileOptions::DESCRIPTION => '',
            StyleProfileOptions::INDUSTRY => null,
            StyleProfileOptions::STYLE_RULE_IDS => [],
            StyleProfileOptions::GLOSSARY_PRIORITY_ORDER_ENABLED => false,
        ]);
        $client->updateStyleProfile('pid', []);

        $this->assertRequest('PATCH', '/v3/style_profiles/pid');
        $this->assertSame(
            '{"name":"New name","description":"","style_rule_ids":[],"glossary_priority_order_enabled":false}',
            (string)$this->request()->getBody()
        );
        $this->assertRequest('PATCH', '/v3/style_profiles/pid', 1);
        $this->assertSame('{}', (string)$this->request(1)->getBody());
    }

    public function testGetUpdateDeleteAcceptAnIdOrAStyleProfileAndEscapeTheId(): void
    {
        $this->reply(self::PROFILE);
        $this->reply(self::PROFILE);
        $this->reply(self::PROFILE);
        $this->mock->append(new \GuzzleHttp\Psr7\Response(204));
        $this->mock->append(new \GuzzleHttp\Psr7\Response(204));
        $client = $this->makeDeepLClient();
        $id = self::PROFILE['style_profile_id'];

        $profile = $client->getStyleProfile($id);
        $client->getStyleProfile('a/b c');
        $client->updateStyleProfile($profile, [StyleProfileOptions::INDUSTRY => 'Legal']);
        $client->deleteStyleProfile($profile);
        $client->deleteStyleProfile('a/b c');

        $this->assertRequest('GET', "/v3/style_profiles/$id");
        $this->assertRequest('GET', '/v3/style_profiles/a%2Fb%20c', 1);
        $this->assertRequest('PATCH', "/v3/style_profiles/$id", 2);
        $this->assertRequest('DELETE', "/v3/style_profiles/$id", 3);
        $this->assertRequest('DELETE', '/v3/style_profiles/a%2Fb%20c', 4);
    }

    public function testRejectsAnEmptyIdAndUnknownOptionsBeforeSending(): void
    {
        $client = $this->makeDeepLClient();
        $empty = new StyleProfile('', 'n', null, null, [], [], [], false, null, null);

        foreach ([
            function () use ($client) {
                $client->getStyleProfile('');
            },
            function () use ($client, $empty) {
                $client->getStyleProfile($empty);
            },
            function () use ($client) {
                $client->updateStyleProfile('', []);
            },
            function () use ($client) {
                $client->deleteStyleProfile('');
            },
            function () use ($client) {
                $client->createStyleProfile('n', ['glossary' => ['g1']]);
            },
            function () use ($client) {
                $client->updateStyleProfile('pid', ['style_rules' => []]);
            },
        ] as $call) {
            try {
                $call();
                $this->fail('expected a DeepLException');
            } catch (DeepLException $exception) {
                $this->assertNotInstanceOf(NotFoundException::class, $exception);
            }
        }
        $this->assertRequestCount(0);
    }

    public function testParsesEveryFieldOfTheSpecExample(): void
    {
        $this->reply(self::PROFILE);

        $profile = $this->makeDeepLClient()->getStyleProfile('pid');

        $this->assertSame(self::PROFILE['style_profile_id'], $profile->styleProfileId);
        $this->assertSame('Legal, German', $profile->name);
        $this->assertSame('Contract wording for the German market.', $profile->description);
        $this->assertSame('Legal', $profile->industry);
        $this->assertSame(self::PROFILE['glossary_ids'], $profile->glossaryIds);
        $this->assertSame(self::PROFILE['style_rule_ids'], $profile->styleRuleIds);
        $this->assertSame(self::PROFILE['translation_memory_ids'], $profile->translationMemoryIds);
        $this->assertTrue($profile->glossaryPriorityOrderEnabled);
        $this->assertSame('2021-08-03T14:16:18.329+00:00', $profile->creationTime->format('Y-m-d\TH:i:s.vP'));
        $this->assertSame('2021-08-04T09:30:00.000+00:00', $profile->updatedTime->format('Y-m-d\TH:i:s.vP'));
    }

    public function testParsesTheTestApiShapeTolerantly(): void
    {
        $this->reply([
            'style_profile_id' => 'pid',
            'name' => 'Minimal',
            'glossary_ids' => [],
            'style_rule_ids' => ['s1', 7, null, 's2'],
            'example_document_ids' => ['d1'],
            'spoken_terms_ids' => ['x1'],
            'creation_time' => '2026-10-26T16:27:10.2982869Z',
            'updated_time' => 'not a timestamp',
        ]);

        $profile = $this->makeDeepLClient()->getStyleProfile('pid');

        $this->assertSame(['pid', 'Minimal'], [$profile->styleProfileId, $profile->name]);
        $this->assertNull($profile->description);
        $this->assertNull($profile->industry);
        $this->assertSame([], $profile->glossaryIds);
        $this->assertSame(['s1', 's2'], $profile->styleRuleIds);
        $this->assertSame([], $profile->translationMemoryIds);
        $this->assertFalse($profile->glossaryPriorityOrderEnabled);
        $this->assertSame('2026-10-26T16:27:10.298286', $profile->creationTime->format('Y-m-d\TH:i:s.u'));
        $this->assertNull($profile->updatedTime);
        $this->assertFalse(property_exists($profile, 'exampleDocumentIds'));
    }

    public function testMalformedCreationTimeIsNull(): void
    {
        $this->reply(array_merge(self::PROFILE, ['creation_time' => 'not a timestamp']));

        $profile = $this->makeDeepLClient()->getStyleProfile('pid');

        $this->assertNull($profile->creationTime);
        $this->assertSame('Legal, German', $profile->name);
        $this->assertInstanceOf(\DateTime::class, $profile->updatedTime);
    }

    public function testNotFoundOnGetThrowsNotFoundException(): void
    {
        $this->reply(['message' => 'Style profile not found'], 404);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Style profile not found');
        $this->makeDeepLClient()->getStyleProfile('pid');
    }
}
