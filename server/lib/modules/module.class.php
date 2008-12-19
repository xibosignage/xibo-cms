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
	protected $layoutid;
	protected $regionid;
	
	protected $mediaid;
	protected $type;

	protected $xml;
	
	public function __construct($mediaid = '', $layoutid = '', $regionid = '')
	{
		$this->mediaid 	= $mediaid;
		$this->layoutid = $layoutid;
		$this->regionid = $regionid;
		
		return true;
	}
	
	/**
	 * Sets the MediaID and gets information about this media record
	 * @return 
	 * @param $mediaid Object
	 */
	final public function SetMediaId($mediaid)
	{
		$this->mediaid = $mediaid;
		
		if ($this->regionid != '' && $this->layoutid != '')
		{
			$this->SetRegionInformation($this->layoutid, $this->regionid, $mediaid);
		}
	}
	
	/**
	 * Sets the LayoutID this media belongs to
	 * @return 
	 * @param $layoutid Object
	 */
	final public function SetLayoutId($layoutid)
	{
		$this->layoutid = $layoutid;
	}

	/**
	 * Sets the Region this media belongs to
	 * @return 
	 * @param $regionid Object
	 */
	final public function SetRegionId($regionid)
	{
		$this->regionid = $regionid;
	}	
	
	/**
	 * Sets the Database
	 * @return 
	 * @param $db Object
	 */
	final public function SetDb(database $db)
	{
		$this->db =& $db;
		
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
		$db =& $this->db;
		
		// Create a region to work with
		include_once("lib/app/region.class.php");
	
		$region = new region($db);
		
		//Set the layout Xml
		$layoutXml = $region->GetLayoutXml($layoutid);
		
		$xml = simplexml_load_string($layoutXml);
		
		//Get the media node and extract the info
		$mediaNodeXpath = $xml->xpath("//region[@id='$regionid']/media[@id='$mediaid']");
		$mediaNode 		= $mediaNodeXpath[0];
		
		$this->xml		= $mediaNode->saveXML();
		
		return true;
	}
	
	/**
	 * This Media item represented as XML
	 * @return 
	 */
	public function AsXml()
	{
		return $this->xml;
	}
	
	public function AddForm()
	{
		
	}
	
	public function EditForm() 
	{
		
	}
	
	public function DeleteForm() 
	{
		
	}
	
	public function AddMedia() 
	{
		
	}
	
	public function EditMedia() 
	{
		
	}
	
	public function DeleteMedia() 
	{
		
	}
	
	public function DisplayPage() 
	{
		
	}
}
?>
