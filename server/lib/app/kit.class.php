<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner and James Packer
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
  
define('_SESSION', "session");
define('_POST', "post");
define('_GET', "get");
define('_REQUEST', "request");

define('_STRING', "string");
define('_STRINGSPECIAL', 'stringspecial');
define('_HTMLSTRING', 'htmlstring');
define('_PASSWORD', "password");
define('_INT', "int");
define('_DOUBLE', "double");
define('_BOOL', "bool");
define('_BOOLEAN', "bool");
define('_WORD', "word");
define('_ARRAY', "array");
define('_ARRAY_INT', "array_int");
define('_USERNAME', "username");
define('_CHECKBOX', "checkbox");
define('_FILENAME', "filename");
define('_URI', "uri");
define('_INPUTBOX', "inputbox");
define('_PASSWORDBOX', "password");

class Kit 
{
	// Ends the current execution and issues a redirect - should only be called before headers have been sent (i.e. no output)
	static function Redirect($page, $message = '', $pageIsUrl = false)
	{
		$url 	= $page;
		$ajax 	= Kit::GetParam('ajax', _REQUEST, _BOOL, false);
		
		if ($ajax)
		{
			echo json_encode($page);
			die();
		}
		
		// Header or JS redirect
		if (headers_sent()) 
		{
			echo "<script>document.location.href='$url';</script>\n";
		} 
		else 
		{
			header( 'Location: ' . $url );
		}
		
		die();
	}
	
	/**
	 * Gets the appropriate Param, making sure its valid
	 * Based on code from Joomla! 1.5
	 * @return 
	 * @param $param Object
	 * @param $source Object[optional]
	 * @param $type Object[optional]
	 * @param $default Object[optional]
	 */
	static public function GetParam($param, $source = _POST, $type = _STRING, $default = '', $sanitize = true)
	{
		// lower case param (we dont care)
		$param = strtolower($param);

		if (is_array($source))
		{
			$source = array_change_key_case($source);
			
			if(!isset($source[$param])) 
			{
				$return = $default;
			}
			else 
			{
				$return = $source[$param];	
			}
		}
		else
		{
			switch ($source)
			{
				case 'session':

					if (isset($_SESSION))
						$_tempSESSION = array_change_key_case($_SESSION);
				
					if(!isset($_tempSESSION[$param])) 
					{
						$return = $default;
					}
					else if ($type == _CHECKBOX)
					{
						// this means that it was defined correctly and it was set
						$return = 1;
					}
					else 
					{
						if ($_tempSESSION[$param] == '')
						{
							$return = $default;
						} 
						else
						{
							$return = $_tempSESSION[$param];
						}
					}
				
					break;
				
				case 'request':

					$_tempREQUEST = array_change_key_case($_REQUEST);
				
					if(!isset($_tempREQUEST[$param])) 
					{
						$return = $default;
					}
					else 
					{
						if ($_tempREQUEST[$param] == '')
						{
							$return = $default;
						} 
						else
						{
							$return = $_tempREQUEST[$param];
						}	
					}
				
					break;
					
				case 'get':

					$_tempGET = array_change_key_case($_GET);
				
					if(!isset($_tempGET[$param])) 
					{
						$return = $default;
					}
					else 
					{
						if ($_tempGET[$param] == '')
						{
							$return = $default;
						} 
						else
						{
							$return = $_tempGET[$param];
						}		
					}
				
					break;
					
				case 'post':

					$_tempPOST = array_change_key_case($_POST);
		
					if(!isset($_tempPOST[$param])) 
					{
						$return = $default;
					}
					else if ($type == _CHECKBOX)
					{
						// this means that it was defined correctly and it was set
						$return = 1;
					}
					else 
					{
						if ($_tempPOST[$param] == '')
						{
							$return = $default;
						} 
						else
						{
							$return = $_tempPOST[$param];
						}		
					}
				
					break;
				
				default:
					return $default;
			}
		}
		
		// Validate this param	
		return Kit::ValidateParam($return, $type, $sanitize);
	}
	
