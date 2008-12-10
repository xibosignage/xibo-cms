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
class media_webpage {
	
	private $db;
	
	//Media information
	private $mediaid;
	private	$duration;
	private $uri;
	
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
		
		$this->uri		= htmlentities((string) $mediaNode['uri']);
		$this->duration = (string) $mediaNode['duration'];
		
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
		<media uri="$this->uri" sequence="" id="$this->mediaid" duration="$this->duration" direction="none" type="webpage" name="$this->uri" filename="" lkid="">
			<text></text>
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
		
		$form = <<<FORM
		<form class="dialog_form" method="post" action="index.php?p=module&mod=webpage&q=AddMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<table>
				<tr>
		    		<td><label for="uri" title="The Location (URL) of the webpage. E.g. http://www.xstreamedia.co.uk">Link<span class="required">*</span></label></td>
		    		<td><input id="uri" name="uri" type="text"></td>
				</tr>
				<tr>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" type="text"></td>		
				</tr>
				<tr>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.co.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
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
		
		$uri = html_entity_decode($this->uri);
		
		//Output the form
		$form = <<<FORM
		<form class="dialog_form" method="post" action="index.php?p=module&mod=webpage&q=EditMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<input type="hidden" name="mediaid" value="$mediaid">
			<table>
				<tr>
		    		<td><label for="uri" title="The Location (URL) of the webpage. E.g. http://www.xstreamedia.co.uk">Link<span class="required">*</span></label></td>
		    		<td><input id="uri" name="uri" value="$uri" type="text"></td>
				</tr>
				<tr>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed (may be overridden on each layout)">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" value="$this->duration" type="text"></td>		
				</tr>
				<tr>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.co.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
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
		$arh = new AjaxRequest();
		
		//Parameters
		$layoutid 	= $_REQUEST['layoutid'];
		$regionid 	= $_REQUEST['regionid'];
		$mediaid	= $_REQUEST['mediaid'];

		//we can delete
		$form = <<<END
		<form class="dialog_form" method="post" action="index.php?p=module&mod=webpage&q=DeleteMedia">
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
		$arh = new AjaxRequest();
		
		//Other properties
		$uri		  = htmlentities($_POST['uri']);
		$duration	  = $_POST['duration'];
		
		//Optional parameters
		$layoutid = $_POST['layoutid'];
		$regionid = $_POST['regionid'];
		
		//Do we want to assign this to the region after adding it?<whey
		if ($layoutid == "" && $regionid == "")
		{
			$this->message .= "Webpages must be assigned to regions";
			return false;
		}
		
		//validation
		if ($uri == "")
		{
			$this->message .= "Please enter a link.";
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
		
		$this->uri 		= $uri;
		$this->duration = $duration;
		
		//Set this as the session information
		setSession('content', 'type', 'webpage');
		
		//Do the assignment here - we probabily want to create a region object to handle this.
		include_once("lib/app/region.class.php");
	
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
		$arh = new AjaxRequest();
		
		//Other properties
		$uri		  = htmlentities($_POST['uri']);
		$duration	  = $_POST['duration'];
		
		//Optional parameters
		$layoutid = $_POST['layoutid'];
		$regionid = $_POST['regionid'];
		$mediaid  = $_POST['mediaid'];
		
		//Ensure regions
		if ($layoutid == "" && $regionid == "")
		{
			$this->message .= "Webpages must be assigned to regions";
			return false;
		}
		
		//validation
		if ($uri == "")
		{
			$this->message .= "Please enter a link.";
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
		
		$this->uri 		= $uri;
		$this->duration = $duration;
		
		//Do the assignment here - we probabily want to create a region object to handle this.
		include_once("lib/app/region.class.php");
	
		$region = new region($db);
		
		if (!$region->SwapMedia($layoutid, $regionid, "", $mediaid, $mediaid, $this->AsXml()))
		{
			$message = "Unable to assign to the Region";
			return false;
		}

		//Set this as the session information
		setSession('content', 'type', 'webpage');
		
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
	
	/**
	 * Deletes the media files associated with this record
	 * @return 
	 */
	private function DeleteMediaFiles()
	{
		//Library location
		$databaseDir = config::getSetting($db, "libraryLocation");
		
		//3 things to check for..
		//the actual file, the thumbnail, the background
		if (file_exists($databaseDir.$this->storedAs))
		{
			unlink($databaseDir.$this->storedAs);
		}
		
		if (file_exists($databaseDir."tn_".$this->storedAs))
		{
			unlink($databaseDir."tn_".$this->storedAs);
		}
		
		if (file_exists($databaseDir."bg_".$this->storedAs))
		{
			unlink($databaseDir."bg_".$this->storedAs);
		}
		
		return true;
	}
	
	/**
	 * Creates a thumbnail image
	 * @return 
	 * @param $name Source
	 * @param $filename Target
	 * @param $new_w New Width
	 * @param $new_h New Height
	 */
	private function CreateThumb($name, $filename, $new_w, $new_h)
	{
		$system=explode('.',$name);
		if (preg_match('/jpg|jpeg/',$system[1]))
		{
			$src_img = imagecreatefromjpeg($name);
		}
		if (preg_match('/png/',$system[1]))
		{
			$src_img = imagecreatefrompng($name);
		}
		
		$old_x = imageSX($src_img);
		$old_y = imageSY($src_img);
		if ($old_x > $old_y) 
		{
			$thumb_w=$new_w;
			$thumb_h=$old_y * ($new_h/$old_x);
		}
		if ($old_x < $old_y) 
		{
			$thumb_w=$old_x * ($new_w/$old_y);
			$thumb_h=$new_h;
		}
		if ($old_x == $old_y) 
		{
			$thumb_w = $new_w;
			$thumb_h = $new_h;
		}
		
		$dst_img = ImageCreateTrueColor($thumb_w,$thumb_h);
		imagecopyresampled($dst_img,$src_img,0,0,0,0,$thumb_w,$thumb_h,$old_x,$old_y); 
	
		if (preg_match("/png/",$system[1]))
		{
			imagepng($dst_img,$filename); 
		} 
		else 
		{
			imagejpeg($dst_img,$filename); 
		}
		imagedestroy($dst_img); 
		imagedestroy($src_img); 
		
		return;
	}
}

?>