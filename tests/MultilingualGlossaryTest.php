<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use Psr\Http\Client\ClientInterface;

class MultilingualGlossaryTest extends DeepLTestBase
{
    private $testEntries = ['Hello' => 'Hallo'];
    private $sourceLang = 'en';
    private $targetLang = 'de';
    private $invalidGlossaryId = 'invalid_glossary_id';
    private $nonexistentGlossaryId = '96ab91fd-e715-41a1-adeb-5d701f84a483';

    private function getGlossaryName(string $testName = ""): string
    {
        $uuid = uniqid();
        return "deepl-php-test-multilingual-glossary: $testName $uuid";
    }

    private function getTestDictionary(): MultilingualGlossaryDictionaryEntries
    {
        return new MultilingualGlossaryDictionaryEntries($this->sourceLang, $this->targetLang, $this->testEntries);
    }

    private function cleanupGlossary(DeepLClient $client, string $glossaryName)
    {
        try {
            $glossaries = $client->listMultilingualGlossaries();
            foreach ($glossaries as $glossary) {
                if ($glossary->name === $glossaryName) {
                    try {
                        $client->deleteMultilingualGlossary($glossary);
                    } catch (DeepLException $exception) {
                        echo "Exception occurred while cleaning up glossary: " . $exception->getMessage() . PHP_EOL;
                    }
                }
            }
        } catch (DeepLException $exception) {
            echo "Exception occurred while listing glossaries for clean up: " . $exception->getMessage() . PHP_EOL;
        }
    }

    /**
     * Returns the dictionary in the glossary with matching language pair, or fails by assert.
     */
    private function findMatchingDictionary(
        MultilingualGlossaryInfo $glossaryInfo,
        string $sourceLang,
        string $targetLang
    ): MultilingualGlossaryDictionaryInfo {
        foreach ($glossaryInfo->dictionaries as $dictionary) {
            if ($dictionary->sourceLang === $sourceLang && $dictionary->targetLang === $targetLang) {
                return $dictionary;
            }
        }
        $this->fail("glossary did not contain expected language pair $sourceLang->$targetLang");
    }

