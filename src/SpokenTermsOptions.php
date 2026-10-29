<?php

// Copyright 2026 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Option keys for DeepLClient::createSpokenTerms(), DeepLClient::updateSpokenTerms() and
 * DeepLClient::replaceSpokenTermsList(). Options that are not set, or null, are not sent.
 */
class SpokenTermsOptions
{
    /** New name of the spoken terms collection. Only used by updateSpokenTerms(). */
    public const NAME = 'name';

    /**
     * SpokenTermsEntries[] to merge into the term lists of the collection. Only used by
     * updateSpokenTerms().
     */
    public const TERM_LISTS = 'term_lists';

    /**
     * Associative array of additional parameters added to the JSON request body, after the fields
     * set by the library; a key that is already set is replaced. Values are sent as given.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
