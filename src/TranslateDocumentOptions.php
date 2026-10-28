<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Options that can be specified when translating documents.
 * @see Translator::translateDocument()
 * @see Translator::uploadDocument()
 */
final class TranslateDocumentOptions
{
    /** Controls whether translations should lean toward formal or informal language.
     * - 'less': use informal language.
     * - 'more': use formal, more polite language.
     * - 'default': use default formality.
     * - 'prefer_less': use informal language if available, otherwise default.
     * - 'prefer_more': use formal, more polite language if available, otherwise default.
     */
    public const FORMALITY = 'formality';

    /** Set to string containing a glossary ID to use the glossary for translation. */
    public const GLOSSARY = 'glossary';

    /** Set to an array of up to 5 glossary IDs to apply multiple glossaries for translation.
     *  Each element can be a string containing a glossary ID, a GlossaryInfo, or a MultilingualGlossaryInfo.
     *  Requires source_lang to be set, and cannot be combined with the singular GLOSSARY option.
     */
    public const GLOSSARY_IDS = 'glossary_ids';

    /** Set to a string containing the file extension of the desired format of the translated file,
     *  for example 'docx'. If unspecified, the translated file is in the same format as the input file.
     */
    public const OUTPUT_FORMAT = 'output_format';

    /** Set to `true` to add a "Translated by DeepL" watermark to the translated document.
     *  Only supported for docx and pdf output; the API ignores it for other output formats.
     */
    public const ENABLE_WATERMARK = 'enable_watermark';

    /** Set to `true` in order to use Document Minification for translation, if available.
     *  @deprecated The DeepL API accepts larger documents, see
     *  https://developers.deepl.com/docs/resources/usage-limits. Will be removed in the next major version.
     */
    public const ENABLE_DOCUMENT_MINIFICATION = 'enable_document_minification';

    /** Set to string containing a style rule ID to use the style rule for translation.
     *  Can also be set to a StyleRuleInfo as returned by getAllStyleRules.
     *  @see \DeepL\DeepLClient::getAllStyleRules()
     */
    public const STYLE_ID = 'style_id';

    /** Set to string containing a style profile ID to use the style profile for translation: its
     *  glossaries, style rule lists and translation memories through one ID.
     *  Can also be set to a StyleProfile as returned by getStyleProfile or listStyleProfiles.
     *  @see \DeepL\DeepLClient::listStyleProfiles()
     */
    public const STYLE_PROFILE_ID = 'style_profile_id';

    /** Set to string containing a translation memory ID to use the translation memory for translation.
     *  Can also be set to a TranslationMemoryInfo as returned by listTranslationMemories.
     *  @see \DeepL\DeepLClient::listTranslationMemories()
     */
    public const TRANSLATION_MEMORY_ID = 'translation_memory_id';

    /** Set to an integer between 0 and 100 to control the minimum matching percentage
     *  for translation memory matches. We recommend a minimum threshold of 75%.
     */
    public const TRANSLATION_MEMORY_THRESHOLD = 'translation_memory_threshold';

    /** Dictionary of extra parameters to pass in the body of the HTTP request.
     * Can be used to access beta features, override built-in parameters, or for testing purposes.
     * Keys in this array will be added to the request body and can override existing keys.
     * Values must be of string type.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
