<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use JsonException;

/**
 * Information about the API usage: how much has been translated in this billing period, and the
 * maximum allowable amount.
 *
 * Depending on the account type, different usage types are included: the character, document and
 * teamDocument fields provide details about each corresponding usage type, allowing each usage type
 * to be checked individually. The anyLimitReached() function checks if any usage type is exceeded.
 *
 * API Pro accounts additionally receive per-key character usage, speech usage, the billing period
 * and a per-product breakdown; these fields are null for other accounts.
 */
class Usage
{
    /**
     * @var UsageDetail|null Usage details for characters, for example due to the translateText() function.
     */
    public $character;

    /**
     * @var UsageDetail|null Usage details for documents.
     */
    public $document;

    /**
     * @var UsageDetail|null Usage details for documents shared among your team.
     */
    public $teamDocument;

    /**
     * @var UsageDetail|null Usage details for characters translated with this API key, as opposed to
     * $character, which covers the whole account. Only set for API Pro accounts.
     */
    public $apiKeyCharacter;

    /**
     * @var UsageDetail|null Usage details for speech-to-text, in minutes. Only set for API Pro accounts.
     */
    public $speechToTextMinutes;

    /**
     * @var UsageDetail|null Usage details for speech-to-speech, in minutes. Only set for API Pro accounts.
     */
    public $speechToSpeechMinutes;

    /**
     * @var UsageProduct[]|null Per-product usage in the current billing period. Only set for API Pro accounts.
     */
    public $products;

    /**
     * @var DateTime|null Start of the current billing period. Only set for API Pro accounts.
     */
    public $startTime;

    /**
     * @var DateTime|null End of the current billing period. Only set for API Pro accounts.
     */
    public $endTime;

    /**
     * @return bool True if any usage type limit has been reached or passed, otherwise false.
     */
    public function anyLimitReached(): bool
    {
        return ($this->character !== null && $this->character->limitReached()) ||
            ($this->document !== null && $this->document->limitReached()) ||
            ($this->teamDocument !== null && $this->teamDocument->limitReached());
    }

    public function __toString(): string
    {
        $list = [
            'Characters' => $this->character,
            'Documents' => $this->document,
            'Team documents' => $this->teamDocument,
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
        $this->document = $this->buildUsageDetail('document', $json);
        $this->teamDocument = $this->buildUsageDetail('team_document', $json);
        $this->apiKeyCharacter = $this->buildUsageDetail('api_key_character', $json);
        $this->speechToTextMinutes = $this->buildUsageDetail('speech_to_text_minutes', $json);
        $this->speechToSpeechMinutes = $this->buildUsageDetail('speech_to_speech_minutes', $json);
        if (isset($json['products']) && is_array($json['products'])) {
            $this->products = array_map([UsageProduct::class, 'fromJson'], $json['products']);
        }
        $this->startTime = isset($json['start_time']) ? new DateTime($json['start_time']) : null;
        $this->endTime = isset($json['end_time']) ? new DateTime($json['end_time']) : null;
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
