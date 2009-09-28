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
class video extends Module
{
	// Custom Media information
	private $uri;
	private $maxFileSize;
	private $maxFileSizeBytes;

	public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '')
	{
		// Must set the type of the class
		$this->type 			= 'video';

		// Get the max upload size from PHP
		$this->maxFileSize 		= ini_get('upload_max_filesize');
		$this->maxFileSizeBytes = convertBytes($this->maxFileSize);

		// Must call the parent class
		parent::__construct($db, $user, $mediaid, $layoutid, $regionid);
	}

	/**
	 * Sets the Layout and Region Information
	 *  it will then fill in any blanks it has about this media if it can
	 * @return
	 * @param $layoutid Object
	 * @param $regionid Object
	 * @param $mediaid Object
	 */
	public function SetRegionInformation($layoutid, $regionid)
	{
		$db						=& $this->db;
		$this->layoutid 		= $layoutid;
		$this->regionid 		= $regionid;
		$mediaid			 	= $this->mediaid;
		$this->existingMedia 	= false;

		if ($this->regionSpecific == 1) return;

		// Load what we know about this media into the object
		$SQL = "SELECT name, type, duration, originalFilename, userID, permissionID, retired, storedAs, isEdited, editedMediaID FROM media WHERE mediaID = $mediaid ";

		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error()); //log the error
			return false;
		}

		if ($db->num_rows($result) != 1)
		{
			trigger_error("More than one row for mediaId [$mediaid] How can this be?");
			return false;
		}

		$row 				= $db->get_row($result);
		$duration			= $row[2];
		$storedAs			= $row[7];

		// Required Attributes
		$this->duration = $duration;

		// Any Options
		$this->SetOption('uri', $storedAs);

		return true;
	}

	/**
	 * Return the Add Form as HTML
	 * @return
	 */
	public function AddForm()
	{
		global $session;
		$db 			=& $this->db;
		$user			=& $this->user;

		// Would like to get the regions width / height
		$layoutid		= $this->layoutid;
		$regionid		= $this->regionid;

		// Set the Session / Security information
		$sessionId 		= session_id();
		$securityToken 	= CreateFormToken();

		$session->setSecurityToken($securityToken);

		//Get the default value for the shared list
		$default = Config::GetSetting($db,"defaultMedia");

		$permissionid = 0;

		if($default=="private")
		{
			$permissionid = 1;
		}

		//shared list
		$shared_list = dropdownlist("SELECT permissionID, permission FROM permission", "permissionid", $permissionid);

		//Save button is different depending on if we are on a region or not
		if ($regionid != "")
		{
			setSession('content','mediatype','video');

			$save_button = <<<END
			<input id="btnSave" type="submit" value="Save" disabled />
			<input class="XiboFormButton" id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" value="Cancel" />
			<input class="XiboFormButton" type="button" href="index.php?p=content&q=LibraryAssignForm&layoutid=$layoutid&regionid=$regionid" title="Library" value="Library" />
END;
		}
		else
		{
			$save_button = <<<END
			<input id="btnSave" type="submit" value="Save" disabled />
			<input class="XiboFormButton" id="btnCancel" type="button" title="Close" href="index.php?p=content&q=displayForms&sp=add" value="Cancel" />
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
						<td><label for="file">Video File<span class="required">*</span></label></td>
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
		<form class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=AddMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<input type="hidden" id="txtFileName" name="txtFileName" readonly="true" />
			<input type="hidden" name="hidFileID" id="hidFileID" value="" />
			<table width="100%">
				<tr>
		    		<td><label for="name" title="The name of the video. Leave this blank to use the file name">Name</label></td>
		    		<td><input id="name" name="name" type="text"></td>
				</tr>
				<tr>
		    		<td><label for="duration" title="The duration in seconds this video should be displayed (may be overridden on each layout)">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" type="text" value="$this->duration"></td>
					<td><label for="permissionid">Sharing<span class="required">*</span></label></td>
					<td>
					$shared_list
					</td>
				</tr>
				<tr>
					<td></td>
					<td>This form accepts: <span class="required">wmv, mpeg and mpg</span> files up to a maximum size of <span class="required">$this->maxFileSize</span>.</td>
				</tr>
				<tr>
					<td></td>
					<td colspan="3">$save_button</td>
				</tr>
			</table>
		</form>
FORM;

		$this->response->html 			= $form;
		$this->response->dialogTitle 	= 'Add New Video';
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '450px';
		$this->response->dialogHeight 	= '280px';

		return $this->response;
	}

	/**
	 * Return the Edit Form as HTML
	 * @return
	 */
	public function EditForm()
	{
		global $session;
		$db 			=& $this->db;
		$user			=& $this->user;

		// Would like to get the regions width / height
		$layoutid		= $this->layoutid;
		$regionid		= $this->regionid;
		$mediaid		= $this->mediaid;
		$lkid			= $this->lkid;
		$userid			= Kit::GetParam('userid', _SESSION, _INT);

		// Set the Session / Security information
		$sessionId 		= session_id();
		$securityToken 	= CreateFormToken();

		$session->setSecurityToken($securityToken);

		// Load what we know about this media into the object
		$SQL = "SELECT name, originalFilename, userID, permissionID, retired, storedAs, isEdited, editedMediaID FROM media WHERE mediaID = $mediaid ";

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

		$row 				= $db->get_row($result);
		$name 				= $row[0];
		$originalFilename 	= $row[1];
		$userid 			= $row[2];
		$permissionid 		= $row[3];
		$retired 			= $row[4];
		$storedAs			= $row[5];
		$isEdited			= $row[6];
		$editedMediaID		= $row[7];

		// derive the ext
		$ext				= strtolower(substr(strrchr($originalFilename, "."), 1));

		//Calc the permissions on it aswell
		list($see_permissions , $edit_permissions) = $user->eval_permission($userid, $permissionid);

		//shared list
		$shared_list = dropdownlist("SELECT permissionID, permission FROM permission", "permissionid", $permissionid);

		//Save button is different depending on if we are on a region or not
		if ($regionid != "")
		{
			setSession('content','mediatype','image');

			$extraNotes = '<em>Note: Uploading a new media item here will replace it on this layout only.</em>';

			$save_button = <<<END
			<input id="btnSave" type="submit" value="Save" />
			<input class="XiboFormButton" id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" value="Cancel" />
			<input class="XiboFormButton" type="button" href="index.php?p=content&q=LibraryAssignForm&layoutid=$layoutid&regionid=$regionid" title="Library" value="Library" />
END;
		}
		else
		{
			$extraNotes = '<em>Note: As you editing from the library uploading a new media item will not replace the old one from any layouts. To do this nagivate to the layout and edit the media from there.</em>';

			$save_button = <<<END
			<input id="btnSave" type="submit" value="Save" />
			<input id="btnCancel" type="button" title="Close" onclick="$('#div_dialog').dialog('close')" value="Cancel" />
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
						<td><label for="file">New Video File<span class="required">*</span></label></td>
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
		<form class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=EditMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" name="lkid" value="$lkid">
			<input type="hidden" name="hidFileID" id="hidFileID" value="" />
			<input type="hidden" id="txtFileName" name="txtFileName" readonly="true" />
			<input type="hidden" id="PHPSESSID" value="$sessionId" />
			<input type="hidden" id="SecurityToken" value="$securityToken" />
			<table>
				<tr>
		    		<td><label for="name" title="The name of the Video. Leave this blank to use the file name">Name</label></td>
		    		<td><input id="name" name="name" type="text" value="$name"></td>
				</tr>
				<tr>
		    		<td><label for="duration" title="The duration in seconds this image should be displayed (may be overridden on each layout)">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" type="text" value="$this->duration"></td>
					<td><label for="permissionid">Sharing<span class="required">*</span></label></td>
					<td>
					$shared_list
					</td>
				</tr>
				<tr>
					<td></td>
					<td>This form accepts: <span class="required">wmv, mpeg and mpg</span> files up to a maximum size of <span class="required">$this->maxFileSize</span>.</td>
				</tr>
				<tr>
					<td></td>
					<td colspan="2">$extraNotes</td>
				</tr>
				<tr>
					<td></td>
					<td colspan="3">$save_button</td>
				</tr>
			</table>
		</form>
FORM;

		$this->response->html 			= $form;
		$this->response->dialogTitle 	= 'Edit Video';
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '450px';
		$this->response->dialogHeight 	= '280px';

		return $this->response;
	}

	/**
	 * Return the Delete Form as HTML
	 * @return
	 */
	public function DeleteForm()
	{
		$db 			=& $this->db;
		$user			=& $this->user;

		// Would like to get the regions width / height
		$layoutid		= $this->layoutid;
		$regionid		= $this->regionid;
		$mediaid		= $this->mediaid;
		$lkid			= $this->lkid;
		$userid			= Kit::GetParam('userid', _SESSION, _INT);

		$options = "";
		//Always have the abilty to unassign from the region
		$options .= "unassign|Unassign from this region only";

		// Load what we know about this media into the object
		$SQL = "SELECT name, type, duration, originalFilename, userID, permissionID, retired, storedAs, isEdited, editedMediaID FROM media WHERE mediaID = $mediaid ";

		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error()); //log the error

			$this->response->SetError('Error querying for the Media information with media ID [$mediaid]');
			$this->response->keepOpen = true;
			return $this->response;
		}

		if ($db->num_rows($result) != 1)
		{
			trigger_error("More than one row for mediaId [$mediaid] How can this be?");

			$this->response->SetError('Error querying for the Media information with media ID [$mediaid]');
			$this->response->keepOpen = true;
			return $this->response;
		}

		$row 				= $db->get_row($result);
		$name 				= $row[0];
		$duration			= $row[2];
		$originalFilename 	= $row[3];
		$userid 			= $row[4];
		$permissionid 		= $row[5];
		$retired 			= $row[6];
		$storedAs			= $row[7];
		$isEdited			= $row[8];
		$editedMediaID		= $row[9];

		// derive the ext
		$ext				= strtolower(substr(strrchr($originalFilename, "."), 1));

		//Calc the permissions on it aswell
		list($see_permissions , $edit_permissions) = $user->eval_permission($userid, $permissionid);

		//Is this user allowed to edit this media?
		if ($edit_permissions)
		{
			$options .= ",retire|Unassign from this region and retire";

			//Is this media retired?
			if ($editedMediaID != "")
			{
				$revised = true;
			}
			else
			{
				$revised = false;
			}

			//Is this media being used anywhere else?
			if ($layoutid == "")
			{
				$SQL = "SELECT layoutID FROM lklayoutmedia WHERE mediaID = $mediaid ";
				$options = "";
			}
			else
			{
				$SQL = "SELECT layoutID FROM lklayoutmedia WHERE mediaID = $mediaid AND layoutid <> $layoutid AND regionID <> '$regionid' ";
			}

			if (!$results = $db->query($SQL))
			{
				trigger_error($db->error());

				$this->response->SetError('Cannot determine if this media has been used. Error.');
				$this->response->keepOpen = true;
				return $this->response;
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
				$this->response->SetError('You do not have permission to alter/delete this media.');
				$this->response->keepOpen = true;
				return $this->response;
			}
		}

		$options = ltrim($options, ",");

		$deleteOptions = listcontent($options,"options");

		//we can delete
		$form = <<<END
		<form class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=DeleteMedia">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" name="lkid" value="$lkid">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<p>'$name' <br />Are you sure you want to: $deleteOptions ?</p>
			<input id="btnSave" type="submit" value="Yes"  />
			<input id="btnCancel" type="button" title="No / Cancel" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" onclick="$('#div_dialog').dialog('close');return false; " value="No" />
		</form>
END;

		$this->response->html 			= $form;
		$this->response->dialogTitle 	= 'Delete Video';
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '450px';
		$this->response->dialogHeight 	= '280px';

		return $this->response;
	}

	/**
	 * Add Media to the Database
	 * @return
	 */
	public function AddMedia()
	{
		$db 		=& $this->db;
		$layoutid 	= $this->layoutid;
		$regionid 	= $this->regionid;
		$mediaid	= $this->mediaid;
		$userid		= Kit::GetParam('userid', _SESSION, _INT);

		// File data
		$tmpName	= Kit::GetParam('hidFileID', _POST, _STRING);

		if ($tmpName == '')
		{
			$this->response->SetError('Cannot save Video details. <br/> You must have picked a file.');
			$this->response->keepOpen = true;
			return $this->response;
		}

		// File name and extension (orignial name)
		$fileName		= Kit::GetParam('txtFileName', _POST, _STRING);
		$fileName 		= basename($fileName);
		$ext 			= strtolower(substr(strrchr($fileName, "."), 1));

		// Other properties
		$name	  		= Kit::GetParam('name', _POST, _STRING);
		$duration	  	= Kit::GetParam('duration', _POST, _INT, 0);
		$permissionid 	= Kit::GetParam('permissionid', _POST, _INT, 1);

		if ($name == '') $name = Kit::ValidateParam($fileName, _FILENAME);

		// Validation
		if ($ext != "wmv" && $ext != "mpeg" && $ext != "mpg")
		{
			$this->response->SetError('Only Vidoes are accepted - wmv, mpeg, mpg [this is ' . $ext . ']');
			$this->response->keepOpen = true;
			return $this->response;
		}

		// Make sure the name isnt too long
		if (strlen($name) > 100)
		{
			$this->response->SetError('The name cannot be longer than 100 characters');
			$this->response->keepOpen = true;
			return $this->response;
		}

		// Ensure the name is not already in the database
		$SQL = sprintf("SELECT name FROM media WHERE name = '%s' AND userid = %d", $db->escape_string($name), $userid);

		if(!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			$this->response->SetError('Error checking whether the media name is ok. Try choosing a different name.');
			$this->response->keepOpen = true;
			return $this->response;
		}

		if ($db->num_rows($result) != 0)
		{
			$this->response->SetError('Some media you own already has this name. Please choose another.');
			$this->response->keepOpen = true;
			return $this->response;
		}

		// All OK to insert this record
		$SQL  = "INSERT INTO media (name, type, duration, originalFilename, permissionID, userID, retired ) ";
		$SQL .= "VALUES ('%s', 'video', '%s', '%s', %d, %d, 0) ";

		$SQL = sprintf($SQL, $db->escape_string($name), $db->escape_string($duration), $db->escape_string($fileName), $permissionid, $userid);

		if (!$mediaid = $db->insert_query($SQL))
		{
			trigger_error($db->error());
			$this->response->SetError('Database error adding this media record.');
			$this->response->keepOpen = true;
			return $this->response;
		}

		// File upload directory.. get this from the settings object
		$databaseDir = Config::GetSetting($db, "LIBRARY_LOCATION");

		// What are we going to store this media as...
		$storedAs = $mediaid.".".$ext;

		// Now we need to move the file
		if (!$result = rename($databaseDir."temp/".$tmpName, $databaseDir.$storedAs))
		{
			// If we couldnt move it - we need to delete the media record we just added
			$SQL = sprintf("DELETE FROM media WHERE mediaID = %d ", $mediaid);

			if (!$db->query($SQL))
			{
				trigger_error($db->error());
				$this->response->SetError('Error storing file.');
				$this->response->keepOpen = true;
				return $this->response;
			}
		}

		// Calculate the MD5 and the file size
		$md5 		= md5_file($databaseDir.$storedAs);
		$fileSize 	= filesize($databaseDir.$storedAs);

		// Update the media record to include this information
		$SQL = sprintf("UPDATE media SET storedAs = '%s', `MD5` = '%s', FileSize = %d WHERE mediaid = %d", $storedAs, $md5, $fileSize, $mediaid);

		if (!$db->query($SQL))
		{
			trigger_error($db->error());
			return true;
		}

		// Required Attributes
		$this->mediaid	= $mediaid;
		$this->duration = $duration;

		// Any Options
		$this->SetOption('uri', $storedAs);

		// Should have built the media object entirely by this time
		if ($regionid != '')
		{
			// This saves the Media Object to the Region
			$this->UpdateRegion();
			$this->response->loadFormUri = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";;
		}
		else
		{
			$this->response->loadFormUri = "index.php?p=content&q=displayForms&sp=add";
		}

		// We want to load a new form
		$this->response->loadForm	= true;

		return $this->response;
	}

	/**
	 * Edit Media in the Database
	 * @return
	 */
	public function EditMedia()
	{
		$db 		=& $this->db;
		$layoutid 	= $this->layoutid;
		$regionid 	= $this->regionid;
		$mediaid	= $this->mediaid;
		$userid		= Kit::GetParam('userid', _SESSION, _INT);

		// Stored As from the XML
		$storedAs	= $this->GetOption('uri');

		// File data
		$tmpName	= Kit::GetParam('hidFileID', _POST, _STRING);

		if ($tmpName == '')
		{
			$fileRevision = false;
		}
		else
		{
			$fileRevision = true;

			// File name and extension (orignial name)
			$fileName		= Kit::GetParam('txtFileName', _POST, _STRING);
			$fileName 		= basename($fileName);
			$ext 			= strtolower(substr(strrchr($fileName, "."), 1));

			// Validation
			if ($ext != "wmv" && $ext != "mpeg" && $ext != "mpg")
			{
				$this->response->SetError('Only Vidoes are accepted - wmv, mpeg, mpg [this is ' . $ext . ']');
				$this->response->keepOpen = true;
				return $this->response;
			}
		}

		// Other properties
		$name	  		= Kit::GetParam('name', _POST, _STRING);
		$duration	  	= Kit::GetParam('duration', _POST, _INT, 0);
		$permissionid 	= Kit::GetParam('permissionid', _POST, _INT, 1);

		if ($name == '')
		{
			if ($fileRevision)
			{
				$name = Kit::ValidateParam($fileName, _FILENAME);
			}
			else
			{
				$this->response->SetError('The Name cannot be blank.');
				$this->response->keepOpen = true;
				return $this->response;
			}
		}

		// Make sure the name isnt too long
		if (strlen($name) > 100)
		{
			$this->response->SetError('The name cannot be longer than 100 characters');
			$this->response->keepOpen = true;
			return $this->response;
		}

		// Ensure the name is not already in the database
		$SQL = sprintf("SELECT name FROM media WHERE name = '%s' AND userid = %d AND mediaid <> %d ", $db->escape_string($name), $userid, $mediaid);

		if(!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			$this->response->SetError('Error checking whether the media name is ok. Try choosing a different name.');
			$this->response->keepOpen = true;
			return $this->response;
		}

		if ($db->num_rows($result) != 0)
		{
			$this->response->SetError('Some media you own already has this name. Please choose another.');
			$this->response->keepOpen = true;
			return $this->response;
		}

		//Are we revising this media - or just plain editing
		if ($fileRevision)
		{
			// All OK to insert this record
			$SQL  = "INSERT INTO media (name, type, duration, originalFilename, permissionID, userID, retired ) ";
			$SQL .= "VALUES ('%s', '%s', '%s', '%s', %d, %d, 0) ";

			$SQL = sprintf($SQL, $db->escape_string($name), $this->type, $db->escape_string($duration), $db->escape_string($fileName), $permissionid, $userid);

			if (!$new_mediaid = $db->insert_query($SQL))
			{
				trigger_error($db->error());
				trigger_error('Error inserting replacement media record.', E_USER_ERROR);
			}

			//What are we going to store this media as...
			$storedAs = $new_mediaid.".".$ext;

			// File upload directory.. get this from the settings object
			$databaseDir = Config::GetSetting($db, "LIBRARY_LOCATION");

			//Now we need to move the file
			if (!$result = rename($databaseDir."/temp/".$tmpName, $databaseDir.$storedAs))
			{
				//If we couldnt move it - we need to delete the media record we just added
				$SQL = "DELETE FROM media WHERE mediaID = $new_mediaid ";

				if (!$db->insert_query($SQL))
				{
					$this->response->SetError('Error rolling back transcation.');
					$this->response->keepOpen = true;
					return $this->response;
				}
			}

			// Update the media record to include this information
			$SQL = "UPDATE media SET storedAs = '$storedAs' WHERE mediaid = $new_mediaid";
			if (!$db->query($SQL))
			{
				trigger_error($db->error());
				$this->response->SetError('Error updating media with Library location.');
				$this->response->keepOpen = true;
				return $this->response;
			}

			// Calculate the MD5 and the file size
			$md5 		= md5_file($databaseDir.$storedAs);
			$fileSize 	= filesize($databaseDir.$storedAs);

			// Update the media record to include this information
			$SQL = sprintf("UPDATE media SET storedAs = '%s', `MD5` = '%s', FileSize = %d WHERE mediaid = %d", $storedAs, $md5, $fileSize, $new_mediaid);

			if (!$db->query($SQL))
			{
				trigger_error($db->error());

				$this->response->SetError('Database error editing this media record.');
				$this->response->keepOpen = true;
				return $this->response;
			}

			// Update the existing record with the new record's id
			$SQL =  "UPDATE media SET isEdited = 1, editedMediaID = $new_mediaid ";
			$SQL .= " WHERE IFNULL(editedMediaID,0) <> $new_mediaid AND mediaID = $mediaid ";

			Debug::LogEntry($db, 'audit', $SQL);

			if (!$db->query($SQL))
			{
				trigger_error($db->error());

				$this->response->SetError('Database error editing this media record.');
				$this->response->keepOpen = true;
				return $this->response;
			}
		}
		else
		{
			// Editing the existing record
			$new_mediaid = $mediaid;

			$SQL =  "UPDATE media SET name = '%s', duration = %d, permissionID = %d";
			$SQL .= " WHERE mediaID = %d ";
			$SQL = sprintf($SQL, $db->escape_string($name), $duration, $permissionid, $mediaid);

			Debug::LogEntry($db, 'audit', $SQL);

			if (!$db->query($SQL))
			{
				trigger_error($db->error());

				$this->response->SetError('Database error editing this media record.');
				$this->response->keepOpen = true;
				return $this->response;
			}
		}

		// Required Attributes
		$this->mediaid	= $new_mediaid;
		$this->duration = $duration;

		// Any Options
		$this->SetOption('uri', $storedAs);

		// Should have built the media object entirely by this time
		if ($regionid != '')
		{
			// This saves the Media Object to the Region
			$this->UpdateRegion();

			$this->response->loadForm	 = true;
			$this->response->loadFormUri = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";;
		}
		else
		{
			$this->response->loadFormUri = "index.php?p=content&q=displayForms&sp=add";
			$this->response->message = 'Edited the Video.';

		}

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
		$mediaid	= $this->mediaid;
		$userid		= Kit::GetParam('userid', _SESSION, _INT);
		$options  	= Kit::GetParam('options', _POST, _WORD);

		// Stored As from the XML
		$this->uri	= $this->GetOption('uri');

		// Do we need to remove this from a layout?
		if ($layoutid != '')
		{
			// Call base method - which will set up the response
			parent::DeleteMedia();
		}
		else
		{
			// Set this message now in preparation
			$this->response->message = 'Deleted the Media.';
		}

		// If we are set to retire we retire
		if ($options == "retire")
		{
			//Update the media record to say it is retired
			$SQL = "UPDATE media SET retired = 1 WHERE mediaid = $mediaid ";

			if (!$db->query($SQL))
			{
				trigger_error($db->error());

				$this->response->SetError('Database error retiring this media record.');
				$this->response->keepOpen = true;
				return $this->response;
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

				$this->response->SetError('Database error deleting this media record.');
				$this->response->keepOpen = true;
				return $this->response;
			}

			$this->DeleteMediaFiles();
		}

		return $this->response;
	}

	/**
	 * Deletes the media files associated with this record
	 * @return
	 */
	private function DeleteMediaFiles()
	{
		$db =& $this->db;

		//Library location
		$databaseDir = Config::GetSetting($db, "LIBRARY_LOCATION");

		//3 things to check for..
		//the actual file, the thumbnail, the background
		if (file_exists($databaseDir.$this->uri))
		{
			unlink($databaseDir.$this->uri);
		}

		if (file_exists($databaseDir."tn_".$this->uri))
		{
			unlink($databaseDir."tn_".$this->uri);
		}

		if (file_exists($databaseDir."bg_".$this->uri))
		{
			unlink($databaseDir."bg_".$this->uri);
		}

		return true;
	}
}

?>