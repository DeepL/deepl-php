<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Language codes for the languages currently supported by DeepL translation. New languages may be added in the future;
 * to retrieve the currently supported languages use the getSourceLanguages() and getSourceLanguages() functions.
 * @see Translator::getSourceLanguages()
 * @see Translator::getTargetLanguages()
 */
class LanguageCode
{
    /** Acehnese language code, may be used as source or target language. */
    public const ACEHNESE = 'ace';

    /** Afrikaans language code, may be used as source or target language. */
    public const AFRIKAANS = 'af';

    /** Aragonese language code, may be used as source or target language. */
    public const ARAGONESE = 'an';

    /** Arabic language code, may be used as source or target language. */
    public const ARABIC = 'ar';

    /** Assamese language code, may be used as source or target language. */
    public const ASSAMESE = 'as';

    /** Aymara language code, may be used as source or target language. */
    public const AYMARA = 'ay';

    /** Azerbaijani language code, may be used as source or target language. */
    public const AZERBAIJANI = 'az';

    /** Bashkir language code, may be used as source or target language. */
    public const BASHKIR = 'ba';

    /** Belarusian language code, may be used as source or target language. */
    public const BELARUSIAN = 'be';

    /** Bulgarian language code, may be used as source or target language. */
    public const BULGARIAN = 'bg';

    /** Bhojpuri language code, may be used as source or target language. */
    public const BHOJPURI = 'bho';

    /** Bengali language code, may be used as source or target language. */
    public const BENGALI = 'bn';

    /** Breton language code, may be used as source or target language. */
    public const BRETON = 'br';

    /** Bosnian language code, may be used as source or target language. */
    public const BOSNIAN = 'bs';

    /** Catalan language code, may be used as source or target language. */
    public const CATALAN = 'ca';

    /** Cebuano language code, may be used as source or target language. */
    public const CEBUANO = 'ceb';

    /** Kurdish (Sorani) language code, may be used as source or target language. */
    public const KURDISH_SORANI = 'ckb';

    /** Czech language code, may be used as source or target language. */
    public const CZECH = 'cs';

    /** Welsh language code, may be used as source or target language. */
    public const WELSH = 'cy';

    /** Danish language code, may be used as source or target language. */
    public const DANISH = 'da';

    /** German language code, may be used as source or target language. */
    public const GERMAN = 'de';

    /** German (Swiss) language code, may only be used as a target language. */
    public const GERMAN_SWISS = 'de-CH';

    /** German (Germany) language code, may only be used as a target language. */
    public const GERMAN_GERMANY = 'de-DE';

    /** Greek language code, may be used as source or target language. */
    public const GREEK = 'el';

    /** English language code, may be used as source or target language; as a target it means American English. */
    public const ENGLISH = 'en';

    /** British English language code, may only be used as a target language. */
    public const ENGLISH_BRITISH = 'en-GB';

    /** American English language code, may only be used as a target language. */
    public const ENGLISH_AMERICAN = 'en-US';

    /** Esperanto language code, may be used as source or target language. */
    public const ESPERANTO = 'eo';

    /** Spanish language code, may be used as source or target language. */
    public const SPANISH = 'es';

    /** Spanish (Spain) language code, may only be used as a target language. */
    public const SPANISH_EUROPEAN = 'es-ES';

    /** Spanish (Latin American) language code, may only be used as a target language. */
    public const SPANISH_LATIN_AMERICAN = 'es-419';

    /** Estonian language code, may be used as source or target language. */
    public const ESTONIAN = 'et';

    /** Basque language code, may be used as source or target language. */
    public const BASQUE = 'eu';

    /** Persian language code, may be used as source or target language. */
    public const PERSIAN = 'fa';

    /** Finnish language code, may be used as source or target language. */
    public const FINNISH = 'fi';

    /** French language code, may be used as source or target language. */
    public const FRENCH = 'fr';

    /** French (Canadian) language code, may only be used as a target language. */
    public const FRENCH_CANADIAN = 'fr-CA';

    /** French (France) language code, may only be used as a target language. */
    public const FRENCH_EUROPEAN = 'fr-FR';

    /** Irish language code, may be used as source or target language. */
    public const IRISH = 'ga';

    /** Galician language code, may be used as source or target language. */
    public const GALICIAN = 'gl';

    /** Guarani language code, may be used as source or target language. */
    public const GUARANI = 'gn';

    /** Konkani language code, may be used as source or target language. */
    public const KONKANI = 'gom';

    /** Gujarati language code, may be used as source or target language. */
    public const GUJARATI = 'gu';

    /** Hausa language code, may be used as source or target language. */
    public const HAUSA = 'ha';

