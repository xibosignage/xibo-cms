<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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

class Debug
{

	public function __construct()
	{
		global $db;
		
		if (!defined('AUDIT'))
		{
			// Get the setting from the DB and define it
			if (Config::GetSetting($db, 'audit') != 'On')
			{
				define('AUDIT', false);
			}
			else
			{
				define('AUDIT', true);
			}
		}
	}
	
	public function ErrorHandler($errno, $errmsg, $filename, $linenum, $vars) {

		global $db;

		// timestamp for the error entry
		$dt = date("Y-m-d H:i:s (T)");

		// define an assoc array of error string
		// in reality the only entries we should
		// consider are E_WARNING, E_NOTICE, E_USER_ERROR,
		// E_USER_WARNING and E_USER_NOTICE
		$errortype = array(E_ERROR => 'Error', E_WARNING => 'Warning', E_PARSE =>
				'Parsing Error', E_NOTICE => 'Notice', E_CORE_ERROR => 'Core Error',
				E_CORE_WARNING => 'Core Warning', E_COMPILE_ERROR => 'Compile Error',
				E_COMPILE_WARNING => 'Compile Warning', E_USER_ERROR => 'User Error',
				E_USER_WARNING => 'User Warning', E_USER_NOTICE => 'User Notice', E_STRICT =>
				'Runtime Notice', );

		// set of errors for which a var trace will be saved
		$user_errors_halt = array(E_USER_ERROR);
		$user_errors_inline = array(E_USER_WARNING, E_USER_ERROR);

		$err = "<errormsg>" . $errmsg . "</errormsg>\n";
		$err .= "<errornum>" . $errno . "</errornum>\n";
		$err .= "<errortype>" . $errortype[$errno] . "</errortype>\n";
		$err .= "<scriptname>" . $filename . "</scriptname>\n";
		$err .= "<scriptlinenum>" . $linenum . "</scriptlinenum>\n";

		
		//If debug is enabled OR we get an error before we get to debug
		if (error_reporting() != 0) 
		{
			//Log everything
			Debug::LogEntry($db, "error", $err);
			
			if (in_array($errno, $user_errors_halt)) 
			{
				$this->DisplayError($errmsg, true);
				$this->MailError($errmsg, $err);
				die();
			}
			//else if we have an inline error
			elseif (in_array($errno, $user_errors_inline)) 
			{
				$this->DisplayError($errmsg, false);
			}
		}
		else //Debug OFF (i.e. production)
		{
			//Log everything but never display
			Debug::LogEntry("error", $err);
			
			if (in_array($errno, $user_errors_inline)) 
			{
				// Mail fatal errors
				$this->MailError($errmsg, $err);
			}
		}
		return true;
	}
	
	function MailError($errmsg, $err) {
		global $db;
		
		return true;

		$to = 'info@xibo.org.uk';
		
		$from = config::getSetting($db, "mail_from");
		if ($from == "") return true;
		
		$subject = "Error message from Xibo";
		$message = wordwrap("$errmsg\n$err");

		$headers = "From: $from" . "\r\n" . "Reply-To: $from" . "\r\n" .
				"X-Mailer: PHP/" . phpversion();

		if (!mail($to, $subject, $message, $headers)) trigger_error("Mail not accepted", E_USER_NOTICE);
		return true;
	}

	//Displays an error message to the client
	function DisplayError($errorMessage, $show_back = true) 
	{ 
		echo "<div class=\"error\">".htmlentities($errorMessage)."</div>";

		return true;
	}
	
	static function LogEntry(database $db, $type, $message, $page = "", $function = "", $logdate = "", $displayid = 0, $scheduleID = 0, $layoutid = 0, $mediaid = 0) 
	{
		if ($type == 'audit' && !AUDIT)
		{
			return;
		}

		$currentdate 		= date("Y-m-d H:i:s");
		$requestUri			= Kit::GetParam('REQUEST_URI', $_SERVER, _STRING, 'Not Supplied');
		$requestIp			= Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING, 'Not Supplied');
		$requestUserAgent 	= Kit::GetParam('HTTP_USER_AGENT', $_SERVER, _STRING, 'Not Supplied');
		$userid 			= Kit::GetParam('userid', _SESSION, _INT, 0);
		$message			= Kit::ValidateParam($message, _HTMLSTRING);
		
		if ($logdate == "") $logdate = $currentdate;

		//Prepare the variables
		if ($page == "")
		{
			$page = Kit::GetParam('p', _GET, _WORD);
		}

		$SQL = "INSERT INTO log (logdate, type, page, function, message, RequestUri, RemoteAddr, UserAgent, UserID, displayID, scheduleID, layoutID, mediaID) ";
		$SQL .= sprintf("VALUES ('$logdate','$type', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, %d, %d, %d)", 
					$db->escape_string($page),
					$db->escape_string($function), 
					$db->escape_string($message),
					$db->escape_string($requestUri), 
					$db->escape_string($requestIp), 
					$db->escape_string($requestUserAgent), 
					$userid, $displayid, $scheduleID, $layoutid, $mediaid);

		if (!$db->query($SQL)) 
		{
			// Log the original message
			error_log($message . "\n\n", 3, "./err_log.xml");

			// Log the log failure
			$message = $db->error();
			error_log($message . "\n\n", 3, "./err_log.xml");
		}

		return true;
	}
}
?>