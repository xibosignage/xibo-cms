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
class media_text {
	
	private $db;
	
	//Media information
	private $mediaid;
	private	$duration;
	private $text;
	private $direction;
	
	//Information vars
	public $message = "";
	private $help_link;

	function __construct() 
	{
		$this->help_link = HELP_BASE . "?p=content/layout/assigncontent";
		return true;
	}
	
	/**
	 * Sets the media Id
	 * @return 
	 * @param $mediaid Object
	 */
	public function SetMediaId($mediaid) 
	{
		$db =& $this->db;
		
		//Set the mediaId
		$this->mediaid = $mediaid;
		
		//This doesnt really do anything on this media type. We need to generate our own unique media id.
		
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
		
		//Create a region to work with
		include_once("lib/app/region.class.php");
	
		$region = new region($db);
		
		//Set the layout Xml
		$layoutXml = $region->GetLayoutXml($layoutid);
		
		$xml = simplexml_load_string($layoutXml);
		
		//Get the media node and extract the info
		$mediaNodeXpath = $xml->xpath("//region[@id='$regionid']/media[@id='$mediaid']");
		$mediaNode 		= $mediaNodeXpath[0];
		
		$this->text	 	 = (string) $mediaNode->text;
		$this->duration  = (string) $mediaNode['duration'];
		$this->direction = (string) $mediaNode['direction'];
		
		return true;
	}
	
	/**
	 * Sets the Database
	 * @return 
	 * @param $db Object
	 */
	public function SetDb($db)
	{
		$this->db =& $db;
		
		return true;
	}
 
 	/**
 	 * Converts this Media object into Xml
 	 * @return 
 	 */
 	public function AsXml()
	{	
		$xml = <<<XML
		<media uri="" id="$this->mediaid" duration="$this->duration" direction="$this->direction" type="text" name="Text" filename="" lkid="">
			<text>
				<![CDATA[
				$this->text
				]]>
			</text>
			<template></template>
		</media>
XML;
		return $xml;
	}
	
	/**
	 * Return the Add Form as HTML
	 * @return 
	 */
	public function AddForm()
	{
		$db =& $this->db;
		
		$layoutid = $_REQUEST['layoutid'];
		$regionid = $_REQUEST['regionid'];
		
		//Would like to get the regions width / height 
		$rWidth		= $_REQUEST['rWidth'];
		$rHeight	= $_REQUEST['rHeight'];
		
		
		$direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down", "direction");
		
		$form = <<<FORM
		<form class="dialog_text_form" method="post" action="index.php?p=module&mod=text&q=AddMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" id="iRegionId" name="regionid" value="$regionid">
			<table>
				<tr>
		    		<td><label for="direction" title="The Direction this text should move, if any">Direction<span class="required">*</span></label></td>
		    		<td>$direction_list</td>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" type="text"></td>		
				</tr>
				<tr>
					<td colspan="4">
						<textarea id="ta_text" name="ta_text"></textarea>
					</td>
				</tr>
				<tr>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.org.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input id="btnSave" type="submit" value="Save"  />
						<input id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" onclick="return init_button(this,'Region Options','',region_options_callback)" value="Cancel" />
						<input type="button" onclick="window.open('$this->help_link')" value="Help" />
					</td>
				</tr>
			</table>
		</form>
FORM;
		return $form;
	}
	
	/**
	 * Return the Edit Form as HTML
	 * @return 
	 */
	public function EditForm()
	{
		$db =& $this->db;
		
		$layoutid = $_REQUEST['layoutid'];
		$regionid = $_REQUEST['regionid'];
		$mediaid  = $_REQUEST['mediaid'];
		
		//Set the mediaId and get the info from the Db
		$this->SetMediaId($mediaid);
		
		//Set the media information (get the node)
		$this->SetRegionInformation($layoutid, $regionid, $mediaid);
		
		$direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down", "direction", $this->direction);
		
		//Output the form
		$form = <<<FORM
		<form class="dialog_text_form" method="post" action="index.php?p=module&mod=text&q=EditMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" id="iRegionId" name="regionid" value="$regionid">
			<table>
				<tr>
		    		<td><label for="direction" title="The Direction this text should move, if any">Direction<span class="required">*</span></label></td>
		    		<td>$direction_list</td>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" value="$this->duration" type="text"></td>		
				</tr>
				<tr>
					<td colspan="4">
						<textarea id="ta_text" name="ta_text">$this->text</textarea>
					</td>
				</tr>
				<tr>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.org.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input id="btnSave" type="submit" value="Save"  />
						<input id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" onclick="return init_button(this,'Region Options','',region_options_callback)" value="Cancel" />
						<input type="button" onclick="window.open('$this->help_link')" value="Help" />
					</td>
				</tr>
			</table>
		</form>
FORM;
		return $form;		
	}
	
