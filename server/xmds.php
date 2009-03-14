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
 DEFINE('XIBO', true);
 include_once("lib/xmds.inc.php");

/**
 * Auths the hardwareKey
 * @return True is licensed, False if not
 * @param $hardwareKey Object
 */
function Auth($hardwareKey)
{
	global $db;
	
	if (eregi('[^A-Za-z0-9]', $hardwareKey)) return false;
	
	//check in the database for this hardwareKey
	$SQL = "SELECT licensed, inc_schedule, isAuditing, displayID FROM display WHERE license = '$hardwareKey'";
	if (!$result = $db->query($SQL)) 
	{
		trigger_error("License key query failed:" .$db->error());
		return false;
	}
	
	//Is it there?
	if ($db->num_rows($result) == 0) 
	{
		return false;
	}
	else 
	{
		//we have seen this display before, so check the licensed value
		$row = $db->get_row($result);
		if ($row[0] == 0) 
		{
			return false;
		}
		else 
		{
			$time = date("Y-m-d H:i:s", time());
			
			//Set the last accessed flag on the display
			$SQL = "UPDATE display SET lastaccessed = '$time', loggedin = 1 WHERE license = '$hardwareKey' ";
			if (!$result = $db->query($SQL)) 
			{
				trigger_error("Display update access failure: " .$db->error());
			}
			
			//It is licensed
			return array("licensed" => true, "inc_schedule" => $row[1], "isAuditing" => $row[2], "displayid" => $row[3]);
		}
	}
	
	return false;
}

/**
 * Checks that the calling service is talking the correct version
 * @return 
 * @param $version Object
 */
function CheckVersion($version)
{
	global $db;
	
	// Look up the Service XMDS version from the Version table
	$serverVersion = Config::Version($db, 'XmdsVersion');
	
	if ($version != $serverVersion)
	{
		Debug::LogEntry($db, 'audit', sprintf('A Client with an incorrect version connected. Client Version: [%s] Server Version [%s]', $version, $serverVersion));
		return false;
	}
	
	return true;
}

/**
 * Registers the Display with the server - if there is an available slot
 * @return 
 * @param $serverKey Object
 * @param $hardwareKey Object
 * @param $displayName Object
 */
function RegisterDisplay($serverKey, $hardwareKey, $displayName, $version)
{
	global $db;
	
	// Sanitize
	$serverKey 		= Kit::ValidateParam($serverKey, _STRING);
	$hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
	$displayName 	= Kit::ValidateParam($displayName, _STRING);
	$version 		= Kit::ValidateParam($version, _STRING);
	
	// Make sure we are talking the same language
	if (!CheckVersion($version))
	{
		return new soap_fault("SOAP-ENV:Client", "", "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk", $serverKey);
	}
	
	define('SERVER_KEY', Config::GetSetting($db, 'SERVER_KEY'));
	
	Debug::LogEntry($db, "audit", "[IN]", "xmds", "RegisterDisplay");
	Debug::LogEntry($db, "audit", "serverKey [$serverKey], hardwareKey [$hardwareKey], displayName [$displayName]", "xmds", "RegisterDisplay");
	
	//Check the serverKey matches the one we have stored in this servers lic.txt file
	if ($serverKey != SERVER_KEY)
	{
		return new soap_fault("SOAP-ENV:Client", "", "The Server key you entered does not match with the server key at this address", $serverKey);
	}
	
	// Check the Length of the hardwareKey
	if (strlen($hardwareKey) > 40)
	{
		return new soap_fault("SOAP-ENV:Client", "", "The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).", $hardwareKey);
	}
	
	//check in the database for this hardwareKey
	$SQL = "SELECT licensed, display FROM display WHERE license = '$hardwareKey'";
	if (!$result = $db->query($SQL)) 
	{
		trigger_error("License key query failed:" .$db->error());
		return new soap_fault("SOAP-ENV:Server", "", "License Key Query Failed, see server errorlog", $db->error());
	}
	
	//Is it there?
	if ($db->num_rows($result) == 0) 
	{
		//Add this display record
		$SQL = sprintf("INSERT INTO display (display, defaultlayoutid, license, licensed) VALUES ('%s', 1, '%s', 0)", $displayName, $hardwareKey);
		if (!$displayid = $db->insert_query($SQL)) 
		{
			trigger_error($db->error());
			return new soap_fault("SOAP-ENV:Server", "", "Error adding display");
		}
		$active = "Display added and is awaiting licensing approval from an Administrator";
	}
	else 
	{
		//we have seen this display before, so check the licensed value
		$row = $db->get_row($result);
		if ($row[0] == 0) 
		{
			//Its Not licensed
			$active = "Display is awaiting licensing approval from an Administrator.";
		}
		else 
		{
			//It is licensed
			//Now check the names
			if ($row[1] == $displayName)
			{
				$active = "Display is active and ready to start.";
			}
			else
			{
				//Update the name
				$SQL = sprintf("UPDATE display SET display = '%s' WHERE license = '%s' ", $displayName, $hardwareKey);
				
				if (!$db->query($SQL)) 
				{
					trigger_error($db->error());
					return new soap_fault("SOAP-ENV:Server", "", "Error editing the display name");
				}
				
				$active = "Changed display name from '{$row[1]}' to '$displayName' Display is active and ready to start.";
			}
		}
	}
	
	Debug::LogEntry($db, "audit", "$active", "xmds", "RegisterDisplay");	
	Debug::LogEntry($db, "audit", "[OUT]", "xmds", "RegisterDisplay");	
	
	return $active;
}

