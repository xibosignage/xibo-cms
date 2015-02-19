<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2014 Daniel Garner
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

class XMDSSoap3
{
    private $licensed;
    private $includeSchedule;
    private $isAuditing;
    private $displayId;
    private $defaultLayoutId;
    private $version_instructions;

    /**
     * Registers a new display
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $displayName
     * @param string $version
     * @return string
     * @throws SoapFault
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $version)
    {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        
        // Check the serverKey matches the one we have
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40)
            throw new SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

        // Check in the database for this hardwareKey
        try {
            $dbh = PDOConnect::init();
            $sth = $dbh->prepare('
                SELECT licensed, displayid
                  FROM display 
                WHERE license = :hardwareKey');

            $sth->execute(array(
                   'hardwareKey' => $hardwareKey
                ));
            
            $result = $sth->fetchAll();
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage());
            throw new SoapFault('Sender', 'Cannot check client key.');
        }

        // If it doesn't already exist, then deny access.
        if (count($result) == 0) {
            Debug::Audit('Attempt to register a Version 3 Display.');
            throw new SoapFault('Sender', 'You cannot register an old display against this CMS.');
        }

        // Otherwise output the display status
        $row = $result[0];

        if ($row['licensed'] == 0) {
            $active = 'Display is awaiting licensing approval from an Administrator.';
        }
        else {
            $active = 'Display is active and ready to start.';
        }

        // Touch the display record
        $displayObject = new Display();
        $displayObject->Touch($row['displayid']);

        // Log Bandwidth
        $this->LogBandwidth($row['displayid'], Bandwidth::$REGISTER, strlen($active));

        Debug::Audit($active, $row['displayid']);

        return $active;
    }

    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $serverKey
     * @param string $hardwareKey Display Hardware Key
     * @param string $version
     * @return string $requiredXml Xml Formatted
     * @throws SoapFault
     */
    function RequiredFiles($serverKey, $hardwareKey, $version)
    {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $rfLookAhead = Kit::ValidateParam(Config::GetSetting('REQUIRED_FILES_LOOKAHEAD'), _INT);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");

        // auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Sender', 'This display is not licensed.');

        if ($this->isAuditing == 1)
            Debug::Audit('[IN] with hardware key: ' . $hardwareKey, $this->displayId);

        // Remove all Nonces for this display
        $nonce = new Nonce();
        $nonce->RemoveAllXmdsNonce($this->displayId);

        // Build a new RF
        $requiredFilesXml = new DOMDocument("1.0");
        $fileElements = $requiredFilesXml->createElement("files");
        $requiredFilesXml->appendChild($fileElements);

        // Hour to hour time bands for the query
        // Start at the current hour
        $fromFilter = time();
        // Move forwards an hour and the rf lookahead
        $rfLookAhead = $fromFilter + 3600 + $rfLookAhead;
        // Dial both items back to the top of the hour
        $fromFilter = $fromFilter - ($fromFilter % 3600);
        $toFilter = $rfLookAhead - ($rfLookAhead % 3600);

        if ($this->isAuditing == 1)
            Debug::Audit(sprintf('FromDT = %s. ToDt = %s', date('Y-m-d h:i:s', $fromFilter), date('Y-m-d h:i:s', $toFilter)), $this->displayId);

        try {
            $dbh = PDOConnect::init();
        
            // Get a list of all layout ids in the schedule right now.
            $SQL  = " SELECT DISTINCT layout.layoutID ";
            $SQL .= " FROM `campaign` ";
            $SQL .= "   INNER JOIN schedule ON schedule.CampaignID = campaign.CampaignID ";
            $SQL .= "   INNER JOIN schedule_detail ON schedule_detail.eventID = schedule.eventID ";
            $SQL .= "   INNER JOIN `lkcampaignlayout` ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
            $SQL .= "   INNER JOIN `layout` ON lkcampaignlayout.LayoutID = layout.LayoutID ";
            $SQL .= "   INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL .= " WHERE lkdisplaydg.DisplayID = :displayId ";
            $SQL .= " AND schedule_detail.FromDT < :fromdt AND schedule_detail.ToDT > :todt ";
            $SQL .= "   AND layout.retired = 0  ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'displayId' => $this->displayId,
                    'fromdt' => $toFilter,
                    'todt' => $fromFilter
                ));
    