	/**
	 * Return the Delete Form as HTML
	 * @return 
	 */
	public function DeleteForm()
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new ResponseManager();
		
		//Parameters
		$layoutid 	= $_REQUEST['layoutid'];
		$regionid 	= $_REQUEST['regionid'];
		$mediaid	= $_REQUEST['mediaid'];

		//we can delete
		$form = <<<END
		<form class="dialog_form" method="post" action="index.php?p=module&mod=text&q=DeleteMedia">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<p>Are you sure you want to remove this webpage from Xibo? <span class="required">It will be lost</span>.</p>
			<input id="btnSave" type="submit" value="Yes"  />
			<input id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" onclick="return init_button(this,'Region Options','',region_options_callback)" value="No" />
			<input type="button" onclick="window.open('$this->help_link')" value="Help" />
		</form>
END;
		
		$arh->decode_response(true, $form);
	}
	
	/**
	 * Add Media to the Database
	 * @return 
	 */
	public function AddMedia()
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new ResponseManager();
		
		//Other properties
		$direction	  = $_POST['direction'];
		$duration	  = $_POST['duration'];
		$text		  = $_POST['ta_text'];
		
		//Optional parameters
		$layoutid = $_POST['layoutid'];
		$regionid = $_POST['regionid'];
		
		//Do we want to assign this to the region after adding it?
		if ($layoutid == "" && $regionid == "")
		{
			$this->message .= "Text must be assigned to regions";
			return false;
		}
		
		//validation
		if ($text == "")
		{
			$this->message .= "Please enter some text";
			return false;
		}
		
		//Validate the URL?
		
		if (!is_numeric($duration))
		{
			$this->message .= "You must enter a value for duration";
			return false;
		}
		
		//Generate a MediaID
		$this->SetMediaId(uniqid());
		
		$this->text		= $text;
		$this->duration = $duration;
		$this->direction = $direction;
		
		//Set this as the session information
		setSession('content', 'type', 'text');
		
		//Do the assignment here - we probabily want to create a region object to handle this.
		include_once("lib/pages/region.class.php");
	
		$region = new region($db);
		
		if (!$region->AddMedia($layoutid, $regionid, "", $this->AsXml()))
		{
			$this->message = "Error adding this media to the library";
			return false;
		}
		
		return true;
	}
	
	/**
	 * Edit Media in the Database
	 * @return 
	 */
	public function EditMedia()
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new ResponseManager();
		
		//Other properties
		$direction	  = $_POST['direction'];
		$duration	  = $_POST['duration'];
		$text		  = $_POST['ta_text'];
		
		if (get_magic_quotes_gpc())
		{
			$text = stripslashes($text);
		}
		
		//Optional parameters
		$layoutid = $_POST['layoutid'];
		$regionid = $_POST['regionid'];
		$mediaid  = $_POST['mediaid'];
		
		//Ensure regions
		if ($layoutid == "" && $regionid == "")
		{
			$this->message .= "Text must be assigned to regions";
			return false;
		}
		
		//validation
		if ($text == "")
		{
			$this->message .= "Please enter some text";
			return false;
		}
		
		//Validate the URL?
		
		if (!is_numeric($duration))
		{
			$this->message .= "You must enter a value for duration";
			return false;
		}
		
		//Save the info in the object
		$this->SetMediaId($mediaid);
		
		$this->text		= $text;
		$this->duration = $duration;
		$this->direction = $direction;
		
		//Do the assignment here - we probabily want to create a region object to handle this.
		include_once("lib/pages/region.class.php");
	
		$region = new region($db);
		
		if (!$region->SwapMedia($layoutid, $regionid, "", $mediaid, $mediaid, $this->AsXml()))
		{
			$message = "Unable to assign to the Region";
			return false;
		}

		//Set this as the session information
		setSession('content', 'type', 'text');
		
		return true;		
	}
	
	/**
	 * Delete Media from the Database
	 * @return 
	 */
	public function DeleteMedia() 
	{
		$db =& $this->db;
		
		$layoutid = $_REQUEST['layoutid'];
		$regionid = $_REQUEST['regionid'];
		$mediaid  = $_REQUEST['mediaid'];
		
		//Options
		//Regardless of the option we want to unassign.
		include_once("lib/app/region.class.php");
	
		$region = new region($db);
		
		if (!$region->RemoveMedia($layoutid, $regionid, $lkid, $mediaid))
		{
			$this->message = "Unable to Remove this media from the Layout";
			return false;
		}
		
		return true;
	}
}

?>