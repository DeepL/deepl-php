<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use \Psr\Http\Client\ClientInterface;

class CorrectTextTest extends DeepLTestBase
{
    /**
     * @dataProvider provideHttpClient
     */
    public function testSingleText(?ClientInterface $httpClient)
    {
        $deeplClient = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $result = $deeplClient->correctText(DeepLTestBase::EXAMPLE_TEXT['en'], 'en-GB');
        $this->checkSanityOfImprovements(DeepLTestBase::EXAMPLE_TEXT['en'], $result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testMultipleText(?ClientInterface $httpClient)
    {
        $deeplClient = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $input = [
            DeepLTestBase::EXAMPLE_TEXT['en'],
            DeepLTestBase::EXAMPLE_TEXT['en']
        ];
        $result = $deeplClient->correctText($input, 'en-US');

        $this->checkSanityOfImprovements($input[0], $result[0]);
        $this->checkSanityOfImprovements($input[1], $result[1]);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testWithoutTargetLang(?ClientInterface $httpClient)
    {
        $deeplClient = $this->makeDeeplClient([TranslatorOptions::HTTP_CLIENT => $httpClient]);
        $result = $deeplClient->correctText(DeepLTestBase::EXAMPLE_TEXT['en']);
        $this->checkSanityOfImprovements(DeepLTestBase::EXAMPLE_TEXT['en'], $result);
    }

    /**
     * @dataProvider provideHttpClient
     */
    public function testConfiguredDeepLClient(?ClientInterface $httpClient)
    {
        $deeplClient = $this->makeDeeplClient([
            DeepLClientOptions::HTTP_CLIENT => $httpClient,
            DeepLClientOptions::DEFAULT_MAX_RETRIES => 2
        ]);
        $result = $deeplClient->correctText(DeepLTestBase::EXAMPLE_TEXT['en'], 'en-GB');
        $this->checkSanityOfImprovements(DeepLTestBase::EXAMPLE_TEXT['en'], $result);
    }

    public function testEmptyText()
    {
        $deeplClient = $this->makeDeeplClient();
        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('text must not be empty');
        $deeplClient->correctText('');
    }

    private function checkSanityOfImprovements(
        string $inputText,
        RephraseTextResult $result,
        string $expectedLang = 'EN',
        float $epsilon = 0.2
    ): void {
        $this->assertEquals(strtoupper($expectedLang), strtoupper($result->detectedSourceLanguage));
        $nImproved = mb_strlen($result->text);
        $nOriginal = mb_strlen($inputText);
        $ratio = $nImproved / $nOriginal;
        $this->assertGreaterThanOrEqual(1 / (1.0 + $epsilon), $ratio);
        $this->assertLessThanOrEqual(1.0 + $epsilon, $ratio);
    }
}
