<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

use DeepL\AppInfo;
use DeepL\DeepLException;
use DeepL\Translator;
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

        $this->makeTranslator($options)->getUsage();

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

        $this->makeTranslator()->getUsage();

        $platform = php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('v') . ' ' . php_uname('m');
        $this->assertStringEndsWith(
            " ($platform) php/" . phpversion(),
            $this->request()->getHeaderLine('User-Agent')
        );
    }

    public function testSendsAuthorizationAndCustomHeaders(): void
    {
        $this->reply(self::USAGE);

        $this->makeTranslator([TranslatorOptions::HEADERS => ['X-Custom' => 'value']], 'my-key')->getUsage();

        $this->assertSame('DeepL-Auth-Key my-key', $this->request()->getHeaderLine('Authorization'));
        $this->assertSame('value', $this->request()->getHeaderLine('X-Custom'));
    }

    /**
     * @dataProvider provideAuthorizationHeaderNames
     */
    public function testDoesNotLetACustomAuthorizationHeaderReplaceTheAuthKey(string $name): void
    {
        $this->reply(self::USAGE);

        $this->makeTranslator([TranslatorOptions::HEADERS => [$name => 'Bearer other']], 'my-key')->getUsage();

        $this->assertSame(['DeepL-Auth-Key my-key'], $this->request()->getHeader('Authorization'));
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
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('authKey must be a non-empty string');

        $this->makeTranslator([], $authKey);
    }

    public static function provideEmptyAuthKeys(): array
    {
        return ['empty' => [''], 'whitespace only' => [" \t\n"]];
    }

    public function testTrimsTheAuthKey(): void
    {
        $this->reply(self::USAGE);

        $this->makeTranslator([], "  my-key \n")->getUsage();

        $this->assertSame('DeepL-Auth-Key my-key', $this->request()->getHeaderLine('Authorization'));
    }

    public function testSelectsTheFreeServerForAFreeKeyWithSurroundingWhitespace(): void
    {
        $this->reply(self::USAGE);

        $translator = new Translator(" abc:fx\n", [
            TranslatorOptions::HTTP_CLIENT => $this->clientOptions([])[TranslatorOptions::HTTP_CLIENT],
        ]);
        $translator->getUsage();

        $this->assertSame('api-free.deepl.com', $this->request()->getUri()->getHost());
        $this->assertSame('DeepL-Auth-Key abc:fx', $this->request()->getHeaderLine('Authorization'));
    }

    /**
     * @dataProvider provideServerUrls
     */
    public function testSendsRequestsToTheServerUrl(?string $serverUrl, string $authKey, string $expectedUrl): void
    {
        $this->reply(self::USAGE);

        $this->makeTranslator([TranslatorOptions::SERVER_URL => $serverUrl], $authKey)->getUsage();

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
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('server_url option must be a non-empty string');

        $this->makeTranslator([TranslatorOptions::SERVER_URL => $serverUrl]);
    }

    public static function provideInvalidServerUrls(): array
    {
        return [[''], [false], [42]];
    }

    public function testLogsRequestAndResponse(): void
    {
        $this->reply(self::USAGE);
        $logger = new TestLogger();

        $this->makeTranslator([TranslatorOptions::LOGGER => $logger])->getUsage();

        $this->assertStringContainsString('Request to DeepL API GET https://api.deepl.com/v2/usage', $logger->content);
        $this->assertStringContainsString(
            'DeepL API response GET https://api.deepl.com/v2/usage 200',
            $logger->content
        );
    }

    public function testWarnsAboutIgnoredAndUnknownOptions(): void
    {
        $this->reply(self::USAGE);
        $logger = new TestLogger();

        $this->makeTranslator([
            TranslatorOptions::LOGGER => $logger,
            TranslatorOptions::TIMEOUT => 3.0,
            TranslatorOptions::PROXY => 'http://proxy.local',
            'unknown_option' => true,
        ])->getUsage();

        $this->assertStringContainsString(
            'Option timeout is ignored as a custom HTTP client is used.',
            $logger->content
        );
        $this->assertStringContainsString('Option proxy is ignored as a custom HTTP client is used.', $logger->content);
        $this->assertStringContainsString(
            'Option unknown_option is not recognized and thus ignored.',
            $logger->content
        );
    }

    public function testIsFreeAccountAuthKey(): void
    {
        $this->assertTrue(Translator::isFreeAccountAuthKey('abc:fx'));
        $this->assertTrue(Translator::isFreeAccountAuthKey("abc:fx\n"));
        $this->assertFalse(Translator::isFreeAccountAuthKey('abc'));
    }

    public function testRecognizesFreeAccountKeys(): void
    {
        $this->assertTrue(Translator::isAuthKeyFreeAccount('abc:fx'));
        $this->assertFalse(Translator::isAuthKeyFreeAccount('abc'));
    }
}