	/**
	 * Validates a Parameter
	 * Based on code from Joomla! 1.5
	 * @return 
	 * @param $param Object
	 * @param $type Object
	 */
	static function ValidateParam($param, $type, $sanitize = true)
	{
		// If we are a NULL always return a null??
		//if ($param == NULL || $param == '')
		//	return NULL;

		// Store in return var
		$return = $param;
		
		// Validate
		// Handle the type constraint
		switch ($type)
		{
			case _INT :

				if ($sanitize) {
					// Only use the first integer value
					if (!$return = filter_var($return, FILTER_SANITIZE_NUMBER_INT))
						$return = 0;
				}
				else {
					if (!$return = filter_var($return, FILTER_VALIDATE_INT))
						trigger_error(sprintf(__('No integer match found for [%s] and return value is not an integer'), $param), E_USER_ERROR);
				}

				break;

			case _DOUBLE :
				
				if ($sanitize) {
					// Only use the first integer value
					if (!$return = filter_var($return, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION))
						$return = 0;
				}
				else {
					if (!$return = filter_var($return, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION))
						trigger_error(sprintf(__('No integer match found for %s, and return value is not an integer'), $param), E_USER_ERROR);
				}

				break;

			case _BOOL :
				$return = filter_var($return, FILTER_VALIDATE_BOOLEAN);
				break;

			case _ARRAY :
				if ($return == '')
				{
					$return = array();
					break;	
				}
				
				if (!is_array($return)) 
				{
					$return = array($return);
				}
				break;

			case _ARRAY_INT:

				if ($return == '') {
					$return = array();
				}
				else {
					if ($sanitize) {
						// Only use the first integer value
						if (!$return = filter_var_array($return, FILTER_SANITIZE_NUMBER_INT))
							$return = array();
					}
					else {
						if (!$return = filter_var_array($return, FILTER_VALIDATE_INT))
							trigger_error(sprintf(__('No integer found for %s, and return value is not an integer'), $param), E_USER_ERROR);
					}
				}
				break;

			case _STRING :
			case _PASSWORD :
				$return = filter_var($return, FILTER_SANITIZE_STRING);
				break;

			case _STRINGSPECIAL:
				$return = filter_var($return, FILTER_SANITIZE_SPECIAL_CHARS);
				break;			
				
			case _HTMLSTRING :
				
				// decimal notation
				$return = preg_replace_callback('/&#(\d+);/m', function($m){
				    return chr($m[1]);
				}, $return);

				// convert hex
				$return = preg_replace_callback('/&#x([a-f0-9]+);/mi', function($m){
				    return chr("0x".$m[1]);
				}, $return);
				
				$return = (string) $return;
				break;

			case _WORD :
				$return = filter_var($return, FILTER_SANITIZE_STRING);
				$return = (string) preg_replace( '/[^A-Z_\-]/i', '', $return );
				break;
				
			case _USERNAME :
				$return = filter_var($return, FILTER_SANITIZE_STRING);
				$return = (string) preg_replace( '/[\x00-\x1F\x7F<>"\'%&]/', '', $return );
				$return	= strtolower($return);
				break;
				
			case _FILENAME :
				if ($return == '')
				{
					$return = '';
					break;	
				}
				// Remove non alphanumerics
				$return = strtolower($return); 
				$code_entities_match 	= array('&quot;' ,'!' ,'@' ,'#' ,'$' ,'%' ,'^' ,'&' ,'*' ,'(' ,')' ,'+' ,'{' ,'}' ,'|' ,':' ,'"' ,'<' ,'>' ,'?' ,'[' ,']' ,'' ,';' ,"'" ,',' ,'_' ,'/' ,'*' ,'+' ,'~' ,'`' ,'=' ,' ' ,'---' ,'--','--'); 
				$code_entities_replace 	= array('' ,'-' ,'-' ,'' ,'' ,'' ,'-' ,'-' ,'' ,'' ,'' ,'' ,'' ,'' ,'' ,'-' ,'' ,'' ,'' ,'' ,'' ,'' ,'' ,'' ,'' ,'-' ,'-' ,'-' ,'' ,'' ,'' ,'' ,'' ,'-' ,'-' ,'-','-'); 
	
				$return = str_replace($code_entities_match, $code_entities_replace, $return);
				break;
				
			case _URI :
				if ($return == '')
				{
					$return = '';
					break;	
				}
				$return = urlencode($return);
				break;
				
			case _CHECKBOX:
				if ($return == 'on') $return = 1;
				if ($return == 'off' || $return == '') $return = 0;

			default :
				// No casting necessary
				if (!$sanitize)
					trigger_error(sprintf(__('Unknown Type %s'), $type), E_USER_ERROR);

				break;
		}
		
		return $return;
	}
	
