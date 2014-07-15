<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2013 Daniel Garner
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

class Layout extends Data
{
    private $xml;
    private $DomXml;

    public function  __construct($db)
    {
        Kit::ClassLoader('campaign');

        parent::__construct($db);
    }

    /**
     * Add a layout
     * @param <type> $layout
     * @param <type> $description
     * @param <type> $tags
     * @param <type> $userid
     * @param <type> $templateId
     * @return <type>
     */
    public function Add($layout, $description, $tags, $userid, $templateId)
    {
        Debug::LogEntry('audit', 'Adding new Layout', 'Layout', 'Add');

        try {
            $dbh = PDOConnect::init();
        
            $currentdate = date("Y-m-d H:i:s");
        
            // Validation
            if (strlen($layout) > 50 || strlen($layout) < 1)
                $this->ThrowError(25001, __("Layout Name must be between 1 and 50 characters"));
    
            if (strlen($description) > 254)
                $this->ThrowError(25002, __("Description can not be longer than 254 characters"));
    
            if (strlen($tags) > 254)
                $this->ThrowError(25003, __("Tags can not be longer than 254 characters"));

            // Ensure there are no layouts with the same name
            $sth = $dbh->prepare('SELECT layout FROM `layout` WHERE layout = :layout AND userID = :userid');
            $sth->execute(array(
                    'layout' => $layout,
                    'userid' => $userid
                ));
            
            if ($row = $sth->fetch())
                $this->ThrowError(25004, sprintf(__("You already own a layout called '%s'. Please choose another name."), $layout));

            Debug::LogEntry('audit', 'Validation Compelte', 'Layout', 'Add');
            // End Validation
        
            // Get the XML for this template.
            $templateXml = $this->GetTemplateXml($templateId, $userid);
        
            Debug::LogEntry('audit', 'Retrieved template xml', 'Layout', 'Add');

            $SQL  = 'INSERT INTO layout (layout, description, userID, createdDT, modifiedDT, tags, xml, status)';
            $SQL .= ' VALUES (:layout, :description, :userid, :createddt, :modifieddt, :tags, :xml, :status)';

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layout' => $layout,
                    'description' => $description,
                    'userid' => $userid,
                    'createddt' => $currentdate,
                    'modifieddt' => $currentdate,
                    'tags' => $tags,
                    'xml' => $templateXml,
                    'status' => 3
                ));

            $id = $dbh->lastInsertId();
        
            Debug::LogEntry('audit', 'Updating Tags', 'Layout', 'Add');
        
            // Are there any tags?
            if ($tags != '')
            {
                // Create an array out of the tags
                $tagsArray = explode(' ', $tags);
    
                // Add the tags XML to the layout
                if (!$this->EditTags($id, $tagsArray))
                    $this->ThrowError(__('Unable to edit tags'));
            }
        
            // Create a campaign
            $campaign = new Campaign($this->db);
    
            $campaignId = $campaign->Add($layout, 1, $userid);
            $campaign->Link($campaignId, $id, 0);
        
            // What permissions should we create this with?
            if (Config::GetSetting('LAYOUT_DEFAULT') == 'public')
            {
                Kit::ClassLoader('campaignsecurity');
                $security = new CampaignSecurity($this->db);
                $security->LinkEveryone($campaignId, 1, 0, 0);
            }
    
            Debug::LogEntry('audit', 'Complete', 'Layout', 'Add');
    
