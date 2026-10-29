<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Values for the model_type option of text translation, and of TextResult::$modelTypeUsed. The option
 * also accepts other strings, and the result keeps any string the API returns.
 * @see TranslateTextOptions::MODEL_TYPE
 */
class ModelType
{
    /** Use a model optimized for translation quality; fails if the language pair is not supported. */
    public const QUALITY_OPTIMIZED = 'quality_optimized';

    /** Use a quality-optimized model, falling back to latency_optimized if the language pair is not supported. */
    public const PREFER_QUALITY_OPTIMIZED = 'prefer_quality_optimized';

    /** Use a model optimized for translation speed. */
    public const LATENCY_OPTIMIZED = 'latency_optimized';
}
