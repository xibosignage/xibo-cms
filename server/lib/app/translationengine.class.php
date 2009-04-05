<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
class TranslationEngine
{	
	/**
	 * Gets and Sets the Local 
	 * @return 
	 */
	public static function InitLocale(database $db)
	{
		$domain		= 'default';
		$encoding	= 'UTF8';
		$config 	= new Config($db);
		
		Debug::LogEntry($db, 'audit', 'IN', 'TranslationEngine', 'InitLocal');
		
		if (!$config->CheckGettext())
		{
			define('GETTEXT', false);
			trigger_error("Unable to load translations");
		}
		else
		{
			define('GETTEXT', true);
			
			// Setup the domain to use the default
			bindtextdomain($domain, "locale");
			textdomain($domain);
			
			// Try to get the local firstly from _REQUEST (post then get)
			$lang = Kit::GetParam('lang', _REQUEST, _WORD, '');
			
			if ($lang != '')
			{
				// Set the language and exit
				Debug::LogEntry($db, 'audit', 'Obtained the Language from REQUEST [' . $lang . ']', 'TranslationEngine', 'InitLocal');
			}
			else
			{
				$langs = Kit::GetParam('HTTP_ACCEPT_LANGUAGE', $_SERVER, _STRING);
				
				if ($langs != '') 
				{
					$langs = explode(',', $langs);
					
					$lang = $langs[0];
					
					Debug::LogEntry($db, 'audit', 'Obtained the Language from HTTP_ACCEPT_LANGUAGE [' . $lang . ']', 'TranslationEngine', 'InitLocal');
				}
			}
			
			$lang = 'fr';
			
			// For windows
			putenv('LANG='.$lang.'.'.$encoding);
			putenv('LANGUAGE='.$lang.'.'.$encoding); 
			putenv('LC_ALL='.$lang.'.'.$encoding); 
			
			// Set local
			setlocale(LC_ALL, $lang.'.'.$encoding);
			
			Debug::LogEntry($db, 'audit', 'Setting Local to: ' . $lang . '.' . $encoding, 'TranslationEngine', 'InitLocal');
			Debug::LogEntry($db, 'audit', 'OUT', 'TranslationEngine', 'InitLocal');
		}
	}
}

/**
 * Global Translation Function
 * @return 
 * @param $string Object
 */ 
function __($string)
{
	global $db;
	
	Debug::LogEntry($db, 'audit', 'Translating [' . $string .']', '', '__');
	
	if (GETTEXT)
	{
		Debug::LogEntry($db, 'audit', 'Translated to [' . _($string) .']', '', '__');
		return _($string);
	}
	else
	{
		Debug::LogEntry($db, 'audit', 'No translation Occured', '', '__');
		return $string;
	}
}
?>