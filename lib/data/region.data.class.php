<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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

require_once("lib/pages/module.class.php");
Kit::ClassLoader('layout');

class Region extends Data
{
    // Caching
    private $layoutXml;
    private $layoutDocument;

    public $delayFinalise = false;
    
    /**
     * Gets the Xml for the specified layout
     * @return 
     * @param $layoutid Object
     */
    public function GetLayoutXml($layoutid)
    {
        if ($this->layoutXml == '') {
            $layout = new Layout();
            $this->layoutXml = $layout->GetLayoutXml($layoutid);
        }

        return $this->layoutXml;
    }

    public function GetLayoutDom($layoutId) {

        if ($this->layoutDocument == NULL) {
            // Load the XML into a new DOMDocument
            $this->layoutDocument = new DOMDocument();
            $this->layoutDocument->loadXML($this->GetLayoutXml($layoutId));
        }

        return $this->layoutDocument;
    }
    
    /**
     * Sets the Layout Xml and writes it back to the database
     * @return 
     * @param $layoutid Object
     * @param $xml Object
     */
    private function SetLayoutXml($layoutid, $xml)
    {
        // Update Cache
        $this->layoutXml = $xml;

        $layout = new Layout($this->db);
        $layout->delayFinalise = $this->delayFinalise;

        if (!$layout->SetLayoutXml($layoutid, $xml))
            return $this->SetError($layout->GetErrorMessage());

        return true;
    }
    
    /**
     * Adds a region to the specified layoutid
     * @param $layoutid Object
     * @param $regionid Object[optional]
     * @return string The region id
     */
    public function AddRegion($layoutid, $userid, $regionid = "", $width = 250, $height = 250, $top = 50, $left = 50, $name = '')
    {
        Debug::LogEntry('audit', 'LayoutId: ' . $layoutid . ', Width: ' . $width . ', Height: ' . $height . ', Top: ' . $top . ', Left: ' . $left . ', Name: ' . $name . '.', 'region', 'AddRegion');

        //Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutid));

        //Do we have a region ID provided?
        if ($regionid == '')
            $regionid = Kit::uniqueId();

        // Validation
        if (!is_numeric($width) || !is_numeric($height) || !is_numeric($top) || !is_numeric($left))
            return $this->SetError(__('Size and coordinates must be generic'));

        if ($width <= 0)
            return $this->SetError(__('Width must be greater than 0'));

        if ($height <= 0)
            return $this->SetError(__('Height must be greater than 0'));

        // make a new region node
        $newRegion = $xml->createElement("region");

        if ($name != '') 
            $newRegion->setAttribute('name', $name);

        $newRegion->setAttribute('id', $regionid);
        $newRegion->setAttribute('userId', $userid);
        $newRegion->setAttribute('width', $width);
        $newRegion->setAttribute('height', $height);
        $newRegion->setAttribute('top', $top);
        $newRegion->setAttribute('left', $left);

        $xml->firstChild->appendChild($newRegion);

        if (!$this->SetLayoutXml($layoutid, $xml->saveXML())) 
            return false;

        // What permissions should we create this with?
        if (Config::GetSetting('LAYOUT_DEFAULT') == 'public')
        {
            Kit::ClassLoader('layoutregiongroupsecurity');
            $security = new LayoutRegionGroupSecurity($this->db);
            $security->LinkEveryone($layoutid, $regionid, 1, 0, 0);
        }

        // Update layout status
        Kit::ClassLoader('Layout');
        $layout = new Layout($this->db);
        $layout->SetValid($layoutid, true);

        return $regionid;
    }
    
    public function DeleteRegion($layoutid, $regionid)
    {
        //Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutid));
        
        //Do we have a region ID provided?
        if ($regionid == "")
            return $this->SetError(__("No region ID provided, cannot delete"));
        
        //Get this region from the layout (xpath)
        $xpath = new DOMXPath($xml);
        
        $regionNodeList = $xpath->query("//region[@id='$regionid']");
        $regionNode = $regionNodeList->item(0);
        
        //Look at each media node...
        $mediaNodes = $regionNode->getElementsByTagName('media');
        
        //Remove the media layout link if appropriate
        foreach ($mediaNodes as $mediaNode)
        {
            //see if there is an LkId set
            $lkid = $mediaNode->getAttribute("lkid");
            
            //If there is, then Remove that link
            if ($lkid != "")
            {
                if (!$this->RemoveDbLink($lkid)) 
                    return false;
            }
        }
        
        //Remove the region node
        $xml->firstChild->removeChild($regionNode);

        if (!$this->SetLayoutXml($layoutid, $xml->saveXML())) 
            return false;
        
        // Update layout status
        Kit::ClassLoader('Layout');
        $layout = new Layout($this->db);
        $layout->SetValid($layoutid, true);

        return true;
    }
    