            // Our layout list will always include the default layout
            $layouts = array();
            $layouts[] = $this->defaultLayoutId;
    
            // Build up the other layouts into an array
            foreach ($sth->fetchAll() as $row)
                $layouts[] = Kit::ValidateParam($row['layoutID'], _INT);  
        }
        catch (Exception $e) {            
            Debug::Error($e->getMessage(), $this->displayId);
            return new SoapFault('Sender', 'Unable to get a list of layouts');
        }

        // Create a comma separated list to pass into the query which gets file nodes
        $layoutIdList = implode(',', $layouts);

        try {
            $dbh = PDOConnect::init();
        
            // Add file nodes to the $fileElements
            $SQL  = "
                    SELECT 1 AS DownloadOrder, 'media' AS RecordType, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize, NULL AS xml 
                       FROM `media`
                     WHERE media.type = 'font'
                        OR (media.type = 'module' AND media.moduleSystemFile = 1) 
                    UNION
                    ";
            $SQL .= " SELECT 4 AS DownloadOrder, 'layout' AS RecordType, layout.layoutID AS path, layout.layoutID AS id, MD5(layout.xml) AS `MD5`, NULL AS FileSize, layout.xml AS xml ";
            $SQL .= "   FROM layout ";
            $SQL .= sprintf(" WHERE layout.layoutid IN (%s)  ", $layoutIdList);
            $SQL .= " UNION ";
            $SQL .= " SELECT 3 AS DownloadOrder, 'media' AS RecordType, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize, NULL AS xml ";
            $SQL .= "   FROM media ";
            $SQL .= "   INNER JOIN lklayoutmedia ";
            $SQL .= "   ON lklayoutmedia.MediaID = media.MediaID ";
            $SQL .= "   INNER JOIN layout ";
            $SQL .= "   ON layout.LayoutID = lklayoutmedia.LayoutID";
            $SQL .= sprintf(" WHERE layout.layoutid IN (%s)  ", $layoutIdList);
            $SQL .= "
                    UNION
                    SELECT 2 AS DownloadOrder, 'media' AS RecordType, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize, NULL AS xml 
                       FROM `media`
                        INNER JOIN `lkmediadisplaygroup`
                        ON lkmediadisplaygroup.mediaid = media.MediaID
                        INNER JOIN lkdisplaydg 
                        ON lkdisplaydg.DisplayGroupID = lkmediadisplaygroup.DisplayGroupID
                    ";
            $SQL .= " WHERE lkdisplaydg.DisplayID = :displayId ";
            $SQL .= " ORDER BY DownloadOrder, RecordType DESC";
    
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'displayId' => $this->displayId
                ));

            // Prepare a SQL statement in case we need to update the MD5 and FileSize on media nodes.
            $mediaSth = $dbh->prepare('UPDATE media SET `MD5` = :md5, FileSize = :size WHERE MediaID = :mediaid');

            foreach ($sth->fetchAll() as $row) {
                $recordType = Kit::ValidateParam($row['RecordType'], _WORD);
                $path = Kit::ValidateParam($row['path'], _STRING);
                $id = Kit::ValidateParam($row['id'], _STRING);
                $md5 = Kit::ValidateParam($row['MD5'], _HTMLSTRING);
                $fileSize = Kit::ValidateParam($row['FileSize'], _INT);
                $xml = Kit::ValidateParam($row['xml'], _HTMLSTRING);

                if ($recordType == 'layout') {
                    // For layouts the MD5 column is the layout xml
                    $fileSize = strlen($xml);
                    
                    if ($this->isAuditing == 1) 
                        Debug::Audit('MD5 for layoutid ' . $id . ' is: [' . $md5 . ']', $this->displayId);

                    // Add nonce
                    $nonce->AddXmdsNonce('layout', $this->displayId, NULL, $fileSize, NULL, $id);
                }
                else if ($recordType == 'media') {
                    // If they are empty calculate them and save them back to the media.
                    if ($md5 == '' || $fileSize == 0) {

                        $md5 = md5_file($libraryLocation.$path);
                        $fileSize = filesize($libraryLocation.$path);
                        
                        // Update the media record with this information
                        $mediaSth->execute(array('md5' => $md5, 'size' => $fileSize, 'mediaid' => $id));
                    }

                    // Add nonce
                    $nonce->AddXmdsNonce('file', $this->displayId, $id, $fileSize, $path);
                }
                else {
                    continue;
                }

                // Add the file node
                $file = $requiredFilesXml->createElement("file");
                $file->setAttribute("type", $recordType);
                $file->setAttribute("id", $id);
                $file->setAttribute("size", $fileSize);
                $file->setAttribute("md5", $md5);
                $file->setAttribute("download", 'xmds');
                $file->setAttribute("path", $path);
                
                $fileElements->appendChild($file);
            }
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage(), $this->displayId);
            return new SoapFault('Sender', 'Unable to get a list of files');
        }

        // Go through each layout and see if we need to supply any resource nodes.
        foreach ($layouts as $layoutId) {
            // Load the layout XML and work out if we have any ticker / text / dataset media items
            $layout = new Layout();

            $layoutInformation = $layout->LayoutInformation($layoutId);

            foreach($layoutInformation['regions'] as $region) {
                foreach($region['media'] as $media) {
                    if ($media['render'] == 'html' || $media['mediatype'] == 'ticker' || $media['mediatype'] == 'text' || $media['mediatype'] == 'datasetview' || $media['mediatype'] == 'webpage' || $media['mediatype'] == 'embedded') {
                        // Append this item to required files
                        $file = $requiredFilesXml->createElement("file");
                        $file->setAttribute('type', 'resource');
                        $file->setAttribute('id', rand());
                        $file->setAttribute('layoutid', $layoutId);
                        $file->setAttribute('regionid', $region['regionid']);
                        $file->setAttribute('mediaid', $media['mediaid']);
                        $file->setAttribute('updated', (isset($media['updated']) ? $media['updated'] : 0));
                        
                        $fileElements->appendChild($file);

                        $nonce->AddXmdsNonce('resource', $this->displayId, NULL, NULL, NULL, $layoutId, $region['regionid'], $media['mediaid']);
                    }
                }
            }
        }

        // Add a blacklist node
        $blackList = $requiredFilesXml->createElement("file");
        $blackList->setAttribute("type", "blacklist");

        $fileElements->appendChild($blackList);

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT MediaID FROM blacklist WHERE DisplayID = :displayid AND isIgnored = 0');
            $sth->execute(array(
                    'displayid' => $this->displayId
                ));
        
            // Add a black list element for each file
            foreach ($sth->fetchAll() as $row) {
                $file = $requiredFilesXml->createElement("file");
                $file->setAttribute("id", $row['MediaID']);
    
                $blackList->appendChild($file);
            }  
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage(), $this->displayId);
            return new SoapFault('Sender', 'Unable to get a list of blacklisted files');
        }

        // Phone Home?
        $this->PhoneHome();

        if ($this->isAuditing == 1)
            Debug::Audit($requiredFilesXml->saveXML(), $this->displayId);

        // Return the results of requiredFiles()
        $requiredFilesXml->formatOutput = true;
        $output = $requiredFilesXml->saveXML();

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, Bandwidth::$RF, strlen($output));

        return $output;
    }

    /**
     * Get File
     * @param string $serverKey   The ServerKey for this CMS
     * @param string $hardwareKey The HardwareKey for this Display
     * @param string $filePath
     * @param string $fileType    The File Type
     * @param int $chunkOffset The Offset of the Chunk Requested
     * @param string $chunkSize   The Size of the Chunk Requested
     * @param string $version
     * @return mixed
     * @throws SoapFault
     */
    function GetFile($serverKey, $hardwareKey, $filePath, $fileType, $chunkOffset, $chunkSize, $version)
    {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $filePath = Kit::ValidateParam($filePath, _STRING);
        $fileType = Kit::ValidateParam($fileType, _WORD);
        $chunkOffset = Kit::ValidateParam($chunkOffset, _INT);
        $chunkSize = Kit::ValidateParam($chunkSize, _INT);

        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', "This display client is not licensed");

        if ($this->isAuditing == 1)
            Debug::Audit("[IN] Params: [$hardwareKey] [$filePath] [$fileType] [$chunkOffset] [$chunkSize]", $this->displayId);
        
        $nonce = new Nonce();

        if ($fileType == "layout") {
            $fileId = Kit::ValidateParam($filePath, _INT);

            // Validate the nonce
            if (!$nonce->AllowedFile('layout', $this->displayId, NULL, $fileId))
                throw new SoapFault('Receiver', 'Requested an invalid file.');

            try {
                $dbh = PDOConnect::init();
            
                $sth = $dbh->prepare('SELECT xml FROM layout WHERE layoutid = :layoutid');
                $sth->execute(array('layoutid' => $fileId));
            
                if (!$row = $sth->fetch())
                    throw new Exception('No file found with that ID');

                $file = $row['xml'];
                
                // Store file size for bandwidth log
                $chunkSize = strlen($file);
            }
            catch (Exception $e) {
                Debug::Error($e->getMessage(), $this->displayId);
                return new SoapFault('Receiver', 'Unable the find layout.');
            }
        }
        else if ($fileType == "media")
        {
            // Get the ID
            if (strstr($filePath, '/') || strstr($filePath, '\\'))
                throw new SoapFault('Receiver', "Invalid file path.");

            // Validate the nonce
            if (!$nonce->AllowedFile('oldfile', $this->displayId, $filePath))
                throw new SoapFault('Receiver', 'Requested an invalid file.');
            
            // Return the Chunk size specified
            $f = fopen($libraryLocation . $filePath, 'r');

            fseek($f, $chunkOffset);

            $file = fread($f, $chunkSize);
            
            // Store file size for bandwidth log
            $chunkSize = strlen($file);
        }
        else {
            throw new SoapFault('Receiver', 'Unknown FileType Requested.');
        }

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, Bandwidth::$GETFILE, $chunkSize);
        
        return $file;
    }

    /**
     * Returns the schedule for the hardware key specified
     * @return string
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $version
     * @throws SoapFault
     */
    function Schedule($serverKey, $hardwareKey, $version)
    {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $rfLookAhead = Kit::ValidateParam(Config::GetSetting('REQUIRED_FILES_LOOKAHEAD'), _INT);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        //auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Sender', "This display client is not licensed");

        $scheduleXml = new DOMDocument("1.0");
        $layoutElements = $scheduleXml->createElement("schedule");

        $scheduleXml->appendChild($layoutElements);

        // Hour to hour time bands for the query
        // Start at the current hour
        $fromFilter = time();
        // Move forwards an hour and the rf lookahead
        $rfLookAhead = $fromFilter + 3600 + $rfLookAhead;
        // Dial both items back to the top of the hour
        $fromFilter = $fromFilter - ($fromFilter % 3600);

        if (Config::GetSetting('SCHEDULE_LOOKAHEAD') == 'On')
            $toFilter = $rfLookAhead - ($rfLookAhead % 3600);
        else 
            $toFilter = ($fromFilter + 3600) - (($fromFilter + 3600) % 3600);

        if ($this->isAuditing == 1)
            Debug::Audit(sprintf('FromDT = %s. ToDt = %s', date('Y-m-d h:i:s', $fromFilter), date('Y-m-d h:i:s', $toFilter)), $this->displayId);
        
        try {
            $dbh = PDOConnect::init();

            // Get all the module dependants
            $sth = $dbh->prepare("SELECT DISTINCT StoredAs FROM `media` WHERE media.type = 'font' OR (media.type = 'module' AND media.moduleSystemFile = 1) ");
            $sth->execute(array());
            $rows = $sth->fetchAll();
            $moduleDependents = array();

            foreach($rows as $dependent)
                $moduleDependents[] = $dependent['StoredAs'];
        
            // Add file nodes to the $fileElements
            // Firstly get all the scheduled layouts
            $SQL  = " SELECT layout.layoutID, schedule_detail.FromDT, schedule_detail.ToDT, schedule.eventID, schedule.is_priority, ";
            $SQL .= "  (SELECT GROUP_CONCAT(DISTINCT StoredAs) FROM media INNER JOIN lklayoutmedia ON lklayoutmedia.MediaID = media.MediaID WHERE lklayoutmedia.LayoutID = layout.LayoutID AND lklayoutmedia.regionID <> 'module' GROUP BY lklayoutmedia.LayoutID) AS Dependents";
            $SQL .= " FROM `campaign` ";
            $SQL .= " INNER JOIN schedule ON schedule.CampaignID = campaign.CampaignID ";
            $SQL .= " INNER JOIN schedule_detail ON schedule_detail.eventID = schedule.eventID ";
            $SQL .= " INNER JOIN `lkcampaignlayout` ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
            $SQL .= " INNER JOIN `layout` ON lkcampaignlayout.LayoutID = layout.LayoutID ";
            $SQL .= " INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL .= " WHERE lkdisplaydg.DisplayID = :displayId ";
            $SQL .= " AND (schedule_detail.FromDT < :fromdt AND schedule_detail.ToDT > :todt )";
            $SQL .= "   AND layout.retired = 0  ";
            $SQL .= " ORDER BY schedule.DisplayOrder, lkcampaignlayout.DisplayOrder, schedule_detail.eventID ";
    
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'displayId' => $this->displayId,
                    'fromdt' => $toFilter,
                    'todt' => $fromFilter
                ));
    
            // We must have some results in here by this point
            foreach ($sth->fetchAll() as $row) {
                $layoutid = $row[0];
                $fromdt = date('Y-m-d H:i:s', $row[1]);
                $todt = date('Y-m-d H:i:s', $row[2]);
                $scheduleid = $row[3];
                $is_priority = Kit::ValidateParam($row[4], _INT);
                $dependents = Kit::ValidateParam($row[5], _STRING);
    
                // Add a layout node to the schedule
                $layout = $scheduleXml->createElement("layout");
    
                $layout->setAttribute("file", $layoutid);
                $layout->setAttribute("fromdt", $fromdt);
                $layout->setAttribute("todt", $todt);
                $layout->setAttribute("scheduleid", $scheduleid);
                $layout->setAttribute("priority", $is_priority);
    
                $layoutElements->appendChild($layout);
            }
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage(), $this->displayId);
            return new SoapFault('Receiver', 'Unable to get A list of layouts for the schedule');
        }

        // Are we interleaving the default?
        if ($this->includeSchedule == 1) {
            // Add as a node at the end of the schedule.
            $layout = $scheduleXml->createElement("layout");

            $layout->setAttribute("file", $this->defaultLayoutId);
            $layout->setAttribute("fromdt", '2000-01-01 00:00:00');
            $layout->setAttribute("todt", '2030-01-19 00:00:00');
            $layout->setAttribute("scheduleid", 0);
            $layout->setAttribute("priority", 0);
            $layout->setAttribute("dependents", implode(',', $moduleDependents));

            $layoutElements->appendChild($layout);
        }

        // Add on the default layout node
        $default = $scheduleXml->createElement("default");
        $default->setAttribute("file", $this->defaultLayoutId);
        $layoutElements->appendChild($default);

        // Add on a list of global dependants
        $globalDependents = $scheduleXml->createElement("dependants");

        foreach ($moduleDependents as $dep) {
            $dependent = $scheduleXml->createElement("file", $dep);
            $globalDependents->appendChild($dependent);
        }
        $layoutElements->appendChild($globalDependents);

        // Format the output
        $scheduleXml->formatOutput = true;
        
        if ($this->isAuditing == 1)
            Debug::Audit($scheduleXml->saveXML(), $this->displayId);

        $output = $scheduleXml->saveXML();

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, Bandwidth::$SCHEDULE, strlen($output));

        return $output;
    }

    /**
     * BlackList
     * @return bool
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $mediaId
     * @param string $type
     * @param string $version
     * @throws SoapFault
     */
    function BlackList($serverKey, $hardwareKey, $mediaId, $type, $reason, $version)
    {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $mediaId = Kit::ValidateParam($mediaId, _STRING);
        $type = Kit::ValidateParam($type, _STRING);
        $reason = Kit::ValidateParam($reason, _STRING);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', "This display client is not licensed", $hardwareKey);

        try {
            $dbh = PDOConnect::init();
        
            // Check to see if this media / display is already blacklisted (and not ignored)
            $sth = $dbh->prepare('SELECT BlackListID FROM blacklist WHERE MediaID = :mediaid AND isIgnored = 0 AND DisplayID = :displayid');
            $sth->execute(array(
                    'mediaid' => $mediaId,
                    'displayid' => $this->displayId
                ));

            $results = $sth->fetchAll();
            
            if (count($results) == 0) {

                $insertSth = $dbh->prepare('
                        INSERT INTO blacklist (MediaID, DisplayID, ReportingDisplayID, Reason)
                            VALUES (:mediaid, :displayid, :reportingdisplayid, :reason)
                    ');

                // Insert the black list record
                if ($type == BLACKLIST_SINGLE) {
                    $insertSth->execute(array(
                            'mediaid' => $mediaId, 
                            'displayid' => $this->displayId,
                            'reportingdisplayid' => $this->displayId, 
                            'reason' => $reason
                        ));
                }
                else {
                    $displaySth = $dbh->prepare('SELECT displayID FROM `display`');
                    $displaySth->execute();

                    foreach ($displaySth->fetchAll() as $row) {

                        $insertSth->execute(array(
                            'mediaid' => $mediaId, 
                            'displayid' => $row['displayID'], 
                            'reportingdisplayid' => $this->displayId, 
                            'reason' => $reason
                        ));
                    }
                }
            }
            else {
                if ($this->isAuditing == 1) 
                    Debug::Audit("Media Already BlackListed [$mediaId]", $this->displayId);
            }
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage(), $this->displayId);
            return new SoapFault('Receiver', "Unable to query for BlackList records.");
        }

        $this->LogBandwidth($this->displayId, Bandwidth::$BLACKLIST, strlen($reason));

        return true;
    }

    /**
     * Submit client logging
     * @return bool
     * @param string $version
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $logXml
     * @throws SoapFault
     */
    function SubmitLog($version, $serverKey, $hardwareKey, $logXml)
    {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $logXml = Kit::ValidateParam($logXml, _HTMLSTRING);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address. ');
        
        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Sender', 'This display client is not licensed.');
        
        if ($this->isAuditing == 1) 
            Debug::Audit('IN. XML [' . $logXml . ']', $this->displayId);

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");

        if (!$document->loadXML($logXml)) {
            Debug::Error('Malformed XML from Player, this will be discarded. The Raw XML String provided is: ' . $logXml, $this->displayId);
            return true;
        }

        foreach ($document->documentElement->childNodes as $node) {
            
            // Make sure we dont consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) 
                continue;

            // Zero out the common vars
            $date = "";
            $message = "";
            $scheduleID = "";
            $layoutID = "";
            $mediaID = "";
            $cat = '';
            $method = '';
            $thread = '';

            // This will be a bunch of trace nodes
            $message = $node->textContent;

            // if ($this->isAuditing == 1) 
            //    Debug::LogEntry("audit", 'Trace Message: [' . $message . ']', "xmds", "SubmitLog", "", $this->displayId);

            // Each element should have a category and a date
            $date = $node->getAttribute('date');
            $cat = strtolower($node->getAttribute('category'));

            if ($date == '' || $cat == '') {
                Debug::Error('Log submitted without a date or category attribute', $this->displayId);
                continue;
            }

            // Get the date and the message (all log types have these)
            foreach ($node->childNodes as $nodeElements) {

                if ($nodeElements->nodeName == "scheduleID") {
                    $scheduleID = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "layoutID") {
                    $layoutID = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "mediaID") {
                    $mediaID = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "type") {
                    $type = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "method") {
                    $method = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "message") {
                    $message = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "thread") {
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

        $this->LogBandwidth($this->displayId, Bandwidth::$SUBMITLOG, strlen($logXml));

        return true;
    }

    /**
     * Submit display statistics to the server
     * @return bool
     * @param string $version
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $statXml
     * @throws SoapFault
     */
    function SubmitStats($version, $serverKey, $hardwareKey, $statXml)
    {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $statXml = Kit::ValidateParam($statXml, _HTMLSTRING);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');
        
        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', "This display client is not licensed");
        
        if ($this->isAuditing == 1) 
            Debug::Audit("IN. StatXml: [" . $statXml . "]", $this->displayId);

        if ($statXml == "")
            throw new SoapFault('Receiver', "Stat XML is empty.");
        
        // Log
        $statObject = new Stat();

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");
        $document->loadXML($statXml);

        foreach ($document->documentElement->childNodes as $node) {
            // Make sure we dont consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) 
                continue;

            // Zero out the common vars
            $fromdt = '';
            $todt = '';
            $type = '';

            $scheduleID = 0;
            $layoutID = 0;
            $mediaID = '';
            $tag = '';

            // Each element should have these attributes
            $fromdt = $node->getAttribute('fromdt');
            $todt = $node->getAttribute('todt');
            $type = $node->getAttribute('type');

            if ($fromdt == '' || $todt == '' || $type == '') {
                trigger_error('Stat submitted without the fromdt, todt or type attributes.');
                continue;
            }

            $scheduleID = $node->getAttribute('scheduleid');
            $layoutID = $node->getAttribute('layoutid');
            $mediaID = $node->getAttribute('mediaid');
            $tag = $node->getAttribute('tag');

            // Write the stat record with the information we have available to us.
            if (!$statObject->Add($type, $fromdt, $todt, $scheduleID, $this->displayId, $layoutID, $mediaID, $tag)) {
                Debug::Error(sprintf('Stat Add failed with error: %s', $statObject->GetErrorMessage()), $this->displayId);
                continue;
            }
        }

        $this->LogBandwidth($this->displayId, Bandwidth::$SUBMITSTATS, strlen($statXml));

        return true;
    }

    /**
     * Store the media inventory for a client
     * @param string $version
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $inventory
     * @return bool
     * @throws SoapFault
     */
    public function MediaInventory($version, $serverKey, $hardwareKey, $inventory)
    {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $inventory = Kit::ValidateParam($inventory, _HTMLSTRING);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', 'This display client is not licensed');

        if ($this->isAuditing == 1) 
            Debug::Audit($inventory, 'xmds', $this->displayId);

        // Check that the $inventory contains something
        if ($inventory == '')
            throw new SoapFault('Receiver', 'Inventory Cannot be Empty');

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");
        $document->loadXML($inventory);

        // Assume we are complete (but we are getting some)
        $mediaInventoryComplete = 1;

        $xpath = new DOMXPath($document);
        $fileNodes = $xpath->query("//file");

        foreach ($fileNodes as $node) {
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
        $displayObject = new Display();
        $displayObject->Touch($this->displayId, array('mediaInventoryStatus' => $mediaInventoryComplete, 'mediaInventoryXml' => $inventory));

        $this->LogBandwidth($this->displayId, Bandwidth::$MEDIAINVENTORY, strlen($inventory));

        return true;
    }

    /**
     * Gets additional resources for assigned media
     * @param string $serverKey
     * @param string $hardwareKey
     * @param int $layoutId
     * @param string $regionId
     * @param string $mediaId
     * @param string $version
     * @return string
     * @throws SoapFault
     */
    function GetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId, $version)
    {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $layoutId = Kit::ValidateParam($layoutId, _INT);
        $regionId = Kit::ValidateParam($regionId, _STRING);
        $mediaId = Kit::ValidateParam($mediaId, _STRING);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', "This display client is not licensed");

        // Validate the nonce
        $nonce = new Nonce();
        if (!$nonce->AllowedFile('resource', $this->displayId, NULL, $layoutId, $regionId, $mediaId))
            throw new SoapFault('Receiver', 'Requested an invalid file.');

        // What type of module is this?
        $region = new region();
        $type = $region->GetMediaNodeType($layoutId, $regionId, $mediaId);

        if ($type == '')
            throw new SoapFault('Receiver', 'Unable to get the media node type');

        // Dummy User Object
        $user = new User();
        $user->userid = 0;
        $user->usertypeid = 1;

        // Initialise the theme (for global styles in GetResource)
        new Theme($user);
        Theme::SetPagename('module');

        // Get the resource from the module
        try {
            $module = ModuleFactory::load($type, $layoutId, $regionId, $mediaId, null, null, $user);
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage(), $this->displayId);
            throw new SoapFault('Receiver', 'Cannot create module. Check CMS Log');
        }

        $resource = $module->GetResource($this->displayId);

        if (!$resource || $resource == '')
            throw new SoapFault('Receiver', 'Unable to get the media resource');

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, Bandwidth::$GETRESOURCE, strlen($resource));

        return $resource;
    }

    /**
     * PHONE_HOME if required
     */
    private function PhoneHome()
    {
        if (Config::GetSetting('PHONE_HOME') == 'On') {
            // Find out when we last PHONED_HOME :D
            // If it's been > 28 days since last PHONE_HOME then
            if (Config::GetSetting('PHONE_HOME_DATE') < (time() - (60 * 60 * 24 * 28))) {
                
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
    private function AuthDisplay($hardwareKey, $status = NULL)
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                SELECT licensed, inc_schedule, isAuditing, displayID, defaultlayoutid, loggedin, 
                    email_alert, display, version_instructions, client_type, client_code, client_version
                  FROM display 
                 WHERE license = :hardwareKey
                ');

            $sth->execute(array(
                    'hardwareKey' => $hardwareKey
                ));

            $result = $sth->fetchAll();
        
            // Is it there?
            if (count($result) == 0)
                return false;
            
            // We have seen this display before, so check the licensed value
            $row = $result[0];

            if ($row['licensed'] == 0)
                return false;
        
            // See if the client was off-line and if appropriate send an alert
            // to say that it has come back on-line
            $this->AlertDisplayUp($row['displayID'], $row['display'], $row['loggedin'], $row['email_alert']);

            // It is licensed?
            $this->licensed = true;
            $this->includeSchedule = $row['inc_schedule'];
            $this->isAuditing = $row['isAuditing'];
            $this->displayId = $row['displayID'];
            $this->defaultLayoutId = $row['defaultlayoutid'];
            $this->version_instructions = $row['version_instructions'];
            $this->clientType = $row['client_type'];
            $this->clientVersion = $row['client_version'];
            $this->clientCode = $row['client_code'];
            
            // Last accessed date on the display
            $displayObject = new Display();
            $displayObject->Touch($this->displayId, array('clientAddress' => Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING)));
                
            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return false;
        }
    }

    private function AlertDisplayUp($displayId, $display, $loggedIn, $emailAlert)
    {
        $maintenanceEnabled = Config::GetSetting('MAINTENANCE_ENABLED');
        
        if ($loggedIn == 0) {

            // Log display up
            $statObject = new Stat();
            $statObject->displayUp($displayId);

            // Do we need to email?
            if ($emailAlert == 1 && ($maintenanceEnabled == 'On' || $maintenanceEnabled == 'Protected') 
                && Config::GetSetting('MAINTENANCE_EMAIL_ALERTS') == 'On') {

                $msgTo = Kit::ValidateParam(Config::GetSetting("mail_to") ,_PASSWORD);
                $msgFrom = Kit::ValidateParam(Config::GetSetting("mail_from"), _PASSWORD);

                $subject = sprintf(__("Recovery for Display %s"), $display);
                $body = sprintf(__("Display %s with ID %d is now back online."), $display, $displayId);

                // Get a list of people that have view access to the display?
                if (Config::GetSetting('MAINTENANCE_ALERTS_FOR_VIEW_USERS') == 1) {
                    foreach (Display::getUsers($displayId) as $user) {
                        if ($user['email'] != '') {
                            Kit::SendEmail($user['email'], $msgFrom, $subject, $body);
                        }
                    }
                }

                // Send to the original admin contact
                Kit::SendEmail($msgTo, $msgFrom, $subject, $body);
            }
        }
    }

    /**
     * Check we havent exceeded the bandwidth limits
     */
    private function CheckBandwidth()
    {
        $xmdsLimit = Config::GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

        if ($xmdsLimit <= 0)
            return true;

        try {
            $dbh = PDOConnect::init();
        
            // Test bandwidth for the current month
            $sth = $dbh->prepare('SELECT IFNULL(SUM(Size), 0) AS BandwidthUsage FROM `bandwidth` WHERE Month = :month');
            $sth->execute(array(
                    'month' => strtotime(date('m').'/02/'.date('Y').' 00:00:00')
                ));

            $bandwidthUsage = $sth->fetchColumn(0);
    
            return ($bandwidthUsage >= ($xmdsLimit * 1024)) ? false : true;  
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage(), $this->displayId);
            return false;
        }
    }

    /**
     * Log Bandwidth Usage
     * @param <type> $displayId
     * @param <type> $type
     * @param <type> $sizeInBytes
     */
    private function LogBandwidth($displayId, $type, $sizeInBytes)
    {    
        $bandwidth = new Bandwidth();
        $bandwidth->Log($displayId, $type, $sizeInBytes);
    }
}
?>
