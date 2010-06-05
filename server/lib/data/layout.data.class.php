<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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
	
	public function EditTags($layoutID, $tags)
	{
		$db =& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'Layout', 'EditTags');
		
		// Make sure we get an array
		if(!is_array($tags))
		{
			$this->SetError(25000, 'Must pass EditTags an array');
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
		$xpath 		= new DOMXPath($xml);
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
		{
			return false;
		}
		
		return true;
	}
	
	/**
	 * Gets the Xml for the specified layout
	 * @return 
	 * @param $layoutid Object
	 */
	private function GetLayoutXml($layoutid)
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
			$this->SetError(25000, 'Unable to Update Layout.');
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
     * @return <int> 
     */
    public function Copy($oldLayoutId, $newLayoutName, $userId)
    {
        $db =& $this->db;

        // The Layout ID is the old layout
        $SQL  = "";
        $SQL .= " INSERT INTO layout (layout, permissionID, xml, userID, description, tags, templateID, retired, duration, background) ";
        $SQL .= " SELECT '%s', permissionID, xml, %d, description, tags, templateID, retired, duration, background ";
        $SQL .= "  FROM layout ";
        $SQL .= " WHERE layoutid = %d";
        $SQL = sprintf($SQL, $db->escape_string($newLayoutName), $userId, $oldLayoutId);

        if (!$newLayoutId = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25000, __('Unable to Copy this Layout'));
            return false;
        }

        // Need to also copy the link records from the old layout to the new one
        $SQL  = "";
        $SQL .= "INSERT INTO lklayoutmedia (mediaID, layoutID, regionID) ";
        $SQL .= " SELECT mediaID, %d, regionID FROM lklayoutmedia WHERE layoutID = %d";

        if (!$db->query(sprintf($SQL, $newLayoutId, $oldLayoutId)))
        {
            trigger_error($db->error());
            $this->SetError(25000, __('Unable to fully copy layout'));

            // Delete the old one...
            $db->query(sprintf('DELETE FROM layout WHERE layoutid = %d', $newLayoutId));

            return false;
        }

        return $newLayoutId;
    }
}
?>