    /** Hebrew language code, may be used as source or target language. */
    public const HEBREW = 'he';

    /** Hindi language code, may be used as source or target language. */
    public const HINDI = 'hi';

    /** Croatian language code, may be used as source or target language. */
    public const CROATIAN = 'hr';

    /** Haitian Creole language code, may be used as source or target language. */
    public const HAITIAN_CREOLE = 'ht';

    /** Hungarian language code, may be used as source or target language. */
    public const HUNGARIAN = 'hu';

    /** Armenian language code, may be used as source or target language. */
    public const ARMENIAN = 'hy';

    /** Indonesian language code, may be used as source or target language. */
    public const INDONESIAN = 'id';

    /** Igbo language code, may be used as source or target language. */
    public const IGBO = 'ig';

    /** Icelandic language code, may be used as source or target language. */
    public const ICELANDIC = 'is';

    /** Italian language code, may be used as source or target language. */
    public const ITALIAN = 'it';

    /** Japanese language code, may be used as source or target language. */
    public const JAPANESE = 'ja';

    /** Javanese language code, may be used as source or target language. */
    public const JAVANESE = 'jv';

    /** Georgian language code, may be used as source or target language. */
    public const GEORGIAN = 'ka';

    /** Kazakh language code, may be used as source or target language. */
    public const KAZAKH = 'kk';

    /** Kurdish (Kurmanji) language code, may be used as source or target language. */
    public const KURDISH_KURMANJI = 'kmr';

    /** Korean language code, may be used as source or target language. */
    public const KOREAN = 'ko';

    /** Kyrgyz language code, may be used as source or target language. */
    public const KYRGYZ = 'ky';

    /** Latin language code, may be used as source or target language. */
    public const LATIN = 'la';

    /** Luxembourgish language code, may be used as source or target language. */
    public const LUXEMBOURGISH = 'lb';

    /** Lombard language code, may be used as source or target language. */
    public const LOMBARD = 'lmo';

    /** Lingala language code, may be used as source or target language. */
    public const LINGALA = 'ln';

    /** Lithuanian language code, may be used as source or target language. */
    public const LITHUANIAN = 'lt';

    /** Latvian language code, may be used as source or target language. */
    public const LATVIAN = 'lv';

    /** Maithili language code, may be used as source or target language. */
    public const MAITHILI = 'mai';

    /** Malagasy language code, may be used as source or target language. */
    public const MALAGASY = 'mg';

    /** Maori language code, may be used as source or target language. */
    public const MAORI = 'mi';

    /** Macedonian language code, may be used as source or target language. */
    public const MACEDONIAN = 'mk';

    /** Malayalam language code, may be used as source or target language. */
    public const MALAYALAM = 'ml';

    /** Mongolian language code, may be used as source or target language. */
    public const MONGOLIAN = 'mn';

    /** Marathi language code, may be used as source or target language. */
    public const MARATHI = 'mr';

    /** Malay language code, may be used as source or target language. */
    public const MALAY = 'ms';

    /** Maltese language code, may be used as source or target language. */
    public const MALTESE = 'mt';

    /** Burmese language code, may be used as source or target language. */
    public const BURMESE = 'my';

    /** Norwegian (bokmål) language code, may be used as source or target language. */
    public const NORWEGIAN = 'nb';

    /** Nepali language code, may be used as source or target language. */
    public const NEPALI = 'ne';

    /** Dutch language code, may be used as source or target language. */
    public const DUTCH = 'nl';

    /** Occitan language code, may be used as source or target language. */
    public const OCCITAN = 'oc';

    /** Oromo language code, may be used as source or target language. */
    public const OROMO = 'om';

    /** Punjabi language code, may be used as source or target language. */
    public const PUNJABI = 'pa';

    /** Pangasinan language code, may be used as source or target language. */
    public const PANGASINAN = 'pag';

    /** Kapampangan language code, may be used as source or target language. */
    public const KAPAMPANGAN = 'pam';

    /** Polish language code, may be used as source or target language. */
    public const POLISH = 'pl';

    /** Dari language code, may be used as source or target language. */
    public const DARI = 'prs';

    /** Pashto language code, may be used as source or target language. */
    public const PASHTO = 'ps';

    /** Portuguese language code, may be used as source or target language; as a target it means European Portuguese. */
    public const PORTUGUESE = 'pt';

    /** Brazilian Portuguese language code, may only be used as a target language. */
    public const PORTUGUESE_BRAZILIAN = 'pt-BR';

    /** European Portuguese language code, may only be used as a target language. */
    public const PORTUGUESE_EUROPEAN = 'pt-PT';

