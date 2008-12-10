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
class media_powerpoint {
	
	private $db;
	
	//Media information
	private $mediaid;
	private $name;
	private	$type;
	private	$duration;
	private	$originalFilename;
	private	$userid;
	private	$permissionid;
	private	$retired;
	private $isEdited;
	private $editedMediaid;
	private $ext; //derived from the originalFilename;
	
	//Information vars
	public $message = "";
	private $maxFileSize;
	private $maxFileSizeBytes;
	private $help_link;
	

	function __construct() 
	{
		
		$this->maxFileSize = ini_get('upload_max_filesize');
		$this->maxFileSizeBytes = convertBytes($this->maxFileSize);
		
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
		
		//Load what we know about this media into the object
		$SQL = "SELECT name, type, duration, originalFilename, userID, permissionID, retired, storedAs, isEdited, editedMediaID FROM media WHERE mediaID = $mediaid ";
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error()); //log the error
			
			$this->message = "Error querying for the Media information with media ID [$mediaid] ";
			return false;
		}
		
		if ($db->num_rows($result) != 1)
		{
			trigger_error("More than one row for mediaId [$mediaid] How can this be?");
			
			$this->message = "Error querying for the Media information with media ID [$mediaid] ";
			return false;
		}
		
		$row = $db->get_row($result);
		
		//Store this medias information in the object
		$this->name 				= $row[0];
		$this->type 				= $row[1];
		$this->duration 			= $row[2];
		$this->originalFilename 	= $row[3];
		$this->userid 				= $row[4];
		$this->permissionid 		= $row[5];
		$this->retired 				= $row[6];
		$this->storedAs				= $row[7];
		$this->isEdited				= $row[8];
		$this->editedMediaID		= $row[9];
		
		//derive the ext
		$this->ext					= strtolower(substr(strrchr($this->originalFilename, "."), 1));
		
		//Calc the permissions on it aswell
		global $user;
		
		list($this->see_permissions , $this->edit_permissions) = $user->eval_permission($this->userid, $this->permissionid);
		
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
		$uri = $this->mediaid . "." . $this->ext;
		
		$xml = <<<XML
		<media uri="$uri" sequence="" id="$this->mediaid" duration="$this->duration" direction="none" type="powerpoint" name="$this->name" filename="$this->storedAs">
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
		
		global $session;
		
		// Set the Session / Security information
		$sessionId 		= session_id();
		$securityToken 	= CreateFormToken();
		
		$session->setSecurityToken($securityToken);
		
		//Parameters
		$layoutid 	= GetParam("layoutid", _REQUEST, _INT);
		$regionid 	= GetParam("regionid", _REQUEST, _STRING);
		
		//Get the default value for the shared list
		$default = config::getSetting($db,"defaultMedia");

		if($default=="private") {
			$permissionid = 1;
		}
		else {
			$permissionid = 0;
		}
		
		//shared list
		$shared_list = dropdownlist("SELECT permissionID, permission FROM permission", "permissionid", $permissionid);
		
		//Save button is different depending on if we are on a region or not
		if ($regionid != "")
		{
			setSession('content','mediatype','powerpoint');
			
			$save_button = <<<END
			<input id="btnSave" type="submit" value="Save" disabled />
			<input id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" onclick="return init_button(this,'Region Options','',region_options_callback)" value="Cancel" />
			<input type="button" href="index.php?p=content&q=LibraryAssignForm&layoutid=$layoutid&regionid=$regionid" onclick="return grid_form(this,'Media Library',exec_filter_callback,'library_filter_form','pages_grid',600,570)" title="Library" value="Library" />
			<input type="button" onclick="window.open('$this->help_link')" value="Help" />
END;
		}
		else
		{
			$save_button = <<<END
			<input id="btnSave" type="submit" value="Save" disabled />
			<input id="btnCancel" type="button" title="Close" onclick="$('#div_dialog').parent().parent().hide()" value="Cancel" />
			<input type="button" onclick="window.open('$this->help_link')" value="Help" />
END;
		}
		
		$form = <<<FORM
		<div style="display:none"><iframe name="fileupload" width="1px" height="1px"></iframe></div>
		<div>
			<form id="file_upload" method="post" action="index.php?p=content&q=FileUpload" enctype="multipart/form-data" target="fileupload">
				<input type="hidden" id="PHPSESSID" value="$sessionId" />
				<input type="hidden" id="SecurityToken" value="$securityToken" />
				<input type="hidden" name="MAX_FILE_SIZE" value="$this->maxFileSizeBytes" />
				<table>
					<tr> 
						<td><label for="file">PowerPoint File<span class="required">*</span></label></td>
						<td colspan="3">
							<input type="file" name="media_file" onchange="fileFormSubmit();this.form.submit();" />
						</td>
					</tr>
				</table>
			</form>
		</div>
		<div id="uploadProgress" style="display:none">
			<img src="img/loading.gif"><span style="padding-left:10px">You may fill in the form while your file is uploading.</span>
		</div>
		<form class="dialog_file_form" method="post" action="index.php?p=module&mod=powerpoint&q=AddMedia">
			<input type="hidden" name="layoutid" value="$layoutid" />
			<input type="hidden" name="regionid" value="$regionid" />
			<input type="hidden" id="txtFileName" name="txtFileName" readonly="true" />
			<input type="hidden" name="hidFileID" id="hidFileID" value="" />
			<table>
				<tr>
		    		<td><label for="name" title="The name of the powerpoint presentation. Leave this blank to use the file name">Name</label></td>
		    		<td><input id="name" name="name" type="text"></td>
				</tr>
				<tr>
		    		<td><label for="duration" title="The duration in seconds this image should be displayed (may be overridden on each layout)">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" type="text"></td>
					<td><label for="permissionid">Sharing<span class="required">*</span></label></td>
					<td>
					$shared_list
					</td>			
				</tr>
				<tr>
					<td></td>
					<td>This form accepts: <span class="required">ppt and pps</span> files up to a maximum size of <span class="required">$this->maxFileSize</span>.</td>
				</tr>
				<tr>
					<td></td>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.co.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
				</tr>
				<tr>
					<td></td>
					<td colspan="4">$save_button</td>
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
		
		//Parameters
		$layoutid 	= GetParam("layoutid", _REQUEST, _INT);
		$regionid 	= GetParam("regionid", _REQUEST, _STRING);
		$mediaid  	= GetParam("mediaid", _REQUEST, _STRING);
		$lkid  		= GetParam("lkid", _REQUEST, _INT);
		
		// Set the Session / Security information
		global $session;
		$sessionId 		= session_id();
		$securityToken 	= CreateFormToken();
		
		$session->setSecurityToken($securityToken);
		
		//Set the mediaId and get the info from the Db
		$this->SetMediaId($mediaid);
		
		//shared list
		$shared_list = dropdownlist("SELECT permissionID, permission FROM permission", "permissionid", $this->permissionid);
		
		//Save button is different depending on if we are on a region or not
		if ($regionid != "")
		{
			$save_button = <<<END
			<input id="btnSave" type="submit" value="Save" />
			<input id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" onclick="return init_button(this,'Region Options','',region_options_callback)" value="Cancel" />
			<input type="button" href="index.php?p=content&q=LibraryAssignForm&layoutid=$layoutid&regionid=$regionid" onclick="return grid_form(this,'Media Library',exec_filter_callback,'library_filter_form','pages_grid',600,570)" title="Library" value="Library" />
			<input type="button" onclick="window.open('$this->help_link')" value="Help" />
END;
		}
		else
		{
			$save_button = <<<END
			<input id="btnSave" type="submit" value="Save" />
			<input id="btnCancel" type="button" title="Close" onclick="$('#div_dialog').parent().parent().hide()" value="Cancel" />
			<input type="button" onclick="window.open('$this->help_link')" value="Help" />
END;
		}
		
		$form = <<<FORM
		<div style="display:none"><iframe name="fileupload" width="1px" height="1px"></iframe></div>
		<div>
			<form id="file_upload" method="post" action="index.php?p=content&q=FileUpload" enctype="multipart/form-data" target="fileupload">
				<input type="hidden" id="PHPSESSID" value="$sessionId" />
				<input type="hidden" id="SecurityToken" value="$securityToken" />
				<input type="hidden" name="MAX_FILE_SIZE" value="$this->maxFileSizeBytes" />
				<table>
					<tr> 
						<td><label for="file">Upload new Version</label></td>
						<td colspan="3">
							<input type="file" name="media_file" onchange="fileFormSubmit();this.form.submit();" />
						</td>
					</tr>
				</table>
			</form>
		</div>
		<div id="uploadProgress" style="display:none">
			<img src="img/loading.gif"><span style="padding-left:10px">You may fill in the form while your file is uploading.</span>
		</div>
		<form class="dialog_file_form" method="post" action="index.php?p=module&mod=powerpoint&q=EditMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" name="lkid" value="$lkid">
			<input type="hidden" id="txtFileName" name="txtFileName" readonly="true" />
			<input type="hidden" name="hidFileID" id="hidFileID" value="" />
			<table>
				<tr>
		    		<td><label for="name" title="The name of the powerpoint presentation. Leave this blank to use the file name">Name</label></td>
		    		<td><input id="name" name="name" type="text" value="$this->name"></td>
				</tr>
				<tr>
		    		<td><label for="duration" title="The duration in seconds this powerpoint presentation should be displayed (may be overridden on each layout)">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" type="text" value="$this->duration"></td>
					<td><label for="permissionid">Sharing<span class="required">*</span></label></td>
					<td>
					$shared_list
					</td>			
				</tr>
				<tr>
					<td></td>
					<td>This form accepts: <span class="required">ppt and pps</span> files up to a maximum size of <span class="required">$this->maxFileSize</span>.</td>
				</tr>
				<tr>
					<td></td>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.co.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
				</tr>
				<tr>
					<td></td>
					<td colspan="3">$save_button</td>
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
		$layoutid 	= GetParam("layoutid", _REQUEST, _INT);
		$regionid 	= GetParam("regionid", _REQUEST, _STRING);
		$mediaid  	= GetParam("mediaid", _REQUEST, _STRING);
		$lkid  		= GetParam("lkid", _REQUEST, _INT);
		
		//Set the mediaId and get the info from the Db
		$this->SetMediaId($mediaid);
		
		$options = "";
		//Always have the abilty to unassign from the region
		$options .= "unassign|Unassign from this region only";
		
		//Is this user allowed to edit this media?
		if ($this->edit_permissions)
		{
			$options .= ",retire|Unassign from this region and retire";
		
			//Is this media retired?
			if ($this->editedMediaid != "")
			{
				$revised = true;
			}
			else
			{
				$revised = false;
			}
		
			//Is this media being used anywhere else?
			if (isset($_REQUEST['layoutid']))
			{
				$SQL = "SELECT layoutID FROM lklayoutmedia WHERE mediaID = $mediaid AND layoutid <> $layoutid AND regionID <> '$regionid' ";
			}
			else
			{
				$SQL = "SELECT layoutID FROM lklayoutmedia WHERE mediaID = $mediaid ";
				$options = "";
			}

			if (!$results = $db->query($SQL)) 
			{
				trigger_error($db->error());
				$arh->decode_response(false,"Cannot determine if this media has been used. Error.");
			}
			if ($db->num_rows($results) == 0 && !$revised)
			{
				$options .= ",delete|Delete this media"; //not used anywhere else
			}
			else
			{
				$options .= ",retire|Retire this media";
			}
		}
		else
		{
			// If this is the normal content page then say they cant edit, otherwise display the form with only the unassign option
			if ($layoutid == "")
			{
				$arh->decode_response(false, "You do not have permission to alter/delete this media");
			}
		}
		
		$options = ltrim($options, ",");
		
		$deleteOptions = listcontent($options,"options");

		//we can delete
		$form = <<<END
		<form class="dialog_form" method="post" action="index.php?p=module&mod=powerpoint&q=DeleteMedia">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" name="lkid" value="$lkid">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<p>'$this->name' <br />Are you sure you want to: $deleteOptions ?</p>
			<input id="btnSave" type="submit" value="Yes"  />
			<input id="btnCancel" type="button" title="No / Cancel" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" onclick="$(this).parent().parent().dialogClose();return false; " value="No" />
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
		
		if (!$_POST['hidFileID']) 
		{
			$this->message = "Cannot upload that file. <br/> Please ensure you have picked a file.";
			return false;
		}

		// File data
		$fileName 	= strtolower(basename($_POST['txtFileName']));
		$ext 		= strtolower(substr(strrchr($fileName, "."), 1));  //get the file extension
		$tmpName	= $_POST['hidFileID'];
		
		//Other properties
		$name		  = $_POST['name'];
		$duration	  = $_POST['duration'];
		$permissionid = $_POST['permissionid'];
		
		//File validation
		if ($ext != "ppt" && $ext != "pps")
		{
			$this->message .= "The presentation must be a PPT or PPS file";
			return false;
		}
		
		//validation
		if ($name == "")
		{
			//Use the File Name
			$name = $fileName;
		}
		else
		{
			$name = cleanFilename($name);	
		}
		
		if (strlen($name) > 100) 
		{
			$this->message .= "The media name cannot be longer than 100 characters";
			return false;
		}
		
		if (!is_numeric($duration))
		{
			$this->message .= "You must enter a value for duration";
			return false;
		}
		
		//Ensure the name is not already in the database
		$SQL = "SELECT name  FROM media  WHERE name = '" . $name . "' AND userid = ".$_SESSION['userid'];

		if(!$result = $db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->message = "Error checking whether the media name is ok. Try choosing a different name.";
			return false;
		} 

		if ($db->num_rows($result) != 0)
		{
			$this->message = "Some media you own already has this name. Please choose another.";
			return false;
		}
		
		//Optional parameters
		$layoutid = $_POST['layoutid'];
		$regionid = $_POST['regionid'];
		
		//Do we want to assign this to the region after adding it?
		if ($layoutid != "" && $regionid != "")
		{
			$assignToRegion = true;
		}
		else
		{
			$assignToRegion = false;
		}
			
		//File upload directory.. get this from the settings object
		$databaseDir = config::getSetting($db, "libraryLocation");

		$SQL =  "INSERT INTO media (name, type, duration, originalFilename, permissionID, userID, retired ) ";
		$SQL .= "VALUES ('$name', 'powerpoint', '$duration', '$fileName', $permissionid, " . $_SESSION['userid'] . ", 0)";

		if (!$mediaid = $db->insert_query($SQL))
		{
			trigger_error($db->error());
			$this->message = "Database error adding this media record.";
			return false;
		}
		
		//What are we going to store this media as...
		$storedAs = $mediaid.".".$ext;
		 
		//Now we need to move the file
		if (!$result = rename($databaseDir."/temp/".$tmpName, $databaseDir.$storedAs))
		{
			//If we couldnt move it - we need to delete the media record we just added
			$SQL = "DELETE FROM media WHERE mediaID = $mediaid ";
			
			if (!$db->insert_query($SQL))
			{
				trigger_error($db->error());
				return false;
			}
		}
		
		//Update the media record to include this information
		$SQL = "UPDATE media SET storedAs = '$storedAs' WHERE mediaid = $mediaid";
		if (!$db->query($SQL))
		{
			trigger_error($db->error());
			return true;
		}
		
		//Set this as the session information
		setSession('content', 'type', 'image');
		
		if ($assignToRegion)
		{
			//Do the assignment here - we probabily want to create a region object to handle this.
			include_once("lib/app/region.class.php");
		
			$region = new region($db);
			
			if (!$region->AddMedia($layoutid, $regionid, $mediaid))
			{
				$message = "Media Added to Library - but not to this layout. Please try and assign it from the library.";
				return false;
			}
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

		//For the edit we may not have a file (only when revising)
		if ($_POST['txtFileName'] == "") {
			$fileRevision = false;
		}
		else
		{
			if (!$_POST['hidFileID']) 
			{
				$this->message = "Cannot upload that file. <br/>You must have picked a file.";
				return false;
			}
			
			$fileRevision = true;
			
			// File data
			$fileName 	= strtolower(basename($_POST['txtFileName']));
			$ext 		= strtolower(substr(strrchr($fileName, "."), 1));  //get the file extension
			$tmpName	= $_POST['hidFileID'];
			
			//File validation
			if ($ext != "ppt" && $ext != "pps")
			{
				$this->message .= "The presentation must be a PPT or PPS file";
				return false;
			}
		}

		//Other properties
		$mediaid	  = $_POST['mediaid'];
		$name		  = $_POST['name'];
		$duration	  = $_POST['duration'];
		$permissionid = $_POST['permissionid'];
		
		//Optional parameters
		$layoutid 	= $_POST['layoutid'];
		$regionid 	= $_POST['regionid'];
		$lkid	 	= $_POST['lkid'];
		
		//Do we want to assign this to the region after adding it?<whey
		if ($layoutid != "" && $regionid != "")
		{
			$assignToRegion = true;
		}
		else
		{
			$assignToRegion = false;
		}
		
		//validation
		if ($name == "" && $fileRevision)
		{
			//Use the File Name
			$name = $fileName;
		}
		else
		{
			if ($name == "")
			{
				$this->message .= "The Name cannot be blank.";
				return false;
			}
			
			$name = cleanFilename($name);	
		}
		
		if (strlen($name) > 100) {
			$this->message .= "The media name cannot be longer than 100 characters";
			return false;
		}
		
		if (!is_numeric($duration))
		{
			$this->message .= "You must enter a value for duration";
			return false;
		}
		
		//Ensure the name is not already in the database
		$SQL = "SELECT name FROM media WHERE name = '" . $name . "' AND userid = ".$_SESSION['userid']. " AND mediaID <> $mediaid AND isEdited = 0 ";

		if(!$result = $db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->message = "Error checking whether the media name is ok. Try choosing a different name.";
			return false;
		} 

		if ($db->num_rows($result) != 0)
		{
			$this->message = "Some media you own already has this name. Please choose another.";
			return false;
		}
					
		//File upload directory.. get this from the settings object
		$databaseDir = config::getSetting($db, "libraryLocation");
		
		//Are we revising this media - or just plain editing
		if ($fileRevision)
		{
			//Revising
			//Insert the new record - with the editedMediaID pointing to the original
			$SQL =  "INSERT INTO media (name, type, duration, originalFilename, permissionID, userID, retired, editedMediaID ) ";
			$SQL .= "VALUES ('$name', 'powerpoint', '$duration', '$fileName', $permissionid, " . $_SESSION['userid'] . ", 0, $mediaid )";
	
			if (!$new_mediaid = $db->insert_query($SQL))
			{
				trigger_error($db->error());
				$this->message = "Database error adding this media record.";
				return false;
			}
			
			//What are we going to store this media as...
			$storedAs = $new_mediaid.".".$ext;
			 
			//Now we need to move the file
			if (!$result = rename($databaseDir."/temp/".$tmpName, $databaseDir.$storedAs))
			{
				//If we couldnt move it - we need to delete the media record we just added
				$SQL = "DELETE FROM media WHERE mediaID = $new_mediaid ";
				
				if (!$db->insert_query($SQL))
				{
					trigger_error($db->error());
					return false;
				}
			}
			
			//Update the media record to include this information
			$SQL = "UPDATE media SET storedAs = '$storedAs' WHERE mediaid = $new_mediaid";
			if (!$db->query($SQL))
			{
				trigger_error($db->error());
				return false;
			}
			
			//Update the existing record with the new record's id
			$SQL =  "UPDATE media SET isEdited = 1, editedMediaID = $mediaid ";
			$SQL .= " WHERE editedMediaID = $mediaid and mediaID <> $new_mediaid ";
	
			if (!$db->query($SQL))
			{
				trigger_error($db->error());
				$this->message = "Database error editing this media record.";
				return false;
			}
		}
		else
		{
			//Editing
			$new_mediaid = $mediaid;
			
			$SQL =  "UPDATE media SET name = '$name', duration = '$duration', permissionID = $permissionid";
			$SQL .= " WHERE mediaID = $mediaid ";
	
			if (!$db->query($SQL))
			{
				trigger_error($db->error());
				$this->message = "Database error editing this media record.";
				return false;
			}
		}
		
		//we now need to edit this region with the new information
		//Little trick - call swap with the same ID's it will just refresh the information
		if ($assignToRegion)
		{
			include_once("lib/app/region.class.php");
		
			$region = new region($db);
			
			if (!$region->SwapMedia($layoutid, $regionid, $lkid, $mediaid, $new_mediaid))
			{
				$message = "Media Added to Library - but not to this layout. Please try and assign it from the library.";
				return false;
			}
		}
		
		//Set this as the session information
		setSession('content', 'type', 'image');
		
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
		$lkid	  = $_REQUEST['lkid'];
		$options  = $_POST['options'];
		
		//Options
		//Regardless of the option we want to unassign.
		include_once("lib/app/region.class.php");
	
		$region = new region($db);
		
		if (!$region->RemoveMedia($layoutid, $regionid, $lkid, $mediaid))
		{
			$this->message = "Unable to Remove this media from the Layout";
			return false;
		}
				
		//If we are set to retire we retire
		if ($options == "retire")
		{
			//Update the media record to say it is retired
			$SQL = "UPDATE media SET retired = 1 WHERE mediaid = $mediaid ";
			
			if (!$db->query($SQL))
			{
				trigger_error($db->error());
				$this->message = "Database error retiring this media record.";
				return false;
			}
		}
		
		//If we are set to delete, we delete
		if ($options == "delete")
		{
			//Update the media record to say it is retired
			$SQL = "DELETE FROM media WHERE mediaid = $mediaid ";
			
			if (!$db->query($SQL))
			{
				trigger_error($db->error());
				$this->message = "Database error deleting this media record.";
				return false;
			}
			
			$this->DeleteMediaFiles();
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