    /**
     * Adds the media to this region
     * @return 
     */
    public function AddMedia($layoutid, $regionid, $regionSpecific, $mediaXmlString) 
    {
        $user   =& $this->user;
        
        //Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutid));
            
        Debug::LogEntry("audit", $mediaXmlString, "region", "AddMedia");
        
        //Get the media's Xml
        $mediaXml = new DOMDocument("1.0");
        
        //Load the Media's XML into a SimpleXML object
        $mediaXml->loadXML($mediaXmlString);

        // Get the Media ID from the mediaXml node
        $mediaid = $mediaXml->documentElement->getAttribute('id');
        
        // Do we need to add a Link here?
        if ($regionSpecific == 0)
        {
            // Add the DB link
            $lkid = $this->AddDbLink($layoutid, $regionid, $mediaid);
            
            // Attach this lkid to the media item
            $mediaXml->documentElement->setAttribute("lkid", $lkid);
        }
        
        //Find the region in question
        $xpath = new DOMXPath($xml);
        
        //Xpath for it
        $regionNodeList = $xpath->query("//region[@id='$regionid']");
        $regionNode = $regionNodeList->item(0);
        
        //Import the new media node into this document
        $newMediaNode = $xml->importNode($mediaXml->documentElement, true);
        
        //Add the new imported node to the regionNode we got from the xpath
        $regionNode->appendChild($newMediaNode);
        
        //Convert back to XML
        $xml = $xml->saveXML();

        // What permissions should we assign this with?
        if (Config::GetSetting('MEDIA_DEFAULT') == 'public')
        {
            Kit::ClassLoader('layoutmediagroupsecurity');

            $security = new LayoutMediaGroupSecurity($this->db);
            $security->LinkEveryone($layoutid, $regionid, $mediaid, 1, 0, 0);
        }
        
        if (!$this->SetLayoutXml($layoutid, $xml)) 
            return false;

        // Update layout status
        Kit::ClassLoader('Layout');
        $layout = new Layout($this->db);
        $layout->SetValid($layoutid, true);
        
        return true;
    }
    
    /**
     * Adds a db link record for the layout, media, region combination
     * @return 
     * @param $layoutid Object
     * @param $region Object
     * @param $mediaid Object
     */
    public function AddDbLink($layoutid, $region, $mediaid)
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
                $this->SetError(__("Database error adding this link record."));
        
