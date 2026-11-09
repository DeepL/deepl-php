<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Options that can be specified when translating documents.
 * @see DeepLClient::translateDocument()
 * @see DeepLClient::translateDocumentUpload()
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

    /** Set to a string containing the file name sent with the uploaded document, for example 'report.docx'.
     *  The API uses its extension to detect the document format. If unspecified, the base name of the input file
     *  path is sent.
     */
    public const FILENAME = 'filename';

    /** Maximum time in seconds (float) that translateDocument() waits for the translation to complete after the
     *  upload. When it expires, translateDocument() throws a DocumentTranslationException carrying the document
     *  handle, so the translation can be resumed. If unspecified, it waits without limit. Zero or negative: the
     *  status is requested once and the wait times out if the translation is not done. Not sent to the API.
     *  Also accepted by DeepLClient::translateDocumentWaitUntilDone(), which accepts no other option.
     */
    public const TIMEOUT = 'timeout';

    /** Set to a string containing the file extension of the desired format of the translated file,
     *  for example 'docx'. If unspecified, the translated file is in the same format as the input file.
     */
    public const OUTPUT_FORMAT = 'output_format';

    /** Set to `true` to add a "Translated by DeepL" watermark to the translated document.
     *  Only supported for docx and pdf output; the API ignores it for other output formats.
     */
    public const ENABLE_WATERMARK = 'enable_watermark';

    /** Set to string containing a style rule ID to use the style rule for translation.
     *  Can also be set to a StyleRuleInfo as returned by getAllStyleRules.
     *  @see \DeepL\DeepLClient::getAllStyleRules()
     */
    public const STYLE_RULE = 'style_rule';

    /** Set to string containing a style profile ID to use the style profile for translation: its
     *  glossaries, style rule lists and translation memories through one ID.
     *  Can also be set to a StyleProfile as returned by getStyleProfile or listStyleProfiles.
     *  @see \DeepL\DeepLClient::listStyleProfiles()
     */
    public const STYLE_PROFILE = 'style_profile';

    /** Set to string containing a translation memory ID to use the translation memory for translation.
     *  Can also be set to a TranslationMemoryInfo as returned by listTranslationMemories.
     *  @see \DeepL\DeepLClient::listTranslationMemories()
     */
    public const TRANSLATION_MEMORY = 'translation_memory';

    /** Set to an integer between 0 and 100 to control the minimum matching percentage
     *  for translation memory matches. Requires TRANSLATION_MEMORY. We recommend a minimum threshold of 75%.
     */
    public const TRANSLATION_MEMORY_THRESHOLD = 'translation_memory_threshold';

    /** Dictionary of extra parameters to pass in the body of the HTTP request.
     * Can be used to access beta features, override built-in parameters, or for testing purposes.
     * Keys in this array will be added to the request body and can override existing keys.
     * The request is a multipart form: booleans are sent as "true"/"false", arrays and objects as their JSON
     * encoding, and other values as strings. A null value removes the field, including one set by the library.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
