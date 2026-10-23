<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

/**
 * Information about the API usage: how much has been translated in this billing period, and the
 * maximum allowable amount.
 *
 * Depending on the account type, different usage types are included: the character, document and
 * teamDocument fields provide details about each corresponding usage type, allowing each usage type
 * to be checked individually. The anyLimitReached() function checks if any usage type is exceeded.
 */
class Usage
{
    /**
     * @var UsageDetail|null Usage details for characters, for example due to the translateText() function.
     */
    public $character;

    /**
     * @var UsageDetail|null Usage details for characters used by this API key only.
     */
    public $apiKeyCharacter;

    /**
     * @var UsageDetail|null Usage details for documents.
     */
    public $document;

    /**
     * @var UsageDetail|null Usage details for documents shared among your team.
     */
    public $teamDocument;

    /**
     * @var UsageDetail|null Usage details for speech-to-text, in minutes.
     */
    public $speechToTextMinutes;

    /**
     * @var UsageDetail|null Usage details for speech-to-text, in milliseconds.
     */
    public $speechToTextMilliseconds;

    /**
     * @var UsageDetail|null Usage details for speech-to-speech, in minutes.
     */
    public $speechToSpeechMinutes;

    /**
     * @var UsageProduct[] Usage broken down per product, empty if the account does not report it.
     */
    public $products;

    /**
     * @var string|null Start of the billing period, as an ISO 8601 timestamp.
     */
    public $startTime;

    /**
     * @var string|null End of the billing period, as an ISO 8601 timestamp.
     */
    public $endTime;

    /**
     * @return bool True if any usage type limit has been reached or passed, otherwise false.
     */
    public function anyLimitReached(): bool
    {
        $details = [
            $this->apiKeyCharacter,
            $this->character,
            $this->document,
            $this->teamDocument,
            $this->speechToTextMinutes,
            $this->speechToSpeechMinutes,
        ];
        /** @var UsageDetail|null $detail */
        foreach ($details as $detail) {
            if ($detail !== null && $detail->limitReached()) {
                return true;
            }
        }
        return false;
    }

    public function __toString(): string
    {
        $list = [
            'API key characters' => $this->apiKeyCharacter,
            'Characters' => $this->character,
            'Documents' => $this->document,
            'Team documents' => $this->teamDocument,
            'Speech-to-text minutes' => $this->speechToTextMinutes,
            'Speech-to-speech minutes' => $this->speechToSpeechMinutes,
        ];
        $result = 'Usage this billing period:';
        foreach ($list as $label => $detail) {
            if ($detail !== null) {
                $result .= "\n$label: $detail->count of $detail->limit";
            }
        }
        return $result;
    }

    /**
     * @throws InvalidContentException
     */
    public function __construct(string $content)
    {
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        $this->character = $this->buildUsageDetail('character', $json);
        $this->apiKeyCharacter = $this->buildUsageDetail('api_key_character', $json);
        $this->document = $this->buildUsageDetail('document', $json);
        $this->teamDocument = $this->buildUsageDetail('team_document', $json);
        $this->speechToTextMinutes = $this->buildUsageDetail('speech_to_text_minutes', $json);
        $this->speechToTextMilliseconds = $this->buildUsageDetail('speech_to_text_milliseconds', $json);
        $this->speechToSpeechMinutes = $this->buildUsageDetail('speech_to_speech_minutes', $json);
        $this->products = array_map(
            function (array $product): UsageProduct {
                return new UsageProduct($product);
            },
            $json['products'] ?? []
        );
        $this->startTime = $json['start_time'] ?? null;
        $this->endTime = $json['end_time'] ?? null;
    }

    private function buildUsageDetail(string $prefix, array $json): ?UsageDetail
    {
        $count = "{$prefix}_count";
        $limit = "{$prefix}_limit";
        if (array_key_exists($count, $json) && array_key_exists($limit, $json)) {
            return new UsageDetail($json[$count], $json[$limit]);
        }
        return null;
    }
}
