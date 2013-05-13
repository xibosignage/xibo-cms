<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
        $db          =& $this->db;
        $currentdate = date("Y-m-d H:i:s");

        Debug::LogEntry($db, 'audit', 'Adding new Layout', 'Layout', 'Add');

        // Validation
        if (strlen($layout) > 50 || strlen($layout) < 1)
        {
            $this->SetError(25001, __("Layout Name must be between 1 and 50 characters"));
            return false;
        }

        if (strlen($description) > 254)
        {
            $this->SetError(25002, __("Description can not be longer than 254 characters"));
            return false;
        }

        if (strlen($tags) > 254)
        {
            $this->SetError(25003, __("Tags can not be longer than 254 characters"));
            return false;
        }

        // Ensure there are no layouts with the same name
        $SQL = sprintf("SELECT layout FROM layout WHERE layout = '%s' AND userID = %d ", $layout, $userid);

        if ($db->GetSingleRow($SQL))
        {
            $this->SetError(25004, sprintf(__("You already own a layout called '%s'. Please choose another name."), $layout));
            return false;
        }
        // End Validation

        Debug::LogEntry($db, 'audit', 'Validation Compelte', 'Layout', 'Add');

        // Get the XML for this template.
        $templateXml = $this->GetTemplateXml($templateId, $userid);

        Debug::LogEntry($db, 'audit', 'Retrieved template xml', 'Layout', 'Add');

        $SQL = <<<END
        INSERT INTO layout (layout, description, userID, createdDT, modifiedDT, tags, xml)
         VALUES ('%s', '%s', %d, '%s', '%s', '%s', '%s')
