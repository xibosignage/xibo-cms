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

class Module implements ModuleInterface
{
	//Media information
	protected $db;
	protected $user;
	protected $region;
	protected $response;
	
	protected $layoutid;
	protected $regionid;
	
	protected $mediaid;
	protected $type;
	private   $schemaVersion;

	protected $xml;
	
	/**
	 * Constructor - sets up this media object with all the available information
	 * @return 
	 * @param $db database
	 * @param $user user
	 * @param $mediaid String[optional]
	 * @param $layoutid String[optional]
	 * @param $regionid String[optional]
	 */
	final public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '')
	{
		include_once("lib/pages/region.class.php");
		
		$this->db 		=& $db;
		$this->user 	=& $user;
		
		$this->mediaid 	= $mediaid;
		$this->layoutid = $layoutid;
		$this->regionid = $regionid;
		
		$this->region 	= new region($db, $user);
		$this->response = new ResponseManager();
		
		Debug::LogEntry($db, 'audit', 'New module created with MediaID: ' . $mediaid . ' LayoutID: ' . $layoutid . ' and RegionID: ' . $regionid);
		
		// Either the information from the region - or some blanks
		$this->SetMediaInformation($this->layoutid, $this->regionid, $mediaid);
		
		return true;
	}
	
	/**
	 * Gets the information about this Media on this region on this layout
	 * @return 
	 * @param $layoutid Object
	 * @param $regionid Object
	 * @param $mediaid Object
	 */
	final private function SetMediaInformation($layoutid, $regionid, $mediaid)
	{
		$db 		=& $this->db;
		$region 	=& $this->region;
		$xmlDoc 	= new DOMDocument();
		
		if ($this->mediaid != '' && $this->regionid != '' && $this->layoutid != '')
		{
			// Set the layout Xml
			$layoutXml = $region->GetLayoutXml($layoutid);
			
			$xml = simplexml_load_string($layoutXml);
			
			// Get the media node and extract the info
			$mediaNodeXpath = $xml->xpath("//region[@id='$regionid']/media[@id='$mediaid']");
			$mediaNode 		= $mediaNodeXpath[0];
			
			$xmlDoc->importNode($mediaNode, true);
		}
		else
		{			
			$xml = <<<XML
			<media id="" type="" duration="" lkid="" schemaVersion="">
				<options />
				<raw />
			</media>
XML;
			$xmlDoc->loadXML($xml);
		}
		
		$this->xml = $xmlDoc;
		
		Debug::LogEntry($db, 'audit', 'XML is: ' . $this->xml->saveXML());
		
		return true;
	}
	
	/**
	 * This Media item represented as XML
	 * @return 
	 */
	final public function AsXml()
	{
		// Make sure the required attributes are present on the Media Node
		// We can add / change:
		// 		MediaID
		//		Duration
		//		Type
		//		SchemaVersion (use the type to get this from the DB)
		// LkID is done by the region code (where applicable - otherwise it will be left blank)
		
		return $this->xml->saveXML();
	}
	
	/**
	 * Adds the name/value element to the XML Options sequence 
	 * @return 
	 * @param $name String
	 * @param $value String
	 */
	final protected function SetOption($name, $value)
	{
		if ($name == '' || $value == '') return;
		
		// Get the options node from this document
		$optionNodes = $this->xml->getElementsByTagName('options');
		// There is only 1
		$optionNode = $optionNodes[0];
		
		// Create a new option node
		$newNode = $this->xml->createElement($name, $value);
		
		
		// Check to see if we already have this option or not
		$xpath = new DOMXPath($xml);
		
		// Xpath for it
		$userOptions = $xpath->query('//options/option/' . $name);
		
		if ($userOptions->length == 0)
		{
			// Append the new node to the list
			$optionNode->appendChild($newNode);
		}
		else
		{
			// Replace the old node we found with XPath with the new node we just created
			$optionNode->replaceChild($newNode, $userOptions[0]);	
		}
	}
	
	/**
	 * Gets the value for the option in Parameter 1
	 * @return 
	 * @param $name String
	 */
	final protected function GetOption($name)
	{
		
	}
	
	/**
	 * Sets the RAW XML string that is given as the content for Raw
	 * @return 
	 * @param $xml String
	 * @param $replace Boolean[optional]
	 */
	final protected function SetRaw($xml, $replace = false)
	{
		if ($xml == '') return;
		
		// Load the XML we are given into its own document
		$rawNode = new DOMDocument();
		$rawNode->loadXML($xml);
		
		// Import the Raw node into this document (with all sub nodes)
		$importedNode = $this->xml->importNode($rawNode->documentElement, true);
		
		// Get the Raw Xml node from our document
		$rawNodes = $this->xml->getElementsByTagName('raw');

		// There is only 1
		$rawNode = $rawNodes[0];
		
		// Append the imported node (at the end of whats already there)
		$rawNode->appendChild($importedNode);
	}
	
	/**
	 * Gets the XML string from RAW
	 * @return 
	 */
	final protected function GetRaw()
	{
		
	}
	
	/**
	 * Updates the region information with this media record
	 * @return 
	 */
	final protected function UpdateRegion()
	{
		// By this point we expect to have a MediaID, duration
		
		$layoutid = $this->layoutid;
		$regionid = $this->regionid;
		
		if (!$this->region->AddMedia($layoutid, $regionid, $this->AsXml()))
		{
			$this->message = "Error adding this media to the library";
			return false;
		}
	}
}
?>