/**
 * Returns a string containing the required files xml for the requesting display
 * @param string $hardwareKey Display Hardware Key
 * @return string $requiredXml Xml Formatted String
 */
function RequiredFiles($serverKey, $hardwareKey, $version)
{
	global $db;
	
	// Sanitize
	$serverKey 		= Kit::ValidateParam($serverKey, _STRING);
	$hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
	$version 		= Kit::ValidateParam($version, _STRING);
	
	// Make sure we are talking the same language
	if (!CheckVersion($version))
	{
		return new soap_fault("SOAP-ENV:Client", "", "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk", $serverKey);
	}

	$libraryLocation = Config::GetSetting($db, "LIBRARY_LOCATION");
	
	//auth this request...
	if (!$displayInfo = Auth($hardwareKey))
	{
		trigger_error("This display is not licensed [$hardwareKey]");
		return new soap_fault("SOAP-ENV:Client", "", "This display client is not licensed");
	}
	
	if ($displayInfo['isAuditing'] == 1) 
	{
		Debug::LogEntry($db, "audit", "[IN]", "xmds", "RequiredFiles");	
		Debug::LogEntry($db, "audit", "$hardwareKey", "xmds", "RequiredFiles");	
	}
	
	$requiredFilesXml = new DOMDocument("1.0");
	$fileElements = $requiredFilesXml->createElement("files");
	
	$requiredFilesXml->appendChild($fileElements);
	
	$currentdate = date("Y-m-d H:i:s");
	$time = time();
	$plus4hours = date("Y-m-d H:i:s",$time + 86400);
	
	//Add file nodes to the $fileElements
	//Firstly get all the scheduled layouts
	$SQL  = " SELECT layout.layoutID, schedule_detail.starttime, schedule_detail.endtime, layout.xml, layout.background ";
	$SQL .= " FROM layout ";
	$SQL .= " INNER JOIN schedule_detail ON schedule_detail.layoutID = layout.layoutID ";
	$SQL .= " INNER JOIN display ON schedule_detail.displayID = display.displayID ";
	$SQL .= sprintf(" WHERE display.license = '%s'  ", $hardwareKey);
	
	$SQLBase = $SQL;
	
	//Do we include the default display
	if ($displayInfo['inc_schedule'] == 1)
	{
		$SQL .= sprintf(" AND ((schedule_detail.starttime < '%s' AND schedule_detail.endtime > '%s' )", $plus4hours, $currentdate);
		$SQL .= " OR (schedule_detail.starttime = '2050-12-31 00:00:00' AND schedule_detail.endtime = '2050-12-31 00:00:00' ))";
	}
	else
	{
		$SQL .= sprintf(" AND (schedule_detail.starttime < '%s' AND schedule_detail.endtime > '%s' )", $plus4hours, $currentdate);
	}
	
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry($db, "audit", "$SQL", "xmds", "RequiredFiles");	

	if (!$results = $db->query($SQL))
	{
		trigger_error($db->error());
		return new soap_fault("SOAP-ENV:Server", "", "Unable to get a list of files", $db->error());
	}
	
	// Was there anything?
	if ($db->num_rows($results) == 0)
	{
		// No rows, run the query for default layout
		$SQL  = $SQLBase;
		$SQL .= sprintf(" AND ((schedule_detail.starttime < '%s' AND schedule_detail.endtime > '%s' )", $plus4hours, $currentdate);
		$SQL .= " OR (schedule_detail.starttime = '2050-12-31 00:00:00' AND schedule_detail.endtime = '2050-12-31 00:00:00' ))";
		
		if (!$results = $db->query($SQL))
		{
			trigger_error($db->error());
			return new soap_fault("SOAP-ENV:Server", "", "Unable to get A list of layouts for the schedule", $db->error());
		}
	}
	
	while ($row = $db->get_row($results))
	{
		$layoutid = $row[0];
		$layoutXml = $row[3];
		$background = $row[4];
		
		// Add all the associated media first
		$SQL = "SELECT storedAs, media.mediaID 
				FROM media 
				INNER JOIN lklayoutmedia ON lklayoutmedia.mediaID = media.mediaID 
				WHERE storedAs IS NOT NULL 
					AND lklayoutmedia.layoutID = $layoutid
					AND media.mediaID NOT IN (SELECT MediaID 
											  FROM blacklist 
											  WHERE DisplayID = " . $displayInfo['displayid'] . " 
											  AND isIgnored = 0 )";
											  
		if (!$mediaResults = $db->query($SQL))
		{
			trigger_error($db->error());
			return new soap_fault("SOAP-ENV:Server", "", "Unable to get a list of media for the layout [$layoutid]");
		}
		
		while ($row = $db->get_row($mediaResults))
		{
			//Add the file node
			$file = $requiredFilesXml->createElement("file");
			
			$file->setAttribute("type", "media");
			$file->setAttribute("path", $row[0]);
			$file->setAttribute("id",	$row[1]);
			$file->setAttribute("size", filesize($libraryLocation.$row[0]));
			$file->setAttribute("md5", md5_file($libraryLocation.$row[0]));
			
			$fileElements->appendChild($file);
		}
		
		//Also append another file node for the background image (if there is one)
		if ($background != "")
		{
			//firstly add this as a node
			$file = $requiredFilesXml->createElement("file");
			
			$file->setAttribute("type", "media");
			$file->setAttribute("path", $background);
			$file->setAttribute("md5", md5_file($libraryLocation.$background));
			$file->setAttribute("size", filesize($libraryLocation.$background));
			
			$fileElements->appendChild($file);
		}
		
		// Add this layout as node
		$file = $requiredFilesXml->createElement("file");
		
		$file->setAttribute("type", "layout");
		$file->setAttribute("path", $layoutid);
		$file->setAttribute("md5", md5($layoutXml . "\n"));
		
		$fileElements->appendChild($file);
	}
	
	//
	// Add a blacklist node
	//
	$blackList = $requiredFilesXml->createElement("file");
	$blackList->setAttribute("type", "blacklist");
	
	$fileElements->appendChild($blackList);
	
	// Populate
	$SQL = "SELECT MediaID 
			FROM blacklist 
			WHERE DisplayID = " . $displayInfo['displayid'] . " 
			AND isIgnored = 0";
			
	if (!$results = $db->query($SQL))
	{
		trigger_error($db->error());
		return new soap_fault("SOAP-ENV:Server", "", "Unable to get a list of blacklisted files", $db->error());
	}
	
	// Add a black list element for each file
	while ($row = $db->get_row($results))
	{
		$file = $requiredFilesXml->createElement("file");
		$file->setAttribute("id", $row[0]);
		
		$blackList->appendChild($file);
	}

	// PHONE_HOME if required.
	if (Config::GetSetting($db,'PHONE_HOME') == 'On') {
		// Find out when we last PHONED_HOME :D
		// If it's been > 28 days since last PHONE_HOME then
		if (Config::GetSetting($db,'PHONE_HOME_DATE') < (time() - (60 * 60 * 24 * 28))) {

			// Retrieve number of displays
			$SQL = "SELECT COUNT(*)
					FROM `display`
					WHERE `licensed` = '1'";
			if (!$results = $db->query($SQL))
			{
				trigger_error($db->error());
			}
			while ($row = $db->get_row($results))
			{
				$PHONE_HOME_CLIENTS = Kit::ValidateParam($row[0],_INT);
			}
			
			// Retrieve version number
			$PHONE_HOME_VERSION = Config::Version($db, 'app_ver');

			$PHONE_HOME_URL = Config::GetSetting($db,'PHONE_HOME_URL') . "?id=" . urlencode(Config::GetSetting($db,'PHONE_HOME_KEY')) . "&version=" . urlencode($PHONE_HOME_VERSION) . "&numClients=" . urlencode($PHONE_HOME_CLIENTS);

			if ($displayInfo['isAuditing'] == 1) 
			{
				Debug::LogEntry($db, "audit", "PHONE_HOME_URL " . $PHONE_HOME_URL , "xmds", "RequiredFiles");	
			}
		
			@file_get_contents($PHONE_HOME_URL);
			
			// Set PHONE_HOME_TIME to NOW.
			$SQL = "UPDATE `setting`
					SET `value` = '" . time() . "'
					WHERE `setting`.`setting` = 'PHONE_HOME_DATE' LIMIT 1";

			if (!$results = $db->query($SQL))
			{
				trigger_error($db->error());
			}
		//endif
		}
	}
	// END OF PHONE_HOME CODE

	if ($displayInfo['isAuditing'] == 1) 
	{
		Debug::LogEntry($db, "audit", $requiredFilesXml->saveXML(), "xmds", "RequiredFiles");	
		Debug::LogEntry($db, "audit", "[OUT]", "xmds", "RequiredFiles");	
	}
	
	// Return the results of requiredFiles()
	return $requiredFilesXml->saveXML();
}

