<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use DateTimeZone;
use Exception;
use JsonException;

/**
 * Internal helpers for parsing API responses.
 * @private
 */
class ParsingUtils
{
    /**
     * Decodes the given JSON content to an associative array.
     * @param string $content JSON content returned by the API.
     * @return array Decoded JSON object or array.
     * @throws InvalidContentException If the content is not valid JSON or is not a JSON object or array.
     */
    public static function decodeJson(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }
        if (!is_array($decoded)) {
            throw self::invalidContent('Expected a JSON object or array in the response');
        }
        return $decoded;
    }

    /**
     * Decodes the given JSON content to a list of JSON objects.
     * @return array[] The decoded objects.
     * @throws InvalidContentException If the content is not valid JSON or not a JSON list of objects.
     */
    public static function decodeObjectList(string $content): array
    {
        $decoded = self::decodeJson($content);
        if ($decoded !== array_values($decoded)) {
            throw self::invalidContent('Expected a JSON list in the response');
        }
        foreach ($decoded as $element) {
            if (!is_array($element)) {
                throw self::invalidContent('Expected a list of JSON objects in the response');
            }
        }
        return $decoded;
    }

    public static function invalidContent(string $message, ?\Throwable $previous = null): InvalidContentException
    {
        return new InvalidContentException(new JsonException($message, 0, $previous));
    }

    /**
     * Runs the given parse function, converting any failure caused by missing or invalid fields in the
     * response (PHP warnings and notices, TypeErrors, DateTime errors) into an InvalidContentException.
     * @param callable $parse Function that parses the response and returns the result.
     * @return mixed The result of the parse function.
     * @throws DeepLException
     */
    public static function guard(callable $parse)
    {
        set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING | E_NOTICE);
        try {
            return $parse();
        } catch (DeepLException $exception) {
            throw $exception;
        } catch (\Throwable $error) {
            throw self::invalidContent('Unexpected response content: ' . $error->getMessage(), $error);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Converts a required API timestamp to a DateTime.
     * @param mixed $timestamp Timestamp as returned by the API.
     * @throws InvalidContentException If the timestamp is absent or unparseable.
     */
    public static function parseTimestamp($timestamp, ?DateTimeZone $defaultTimeZone = null): DateTime
    {
        if (!is_string($timestamp) || $timestamp === '') {
            throw self::invalidContent('Expected a timestamp in the response');
        }
        try {
            return new DateTime($timestamp, $defaultTimeZone);
        } catch (Exception $exception) {
            throw self::invalidContent("Invalid timestamp in the response: $timestamp", $exception);
        }
    }

    /**
     * Converts an optional API timestamp to a DateTime.
     * @param mixed $timestamp Timestamp as returned by the API, or null if it was absent.
     * @param DateTimeZone|null $defaultTimeZone Time zone assumed for timestamps without one, default is the
     * PHP default time zone.
     * @return DateTime|null The parsed timestamp, or null if it was absent or unparseable.
     */
    public static function parseOptionalTimestamp($timestamp, ?DateTimeZone $defaultTimeZone = null): ?DateTime
    {
        if (!is_string($timestamp) || $timestamp === '') {
            return null;
        }
        try {
            return new DateTime($timestamp, $defaultTimeZone);
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * @param mixed $value
     * @return array|null The value if it is a JSON object or list, otherwise null.
     */
    public static function objectOrNull($value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /**
     * @param mixed $value
     * @return int|float The value if it is numeric, otherwise 0.
     */
    public static function numberOrZero($value)
    {
        return is_numeric($value) ? $value + 0 : 0;
    }

    /**
     * Maps the object entries of a list, skipping entries that are not objects.
     * @param mixed $list
     */
    public static function mapObjects($list, callable $fromJson): array
    {
        if (!is_array($list)) {
            return [];
        }
        return array_values(array_map($fromJson, array_filter($list, 'is_array')));
    }
}
