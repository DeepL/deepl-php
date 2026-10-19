<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Fixtures shared by the offline and the real-API suite.
 */
class Fixtures
{
    public const EXAMPLE_TEXT = 'proton beam';

    /** @var string[] */
    private static $tempDirs = [];

    public static function resourcePath(string $name): string
    {
        return __DIR__ . '/../resources/' . $name;
    }

    /** Creates an empty directory that removeTempDirs() deletes. */
    public static function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/deepl-php-test-' . uniqid('', true);
        mkdir($dir);
        self::$tempDirs[] = $dir;
        return $dir;
    }

    public static function removeTempDirs(): void
    {
        foreach (self::$tempDirs as $dir) {
            DocumentMinifier::recursivelyDeleteDirectory($dir);
        }
        self::$tempDirs = [];
    }

    /** Writes a file into a new temporary directory and returns its path. */
    public static function tempFile(string $name, string $content): string
    {
        $path = self::tempDir() . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }
}
