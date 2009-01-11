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
 
class contentDAO 
{
	private $db;
	private $user;
	private $isadmin = false;
	private $has_permissions = true;
	private $sub_page = "";
	
	//Table Fields
	private $mediaid;
	private $name = ""; 
	private $filepath = ""; 
	private $type = "";
	private $length = "";
	private $width = "";
	private $height = "";
	private $permissionid;
	private $media_class = "";
	private $retired;
	
	//are we redirecting to another page once we are done?
	private $redirect = false;
	private $redirect_addr = "";

	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
		
		$usertype = Kit::GetParam('usertype', _SESSION, _INT, 0);
		
		if ($usertype == 1) $this->isadmin = true;
		
		$this->sub_page = Kit::GetParam('sp', _GET, _WORD, 'view');
		
		return;
	}
	
	function on_page_load() 
	{
		return "";
	}
	
	function echo_page_heading() 
	{
		switch ($this->sub_page) 
		{
				
			case 'view':
				echo "Library";
				break;
				
			case 'scanner':
				echo "Scanner";
				break;
				
			case 'scanner_results';
				echo "Scanner Results";
				break;
				
			default:
				echo "Content View";
				break;
		}
		return true;
	}
		
	function displayFilter() 
	{
		$db =& $this->db;
		
		$mediatype = ""; //1
		$name = ""; //2
		$usertype = 0; //3
		$playlistid = ""; //4
		
		if (isset($_SESSION['content']['mediatype'])) $mediatype = $_SESSION['content']['mediatype'];
		if (isset($_SESSION['content']['name'])) $name = $_SESSION['content']['name'];
		if (isset($_SESSION['content']['usertype'])) $usertype = $_SESSION['content']['usertype'];
		if (isset($_SESSION['content']['playlistid'])) $playlistid = $_SESSION['content']['playlistid'];
		
		//shared list
		$shared = "All";
		if (isset($_SESSION['content']['shared'])) $shared = $_SESSION['content']['shared'];
		$shared_list = dropdownlist("SELECT 'all','All' UNION SELECT permissionID, permission FROM permission", "shared", $shared);
		
		$filter_userid = "";
		if (isset($_SESSION['content']['filter_userid'])) $filter_userid = $_SESSION['content']['filter_userid'];
		
		$user_list = listcontent("all|All,".userlist("SELECT DISTINCT userid FROM layout"),"filter_userid", $filter_userid);
		
		//retired list
		$retired = "0";
		if(isset($_SESSION['playlist']['filter_retired'])) $retired = $_SESSION['playlist']['retired'];
		$retired_list = listcontent("all|All,1|Yes,0|No","filter_retired",$retired);

		//type list query to get all playlists that are in the database which have NOT been assigned to the display
		$sql = "SELECT 'all', 'all' ";
		$sql .= "UNION ";
		$sql .= "SELECT type, type ";
		$sql .= "FROM media ";
		$sql .= "GROUP BY type ";
		
		$type_list =  dropdownlist($sql,"mediatype",$mediatype);

		$output = <<<END
				<form id="filter_form">
					<input type="hidden" name="p" value="content">
					<input type="hidden" name="q" value="data_table">
					<input type="hidden" name="pages" id="pages">
				<table id="content_filterform" class="filterform">
					<tr>
						<td>Name</td>
						<td><input type='text' name='2' id='2' value="$name"></td>
						<td>Shared</td>
						<td>$shared_list</td>
					</tr>
					<tr>
						<td>Type</td>
						<td>$type_list</td>
					</tr>
					<tr>
						<td>Owner</td>
						<td>$user_list</td>
						<td>Retired</td>
						<td>$retired_list</td>
					</tr>
				</table>
			</form>
END;
		echo $output;
	}
	
	/**
	 * Prints out a Table of all media items
	 *
	 */
	function data_table() 
	{
		$db =& $this->db;
		
		global $user;
		
		//Get the input params and store them
		$mediatype 		= Kit::GetParam('mediatype', _REQUEST, _WORD, 'all');
		$name 			= Kit::GetParam('2', _REQUEST, _STRING);
		$shared 		= Kit::GetParam('shared', _REQUEST, _STRING);
		$filter_userid 	= Kit::GetParam('filter_userid', _REQUEST, _STRING, 'all');
		$filter_retired = Kit::GetParam('filter_retired', _REQUEST, _STRING, 'all');
		
		setSession('content', 'mediatype', $mediatype);
		setSession('content', 'name', $name);
		setSession('content', 'shared', $shared);
		setSession('content', 'filter_userid', $filter_userid);
		setSession('content', 'filter_retired', $filter_userid);
		
		//construct the SQL
		$SQL  = "";
		$SQL .= "SELECT  media.mediaID, ";
		$SQL .= "        media.name, ";
		$SQL .= "        media.type, ";
		$SQL .= "        media.duration, ";
		$SQL .= "        media.userID, ";
		$SQL .= "        permission.permission, ";
		$SQL .= "        media.permissionID ";
		$SQL .= "FROM    media ";
		$SQL .= "INNER JOIN permission ON permission.permissionID = media.permissionID ";
		$SQL .= "WHERE   1            = 1  AND isEdited = 0 ";
		if ($mediatype != "all") 
		{
			$SQL .= sprintf(" AND media.type = '%s'", $db->escape_string($mediatype));
		}
		if ($name != "") 
		{
			$SQL .= " AND media.name LIKE '%$name%' ";
		}
		if ($filter_userid != 'all') 
		{
			$SQL .= sprintf(" AND media.userid = %d ", $filter_userid);
		}
		if ($shared != "all") 
		{
			$SQL .= sprintf(" AND media.permissionID = %d ", $shared);			
		}
		//retired options
		if ($filter_retired == '1') 
		{
			$SQL .= " AND media.retired = 1 ";
		}
		elseif ($filter_retired == '0') 
		{
			$SQL .= " AND media.retired = 0 ";			
		}
		
		Debug::LogEntry($db, 'audit', $SQL);

		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Cant get content list", E_USER_ERROR);			
		}

    	$output = <<<END
			<div class="info_table">
		    <table style="width:100%">
			<thead>
			    <tr>
			        <th>Name</th>
			        <th>Type</th>
			        <th>h:mi:ss</th>            
			        <th>Shared</th>       
					<th>Owner</th>
			        <th>Action</th>     
			    </tr>
			</thead>
			<tbody>