/**
 * Gets the specified file
 * @return 
 * @param $hardwareKey Object
 * @param $filePath Object
 * @param $fileType Object
 */
function GetFile($serverKey, $hardwareKey, $filePath, $fileType, $chunkOffset, $chunkSize, $version)
{
	global $db;
	
	// Sanitize
	$serverKey 		= Kit::ValidateParam($serverKey, _STRING);
	$hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
	$fileType 		= Kit::ValidateParam($fileType, _WORD);
	$chunkOffset 	= Kit::ValidateParam($chunkOffset, _INT);
	$chunkSize 		= Kit::ValidateParam($chunkSize, _INT);
	$version 		= Kit::ValidateParam($version, _STRING);
	
	$libraryLocation = Config::GetSetting($db, "LIBRARY_LOCATION");
	
	// Make sure we are talking the same language
	if (!CheckVersion($version))
	{
		return new soap_fault("SOAP-ENV:Client", "", "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk", $serverKey);
	}
	
	//auth this request...
	if (!$displayInfo = Auth($hardwareKey))
	{
		return new soap_fault("SOAP-ENV:Client", "", "This display client is not licensed");
	}
	
	if ($displayInfo['isAuditing'] == 1) 
	{
		Debug::LogEntry($db, "audit", "[IN]", "xmds", "GetFile");	
		Debug::LogEntry($db, "audit", "Params: [$hardwareKey] [$filePath] [$fileType] [$chunkOffset] [$chunkSize]", "xmds", "GetFile");	
	}

	if ($fileType == "layout")
	{
		$filePath = Kit::ValidateParam($filePath, _INT);
		
		$SQL = sprintf("SELECT xml FROM layout WHERE layoutid = %d", $filePath);
		if (!$results = $db->query($SQL))
		{
			trigger_error($db->error());
			return new soap_fault("SOAP-ENV:Server", "", "Unable to get a list of files", $db->error());
		}
		
		$row = $db->get_row($results);
		
		$file = $row[0];
	}
	elseif ($fileType == "media")
	{
		$filePath = Kit::ValidateParam($filePath, _STRING);
		
		//Return the Chunk size specified
		$f = fopen($libraryLocation.$filePath,"r");
		
		fseek($f, $chunkOffset);
		
		$file = fread($f, $chunkSize);
	}
	else 
	{
		return new soap_fault("SOAP-ENV:Client", "", "Unknown FileType Requested.");
	}
	
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry($db, "audit", "[OUT]", "xmds", "GetFile");	
	
	return base64_encode($file);
}

