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
		
		// If this module is definately associated with a region - then get the region information
		if ($this->mediaid != '' && $this->regionid != '' && $this->layoutid != '')
		{
			$this->SetRegionInformation($this->layoutid, $this->regionid, $mediaid);
		}
		
		return true;
	}
	
	/**
	 * Gets the information about this Media on this region on this layout
	 * @return 
	 * @param $layoutid Object
	 * @param $regionid Object
	 * @param $mediaid Object
	 */
	private function SetRegionInformation($layoutid, $regionid, $mediaid)
	{
		$db 		=& $this->db;
		$region 	=& $this->region;
		
		// Set the layout Xml
		$layoutXml = $region->GetLayoutXml($layoutid);
		
		$xml = simplexml_load_string($layoutXml);
		
		// Get the media node and extract the info
		$mediaNodeXpath = $xml->xpath("//region[@id='$regionid']/media[@id='$mediaid']");
		$mediaNode 		= $mediaNodeXpath[0];
		
		$this->xml		= $mediaNode->saveXML();
		
		return true;
	}
	
	/**
	 * This Media item represented as XML
	 * @return 
	 */
	final public function AsXml()
	{
		return $this->xml;
	}
	
	/**
	 * Adds the name/value element to the XML Options sequence 
	 * @return 
	 * @param $name String
	 * @param $value String
	 */
	final protected function SetOption($name, $value)
	{
		
	}
	
	/**
	 * Updates the region information with this media record
	 * @return 
	 */
	protected function UpdateRegion()
	{
		
	}
}
?>
