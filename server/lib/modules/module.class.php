<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner
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
	protected $name;
	protected $type;
	private   $schemaVersion;
	protected $regionSpecific;
	protected $duration;
	protected $lkid;

	protected $xml;
	
	protected $existingMedia;
	protected $deleteFromRegion;
	
	/**
	 * Constructor - sets up this media object with all the available information
	 * @return 
	 * @param $db database
	 * @param $user user
	 * @param $mediaid String[optional]
	 * @param $layoutid String[optional]
	 * @param $regionid String[optional]
	 */
	public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '')
	{
		include_once("lib/pages/region.class.php");
		
		$this->db 		=& $db;
		$this->user 	=& $user;
		
		$this->mediaid 	= $mediaid;
		$this->name 	= "";
		$this->layoutid = $layoutid;
		$this->regionid = $regionid;
		
		$this->region 	= new region($db, $user);
		$this->response = new ResponseManager();
		
		$this->existingMedia 	= false;
		$this->deleteFromRegion = false;
		$this->lkid				= '';
		$this->duration 		= '';
		
		// Determine which type this module is
		$this->SetModuleInformation();
		
		Debug::LogEntry($db, 'audit', 'New module created with MediaID: ' . $mediaid . ' LayoutID: ' . $layoutid . ' and RegionID: ' . $regionid);
		
		// Either the information from the region - or some blanks
		$this->SetMediaInformation($this->layoutid, $this->regionid, $mediaid);
		
		return true;
	}
	
	/**
	 * Sets the module information
	 * @return 
	 */
	final private function SetModuleInformation()
	{
		$db 		=& $this->db;
		$type		= $this->type;
		
		if ($type == '')
		{
			$this->response->SetError('Unable to create Module [No type given] - please refer to the Module Documentation.');
			$this->response->Respond();
		}
		
		$SQL = sprintf("SELECT * FROM module WHERE Module = '%s'", $db->escape_string($type));
		
		if (!$result = $db->query($SQL)) 
		{
			$this->response->SetError('Unable to create Module [Cannot find type in the database] - please refer to the Module Documentation.');
			$this->response->Respond();
		}
		
		if ($db->num_rows($result) != 1)
		{
			$this->response->SetError('Unable to create Module [No registered modules of this type] - please refer to the Module Documentation.');
			$this->response->Respond();
		}
		
		$row = $db->get_assoc_row($result);
		
		$this->schemaVersion 	= Kit::ValidateParam($row['SchemaVersion'], _INT);
		$this->regionSpecific 	= Kit::ValidateParam($row['RegionSpecific'], _INT);
		
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
			$this->existingMedia = true;
			
			// Set the layout Xml
			$layoutXml = $region->GetLayoutXml($layoutid);
			
			Debug::LogEntry($db, 'audit', 'Layout XML retrieved: ' . $layoutXml);
			
			$layoutDoc = new DOMDocument();
			$layoutDoc->loadXML($layoutXml);
			
			$layoutXpath = new DOMXPath($layoutDoc);
			
			// Get the media node and extract the info
			$mediaNodeXpath = $layoutXpath->query("//region[@id='$regionid']/media[@id='$mediaid']");
			
			if ($mediaNodeXpath->length > 0)
			{
				Debug::LogEntry($db, 'audit', 'Media Node Found.');
				
				// Create a Media node in the DOMDocument for us to replace
				$xmlDoc->loadXML('<root/>');
			}
			else
			{
				$this->response->SetError('Cannot find this media item. Please refresh the region options.');
				$this->response->Respond();
			}
			
			$mediaNode = $mediaNodeXpath->item(0);
			$mediaNode->setAttribute('schemaVersion', $this->schemaVersion);
			
			$this->duration = $mediaNode->getAttribute('duration');
			$this->lkid	 	= $mediaNode->getAttribute('lkid');
			
			$mediaNode = $xmlDoc->importNode($mediaNode, true);
			$xmlDoc->documentElement->appendChild($mediaNode);
		}
		else
		{
			if ($this->mediaid != '')
			{
				// We do not have a region or a layout
				// But this is some existing media
				// Therefore make sure we get the bare minimum!
				$this->existingMedia = true;
				
				// Load what we know about this media into the object
				$SQL = "SELECT duration,name FROM media WHERE mediaID = '$mediaid'";
				
				if (!$result = $db->query($SQL))
				{
					trigger_error($db->error()); //log the error
				}
				
				if ($db->num_rows($result) != 0)
				{
					$row 				= $db->get_row($result);
					$this->duration		= $row[0];
					$this->name		= $row[1];
				}
			}
			
			$xml = <<<XML
			<root>
				<media id="" type="$this->type" duration="" lkid="" schemaVersion="$this->schemaVersion">
					<options />
					<raw />
				</media>
			</root>
XML;
			$xmlDoc->loadXML($xml);
		}
		
		$this->xml = $xmlDoc;
		
		Debug::LogEntry($db, 'audit', 'XML is: ' . $this->xml->saveXML());
		
		return true;
	}
	
	/**
	 * Sets the Layout and Region Information
	 * @return 
	 * @param $layoutid Object
	 * @param $regionid Object
	 * @param $mediaid Object
	 */
	public function SetRegionInformation($layoutid, $regionid)
	{	
		$this->layoutid = $layoutid;
		$this->regionid = $regionid;
		
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
		$mediaNodes = $this->xml->getElementsByTagName('media');
		$mediaNode	= $mediaNodes->item(0);
		
		$mediaNode->setAttribute('id', $this->mediaid);
		$mediaNode->setAttribute('duration', $this->duration);
		$mediaNode->setAttribute('type', $this->type);
		
		return $this->xml->saveXML($mediaNode);
	}
	
	/**
	 * Adds the name/value element to the XML Options sequence 
	 * @return 
	 * @param $name String
	 * @param $value String
	 */
	final protected function SetOption($name, $value)
	{
		$db =& $this->db;
		if ($name == '') return;
		
		Debug::LogEntry($db, 'audit', sprintf('IN with Name=%s and value=%s', $name, $value), 'module', 'Set Option');		
		
		// Get the options node from this document
		$optionNodes = $this->xml->getElementsByTagName('options');
		// There is only 1
		$optionNode = $optionNodes->item(0);
		
		// Create a new option node
		$newNode = $this->xml->createElement($name, $value);
		
		Debug::LogEntry($db, 'audit', sprintf('Created a new Option Node with Name=%s and value=%s', $name, $value), 'module', 'Set Option');
		
		// Check to see if we already have this option or not
		$xpath = new DOMXPath($this->xml);
		
		// Xpath for it
		$userOptions = $xpath->query('//options/' . $name);
		
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
	 * Gets the value for the option in Parameter 1
	 * @return 
	 * @param $name String The Option Name
	 * @param $default Object[optional] The Default Value
	 */
	final protected function GetOption($name, $default = false)
	{
		$db =& $this->db;
		
		if ($name == '') return false;
		
		// Check to see if we already have this option or not
		$xpath = new DOMXPath($this->xml);
		
		// Xpath for it
		$userOptions = $xpath->query('//options/' . $name);
		
		if ($userOptions->length == 0)
		{
			// We do not have an option - return the default
			Debug::LogEntry($db, 'audit', 'GetOption ' . $name . ': Not Set - returning default ' . $default);
			return $default;
		}
		else
		{
			// Replace the old node we found with XPath with the new node we just created
			Debug::LogEntry($db, 'audit', 'GetOption ' . $name . ': Set - returning: ' . $userOptions->item(0)->nodeValue);
			return $userOptions->item(0)->nodeValue;
		}
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
		$rawNode->loadXML('<raw>' . $xml . '</raw>');
		
		// Import the Raw node into this document (with all sub nodes)
		$importedNode = $this->xml->importNode($rawNode->documentElement, true);
		
		// Get the Raw Xml node from our document
		$rawNodes = $this->xml->getElementsByTagName('raw');

		// There is only 1
		$rawNode = $rawNodes->item(0);
		
		// Append the imported node (at the end of whats already there)
		$rawNode->parentNode->replaceChild($importedNode, $rawNode);
	}
	
	/**
	 * Gets the XML string from RAW
	 * @return 
	 */
	final protected function GetRaw()
	{
		// Get the Raw Xml node from our document
		$rawNodes = $this->xml->getElementsByTagName('raw');

		// There is only 1
		$rawNode = $rawNodes->item(0);
		
		// Return it as a XML string
		return $this->xml->saveXML($rawNode);
	}
	
	/**
	 * Updates the region information with this media record
	 * @return 
	 */
	final public function UpdateRegion()
	{
		// By this point we expect to have a MediaID, duration
		$layoutid = $this->layoutid;
		$regionid = $this->regionid;
		
		if ($this->deleteFromRegion)
		{
			// We call region delete
			if (!$this->region->RemoveMedia($layoutid, $regionid, $this->lkid, $this->mediaid))
			{
				$this->message = "Unable to Remove this media from the Layout";
				return false;
			}
		}
		else
		{
			if ($this->existingMedia)
			{
				// We call region swap with the same media id
				if (!$this->region->SwapMedia($layoutid, $regionid, $this->lkid, $this->mediaid, $this->mediaid, $this->AsXml()))
				{
					$this->message = "Unable to assign to the Region";
					return false;
				}
			}
			else
			{
				// We call region add
				if (!$this->region->AddMedia($layoutid, $regionid, $this->regionSpecific, $this->AsXml()))
				{
					$this->message = "Error adding this media to the library";
					return false;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Return the Delete Form as HTML
	 * @return 
	 */
	public function DeleteForm()
	{
		$db =& $this->db;
		
		//Parameters
		$layoutid 	= $this->layoutid;
		$regionid 	= $this->regionid;
		$mediaid	= $this->mediaid;

		//we can delete
		$form = <<<END
		<form class="XiboForm" method="post" action="index.php?p=module&mod=text&q=Exec&method=DeleteMedia">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<p>Are you sure you want to remove this item from Xibo? <span class="required">It will be lost</span>.</p>
			<input id="btnSave" type="submit" value="Yes"  />
			<input class="XiboFormButton" id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" value="No" />
		</form>
END;
		
		$this->response->html 		 	= $form;
		$this->response->dialogTitle 	= 'Delete Item';
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '450px';
		$this->response->dialogHeight 	= '150px';

		return $this->response;	
	}
	
	/**
	 * Delete Media from the Database
	 * @return 
	 */
	public function DeleteMedia() 
	{
		$db 		=& $this->db;
		
		$layoutid 	= $this->layoutid;
		$regionid 	= $this->regionid;
		
		$url 		= "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
		
		$this->deleteFromRegion = true;
		$this->UpdateRegion();
		
		// We want to load a new form
		$this->response->loadForm	= true;
		$this->response->loadFormUri= $url;
		
		return $this->response;	
	}
	
	/**
	 * Default AddForm
	 * @return 
	 */
	public function AddForm()
	{
		$form = <<<END
		<p>Not yet implemented by this module.</p>
END;
		
		$this->response->html 		 	= $form;
		$this->response->dialogTitle 	= 'Add Item';
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '450px';
		$this->response->dialogHeight 	= '150px';

		return $this->response;	
	}
	
	/**
	 * Default Edit Form
	 * @return 
	 */
	public function EditForm()
	{
		$form = <<<END
		<p>Not yet implemented by this module.</p>
END;
		
		$this->response->html 		 	= $form;
		$this->response->dialogTitle 	= 'Add Item';
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '450px';
		$this->response->dialogHeight 	= '150px';

		return $this->response;	
	}
	
	/**
	 * Default Add Media
	 * @return 
	 */
	public function AddMedia()
	{
		// We want to load a new form
		$this->response->message	= 'Add Media has not been implemented for this module.';
		
		return $this->response;	
	}
	
	/**
	 * Default EditMedia
	 * @return 
	 */
	public function EditMedia()
	{
		// We want to load a new form
		$this->response->message	= 'Edit Media has not been implemented for this module.';
		
		return $this->response;	
	}

	/**
	 * Default GetName
	 * @return
	 */
	public function GetName()
	{
		return $this->name;
	}
	 
}
?>
