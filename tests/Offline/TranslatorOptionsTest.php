<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\AppInfo;
use DeepL\DeepLAdminClient;
use DeepL\DeepLException;
use DeepL\DeepLClient;
use DeepL\DocumentHandle;
use DeepL\Fixtures;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\SpokenTermsEntries;
use DeepL\TranslatorOptions;

class TranslatorOptionsTest extends OfflineTestCase
{
    private const USAGE = ['character_count' => 1, 'character_limit' => 2];

    /**
     * @dataProvider provideUserAgents
     */
    public function testSendsUserAgent(array $options, string $pattern): void
    {
        $this->reply(self::USAGE);

        $this->makeDeepLClient($options)->getUsage();

        $this->assertMatchesRegularExpression($pattern, $this->request()->getHeaderLine('User-Agent'));
    }

    public static function provideUserAgents(): array
    {
        $version = '\d+\.\d+\.\d+';
        $app = new AppInfo('my-app', '1.2.3');
        return [
            'default with platform' => [
                [],
                "#^deepl-php/$version \\(.+ \\S+\\) php/\\S+$#",
            ],
            'explicit opt in' => [
                [TranslatorOptions::SEND_PLATFORM_INFO => true],
                "#^deepl-php/$version \\(.+ \\S+\\) php/\\S+$#",
            ],
            'opt out' => [
                [TranslatorOptions::SEND_PLATFORM_INFO => false],
                "#^deepl-php/$version$#",
            ],
            'app info with platform' => [
                [TranslatorOptions::APP_INFO => $app],
                "#^deepl-php/$version \\(.+\\) php/\\S+ my-app/1\\.2\\.3$#",
            ],
            'app info without platform' => [
                [TranslatorOptions::SEND_PLATFORM_INFO => false, TranslatorOptions::APP_INFO => $app],
                "#^deepl-php/$version my-app/1\\.2\\.3$#",
            ],
            'custom header wins' => [
                [TranslatorOptions::HEADERS => ['User-Agent' => 'my-client'], TranslatorOptions::APP_INFO => $app],
                '#^my-client$#',
            ],
        ];
    }

    public function testSendsThePlatformAsOsVersionAndMachine(): void
    {
        $this->reply(self::USAGE);

        $this->makeDeepLClient()->getUsage();

        $platform = php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('v') . ' ' . php_uname('m');
        $this->assertStringEndsWith(
            " ($platform) php/" . phpversion(),
            $this->request()->getHeaderLine('User-Agent')
        );
    }

    public function testSendsAuthorizationAndCustomHeaders(): void
    {
        $this->reply(self::USAGE);

        $this->makeDeepLClient([TranslatorOptions::HEADERS => ['X-Custom' => 'value']], 'my-key')->getUsage();

        $this->assertSame('DeepL-Auth-Key my-key', $this->request()->getHeaderLine('Authorization'));
        $this->assertSame('value', $this->request()->getHeaderLine('X-Custom'));
    }

    /**
     * @dataProvider provideAuthorizationHeaderNames
     */
    public function testLetsACustomAuthorizationHeaderReplaceTheAuthKey(string $name): void
    {
        $this->reply(self::USAGE);

        $this->makeDeepLClient([TranslatorOptions::HEADERS => [$name => 'Bearer other']], 'my-key')->getUsage();

        $this->assertSame(['Bearer other'], $this->request()->getHeader('Authorization'));
    }

    public static function provideAuthorizationHeaderNames(): array
    {
        return [['Authorization'], ['authorization'], ['AUTHORIZATION']];
    }

    /**
     * @dataProvider provideEmptyAuthKeys
     */
    public function testThrowsWhenTheAuthKeyIsEmpty(string $authKey): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('authKey must be a non-empty string');