END;
		echo $output;
		
	    while ($aRow = $db->get_row($results)) 
		{
	    	$mediaid 		= Kit::ValidateParam($aRow[0], _INT);
			$media 			= Kit::ValidateParam($aRow[1], _STRING);
			$mediatype 		= Kit::ValidateParam($aRow[2], _WORD);
			$length 		= sec2hms(Kit::ValidateParam($aRow[3], _DOUBLE));
			$ownerid 		= Kit::ValidateParam($aRow[4], _INT);
			
			$permission 	= Kit::ValidateParam($aRow[5], _STRING);
			$permissionid 	= Kit::ValidateParam($aRow[6], _INT);
			
			//get the username from the userID using the user module
			$username 		= $user->getNameFromID($ownerid);
			$group			= $user->getGroupFromID($ownerid);
			
			//get the permissions
			list($see_permissions , $edit_permissions) = $user->eval_permission($ownerid, $permissionid);
			
			if ($see_permissions) //is this user allowed to see this
			{ 
				if ($edit_permissions) 
				{
					//double click action - depends on what type of media we are
					$title = <<<END
					<tr href='index.php?p=module&mod=$mediatype&q=EditForm&mediaid=$mediaid' ondblclick="init_button(this,'Edit Content', exec_filter_callback, set_form_size(450,320))">
END;
				}
				else 
				{
					$title = <<<END
					<tr ondblclick="alert('You do not have permission to edit this media')">
END;
				}
				
				echo $title;
				echo "<td>$media</td>\n";
		    	echo "<td>$mediatype</td>\n";
		    	echo "<td>$length</td>\n";
		    	echo "<td>$permission</td>\n";
				echo "<td>$username</td>";
				
				//ACTION buttons
		    	if ($edit_permissions) 
				{
					
			   		$buttons = "<a class='positive' href='index.php?p=module&mod=$mediatype&q=EditForm&mediaid=$mediaid' onclick=\"return init_button(this,'Edit Content',exec_filter_callback,set_form_size(450,320))\"><span>Edit</span></a>";					
	    			$buttons .= "<a class='negative' href='index.php?p=module&mod=$mediatype&q=DeleteForm&mediaid=$mediaid' onclick=\"return init_button(this,'Delete Content',exec_filter_callback,media_form_call(350,160))\"><span>Delete</span></a>";
		    	}
		    	else 
				{
		    		$buttons = "No available actions.";
		    	}
				
				$output = <<<END
				<td>
					<div class='buttons'>
						$buttons
					</div>
				</td>
END;
				echo $output;		
				
		    	echo "</tr>\n";
			}
	    }
		
    	echo "</tbody></table>\n</div>\n";

    	exit;
	}
	
	/**
	 * Display the forms
	 * @return 
	 */
	function displayForms() 
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		
		//displays all the content add forms - tabbed.
		$response = new ResponseManager();
		
		// Get a list of the enabled modules and then create buttons for them
		if (!$enabledModules = new ModuleManager($db, $user, 0)) trigger_error($enabledModules->message, E_USER_ERROR);
		
		$buttons = '';
		
		// Loop through the buttons we have and output store HTML for each one in $buttons.
		while ($modulesItem = $enabledModules->GetNextModule())
		{
			$mod 		= Kit::ValidateParam($modulesItem['Module'], _STRING);
			$caption 	= 'Add ' . $mod;
			$mod		= strtolower($mod);
			$title 		= Kit::ValidateParam($modulesItem['Description'], _STRING);
			$img 		= Kit::ValidateParam($modulesItem['ImageUri'], _STRING);
			
			$uri		= 'index.php?p=module&q=Exec&mod=' . $mod . '&method=AddForm';
			
			$buttons .= <<<HTML
			<div class="regionicons">
				<a class="XiboFormButton" title="$title" href="$uri">
				<img class="dash_button" src="$img" />
				<span class="dash_text">$caption</span></a>
			</div>
HTML;
		}
		
		$options = <<<END
		<div id="canvas">
			<div id="buttons">
				$buttons
			</div>
		</div>
