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

class region 
{
	private $user;
	private $db;
	public $errorMsg;
	
	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
		
		require_once("lib/pages/module.class.php");
	}
	
	/**
	 * Gets the Xml for the specified layout
	 * @return 
	 * @param $layoutid Object
	 */
	public function GetLayoutXml($layoutid)
	{
		$db =& $this->db;
		
		//Get the Xml for this Layout from the DB
		$SQL = sprintf("SELECT xml FROM layout WHERE layoutID = %d ", $layoutid);
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->errorMsg = __("Unable to Query for that layout, there is a database error.");
			return false;
		}
		
		$row = $db->get_row($results) ;
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
		
		$xml = addslashes($xml);
		
		//Write it back to the database
		$SQL = sprintf("UPDATE layout SET xml = '%s' WHERE layoutID = %d ", $xml, $layoutid);
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->errMsg = __("Unable to Update that layouts XML with a new Media Node");
			return false;
		}

                // Notify (dont error)
                Kit::ClassLoader('display');
                $displayObject = new Display($db);
                $displayObject->NotifyDisplays($layoutid);
		
		return true;
	}
	
	/**
	 * Adds a region to the specified layoutid
	 * @return 
	 * @param $layoutid Object
	 * @param $regionid Object[optional]
	 */
	public function AddRegion($layoutid, $regionid = "")
	{
            $db =& $this->db;

            //Load the XML for this layout
            $xml = new DOMDocument("1.0");
            $xml->loadXML($this->GetLayoutXml($layoutid));

            //Do we have a region ID provided?
            if ($regionid == '')
                $regionid = uniqid();

            // make a new region node
            $newRegion = $xml->createElement("region");
            $newRegion->setAttribute('id', $regionid);
            $newRegion->setAttribute('userId', $this->user->userid);
            $newRegion->setAttribute('width', 50);
            $newRegion->setAttribute('height', 50);
            $newRegion->setAttribute('top', 50);
            $newRegion->setAttribute('left', 50);

            $xml->firstChild->appendChild($newRegion);

            if (!$this->SetLayoutXml($layoutid, $xml->saveXML())) return false;

            // What permissions should we create this with?
            if (Config::GetSetting($db, 'LAYOUT_DEFAULT') == 'public')
            {
                Kit::ClassLoader('layoutregiongroupsecurity');
                $security = new LayoutRegionGroupSecurity($db);
                $security->LinkEveryone($layoutid, $regionid, 1, 0, 0);
            }

            return true;
	}
	
	public function DeleteRegion($layoutid, $regionid)
	{
		$db =& $this->db;
			
		//Load the XML for this layout
		$xml = new DOMDocument("1.0");
		$xml->loadXML($this->GetLayoutXml($layoutid));
		
		//Do we have a region ID provided?
		if ($regionid == "")
		{
			$this->errMsg = __("No region ID provided, cannot delete");
			return false;
		}
		
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
				if (!$this->RemoveDbLink($lkid)) return false;
			}
		}
		
		//Remove the region node
		$xml->firstChild->removeChild($regionNode);
		
		if (!$this->SetLayoutXml($layoutid, $xml->saveXML())) return false;
		
		return true;
	}
	
	/**
	 * Adds the media to this region
	 * @return 
	 */
	public function AddMedia($layoutid, $regionid, $regionSpecific, $mediaXmlString) 
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		
		//Load the XML for this layout
		$xml = new DOMDocument("1.0");
		$xml->loadXML($this->GetLayoutXml($layoutid));
			
		Debug::LogEntry($db, "audit", $mediaXmlString, "region", "AddMedia");
		
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
        if (Config::GetSetting($db, 'MEDIA_DEFAULT') == 'public')
        {
            Kit::ClassLoader('layoutmediagroupsecurity');

            $security = new LayoutMediaGroupSecurity($db);
            $security->LinkEveryone($layoutid, $regionid, $mediaid, 1, 0, 0);
        }
		
		if (!$this->SetLayoutXml($layoutid, $xml)) return false;
		
		return true;
	}
	
	/**
	 * Adds a db link record for the layout, media, region combination
	 * @return 
	 * @param $layoutid Object
	 * @param $region Object
	 * @param $mediaid Object
	 */
	private function AddDbLink($layoutid, $region, $mediaid)
	{
		$db =& $this->db;
		
		$SQL = sprintf("INSERT INTO lklayoutmedia (layoutID, regionID, mediaID) VALUES (%d, '%s', %d)", $layoutid, $db->escape_string($region), $mediaid);
		
		if (!$id = $db->insert_query($SQL))
		{
			trigger_error($db->error());
			$this->errorMsg = __("Database error adding this link record.");
			return false;
		}
		
		return $id;
	}
	
	/**
	 * Updates the specified DbLink to the media ID provided
	 * @return 
	 * @param $lkid Object
	 * @param $mediaid Object
	 */
	private function UpdateDbLink($lkid, $mediaid)
	{
		$db =& $this->db;
		
		$SQL = "UPDATE lklayoutmedia SET mediaid = $mediaid WHERE lklayoutmediaID = $lkid ";
		
		if (!$db->query($SQL))
		{
			trigger_error($db->error());
			$this->errorMsg = __("Database error updating this link record.");
			return false;
		}
		
		return true;
	}
	
	/**
	 * Removes the DBlink for records for the given id's
	 * @return 
	 */
	private function RemoveDbLink($lkid)
	{
		$db =& $this->db;
		
		$SQL = "DELETE FROM lklayoutmedia WHERE lklayoutmediaID = $lkid ";
		
		if (!$db->query($SQL))
		{
			trigger_error($db->error());
			$this->errorMsg = __("Database error deleting this link record.");
			return false;
		}
		
		return true;
	}
	
	public function RemoveMedia($layoutid, $regionid, $lkid, $mediaid) 
	{
		$db =& $this->db;
		
		//Load the XML for this layout
		$xml = new DOMDocument("1.0");
		$xml->loadXML($this->GetLayoutXml($layoutid));
		
		//Should we use the LkID or the mediaID
		if ($lkid != "")
		{
			//Get the media node
			$xpathQuery = "//region[@id='$regionid']/media[@lkid='$lkid']";
			
			if (!$this->RemoveDbLink($lkid)) return false;
		}
		else
		{
			$xpathQuery = "//region[@id='$regionid']/media[@id='$mediaid']";
		}
		
		//Find the region in question
		$xpath = new DOMXPath($xml);
		
		//Xpath for it
		$mediaNodeList 	= $xpath->query($xpathQuery);
		$mediaNode 		= $mediaNodeList->item(0);
		
		$mediaNode->parentNode->removeChild($mediaNode);
		
		//Convert back to XML
		$xml = $xml->saveXML();
		
		if (!$this->SetLayoutXml($layoutid, $xml)) return false;
		
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
		$db =& $this->db;

                Debug::LogEntry($db, 'audit', 'LkID = ' . $lkid, 'region', 'ReorderMedia');

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
		if (!$this->SetLayoutXml($layoutid, $xml)) return false;
		
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
		$db 	=& $this->db;
		$user 	=& $this->user;
		
		//Load the XML for this layout
		$xml = new DOMDocument("1.0");
		$xml->loadXML($this->GetLayoutXml($layoutid));
			
		Debug::LogEntry($db, "audit", 'Media String Given: ' . $mediaXmlString, "region", "SwapMedia");
		
		//Get the media's Xml
		$mediaXml = new DOMDocument("1.0");
		
		//Load the Media's XML into a SimpleXML object
		$mediaXml->loadXML($mediaXmlString);
		
		
		//Find the current media node
		$xpath = new DOMXPath($xml);
		
		//Should we use the LkID or the mediaID
		if ($lkid == "")
		{
			Debug::LogEntry($db, "audit", "No link ID. Using mediaid", "region", "SwapMedia");
			$mediaNodeList = $xpath->query("//region[@id='$regionid']/media[@id='$existingMediaid']");
		}
		else
		{
			Debug::LogEntry($db, "audit",  "Link ID detected, using for Xpath", "region", "SwapMedia");			
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
			Debug::LogEntry($db, "audit", "Current Link ID = $currentLkid", "region", "SwapMedia");
			$this->UpdateDbLink($currentLkid, $newMediaid);
			
			$lkid = $currentLkid;
		}
		else
		{
			// Make a new link? Or assume a link already set? Or just give up?
		}
		
		Debug::LogEntry($db, "audit", "Setting Link ID on new media node", "region", "SwapMedia");
		$mediaXml->documentElement->setAttribute("lkid", $lkid);
		
		Debug::LogEntry($db, "audit", $mediaXml->saveXML(), "region", "SwapMedia");
		
		//Replace the Nodes
		$newMediaNode = $xml->importNode($mediaXml->documentElement, true);
		$oldMediaNode->parentNode->replaceChild($newMediaNode, $oldMediaNode);
		
		//Convert back to XML
		$xml = $xml->saveXML();
		
		//Save it
		if (!$this->SetLayoutXml($layoutid, $xml)) return false;
		
		//Its swapped
		return true;
	}
	
	public function EditBackground($layoutid, $bg_color, $bg_image, $width, $height)
	{
		$db =& $this->db;
		
		//Load the XML for this layout
		$xml = new DOMDocument("1.0");
		$xml->loadXML($this->GetLayoutXml($layoutid));
		
		//Alter the background properties
		$xml->documentElement->setAttribute("background",$bg_image);
		$xml->documentElement->setAttribute("bgcolor", $bg_color);
		$xml->documentElement->setAttribute('width', $width);
		$xml->documentElement->setAttribute('height', $height);
		$xml->documentElement->setAttribute("schemaVersion", Config::Version($db, 'XlfVersion'));
		
		//Convert back to XML		
		if (!$this->SetLayoutXml($layoutid, $xml->saveXML())) return false;
		
		//Its swapped
		return true;
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
	public function EditRegion($layoutid, $regionid, $width, $height, $top, $left, $name = '')
	{
		$db =& $this->db;
		
		//Do a little error checking on the widths given
		if (!is_numeric($width) || !is_numeric($height) || !is_numeric($top) || !is_numeric($left))
		{
			$this->errorMsg = __("Non numerics, try refreshing the browser");
			return false;
		}
		
		//Load the XML for this layout
		$xml = new DOMDocument("1.0");
		$xml->loadXML($this->GetLayoutXml($layoutid));
		
		//Find the region
		$xpath = new DOMXPath($xml);
		
		$regionNodeList = $xpath->query("//region[@id='$regionid']");
		$regionNode = $regionNodeList->item(0);
		
		if ($name != '') $regionNode->setAttribute('name', $name);
		$regionNode->setAttribute('width',$width);
		$regionNode->setAttribute('height', $height);
		$regionNode->setAttribute('top', $top);
		$regionNode->setAttribute('left', $left);

                // If the userId is blank, then set it to be the layout user id?
                if (!$ownerId = $regionNode->getAttribute('userId'))
                {
                    $ownerId = $db->GetSingleValue(sprintf("SELECT userid FROM layout WHERE layoutid = %d", $layoutid), 'userid', _INT);
                    $regionNode->setAttribute('userId', $ownerId);
                }
		
		//Convert back to XML		
		if (!$this->SetLayoutXml($layoutid, $xml->saveXML())) return false;
		
		//Its swapped
		return true;
	}

    public function GetOwnerId($layoutId, $regionId)
    {
        $db =& $this->db;

        //Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutId));

        //Find the region
        $xpath = new DOMXPath($xml);

        $regionNodeList = $xpath->query("//region[@id='$regionId']");
        $regionNode = $regionNodeList->item(0);

        // If the userId is blank, then set it to be the layout user id?
        if (!$ownerId = $regionNode->getAttribute('userId'))
        {
            $ownerId = $db->GetSingleValue(sprintf("SELECT userid FROM layout WHERE layoutid = %d", $layoutId), 'userid', _INT);
            $regionNode->setAttribute('userid', $ownerId);
        }

        return $ownerId;
    }

    public function GetRegionName($layoutId, $regionId)
    {
        $db =& $this->db;

        //Load the XML for this layout
        $xml = new DOMDocument("1.0");
        $xml->loadXML($this->GetLayoutXml($layoutId));

        //Find the region
        $xpath = new DOMXPath($xml);

        $regionNodeList = $xpath->query("//region[@id='$regionId']");
        $regionNode = $regionNodeList->item(0);

        return $regionNode->getAttribute('name');
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
        $db =& $this->db;

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
            Debug::LogEntry($db, 'audit', 'No link ID. Using mediaid and regionid', 'region', 'GetMediaNodeType');
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
}
?>
