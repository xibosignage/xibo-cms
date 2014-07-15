<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2013 Daniel Garner
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
define('BLACKLIST_ALL', "All");
define('BLACKLIST_SINGLE', "Single");

class XMDSSoap
{
    private $db;

    private $licensed;
    private $includeSchedule;
    private $isAuditing;
    private $displayId;
    private $defaultLayoutId;
    private $version_instructions;

    public function __construct()
    {
        global $db;
        $this->db =& $db;
    }

    /**
     * Registers a new display
     * @param <type> $serverKey
     * @param <type> $hardwareKey
     * @param <type> $displayName
     * @param <type> $version
     * @return <type>
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $version)
    {
	$db =& $this->db;
	define('SERVER_KEY', Config::GetSetting('SERVER_KEY'));

	// Sanitize
	$serverKey 	= Kit::ValidateParam($serverKey, _STRING);
	$hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
	$displayName 	= Kit::ValidateParam($displayName, _STRING);
	$version 	= Kit::ValidateParam($version, _STRING);
        $clientAddress  = Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING);

	// Make sure we are talking the same language
	if (!$this->CheckVersion($version))
            throw new SoapFault('Sender', 'Your client is not of the correct version for communication with this server.');

	Debug::LogEntry("audit", "[IN]", "xmds", "RegisterDisplay");
	Debug::LogEntry("audit", "serverKey [$serverKey], hardwareKey [$hardwareKey], displayName [$displayName]", "xmds", "RegisterDisplay");

	// Check the serverKey matches the one we have stored in this servers lic.txt file
	if ($serverKey != SERVER_KEY)
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

	// Check the Length of the hardwareKey
	if (strlen($hardwareKey) > 40)
            throw new SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

	// Check in the database for this hardwareKey
	$SQL = "SELECT licensed, display, DisplayID FROM display WHERE license = '$hardwareKey'";

	if (!$result = $db->query($SQL))
	{
            trigger_error("License key query failed:" . $db->error());
            throw new SoapFault('Sender', 'Cannot check client key.');
	}

	// Use a display object to Add or Edit the display
	$displayObject = new Display($db);

	// Is it there?
	if ($db->num_rows($result) == 0)
	{
            // Get the default layout id
            $defaultLayoutId = 4;

            // Add this display record
            if (!$displayid = $displayObject->Add($displayName, 0, $defaultLayoutId, $hardwareKey, 0, 0))
                throw new SoapFault('Sender', 'Error adding display');

            $active = 'Display added and is awaiting licensing approval from an Administrator';
	}
	else
	{
            // We have seen this display before, so check the licensed value
            $row = $db->get_row($result);

            $displayid = Kit::ValidateParam($row[2], _INT);

            // Touch the display to update its last accessed and client address.
            $displayObject->Touch($hardwareKey, $clientAddress);

            // Determine if we are licensed or not
            if ($row[0] == 0)
            {
                // It is not licensed
                $active = 'Display is awaiting licensing approval from an Administrator.';
            }
            else
            {
                // It is licensed
                $active = 'Display is active and ready to start.';
            }
	}

        // Log Bandwidth
        $this->LogBandwidth($displayid, 1, strlen($active));

	Debug::LogEntry("audit", "$active", "xmds", "RegisterDisplay");

	return $active;
    }

    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $hardwareKey Display Hardware Key
     * @return string $requiredXml Xml Formatted String
     */
    function RequiredFiles($serverKey, $hardwareKey, $version)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 	= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $version 	= Kit::ValidateParam($version, _STRING);
        $rfLookahead    = Kit::ValidateParam(Config::GetSetting('REQUIRED_FILES_LOOKAHEAD'), _INT);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Sender', 'Your client is not of the correct version for communication with this server.');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");

        // auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Sender', 'This display is not licensed.');

        if ($this->isAuditing == 1)
            Debug::LogEntry("audit", '[IN] with hardware key: ' . $hardwareKey, "xmds", "RequiredFiles");

        $requiredFilesXml = new DOMDocument("1.0");
        $fileElements 	= $requiredFilesXml->createElement("files");
        $fileElements->setAttribute('version_instructions', $this->version_instructions);

        $requiredFilesXml->appendChild($fileElements);

        $currentdate 	= time();
        $rfLookahead 	= $currentdate + $rfLookahead;