/**
 * Returns the schedule for the hardware key specified
 * @return 
 * @param $hardwareKey Object
 */
function Schedule($serverKey, $hardwareKey, $version)
{
	global $db;
	
	// Sanitize
	$serverKey 		= Kit::ValidateParam($serverKey, _STRING);
	$hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
	$version 		= Kit::ValidateParam($version, _STRING);
	
	// Make sure we are talking the same language
	if (!CheckVersion($version))
	{
		return new soap_fault("SOAP-ENV:Client", "", "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk", $serverKey);
	}
	
	//auth this request...
	if (!$displayInfo = Auth($hardwareKey))
	{
		return new soap_fault("SOAP-ENV:Client", "", "This display client is not licensed", $hardwareKey);
	}

	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry($db, "audit", "[IN] $hardwareKey", "xmds", "Schedule");
	
	$scheduleXml = new DOMDocument("1.0");
	$layoutElements = $scheduleXml->createElement("schedule");
	
	$scheduleXml->appendChild($layoutElements);
	
	$currentdate = date("Y-m-d H:i:s");
	$time = time();
	$plus4hours = date("Y-m-d H:i:s",$time + 86400);
	
	//Add file nodes to the $fileElements
	//Firstly get all the scheduled layouts
	$SQL  = " SELECT layout.layoutID, schedule_detail.starttime, schedule_detail.endtime, schedule_detail.eventID ";
	$SQL .= " FROM layout ";
	$SQL .= " INNER JOIN schedule_detail ON schedule_detail.layoutID = layout.layoutID ";
	$SQL .= " INNER JOIN display ON schedule_detail.displayID = display.displayID ";
	$SQL .= " WHERE display.license = '$hardwareKey'  ";
	
	// Store the Base SQL for this display
	$SQLBase = $SQL;
	
	//Do we include the default display
	if ($displayInfo['inc_schedule'] == 1)
	{
		$SQL .= " AND ((schedule_detail.starttime < '$currentdate' AND schedule_detail.endtime > '$currentdate' )";
		$SQL .= " OR (schedule_detail.starttime = '2050-12-31 00:00:00' AND schedule_detail.endtime = '2050-12-31 00:00:00' ))";
	}
	else
	{
		$SQL .= " AND (schedule_detail.starttime < '$currentdate' AND schedule_detail.endtime > '$currentdate' )";
	}
	
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry($db, "audit", "$SQL", "xmds", "Schedule");

	// Run the query
	if (!$results = $db->query($SQL))
	{
		trigger_error($db->error());
		return new soap_fault("SOAP-ENV:Server", "", "Unable to get A list of layouts for the schedule", $db->error());
	}
	
	// Was there anything?
	if ($db->num_rows($results) == 0)
	{
		// No rows, run the query for default layout
		$SQL  = $SQLBase;
		$SQL .= " AND ((schedule_detail.starttime < '$currentdate' AND schedule_detail.endtime > '$currentdate' )";
		$SQL .= " OR (schedule_detail.starttime = '2050-12-31 00:00:00' AND schedule_detail.endtime = '2050-12-31 00:00:00' ))";
		
		if (!$results = $db->query($SQL))
		{
			trigger_error($db->error());
			return new soap_fault("SOAP-ENV:Server", "", "Unable to get A list of layouts for the schedule", $db->error());
		}
	}
	
	while ($row = $db->get_row($results))
	{
		$layoutid 	= $row[0];
		$fromdt 	= $row[1];
		$todt		= $row[2];
		$scheduleid = $row[3];
			
		//firstly add this as a node
		$layout = $scheduleXml->createElement("layout");
		
		$layout->setAttribute("file", $layoutid);
		$layout->setAttribute("fromdt", $fromdt);
		$layout->setAttribute("todt", $todt);
		$layout->setAttribute("scheduleid", $scheduleid);
		
		$layoutElements->appendChild($layout);
	}
	
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry($db, "audit", $scheduleXml->saveXML(), "xmds", "Schedule");
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry($db, "audit", "[OUT]", "xmds", "Schedule");
	
	return $scheduleXml->saveXML();
}