	/**
	 * Gets a formatted Url
	 * @return 
	 * @param $page Object[optional]
	 */
	public static function GetURL($page = "")
	{
		$page = $this->ValidateParam($page, _WORD);
		$fullUrl = 'http';
		
		if(isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')
		{
			$fullUrl .=  's';
		}
		
		$fullUrl .=  '://';
		
		if($_SERVER['SERVER_PORT']!='80')
		{
			$fullUrl .=  $_SERVER['HTTP_HOST'].':'.$_SERVER['SERVER_PORT'].$_SERVER['SCRIPT_NAME'];
		}
		else
		{
			$fullUrl .=  $_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
		}
		
		// Append the page if its not empty
		if ($page != '')
		{
			$fullUrl .= '?p=' . $page;
		}
		
		return $fullUrl;
	}

        /**
         * Ensures a the relevant file for a class is inclued
         * @param <string> $class
         * @return <boolean> False on failure
         */
        static function ClassLoader($class)
	{
            if (class_exists($class))
                return;

            $class = strtolower($class);

            // It doesnt already exist - so lets look in some places to try and find it
            if (file_exists('lib/pages/' . $class . '.class.php'))
            {
                include_once('lib/pages/' . $class . '.class.php');
            }

            if (file_exists('lib/data/' . $class . '.data.class.php'))
            {
                include_once('lib/data/' . $class . '.data.class.php');
            }

            if (file_exists('modules/' . $class . '.module.php'))
            {
                include_once('modules/' . $class . '.module.php');
            }

            if (file_exists('modules/' . $class . '.php'))
            {
                include_once('modules/' . $class . '.php');
            }

            if (file_exists('lib/service/' . $class . '.class.php'))
            {
                include_once('lib/service/' . $class . '.class.php');
            }
	}

    /**
     * GetXiboRoot
     * @return <string> The Root of the Xibo installation
     */
    public static function GetXiboRoot()
    {

        # Check REQUEST_URI is set. IIS doesn't set it so we need to build it
        # Attribution:
        # Code snippet from http://support.ecenica.com/web-hosting/scripting/troubleshooting-scripting-errors/how-to-fix-server-request_uri-php-error-on-windows-iis/
        # Released under BSD License
        # Copyright (c) 2009, Ecenica Limited All rights reserved.
        if (!isset($_SERVER['REQUEST_URI']))
        {
            $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
            if (isset($_SERVER['QUERY_STRING']))
            {
                $_SERVER['REQUEST_URI'].='?'.$_SERVER['QUERY_STRING'];
            }
        }
        ## End Code Snippet

        $request = explode('?', $_SERVER['REQUEST_URI']);

        $fullUrl = 'http';
        
        if(isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')
        {
            $fullUrl .=  's';
        }

        $fullUrl .=  '://';

        if($_SERVER['SERVER_PORT']!='80')
        {
            $fullUrl .=  $_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'];
        }
        else
        {
            $fullUrl .=  $_SERVER['SERVER_NAME'];
        }

        return $fullUrl . $request[0];
    }

    /**
     * Gets the Current page, optionally with arguments.
     */
    public static function GetCurrentPage()
    {

        # Check REQUEST_URI is set. IIS doesn't set it so we need to build it
        # Attribution:
        # Code snippet from http://support.ecenica.com/web-hosting/scripting/troubleshooting-scripting-errors/how-to-fix-server-request_uri-php-error-on-windows-iis/
        # Released under BSD License
        # Copyright (c) 2009, Ecenica Limited All rights reserved.
        if (!isset($_SERVER['REQUEST_URI']))
        {
            $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
            if (isset($_SERVER['QUERY_STRING']))
            {
                $_SERVER['REQUEST_URI'].='?'.$_SERVER['QUERY_STRING'];
            }
        }
        ## End Code Snippet

        $request = explode('?', $_SERVER['REQUEST_URI']);

        if (isset($request[1]))
            return $request[1];

        return '';
    }

    /**
     * Sends an email alert
     */
    public static function SendEmail($to, $from, $subject, $message)
    {
        $headers  = sprintf("From: %s\r\nX-Mailer: php", $from);
        return mail($to, $subject, $message, $headers);
    }

    /**
     * Generate a select list
     * @param string Select list name
     * @param array Array of Values
     * @param string Key for item id
     * @param string Key for item name
     * @param string ID value for selected item
     * @param string Extra attributes to put on the list
     * @param string Key for item class
     * @return string
     */
    public static function SelectList($listName, $listValues, $idColumn, $nameColumn, $selectedId = '', $callBack = '', $classColumn = '')
    {
        $list = '<select name="' . $listName . '" id="' . $listName . '"' . $callBack . '>';

        foreach ($listValues as $listItem)
        {
            $class = ($classColumn == '') ? '' : 'class="' . $listItem[$classColumn] . '"';
            $list .= '<option ' . $class . ' value="' . $listItem[$idColumn] . '" ' . (($listItem[$idColumn] == $selectedId) ? 'selected' : '') . '>' . $listItem[$nameColumn] . '</option>';
        }

        $list .= '</select>';

        return $list;
    }
    
    /**
     * Is a filter form pinned
     * @param type The Page we are on
     * @param type The Filter we are on
     * @return boolean
     */
    public static function IsFilterPinned($page, $filter)
    {
        if (isset($_SESSION[$page][$filter]))
            return ($_SESSION[$page][$filter] == 1);
        
        return false;
    }

    public static function ReturnBytes($val) {
    	
    	$val = trim($val);
	    $last = strtolower($val[strlen($val)-1]);
	    switch($last) {
	        // The 'G' modifier is available since PHP 5.1.0
	        case 'g':
	            $val *= 1024;
	        case 'm':
	            $val *= 1024;
	        case 'k':
	            $val *= 1024;
	    }

	    return $val;
    }

    /**
	* Creates a form token
	* @return 
	*/
	public static function Token($tokenName = "token")
	{
		//Store in the users session
		$token = md5(uniqid()."xsmsalt".time());
		
		$_SESSION[$tokenName] = $token;
		$_SESSION[$tokenName.'_timeout'] = time();
		
		return '<input type="hidden" name="' . $tokenName . '" value="' . $token . '">';
	}

	/**
	 * Checks a form token
	 * @param string token
	 * @return 
	 */
	public static function CheckToken($tokenName = "token")
	{
		if (!isset($_POST[$tokenName]) || !isset($_SESSION[$tokenName]))
			return false;
		
		if ($_POST[$tokenName] == $_SESSION[$tokenName])
		{
			// See if its still in Date
			if (($_SESSION[$tokenName.'_timeout'] + 1200) <= time())
			{
				return false;
			}
			return true;
		}
		else
		{
			unset($_SESSION[$tokenName]);

			Debug::LogEntry('error', "Form token incorrect from: ". $_SERVER['REMOTE_ADDR']. " with token [" . $_POST[$tokenName] . "] for session_id [" . session_id() . ']');
			return false;
		}
	}

	/**
	 * Format Bytes
	 * http://stackoverflow.com/questions/2510434/format-bytes-to-kilobytes-megabytes-gigabytes
	 * @param  [int]  $size   The file size in bytes
	 * @param  integer $precision The precision to go to
	 * @return [string] The Formatted string with suffix
	 */
	public static function formatBytes($size, $precision = 2) {
		$base = log($size) / log(1024);
		$suffixes = array('', 'k', 'M', 'G', 'T');   

		return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
	}

	public static function uniqueId() {
		return uniqid(rand());
	}
}
?>
