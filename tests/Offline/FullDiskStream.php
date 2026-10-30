<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

// phpcs:disable PSR1.Methods.CamelCapsMethodName -- PHP names the stream wrapper methods.

/**
 * Stream wrapper whose files do not exist beforehand and accept no bytes, like a full disk.
 */
class FullDiskStream
{
    public const PROTOCOL = 'deepl-full-disk';

    /** @var resource|null */
    public $context;

    public static function register(): void
    {
        if (!in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, self::class);
        }
    }

    public static function unregister(): void
    {
        if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PROTOCOL);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        return 0;
    }

    public function stream_close(): void
    {
    }

    /** @return array|false */
    public function url_stat(string $path, int $flags)
    {
        return false;
    }
}
