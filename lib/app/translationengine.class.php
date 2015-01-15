<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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
	/**
	 * Gets and Sets the Local 
	 * @return 
	 */
	public static function InitLocale()
	{
            $localeDir	= 'locale';
            $default    = Config::GetSetting('DEFAULT_LANGUAGE');
            
            global $transEngine;
            global $stream;

            //Debug::LogEntry('audit', 'IN', 'TranslationEngine', 'InitLocal');

            // Try to get the local firstly from _REQUEST (post then get)
            $lang = Kit::GetParam('lang', _REQUEST, _WORD, '');

            // Build an array of supported languages
            $supportedLangs = scandir($localeDir);

            if ($lang != '')
            {
                // Set the language
                Debug::LogEntry('audit', 'Set the Language from REQUEST [' . $lang . ']', 'TranslationEngine', 'InitLocal');

                // Is this language supported?
                // if not just use the default (eb_GB).
                if (!in_array($lang . '.mo', $supportedLangs))
                {
                    trigger_error(sprintf('Language not supported. %s', $lang));

                    // Use the default language instead.
                    $lang = $default;
                }
            }
            else
            {
                $langs = Kit::GetParam('HTTP_ACCEPT_LANGUAGE', $_SERVER, _STRING);

                if ($langs != '')
                {
                    //Debug::LogEntry('audit', ' HTTP_ACCEPT_LANGUAGE [' . $langs . ']', 'TranslationEngine', 'InitLocal');
                    $langs = explode(',', $langs);

                    foreach ($langs as $lang)
                    {
                        // Remove any quality rating (as we aren't interested)
                        $rawLang = explode(';', $lang);
                        $lang = str_replace("-", "_", $rawLang[0]);

                        if (in_array($lang . '.mo', $supportedLangs))
                        {
                            //Debug::LogEntry('audit', 'Obtained the Language from HTTP_ACCEPT_LANGUAGE [' . $lang . ']', 'TranslationEngine', 'InitLocal');
                            break;
                        }

                        // Set lang as the default
                        $lang = $default;
                    }
                }
                else
                {
                    $lang = $default;
                }
            }

            // We have the language
            //Debug::LogEntry('audit', 'Creating new file streamer for '. $localeDir . '/' . $lang . '.mo', 'TranslationEngine', 'InitLocal');

            if (!$stream = new CachedFileReader($localeDir . '/' . $lang . '.mo'))
            {
                trigger_error('Unable to translate this language');
                $transEngine = false;
                
                return;
            }

            $transEngine    = new gettext_reader($stream);
	}
}

/**
 * Global Translation Function
 * @return 
 * @param $string Object
 */ 
function __($string, $args = null)
{
	global $transEngine;
        
    if ($transEngine != '')
	   $string = $transEngine->translate($string);

    if (count($args) > 0)
        $string = vsprintf($string, $args);

    return $string;
}
?>