        $this->makeDeepLClient([], $authKey);
    }

    public static function provideEmptyAuthKeys(): array
    {
        return ['empty' => [''], 'whitespace only' => [" \t\n"]];
    }

    public function testTrimsTheAuthKey(): void
    {
        $this->reply(self::USAGE);

        $this->makeDeepLClient([], "  my-key \n")->getUsage();

        $this->assertSame('DeepL-Auth-Key my-key', $this->request()->getHeaderLine('Authorization'));
    }

    public function testSelectsTheFreeServerForAFreeKeyWithSurroundingWhitespace(): void
    {
        $this->reply(self::USAGE);

        $deeplClient = new DeepLClient(" abc:fx\n", [
            TranslatorOptions::HTTP_CLIENT => $this->clientOptions([])[TranslatorOptions::HTTP_CLIENT],
        ]);
        $deeplClient->getUsage();

        $this->assertSame('api-free.deepl.com', $this->request()->getUri()->getHost());
        $this->assertSame('DeepL-Auth-Key abc:fx', $this->request()->getHeaderLine('Authorization'));
    }

    /**
     * @dataProvider provideServerUrls
     */
    public function testSendsRequestsToTheServerUrl(?string $serverUrl, string $authKey, string $expectedUrl): void
    {
        $this->reply(self::USAGE);

        $this->makeDeepLClient([TranslatorOptions::SERVER_URL => $serverUrl], $authKey)->getUsage();

        $this->assertSame($expectedUrl, (string)$this->request()->getUri());
    }

    public static function provideServerUrls(): array
    {
        return [
            'paid key default' => [null, 'key', 'https://api.deepl.com/v2/usage'],
            'free key default' => [null, 'key:fx', 'https://api-free.deepl.com/v2/usage'],
            'custom url' => ['http://localhost:3000', 'key', 'http://localhost:3000/v2/usage'],
            'trailing slash removed' => ['https://example.com/base/', 'key', 'https://example.com/base/v2/usage'],
        ];
    }

    /**
     * @dataProvider provideInvalidServerUrls
     */
    public function testThrowsWhenTheServerUrlIsInvalid($serverUrl): void
    {
        foreach ([
            function () use ($serverUrl) {
                new DeepLClient('k', [TranslatorOptions::SERVER_URL => $serverUrl]);
            },
            function () use ($serverUrl) {
                new DeepLAdminClient('k', [TranslatorOptions::SERVER_URL => $serverUrl]);
            },
        ] as $construct) {
            try {
                $construct();
                $this->fail('expected an InvalidArgumentException');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame(
                    'If specified, server_url option must be an absolute http or https URL with a host.',
                    $exception->getMessage()
                );
            }
        }
    }

    public static function provideInvalidServerUrls(): array
    {
        return [
            'empty' => [''],
            'false' => [false],
            'not a string' => [42],
            'no scheme' => ['api.deepl.com'],
            'other scheme' => ['ftp://api.deepl.com'],
            'no host' => ['https://'],
        ];
    }

    /**
     * @dataProvider provideInvalidProxies
     */
    public function testThrowsWhenTheProxyIsInvalid($proxy): void
    {
        foreach ([
            function () use ($proxy) {
                new DeepLClient('k', [TranslatorOptions::PROXY => $proxy]);
            },
            function () use ($proxy) {
                new DeepLAdminClient('k', [TranslatorOptions::PROXY => $proxy]);
            },
        ] as $construct) {
            try {
                $construct();
                $this->fail('expected an InvalidArgumentException');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('proxy option must be a proxy URL', $exception->getMessage());
            }
        }
    }

    public static function provideInvalidProxies(): array
    {
        return [
            'empty' => [''],
            'unsupported scheme' => ['ftp://x'],
            'no host' => ['http://'],
            'whitespace' => ['not a url'],
            'not a string' => [42],
        ];
    }

    /**
     * @dataProvider provideUsableProxies
     */
    public function testAcceptsAProxyCurlCanUse(string $proxy): void
    {
        $options = [TranslatorOptions::PROXY => $proxy];
        $this->assertInstanceOf(DeepLClient::class, new DeepLClient('k', $options));
        $this->assertInstanceOf(DeepLAdminClient::class, new DeepLAdminClient('k', $options));
    }

    public static function provideUsableProxies(): array
    {
        return [
            'host and port without a scheme, used as http' => ['proxy.local:3128'],
            'http URL with credentials' => ['http://user:pass@10.10.1.10:3128'],
            'socks5h URL' => ['socks5h://proxy.local:1080'],
        ];
    }

    public function testLogsRequestAndResponse(): void
    {
        $this->reply(self::USAGE);
        $logger = new TestLogger();

        $this->makeDeepLClient([TranslatorOptions::LOGGER => $logger])->getUsage();

        $this->assertStringContainsString('Request to DeepL API GET https://api.deepl.com/v2/usage', $logger->content);
        $this->assertStringContainsString(
            'DeepL API response GET https://api.deepl.com/v2/usage 200',
            $logger->content
        );
    }

    public function testWarnsAboutOptionsIgnoredWithACustomHttpClient(): void
    {
        $this->reply(self::USAGE);
        $logger = new TestLogger();

        $this->makeDeepLClient([
            TranslatorOptions::LOGGER => $logger,
            TranslatorOptions::TIMEOUT => 3.0,
            TranslatorOptions::PROXY => 'http://proxy.local',
        ])->getUsage();

        $this->assertStringContainsString(
            'Option timeout is ignored as a custom HTTP client is used.',
            $logger->content
        );
        $this->assertStringContainsString('Option proxy is ignored as a custom HTTP client is used.', $logger->content);
    }

    public function testDoesNotWarnAboutANullTimeoutOrProxyWithACustomHttpClient(): void
    {
        $this->reply(self::USAGE);
        $logger = new TestLogger();

        $this->makeDeepLClient([
            TranslatorOptions::LOGGER => $logger,
            TranslatorOptions::TIMEOUT => null,
            TranslatorOptions::PROXY => null,
        ])->getUsage();

        $this->assertStringNotContainsString('is ignored', $logger->content);
    }

    public function testThrowsForAnUnknownOptionKey(): void
    {
        foreach ([
            function () {
                $this->makeDeepLClient(['no_such_option' => 1]);
            },
            function () {
                $this->makeAdminClient(['no_such_option' => 1]);
            },
        ] as $construct) {
            try {
                $construct();
                $this->fail('expected an InvalidArgumentException');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('Unknown client option: no_such_option', $exception->getMessage());
            }
        }
    }

    public function testConstructorsTreatNullOptionsAsNoOptions(): void
    {
        $this->assertInstanceOf(DeepLClient::class, new DeepLClient('k', null));
        $this->assertInstanceOf(DeepLAdminClient::class, new DeepLAdminClient('k', null));
    }

    /**
     * @dataProvider provideCallsWithNullOptions
     */
    public function testTreatsNullOptionsAsNoOptions(callable $call): void
    {
        $this->reply(['message' => 'denied'], 403);

        try {
            $call($this->makeDeepLClient());
            $this->fail('expected a DeepLException');
        } catch (DeepLException $exception) {
            $this->assertRequestCount(1);
        }
    }

    public static function provideCallsWithNullOptions(): array
    {
        $dictionary = function (): MultilingualGlossaryDictionaryEntries {
            return new MultilingualGlossaryDictionaryEntries('en', 'de', ['Hello' => 'Hallo']);
        };
        return [
            'translateText' => [function (DeepLClient $c) {
                $c->translateText('Hello', null, 'de', null);
            }],
            'translateDocument' => [function (DeepLClient $c) {
                $input = Fixtures::tempFile('in.txt', 'Hello');
                $c->translateDocument($input, Fixtures::tempDir() . '/o', null, 'de', null);
            }],
            'translateDocumentUpload' => [function (DeepLClient $c) {
                $c->translateDocumentUpload(Fixtures::tempFile('in.txt', 'Hello'), null, 'de', null);
            }],
            'translateDocumentWaitUntilDone' => [function (DeepLClient $c) {
                $c->translateDocumentWaitUntilDone(new DocumentHandle('doc1', 'k'), null);
            }],
            'rephraseText' => [function (DeepLClient $c) {
                $c->rephraseText('Hello', 'en-GB', null);
            }],
            'correctText' => [function (DeepLClient $c) {
                $c->correctText('Hello', null, null);
            }],
            'createMultilingualGlossary' => [function (DeepLClient $c) use ($dictionary) {
                $c->createMultilingualGlossary('n', [$dictionary()], null);
            }],
            'createMultilingualGlossaryFromCsv' => [function (DeepLClient $c) {
                $c->createMultilingualGlossaryFromCsv('n', 'en', 'de', "Hello,Hallo\n", null);
            }],
            'updateMultilingualGlossary' => [function (DeepLClient $c) {
                $c->updateMultilingualGlossary('gid', null);
            }],
            'updateMultilingualGlossaryName' => [function (DeepLClient $c) {
                $c->updateMultilingualGlossaryName('gid', 'n', null);
            }],
            'updateMultilingualGlossaryDictionary' => [function (DeepLClient $c) use ($dictionary) {
                $c->updateMultilingualGlossaryDictionary('gid', $dictionary(), null);
            }],
            'updateMultilingualGlossaryDictionaryFromCsv' => [function (DeepLClient $c) {
                $c->updateMultilingualGlossaryDictionaryFromCsv('gid', 'en', 'de', "Hello,Hallo\n", null);
            }],
            'replaceMultilingualGlossaryDictionary' => [function (DeepLClient $c) use ($dictionary) {
                $c->replaceMultilingualGlossaryDictionary('gid', $dictionary(), null);
            }],
            'replaceMultilingualGlossaryDictionaryFromCsv' => [function (DeepLClient $c) {
                $c->replaceMultilingualGlossaryDictionaryFromCsv('gid', 'en', 'de', "Hello,Hallo\n", null);
            }],
            'createSpokenTerms' => [function (DeepLClient $c) {
                $c->createSpokenTerms('n', [new SpokenTermsEntries('en', ['a'])], null);
            }],
            'updateSpokenTerms' => [function (DeepLClient $c) {
                $c->updateSpokenTerms('sid', null);
            }],
            'replaceSpokenTermsList' => [function (DeepLClient $c) {
                $c->replaceSpokenTermsList('sid', new SpokenTermsEntries('en', ['a']), null);
            }],
            'createStyleRule' => [function (DeepLClient $c) {
                $c->createStyleRule('n', 'de', null, null, null);
            }],
            'updateStyleRuleName' => [function (DeepLClient $c) {
                $c->updateStyleRuleName('sid', 'n', null);
            }],
            'updateStyleRuleConfiguredRules' => [function (DeepLClient $c) {
                $c->updateStyleRuleConfiguredRules('sid', [], null);
            }],
            'createStyleRuleCustomInstruction' => [function (DeepLClient $c) {
                $c->createStyleRuleCustomInstruction('sid', 'l', 'p', null, null);
            }],
            'updateStyleRuleCustomInstruction' => [function (DeepLClient $c) {
                $c->updateStyleRuleCustomInstruction('sid', 'iid', 'l', 'p', null, null);
            }],
            'createStyleProfile' => [function (DeepLClient $c) {
                $c->createStyleProfile('n', null);
            }],
            'updateStyleProfile' => [function (DeepLClient $c) {
                $c->updateStyleProfile('pid', null);
            }],
            'listTranslationMemorySegments' => [function (DeepLClient $c) {
                $c->listTranslationMemorySegments('tmid', null);
            }],
        ];
    }

    public function testIsFreeAccountAuthKey(): void
    {
        $this->assertTrue(DeepLClient::isFreeAccountAuthKey('abc:fx'));
        $this->assertTrue(DeepLClient::isFreeAccountAuthKey("abc:fx\n"));
        $this->assertFalse(DeepLClient::isFreeAccountAuthKey('abc'));
    }
}