        // Get a list of all layout ids in the schedule right now.
        $SQL  = " SELECT DISTINCT layout.layoutID ";
        $SQL .= " FROM `campaign` ";
        $SQL .= "   INNER JOIN schedule_detail ON schedule_detail.CampaignID = campaign.CampaignID ";
        $SQL .= "   INNER JOIN `lkcampaignlayout` ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
        $SQL .= "   INNER JOIN `layout` ON lkcampaignlayout.LayoutID = layout.LayoutID ";
        $SQL .= "   INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
        $SQL .= "   INNER JOIN display ON lkdisplaydg.DisplayID = display.displayID ";
        $SQL .= sprintf(" WHERE display.license = '%s'  ", $hardwareKey);
        $SQL .= sprintf(" AND schedule_detail.FromDT < %d AND schedule_detail.ToDT > %d ", $rfLookahead, $currentdate - 3600);
        $SQL .= "   AND layout.retired = 0  ";

        if ($this->isAuditing == 1)
            Debug::LogEntry("audit", $SQL, "xmds", "RequiredFiles");

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            return new SoapFault('Sender', 'Unable to get a list of layouts');
        }

        // Our layout list will always include the default layout
        $layouts = array();
        $layouts[] = $this->defaultLayoutId;

        // Build up the other layouts into an array
        while ($row = $db->get_assoc_row($results))
            $layouts[] = Kit::ValidateParam($row['layoutID'], _INT);

        // Create a comma separated list to pass into the query which gets file nodes
        $layoutIdList = implode(',', $layouts);

        // Add file nodes to the $fileElements
        $SQL  = " SELECT 'layout' AS RecordType, layout.layoutID AS path, layout.layoutID AS id, MD5(layout.xml) AS `MD5`, NULL AS FileSize, layout.background, layout.xml AS xml ";
        $SQL .= "   FROM layout ";
        $SQL .= sprintf(" WHERE layout.layoutid IN (%s)  ", $layoutIdList);
        $SQL .= " UNION ";
        $SQL .= " SELECT 'media' AS RecordType, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize, NULL AS background, NULL AS xml ";
        $SQL .= "   FROM media ";
        $SQL .= " 	INNER JOIN lklayoutmedia ";
        $SQL .= " 	ON lklayoutmedia.MediaID = media.MediaID ";
        $SQL .= " 	INNER JOIN layout ";
        $SQL .= " 	ON layout.LayoutID = lklayoutmedia.LayoutID";
        $SQL .= sprintf(" WHERE layout.layoutid IN (%s)  ", $layoutIdList);
        $SQL .= "
                UNION
                SELECT 'media' AS RecordType, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize, NULL AS background, NULL AS xml 
                   FROM `media`
                    INNER JOIN `lkmediadisplaygroup`
                    ON lkmediadisplaygroup.mediaid = media.MediaID
                    INNER JOIN lkdisplaydg 
                    ON lkdisplaydg.DisplayGroupID = lkmediadisplaygroup.DisplayGroupID
                    INNER JOIN display 
                    ON lkdisplaydg.DisplayID = display.displayID
                ";
        $SQL .= sprintf(" WHERE display.license = '%s'  ", $hardwareKey);
        $SQL .= " ORDER BY RecordType DESC";

        if ($this->isAuditing == 1) Debug::LogEntry("audit", $SQL, "xmds", "RequiredFiles");

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            return new SoapFault('Sender', 'Unable to get a list of files');
        }

        // Added paths
        $paths = array();

        while ($row = $db->get_assoc_row($results))
        {
            $recordType	= Kit::ValidateParam($row['RecordType'], _WORD);
            $path	= Kit::ValidateParam($row['path'], _STRING);
            $id		= Kit::ValidateParam($row['id'], _STRING);
            $md5	= Kit::ValidateParam($row['MD5'], _HTMLSTRING);
            $fileSize	= Kit::ValidateParam($row['FileSize'], _INT);
            $background	= Kit::ValidateParam($row['background'], _STRING);
            $xml = Kit::ValidateParam($row['xml'], _HTMLSTRING);

            if ($recordType == 'layout')
            {
                // For layouts the MD5 column is the layout xml
                $fileSize 	= strlen($xml);
                
                if ($this->isAuditing == 1) 
                    Debug::LogEntry("audit", 'MD5 for layoutid ' . $id . ' is: [' . $md5 . ']', "xmds", "RequiredFiles");
            }
            else if ($recordType == 'media')
            {
                // If they are empty calculate them and save them back to the media.
                if ($md5 == '' || $fileSize == 0)
                {
                    $md5 	= md5_file($libraryLocation.$path);
                    $fileSize	= filesize($libraryLocation.$path);
                    
                    // Update the media record with this information
                    $SQL = sprintf("UPDATE media SET `MD5` = '%s', FileSize = %d WHERE MediaID = %d", $md5, $fileSize, $id);

                    if (!$db->query($SQL))
                        trigger_error($db->error());
                }
            }
            else
            {
                continue;
            }

            // Add the file node
            $file = $requiredFilesXml->createElement("file");

            $file->setAttribute("type", $recordType);
            $file->setAttribute("path", $path);
            $file->setAttribute("id", $id);
            $file->setAttribute("size", $fileSize);
            $file->setAttribute("md5", $md5);
            
            $fileElements->appendChild($file);

            // Add this to the paths array
            $paths[] = $path;

            // If this is a layout type and there is a background then add the background node
            // TODO: We need to alter the layout table to have a background ID rather than a path
            // TODO: We need to alter the background edit method to create a lklayoutmedia link for
            // background images (and maintain it when they change)
            if ($recordType == 'layout' && $background != '' && !in_array($background, $paths))
            {
                // Also append another file node for the background image (if there is one)
                $file = $requiredFilesXml->createElement("file");
                $file->setAttribute("type", "media");
                $file->setAttribute("path", $background);
                $file->setAttribute("md5", md5_file($libraryLocation.$background));
                $file->setAttribute("size", filesize($libraryLocation.$background));
                $fileElements->appendChild($file);

                // Add this to the paths array
                $paths[] = $background;
            }
        }

        Kit::ClassLoader('layout');

        // Go through each layout and see if we need to supply any resource nodes.
        foreach ($layouts as $layoutId) {
            // Load the layout XML and work out if we have any ticker / text / dataset media items
            $layout = new Layout($db);

            $layoutInformation = $layout->LayoutInformation($layoutId);

            foreach($layoutInformation['regions'] as $region) {
                foreach($region['media'] as $media) {
                    if ($media['mediatype'] == 'ticker' || $media['mediatype'] == 'text' || $media['mediatype'] == 'datasetview' || $media['mediatype'] == 'webpage') {
                        // Append this item to required files
                        $file = $requiredFilesXml->createElement("file");
                        $file->setAttribute('type', 'resource');
                        $file->setAttribute('id', rand());
                        $file->setAttribute('layoutid', $layoutId);
                        $file->setAttribute('regionid', $region['regionid']);
                        $file->setAttribute('mediaid', $media['mediaid']);
                        $file->setAttribute('updated', (isset($media['updated']) ? $media['updated'] : 0));
                        
                        $fileElements->appendChild($file);
                    }
                }
            }
        }

        // Add a blacklist node
        $blackList = $requiredFilesXml->createElement("file");
        $blackList->setAttribute("type", "blacklist");

        $fileElements->appendChild($blackList);

        // Populate
        $SQL = "SELECT MediaID
                FROM blacklist
                WHERE DisplayID = " . $this->displayId . "
                AND isIgnored = 0";

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            return new SoapFault('Sender', 'Unable to get a list of blacklisted files');
        }

        // Add a black list element for each file
        while ($row = $db->get_row($results))
        {
            $file = $requiredFilesXml->createElement("file");
            $file->setAttribute("id", $row[0]);

            $blackList->appendChild($file);
        }

        // Phone Home?
        $this->PhoneHome();

        if ($this->isAuditing == 1)
        {
            Debug::LogEntry("audit", $requiredFilesXml->saveXML(), "xmds", "RequiredFiles");
            Debug::LogEntry("audit", "[OUT]", "xmds", "RequiredFiles");
        }

        // Return the results of requiredFiles()
        $requiredFilesXml->formatOutput = true;
        $output = $requiredFilesXml->saveXML();

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, 2, strlen($output));

        return $output;
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
        $db =& $this->db;

        // Sanitize
        $serverKey 	= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $fileType 	= Kit::ValidateParam($fileType, _WORD);
        $chunkOffset 	= Kit::ValidateParam($chunkOffset, _INT);
        $chunkSize 	= Kit::ValidateParam($chunkSize, _INT);
        $version 	= Kit::ValidateParam($version, _STRING);

        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");
        }

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        //auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
            throw new SoapFault('Receiver', "This display client is not licensed");
        }

        if ($this->isAuditing == 1)
        {
            Debug::LogEntry("audit", "[IN]", "xmds", "GetFile");
            Debug::LogEntry("audit", "Params: [$hardwareKey] [$filePath] [$fileType] [$chunkOffset] [$chunkSize]", "xmds", "GetFile");
        }

        if ($fileType == "layout")
        {
            $filePath = Kit::ValidateParam($filePath, _INT);

            $SQL = sprintf("SELECT xml FROM layout WHERE layoutid = %d", $filePath);
            if (!$results = $db->query($SQL))
            {
                trigger_error($db->error());
                return new SoapFault('Receiver', 'Unable the find layout.');
            }

            $row = $db->get_row($results);
            $file = $row[0];
            
            // Store file size for bandwidth log
            $chunkSize = strlen($file);
        }
        elseif ($fileType == "media")
        {
            $filePath = Kit::ValidateParam($filePath, _STRING);

            if (strstr($filePath, '/') || strstr($filePath, '\\'))
            {
                throw new SoapFault('Receiver', "Invalid file path.");
            }

            // Return the Chunk size specified
            $f = fopen($libraryLocation.$filePath,"r");

            fseek($f, $chunkOffset);

            $file = fread($f, $chunkSize);
        }
        else
        {
            throw new SoapFault('Receiver', 'Unknown FileType Requested.');
        }

        if ($this->isAuditing == 1) Debug::LogEntry("audit", "[OUT]", "xmds", "GetFile");

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, 4, $chunkSize);
        
        return $file;
    }

    /**
     * Returns the schedule for the hardware key specified
     * @return
     * @param $hardwareKey Object
     */
    function Schedule($serverKey, $hardwareKey, $version)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 	= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $version 	= Kit::ValidateParam($version, _STRING);
        $sLookahead     = Kit::ValidateParam(Config::GetSetting('REQUIRED_FILES_LOOKAHEAD'), _INT);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Sender', "Your client is not of the correct version for communication with this server.");

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        //auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Sender', "This display client is not licensed");

        if ($this->isAuditing == 1)
            Debug::LogEntry("audit", "[IN] $hardwareKey", "xmds", "Schedule");

        $scheduleXml = new DOMDocument("1.0");
        $layoutElements = $scheduleXml->createElement("schedule");

        $scheduleXml->appendChild($layoutElements);

        $currentdate 	= time();

        if (Config::GetSetting('SCHEDULE_LOOKAHEAD') == 'On')
        {
            $sLookahead = $currentdate + $sLookahead;
        }
        else
        {
            $sLookahead = $currentdate;
        }

        // Add file nodes to the $fileElements
        // Firstly get all the scheduled layouts
        $SQL  = " SELECT layout.layoutID, schedule_detail.FromDT, schedule_detail.ToDT, schedule_detail.eventID, schedule_detail.is_priority, ";
        $SQL .= "  (SELECT GROUP_CONCAT(StoredAs) FROM media INNER JOIN lklayoutmedia ON lklayoutmedia.MediaID = media.MediaID WHERE lklayoutmedia.LayoutID = layout.LayoutID GROUP BY lklayoutmedia.LayoutID) AS Dependents";
        $SQL .= " FROM `campaign` ";
        $SQL .= " INNER JOIN schedule_detail ON schedule_detail.CampaignID = campaign.CampaignID ";
        $SQL .= " INNER JOIN `lkcampaignlayout` ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
        $SQL .= " INNER JOIN `layout` ON lkcampaignlayout.LayoutID = layout.LayoutID ";
        $SQL .= " INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
        $SQL .= " INNER JOIN display ON lkdisplaydg.DisplayID = display.displayID ";
        $SQL .= sprintf(" WHERE display.license = '%s'  ", $hardwareKey);
        $SQL .= sprintf(" AND (schedule_detail.FromDT < %d AND schedule_detail.ToDT > %d )", $sLookahead, $currentdate - 3600);
        $SQL .= "   AND layout.retired = 0  ";
        $SQL .= " ORDER BY schedule_detail.DisplayOrder, lkcampaignlayout.DisplayOrder, schedule_detail.eventID ";

        if ($this->isAuditing == 1)
            Debug::LogEntry("audit", $SQL, "xmds", "Schedule");

        // Run the query
        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            return new SoapFault('Unable to get A list of layouts for the schedule');
        }

        // We must have some results in here by this point
        while ($row = $db->get_row($results))
        {
            $layoutid       = $row[0];
            $fromdt         = date('Y-m-d H:i:s', $row[1]);
            $todt           = date('Y-m-d H:i:s', $row[2]);
            $scheduleid     = $row[3];
            $is_priority    = Kit::ValidateParam($row[4], _INT);
            $dependents = Kit::ValidateParam($row[5], _STRING);

            // Add a layout node to the schedule
            $layout = $scheduleXml->createElement("layout");

            $layout->setAttribute("file", $layoutid);
            $layout->setAttribute("fromdt", $fromdt);
            $layout->setAttribute("todt", $todt);
            $layout->setAttribute("scheduleid", $scheduleid);
            $layout->setAttribute("priority", $is_priority);
            $layout->setAttribute("dependents", $dependents);

            $layoutElements->appendChild($layout);
        }

        // Are we interleaving the default?
        if ($this->includeSchedule == 1)
        {
            // Add as a node at the end of the schedule.
            $layout = $scheduleXml->createElement("layout");

            $layout->setAttribute("file", $this->defaultLayoutId);
            $layout->setAttribute("fromdt", '2000-01-01 00:00:00');
            $layout->setAttribute("todt", '2030-01-19 00:00:00');
            $layout->setAttribute("scheduleid", 0);
            $layout->setAttribute("priority", 0);

            $layoutElements->appendChild($layout);
        }

        // Add on the default layout node
        $default = $scheduleXml->createElement("default");
        $default->setAttribute("file", $this->defaultLayoutId);
        $layoutElements->appendChild($default);

        // Format the output
        $scheduleXml->formatOutput = true;
        
        if ($this->isAuditing == 1)
            Debug::LogEntry("audit", $scheduleXml->saveXML(), "xmds", "Schedule");

        $output = $scheduleXml->saveXML();

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, 3, strlen($output));

        return $output;
    }

    /**
     *
     * @return
     * @param $hardwareKey Object
     * @param $mediaId Object
     * @param $type Object
     */
    function BlackList($serverKey, $hardwareKey, $mediaId, $type, $reason, $version)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 		= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $mediaId	 	= Kit::ValidateParam($mediaId, _STRING);
        $type		 	= Kit::ValidateParam($type, _STRING);
        $reason		 	= Kit::ValidateParam($reason, _STRING);
        $version 		= Kit::ValidateParam($version, _STRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
                throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.", $serverKey);
        }

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
                throw new SoapFault('Receiver', "This display client is not licensed", $hardwareKey);
        }

        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "[IN]", "xmds", "BlackList", "", $this->displayId);
        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "$xml", "xmds", "BlackList", "", $this->displayId);

        // Check to see if this media/display is already blacklisted (and not ignored)
        $SQL = "SELECT BlackListID FROM blacklist WHERE MediaID = $mediaId AND isIgnored = 0 AND DisplayID = " . $this->displayId;

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            return new SoapFault("Unable to query for BlackList records.");
        }

        if ($db->num_rows($results) == 0)
        {
            // Insert the black list record
            $SQL = "SELECT displayID FROM display";

            if ($type == BLACKLIST_SINGLE)
                $SQL .= " WHERE displayID = " . $this->displayId;

            if (!$displays = $db->query($SQL))
            {
                trigger_error($db->error());
                return new SoapFault("Unable to query for BlackList Displays.");
            }

            while ($row = $db->get_row($displays))
            {
                $displayId = $row[0];

                $SQL = "INSERT INTO blacklist (MediaID, DisplayID, ReportingDisplayID, Reason)
                            VALUES ($mediaId, $displayId, " . $this->displayId . ", '$reason') ";

                if (!$db->query($SQL))
                {
                    trigger_error($db->error());
                    return new SoapFault("Unable to insert BlackList records.");
                }
            }
        }
        else
        {
            if ($this->isAuditing == 1) Debug::LogEntry( "audit", "Media Already BlackListed [$mediaId]", "xmds", "BlackList", "", $this->displayId);
        }

        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "[OUT]", "xmds", "BlackList", "", $this->displayId);

        return true;
    }

    /**
     * Submit client logging
     * @return
     * @param $version Object
     * @param $serverKey Object
     * @param $hardwareKey Object
     * @param $logXml Object
     */
    function SubmitLog($version, $serverKey, $hardwareKey, $logXml)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 	= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $version 	= Kit::ValidateParam($version, _STRING);
        $logXml 	= Kit::ValidateParam($logXml, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
            throw new SoapFault('Sender', "Your client is not of the correct version for communication with this server.");
        }

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
            throw new SoapFault('Sender', 'This display client is not licensed.');
        }

        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "IN", "xmds", "SubmitLog", "", $this->displayId);
        if ($this->isAuditing == 1) Debug::LogEntry( "audit", 'XML [' . $logXml . ']', "xmds", "SubmitLog", "", $this->displayId);

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");

        if (!$document->loadXML($logXml))
        {
            throw new SoapFault('Receiver', "XML Cannot be loaded into DOM Document.");
        }

        foreach ($document->documentElement->childNodes as $node)
        {
            // Make sure we dont consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) continue;

            //Zero out the common vars
            $date 		= "";
            $message 	= "";
            $scheduleID = "";
            $layoutID 	= "";
            $mediaID 	= "";
            $cat		= '';
            $method		= '';
            $thread = '';

            // This will be a bunch of trace nodes
            $message = $node->textContent;

            if ($this->isAuditing == 1) 
                Debug::LogEntry( "audit", 'Trace Message: [' . $message . ']', "xmds", "SubmitLog", "", $this->displayId);

            // Each element should have a category and a date
            $date	= $node->getAttribute('date');
            $cat	= strtolower($node->getAttribute('category'));

            if ($date == '' || $cat == '')
            {
                trigger_error('Log submitted without a date or category attribute');
                continue;
            }

            // Get the date and the message (all log types have these)
            foreach ($node->childNodes as $nodeElements)
            {
                if ($nodeElements->nodeName == "scheduleID")
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
                else if ($nodeElements->nodeName == "method")
                {
                    $method	= $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "message")
                {
                    $message = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "thread")
                {
                    if ($nodeElements->textContent != '')
                        $thread = '[' . $nodeElements->textContent . '] ';
                }
            }

            // If the message is still empty, take the entire node content
            if ($message == '')
                $message = $node->textContent;

            // We should have enough information to log this now.
            $logType = ($cat == 'error') ? 'error' : 'audit';
            
            Debug::LogEntry($logType, $message, 'Client', $thread . $method, $date, $this->displayId, $scheduleID, $layoutID, $mediaID);
        }

        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "OUT", "xmds", "SubmitLog", "", $this->displayId);

        return true;
    }

    /**
     * Submit display statistics to the server
     * @return
     * @param $version Object
     * @param $serverKey Object
     * @param $hardwareKey Object
     * @param $statXml Object
     */
    function SubmitStats($version, $serverKey, $hardwareKey, $statXml)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 		= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $version 		= Kit::ValidateParam($version, _STRING);
        $statXml 		= Kit::ValidateParam($statXml, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");
        }

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
            throw new SoapFault('Receiver', "This display client is not licensed");
        }

        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "IN", "xmds", "SubmitStats", "", $this->displayId);
        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "StatXml: [" . $statXml . "]", "xmds", "SubmitStats", "", $this->displayId);

        if ($statXml == "")
        {
            throw new SoapFault('Receiver', "Stat XML is empty.");
        }

        // Log
        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "About to create Stat Object.", "xmds", "SubmitStats", "", $this->displayId);

        $statObject = new Stat($db);

        // Log
        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "About to Create DOMDocument.", "xmds", "SubmitStats", "", $this->displayId);

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");
        $document->loadXML($statXml);

        foreach ($document->documentElement->childNodes as $node)
        {
            // Make sure we dont consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) continue;

            //Zero out the common vars
            $fromdt	= '';
            $todt	= '';
            $type	= '';

            $scheduleID = 0;
            $layoutID 	= 0;
            $mediaID 	= '';
            $tag	= '';

            // Each element should have these attributes
            $fromdt	= $node->getAttribute('fromdt');
            $todt	= $node->getAttribute('todt');
            $type	= $node->getAttribute('type');

            if ($fromdt == '' || $todt == '' || $type == '')
            {
                trigger_error('Stat submitted without the fromdt, todt or type attributes.');
                continue;
            }

            $scheduleID	= $node->getAttribute('scheduleid');
            $layoutID	= $node->getAttribute('layoutid');
            $mediaID	= $node->getAttribute('mediaid');
            $tag	= $node->getAttribute('tag');

            // Write the stat record with the information we have available to us.
            if (!$statObject->Add($type, $fromdt, $todt, $scheduleID, $this->displayId, $layoutID, $mediaID, $tag))
            {
                trigger_error(sprintf('Stat Add failed with error: %s', $statObject->GetErrorMessage()));
                continue;
            }
        }

        if ($this->isAuditing == 1) Debug::LogEntry( "audit", "OUT", "xmds", "SubmitStats", "", $this->displayId);

        return true;
    }

    /**
     * Store the media inventory for a client
     * @param <type> $hardwareKey
     * @param <type> $inventory
     */
    public function MediaInventory($version, $serverKey, $hardwareKey, $inventory)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 	= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $version 	= Kit::ValidateParam($version, _STRING);
        $inventory 	= Kit::ValidateParam($inventory, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', 'This display client is not licensed');

        if ($this->isAuditing == 1) Debug::LogEntry( 'audit', $inventory, 'xmds', 'MediaInventory', '', $this->displayId);

        // Check that the $inventory contains something
        if ($inventory == '')
            throw new SoapFault('Receiver', 'Inventory Cannot be Empty');

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");
        $document->loadXML($inventory);

        // Get some information from the media inventory XML and update the display record with it.
        $macAddress = Kit::ValidateParam($document->documentElement->getAttribute('macAddress'), _STRING);
        $clientType = Kit::ValidateParam($document->documentElement->getAttribute('clientType'), _STRING);
        $clientVersion = Kit::ValidateParam($document->documentElement->getAttribute('clientVersion'), _STRING);
        $clientCode = Kit::ValidateParam($document->documentElement->getAttribute('clientCode'), _INT);

        // Assume we are complete (but we are getting some)
        $mediaInventoryComplete = 1;

        $xpath = new DOMXPath($document);
        $fileNodes = $xpath->query("//file");

        foreach ($fileNodes as $node)
        {
            $mediaId = $node->getAttribute('id');
            $complete = $node->getAttribute('complete');
            $md5 = $node->getAttribute('md5');
            $lastChecked = $node->getAttribute('lastChecked');

            // TODO: Check the MD5?

            // If this item is a 0 then set not complete
            if ($complete == 0)
                $mediaInventoryComplete = 2;
        }

        // Touch the display record
        $displayObject = new Display($db);
        $displayObject->Touch($hardwareKey, '', $mediaInventoryComplete, $inventory, $macAddress, $clientType, $clientVersion, $clientCode);

        return true;
    }

    /**
     * Gets additional resources for assigned media
     * @param <type> $serverKey
     * @param <type> $hardwareKey
     * @param <type> $layoutId
     * @param <type> $regionId
     * @param <type> $mediaId
     * @param <type> $version
     */
    function GetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId, $version)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $layoutId = Kit::ValidateParam($layoutId, _INT);
        $regionId = Kit::ValidateParam($regionId, _STRING);
        $mediaId = Kit::ValidateParam($mediaId, _STRING);
        $version = Kit::ValidateParam($version, _STRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', "This display client is not licensed");

        // What type of module is this?
        Kit::ClassLoader('region');
        $region = new region($db);
        $type = $region->GetMediaNodeType($layoutId, $regionId, $mediaId);

        if ($type == '')
            throw new SoapFault('Receiver', 'Unable to get the media node type');

        // Dummy User Object
        $user = new User($db);
        $user->userid = 0;
        $user->usertypeid = 1;

        // Get the resource from the module
        require_once('modules/' . $type . '.module.php');
        
        if (!$module = new $type($db, $user, $mediaId, $layoutId, $regionId))
            throw new SoapFault('Receiver', 'Cannot create module. Check CMS Log');

        $resource = $module->GetResource($this->displayId);

        if (!$resource || $resource == '')
            throw new SoapFault('Receiver', 'Unable to get the media resource');

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, 5, strlen($resource));

        return $resource;
    }

    /**
     * PHONE_HOME if required
     */
    private function PhoneHome() {
        
        if (Config::GetSetting('PHONE_HOME') == 'On')
        {
            // Find out when we last PHONED_HOME :D
            // If it's been > 28 days since last PHONE_HOME then
            if (Config::GetSetting('PHONE_HOME_DATE') < (time() - (60 * 60 * 24 * 28)))
            {
                if ($this->isAuditing == 1)
                {
                    Debug::LogEntry("audit", "PHONE_HOME [IN]", "xmds", "RequiredFiles");
                }

                try {
                    $dbh = PDOConnect::init();
                
                    // Retrieve number of displays
                    $sth = $dbh->prepare('SELECT COUNT(*) AS Cnt FROM `display` WHERE `licensed` = 1');
                    $sth->execute();

                    $PHONE_HOME_CLIENTS = $sth->fetchColumn();
                
                    // Retrieve version number
                    $PHONE_HOME_VERSION = Config::Version('app_ver');
                
                    $PHONE_HOME_URL = Config::GetSetting('PHONE_HOME_URL') . "?id=" . urlencode(Config::GetSetting('PHONE_HOME_KEY')) . "&version=" . urlencode($PHONE_HOME_VERSION) . "&numClients=" . urlencode($PHONE_HOME_CLIENTS);
                
                    if ($this->isAuditing == 1)
                        Debug::LogEntry("audit", "PHONE_HOME_URL " . $PHONE_HOME_URL , "xmds", "RequiredFiles");
                    
                    // Set PHONE_HOME_TIME to NOW.
                    $sth = $dbh->prepare('UPDATE `setting` SET `value` = :time WHERE `setting`.`setting` = :setting LIMIT 1');
                    $sth->execute(array(
                            'time' => time(),
                            'setting' => 'PHONE_HOME_DATE'
                        ));
                                
                    @file_get_contents($PHONE_HOME_URL);
                
                    if ($this->isAuditing == 1)
                        Debug::LogEntry("audit", "PHONE_HOME [OUT]", "xmds", "RequiredFiles");
                }
                catch (Exception $e) {
                    
                    Debug::LogEntry('error', $e->getMessage());

                    return false;
                }
            }
        }
    }

    /**
     * Authenticates the display
     * @param <type> $hardwareKey
     * @return <type>
     */
    private function AuthDisplay($hardwareKey)
    {
	$db =& $this->db;

	// check in the database for this hardwareKey
	$SQL = "SELECT licensed, inc_schedule, isAuditing, displayID, defaultlayoutid, loggedin, email_alert, display, version_instructions FROM display WHERE license = '$hardwareKey'";

        if (!$result = $db->query($SQL))
	{
            trigger_error("License key query failed:" .$db->error());
            return false;
	}

	//Is it there?
	if ($db->num_rows($result) == 0)
            return false;
	
        //we have seen this display before, so check the licensed value
        $row = $db->get_row($result);

        if ($row[0] == 0)
            return false;

        // Pull the client IP address
        $clientAddress = Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING);

        // See if the client was offline and if appropriate send an alert
        // to say that it has come back online
        if ($row[5] == 0 && $row[6] == 1 && Config::GetSetting('MAINTENANCE_ENABLED') == 'On' && 
            (Config::GetSetting('MAINTENANCE_EMAIL_ALERTS') == 'On' || Config::GetSetting('MAINTENANCE_EMAIL_ALERTS') == 'Protected'))
        {
            $msgTo    = Kit::ValidateParam(Config::GetSetting("mail_to"),_PASSWORD);
            $msgFrom  = Kit::ValidateParam(Config::GetSetting("mail_from"),_PASSWORD);

            $subject  = sprintf(__("Recovery for Display %s"),$row[7]);
            $body     = sprintf(__("Display %s with ID %d is now back online."), $row[7], $row[3]);

            Kit::SendEmail($msgTo, $msgFrom, $subject, $body);
        }

        // Last accessed date on the display
        $displayObject = new Display($db);
        $displayObject->Touch($hardwareKey, $clientAddress);

        // It is licensed?
        $this->licensed = true;
        $this->includeSchedule = $row[1];
        $this->isAuditing = $row[2];
        $this->displayId = $row[3];
        $this->defaultLayoutId = $row[4];
        $this->version_instructions = $row[8];
        
        return true;
    }

    /**
     * Checks that the calling service is talking the correct version
     * @return
     * @param $version Object
     */
    private function CheckVersion($version)
    {
        $db =& $this->db;

        // Look up the Service XMDS version from the Version table
        $serverVersion = Config::Version('XmdsVersion');

        if ($version != $serverVersion)
        {
            Debug::LogEntry('audit', sprintf('A Client with an incorrect version connected. Client Version: [%s] Server Version [%s]', $version, $serverVersion));
            return false;
        }

        return true;
    }

    /**
     * Check we havent exceeded the bandwidth limits
     */
    private function CheckBandwidth()
    {
        $xmdsLimit = Config::GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

        if ($xmdsLimit <= 0)
            return true;

        // Test bandwidth for the current month
        $startOfMonth = strtotime(date('m').'/01/'.date('Y').' 00:00:00');

        $sql = sprintf('SELECT IFNULL(SUM(Size), 0) AS BandwidthUsage FROM `bandwidth` WHERE Month = %d', $startOfMonth);
        $bandwidthUsage = $this->db->GetSingleValue($sql, 'BandwidthUsage', _INT);

        return ($bandwidthUsage >= ($xmdsLimit * 1024)) ? false : true;
    }

    /**
     * Log Bandwidth Usage
     * @param <type> $displayId
     * @param <type> $type
     * @param <type> $sizeInBytes
     */
    private function LogBandwidth($displayId, $type, $sizeInBytes)
    {
        $startOfMonth = strtotime(date('m').'/02/'.date('Y').' 00:00:00');
    
        $sql  = "INSERT INTO `bandwidth` (Month, Type, DisplayID, Size) VALUES (%d, %d, %d, %d) ";
        $sql .= "ON DUPLICATE KEY UPDATE Size = Size + %d ";
        $sql  = sprintf($sql, $startOfMonth, $type, $displayId, $sizeInBytes, $sizeInBytes);

        $this->db->query($sql);

        return true;
    }
}
?>