/**
 * Recieves the XmlLog from the display
 * @return 
 * @param $hardwareKey String
 * @param $xml String
 */
function RecieveXmlLog($serverKey, $hardwareKey, $xml, $version)
{
	global $db;
	
	// Sanitize
	$serverKey 		= Kit::ValidateParam($serverKey, _STRING);
	$hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
	$version 		= Kit::ValidateParam($version, _STRING);
	
	// Make sure we are talking the same language
	if (!CheckVersion($version))
	{
		return new soap_fault("SOAP-ENV:Client", "", "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk", $serverKey);
	}

	//auth this request...
	if (!$displayInfo = Auth($hardwareKey))
	{
		return new soap_fault("SOAP-ENV:Client", "", "This display client is not licensed", $hardwareKey);
	}
		
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry ($db, "audit", "[IN]", "xmds", "RecieveXmlLog", "", $displayInfo['displayid']);
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry ($db, "audit", "$xml", "xmds", "RecieveXmlLog", "", $displayInfo['displayid']);
	
	$document = new DOMDocument("1.0");
	$document->loadXML("<log>".$xml."</log>");
	
	foreach ($document->documentElement->childNodes as $node)
	{
		//Zero out the common vars
		$date 		= "";
		$message 	= "";
		$scheduleID = "";
		$layoutID 	= "";
		$mediaID 	= "";
		$type		= "";
			
		// Get the date and the message (all log types have these)
		foreach ($node->childNodes as $nodeElements)
		{			
			if ($nodeElements->nodeName == "date")
			{
				$date = $nodeElements->textContent;
			}
			else if ($nodeElements->nodeName == "message")
			{
				$message = $nodeElements->textContent;
			}
			else if ($nodeElements->nodeName == "scheduleID")
			{
				$scheduleID = $nodeElements->textContent;
			}
			else if ($nodeElements->nodeName == "layoutID")
			{
				$layoutID = $nodeElements->textContent;
			}
			else if ($nodeElements->nodeName == "mediaID")
			{
				$mediaID = $nodeElements->textContent;
			}
			else if ($nodeElements->nodeName == "type")
			{
				$type = $nodeElements->textContent;
			}
		}
		
		switch ($node->nodeName)
		{
			case "Stat":
				StatRecord($type, $date, $scheduleID, $displayInfo['displayid'], $layoutID, $mediaID, $date, $date);
				break;
				
			case "Error":
				Debug::LogEntry($db, "error", $message, "xmds", "RecieveXmlLog", $date, $displayInfo['displayid'], $scheduleID, $layoutID, $mediaID);
				break;
				
			case "Audit":
				Debug::LogEntry($db, "audit", $message, "xmds", "RecieveXmlLog", $date, $displayInfo['displayid'], $scheduleID, $layoutID, $mediaID);
				break;
				
			default:
				Debug::LogEntry($db, "audit", "Unknown entry in client log " . $node->nodeName, "xmds", "RecieveXmlLog", $date, $displayInfo['displayid'], $scheduleID, $layoutID, $mediaID);
				break;
		}
	}

	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry ($db, "audit", "[OUT]", "xmds", "RecieveXmlLog", "", $displayInfo['displayid']);
	
	return true;
}

