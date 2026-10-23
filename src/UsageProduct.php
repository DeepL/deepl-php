<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Usage for one product, as reported in the products field of the usage response.
 */
class UsageProduct
{
    /**
     * @var string The product this usage refers to, for example 'translate' or 'speechToText'.
     */
    public $productType;

    /**
     * @var string The unit the product is billed in, for example 'characters' or 'minutes'.
     */
    public $billingUnit;

    /**
     * @var int The amount used by this API key, expressed in the billing unit.
     */
    public $apiKeyUnitCount;

    /**
     * @var int The amount used by the whole account, expressed in the billing unit.
     */
    public $accountUnitCount;

    /**
     * @var int The characters used by this API key, 0 for products not billed in characters.
     */
    public $apiKeyCharacterCount;

    /**
     * @var int The characters used by the whole account, 0 for products not billed in characters.
     */
    public $characterCount;

    public function __construct(array $json)
    {
        $this->productType = $json['product_type'] ?? '';
        $this->billingUnit = $json['billing_unit'] ?? '';
        $this->apiKeyUnitCount = $json['api_key_unit_count'] ?? 0;
        $this->accountUnitCount = $json['account_unit_count'] ?? 0;
        $this->apiKeyCharacterCount = $json['api_key_character_count'] ?? 0;
        $this->characterCount = $json['character_count'] ?? 0;
    }
}