    /** Quechua language code, may be used as source or target language. */
    public const QUECHUA = 'qu';

    /** Romanian language code, may be used as source or target language. */
    public const ROMANIAN = 'ro';

    /** Russian language code, may be used as source or target language. */
    public const RUSSIAN = 'ru';

    /** Sanskrit language code, may be used as source or target language. */
    public const SANSKRIT = 'sa';

    /** Sicilian language code, may be used as source or target language. */
    public const SICILIAN = 'scn';

    /** Slovak language code, may be used as source or target language. */
    public const SLOVAK = 'sk';

    /** Slovenian language code, may be used as source or target language. */
    public const SLOVENIAN = 'sl';

    /** Albanian language code, may be used as source or target language. */
    public const ALBANIAN = 'sq';

    /** Serbian language code, may be used as source or target language. */
    public const SERBIAN = 'sr';

    /** Sesotho language code, may be used as source or target language. */
    public const SESOTHO = 'st';

    /** Sundanese language code, may be used as source or target language. */
    public const SUNDANESE = 'su';

    /** Swedish language code, may be used as source or target language. */
    public const SWEDISH = 'sv';

    /** Swahili language code, may be used as source or target language. */
    public const SWAHILI = 'sw';

    /** Tamil language code, may be used as source or target language. */
    public const TAMIL = 'ta';

    /** Telugu language code, may be used as source or target language. */
    public const TELUGU = 'te';

    /** Tajik language code, may be used as source or target language. */
    public const TAJIK = 'tg';

    /** Thai language code, may be used as source or target language. */
    public const THAI = 'th';

    /** Turkmen language code, may be used as source or target language. */
    public const TURKMEN = 'tk';

    /** Tagalog language code, may be used as source or target language. */
    public const TAGALOG = 'tl';

    /** Tswana language code, may be used as source or target language. */
    public const TSWANA = 'tn';

    /** Turkish language code, may be used as source or target language. */
    public const TURKISH = 'tr';

    /** Tsonga language code, may be used as source or target language. */
    public const TSONGA = 'ts';

    /** Tatar language code, may be used as source or target language. */
    public const TATAR = 'tt';

    /** Ukrainian language code, may be used as source or target language. */
    public const UKRAINIAN = 'uk';

    /** Urdu language code, may be used as source or target language. */
    public const URDU = 'ur';

    /** Uzbek language code, may be used as source or target language. */
    public const UZBEK = 'uz';

    /** Vietnamese language code, may be used as source or target language. */
    public const VIETNAMESE = 'vi';

    /** Wolof language code, may be used as source or target language. */
    public const WOLOF = 'wo';

    /** Xhosa language code, may be used as source or target language. */
    public const XHOSA = 'xh';

    /** Yiddish language code, may be used as source or target language. */
    public const YIDDISH = 'yi';

    /** Cantonese language code, may be used as source or target language. */
    public const CANTONESE = 'yue';

    /** Chinese language code, may be used as source or target language. */
    public const CHINESE = 'zh';

    /** Chinese (simplified) language code, may only be used as a target language. */
    public const CHINESE_SIMPLIFIED = 'zh-HANS';

    /** Chinese (traditional) language code, may only be used as a target language. */
    public const CHINESE_TRADITIONAL = 'zh-HANT';

    /** Zulu language code, may be used as source or target language. */
    public const ZULU = 'zu';

    /**
     * Changes the upper- and lower-casing of the given language code to match ISO 639-1 with an optional regional code
     * from ISO 3166-1.
     * @param string $langCode String containing language code to standardize.
     * @return string String containing the standardized language code.
     * @throws DeepLException If language code is an empty string.
     */
    public static function standardizeLanguageCode(string $langCode): string
    {
        if (strlen($langCode) === 0) {
            throw new DeepLException('langCode must be a non-empty string');
        }

        $exploded = explode('-', $langCode, 2);

        if (isset($exploded[1])) {
            return strtolower($exploded[0]) . '-' . strtoupper($exploded[1]);
        } else {
            return strtolower($exploded[0]);
        }
    }

    /**
     * Removes the regional variant (if any) from a language code, for example inputs 'en' and 'en-US' both return 'en'.
     * @param string $langCode String containing language code to convert.
     * @return string String containing language code without a regional variant.
     * @throws DeepLException
     */
    public static function removeRegionalVariant(string $langCode): string
    {
        if (strlen($langCode) === 0) {
            throw new DeepLException('langCode must be a non-empty string');
        }

        $exploded = explode('-', $langCode, 2);
        return strtolower($exploded[0]);
    }
}