define('BLACKLIST_ALL', "All");
define('BLACKLIST_SINGLE', "Single");
/**
 * 
 * @return 
 * @param $hardwareKey Object
 * @param $mediaId Object
 * @param $type Object
 */
function BlackList($serverKey, $hardwareKey, $mediaId, $type, $reason, $version)
{
	global $db;
	
	// Sanitize
	$serverKey 		= Kit::ValidateParam($serverKey, _STRING);
	$hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
	$mediaId	 	= Kit::ValidateParam($mediaId, _STRING);
	$type		 	= Kit::ValidateParam($type, _STRING);
	$reason		 	= Kit::ValidateParam($reason, _STRING);
	$version 		= Kit::ValidateParam($version, _STRING);
	
	// Make sure we are talking the same language
	if (!CheckVersion($version))
	{
		return new soap_fault("SOAP-ENV:Client", "", "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk", $serverKey);
	}

	// Auth this request...
	if (!$displayInfo = Auth($hardwareKey))
	{
		return new soap_fault("SOAP-ENV:Client", "", "This display client is not licensed", $hardwareKey);
	}
		
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry ($db, "audit", "[IN]", "xmds", "BlackList", "", $displayInfo['displayid']);
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry ($db, "audit", "$xml", "xmds", "BlackList", "", $displayInfo['displayid']);
		
	// Check to see if this media/display is already blacklisted (and not ignored)
	$SQL = "SELECT BlackListID FROM blacklist WHERE MediaID = $mediaId AND isIgnored = 0 AND DisplayID = " . $displayInfo['displayid'];
	
	if (!$results = $db->query($SQL))
	{
		trigger_error($db->error());
		return new soap_fault("SOAP-ENV:Server", "", "Unable to query for BlackList records.", $db->error());
	}
	
	if ($db->num_rows($results) == 0)
	{
		// Insert the black list record
		// Get all the displays and create a blacklist records
		$SQL = "SELECT displayID FROM display";
		if ($type == BLACKLIST_SINGLE)
		{
			// Only the current display
			$SQL .= " WHERE displayID = " . $displayInfo['displayid'];
		}
		
		if (!$displays = $db->query($SQL))
		{
			trigger_error($db->error());
			return new soap_fault("SOAP-ENV:Server", "", "Unable to query for BlackList Displays.", $db->error());
		}
		
		while ($row = $db->get_row($displays))
		{
			$displayId = $row[0];
			
			$SQL = "INSERT INTO blacklist (MediaID, DisplayID, ReportingDisplayID, Reason)
						VALUES ($mediaId, $displayId, " . $displayInfo['displayid'] . ", '$reason') ";
						
			if (!$db->query($SQL))
			{
				trigger_error($db->error());
				return new soap_fault("SOAP-ENV:Server", "", "Unable to insert BlackList records.", $db->error());
			}
		}
	}
	else
	{
		if ($displayInfo['isAuditing'] == 1) Debug::LogEntry ($db, "audit", "Media Already BlackListed [$mediaId]", "xmds", "BlackList", "", $displayInfo['displayid']);
	}
	
	if ($displayInfo['isAuditing'] == 1) Debug::LogEntry ($db, "audit", "[OUT]", "xmds", "BlackList", "", $displayInfo['displayid']);
	
	return true;
}