END;

        $SQL = sprintf($SQL, $db->escape_string($layout),
                            $db->escape_string($description), $userid,
                            $db->escape_string($currentdate),
                            $db->escape_string($currentdate),
                            $db->escape_string($tags),
                            $templateXml);

        if(!$id = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25005, __('Could not add Layout'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'Updating Tags', 'Layout', 'Add');

        // Are there any tags?
        if ($tags != '')
        {
            // Create an array out of the tags
            $tagsArray = explode(' ', $tags);

            // Add the tags XML to the layout
            if (!$this->EditTags($id, $tagsArray))
            {
                $this->Delete($id);
                return false;
            }
        }

        // Create a campaign
        $campaign = new Campaign($db);

        $campaignId = $campaign->Add($layout, 1, $userid);
        $campaign->Link($campaignId, $id, 0);

        // What permissions should we create this with?
        if (Config::GetSetting($db, 'LAYOUT_DEFAULT') == 'public')
        {
            Kit::ClassLoader('campaignsecurity');
            $security = new CampaignSecurity($db);
            $security->LinkEveryone($campaignId, 1, 0, 0);
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'Layout', 'Add');

        return $id;
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
        
        $db          =& $this->db;
        $currentdate = date("Y-m-d H:i:s");

        // Validation
        if ($layoutId == 0)
            return $response->SetError(__('Layout not selected'));
        
        if (strlen($layout) > 50 || strlen($layout) < 1) 
        {
            $response->SetError(__("Layout Name must be between 1 and 50 characters"));
            $response->Respond();
        }
        
        if (strlen($description) > 254) 
        {
            $response->SetError(__("Description can not be longer than 254 characters"));
            $response->Respond();
        }
        
        if (strlen($tags) > 254) 
        {
            $response->SetError(__("Tags can not be longer than 254 characters"));
            $response->Respond();
        }
        
        // Name check
        if ($db->GetSingleRow(sprintf("SELECT layout FROM layout WHERE layout = '%s' AND userID = %d AND layoutid <> %d ", $db->escape_string($layout), $userid, $layoutId)))
        {
            trigger_error($db->error());
            $this->SetError(25004, sprintf(__("You already own a layout called '%s'. Please choose another name."), $layout));
            return false;
        }
        // End Validation

        $SQL = <<<END

        UPDATE layout SET
            layout = '%s',
            description = '%s',
            modifiedDT = '%s',
            retired = %d,
            tags = '%s'
        
        WHERE layoutID = %s;        
END;

        $SQL = sprintf($SQL, 
                        $db->escape_string($layout),
                        $db->escape_string($description), 
                        $db->escape_string($currentdate), $retired, 
                        $db->escape_string($tags), $layoutId);
        
        Debug::LogEntry($db, 'audit', $SQL);

        if(!$db->query($SQL)) 
        {
            trigger_error($db->error());
            $response->SetError(sprintf(__('Unknown error editing %s'), $layout));
            $response->Respond();
        }
        
        // Create an array out of the tags
        $tagsArray = explode(' ', $tags);
        
        // Add the tags XML to the layout
        $layoutObject = new Layout($db);
        
        if (!$layoutObject->EditTags($layoutId, $tagsArray))
            return false;

        // Maintain the name on the campaign
        Kit::ClassLoader('campaign');
        $campaign = new Campaign($db);
        $campaignId = $campaign->GetCampaignId($layoutId);
        $campaign->Edit($campaignId, $layout);

        // Notify (dont error)
        Kit::ClassLoader('display');
        $displayObject = new Display($db);
        $displayObject->NotifyDisplays($campaignId);

        return true;
    }

    /**
     * Gets the XML for the specified template id
     * @param <type> $templateId
     */
    private function GetTemplateXml($templateId, $userId)
    {
        $db =& $this->db;

        if ($templateId == 0)
        {
            // make some default XML
            $xmlDoc = new DOMDocument("1.0");
            $layoutNode = $xmlDoc->createElement("layout");

            $layoutNode->setAttribute("width", 800);
            $layoutNode->setAttribute("height", 450);
            $layoutNode->setAttribute("bgcolor", "#000000");
            $layoutNode->setAttribute("schemaVersion", Config::Version($db, 'XlfVersion'));

            $xmlDoc->appendChild($layoutNode);

            $xml = $xmlDoc->saveXML();
        }
        else
        {
            // Get the template XML
            if (!$row = $db->GetSingleRow(sprintf("SELECT xml FROM template WHERE templateID = %d ", $templateId)))
                trigger_error(__('Error getting this template.'), E_USER_ERROR);

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

    /**
     * Edit Tags for a layout
     * @param <type> $layoutID
     * @param <type> $tags
     * @return <type>
     */
    public function EditTags($layoutID, $tags)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'Layout', 'EditTags');

        // Make sure we get an array
        if(!is_array($tags))
        {
            $this->SetError(25006, 'Must pass EditTags an array');
            return false;
        }

        // Set the XML
        if (!$this->SetXml($layoutID))
        {
            Debug::LogEntry($db, 'audit', 'Failed to Set the layout Xml.', 'Layout', 'EditTags');
            return false;
        }

        Debug::LogEntry($db, 'audit', 'Got the XML from the DB. Now creating the tags.', 'Layout', 'EditTags');

        // Create the tags XML
        $tagsXml = '<tags>';

        foreach($tags as $tag)
        {
            $tagsXml .= sprintf('<tag>%s</tag>', $tag);
        }

        $tagsXml .= '</tags>';

        Debug::LogEntry($db, 'audit', 'Tags XML is:' . $tagsXml, 'Layout', 'EditTags');

        // Load the tags XML into a document
        $tagsXmlDoc = new DOMDocument('1.0');
        $tagsXmlDoc->loadXML($tagsXml);


        // Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->xml);

        // Import the new node into this document
        $newTagsNode = $xml->importNode($tagsXmlDoc->documentElement, true);

        // Xpath for an existing tags node
        $xpath 	= new DOMXPath($xml);
        $tagsNode 	= $xpath->query("//tags");

        // Does the tags node exist?
        if ($tagsNode->length < 1)
        {
            // We need to append our new node to the layout node
            $layoutXpath	= new DOMXPath($xml);
            $layoutNode 	= $xpath->query("//layout");
            $layoutNode 	= $layoutNode->item(0);

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

        Debug::LogEntry($db, 'audit', $xml, 'layout', 'EditTags');

        // Save it
        if (!$this->SetLayoutXml($layoutID, $xml)) return false;

        Debug::LogEntry($db, 'audit', 'OUT', 'Layout', 'EditTags');

        return true;
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

        Debug::LogEntry($this->db, 'audit', 'Loading LayoutXml into the DOM', 'layout', 'SetDomXML');

        if (!$this->DomXml->loadXML($this->xml))
            return false;

        Debug::LogEntry($this->db, 'audit', 'Loaded LayoutXml into the DOM', 'layout', 'SetDomXML');

        return true;
    }

    /**
     * Gets the Xml for the specified layout
     * @return
     * @param $layoutid Object
     */
    public function GetLayoutXml($layoutid)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'Layout', 'GetLayoutXml');

        //Get the Xml for this Layout from the DB
        $SQL = sprintf("SELECT xml FROM layout WHERE layoutID = %d ", $layoutid);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25000, 'Layout does not exist.');
            return false;
        }

        $row = $db->get_row($results) ;

        Debug::LogEntry($db, 'audit', 'OUT', 'Layout', 'GetLayoutXml');

        return $row[0];
    }

    /**
     * Sets the Layout Xml and writes it back to the database
     * @return
     * @param $layoutid Object
     * @param $xml Object
     */
    private function SetLayoutXml($layoutid, $xml)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'Layout', 'SetLayoutXml');

        $xml = addslashes($xml);

        // Write it back to the database
        $SQL = sprintf("UPDATE layout SET xml = '%s' WHERE layoutID = %d ", $xml, $layoutid);


        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25007, 'Unable to Update Layout.');
            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'Layout', 'SetLayoutXml');

        return true;
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
        $db =& $this->db;
        $currentdate = date("Y-m-d H:i:s");
        $campaign = new Campaign($db);

        // Include to media data class?
        if ($copyMedia)
        {
            Kit::ClassLoader('media');
            Kit::ClassLoader('mediagroupsecurity');
            $mediaObject = new Media($db);
            $mediaSecurity = new MediaGroupSecurity($db);
        }

        // We need the old campaignid
        $oldCampaignId = $campaign->GetCampaignId($oldLayoutId);

        // Permissions model
        Kit::ClassLoader('campaignsecurity');
        Kit::ClassLoader('layoutregiongroupsecurity');
        Kit::ClassLoader('layoutmediagroupsecurity');

        // The Layout ID is the old layout
        $SQL  = "";
        $SQL .= " INSERT INTO layout (layout, xml, userID, description, tags, templateID, retired, duration, background, createdDT, modifiedDT) ";
        $SQL .= " SELECT '%s', xml, %d, description, tags, templateID, retired, duration, background, '%s', '%s' ";
        $SQL .= "  FROM layout ";
        $SQL .= " WHERE layoutid = %d";
        $SQL = sprintf($SQL, $db->escape_string($newLayoutName), $userId, $db->escape_string($currentdate), $db->escape_string($currentdate), $oldLayoutId);

        Debug::LogEntry($db, 'audit', $SQL, 'layout', 'Copy');

        if (!$newLayoutId = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25000, __('Unable to Copy this Layout'));
            return false;
        }

        // Create a campaign
        $newCampaignId = $campaign->Add($newLayoutName, 1, $userId);

        // Link them
        $campaign->Link($newCampaignId, $newLayoutId, 0);

        // Open the layout XML and parse for media nodes
        if (!$this->SetDomXml($newLayoutId))
        {
            $this->Delete($newLayoutId);
            $this->SetError(25000, __('Unable to copy layout'));
            return false;
        }

        Debug::LogEntry($this->db, 'audit', 'Loaded XML into the DOM.', 'layout', 'Copy');
        
        // Get all media nodes
        $xpath = new DOMXpath($this->DomXml);

        // Create an XPath to get all media nodes
        $mediaNodes = $xpath->query("//media");

        Debug::LogEntry($this->db, 'audit', 'About to loop through media nodes', 'layout', 'Copy');
        
        // On each media node, take the existing LKID and MediaID and create a new LK record in the database
        foreach ($mediaNodes as $mediaNode)
        {
            $mediaId = $mediaNode->getAttribute('id');
            $type = $mediaNode->getAttribute('type');

            // Store the old media id
            $oldMediaId = $mediaId;

            Debug::LogEntry($this->db, 'audit', sprintf('Media %s node found with id %d', $type, $mediaId), 'layout', 'Copy');

            // If this is a non region specific type, then move on
            if ($this->IsRegionSpecific($type))
            {
                // Generate a new media id
                $newMediaId = md5(uniqid());
                
                $mediaNode->setAttribute('id', $newMediaId);

                // Copy media security
                $security = new LayoutMediaGroupSecurity($db);
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
                {
                    $this->Delete($newLayoutId);
                    return false;
                }

                // Update the permissions for the new media record
                $mediaSecurity->Copy($oldMediaId, $mediaId);

                // Copied the media node, so set the ID
                $mediaNode->setAttribute('id', $mediaId);

                // Also need to set the options node
                // Get the stored as value of the new node
                if (!$fileName = $this->db->GetSingleValue(sprintf("SELECT StoredAs FROM media WHERE MediaID = %d", $mediaId), 'StoredAs', _STRING))
                    return $this->SetError(25000, __('Unable to stored value of newly copied media'));

                $newNode = $this->DomXml->createElement('uri', $fileName);

                // Find the old node
                $uriNodes = $mediaNode->getElementsByTagName('uri');
                $uriNode = $uriNodes->item(0);

                // Replace it
                $uriNode->parentNode->replaceChild($newNode, $uriNode);
            }

            // Add the database link for this media record
            if (!$lkId = $this->AddLk($newLayoutId, $regionId, $mediaId))
            {
                $this->Delete($newLayoutId);
                return false;
            }

            // Update the permissions for this media on this layout
            $security = new LayoutMediaGroupSecurity($db);
            $security->CopyAllForMedia($oldLayoutId, $newLayoutId, $oldMediaId, $mediaId);

            // Set this LKID on the media node
            $mediaNode->setAttribute('lkid', $lkId);
        }

        Debug::LogEntry($this->db, 'audit', 'Finished looping through media nodes', 'layout', 'Copy');

        // Set the XML
        $this->SetLayoutXml($newLayoutId, $this->DomXml->saveXML());

        // Layout permissions
        $security = new CampaignSecurity($db);
        $security->CopyAll($oldCampaignId, $newCampaignId);

        $security = new LayoutRegionGroupSecurity($db);
        $security->CopyAll($oldLayoutId, $newLayoutId);
        
        // Return the new layout id
        return $newLayoutId;
    }

    /**
     * Retire a layout
     * @param int $layoutId [description]
     */
    public function Retire($layoutId) {
        
        $db =& $this->db;

        // Make sure the layout id is present
        if ($layoutId == 0)
            return $this->SetError(__('No Layout selected'));
        
        $SQL = sprintf("UPDATE layout SET retired = 1 WHERE layoutID = %d", $layoutId);
    
        if (!$db->query($SQL)) {
            trigger_error($db->error());
            return $this->SetError(__('Unable to retire this layout.'));
        }
    }

    /**
     * Deletes a layout
     * @param <type> $layoutId
     * @return <type>
     */
    public function Delete($layoutId)
    {
        $db =& $this->db;

        // Make sure the layout id is present
        if ($layoutId == 0)
            return $this->SetError(__('No Layout selected'));

        $campaign = new Campaign($db);
        $campaignId = $campaign->GetCampaignId($layoutId);

        // Remove all LK records for this layout
        $db->query(sprintf('DELETE FROM lklayoutmediagroup WHERE layoutid = %d', $layoutId));
        $db->query(sprintf('DELETE FROM lklayoutregiongroup WHERE layoutid = %d', $layoutId));
        $db->query(sprintf('DELETE FROM lklayoutmedia WHERE layoutid = %d', $layoutId));

        // Remove the Campaign (will remove links to this layout - orphaning the layout)
        if (!$campaign->Delete($campaignId))
            return $this->SetError(25008, __('Unable to delete campaign'));

        // Remove the Layout (now it is orphaned it can be deleted safely)
        if (!$db->query(sprintf('DELETE FROM layout WHERE layoutid = %d', $layoutId)))
            return $this->SetError(25008, __('Unable to delete layout'));

        return true;
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
        $db =& $this->db;

        $SQL = sprintf("INSERT INTO lklayoutmedia (layoutID, regionID, mediaID) VALUES (%d, '%s', %d)", $layoutid, $db->escape_string($region), $mediaid);

        if (!$id = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError('25999',__("Database error adding this link record."));
            return false;
        }

        return $id;
    }

    /**
     * Is a module type region specific?
     * @param <bool> $type
     */
    private function IsRegionSpecific($type)
    {
        $sql = sprintf("SELECT RegionSpecific FROM module WHERE Module = '%s'", $this->db->escape_string($type));

        Debug::LogEntry($this->db, 'audit', sprintf('Checking to see if %s is RegionSpecific with SQL %s', $type, $sql), 'layout', 'Copy');

        return ($this->db->GetSingleValue($sql, 'RegionSpecific', _INT) == 1) ? true : false;
    }

    /**
     * Set the Background Image
     * @param int $layoutId          [description]
     * @param int $resolutionId      [description]
     * @param string $color          [description]
     * @param int $backgroundImageId [description]
     */
    public function SetBackground($layoutId, $resolutionId, $color, $backgroundImageId) {

        $db =& $this->db;

        if ($layoutId == 0)
            return $response->SetError(__('Layout not selected'));

        if ($layoutId == 0)
            return $response->SetError(__('Resolution not selected'));


        // Allow for the 0 media idea (no background image)
        if ($backgroundImageId == 0)
        {
            $bg_image = '';
        }
        else
        {
            // Get the file URI
            $SQL = sprintf("SELECT StoredAs FROM media WHERE MediaID = %d", $backgroundImageId);

            // Look up the bg image from the media id given
            if (!$bg_image = $db->GetSingleValue($SQL, 'StoredAs', _STRING))
                return $this->SetError(__('Cannot find the background image selected'));
        }

        // Look up the width and the height
        $SQL = sprintf("SELECT width, height FROM resolution WHERE resolutionID = %d ", $resolutionId);
        
        if (!$results = $db->query($SQL)) 
        {
            trigger_error($db->error());
            return $this->SetError(__('Unable to get the Resolution information'));
        }
        
        $row    = $db->get_row($results) ;
        $width  =  Kit::ValidateParam($row[0], _INT);
        $height =  Kit::ValidateParam($row[1], _INT);
        
        include_once("lib/data/region.data.class.php");
        
        $region = new region($db);
        
        if (!$region->EditBackground($layoutId, '#' . $color, $bg_image, $width, $height, $resolutionId))
        {
            //there was an ERROR
            $response->SetError($region->errorMsg);
            $response->Respond();
        }
        
        // Update the layout record with the new background
        $SQL = sprintf("UPDATE layout SET background = '%s' WHERE layoutid = %d ", $bg_image, $layoutId);
        
        if (!$db->query($SQL)) 
        {
            trigger_error($db->error());
            return $this->SetError(__("Unable to update background information"));
        }

        return true;
    }
}
?>