END;
		
		$response->html 		= $options;
		$response->dialogTitle 	= 'Add Media to the Library.';
		$response->dialogSize 	= true;
		$response->dialogWidth 	= '650px';
		$response->dialogHeight = '280px';

		$response->Respond();
	}

	/**
	 * Displays the page logic
	 *
	 * @return unknown
	 */
	function displayPage() 
	{
		$db =& $this->db;
		
		if (!$this->has_permissions) 
		{
			displayMessage(MSG_MODE_MANUAL, "You do not have permissions to access this page");
			return false;
		}
		
		switch ($this->sub_page) 
		{	
			case 'view':
				require("template/pages/content_view.php");
				break;
					
			default:
				break;
		}
		
		return false;
	}
	
	/**
	 * Displays the Library Assign form
	 * @return 
	 */
	function LibraryAssignForm() 
	{
		$db 			=& $this->db;
		$user			=& $this->user;
		$response		= new ResponseManager();
		$formMgr 		= new FormManager($db, $user);
		
		if (isset($_SESSION['content']['mediatype'])) $mediatype = $_SESSION['content']['mediatype'];
		if (isset($_SESSION['content']['name'])) $name = $_SESSION['content']['name'];
		
		//Media Type drop down list
		$sql = "SELECT 'all', 'all' ";
		$sql .= "UNION ";
		$sql .= "SELECT type, type ";
		$sql .= "FROM media WHERE 1=1 ";
		$sql .= "  GROUP BY type ";
		
		$type_list 	= $formMgr->DropDown($sql, 'type', $mediatype);
		
		//Input vars
		$layoutid = Kit::GetParam('layoutid', _REQUEST, _INT);
		$regionid = Kit::GetParam('regionid', _REQUEST, _STRING);
		
		$form = <<<HTML
		<form>
			<input type="hidden" name="p" value="content">
			<input type="hidden" name="q" value="LibraryAssignView">
			<input type="hidden" name="layoutid" value="$layoutid" />
			<input type="hidden" name="regionid" value="$regionid" />
			<table>
				<tr>
					<td>Name</td>
					<td><input type="text" name="name" id="name" value="$name"></td>
					<td>Media Type</td>
					<td>$type_list</td>
				</tr>
			</table>
		</form>
HTML;
		
		$id = uniqid();
		
		$xiboGrid = <<<HTML
		<div class="XiboGrid" id="$id">
			<div class="XiboFilter">
				$form
			</div>
			<div class="XiboData">
			
			</div>
		</div>
HTML;
		
		// Construct the Response
		$response->html			= $xiboGrid;
		$response->success		= true;
		$response->dialogSize	= true;
		$response->dialogWidth	= '500px';
		$response->dialogHeight = '380px';
		$response->dialogTitle	= 'Assign an item from the Library';
		
		$response->Respond();	
	}
	
	/**
	 * Show the library
	 * @return 
	 */
	function LibraryAssignView() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$userid 	= Kit::GetParam('userid', _SESSION, _INT);
		$response	= new ResponseManager();
		
		//Input vars
		$layoutid 	= Kit::GetParam('layoutid', _REQUEST, _INT);
		$regionid 	= Kit::GetParam('regionid', _REQUEST, _STRING);
		$mediatype 	= Kit::GetParam('type', _POST, _STRING, 'all');
		$name 		= Kit::GetParam('name', _POST, _STRING, 'all');
		
		setSession('content', 'mediatype', $mediatype);
		setSession('content', 'name', $name);
		
		// query to get all media that is in the database ready to display
		$SQL  = "";
		$SQL .= "SELECT  media.mediaID, ";
		$SQL .= "        media.name, ";
		$SQL .= "        media.type, ";
		$SQL .= "        media.duration, ";
		$SQL .= "        media.userID, ";
		$SQL .= "        permission.permission, ";
		$SQL .= "        media.permissionID ";
		$SQL .= "FROM    media ";
		$SQL .= "INNER JOIN permission ON permission.permissionID = media.permissionID ";
		$SQL .= "WHERE   retired = 0 AND isEdited = 0 ";
		if($mediatype != "all") 
		{
			$SQL.= sprintf(" AND media.type = '%s'", $mediatype); //id of the remaining items
		}
		if ($name != "all") 
		{
			$SQL.= " AND media.name LIKE '%" . sprintf('%s', $name) . "%'";
		}
		
		if(!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Cant get content list", E_USER_ERROR);			
		}
		
		//some table headings
		$form = <<<END
		<form class="XiboForm" method="post" action="index.php?p=layout&q=AddFromLibrary">
			<input type="hidden" name="layoutid" value="$layoutid" />
			<input type="hidden" name="regionid" value="$regionid" />
			<div class="dialog_table" style="overflow-y: scroll; height: 300px;">
			<table style="width:100%">
				<thead>
			    <tr>
			        <th>Name</th>
		            <th>Type</th>
		            <th>Duration</th>
		            <th>Shared</th>
			        <th>Select</th>
			    </tr>
				</thead>
				<tbody>
