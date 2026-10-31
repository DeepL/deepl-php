<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Usage of one product in the current billing period, only returned for API Pro accounts.
 */
class UsageProduct
{
    /**
     * @var string|null The type of product, for example 'translate', 'write', 'speechToText' or
     * 'speechToSpeech'; null if the response does not contain it.
     */
    public $productType;

    /**
     * @var string|null The billing unit for this product, for example 'characters' or 'minutes'; null if the
     * response does not contain it.
     */
    public $billingUnit;

    /**
     * @var int|null Units used for this product by this API key in the current billing period; null if the
     * response does not contain it.
     */
    public $apiKeyUnitCount;

    /**
     * @var int|null Units used for this product by the whole account in the current billing period; null if
     * the response does not contain it.
     */
    public $accountUnitCount;

    public function __construct(
        ?string $productType,
        ?string $billingUnit,
        ?int $apiKeyUnitCount,
        ?int $accountUnitCount
    ) {
        $this->productType = $productType;
        $this->billingUnit = $billingUnit;
        $this->apiKeyUnitCount = $apiKeyUnitCount;
        $this->accountUnitCount = $accountUnitCount;
    }

    /**
     * @param array $json Decoded JSON object of one entry of the products array.
     */
    public static function fromJson(array $json): UsageProduct
    {
        return new UsageProduct(
            isset($json['product_type']) ? (string)$json['product_type'] : null,
            isset($json['billing_unit']) ? (string)$json['billing_unit'] : null,
            isset($json['api_key_unit_count']) ? (int)$json['api_key_unit_count'] : null,
            isset($json['account_unit_count']) ? (int)$json['account_unit_count'] : null
        );
    }
}