            return false;
        }
    }
    
    /**
     * Updates the specified DbLink to the media ID provided
     * @return 
     * @param $lkid Object
     * @param $mediaid Object
     */
    public function UpdateDbLink($lkid, $mediaid)
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('UPDATE lklayoutmedia SET mediaid = :mediaid WHERE lklayoutmediaID = :lkid');
            $sth->execute(array(
                    'mediaid' => $mediaid,
                    'lkid' => $lkid
                ));

            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(__("Database error updating this link record."));
        
            return false;
        }
    }
    
    /**
     * Removes the DBlink for records for the given id's
     * @return 
     */
    public function RemoveDbLink($lkid)
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lklayoutmedia WHERE lklayoutmediaID = :lkid');
            $sth->execute(array(
                    'lkid' => $lkid
                ));
                
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(__("Database error deleting this link record."));
        
            return false;
        }
    }
    
    public function RemoveMedia($layoutid, $regionid, $lkid, $mediaid) 
    {
        //Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutid));
        
        //Should we use the LkID or the mediaID
        if ($lkid != "")
        {
            //Get the media node
            $xpathQuery = "//region[@id='$regionid']/media[@lkid='$lkid']";
            
            if (!$this->RemoveDbLink($lkid)) 
                return false;
        }
        else
        {
            $xpathQuery = "//region[@id='$regionid']/media[@id='$mediaid']";
        }
        
        //Find the region in question
        $xpath = new DOMXPath($xml);
        
        //Xpath for it
        $mediaNodeList  = $xpath->query($xpathQuery);
        $mediaNode      = $mediaNodeList->item(0);

        if ($mediaNode == null) {
            // Protect against corrupted layouts with left over lklayoutmedia records.
            Debug::Error('Cannot find this media in layoutId ' . $layoutid . ' using ' . $xpathQuery);
            return true;
        }
        
        $mediaNode->parentNode->removeChild($mediaNode);
        
        //Convert back to XML
        $xml = $xml->saveXML();
        
        if (!$this->SetLayoutXml($layoutid, $xml)) 
            return false;
        
        // Update layout status
        Kit::ClassLoader('Layout');
        $layout = new Layout($this->db);
        $layout->SetValid($layoutid, true);

        return true;
    }
    
    /**
     * Moves the MediaID node to the Position speficied by $sequence within a region
     * @return 
     * @param $layoutid Object
     * @param $region Object
     * @param $mediaid Object
     * @param $sequence Object
     */
    public function ReorderMedia($layoutid, $regionid, $mediaid, $sequence, $lkid = '')
    {
        Debug::LogEntry('audit', 'LkID = ' . $lkid, 'region', 'ReorderMedia');

        //Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutid));

        //Get the Media Node in question in a DOMNode using Xpath
        $xpath = new DOMXPath($xml);

        if ($lkid == '')
            $mediaNodeList = $xpath->query("//region[@id='$regionid']/media[@id='$mediaid']");
        else
            $mediaNodeList = $xpath->query("//region[@id='$regionid']/media[@lkid='$lkid']");

        $mediaNode = $mediaNodeList->item(0);
        
        //Remove this node from its parent
        $mediaNode->parentNode->removeChild($mediaNode);
        
        //Get a NodeList of the Region specified (using XPath again)
        $regionNodeList = $xpath->query("//region[@id='$regionid']/media");
        
        //Get the $sequence node from the list
        $mediaSeq = $regionNodeList->item($sequence);
        
        //Insert the Media Node in question before this $sequence node
        $mediaSeq->parentNode->insertBefore($mediaNode, $mediaSeq);
        
        //Done      
        
        //Convert back to XML
        $xml = $xml->saveXML();
        
        //Save it
        if (!$this->SetLayoutXml($layoutid, $xml)) 
            return false;

        // Update layout status
        Kit::ClassLoader('Layout');
        $layout = new Layout($this->db);
        $layout->SetValid($layoutid, true);
        
        //Its swapped
        return true;
    }
    
    /**
     * Swaps the media record
     * @return 
     * @param $layoutid Int
     * @param $regionid Int
     * @param $existingMediaid Int
     * @param $newMediaid Int
     * @param $mediaXml String
     */
    public function SwapMedia($layoutid, $regionid, $lkid, $existingMediaid, $newMediaid, $mediaXmlString)
    {
        $user   =& $this->user;
        
        // Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutid));
            
        Debug::LogEntry("audit", 'Media String Given: ' . $mediaXmlString, "region", "SwapMedia");
        
        //Get the media's Xml
        $mediaXml = new DOMDocument("1.0");
        
        //Load the Media's XML into a SimpleXML object
        $mediaXml->loadXML($mediaXmlString);
        
        
        //Find the current media node
        $xpath = new DOMXPath($xml);
        
        //Should we use the LkID or the mediaID
        if ($lkid == "")
        {
            Debug::LogEntry("audit", "No link ID. Using mediaid", "region", "SwapMedia");
            $mediaNodeList = $xpath->query("//region[@id='$regionid']/media[@id='$existingMediaid']");
        }
        else
        {
            Debug::LogEntry("audit",  "Link ID detected, using for Xpath", "region", "SwapMedia");          
            $mediaNodeList = $xpath->query("//region[@id='$regionid']/media[@lkid='$lkid']");
        }
        
        // Get the old media node (the one we are to replace)
        if (!$oldMediaNode = $mediaNodeList->item(0))
            return false;
        
        //Get the LkId of the current record... if its not blank we want to update this link with the new id
        $currentLkid = $oldMediaNode->getAttribute("lkid");
        
        // This repairs records that have been saved without a link ID? Maybe
        if ($currentLkid != "")
        {
            Debug::LogEntry("audit", "Current Link ID = $currentLkid", "region", "SwapMedia");
            $this->UpdateDbLink($currentLkid, $newMediaid);
            
            $lkid = $currentLkid;
        }
        else
        {
            // Make a new link? Or assume a link already set? Or just give up?
        }
        
        Debug::LogEntry("audit", "Setting Link ID on new media node", "region", "SwapMedia");
        $mediaXml->documentElement->setAttribute("lkid", $lkid);
        
        Debug::LogEntry("audit", $mediaXml->saveXML(), "region", "SwapMedia");
        
        //Replace the Nodes
        $newMediaNode = $xml->importNode($mediaXml->documentElement, true);
        $oldMediaNode->parentNode->replaceChild($newMediaNode, $oldMediaNode);
        
        //Convert back to XML
        $xml = $xml->saveXML();
        
        //Save it
        if (!$this->SetLayoutXml($layoutid, $xml)) 
            return false;

        // Update layout status
        $layout = new Layout($this->db);
        $layout->SetValid($layoutid);
        
        //Its swapped
        return true;
    }
    
    public function EditBackground($layoutid, $bg_color, $bg_image, $width, $height, $resolutionId, $zindex = NULL)
    {
        //Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutid));
        
        //Alter the background properties
        $xml->documentElement->setAttribute("background", $bg_image);
        $xml->documentElement->setAttribute("bgcolor", $bg_color);
        $xml->documentElement->setAttribute('width', $width);
        $xml->documentElement->setAttribute('height', $height);
        $xml->documentElement->setAttribute('resolutionid', $resolutionId);
        $xml->documentElement->setAttribute("schemaVersion", Config::Version('XlfVersion'));

        if ($zindex != NULL && $zindex != 0)
            $xml->documentElement->setAttribute('zindex', $zindex);
        else
            $xml->documentElement->removeAttribute('zindex');
        
        // Convert back to XML
        if (!$this->SetLayoutXml($layoutid, $xml->saveXML())) 
            return false;

        // Update layout status
        $layout = new Layout($this->db);
        $layout->delayFinalise = $this->delayFinalise;
        $layout->SetValid($layoutid);
        
        // Its swapped
        return true;
    }

    public function EditBackgroundImage($layoutId, $backgroundImage)
    {
        // Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutId));
        
        // Alter the background properties
        $xml->documentElement->setAttribute("background", $backgroundImage);
        
        // Convert back to XML
        return ($this->SetLayoutXml($layoutId, $xml->saveXML()));
    }
    
    /**
     * Edits the region 
     * @return 
     * @param $layoutid Object
     * @param $regionid Object
     * @param $width Object
     * @param $height Object
     * @param $top Object
     * @param $left Object
     */
    public function EditRegion($layoutid, $regionid, $width, $height, $top, $left, $name = '', $options = '', $zindex = NULL)
    {
        Debug::LogEntry('audit', sprintf('IN - RegionID = %s. Width = %s. Height = %s, Top = %s, Left = %s, Name = %s', $regionid, $width, $height, $top, $left, $name), 'Region', 'EditRegion');

        try {
            $dbh = PDOConnect::init();

            // Validation
            if (!is_numeric($width) || !is_numeric($height) || !is_numeric($top) || !is_numeric($left))
                return $this->SetError(__('Size and coordinates must be numeric'));
    
            if ($width <= 0)
                return $this->SetError(__('Width must be greater than 0'));
    
            if ($height <= 0)
                return $this->SetError(__('Height must be greater than 0'));

            if ($zindex != null && $zindex <= 0)
                return $this->SetError(__('Layer must be greater than 0'));
            
            //Load the XML for this layout
            $xml = new DOMDocument("1.0");
            $xml->loadXML($this->GetLayoutXml($layoutid));
            
            //Find the region
            $xpath = new DOMXPath($xml);
            
            $regionNodeList = $xpath->query("//region[@id='$regionid']");
            $regionNode = $regionNodeList->item(0);
            
            if ($name != '') 
                $regionNode->setAttribute('name', $name);
            
            $regionNode->setAttribute('width',$width);
            $regionNode->setAttribute('height', $height);
            $regionNode->setAttribute('top', $top);
            $regionNode->setAttribute('left', $left);

            if ($zindex != NULL && $zindex != 0)
                $regionNode->setAttribute('zindex', $zindex);
            else
                $regionNode->removeAttribute('zindex');
    
            // If the userId is blank, then set it to be the layout user id?
            if (!$ownerId = $regionNode->getAttribute('userId'))
            {
                $sth = $dbh->prepare('SELECT userid FROM layout WHERE layoutid = :layoutid');
                $sth->execute(array(
                        'layoutid' => $layoutid
                    ));

                if (!$row = $sth->fetch())
                    throw new Exception("Error Processing Request", 1);
                    
                $ownerId = Kit::ValidateParam($row['userid'], _INT);
                $regionNode->setAttribute('userId', $ownerId);
            }
            
            // Do we need to set any options?
            if ($options != '')
            {
                // There will be an array of options
                foreach($options as $option)
                    $this->SetOption($xml, $regionid, $option['name'], $option['value']);
            }

            //Debug::LogEntry('audit', sprintf('Layout XML = %s', $xml->saveXML()), 'Region', 'EditRegion');
            
            //Convert back to XML       
            if (!$this->SetLayoutXml($layoutid, $xml->saveXML())) 
                return false;
    
            // Update layout status
            $layout = new Layout();
            $layout->delayFinalise = $this->delayFinalise;
            $layout->SetValid($layoutid);

            // Its swapped
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function GetOwnerId($layoutId, $regionId)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Load the XML for this layout
            $xml = new DOMDocument("1.0");
            $xml->loadXML($this->GetLayoutXml($layoutId));
    
            // Find the region
            $xpath = new DOMXPath($xml);
    
            $regionNodeList = $xpath->query("//region[@id='$regionId']");
            $regionNode = $regionNodeList->item(0);
    
            // If the userId is blank, then set it to be the layout user id?
            if (!$ownerId = $regionNode->getAttribute('userId'))
            {
                $sth = $dbh->prepare('SELECT userid FROM layout WHERE layoutid = :layoutid');
                $sth->execute(array(
                        'layoutid' => $layoutId
                    ));

                if (!$row = $sth->fetch())
                    throw new Exception("Error Processing Request", 1);
                    
                $ownerId = Kit::ValidateParam($row['userid'], _INT);
                $regionNode->setAttribute('userid', $ownerId);
            }
    
            return $ownerId;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function GetRegionName($layoutId, $regionId)
    {
        // Get the region node
        $regionNode = $this->getRegion($layoutId, $regionId);

        return $regionNode->getAttribute('name');
    }

    public function getRegion($layoutId, $regionId)
    {
        // Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutId));

        // Find the region
        $xpath = new DOMXPath($xml);

        $regionNodeList = $xpath->query("//region[@id='$regionId']");
        return $regionNode = $regionNodeList->item(0);
    }

    /**
     * Get media node type
     * @param <int> $layoutId
     * @param <string> $regionId
     * @param <string> $mediaId
     * @return <string>
     */
    public function GetMediaNodeType($layoutId, $regionId = '', $mediaId = '', $lkId = '')
    {
        // Validate
        if ($regionId == '' && $mediaId == '' && $lkId == '')
            return false;

        //Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutId));

        //Find the region
        $xpath = new DOMXPath($xml);

        if ($lkId == '')
        {
            Debug::LogEntry('audit', 'No link ID. Using mediaid and regionid', 'region', 'GetMediaNodeType');
            $mediaNodeList = $xpath->query('//region[@id="' . $regionId . '"]/media[@id="' . $mediaId . '"]');
        }
        else
        {
            $mediaNodeList = $xpath->query('//media[@lkid=' . $lkId . ']');
        }

        // Only 1 node
        if (!$mediaNode = $mediaNodeList->item(0))
            return false;

        return $mediaNode->getAttribute('type');
    }

    /**
     * Reorder the timeline according to the media list provided
     * @param <type> $layoutId
     * @param <type> $regionId
     * @param <type> $mediaList
     */
    public function ReorderTimeline($layoutId, $regionId, $mediaList)
    {
        // Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutId));

        //Get the Media Node in question in a DOMNode using Xpath
        $xpath = new DOMXPath($xml);

        $sequence = 0;
        $numberofMediaItems = count($mediaList);

        Debug::LogEntry('audit', 'There are ' . $numberofMediaItems . ' media items to reorder', 'region', 'ReorderTimeline');

        foreach($mediaList as $mediaItem)
        {
            // Look for mediaid and lkid
            $mediaId = $mediaItem['mediaid'];
            $lkId = $mediaItem['lkid'];

            Debug::LogEntry('audit', 'RegionId: ' . $regionId . '. MediaId: ' . $mediaId . '. LkId: ' . $lkId, 'region', 'ReorderTimeline');

            if ($lkId == '')
                $mediaNodeList = $xpath->query("//region[@id='$regionId']/media[@id='$mediaId']");
            else
                $mediaNodeList = $xpath->query("//region[@id='$regionId']/media[@lkid='$lkId']");

            $mediaNode = $mediaNodeList->item(0);

            // Remove this node from its parent
            $mediaNode->parentNode->removeChild($mediaNode);

            // Get a NodeList of the Region specified (using XPath again)
            $regionNodeList = $xpath->query("//region[@id='$regionId']/media");

            // Insert the Media Node in question before this $sequence node
            if ($sequence == $numberofMediaItems - 1)
            {
                // Get the region node, and append the child node to the end
                $regionNode = $regionNodeList = $xpath->query("//region[@id='$regionId']")->item(0);
                $regionNode->appendChild($mediaNode);
            }
            else
            {
                // Get the $sequence node from the list
                $mediaSeq = $regionNodeList->item($sequence);
                $mediaSeq->parentNode->insertBefore($mediaNode, $mediaSeq);
            }

            // Increment the sequence
            $sequence++;
        }

        // Save it
        if (!$this->SetLayoutXml($layoutId, $xml->saveXML()))
            return false;

        // Update layout status
        Kit::ClassLoader('Layout');
        $layout = new Layout($this->db);
        $layout->SetValid($layoutId, true);

        return true;
    }
    
    /**
     * Get region option
     * @param type $layoutId
     * @param type $regionId
     * @param type $name
     * @param type $default
     * @return boolean
     */
    final public function GetOption($layoutId, $regionId, $name, $default = false)
    {
        // Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutId));

        if ($name == '') 
            return false;

        // Check to see if we already have this option or not
        $xpath = new DOMXPath($xml);

        // Xpath for it
        $userOptions = $xpath->query('//region[@id="' . $regionId . '"]/options/' . $name);

        if ($userOptions->length == 0)
        {
            // We do not have an option - return the default
            Debug::LogEntry('audit', 'GetOption ' . $name . ': Not Set - returning default ' . $default);
            return $default;
        }
        else
        {
            // Replace the old node we found with XPath with the new node we just created
            Debug::LogEntry('audit', 'GetOption ' . $name . ': Set - returning: ' . $userOptions->item(0)->nodeValue);
            return ($userOptions->item(0)->nodeValue != '') ? $userOptions->item(0)->nodeValue : $default;
        }
    }
    
    /**
     * Adds the name/value element to the XML Options sequence
     * @return
     * @param $name String
     * @param $value String
     */
    final protected function SetOption($xml, $regionId, $name, $value)
    {
        if ($name == '') 
            return;

        Debug::LogEntry('audit', sprintf('IN with Name=%s and value=%s', $name, $value), 'region', 'Set Option');

        // Get the options node from this document
        $xpath = new DOMXPath($xml);

        // Xpath for it
        $optionNodes = $xpath->query('//region[@id="' . $regionId . '"]/options');
        
        // What if it isnt there (older layout?)
        if ($optionNodes->length == 0)
        {
            // Append one.
            $regionNodes = $xpath->query('//region[@id="' . $regionId . '"]');
            $regionNode = $regionNodes->item(0);
            
            $optionNode = $xml->createElement('options');
            $regionNode->appendChild($optionNode);
        }
        else
            $optionNode = $optionNodes->item(0);

        // Create a new option node
        $newNode = $xml->createElement($name, $value);

        Debug::LogEntry('audit', sprintf('Created a new Option Node with Name=%s and value=%s', $name, $value), 'region', 'Set Option');

        // Xpath for it
        $userOptions = $xpath->query('//region[@id="' . $regionId . '"]/options/' . $name);

        if ($userOptions->length == 0)
        {
            // Append the new node to the list
            $optionNode->appendChild($newNode);
        }
        else
        {
            // Replace the old node we found with XPath with the new node we just created
            $optionNode->replaceChild($newNode, $userOptions->item(0));
        }
    }

    /**
     * Get media node list
     * @param int $layoutId
     * @param string $regionId
     * @param string[optional] $mediaId
     * @param string[optional] $lkId
     * @return DOMNodeList
     */
    public function GetMediaNodeList($layoutId, $regionId = '', $mediaId = '', $lkId = '') {

        // Validate
        if ($regionId == '' && $mediaId == '' && $lkId == '')
            return false;

        // Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutId));

        $xpath = new DOMXPath($xml);

        if ($lkId != '') {
            $mediaNodeList = $xpath->query('//media[@lkid=' . $lkId . ']');
        }
        else if ($mediaId != '' && $regionId == '') {
            $mediaNodeList = $xpath->query('//media[@id=' . $mediaId . ']');
        }
        else if ($mediaId != '' && $regionId != '') {
            $mediaNodeList = $xpath->query('//region[@id="' . $regionId . '"]/media[@id="' . $mediaId . '"]');
        }
        else {
            $mediaNodeList = $xpath->query('//region[@id="' . $regionId . '"]/media');
        }

        return $mediaNodeList;
    }

    /**
     * Get Option for Media Id
     * @param int $layoutId The Layout ID
     * @param string $mediaId  The Media ID
     * @param string $name     The Option Name
     * @param string $default  The Default Value if none found
     */
    public function GetOptionForMediaId($layoutId, $mediaId, $name, $default = false) {

        if ($name == '') 
            return false;

        if (!$this->GetLayoutDom($layoutId))
            return false;

        // Check to see if we already have this option or not
        $xpath = new DOMXPath($this->layoutDocument);

        // Xpath for it
        $userOptions = $xpath->query('//region/media[@id=\'' . $mediaId . '\']/options/' . $name);

        // Debug::LogEntry('audit', '//region/media[@id=\'' . $mediaId . '\']/options/' . $name);

        if ($userOptions->length == 0) {
            // We do not have an option - return the default
            Debug::LogEntry('audit', 'GetOption ' . $name . ': Not Set - returning default ' . $default, 'region');
            return $default;
        }
        else {
            // Replace the old node we found with XPath with the new node we just created
            Debug::LogEntry('audit', 'GetOption ' . $name . ': Set - returning: ' . $userOptions->item(0)->nodeValue, 'region');
            return ($userOptions->item(0)->nodeValue != '') ? $userOptions->item(0)->nodeValue : $default;
        }
    }

    /**
     * Add Existing Media from the Library
     * @param [int] $user [A user object for the currently logged in user]
     * @param [int] $layoutId  [The LayoutID to Add on]
     * @param [int] $regionId  [The RegionID to Add on]
     * @param [array] $mediaList [A list of media ids from the library that should be added to to supplied layout/region]
     */
    public function AddFromLibrary($user, $layoutId, $regionId, $mediaList) {
        Debug::LogEntry('audit', 'IN', 'Region', 'AddFromLibrary');

        try {
            $dbh = PDOConnect::init();

            // Check that some media assignments have been made
            if (count($mediaList) == 0)
                return $this->SetError(25006, __('No media to assign'));
    
            // Loop through all the media
            foreach ($mediaList as $mediaId)
            {
                Debug::LogEntry('audit', 'Assigning MediaID: ' . $mediaId);

                $mediaId = Kit::ValidateParam($mediaId, _INT);
    
                // Get the type from this media
                $sth = $dbh->prepare('SELECT type FROM media WHERE mediaID = :mediaid');
                $sth->execute(array(
                        'mediaid' => $mediaId
                    ));

                if (!$row = $sth->fetch())
                    $this->ThrowError(__('Error getting type from a media item.'));
                
                $mod = Kit::ValidateParam($row['type'], _WORD);

                try {
                    // Create the media object without any region and layout information
                    $module = ModuleFactory::createForMedia($mod, $mediaId, null, $user);
                }
                catch (Exception $e) {
                    return $this->SetError($e->getMessage());
                }

                // Check we have permissions to use this media (we will use this to copy the media later)
                if (!$module->auth->view)
                    return $this->SetError(__('You have selected media that you no longer have permission to use. Please reload Library form.'));
    
                if (!$module->SetRegionInformation($layoutId, $regionId))
                    return $this->SetError($module->GetErrorMessage());
    
                if (!$module->UpdateRegion())
                    return $this->SetError($module->GetErrorMessage());
    
                // Need to copy over the permissions from this media item & also the delete permission
                $security = new LayoutMediaGroupSecurity($this->db);
                $security->Link($layoutId, $regionId, $mediaId, $user->getGroupFromID($user->userid, true), $module->auth->view, $module->auth->edit, 1);
            }
    
            // Update layout status
            $layout = new Layout($this->db);
            $layout->SetValid($layoutId, true);
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }
}
?>