            return $id;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25005, __('Could not add Layout'));
        
            return false;
        }
    }

    /**
     * Edit a Layout
     * @param int $layoutId    [description]
     * @param string $layout      [description]
     * @param string $description [description]
     * @param string $tags        [description]
     * @param int $userid      [description]
     * @param int $retired      [description]
     */
    public function Edit($layoutId, $layout, $description, $tags, $userid, $retired) {
        
        try {
            $dbh = PDOConnect::init();
                    
            $currentdate = date("Y-m-d H:i:s");
        
            // Validation
            if (strlen($layout) > 50 || strlen($layout) < 1)
                $this->ThrowError(25001, __("Layout Name must be between 1 and 50 characters"));
    
            if (strlen($description) > 254)
                $this->ThrowError(25002, __("Description can not be longer than 254 characters"));
    
            if (strlen($tags) > 254)
                $this->ThrowError(25003, __("Tags can not be longer than 254 characters"));

            // Ensure there are no layouts with the same name
            $sth = $dbh->prepare('SELECT layout FROM `layout` WHERE layout = :layout AND userID = :userid AND layoutid <> :layoutid');
            $sth->execute(array(
                    'layout' => $layout,
                    'userid' => $userid,
                    'layoutid' => $layoutId
                ));
            
            if ($row = $sth->fetch())
                $this->ThrowError(25004, sprintf(__("You already own a layout called '%s'. Please choose another name."), $layout));

            Debug::LogEntry('audit', 'Validation Compelte', 'Layout', 'Add');
            // End Validation
            
            $SQL  = 'UPDATE layout SET layout = :layout, description = :description, modifiedDT = :modifieddt, retired = :retired, tags = :tags WHERE layoutID = :layoutid';

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layout' => $layout,
                    'description' => $description,
                    'modifieddt' => $currentdate,
                    'retired' => $retired,
                    'tags' => $tags,
                    'layoutid' => $layoutId
                ));
                
            // Create an array out of the tags
            $tagsArray = explode(' ', $tags);
            
            // Add the tags XML to the layout
            if (!$this->EditTags($layoutId, $tagsArray))
                throw new Exception("Error Processing Request", 1);
                
            // Maintain the name on the campaign
            Kit::ClassLoader('campaign');
            $campaign = new Campaign($this->db);
            $campaignId = $campaign->GetCampaignId($layoutId);
            $campaign->Edit($campaignId, $layout);
    
            // Notify (dont error)
            Kit::ClassLoader('display');
            $displayObject = new Display($this->db);
            $displayObject->NotifyDisplays($campaignId);
    
            // Is this layout valid
            $this->SetValid($layoutId);
    
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(sprintf(__('Unknown error editing %s'), $layout));
        
            return false;
        }
    }

    /**
     * Gets the XML for the specified template id
     * @param <type> $templateId
     */
    private function GetTemplateXml($templateId, $userId)
    {
        try {
            $dbh = PDOConnect::init();
        
            if ($templateId == 0) {
                // make some default XML
                $xmlDoc = new DOMDocument("1.0");
                $layoutNode = $xmlDoc->createElement("layout");
    
                $layoutNode->setAttribute("width", 800);
                $layoutNode->setAttribute("height", 450);
                $layoutNode->setAttribute("bgcolor", "#000000");
                $layoutNode->setAttribute("schemaVersion", Config::Version('XlfVersion'));
    
                $xmlDoc->appendChild($layoutNode);
    
                $xml = $xmlDoc->saveXML();
            }
            else {
                // Get the template XML
                $sth = $dbh->prepare('SELECT xml FROM template WHERE templateID = :templateid');
                $sth->execute(array(
                        'templateid' => $templateId
                    ));

                if (!$row = $sth->fetch())
                    $this->ThrowError(__('Unknown template'));
    
                $xmlDoc = new DOMDocument("1.0");
                $xmlDoc->loadXML($row['xml']);
    
                $regionNodeList = $xmlDoc->getElementsByTagName('region');
    
                //get the regions
                foreach ($regionNodeList as $region)
                    $region->setAttribute('userId', $userId);
    
                $xml = $xmlDoc->saveXML();
            }
        
            return $xml;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Edit Tags for a layout
     * @param <type> $layoutID
     * @param <type> $tags
     * @return <type>
     */
    public function EditTags($layoutID, $tags)
    {
        Debug::LogEntry('audit', 'IN', 'Layout', 'EditTags');
        
        try {
            $dbh = PDOConnect::init();
        
            // Make sure we get an array
            if(!is_array($tags))
                $this->ThrowError(25006, 'Must pass EditTags an array');
                
            // Set the XML
            if (!$this->SetXml($layoutID))
                $this->ThrowError(__('Unable to set XML'));
        
            Debug::LogEntry('audit', 'Got the XML from the DB. Now creating the tags.', 'Layout', 'EditTags');
        
            // Create the tags XML
            $tagsXml = '<tags>';
    
            foreach($tags as $tag)
                $tagsXml .= sprintf('<tag>%s</tag>', $tag);
    
            $tagsXml .= '</tags>';
    
            Debug::LogEntry('audit', 'Tags XML is:' . $tagsXml, 'Layout', 'EditTags');
        
            // Load the tags XML into a document
            $tagsXmlDoc = new DOMDocument('1.0');
            $tagsXmlDoc->loadXML($tagsXml);
    
            // Load the XML for this layout
            $xml = new DOMDocument("1.0");
            $xml->loadXML($this->xml);
    
            // Import the new node into this document
            $newTagsNode = $xml->importNode($tagsXmlDoc->documentElement, true);
    
            // Xpath for an existing tags node
            $xpath     = new DOMXPath($xml);
            $tagsNode     = $xpath->query("//tags");
    
            // Does the tags node exist?
            if ($tagsNode->length < 1)
            {
                // We need to append our new node to the layout node
                $layoutXpath    = new DOMXPath($xml);
                $layoutNode     = $xpath->query("//layout");
                $layoutNode     = $layoutNode->item(0);
    
                $layoutNode->appendChild($newTagsNode);
            }
            else
            {
                // We need to swap our new node with the existing one
                $tagsNode = $tagsNode->item(0);
    
                // Replace the node
                $tagsNode->parentNode->replaceChild($newTagsNode, $tagsNode);
            }
    
            // Format the output a bit nicer for Alex
            $xml->formatOutput = true;
    
            // Convert back to XML
            $xml = $xml->saveXML();
    
            // Save it
            if (!$this->SetLayoutXml($layoutID, $xml)) 
                throw new Exception("Error Processing Request", 1);
    
            Debug::LogEntry('audit', 'OUT', 'Layout', 'EditTags');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Sets the Layout XML for this layoutid
     * @return
     * @param $layoutID Object
     */
    private function SetXml($layoutID)
    {
        if(!$this->xml = $this->GetLayoutXml($layoutID))
            return false;

        return true;
    }

    private function SetDomXml($layoutId)
    {
        if (!$this->SetXml($layoutId))
            return false;

        $this->DomXml = new DOMDocument("1.0");

        Debug::LogEntry('audit', 'Loading LayoutXml into the DOM', 'layout', 'SetDomXML');

        if (!$this->DomXml->loadXML($this->xml))
            return false;

        Debug::LogEntry('audit', 'Loaded LayoutXml into the DOM', 'layout', 'SetDomXML');

        return true;
    }

    /**
     * Gets the Xml for the specified layout
     * @return
     * @param $layoutid Object
     */
    public function GetLayoutXml($layoutid)
    {
        Debug::LogEntry('audit', 'IN', 'Layout', 'GetLayoutXml');
        
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT xml FROM layout WHERE layoutID = :layoutid');
            $sth->execute(array(
                    'layoutid' => $layoutid
                ));

            if (!$row = $sth->fetch())
                throw new Exception("Layout does not exist", 1);
                
            Debug::LogEntry('audit', 'OUT', 'Layout', 'GetLayoutXml');
        
            return $row['xml'];  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25000, 'Layout does not exist.');
        
            return false;
        }
    }

    /**
     * Sets the Layout Xml and writes it back to the database
     * @return
     * @param $layoutid Object
     * @param $xml Object
     */
    public function SetLayoutXml($layoutid, $xml)
    {
        Debug::LogEntry('audit', 'IN', 'Layout', 'SetLayoutXml');
        
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('UPDATE layout SET xml = :xml, modifieddt = NOW() WHERE layoutID = :layoutid');
            $sth->execute(array(
                    'layoutid' => $layoutid,
                    'xml' => $xml
                ));

            // Get the Campaign ID
            Kit::ClassLoader('campaign');
            $campaign = new Campaign($this->db);
            $campaignId = $campaign->GetCampaignId($layoutid);

            // Notify (dont error)
            Kit::ClassLoader('display');
            $displayObject = new Display($this->db);
            $displayObject->NotifyDisplays($campaignId);
        
            Debug::LogEntry('audit', 'OUT', 'Layout', 'SetLayoutXml');
        
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25007, 'Unable to Update Layout.');
        
            return false;
        }
    }

    /**
     * Copys a Layout
     * @param <int> $oldLayoutId
     * @param <string> $newLayoutName
     * @param <int> $userId
     * @param <bool> $copyMedia Make copies of this layouts media
     * @return <int> 
     */
    public function Copy($oldLayoutId, $newLayoutName, $userId, $copyMedia = false)
    {
        try {
            $dbh = PDOConnect::init();
        
            $currentdate = date("Y-m-d H:i:s");
            $campaign = new Campaign($this->db);
    
            // Include to media data class?
            if ($copyMedia)
            {
                Kit::ClassLoader('media');
                Kit::ClassLoader('mediagroupsecurity');
                $mediaObject = new Media($this->db);
                $mediaSecurity = new MediaGroupSecurity($this->db);
            }
    
            // We need the old campaignid
            $oldCampaignId = $campaign->GetCampaignId($oldLayoutId);
    
            // Permissions model
            Kit::ClassLoader('campaignsecurity');
            Kit::ClassLoader('layoutregiongroupsecurity');
            Kit::ClassLoader('layoutmediagroupsecurity');
    
            // The Layout ID is the old layout
            $SQL  = "";
            $SQL .= " INSERT INTO layout (layout, xml, userID, description, tags, templateID, retired, duration, background, createdDT, modifiedDT, status) ";
            $SQL .= " SELECT :layout, xml, :userid, description, tags, templateID, retired, duration, background, :createddt, :modifieddt, status ";
            $SQL .= "  FROM layout ";
            $SQL .= " WHERE layoutid = :layoutid";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layout' => $newLayoutName,
                    'userid' => $userId,
                    'createddt' => $currentdate,
                    'modifieddt' => $currentdate,
                    'layoutid' => $oldLayoutId
                ));

            $newLayoutId = $dbh->lastInsertId();
    
            // Create a campaign
            $newCampaignId = $campaign->Add($newLayoutName, 1, $userId);
    
            // Link them
            $campaign->Link($newCampaignId, $newLayoutId, 0);
    
            // Open the layout XML and parse for media nodes
            if (!$this->SetDomXml($newLayoutId))
                $this->ThrowError(25000, __('Unable to copy layout'));

            // Get all media nodes
            $xpath = new DOMXpath($this->DomXml);
    
            // Create an XPath to get all media nodes
            $mediaNodes = $xpath->query("//media");
    
            Debug::LogEntry('audit', 'About to loop through media nodes', 'layout', 'Copy');
            
            // On each media node, take the existing LKID and MediaID and create a new LK record in the database
            $sth = $dbh->prepare('SELECT StoredAs FROM media WHERE MediaID = :mediaid');

            foreach ($mediaNodes as $mediaNode)
            {
                $mediaId = $mediaNode->getAttribute('id');
                $type = $mediaNode->getAttribute('type');
    
                // Store the old media id
                $oldMediaId = $mediaId;
    
                Debug::LogEntry('audit', sprintf('Media %s node found with id %d', $type, $mediaId), 'layout', 'Copy');
    
                // If this is a non region specific type, then move on
                if ($this->IsRegionSpecific($type))
                {
                    // Generate a new media id
                    $newMediaId = md5(uniqid());
                    
                    $mediaNode->setAttribute('id', $newMediaId);
    
                    // Copy media security
                    $security = new LayoutMediaGroupSecurity($this->db);
                    $security->CopyAllForMedia($oldLayoutId, $newLayoutId, $mediaId, $newMediaId);
                    continue;
                }
    
                // Get the regionId
                $regionNode = $mediaNode->parentNode;
                $regionId = $regionNode->getAttribute('id');
    
                // Do we need to copy this media record?
                if ($copyMedia)
                {
                    // Take this media item and make a hard copy of it.
                    if (!$mediaId = $mediaObject->Copy($mediaId, $newLayoutName))
                        throw new Exception("Error Processing Request", 1);
                        
                    // Update the permissions for the new media record
                    $mediaSecurity->Copy($oldMediaId, $mediaId);
    
                    // Copied the media node, so set the ID
                    $mediaNode->setAttribute('id', $mediaId);
    
                    // Also need to set the options node
                    // Get the stored as value of the new node
                    $sth->execute(array('mediaid' => $mediaId));

                    if (!$row = $sth->fetch())
                        $this->ThrowError(25000, __('Unable to find stored value of newly copied media'));

                    $fileName = Kit::ValidateParam($row['StoredAs'], _STRING);
                    
                    $newNode = $this->DomXml->createElement('uri', $fileName);
    
                    // Find the old node
                    $uriNodes = $mediaNode->getElementsByTagName('uri');
                    $uriNode = $uriNodes->item(0);
    
                    // Replace it
                    $uriNode->parentNode->replaceChild($newNode, $uriNode);
                }
    
                // Add the database link for this media record
                if (!$lkId = $this->AddLk($newLayoutId, $regionId, $mediaId))
                    throw new Exception("Error Processing Request", 1);
    
                // Update the permissions for this media on this layout
                $security = new LayoutMediaGroupSecurity($this->db);
                $security->CopyAllForMedia($oldLayoutId, $newLayoutId, $oldMediaId, $mediaId);
    
                // Set this LKID on the media node
                $mediaNode->setAttribute('lkid', $lkId);
            }
    
            Debug::LogEntry('audit', 'Finished looping through media nodes', 'layout', 'Copy');
    
            // Set the XML
            $this->SetLayoutXml($newLayoutId, $this->DomXml->saveXML());
    
            // Layout permissions
            $security = new CampaignSecurity($this->db);
            $security->CopyAll($oldCampaignId, $newCampaignId);
    
            $security = new LayoutRegionGroupSecurity($this->db);
            $security->CopyAll($oldLayoutId, $newLayoutId);
            
            // Return the new layout id
            return $newLayoutId;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25000, __('Unable to Copy this Layout'));
        
            return false;
        }
    }

    /**
     * Retire a layout
     * @param int $layoutId [description]
     */
    public function Retire($layoutId) {
        
        try {
            $dbh = PDOConnect::init();

            // Make sure the layout id is present
            if ($layoutId == 0)
                $this->ThrowError(__('No Layout selected'));
        
            $sth = $dbh->prepare('UPDATE layout SET retired = 1 WHERE layoutID = :layoutid');
            $sth->execute(array('layoutid' => $layoutId));
            
            return true; 
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError(__('Unable to retire this layout.'));
        
            return false;
        }
    }

    /**
     * Deletes a layout
     * @param <type> $layoutId
     * @return <type>
     */
    public function Delete($layoutId)
    {
        try {
            $dbh = PDOConnect::init();

            // Make sure the layout id is present
            if ($layoutId == 0)
                $this->ThrowError(__('No Layout selected'));
        
            $sth = $dbh->prepare('DELETE FROM lklayoutmediagroup WHERE layoutid = :layoutid');
            $sth->execute(array('layoutid' => $layoutId));

            $sth = $dbh->prepare('DELETE FROM lklayoutregiongroup WHERE layoutid = :layoutid');
            $sth->execute(array('layoutid' => $layoutId));

            $sth = $dbh->prepare('DELETE FROM lklayoutmedia WHERE layoutid = :layoutid');
            $sth->execute(array('layoutid' => $layoutId));
        
            // Handle the deletion of the campaign        
            $campaign = new Campaign($this->db);
            $campaignId = $campaign->GetCampaignId($layoutId);
    
            // Remove the Campaign (will remove links to this layout - orphaning the layout)
            if (!$campaign->Delete($campaignId))
                $this->ThrowError(25008, __('Unable to delete campaign'));
    
            // Remove the Layout (now it is orphaned it can be deleted safely)
            $sth = $dbh->prepare('DELETE FROM layout WHERE layoutid = :layoutid');
            $sth->execute(array('layoutid' => $layoutId));

            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25008, __('Unable to delete layout'));
        
            return false;
        }
    }

    /**
     * Adds a DB link between a layout and its media
     * @param <type> $layoutid
     * @param <type> $region
     * @param <type> $mediaid
     * @return <type>
     */
    private function AddLk($layoutid, $region, $mediaid)
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('INSERT INTO lklayoutmedia (layoutID, regionID, mediaID) VALUES (:layoutid, :regionid, :mediaid)');
            $sth->execute(array(
                    'layoutid' => $layoutid,
                    'regionid' => $region,
                    'mediaid' => $mediaid
                ));
        
            return $dbh->lastInsertId();  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError('25999',__("Database error adding this link record."));
        
            return false;
        }
    }

    /**
     * Is a module type region specific?
     * @param <bool> $type
     */
    private function IsRegionSpecific($type)
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT RegionSpecific FROM module WHERE Module = :module');
            $sth->execute(array(
                    'module' => $type
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(__('Unknown Module'));
        
            Debug::LogEntry('audit', sprintf('Checking to see if %s is RegionSpecific', $type), 'layout', 'Copy');
        
            return (Kit::ValidateParam($row['RegionSpecific'], _INT) == 1) ? true : false;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Set the Background Image
     * @param int $layoutId          [description]
     * @param int $resolutionId      [description]
     * @param string $color          [description]
     * @param int $backgroundImageId [description]
     */
    public function SetBackground($layoutId, $resolutionId, $color, $backgroundImageId) {
        Debug::LogEntry('audit', 'IN', 'Layout', 'SetBackground');
        
        try {
            $dbh = PDOConnect::init();
                
            if ($layoutId == 0)
                $this->ThrowError(__('Layout not selected'));
    
            if ($layoutId == 0)
                $this->ThrowError(__('Resolution not selected'));
    
            // Allow for the 0 media idea (no background image)
            if ($backgroundImageId == 0)
            {
                $bg_image = '';
            }
            else
            {
                // Get the file URI
                $sth = $dbh->prepare('SELECT StoredAs FROM media WHERE MediaID = :mediaid');
                $sth->execute(array(
                    'mediaid' => $backgroundImageId
                ));
    
                // Look up the bg image from the media id given
                if (!$row = $sth->fetch())
                    $this->ThrowError(__('Cannot find the background image selected'));

                $bg_image = Kit::ValidateParam($row['StoredAs'], _STRING);
            }
        
            // Look up the width and the height
            $sth = $dbh->prepare('SELECT width, height FROM resolution WHERE resolutionID = :resolutionid');
            $sth->execute(array(
                'resolutionid' => $resolutionId
            ));

            // Look up the bg image from the media id given
            if (!$row = $sth->fetch())
                return $this->SetError(__('Unable to get the Resolution information'));

            $width  =  Kit::ValidateParam($row['width'], _INT);
            $height =  Kit::ValidateParam($row['height'], _INT);

            include_once("lib/data/region.data.class.php");
            
            $region = new region($this->db);
            
            if (!$region->EditBackground($layoutId, '#' . $color, $bg_image, $width, $height, $resolutionId))
                throw new Exception("Error Processing Request", 1);
                    
            // Update the layout record with the new background
            $sth = $dbh->prepare('UPDATE layout SET background = :background WHERE layoutid = :layoutid');
            $sth->execute(array(
                'background' => $bg_image,
                'layoutid' => $layoutId
            ));
    
            // Is this layout valid
            $this->SetValid($layoutId);
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError(__("Unable to update background information"));
        
            return false;
        }
    }

    /**
     * Gets a list of regions in the provided layout
     * @param [int] $layoutId [The Layout ID]
     */
    public function GetRegionList($layoutId) {
        
        if (!$this->SetDomXml($layoutId))
            return false;

        Debug::LogEntry('audit', '[IN] Loaded XML into DOM', 'layout', 'GetRegionList');

        // Get region nodes
        $regionNodes = $this->DomXml->getElementsByTagName('region');

        $regions = array();

        // Loop through each and build an array
        foreach ($regionNodes as $region) {

            $item = array();
            $item['width'] = $region->getAttribute('width');
            $item['height'] = $region->getAttribute('height');
            $item['left'] = $region->getAttribute('left');
            $item['top'] = $region->getAttribute('top');
            $item['regionid'] = $region->getAttribute('id');
            $item['ownerid'] = $region->getAttribute('userId');
            $item['name'] = $region->getAttribute('name');

            $regions[] = $item;
        }

        Debug::LogEntry('audit', '[OUT]', 'layout', 'GetRegionList');

        return $regions;
    }

    /**
     * Check that the provided layout is valid
     * @param [int] $layoutId [The Layout ID]
     */
    public function IsValid($layoutId, $reassess = false) {
        try {
            $dbh = PDOConnect::init();
        
            Kit::ClassLoader('region');
    
            // Dummy User Object
            $user = new User($this->db);
            $user->userid = 0;
            $user->usertypeid = 1;
    
            Debug::LogEntry('audit', '[IN]', 'layout', 'IsValid');
    
            if (!$reassess) {
                $sth = $dbh->prepare('SELECT status FROM `layout` WHERE LayoutID = :layoutid');
                $sth->execute(array(
                    'layoutid' => $layoutId
                ));

                if (!$row = $sth->fetch())
                    throw new Exception("Error Processing Request", 1);
                
                return Kit::ValidateParam($row['status'], _INT);
            }
    
            Debug::LogEntry('audit', 'Reassesment Required', 'layout', 'IsValid');
    
            // Take the layout, loop through its regions, check them and call IsValid on all media in them.
            $regions = $this->GetRegionList($layoutId);
    
            if (count($regions) <= 0)
                return 3;
    
            // Loop through each and build an array
            foreach ($regions as $region) {

                Debug::LogEntry('audit', 'Assessing Region: ' . $region['regionid'], 'layout', 'IsValid');

                // Create a layout object
                $regionObject = new Region($this->db);
                $mediaNodes = $regionObject->GetMediaNodeList($layoutId, $region['regionid']);

                if ($mediaNodes->length <= 0) {
                    Debug::LogEntry('audit', 'No Media nodes in region, therefore invalid.', 'layout', 'IsValid');
                    return 3;
                }
    
                foreach($mediaNodes as $mediaNode)
                {
                    // Put this node vertically in the region timeline
                    $mediaId = $mediaNode->getAttribute('id');
                    $lkId = $mediaNode->getAttribute('lkid');
                    $mediaType = $mediaNode->getAttribute('type');
                    
                    // Create a media module to handle all the complex stuff
                    require_once("modules/$mediaType.module.php");
                    $tmpModule = new $mediaType($this->db, $user, $mediaId, $layoutId, $region['regionid'], $lkId);
    
                    $status = $tmpModule->IsValid();
    
                    if ($status != 1)
                        return $status;
                }

                Debug::LogEntry('audit', 'Finished with Region', 'layout', 'IsValid');
            }
    
            Debug::LogEntry('audit', 'Layout looks in good shape', 'layout', 'IsValid');
    
            // If we get to the end, we are OK!
            return 1;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return 3;
        }
    }

    /**
     * Set the Validity of this Layout
     * @param [int] $layoutId [The Layout Id]
     */
    public function SetValid($layoutId) {
        try {
            $dbh = PDOConnect::init();

            Debug::LogEntry('audit', 'IN', 'Layout', 'SetValid');

            $status = $this->IsValid($layoutId, true);
        
            $sth = $dbh->prepare('UPDATE `layout` SET status = :status WHERE LayoutID = :layoutid');
            $sth->execute(array(
                    'status' => $status,
                    'layoutid' => $layoutId
                ));

            Debug::LogEntry('audit', 'OUT', 'Layout', 'SetValid');
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Returns an array containing all the layouts particulars
     * @param int $layoutId The layout ID
     */
    public function LayoutInformation($layoutId) {
        Debug::LogEntry('audit', '[IN]', 'layout', 'LayoutInformation');

        // The array to ultimately return
        $info = array();
        $info['regions'] = array();

        // Use the Region class to help
        Kit::ClassLoader('region');

        // Dummy User Object
        $user = new User($this->db);
        $user->userid = 0;
        $user->usertypeid = 1;

        // Take the layout, loop through its regions, check them and call LayoutInformation on all media in them.
        $info['regions'] = $this->GetRegionList($layoutId);

        if (count($info['regions']) <= 0)
            return $info;

        // Loop through each and build an array
        foreach ($info['regions'] as &$region) {

            $region['media'] = array();

            Debug::LogEntry('audit', 'Assessing Region: ' . $region['regionid'], 'layout', 'LayoutInformation');

            // Create a layout object
            $regionObject = new Region($this->db);
            $mediaNodes = $regionObject->GetMediaNodeList($layoutId, $region['regionid']);

            // Create a data set to see if there are any requirements to serve an updated date time
            Kit::ClassLoader('dataset');
            $dataSetObject = new DataSet($this->db);

            foreach($mediaNodes as $mediaNode) {

                $node = array(
                        'mediaid' => $mediaNode->getAttribute('id'),
                        'lkid' => $mediaNode->getAttribute('lkid'),
                        'mediatype' => $mediaNode->getAttribute('type')
                    );

                // DataSets are a special case. We want to get the last updated time from the dataset.
                $dataSet = $dataSetObject->GetDataSetFromLayout($layoutId, $region['regionid'], $mediaNode->getAttribute('id'));

                if (count($dataSet) == 1) {
                    
                    $node['updated'] = $dataSet[0]['LastDataEdit'];
                }

                // Put this node vertically in the region time-line
                $region['media'][] = $node;
            }

            Debug::LogEntry('audit', 'Finished with Region', 'layout', 'LayoutInformation');
        }

        return $info;
    }
}
?>
