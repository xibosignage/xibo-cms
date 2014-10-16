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
	 * Gets and Sets the Local 
	 * @return 
	 */
	public static function InitLocale($language = NULL)
	{
        $localeDir	= 'locale';
        $default = ($language == NULL) ? Config::GetSetting('DEFAULT_LANGUAGE') : $language;
        
        global $transEngine;
        global $stream;

        //Debug::LogEntry('audit', 'IN', 'TranslationEngine', 'InitLocal');
        // Build an array of supported languages
        $supportedLangs = scandir($localeDir);

        // Try to get the local firstly from _REQUEST (post then get)
        $lang = Kit::GetParam('lang', _REQUEST, _WORD, '');

        // If we don't have a language, try from HTTP accept
        if ($lang == '' && Config::GetSetting('DETECT_LANGUAGE') == 1) {
            $langs = Kit::GetParam('HTTP_ACCEPT_LANGUAGE', $_SERVER, _STRING);

            if ($langs != '') {
                //Debug::LogEntry('audit', ' HTTP_ACCEPT_LANGUAGE [' . $langs . ']', 'TranslationEngine', 'InitLocal');
                $langs = explode(',', $langs);

                foreach ($langs as $lang) {
                    // Remove any quality rating (as we aren't interested)
                    $rawLang = explode(';', $lang);
                    $lang = str_replace('-', '_', $rawLang[0]);

                    if (in_array($lang . '.mo', $supportedLangs)) {
                        //Debug::LogEntry('audit', 'Obtained the Language from HTTP_ACCEPT_LANGUAGE [' . $lang . ']', 'TranslationEngine', 'InitLocal');
                        break;
                    }

                    // Set lang as the default
                    $lang = $default;
                }
            }
        }

        // Are we still empty?
        if ($lang == '')
            $lang = $default;

        // Sanitize it
        $lang = str_replace('-', '_', $lang);
        $jsLang = str_replace('_', '-', $lang);

        // Check its valid
        if (!in_array($lang . '.mo', $supportedLangs)) {
            trigger_error(sprintf('Language not supported. %s', $lang));

            // Fall back
            $lang = 'en_GB';
        }

        //Debug::LogEntry('audit', 'Creating new file streamer for '. $localeDir . '/' . $lang . '.mo', 'TranslationEngine', 'InitLocal');
        if (!$stream = new CachedFileReader($localeDir . '/' . $lang . '.mo')) {
            $transEngine = false;
            return;
        }

        $transEngine = new gettext_reader($stream);
        self::$locale = $lang;
        self::$jsLocale = str_replace('_', '-', $lang);
	}

    public static function GetLocale() {
        return self::$locale;
    }

    public static function GetJsLocale() {
        return self::$jsLocale;
    }
}

/**
 * Global Translation Function
 * @return 
 * @param $string Object
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