END;

		// while loop
		while ($row = $db->get_row($results)) 
		{			
			$mediaid 		= Kit::ValidateParam($row[0], _INT);
			$media 			= Kit::ValidateParam($row[1], _STRING);
			$mediatype 		= Kit::ValidateParam($row[2], _WORD);
			$length 		= sec2hms(Kit::ValidateParam($row[3], _DOUBLE));
			$ownerid 		= Kit::ValidateParam($row[4], _INT);
			
			$permission 	= Kit::ValidateParam($row[5], _STRING);
			$permissionid 	= Kit::ValidateParam($row[6], _INT);
			
			//get the username from the userID using the user module
			$username 		= $user->getNameFromID($ownerid);
			$group			= $user->getGroupFromID($ownerid);
	
			//get the permissions
			list($see_permissions , $edit_permissions) = $user->eval_permission($ownerid, $permissionid);
			
			if ($see_permissions) 
			{ //is this user allowed to see this

				$form .= "<tr>";
				$form .= "<td>" . $media . "</td>\n";
				$form .= "<td>" . $mediatype . "</td>\n";
				$form .= "<td>" . $length . "</td>\n";
				$form .= "<td>" . $permission . "</td>\n";
				$form .= "<td><input type='checkbox' name='mediaids[]' value='$mediaid'></td>";
				$form .= "</tr>";
			}
		}

		//table ending
		$form .= <<<END
				</tbody>
			</table>
			<input type='submit' value="Assign" / >
		</div>
	</form>
