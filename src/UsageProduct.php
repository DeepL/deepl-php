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
     * @var string The type of product, for example 'translate', 'write', 'speechToText' or 'speechToSpeech'.
     */
    public $productType;

    /**
     * @var string The billing unit for this product, for example 'characters' or 'minutes'.
     */
    public $billingUnit;

    /**
     * @var int Units used for this product by this API key in the current billing period.
     */
    public $apiKeyUnitCount;

    /**
     * @var int Units used for this product by the whole account in the current billing period.
     */
    public $accountUnitCount;

    public function __construct(
        string $productType,
        string $billingUnit,
        int $apiKeyUnitCount,
        int $accountUnitCount
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
            $json['product_type'],
            $json['billing_unit'],
            $json['api_key_unit_count'],
            $json['account_unit_count']
        );
    }
}
