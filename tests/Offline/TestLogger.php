<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL\Offline;

class TestLogger implements \Psr\Log\LoggerInterface
{
    public $content = "";

    public function emergency($message, array $context = array()): void
    {
        $this->content .= $message . PHP_EOL;
    }

    public function alert($message, array $context = array()): void
    {
        $this->content .= $message . PHP_EOL;
    }

    public function critical($message, array $context = array()): void
    {
        $this->content .= $message . PHP_EOL;
    }

    public function error($message, array $context = array()): void
    {
        $this->content .= $message . PHP_EOL;
    }

    public function warning($message, array $context = array()): void
    {
        $this->content .= $message . PHP_EOL;
    }

    public function notice($message, array $context = array()): void
    {
        $this->content .= $message . PHP_EOL;
    }

    public function info($message, array $context = array()): void
    {
        $this->content .= $message . PHP_EOL;
    }

    public function debug($message, array $context = array()): void
    {
        $this->content .= $message . PHP_EOL;
    }

    public function log($level, $message, array $context = array()): void
    {
        $this->content .= $message . PHP_EOL;
    }
}
