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
Kit::ClassLoader('campaign');

class Layout extends Data
{
    public $layoutId;
    public $ownerId;
    public $campaignId;
    public $retired;
    public $backgroundImageId;

    public $layout;
    public $description;
    public $status;
    public $tags;
    public $regionId;
    public $lkLayoutMediaId;
    public $mediaOwnerId;

    public $xml;
    private $DomXml;

    public $delayFinalise = false;

    public static function Entries($sort_order = array(), $filter_by = array())
    {
        $entries = array();

        try {
            $dbh = PDOConnect::init();

            $params = array();
            $SQL  = "";
            $SQL .= "SELECT layout.layoutID, ";
            $SQL .= "        layout.layout, ";
            $SQL .= "        layout.description, ";
            $SQL .= "        layout.userID, ";
            $SQL .= "        layout.xml, ";
            $SQL .= "        campaign.CampaignID, ";
            $SQL .= "        layout.status, ";
            $SQL .= "        layout.retired, ";
            $SQL .= "        layout.backgroundImageId, ";
            
            if (Kit::GetParam('showTags', $filter_by, _INT) == 1)
                $SQL .= " tag.tag AS tags, ";
            else
                $SQL .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktaglayout ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = layout.LayoutID GROUP BY lktaglayout.layoutId) AS tags, ";

            // MediaID
            if (Kit::GetParam('mediaId', $filter_by, _INT, 0) != 0) {
                $SQL .= "   lklayoutmedia.regionid, ";
                $SQL .= "   lklayoutmedia.lklayoutmediaid, ";
                $SQL .= "   media.userID AS mediaownerid ";
            }
            else {
                $SQL .= "   NULL AS regionid, ";
                $SQL .= "   NULL AS lklayoutmediaid, ";
                $SQL .= "   NULL AS mediaownerid ";
            }

            $SQL .= "   FROM layout ";
            $SQL .= "  INNER JOIN `lkcampaignlayout` ";
            $SQL .= "   ON lkcampaignlayout.LayoutID = layout.LayoutID ";
            $SQL .= "   INNER JOIN `campaign` ";
            $SQL .= "   ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
            $SQL .= "       AND campaign.IsLayoutSpecific = 1";

            if (Kit::GetParam('showTags', $filter_by, _INT) == 1) {
                $SQL .= " LEFT OUTER JOIN lktaglayout ON lktaglayout.layoutId = layout.layoutId ";
                $SQL .= " LEFT OUTER JOIN tag ON tag.tagId = lktaglayout.tagId ";
            }

            if (Kit::GetParam('campaignId', $filter_by, _INT, 0) != 0) {
                // Join Campaign back onto it again
                $SQL .= " INNER JOIN `lkcampaignlayout` lkcl ON lkcl.layoutid = layout.layoutid AND lkcl.CampaignID = :campaignId ";
                $params['campaignId'] = Kit::GetParam('campaignId', $filter_by, _INT, 0);
            }

            // Get the Layout by CampaignId
            if (Kit::GetParam('layoutSpecificCampaignId', $filter_by, _INT, 0) != 0) {
                $SQL .= " AND `campaign`.campaignId = :layoutSpecificCampaignId ";
                $params['layoutSpecificCampaignId'] = Kit::GetParam('layoutSpecificCampaignId', $filter_by, _INT, 0);
            }

            // MediaID
            if (Kit::GetParam('mediaId', $filter_by, _INT, 0) != 0) {
                $SQL .= " INNER JOIN `lklayoutmedia` ON lklayoutmedia.layoutid = layout.layoutid AND lklayoutmedia.mediaid = :mediaId";
                $SQL .= " INNER JOIN `media` ON lklayoutmedia.mediaid = media.mediaid ";
                $params['mediaId'] = Kit::GetParam('mediaId', $filter_by, _INT, 0);
            }

            $SQL .= " WHERE 1 = 1 ";

            if (Kit::GetParam('layout', $filter_by, _STRING) != '')
            {
                // convert into a space delimited array
                $names = explode(' ', Kit::GetParam('layout', $filter_by, _STRING));

                foreach($names as $searchName)
                {
                    // Not like, or like?
                    if (substr($searchName, 0, 1) == '-') {
                        $SQL.= " AND  layout.layout NOT LIKE :search ";
                        $params['search'] = '%' . ltrim($searchName) . '%';
                    }
                    else {
                        $SQL.= " AND  layout.layout LIKE :search ";
                        $params['search'] = '%' . $searchName . '%';
                    }
                }
            }

            // Layout
            if (Kit::GetParam('layoutId', $filter_by, _INT, 0) != 0) {
                $SQL .= " AND layout.layoutId = :layoutId ";
                $params['layoutId'] = Kit::GetParam('layoutId', $filter_by, _INT, 0);
            }

            // Owner filter
            if (Kit::GetParam('userId', $filter_by, _INT, 0) != 0) {
                $SQL .= " AND layout.userid = :userId ";
                $params['userId'] = Kit::GetParam('userId', $filter_by, _INT, 0);
            }
            
            // Retired options
            if (Kit::GetParam('retired', $filter_by, _INT, 0) != -1) {
                $SQL .= " AND layout.retired = :retired ";
                $params['retired'] = Kit::GetParam('retired', $filter_by, _INT);
            }

            // Tags
            if (Kit::GetParam('tags', $filter_by, _STRING) != '') {
                $SQL .= " AND layout.layoutID IN (
                    SELECT lktaglayout.layoutId 
                      FROM tag 
                        INNER JOIN lktaglayout 
                        ON lktaglayout.tagId = tag.tagId 
                    ";

                $i = 0;
                foreach (explode(',', Kit::GetParam('tags', $filter_by, _STRING)) as $tag) {
                    $i++;

                    if ($i == 1)
                        $SQL .= " WHERE tag LIKE :tags$i ";
                    else
                        $SQL .= " OR tag LIKE :tags$i ";

                    $params['tags' . $i] =  '%' . $tag . '%';
                }

                $SQL .= ") ";
            }
            
            // Exclude templates by default
            if (Kit::GetParam('excludeTemplates', $filter_by, _INT, 1) == 1) {
                $SQL .= " AND layout.layoutID NOT IN (SELECT layoutId FROM lktaglayout WHERE tagId = 1) ";
            }
            else {
                $SQL .= " AND layout.layoutID IN (SELECT layoutId FROM lktaglayout WHERE tagId = 1) ";
            }

            // Show All, Used or UnUsed
            if (Kit::GetParam('filterLayoutStatusId', $filter_by, _INT, 1) != 1)  {
                if (Kit::GetParam('filterLayoutStatusId', $filter_by, _INT) == 2) {
                    // Only show used layouts
                    $SQL .= ' AND ('
                        . '     campaign.CampaignID IN (SELECT DISTINCT schedule.CampaignID FROM schedule) '
                        . '     OR layout.layoutID IN (SELECT DISTINCT defaultlayoutid FROM display) ' 
                        . ' ) ';
                }
                else {
                    // Only show unused layouts
                    $SQL .= ' AND campaign.CampaignID NOT IN (SELECT DISTINCT schedule.CampaignID FROM schedule) '
                        . ' AND layout.layoutID NOT IN (SELECT DISTINCT defaultlayoutid FROM display) ';
                }
            }

            // Sorting?
            if (is_array($sort_order))
                $SQL .= 'ORDER BY ' . implode(',', $sort_order);
        
            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            foreach ($sth->fetchAll() as $row) {
                $layout = new Layout();

                // Validate each param and add it to the array.
                $layout->layoutId = Kit::ValidateParam($row['layoutID'], _INT);
                $layout->layout = Kit::ValidateParam($row['layout'], _STRING);
                $layout->description = Kit::ValidateParam($row['description'], _STRING);
                $layout->tags = Kit::ValidateParam($row['tags'], _STRING);
                $layout->ownerId = Kit::ValidateParam($row['userID'], _INT);
                $layout->xml = Kit::ValidateParam($row['xml'], _HTMLSTRING);
                $layout->campaignId = Kit::ValidateParam($row['CampaignID'], _INT);
                $layout->retired = Kit::ValidateParam($row['retired'], _INT);
                $layout->status = Kit::ValidateParam($row['status'], _INT);
                $layout->backgroundImageId = Kit::ValidateParam($row['backgroundImageId'], _INT);
                $layout->mediaOwnerId = Kit::ValidateParam($row['mediaownerid'], _INT);
                
                // Details for media assignment
                $layout->regionId = Kit::ValidateParam($row['regionid'], _STRING);
                $layout->lkLayoutMediaId = Kit::ValidateParam($row['lklayoutmediaid'], _INT);

                $entries[] = $layout;
            }
          
            return $entries;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            return false;
        }
    }

    /**
     * Add a layout
     * @param <type> $layout
     * @param <type> $description
     * @param <type> $tags
     * @param <type> $userid
     * @param <type> $templateId
     * @param <type> $templateId
     * @param <string> $xml Use the provided XML instead of a template
     * @return <type>
     */
    public function Add($layout, $description, $tags, $userid, $templateId, $resolutionId, $xml = '')
    {
        Debug::LogEntry('audit', 'Adding new Layout', 'Layout', 'Add');

        try {
            $dbh = PDOConnect::init();
        
            $currentdate = date("Y-m-d H:i:s");

            // We must provide either a template or a resolution
            if ($templateId == 0 && $resolutionId == 0 && $xml == '')
                $this->ThrowError(__('To add a Layout either a Template or Resolution must be provided'));
        
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
            
            // Are we coming from a template?
            if ($templateId != '' && $templateId != 0) {
                // Copy the template layout and adjust
                if (!$id = $this->Copy($templateId, $layout, $description, $userid, true)) {
                    throw new Exception(__('Unable to use this template'));
                }
            }
            else {
                // We should use the resolution or the provided XML.
                if ($xml != '') {
                    $initialXml = $xml;
                }
                else {
                    // Do we have a template?
                    if (!$initialXml = $this->GetInitialXml($resolutionId, $userid))
                        throw new Exception(__('Unable to get initial XML'));
                }
            
                Debug::LogEntry('audit', 'Retrieved template xml', 'Layout', 'Add');

                $SQL  = 'INSERT INTO layout (layout, description, userID, createdDT, modifiedDT, xml, status)';
                $SQL .= ' VALUES (:layout, :description, :userid, :createddt, :modifieddt, :xml, :status)';

                $sth = $dbh->prepare($SQL);
                $sth->execute(array(
                        'layout' => $layout,
                        'description' => $description,
                        'userid' => $userid,
                        'createddt' => $currentdate,
                        'modifieddt' => $currentdate,
                        'xml' => $initialXml,
                        'status' => 3
                    ));

                $id = $dbh->lastInsertId();

                // Create a campaign
                $campaign = new Campaign($this->db);
        
                $campaignId = $campaign->Add($layout, 1, $userid);
                $campaign->Link($campaignId, $id, 0);

                // What permissions should we create this with?
                if (Config::GetSetting('LAYOUT_DEFAULT') == 'public') {
                    
                    $security = new CampaignSecurity($this->db);
                    $security->LinkEveryone($campaignId, 1, 0, 0);
                    
                    // Permissions on the new region(s)?
                    $layout = new Layout($this->db);

                    foreach($layout->GetRegionList($id) as $region) {
                        
                        $security = new LayoutRegionGroupSecurity($this->db);
                        $security->LinkEveryone($id, $region['regionid'], 1, 0, 0);
                    }
                }
            }
        
            // By this point we should have a layout record created        
            // Are there any tags?
            if ($tags != '') {
                // Create an array out of the tags
                $tagsArray = explode(',', $tags);
    
                // Add the tags XML to the layout
                if (!$this->EditTags($id, $tagsArray))
                    $this->ThrowError(__('Unable to edit tags'));
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
            
            $SQL  = 'UPDATE layout SET layout = :layout, description = :description, modifiedDT = :modifieddt, retired = :retired WHERE layoutID = :layoutid';

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layout' => $layout,
                    'description' => $description,
                    'modifieddt' => $currentdate,
                    'retired' => $retired,
                    'layoutid' => $layoutId
                ));
                
            // Create an array out of the tags
            $tagsArray = explode(',', $tags);
            
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
     * Gets the initial XML for a layout
     * @param <type> $resolutionId
     * @param <type> $templateId
     * @param <type> $userId
     */
    private function GetInitialXml($resolutionId, $userId)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Look up the width and height for the resolution
            $sth = $dbh->prepare('SELECT * FROM resolution WHERE resolutionid = :resolutionid');
            $sth->execute(array(
                    'resolutionid' => $resolutionId
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(__('Unknown Resolution'));

            // make some default XML
            $xmlDoc = new DOMDocument("1.0");
            $layoutNode = $xmlDoc->createElement("layout");

            $layoutNode->setAttribute("width", $row['intended_width']);
            $layoutNode->setAttribute("height", $row['intended_height']);
            $layoutNode->setAttribute("resolutionid", $resolutionId);
            $layoutNode->setAttribute("bgcolor", "#000000");
            $layoutNode->setAttribute("schemaVersion", $row['version']);

            $xmlDoc->appendChild($layoutNode);

            $newRegion = $xmlDoc->createElement('region');
            $newRegion->setAttribute('id', uniqid());
            $newRegion->setAttribute('userId', $userId);
            $newRegion->setAttribute('width', $row['intended_width']);
            $newRegion->setAttribute('height', $row['intended_height']);
            $newRegion->setAttribute('top', 0);
            $newRegion->setAttribute('left', 0);

            $layoutNode->appendChild($newRegion);

            $xml = $xmlDoc->saveXML();
            
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
     * Links a layout and tag
     * @param [string] $tag The Tag
     * @param [int] $layoutId The Layout
     */
    public function tag($tag, $layoutId)
    {
        $tagObject = new Tag();
        if (!$tagId = $tagObject->add($tag))
            return $this->SetError($tagObject->GetErrorMessage());

        try {
            $dbh = PDOConnect::init();

            // See if this tag exists
            $sth = $dbh->prepare('SELECT * FROM `lktaglayout` WHERE layoutId = :layoutId AND tagId = :tagId');
            $sth->execute(array(
                    'tagId' => $tagId,
                    'layoutId' => $layoutId
                ));

            if (!$row = $sth->fetch()) {
        
                $sth = $dbh->prepare('INSERT INTO `lktaglayout` (tagId, layoutId) VALUES (:tagId, :layoutId)');
                $sth->execute(array(
                        'tagId' => $tagId,
                        'layoutId' => $layoutId
                    ));
          
                return $dbh->lastInsertId();
            }
            else {
                return Kit::ValidateParam($row['lkTagLayoutId'], _INT);
            }
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Untag a layout
     * @param  [string] $tag The Tag
     * @param  [int] $layoutId The Layout Id
     */
    public function unTag($tag, $layoutId) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM `lktaglayout` WHERE tagId IN (SELECT tagId FROM tag WHERE tag = :tag) AND layoutId = :layoutId)');
            $sth->execute(array(
                    'tag' => $tag,
                    'layoutId' => $layoutId
                ));
          
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Untag all tags on a layout
     * @param  [int] $layoutId The Layout Id
     */
    public function unTagAll($layoutId) {
        Debug::Audit('IN');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM `lktaglayout` WHERE layoutId = :layoutId');
            $sth->execute(array(
                    'layoutId' => $layoutId
                ));
          
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
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
        
            // Untag all
            $this->unTagAll($layoutID);

            // Create the tags XML
            $tagsXml = '<tags>';
    
            foreach($tags as $tag) {
                $this->tag($tag, $layoutID);
                $tagsXml .= sprintf('<tag>%s</tag>', $tag);
            }
    
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
            $campaign = new Campaign($this->db);
            $campaignId = $campaign->GetCampaignId($layoutid);

            // Notify (dont error)
            if (!$this->delayFinalise) {
                $displayObject = new Display($this->db);
                $displayObject->NotifyDisplays($campaignId);
            }
        
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
    public function Copy($oldLayoutId, $newLayoutName, $newDescription, $userId, $copyMedia = false)
    {
        try {
            $dbh = PDOConnect::init();
        
            $currentdate = date("Y-m-d H:i:s");
            $campaign = new Campaign($this->db);
    
            // Include to media data class?
            if ($copyMedia) {
                $mediaObject = new Media($this->db);
                $mediaSecurity = new MediaGroupSecurity($this->db);
            }
    
            // We need the old campaignid
            $oldCampaignId = $campaign->GetCampaignId($oldLayoutId);
    
            // The Layout ID is the old layout
            $SQL  = "";
            $SQL .= " INSERT INTO layout (layout, xml, userID, description, retired, duration, backgroundImageId, createdDT, modifiedDT, status) ";
            $SQL .= " SELECT :layout, xml, :userid, :description, retired, duration, backgroundImageId, :createddt, :modifieddt, status ";
            $SQL .= "  FROM layout ";
            $SQL .= " WHERE layoutid = :layoutid";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layout' => $newLayoutName,
                    'description' => $newDescription,
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

            // Handle the Background
            $sth = $dbh->prepare('SELECT mediaId FROM lklayoutmedia WHERE layoutId = :layoutId AND regionId = :regionId');
            $sth->execute(array('layoutId' => $oldLayoutId, 'regionId' => 'background'));

            if ($row = $sth->fetch()) {
                // This layout does have a background image
                // Link it to the new one
                if (!$newLkId = $this->AddLk($newLayoutId, 'background', $row['mediaId']))
                    throw new Exception(__('Unable to link background'));
            }

            // Get all media nodes
            $xpath = new DOMXpath($this->DomXml);
    
            // Create an XPath to get all media nodes
            $mediaNodes = $xpath->query("//media");
    
            Debug::LogEntry('audit', 'About to loop through media nodes', 'layout', 'Copy');

            $copiesMade = array();
            
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
                    $newMediaId = md5(Kit::uniqueId());
                    
                    $mediaNode->setAttribute('id', $newMediaId);
    
                    // Copy media security
                    $security = new LayoutMediaGroupSecurity($this->db);
                    $security->CopyAllForMedia($oldLayoutId, $newLayoutId, $mediaId, $newMediaId);
                    continue;
                }

                // Library media assigned to the layout, it will have a lkid
                $lkId = $mediaNode->getAttribute('lkid');
    
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

                    // Update the permissions for this media on this layout
                    $security = new LayoutMediaGroupSecurity($this->db);
                    $security->CopyAllForMedia($oldLayoutId, $newLayoutId, $oldMediaId, $mediaId);
                }
                else {
                    // We haven't copied the media file, therefore we only want to copy permissions once per region
                    // this is due to https://github.com/xibosignage/xibo/issues/487
                    if (!isset($copiesMade[$regionId]) || !in_array($mediaId, $copiesMade[$regionId])) {
                        // Update the permissions for this media on this layout
                        $security = new LayoutMediaGroupSecurity($this->db);
                        $security->CopyAllForMedia($oldLayoutId, $newLayoutId, $oldMediaId, $mediaId);

                        $copiesMade[$regionId][] = $mediaId;
                    }
                }
    
                // Add the database link for this media record
                if (!$newLkId = $this->AddLk($newLayoutId, $regionId, $mediaId))
                    throw new Exception("Error Processing Request", 1);
    
                // Set this LKID on the media node
                $mediaNode->setAttribute('lkid', $newLkId);
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

            // Untag
            $this->unTagAll($layoutId);
        
            // Security
            $sth = $dbh->prepare('DELETE FROM lklayoutmediagroup WHERE layoutid = :layoutid');
            $sth->execute(array('layoutid' => $layoutId));

            $sth = $dbh->prepare('DELETE FROM lklayoutregiongroup WHERE layoutid = :layoutid');
            $sth->execute(array('layoutid' => $layoutId));

            // Media Links
            $sth = $dbh->prepare('DELETE FROM lklayoutmedia WHERE layoutid = :layoutid');
            $sth->execute(array('layoutid' => $layoutId));

            // Handle the deletion of the campaign
            $campaign = new Campaign();
            $campaignId = $campaign->GetCampaignId($layoutId);
    
            // Remove the Campaign (will remove links to this layout - orphaning the layout)
            if (!$campaign->Delete($campaignId))
                $this->ThrowError(25008, __('Unable to delete campaign'));

            // Remove the Layout from any display defaults
            $sth = $dbh->prepare('UPDATE `display` SET defaultlayoutid = 4 WHERE defaultlayoutid = :layoutid');
            $sth->execute(array('layoutid' => $layoutId));

            // Remove the Layout from any Campaigns
            if (!$campaign->unlinkAllForLayout($layoutId))
                $this->ThrowError($campaign->GetErrorMessage());
    
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
    public function AddLk($layoutid, $region, $mediaid)
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
    public function SetBackground($layoutId, $resolutionId, $color, $backgroundImageId, $zindex = NULL) {
        Debug::LogEntry('audit', 'IN', 'Layout', 'SetBackground');
        
        try {
            $dbh = PDOConnect::init();
                
            if ($layoutId == 0)
                $this->ThrowError(__('Layout not selected'));
    
            if ($resolutionId == 0)
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

                // Tag the background image as a background image
                $media = new Media();
                $media->tag('background', $backgroundImageId);
            }
        
            // Look up the width and the height
            $sth = $dbh->prepare('SELECT intended_width, intended_height, width, height, version FROM resolution WHERE resolutionID = :resolutionid');
            $sth->execute(array(
                'resolutionid' => $resolutionId
            ));

            // Look up the bg image from the media id given
            if (!$row = $sth->fetch())
                return $this->SetError(__('Unable to get the Resolution information'));

            $version = Kit::ValidateParam($row['version'], _INT);

            if ($version == 1) {
                $width  =  Kit::ValidateParam($row['width'], _INT);
                $height =  Kit::ValidateParam($row['height'], _INT);
            }
            else {
                $width  =  Kit::ValidateParam($row['intended_width'], _INT);
                $height =  Kit::ValidateParam($row['intended_height'], _INT);
            }

            $region = new region($this->db);
            $region->delayFinalise = $this->delayFinalise;

            if (!$region->EditBackground($layoutId, $color, $bg_image, $width, $height, $resolutionId, $zindex))
                throw new Exception("Error Processing Request", 1);
                    
            // Update the layout record with the new background
            $sth = $dbh->prepare('UPDATE layout SET backgroundimageid = :backgroundimageid WHERE layoutid = :layoutid');
            $sth->execute(array(
                'backgroundimageid' => $backgroundImageId,
                'layoutid' => $layoutId
            ));

            // Check to see if we already have a LK record for this.
            $lkSth = $dbh->prepare('SELECT lklayoutmediaid FROM `lklayoutmedia` WHERE layoutid = :layoutid AND regionID = :regionid');
            $lkSth->execute(array('layoutid' => $layoutId, 'regionid' => 'background'));

            if ($lk = $lkSth->fetch()) {
                // We have one
                if ($backgroundImageId != 0) {
                    // Update it
                    if (!$region->UpdateDbLink($lk['lklayoutmediaid'], $backgroundImageId))
                        $this->ThrowError(__('Unable to update background link'));
                }
                else {
                    // Delete it
                    if (!$region->RemoveDbLink($lk['lklayoutmediaid']))
                        $this->ThrowError(__('Unable to remove background link'));
                }
            }
            else {
                // None - do we need one?
                if ($backgroundImageId != 0) {
                    if (!$region->AddDbLink($layoutId, 'background', $backgroundImageId))
                        $this->ThrowError(__('Unable to create background link'));
                }
            }
    
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

    public function EditBackgroundImage($layoutId, $backgroundImageId)
    {
        try {
            $dbh = PDOConnect::init();
                
            if ($layoutId == 0)
                $this->ThrowError(__('Layout not selected'));
    
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

                // Tag the background image as a background image
                $media = new Media();
                $media->tag('background', $backgroundImageId);
            }

            $region = new region();
            
            if (!$region->EditBackgroundImage($layoutId, $bg_image))
                throw new Exception("Error Processing Request", 1);
                    
            // Update the layout record with the new background
            $sth = $dbh->prepare('UPDATE layout SET backgroundimageid = :backgroundimageid WHERE layoutid = :layoutid');
            $sth->execute(array(
                'backgroundimageid' => $backgroundImageId,
                'layoutid' => $layoutId
            ));

            // Check to see if we already have a LK record for this.
            $lkSth = $dbh->prepare('SELECT lklayoutmediaid FROM `lklayoutmedia` WHERE layoutid = :layoutid AND regionID = :regionid');
            $lkSth->execute(array('layoutid' => $layoutId, 'regionid' => 'background'));

            if ($lk = $lkSth->fetch()) {
                // We have one
                if ($backgroundImageId != 0) {
                    // Update it
                    if (!$region->UpdateDbLink($lk['lklayoutmediaid'], $backgroundImageId))
                        $this->ThrowError(__('Unable to update background link'));
                }
                else {
                    // Delete it
                    if (!$region->RemoveDbLink($lk['lklayoutmediaid']))
                        $this->ThrowError(__('Unable to remove background link'));
                }
            }
            else {
                // None - do we need one?
                if ($backgroundImageId != 0) {
                    if (!$region->AddDbLink($layoutId, 'background', $backgroundImageId))
                        $this->ThrowError(__('Unable to create background link'));
                }
            }
    
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

            // Dummy DB object (if necessary)
            if ($this->db == NULL) {
                $this->db = new Database();
            }
    
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
                    $tmpModule = ModuleFactory::load($mediaType, $layoutId, $region['regionid'], $mediaId, $lkId, $this->db, $user);
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
     * Upgrade a Layout between schema versions
     * @param int $layoutId
     * @param int $resolutionId
     * @param int $scaleContent
     * @return bool
     */
    public function upgrade($layoutId, $resolutionId, $scaleContent)
    {
        // Get the Layout XML
        $this->SetDomXml($layoutId);

        // Get the Schema Versions
        $layoutVersion = (int)$this->DomXml->documentElement->getAttribute('schemaVersion');
        $width = (int)$this->DomXml->documentElement->getAttribute('width');
        $height = (int)$this->DomXml->documentElement->getAttribute('height');
        $color = $this->DomXml->documentElement->getAttribute('bgcolor');
        $version = Config::Version('XlfVersion');

        // Get some more info about the layout
        try {
            $dbh = PDOConnect::init();
            $sth = $dbh->prepare('SELECT backgroundImageId FROM `layout` WHERE layoutId = :layoutId');
            $sth->execute(array(
                'layoutId' => $layoutId
            ));
    
            // Look up the bg image from the media id given
            if (!$row = $sth->fetch())
                $this->ThrowError(__('Unable to get the Layout information'));  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
        
        Debug::Audit('Updating layoutId: ' . $layoutId . ' from version: ' . $layoutVersion . ' to: ' . $version);

        // Upgrade
        $this->delayFinalise = true;

        // Set the background
        $this->SetBackground($layoutId, $resolutionId, $color, $row['backgroundImageId']);

        // Get the Layout XML again (now that we have set the background)
        $this->SetDomXml($layoutId);

        // Get the Width and Height back out
        $updatedWidth = (int)$this->DomXml->documentElement->getAttribute('width');
        $updatedHeight = (int)$this->DomXml->documentElement->getAttribute('height');
        
        // Work out the ratio
        $ratio = min($updatedWidth / $width, $updatedHeight / $height);

        // Get all the regions.
        foreach ($this->GetRegionList($layoutId) as $region) {
            // New region object each time, because the region stores the layout xml
            $regionObject = new Region();
            $regionObject->delayFinalise = $this->delayFinalise;

            // Work out a new width and height
            $newWidth = $region['width'] * $ratio;
            $newHeight = $region['height'] * $ratio;
            $newTop = $region['top'] * $ratio;
            $newLeft = $region['left'] * $ratio;

            $regionObject->EditRegion($layoutId, $region['regionid'], $newWidth, $newHeight, $newTop, $newLeft, $region['name']);

            if ($scaleContent == 1) {
                Debug::Audit('Updating the scale of media in regionId ' . $region['regionid']);
                // Also update the width, height and font-size on each media item

                foreach ($regionObject->GetMediaNodeList($layoutId, $region['regionid']) as $mediaNode) {
                    // Run some regular expressions over each, to adjust the values by the ratio we have calculated.
                    // widths
                    $mediaId = $mediaNode->getAttribute('id');
                    $lkId = $mediaNode->getAttribute('lkid');
                    $mediaType = $mediaNode->getAttribute('type');

                    // Create a media module to handle all the complex stuff
                    $tmpModule = ModuleFactory::load($mediaType, $layoutId, $region['regionid'], $mediaId, $lkId);

                    // Get the XML
                    $mediaXml = $tmpModule->asXml();

                    // Replace widths
                    $mediaXml = preg_replace_callback(
                        '/width:(.*?)/',
                        function ($matches) use ($ratio) {
                            return "width:" . $matches[1] * $ratio;
                        }, $mediaXml);

                    // Replace heights
                    $mediaXml = preg_replace_callback(
                        '/height:(.*?)/',
                        function ($matches) use ($ratio) {
                            return "height:" . $matches[1] * $ratio;
                        }, $mediaXml);

                    // Replace fonts
                    $mediaXml = preg_replace_callback(
                        '/font-size:(.*?)px;/',
                        function ($matches) use ($ratio) {
                            return "font-size:" . $matches[1] * $ratio . "px;";
                        }, $mediaXml);

                    // Save this new XML
                    $tmpModule->SetMediaXml($mediaXml);
                }
            }
        }

        $this->delayFinalise = false;
        $this->SetValid($layoutId);

        return true;
    }

    /**
     * Set the Validity of this Layout
     * @param [int] $layoutId [The Layout Id]
     */
    public function SetValid($layoutId) {
        // Delay?
        if ($this->delayFinalise)
            return;

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

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT * FROM `layout` WHERE layoutid = :layout_id');
            $sth->execute(array('layout_id' => $layoutId));
          
            $rows = $sth->fetchAll();

            if (count($rows) <= 0)
                $this->ThrowError(__('Unable to find layout'));

            $row = $rows[0];

            $info['layout'] = Kit::ValidateParam($row['layout'], _STRING);
            $modifiedDt = new DateTime($row['modifiedDT']);
            $info['updated'] = $modifiedDt->getTimestamp();
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }

        // Get the width and height
        $xml = new DOMDocument();
        $xml->loadXML($row['xml']);

        // get the width and the height
        $info['width'] = $xml->documentElement->getAttribute('width');
        $info['height'] = $xml->documentElement->getAttribute('height');

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
                        'mediatype' => $mediaNode->getAttribute('type'),
                        'render' => $mediaNode->getAttribute('render'),
                        'userid' => $mediaNode->getAttribute('userid'),
                        'updated' => $info['updated']
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

    /**
     * Export a layout.
     * @param [type] $layoutId [description]
     */
    function Export($layoutId) {

        if ($layoutId == 0 || $layoutId == '')
            return $this->SetError(__('Must provide layoutId'));

        $config = new Config();
        if (!$config->CheckZip())
            return $this->SetError(__('Zip is not enabled on this server'));

        $libraryPath = Config::GetSetting('LIBRARY_LOCATION');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                SELECT layout, description, backgroundImageId, xml
                  FROM layout
                 WHERE layoutid = :layoutid');

            $sth->execute(array('layoutid' => $layoutId));
        
            if (!$row = $sth->fetch())
                $this->ThrowError(__('Layout not found.'));

            // Open a ZIP file with the same name as the layout
            File::EnsureLibraryExists();
            $zip = new ZipArchive();
            $fileName = $libraryPath . 'temp/export_' . Kit::ValidateParam($row['layout'], _FILENAME) . '.zip';
            $result = $zip->open($fileName, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
            if ($result !== true)
                $this->ThrowError(__('Can\'t create ZIP. Error Code: ' . $result));
            
            // Add layout information to the ZIP
            $layout = array(
                    'layout' => Kit::ValidateParam($row['layout'], _STRING),
                    'description' => Kit::ValidateParam($row['description'], _STRING)
                );

            $zip->addFromString('layout.json', json_encode($layout));

            // Add the layout XLF
            $xml = $row['xml'];
            $zip->addFromString('layout.xml', $xml);

            $params = array('layoutid' => $layoutId, 'excludeType' => 'module');
            $SQL = ' 
                SELECT media.mediaid, media.name, media.storedAs, originalFileName, type, duration
                  FROM `media` 
                    INNER JOIN `lklayoutmedia`
                    ON lklayoutmedia.mediaid = media.mediaid
                 WHERE lklayoutmedia.layoutid = :layoutid
                   AND media.type <> :excludeType
                ';

            // Add the media to the ZIP
            $mediaSth = $dbh->prepare($SQL);
            $mediaSth->execute($params);

            $mappings = array();

            foreach ($mediaSth->fetchAll() as $media) {
                $mediaFilePath = $libraryPath . $media['storedAs'];
                $zip->addFile($mediaFilePath, 'library/' . $media['originalFileName']);

                $mappings[] = array(
                    'file' => $media['originalFileName'], 
                    'mediaid' => $media['mediaid'], 
                    'name' => $media['name'],
                    'type' => $media['type'],
                    'duration' => $media['duration'],
                    'background' => ($media['mediaid'] == $row['backgroundImageId']) ? 1 : 0
                    );
            }

            // Add the mappings file to the ZIP
            $zip->addFromString('mapping.json', json_encode($mappings));
    
            $zip->close();
    
            // Uncomment only if you are having permission issues
            // chmod($fileName, 0777);
    
            // Push file back to browser
            if (ini_get('zlib.output_compression')) {
                ini_set('zlib.output_compression', 'Off');      
            }

            $size = filesize($fileName);

            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary"); 
            header("Content-disposition: attachment; filename=\"" . basename($fileName) . "\"");
    
            //Output a header
            header('Pragma: public');
            header('Cache-Control: max-age=86400');
            header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
            header('Content-Length: ' . $size);
            
            // Send via Apache X-Sendfile header?
            if (Config::GetSetting('SENDFILE_MODE') == 'Apache') {
                header("X-Sendfile: $fileName");
                exit();
            }

            // Send via Nginx X-Accel-Redirect?
            if (Config::GetSetting('SENDFILE_MODE') == 'Nginx') {
                header("X-Accel-Redirect: /download/temp/" . basename($fileName));
                exit();
            }
            
            // Return the file with PHP
            // Disable any buffering to prevent OOM errors.
            @ob_end_clean();
            @ob_end_flush();
            readfile($fileName);
    
            exit;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    function Import($zipFile, $layout, $userId, $template, $replaceExisting, $importTags, $delete = true) {
        // I think I might add a layout and then import
        
        if (!file_exists($zipFile))
            return $this->SetError(__('File does not exist'));

        // Open the Zip file
        $zip = new ZipArchive();
        if (!$zip->open($zipFile))
            return $this->SetError(__('Unable to open ZIP'));

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT mediaid, storedAs FROM `media` WHERE name = :name AND IsEdited = 0');
            
            // Get the layout details
            $layoutDetails = json_decode($zip->getFromName('layout.json'), true);

            // Set the layout name
            $layout = (($layout != '') ? $layout : $layoutDetails['layout']);
            $description = (isset($layoutDetails['description']) ? $layoutDetails['description'] : '');

            // Get the layout xml
            $xml = $zip->getFromName('layout.xml');
    
            // Add the layout
            if (!$layoutId = $this->Add($layout, $description, NULL, $userId, NULL, NULL, $xml))
                return false;

            // Either remove out the tags, or add them to the DB
            if ($importTags) {
                // Pull the tags out of the XML
                $xmlDoc = new DOMDocument();
                $xmlDoc->loadXML($xml);

                $xpath = new DOMXPath($xmlDoc);
                $tagsNode = $xpath->query("//tags");

                foreach ($tagsNode as $tag) {
                    $this->tag($tag->nodeValue, $layoutId);
                }
            }
            else {
                $this->EditTags($layoutId, array());
            }

            // Are we a template?
            if ($template)
                $this->tag('template', $layoutId);

            // Tag as imported
            $this->tag('imported', $layoutId);

            // Set the DOM XML
            $this->SetDomXml($layoutId);

            // Set the user on each region
            foreach ($this->DomXml->getElementsByTagName('region') as $region) {
                $region->setAttribute('userId', $userId);
            }

            // Set the user on each media node
            foreach ($this->DomXml->getElementsByTagName('media') as $media) {
                $media->setAttribute('userId', $userId);
            }

            // We will need a file object and a media object
            $fileObject = new File();
            $mediaObject = new Media();
            $currentType = '';
    
            // Go through each region and add the media (updating the media ids)
            $mappings = json_decode($zip->getFromName('mapping.json'), true);
    
            foreach($mappings as $file) {

                Debug::LogEntry('audit', 'Found file ' . json_encode($file));

                // Do we need to recharge our media object
                if ($currentType != '' && $file['type'] != $currentType) {
                    $mediaObject = new Media();
                }

                // Set the current type
                $currentType = $file['type'];

                // Does a media item with this name already exist?
                $sth->execute(array('name' => $file['name']));
                $rows = $sth->fetchAll();

                if (count($rows) > 0) {
                    if ($replaceExisting) {
                        // Alter the name of the file and add it
                        $file['name'] = 'import_' . $layout . '_' . uniqid();

                        // Add the file
                        if (!$fileId = $fileObject->NewFile($zip->getFromName('library/' . $file['file']), $userId))
                            return $this->SetError(__('Unable to add a media item'));

                        // Add this media to the library
                        if (!$mediaId = $mediaObject->Add($fileId, $file['type'], $file['name'], $file['duration'], $file['file'], $userId))
                            return $this->SetError($mediaObject->GetErrorMessage());

                        // Tag it
                        $mediaObject->tag('imported', $mediaId);
                    }
                    else {
                        // Don't add the file, use the one that already exists
                        $mediaObject->mediaId = $rows[0]['mediaid'];
                        $mediaObject->storedAs = $rows[0]['storedAs'];
                    }
                }
                else {
                    // Add the file
                    if (!$fileId = $fileObject->NewFile($zip->getFromName('library/' . $file['file']), $userId))
                        return $this->SetError(__('Unable to add a media item'));

                    // Add this media to the library
                    if (!$mediaId = $mediaObject->Add($fileId, $file['type'], $file['name'], $file['duration'], $file['file'], $userId))
                        return $this->SetError($mediaObject->GetErrorMessage());

                    // Tag it
                    $mediaObject->tag('imported', $mediaId);
                }

                Debug::LogEntry('audit', 'Post File Import Fix', get_class(), __FUNCTION__);
    
                // Get this media node from the layout using the old media id
                if (!$this->PostImportFix($userId, $layoutId, $file['mediaid'], $mediaObject->mediaId, $mediaObject->storedAs, $file['background']))
                    return false;
            }

            Debug::LogEntry('audit', 'Saving XLF', get_class(), __FUNCTION__);

            // Save the updated XLF
            if (!$this->SetLayoutXml($layoutId, $this->DomXml->saveXML()))
                return false;

            $this->SetValid($layoutId);

            // Finished, so delete
            $zip->close();

            if ($delete)
                @unlink($zipFile);

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function PostImportFix($userId, $layoutId, $oldMediaId, $newMediaId, $storedAs = '', $background = 0) {
        
        Debug::LogEntry('audit', 'Swapping ' . $oldMediaId . ' for ' . $newMediaId, get_class(), __FUNCTION__);

        // Are we the background image?
        if ($background == 1) {
            // Background Image
            $this->DomXml->documentElement->setAttribute('background', $storedAs);

            // Add the ID to the layout record.
            try {
                $dbh = PDOConnect::init();
            
                $sth = $dbh->prepare('UPDATE `layout` SET backgroundImageId = :mediaId WHERE layoutId = :layoutId');
                $sth->execute(array(
                        'mediaId' => $newMediaId,
                        'layoutId' => $layoutId
                    ));

                // Link
                $this->AddLk($layoutId, 'background', $newMediaId);
            }
            catch (Exception $e) {
                
                Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
            
                if (!$this->IsError())
                    $this->SetError(1, __('Unknown Error'));
            
                return false;
            }
        }
        else {
            // Media Items
            $xpath = new DOMXPath($this->DomXml);
            $mediaNodeList = $xpath->query('//media[@id=' . $oldMediaId . ']');

            foreach ($mediaNodeList as $node) {
                // Update the ID
                $node->setAttribute('id', $newMediaId);

                // Update Owner
                $node->setAttribute('userId', $userId);

                // Update the URI option
                // Get the options node from this document
                $optionNodes = $node->getElementsByTagName('options');

                // There is only 1
                $optionNode = $optionNodes->item(0);

                // Get the option node for the URI
                $oldUriNode = $xpath->query('.//uri', $optionNode);

                // Create a new uri option node and use it as a replacement for this one.
                $newNode = $this->DomXml->createElement('uri', $storedAs);

                if ($oldUriNode->length == 0) {
                    
                    // Append the new node to the list
                    $optionNode->appendChild($newNode);
                }
                else {
                    
                    // Replace the old node we found with XPath with the new node we just created
                    $optionNode->replaceChild($newNode, $oldUriNode->item(0));
                }
                
                // Get the parent node (the region node)
                $regionId = $node->parentNode->getAttribute('id');

                Debug::LogEntry('audit', 'Adding Link ' . $regionId, get_class(), __FUNCTION__);

                // Insert a link
                Kit::ClassLoader('region');
                $region = new Region($this->db);
                if (!$lkId = $region->AddDbLink($layoutId, $regionId, $newMediaId))
                    return false;

                // Attach this lkid to the media item
                $node->setAttribute("lkid", $lkId);
            }
        }

        return true;
    }

    /**
     * Import a Folder of ZIP files
     * @param  [string] $folder The folder to import
     */
    public function importFolder($folder) 
    {
        Debug::Audit('Importing folder: ' . $folder);

        if (is_dir($folder)) {
            // Get a list of files
            foreach (array_diff(scandir($folder), array('..', '.')) as $file) {

                Debug::Audit('Found file: ' . $file);

                if (stripos($file, '.zip'))
                    $this->Import($folder . DIRECTORY_SEPARATOR . $file, NULL, 1, false, false, true, false);
            }
        }
    }

    /**
     * Delete all layouts for a user
     * @param int $userId
     * @return bool
     */
    public function deleteAllForUser($userId)
    {
        $layouts = $this->Entries(null, array('userId' => $userId));

        foreach ($layouts as $layout) {
            /* @var Layout $layout */
            if (!$layout->Delete($layout->layoutId))
                return $this->SetError($layout->GetErrorMessage());
        }

        return true;
    }

    /**
     * Set the owner
     * @param int $layoutId
     * @param int $userId
     */
    public static function setOwner($layoutId, $userId)
    {
        $dbh = PDOConnect::init();

        $params = array(
            'userId' => $userId,
            'layoutId' => $layoutId
        );

        $sth = $dbh->prepare('UPDATE `layout` SET userId = :userId WHERE layoutId = :layoutId');
        $sth->execute($params);

        \Xibo\Helper\Log::audit('layout', $layoutId, 'Changing Ownership', $params);
    }
}
