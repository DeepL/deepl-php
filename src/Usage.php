<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;

/**
 * Information about the API usage: how much has been translated in this billing period, and the
 * maximum allowable amount.
 *
 * The character field holds the account's character usage. The anyCharacterLimitReached() function
 * checks the account's and this API key's character limits.
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
     * @return bool True if the account's character limit or this API key's character limit is reached;
     * the speech minutes have their own details.
     */
    public function anyCharacterLimitReached(): bool
    {
        return ($this->character !== null && $this->character->limitReached()) ||
            ($this->apiKeyCharacter !== null && $this->apiKeyCharacter->limitReached());
    }

    public function __toString(): string
    {
        $list = [
            'Characters' => $this->character,
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
        $json = ParsingUtils::decodeJson($content);

        $this->character = $this->buildUsageDetail('character', $json);
        $this->apiKeyCharacter = $this->buildUsageDetail('api_key_character', $json);
        $this->speechToTextMinutes = $this->buildUsageDetail('speech_to_text_minutes', $json);
        $this->speechToSpeechMinutes = $this->buildUsageDetail('speech_to_speech_minutes', $json);
        if (isset($json['products']) && is_array($json['products'])) {
            $this->products = array_map(
                [UsageProduct::class, 'fromJson'],
                array_values(array_filter($json['products'], 'is_array'))
            );
        }
        $this->startTime = ParsingUtils::parseOptionalTimestamp(self::stringOrNull($json, 'start_time'));
        $this->endTime = ParsingUtils::parseOptionalTimestamp(self::stringOrNull($json, 'end_time'));
    }

    private static function stringOrNull(array $json, string $key): ?string
    {
        return isset($json[$key]) && is_string($json[$key]) ? $json[$key] : null;
    }

    private function buildUsageDetail(string $prefix, array $json): ?UsageDetail
    {
        $count = "{$prefix}_count";
        $limit = "{$prefix}_limit";
        if (!isset($json[$count], $json[$limit]) || !is_numeric($json[$count]) || !is_numeric($json[$limit])) {
            return null;
        }
        return new UsageDetail((int)$json[$count], (int)$json[$limit]);
    }
}