    public function testMultilingualGlossaryDictionaryEntries()
    {
        $this->assertExceptionContains('no entries', function () {
            GlossaryUtils::fromTsv("");
        });
        $this->assertExceptionContains('source term', function () {
            GlossaryUtils::fromTsv("Küche\tKitchen\nKüche\tCuisine");
        });
        $this->assertExceptionContains('term separator', function () {
            GlossaryUtils::fromTsv("A\tB\tC");
        });

        $this->assertExceptionContains('no entries', function () {
            new MultilingualGlossaryDictionaryEntries('de', 'en', []);
        });
        $this->assertExceptionContains('invalid characters', function () {
            new MultilingualGlossaryDictionaryEntries('de', 'en', ["A" => "B\tC"]);
        });
        $this->assertExceptionContains('invalid characters', function () {
            new MultilingualGlossaryDictionaryEntries('de', 'en', ["A" => "B\u{2028}C"]);
        });
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryCreate(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $dictionaries = [$this->getTestDictionary()];
            $glossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);
            $this->assertEquals($glossaryName, $glossary->name);
            $this->assertEquals(1, count($glossary->dictionaries));
            $this->assertEquals($this->sourceLang, $glossary->dictionaries[0]->sourceLang);
            $this->assertEquals($this->targetLang, $glossary->dictionaries[0]->targetLang);
            $this->assertEquals(1, $glossary->dictionaries[0]->entryCount);

            $getResult = $client->getMultilingualGlossary($glossary->glossaryId);
            $this->assertEquals($glossary->name, $getResult->name);
            $this->assertEquals(1, count($getResult->dictionaries));
            $this->assertEquals($this->sourceLang, $getResult->dictionaries[0]->sourceLang);
            $this->assertEquals($this->targetLang, $getResult->dictionaries[0]->targetLang);
            $this->assertEquals(1, $getResult->dictionaries[0]->entryCount);
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryCreateLarge(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $entries = [];
            for ($i = 0; $i <10000; $i++) {
                $entries["Source-$i"] = "Target-$i";
            }
            $dictionary = new MultilingualGlossaryDictionaryEntries($this->sourceLang, $this->targetLang, $entries);
            $this->assertGreaterThan(100000, strlen($dictionary->convertToTsv()));

            $glossary = $client->createMultilingualGlossary(
                $glossaryName,
                [$dictionary]
            );
            $this->assertEquals($glossaryName, $glossary->name);
            $this->assertEquals(1, count($glossary->dictionaries));
            $this->assertEquals($this->sourceLang, $glossary->dictionaries[0]->sourceLang);
            $this->assertEquals($this->targetLang, $glossary->dictionaries[0]->targetLang);
            $this->assertEquals(count($entries), $glossary->dictionaries[0]->entryCount);
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryCreateCsv(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $expectedEntries = ["sourceEntry1" => "targetEntry1", "source\"Entry" => "target,Entry"];
            $csvContent = "sourceEntry1,targetEntry1,en,de\n\"source\"\"Entry\",\"target,Entry\",en,de";
            $glossary = $client->createMultilingualGlossaryFromCsv(
                $glossaryName,
                $this->sourceLang,
                $this->targetLang,
                $csvContent
            );
            $this->assertEquals($expectedEntries, $client->getMultilingualGlossaryEntries(
                $glossary,
                $this->sourceLang,
                $this->targetLang
            )[0]->entries);
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryCreateInvalid(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $dictionaries = [$this->getTestDictionary()];
            $this->assertExceptionContains("name", function () use ($dictionaries, $glossaryName, $client) {
                $client->createMultilingualGlossary("", $dictionaries);
            });
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryUpdateRename(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        $editedGlossaryName = $this->getGlossaryName() . "-edited";
        try {
            $dictionaries = [$this->getTestDictionary()];

            $createdGlossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);
            $glossaryId = $createdGlossary->glossaryId;

            $updatedGlossary = $client->updateMultilingualGlossary($glossaryId, $editedGlossaryName, null);
            $this->assertEquals($editedGlossaryName, $updatedGlossary->name);
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
            $this->cleanupGlossary($client, $editedGlossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryUpdateDictionary(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $dictionaries = [
                new MultilingualGlossaryDictionaryEntries('en', 'de', ['Hello' => 'Hallo'])];
            $createdGlossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);

            $updatedGlossary = $client->updateMultilingualGlossary(
                $createdGlossary,
                null,
                [new MultilingualGlossaryDictionaryEntries('de', 'en', ['Hallo' => 'Hello'])]
            );
            $this->assertEquals(2, count($updatedGlossary->dictionaries));
            $this->assertEquals(1, $this->findMatchingDictionary($updatedGlossary, 'de', 'en')->entryCount);

            $updatedGlossary = $client->updateMultilingualGlossary(
                $createdGlossary,
                null,
                [new MultilingualGlossaryDictionaryEntries('en', 'de', ['Apple' => 'Apfel'])]
            );
            $this->assertEquals(2, count($updatedGlossary->dictionaries));
            $this->assertEquals(1, $this->findMatchingDictionary($updatedGlossary, 'de', 'en')->entryCount);
            $this->assertEquals(2, $this->findMatchingDictionary($updatedGlossary, 'en', 'de')->entryCount);
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryReplaceDictionary(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $dictionaries = [new MultilingualGlossaryDictionaryEntries('en', 'de', ['Hello' => 'Hallo'])];
            $createdGlossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);

            $updatedDictionary = $client->replaceMultilingualGlossaryDictionary(
                $createdGlossary,
                new MultilingualGlossaryDictionaryEntries('de', 'en', ['Hallo' => 'Hello'])
            );
            $this->assertEquals(1, $updatedDictionary->entryCount);
            $updatedGlossary = $client->getMultilingualGlossary($createdGlossary);
            $this->assertEquals(2, count($updatedGlossary->dictionaries));
            $this->assertEquals(1, $this->findMatchingDictionary($updatedGlossary, 'en', 'de')->entryCount);
            $this->assertEquals(1, $this->findMatchingDictionary($updatedGlossary, 'de', 'en')->entryCount);

            $updatedDictionary = $client->replaceMultilingualGlossaryDictionary(
                $createdGlossary,
                new MultilingualGlossaryDictionaryEntries('en', 'de', ['Apple' => 'Apfel'])
            );
            $this->assertEquals(1, $updatedDictionary->entryCount);
            $updatedGlossary = $client->getMultilingualGlossary($createdGlossary);
            $this->assertEquals(2, count($updatedGlossary->dictionaries));
            $this->assertEquals(1, $this->findMatchingDictionary($updatedGlossary, 'de', 'en')->entryCount);
            $this->assertEquals(1, $this->findMatchingDictionary($updatedGlossary, 'en', 'de')->entryCount);
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryGet(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $dictionaries = [$this->getTestDictionary()];
            $createdGlossary =                $client->createMultilingualGlossary($glossaryName, $dictionaries);

            $glossary = $client->getMultilingualGlossary($createdGlossary->glossaryId);

            $this->assertEquals($glossaryName, $glossary->name);
            $this->assertEquals($createdGlossary->glossaryId, $glossary->glossaryId);
            $this->assertEquals($createdGlossary->creationTime, $glossary->creationTime);
            $this->assertEquals($this->sourceLang, $glossary->dictionaries[0]->sourceLang);
            $this->assertEquals($this->targetLang, $glossary->dictionaries[0]->targetLang);
            $this->assertEquals(count($this->testEntries), $glossary->dictionaries[0]->entryCount);

            $this->assertExceptionClass(DeepLException::class, function () use ($client) {
                $client->getMultilingualGlossary($this->invalidGlossaryId);
            });
            $this->assertExceptionClass(GlossaryNotFoundException::class, function () use ($client) {
                $client->getMultilingualGlossary($this->nonexistentGlossaryId);
            });
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryGetEntries(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $entries = [
                'Apple' => 'Apfel', 'Banana' => 'Banane', 'A%=&' => 'B&=%', "\u{0394}\u{3041}" => "\u{6DF1}",
                "\u{1FAA8}" => "\u{1FAB5}"
            ];
            $dictionaries = [new MultilingualGlossaryDictionaryEntries($this->sourceLang, $this->targetLang, $entries)];
            $createdGlossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);

            $response = $client->getMultilingualGlossaryEntries($createdGlossary, $this->sourceLang, $this->targetLang);
            $this->assertEquals($dictionaries[0]->entries, $response[0]->entries);

            $response = $client->getMultilingualGlossaryEntries(
                $createdGlossary->glossaryId,
                $this->sourceLang,
                $this->targetLang
            );
            $this->assertEquals($dictionaries[0]->entries, $response[0]->entries);

            $this->assertExceptionClass(DeepLException::class, function () use ($client) {
                $client->getMultilingualGlossaryEntries($this->invalidGlossaryId, $this->sourceLang, $this->targetLang);
            });
            $this->assertExceptionClass(GlossaryNotFoundException::class, function () use ($client) {
                $client->getMultilingualGlossaryEntries(
                    $this->nonexistentGlossaryId,
                    $this->sourceLang,
                    $this->targetLang
                );
            });
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryList(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $dictionaries = [$this->getTestDictionary()];
            $client->createMultilingualGlossary($glossaryName, $dictionaries);
            $glossaries = $client->listMultilingualGlossaries();
            $found = false;
            foreach ($glossaries as $glossary) {
                if ($glossary->name === $glossaryName) {
                    $found = true;
                }
            }
            $this->assertTrue($found);
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryDelete(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $dictionaries = [$this->getTestDictionary()];
            $glossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);
            $client->deleteMultilingualGlossary($glossary);

            $this->assertExceptionClass(GlossaryNotFoundException::class, function () use ($glossary, $client) {
                $client->deleteMultilingualGlossary($glossary);
            });
            $this->assertExceptionClass(DeepLException::class, function () use ($client) {
                $client->deleteMultilingualGlossary($this->invalidGlossaryId);
            });
            $this->assertExceptionClass(GlossaryNotFoundException::class, function () use ($client) {
                $client->deleteMultilingualGlossary($this->nonexistentGlossaryId);
            });
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryDeleteDictionary(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $dictionaries = [
                new MultilingualGlossaryDictionaryEntries("en", "de", ["Hello" => "Hallo"]),
                new MultilingualGlossaryDictionaryEntries("de", "en", ["Hallo" => "Hello"]),
                new MultilingualGlossaryDictionaryEntries("en", "fr", ["Hello" => "Bonjour"])
            ];
            $glossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);
            $this->assertEquals(3, count($glossary->dictionaries));

            $client->deleteMultilingualGlossaryDictionary($glossary, null, "en", "de");
            $glossary = $client->getMultilingualGlossary($glossary->glossaryId);
            $this->assertEquals(2, count($glossary->dictionaries));

            $this->assertExceptionClass(GlossaryNotFoundException::class, function () use ($glossary, $client) {
                $client->deleteMultilingualGlossaryDictionary($glossary, null, "en", "de");
            });

            $dictionaryDeEn = $glossary->dictionaries[0];
            $client->deleteMultilingualGlossaryDictionary($glossary, $dictionaryDeEn);
            $glossary = $client->getMultilingualGlossary($glossary->glossaryId);
            $this->assertEquals(1, count($glossary->dictionaries));

            $client->deleteMultilingualGlossaryDictionary($glossary, null, "en", "fr");
            $glossary = $client->getMultilingualGlossary($glossary->glossaryId);
            $this->assertEquals(0, count($glossary->dictionaries));
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryTranslateTextSentence(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $dictionaries = [new MultilingualGlossaryDictionaryEntries(
                'en',
                'de',
                ['artist' => 'Maler', 'prize' => 'Gewinn']
            )];
            $input = "The artist was awarded a prize.";
            $glossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);
            $result = $client->translateText(
                $input,
                $this->sourceLang,
                $this->targetLang,
                [TranslateTextOptions::GLOSSARY => $glossary]
            );
            if (!$this->isMockServer) {
                $this->assertStringContainsString('Maler', $result->text);
                $this->assertStringContainsString('Gewinn', $result->text);
            } else {
                // Add an assertion for mock server (phpunit expects an assertion in each test)
                $this->assertEquals($this->sourceLang, $result->detectedSourceLang);
            }

            // Also test using a glossary ID
            $result = $client->translateText(
                $input,
                $this->sourceLang,
                $this->targetLang,
                [TranslateTextOptions::GLOSSARY => $glossary->glossaryId]
            );
            if (!$this->isMockServer) {
                $this->assertStringContainsString('Maler', $result->text);
                $this->assertStringContainsString('Gewinn', $result->text);
            }
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryTranslateTextBasic(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $textsEn = ['Apple', 'Banana'];
            $textsDe = ['Apfel', 'Banane'];
            $entriesEnDe = [];
            $entriesDeEn = [];
            for ($i = 0; $i < count($textsDe); $i++) {
                $entriesDeEn[$textsDe[$i]] = $textsEn[$i];
                $entriesEnDe[$textsEn[$i]] = $textsDe[$i];
            }
            $dictionaries = [new MultilingualGlossaryDictionaryEntries("en", "de", $entriesEnDe),
                new MultilingualGlossaryDictionaryEntries("de", "en", $entriesDeEn)];

            $glossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);

            $resultEn = $client->translateText($textsDe, 'de', 'en-US', ["glossary" => $glossary]);
            $resultDe = $client->translateText($textsEn, 'en', 'de', ["glossary" => $glossary]);

            for ($i = 0; $i < count($textsDe); $i++) {
                $this->assertEquals($textsDe[$i], $resultDe[$i]->text);
                $this->assertEquals($textsEn[$i], $resultEn[$i]->text);
            }
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryTranslateDocument(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        $inputText = "artist\nprize";

        $entries = ['artist' => 'Maler', 'prize' => 'Gewinn'];
        $dictionaries = [new MultilingualGlossaryDictionaryEntries('en', 'de', $entries)];
        list(, $exampleDocument, , $outputDocumentPath) = $this->tempFiles();
        $this->writeFile($exampleDocument, $inputText);

        try {
            $glossary = $client->createMultilingualGlossary($glossaryName, $dictionaries);
            $client->translateDocument(
                $exampleDocument,
                $outputDocumentPath,
                $this->sourceLang,
                $this->targetLang,
                [TranslateDocumentOptions::GLOSSARY => $glossary]
            );
            $this->assertEquals("Maler\nGewinn", $this->readFile($outputDocumentPath));

            unlink($outputDocumentPath);
            $client->translateDocument(
                $exampleDocument,
                $outputDocumentPath,
                $this->sourceLang,
                $this->targetLang,
                [TranslateDocumentOptions::GLOSSARY => $glossary->glossaryId]
            );
            $this->assertEquals("Maler\nGewinn", $this->readFile($outputDocumentPath));
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }

    /**
     * @dataProvider provideHttpClient
     * @throws DeepLException
     */
    public function testMultilingualGlossaryTranslateTextInvalid(?ClientInterface $httpClient)
    {
        $client = $this->makeDeepLClient([DeepLClientOptions::HTTP_CLIENT => $httpClient]);
        $glossaryName = $this->getGlossaryName();
        try {
            $glossary = $client->createMultilingualGlossary(
                $glossaryName,
                [new MultilingualGlossaryDictionaryEntries('en', 'de', $this->testEntries),
                new MultilingualGlossaryDictionaryEntries('de', 'en', $this->testEntries)]
            );

            $this->assertExceptionContains('sourceLang is required', function () use ($glossary, $client) {
                $client->translateText('test', null, 'de', ['glossary' => $glossary]);
            });
        } finally {
            $this->cleanupGlossary($client, $glossaryName);
        }
    }
}
