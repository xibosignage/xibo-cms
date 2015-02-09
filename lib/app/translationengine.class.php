<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-14 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

require_once("3rdparty/php-gettext/streams.php");
require_once("3rdparty/php-gettext/gettext.php");
$transEngine = '';
$stream = '';

class TranslationEngine
{
    private static $locale;
    private static $jsLocale;

	/**
	 * Gets and Sets the Locale
     * @param $language string[optional] The Language to Load
	 */
	public static function InitLocale($language = NULL)
	{
        $localeDir	= 'locale';
        $default = ($language == NULL) ? Config::GetSetting('DEFAULT_LANGUAGE') : $language;

        global $transEngine;
        global $stream;

        // Build an array of supported languages
        $supportedLanguages = scandir($localeDir);

        // Try to get the local firstly from _REQUEST (post then get)
        $requestedLanguage = Kit::GetParam('lang', _REQUEST, _WORD, '');
        $foundLanguage = '';

        if ($requestedLanguage != '') {
            // Serve only the requested language
            // Firstly, Sanitize it
            $requestedLanguage = str_replace('-', '_', $requestedLanguage);

            // Check its valid
            if (in_array($requestedLanguage . '.mo', $supportedLanguages)) {
                $foundLanguage = $requestedLanguage;
            }
        }
        else if (Config::GetSetting('DETECT_LANGUAGE') == 1) {
            // Detect the language, try from HTTP accept
            // Parse the language header and build a preference array
            $languagePreferenceArray = TranslationEngine::parseHttpAcceptLanguageHeader();

            if (count($languagePreferenceArray) > 0) {

                // Go through the list until we have a match
                foreach ($languagePreferenceArray as $languagePreference => $preferenceRating) {

                    // We don't ship an en.mo, so fudge in a case where we automatically convert that to en_GB
                    if ($languagePreference == 'en')
                        $languagePreference = 'en_GB';

                    // Sanitize
                    $languagePreference = str_replace('-', '_', $languagePreference);

                    // Check it is valid
                    if (in_array($languagePreference . '.mo', $supportedLanguages)) {
                        $foundLanguage = $languagePreference;
                        break;
                    }
                }
            }
        }

        // Are we still empty, then default language from settings
        if ($foundLanguage == '') {
            // Check the default
            if (!in_array($default . '.mo', $supportedLanguages)) {
                Debug::Info('Resolved language ' . $default . ' not available.');
                return;
            }

            // The default is valid
            $foundLanguage = $default;
        }

        // Debug::LogEntry('audit', 'Creating new file streamer for '. $localeDir . '/' . $foundLanguage . '.mo', 'TranslationEngine', 'InitLocal');
        if (!$stream = new CachedFileReader($localeDir . '/' . $foundLanguage . '.mo')) {
            Debug::Info('Resolved language ' . $foundLanguage . ' not available.');
            $transEngine = false;
            return;
        }

        $transEngine = new gettext_reader($stream);
        self::$locale = $foundLanguage;
        self::$jsLocale = str_replace('_', '-', $foundLanguage);
	}

    /**
     * Get the Locale
     * @param null $characters The number of characters to take from the beginning of the local string
     * @return mixed
     */
    public static function GetLocale($characters = null) {
        return ($characters == null) ? self::$locale : substr(self::$locale, 0, $characters);
    }

    public static function GetJsLocale() {
        return self::$jsLocale;
    }

    /**
     * Parse the HttpAcceptLanguage Header
     * Inspired by: http://www.thefutureoftheweb.com/blog/use-accept-language-header
     * @param null $header
     * @return array Language array where the key is the language identifier and the value is the preference double.
     */
    public static function parseHttpAcceptLanguageHeader($header = null)
    {
        if ($header == null)
            $header = Kit::GetParam('HTTP_ACCEPT_LANGUAGE', $_SERVER, _STRING);

        $languages = array();

        if ($header != '') {
            // break up string into pieces (languages and q factors)
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $header, $langParse);

            if (count($langParse[1])) {
                // create a list like "en" => 0.8
                $languages = array_combine($langParse[1], $langParse[4]);

                // set default to 1 for any without q factor
                foreach ($languages as $lang => $val) {
                    if ($val === '')
                        $languages[$lang] = 1;
                }

                // sort list based on value
                arsort($languages, SORT_NUMERIC);
            }
        }

        return $languages;
    }
}

/**
 * Global Translation Function
 * @return string
 * @param $string string
 */
function __($string)
{
	global $transEngine;

    if ($transEngine != '')
	   $string = $transEngine->translate($string);

    $args = func_get_args();
    array_shift($args);

    if (count($args) > 0)
        $string = vsprintf($string, $args);

    return $string;
}
?>
