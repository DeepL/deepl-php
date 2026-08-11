<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class DeepLTestBase extends TestCase
{
    use \phpmock\phpunit\PHPMock;

    protected $authKey;
    protected $serverUrl;
    protected $proxyUrl;
    protected $isMockServer;
    protected $isMockProxyServer;

    protected $sessionNoResponse;
    protected $session429Count;
    protected $sessionInitCharacterLimit;
    protected $sessionInitDocumentLimit;
    protected $sessionInitTeamDocumentLimit;
    protected $sessionDocFailure;
    protected $sessionDocQueueTime;
    protected $sessionDocTranslateTime;
    protected $sessionExpectProxy;
    protected $sessionTmJobProcessingPolls;

    protected const EXAMPLE_TEXT = [
        'ar' => 'شعاع البروتون',
        'bg' => 'протонен лъч',
        'cs' => 'protonový paprsek',
        'da' => 'protonstråle',
        'de' => 'Protonenstrahl',
        'el' => 'δέσμη πρωτονίων',
        'en' => 'proton beam',
        'en-US' => 'proton beam',
        'en-GB' => 'proton beam',
        'es' => 'haz de protones',
        'et' => 'prootonikiirgus',
        'fi' => 'protonisäde',
        'fr' => 'faisceau de protons',
        'hu' => 'protonnyaláb',
        'id' => 'berkas proton',
        'it' => 'fascio di protoni',
        'ja' => '陽子ビーム',
        'ko' => '양성자 빔',
        'lt' => 'protonų spindulys',
        'lv' => 'protonu staru kūlis',
        'nb' => 'protonstråle',
        'nl' => 'protonenbundel',
        'pl' => 'wiązka protonów',
        'pt' => 'feixe de prótons',
        'pt-BR' => 'feixe de prótons',
        'pt-PT' => 'feixe de prótons',
        'ro' => 'fascicul de protoni',
        'ru' => 'протонный луч',
        'sk' => 'protónový lúč',
        'sl' => 'protonski žarek',
        'sv' => 'protonstråle',
        'tr' => 'proton ışını',
        'uk' => 'протонний пучок',
        'zh' => '质子束',
    ];

    protected const DOC_MINIFICATION_TEST_FILES_MAPPING = [
        'example_document_template.docx' => 'example_document.docx',
        'example_presentation_template.pptx' => 'example_presentation.pptx',
    ];

    protected const DOC_MINIFICATION_UNSUPPORTED_TEST_TEMPLATE = 'example_zip_template.zip';
    protected const DOC_MINIFICATION_UNSUPPORTED_TEST_FILE = 'example_zip.zip';

    protected const EXAMPLE_DOCUMENT_INPUT = DeepLTestBase::EXAMPLE_TEXT['en'];
    protected const EXAMPLE_DOCUMENT_OUTPUT = DeepLTestBase::EXAMPLE_TEXT['de'];
    protected $EXAMPLE_LARGE_DOCUMENT_INPUT;
    protected $EXAMPLE_LARGE_DOCUMENT_OUTPUT;

    public function __construct(?string $name = null, array $data = array(), $dataName = '')
    {
        $this->EXAMPLE_LARGE_DOCUMENT_INPUT = str_repeat(DeepLTestBase::EXAMPLE_TEXT['en'] . PHP_EOL, 1000);
        $this->EXAMPLE_LARGE_DOCUMENT_OUTPUT = str_repeat(DeepLTestBase::EXAMPLE_TEXT['de'] . PHP_EOL, 1000);

        $this->serverUrl = getenv('DEEPL_SERVER_URL');
        $this->proxyUrl = getenv('DEEPL_PROXY_URL');
        $this->isMockServer = getenv('DEEPL_MOCK_SERVER_PORT') !== false;
        $this->isMockProxyServer = $this->isMockServer && getenv('DEEPL_MOCK_PROXY_SERVER_PORT') !== false;

        if ($this->isMockServer) {
            $this->authKey = 'mock_server';
            if ($this->serverUrl === false) {
                throw new \Exception('DEEPL_SERVER_URL environment variable must be set if using a mock server');
            }
        } else {
            $this->authKey = getenv('DEEPL_AUTH_KEY');
            if ($this->authKey === false) {
                throw new \Exception('DEEPL_AUTH_KEY environment variable must be set unless using a mock server');
            }
        }

        parent::__construct($name, $data, $dataName);
    }

    protected function needsMockServer()
    {
        if (!$this->isMockServer) {
            self::markTestSkipped('Test requires mock server');
        }
    }

    protected function needsMockProxyServer()
    {
        if (!$this->isMockProxyServer) {
            self::markTestSkipped('Test requires mock proxy server');
        }
    }

    protected function needsRealServer()
    {
        if ($this->isMockServer) {
            self::markTestSkipped('Test requires real server');
        }
    }

    private function makeSessionName(): string
    {
        return $this->getName() . '/' . Uuid::uuid4();
    }

    private function sessionHeaders(): array
    {
        $result = array();
        if ($this->sessionNoResponse !== null) {
            $result['mock-server-session-no-response-count'] = strval($this->sessionNoResponse);
        }
        if ($this->session429Count !== null) {
            $result['mock-server-session-429-count'] = strval($this->session429Count);
        }
        if ($this->sessionInitCharacterLimit !== null) {
            $result['mock-server-session-init-character-limit'] = strval($this->sessionInitCharacterLimit);
        }
        if ($this->sessionInitDocumentLimit !== null) {
            $result['mock-server-session-init-document-limit'] = strval($this->sessionInitDocumentLimit);
        }
        if ($this->sessionInitTeamDocumentLimit !== null) {
            $result['mock-server-session-init-team-document-limit'] = strval($this->sessionInitTeamDocumentLimit);
        }
        if ($this->sessionDocFailure !== null) {
            $result['mock-server-session-doc-failure'] = strval($this->sessionDocFailure);
        }
        if ($this->sessionDocQueueTime !== null) {
            $result['mock-server-session-doc-queue-time'] = strval($this->sessionDocQueueTime * 1000);
        }
        if ($this->sessionDocTranslateTime !== null) {
            $result['mock-server-session-doc-translate-time'] = strval($this->sessionDocTranslateTime * 1000);
        }
        if ($this->sessionExpectProxy !== null) {
            $result['mock-server-session-expect-proxy'] = $this->sessionExpectProxy ? '1' : '0';
        }
        if ($this->sessionTmJobProcessingPolls !== null) {
            $result['mock-server-session-tm-job-processing-polls'] = strval($this->sessionTmJobProcessingPolls);
        }

        if (count($result) > 0) {
            $result['mock-server-session'] = $this->makeSessionName();
        }

        return $result;
    }

    public function makeTranslator(array $options = []): Translator
    {
        $mergedOptions = array_replace(
            [TranslatorOptions::HEADERS => $this->sessionHeaders()],
            $options ?? []
        );

        if ($this->serverUrl !== false) {
            $mergedOptions[TranslatorOptions::SERVER_URL] = $this->serverUrl;
        }

        return new Translator($this->authKey, $mergedOptions);
    }

    public function makeDeeplClient(array $options = []): DeepLClient
    {
        $mergedOptions = array_replace(
            [TranslatorOptions::HEADERS => $this->sessionHeaders()],
            $options ?? []
        );

        if ($this->serverUrl !== false) {
            $mergedOptions[TranslatorOptions::SERVER_URL] = $this->serverUrl;
        }
        
        return new DeepLClient($this->authKey, $mergedOptions);
    }

    public function makeTranslatorWithRandomAuthKey(array $options = []): Translator
    {
        $mergedOptions = array_replace([
            TranslatorOptions::SERVER_URL => $this->serverUrl,
            TranslatorOptions::HEADERS => $this->sessionHeaders(),
        ], $options ?? []);
        $authKey = Uuid::uuid4();

        return new Translator($authKey, $mergedOptions);
    }

    public static function readFile(string $filepath): string
    {
        $size = filesize($filepath);
        if ($size == 0) {
            return "";
        } else {
            $fh = fopen($filepath, 'r');
            $content = fread($fh, filesize($filepath));
            fclose($fh);
            return $content;
        }
    }

    public static function writeFile(string $filepath, string $content)
    {
        $fh = fopen($filepath, 'w');
        fwrite($fh, $content);
        fclose($fh);
    }

    public function tempFiles(): array
    {
        $tempDir = sys_get_temp_dir() . '/deepl-php-test-' . Uuid::uuid4() . '/';
        $exampleDocument = $tempDir . 'example_document.txt';
        $exampleLargeDocument = $tempDir . 'example_large_document.txt';
        $outputDocumentPath = $tempDir . 'output_document.txt';

        mkdir($tempDir);
        $this->writeFile($exampleDocument, DeepLTestBase::EXAMPLE_DOCUMENT_INPUT);
        $this->writeFile($exampleLargeDocument, $this->EXAMPLE_LARGE_DOCUMENT_INPUT);

        return [$tempDir, $exampleDocument, $exampleLargeDocument, $outputDocumentPath];
    }

    public function assertExceptionContains(string $needle, callable $function): \Exception
    {
        try {
            $function();
        } catch (\Exception $exception) {
            $this->assertStringContainsString($needle, $exception->getMessage());
            return $exception;
        }
        $this->fail("Expected exception containing '$needle' but nothing was thrown");
    }

    public function assertExceptionClass($class, callable $function): \Exception
    {
        try {
            $function();
        } catch (\Exception $exception) {
            $this->assertEquals($class, get_class($exception));
            return $exception;
        }
        $this->fail("Expected exception of class '$class' but nothing was thrown");
    }

    public static function getFullPathForTestFile(string $testFileName): string
    {
        return __DIR__ . '/../resources/' . $testFileName;
    }

    public static function createDocumentMinificationTestFiles(): void
    {
        foreach (DeepLTestBase::DOC_MINIFICATION_TEST_FILES_MAPPING as $templateFilename => $inflatedFilename) {
            $testDocTemplateFile = self::getFullPathForTestFile($templateFilename);
            $testDocInflatedFile = self::getFullPathForTestFile($inflatedFilename);
            self::inflateTestFileWithLargeImage($testDocTemplateFile, $testDocInflatedFile);
        }
    }

    public static function removeDocumentMinificationTestFiles()
    {
        foreach (DeepLTestBase::DOC_MINIFICATION_TEST_FILES_MAPPING as $inflatedFileToDelete) {
            unlink($inflatedFileToDelete);
        }
    }

    protected static function inflateTestFileWithLargeImage(string $inputFile, string $outputFile)
    {
        $extractionDir = self::getFullPathForTestFile('inflation_tmp_dir');
        if (!is_dir($extractionDir)) {
            if (!mkdir($extractionDir)) {
                throw new \RuntimeException("Failed creating dir $extractionDir for test files for doc minification");
            }
        }
        self::extractZipFileTo($inputFile, $extractionDir);
        $zip = new \ZipArchive();
        if ($zip->open($outputFile, \ZipArchive::CREATE) === true) {
            $fakeImageName = 'placeholder_image.png';
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ~!@#$%^&*()_+=-<,>.?:';
            $length = 90000000;
            # str_shuffle is not cryptographically secure, but this is just test data
            $randomString = substr(str_shuffle(
                str_repeat($characters, ceil($length/strlen($characters)))
            ), 1, $length);
            $zip->addFromString($fakeImageName, $randomString);

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractionDir));
            foreach ($iterator as $file) {
                if (substr($file, -2) === '/.' || substr($file, -3) === '/..') {
                    continue;
                }
                $file->isDir() ?
                    $zip->addEmptyDir(str_replace($extractionDir . '/', '', $file . '/'))
                    : $zip->addFile($file, str_replace($extractionDir . '/', '', $file));
            }
            $zip->close();
        } else {
            throw new \RuntimeException('Failed creating inflated test file for doc minification');
        }

        DocumentMinifier::recursivelyDeleteDirectory($extractionDir);
    }

    protected static function extractZipFileTo(string $zipFilePath, string $extractionDirPath): void
    {
        if (!is_dir($extractionDirPath)) {
            mkdir($extractionDirPath);
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath) === false) {
            throw new \RuntimeException("Failed opening zip file $zipFilePath");
        }
        $zip->extractTo($extractionDirPath);
        $zip->close();
    }

    public function assertDirectoriesAreEqual(string $dir1, string $dir2, string $message = ''): void
    {
        $dir1Hashes = $this->getDirectoryContentsToHashes($dir1);
        $dir2Hashes = $this->getDirectoryContentsToHashes($dir2);

        $this->assertAssociativeArraysAreValueEqual($dir1Hashes, $dir2Hashes, $message);
    }

    protected function assertAssociativeArraysAreValueEqual(array $array1, array $array2, string $message = ''): void
    {
        $this->assertEquals(count($array1), count($array2));
        foreach ($array1 as $key1 => $value1) {
            if (is_string($value1)) {
                $this->assertEquals($value1, $array2[$key1], $message);
            } else {
                $this->assertAssociativeArraysAreValueEqual($array1[$key1], $array2[$key1], $message);
            }
        }
    }

    protected function getDirectoryContentsToHashes(string $dir): array
    {
        $hashes = array();
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $path) {
                if ($path !== '.' && $path !== '..') {
                    $absolutePath = $dir . '/' . $path;
                    if (is_dir($path)) {
                        $hashes[$path] = $this->getDirectoryContentsToHashes($absolutePath);
                    } else {
                        $hashes[$path] = hash_file('md5', $absolutePath);
                        if ($hashes[$path] === false) {
                            throw new \RuntimeException("Failed hashing $absolutePath");
                        }
                    }
                }
            }
        }
        return $hashes;
    }

    /**
     * This is necessary due to https://github.com/php-mock/php-mock-phpunit#restrictions
     * In short, as these methods can be called by other tests before UserAgentTest and other
     * tests that use their mocks are executed, we need to call `defineFunctionMock` before
     * calling the unmocked function, or the mock will not work.
     * Otherwise the tests will fail with:
     *     Expectation failed for method name is "delegate" when invoked 1 time(s).
     *     Method was expected to be called 1 times, actually called 0 times.
     */
    public static function setUpBeforeClass(): void
    {
        self::defineFunctionMock(__NAMESPACE__, 'curl_exec');
        self::defineFunctionMock(__NAMESPACE__, 'curl_getinfo');
        self::defineFunctionMock(__NAMESPACE__, 'curl_setopt_array');
    }

    public static function provideHttpClient(): array
    {
        return [[null], [new \GuzzleHttp\Client()]];
    }

    public static function provideModelType(): array
    {
        return [
            ['quality_optimized', 'quality_optimized'],
            ['latency_optimized', 'latency_optimized'],
            ['prefer_quality_optimized', 'quality_optimized']
        ];
    }

    public static function provideHttpClientAndModelType(): array
    {
        return self::dataProviderCartesianProduct(self::provideHttpClient(), self::provideModelType());
    }

    protected static function dataProviderCartesianProduct(array $providerOutput1, array $providerOutput2): array
    {
        $result = [];
        foreach ($providerOutput1 as $args1) {
            foreach ($providerOutput2 as $args2) {
                $result[] = array_merge($args1, $args2);
            }
        }
        return $result;
    }
}