END;
		
		// Construct the Response
		$response->html 		= $form;
		$response->success		= true;
		$response->sortable		= false;
		$response->sortingDiv	= '.info_table table';
		
		$response->Respond();
	}
	
	/**
	 * Gets called by the SWFUpload Object for uploading files
	 * @return 
	 */
	function FileUpload()
	{
		$db =& $this->db;
		
		/*
		 * Normal file post:
		 * 
		 * need to get the fileId
		 * and the file name
		 * 
		 * and return them to the javascript (which will do a window.parent.setsomevalues)
		 * 
		 */
		
		//File upload directory.. get this from the settings object
		$libraryFolder 	= Config::GetSetting($db, "LIBRARY_LOCATION");
		$fileId 		= uniqid() . rand(100, 999);
		
		Debug::LogEntry($db, "audit", '[IN - FileId ' . $fileId . '] to library location: '. $libraryFolder, 'FileUpload');
		
		if (isset($_FILES["media_file"]) && is_uploaded_file($_FILES["media_file"]["tmp_name"]) && $_FILES["media_file"]["error"] == 0) 
		{
			$error 			= 0;
			$fileName 		= $_FILES["media_file"]["name"];
			$fileLocation 	= $libraryFolder."temp/".$fileId;
			
			// Save the FILE
			move_uploaded_file($_FILES["media_file"]["tmp_name"], $fileLocation);
			
			Debug::LogEntry($db, "audit", "Upload Success", "FileUpload");
		} 
		else 
		{
			Debug::LogEntry($db, "audit", "Error uploading the file num [{$_FILES["media_file"]["error"]}]", "FileUpload");
			
			$error = $_FILES["media_file"]["error"];
			$fileName = "Error";
			$fileId = 0;
		}
		
		$complete_page = <<<HTML
		<html>
			<head>
				<script type="text/javascript" src="3rdparty/jQuery/jquery.min.js"></script>
				<script type="text/javascript">
					
					var fileId = '$fileId';
					var fileName = '$fileName';
					var errorNo = $error;
					
					function report()
					{
						var form = window.parent.fileUploadReport(fileName, fileId, errorNo);
						
					}
					
					window.onload = report;
					
				</script>
			</head>
			<body></body>
		</html>
HTML;
		
		echo $complete_page;
		
		Debug::LogEntry($db, "audit", "[OUT]", "FileUpload");
		exit;
	}
}
?>