//$debug = 1;
$service = new soap_server();

$service->configureWSDL("xmds", "urn:xmds");

$service->register("RegisterDisplay", 
		array('serverKey' => 'xsd:string', 'hardwareKey' => 'xsd:string', 'displayName' => 'xsd:string', 'version' => 'xsd:string'),
		array('ActivationMessage' => 'xsd:string'),
		'urn:xmds',
		'urn:xmds#RegisterDisplay',
		'rpc',
		'encoded',
		'Registered the Display on the Xibo Network'
		);
		
$service->register("RequiredFiles", 
		array('serverKey' => 'xsd:string', 'hardwareKey' => 'xsd:string', 'version' => 'xsd:string'),
		array('RequiredFilesXml' => 'xsd:string'),
		'urn:xmds',
		'urn:xmds#RequiredFiles',
		'rpc',
		'encoded',
		'The files required by the requesting display'
		);
		
$service->register("GetFile", 
		array('serverKey' => 'xsd:string', 'hardwareKey' => 'xsd:string', 'filePath' => 'xsd:string', 'fileType' => 'xsd:string', 'chunkOffset' => 'xsd:int', 'chuckSize' => 'xsd:int', 'version' => 'xsd:string'),
		array('file' => 'xsd:base64Binary'),
		'urn:xmds',
		'urn:xmds#GetFile',
		'rpc',
		'encoded',
		'Gets the file requested'
		);	
		
$service->register("Schedule", 
		array('serverKey' => 'xsd:string', 'hardwareKey' => 'xsd:string', 'version' => 'xsd:string'),
		array('ScheduleXml' => 'xsd:string'),
		'urn:xmds',
		'urn:xmds#Schedule',
		'rpc',
		'encoded',
		'Gets the schedule'
		);	
		
$service->register("RecieveXmlLog",
		array('serverKey' => 'xsd:string', 'hardwareKey' => 'xsd:string', 'xml' => 'xsd:string', 'version' => 'xsd:string'),
		array('success' => 'xsd:boolean'),
		'urn:xmds',
		'urn:xmds#RecieveXmlLog',
		'rpc',
		'encoded',
		'Recieves the Log Xml'
	);

$service->register("BlackList",
		array('serverKey' => 'xsd:string', 'hardwareKey' => 'xsd:string', 'mediaId' => 'xsd:int', 'type' => 'xsd:string', 'reason'=>'xsd:string', 'version' => 'xsd:string'),
		array('success' => 'xsd:boolean'),
		'urn:xmds',
		'urn:xmds#BlackList',
		'rpc',
		'encoded',
		'Set media to be blacklisted'
	);
		
$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
$service->service($HTTP_RAW_POST_DATA);

?>
