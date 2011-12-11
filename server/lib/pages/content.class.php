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
		return '';
	}
	
	function echo_page_heading() 
	{
		echo __("Library");
		
		return true;
	}
	
	/**
	 * Library Filter form
	 * @return 
	 */	
	function LibraryFilter() 
	{
		$db =& $this->db;
		
		$mediatype = ""; //1
		$usertype = 0; //3
		$playlistid = ""; //4
		
		if (isset($_SESSION['content']['mediatype'])) $mediatype = $_SESSION['content']['mediatype'];
		if (isset($_SESSION['content']['usertype'])) $usertype = $_SESSION['content']['usertype'];
		if (isset($_SESSION['content']['playlistid'])) $playlistid = $_SESSION['content']['playlistid'];
		
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
		
		// Messages
		$msgName	= __('Name');
		$msgType	= __('Type');
		$msgRetired	= __('Retired');
		$msgOwner	= __('Owner');
		$msgShared	= __('Shared');

		$filterForm = <<<END
			<div class="FilterDiv" id="LibraryFilter">
				<form>
					<input type="hidden" name="p" value="content">
					<input type="hidden" name="q" value="LibraryGrid">
					<input type="hidden" name="pages" id="pages">
					
					<table id="content_filterform" class="filterform">
						<tr>
							<td>$msgName</td>
							<td><input type='text' name='2' id='2' /></td>
							<td>$msgType</td>
							<td>$type_list</td>
							<td>$msgRetired</td>
							<td>$retired_list</td>
						</tr>
						<tr>
							<td>$msgOwner</td>
							<td>$user_list</td>
							<td></td>
							<td></td>
						</tr>
					</table>
			</form>
		</div>
END;

            $id = uniqid();

            $xiboGrid = <<<HTML
            <div class="XiboGrid" id="$id">
                <div class="XiboFilter">
                        $filterForm
                </div>
                <div class="XiboData">

                </div>
            </div>
HTML;
            echo $xiboGrid;
	}
	
	/**
	 * Prints out a Table of all media items
	 *
	 */
	function LibraryGrid() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();

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
		
		// Construct the SQL
		$SQL  = "";
		$SQL .= "SELECT  media.mediaID, ";
		$SQL .= "        media.name, ";
		$SQL .= "        media.type, ";
		$SQL .= "        media.duration, ";
		$SQL .= "        media.userID, ";
		$SQL .= "        media.FileSize ";
		$SQL .= "FROM    media ";
		$SQL .= "WHERE   isEdited = 0 ";
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
		//retired options
		if ($filter_retired == '1') 
		{
			$SQL .= " AND media.retired = 1 ";
		}
		elseif ($filter_retired == '0') 
		{
			$SQL .= " AND media.retired = 0 ";			
		}
		$SQL .= " ORDER BY media.name ";

		Debug::LogEntry($db, 'audit', $SQL);

		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__('Cant get content list'), E_USER_ERROR);			
		}
		
		// Messages
		$msgName	= __('Name');
		$msgType	= __('Type');
		$msgRetired	= __('Retired');
		$msgOwner	= __('Owner');
		$msgFileSize	= __('Size');
		$msgShared	= __('Permissions');
		$msgAction	= __('Action');

    	$output = <<<END
			<div class="info_table">
		    <table style="width:100%">
			<thead>
			    <tr>
			        <th>$msgName</th>
			        <th>$msgType</th>
			        <th>h:mi:ss</th>            
			        <th>$msgFileSize</th>
                                <th>$msgOwner</th>
			        <th>$msgShared</th>       
			        <th>$msgAction</th>     
			    </tr>
			</thead>
			<tbody>
END;
		
        while ($aRow = $db->get_row($results))
        {
            $mediaid 		= Kit::ValidateParam($aRow[0], _INT);
            $media 			= Kit::ValidateParam($aRow[1], _STRING);
            $mediatype 		= Kit::ValidateParam($aRow[2], _WORD);
            $length 		= sec2hms(Kit::ValidateParam($aRow[3], _DOUBLE));
            $ownerid 		= Kit::ValidateParam($aRow[4], _INT);
            $fileSize = Kit::ValidateParam($aRow[5], _INT);

            // Size in MB
            $sz = 'BKMGTP';
            $factor = floor((strlen($fileSize) - 1) / 3);
            $fileSize = sprintf('%.2f', $fileSize / pow(1024, $factor)) . @$sz[$factor];

            //get the username from the userID using the user module
            $username = $user->getNameFromID($ownerid);

            $group = $this->GroupsForMedia($mediaid);

            // Permissions
            $auth = $this->user->MediaAuth($mediaid, true);

            if ($auth->view) //is this user allowed to see this
            {
                if ($auth->edit)
                {
                    //double click action - depends on what type of media we are
                    $output .= <<<END
                    <tr href='index.php?p=module&mod=$mediatype&q=Exec&method=EditForm&mediaid=$mediaid' ondblclick="XiboFormRender($(this).attr('href'))">
END;
                }
                else
                {
                    $output .= '<tr ondblclick="alert(' . __('You do not have permission to edit this media') . ')">';
                }

                $output .= "<td>$media</td>\n";
                $output .= "<td>$mediatype</td>\n";
                $output .= "<td>$length</td>\n";
                $output .= "<td>$fileSize</td>\n";
                $output .= "<td>$username</td>";
                $output .= "<td>$group</td>";

                // ACTION buttons
                if ($auth->edit)
                {
                    $msgEdit	= __('Edit');
                    $msgDelete	= __('Delete');

                    $buttons 	= "<button class='XiboFormButton' title='$msgEdit' href='index.php?p=module&mod=$mediatype&q=Exec&method=EditForm&mediaid=$mediaid'><span>$msgEdit</span></button>";
                    
                    if ($auth->del)
                        $buttons .= "<button class='XiboFormButton' title='$msgDelete' href='index.php?p=module&mod=$mediatype&q=Exec&method=DeleteForm&mediaid=$mediaid'><span>$msgDelete</span></button>";

                    if ($auth->modifyPermissions)
                        $buttons .= "<button class='XiboFormButton' title='$msgShared' href='index.php?p=module&mod=$mediatype&q=Exec&method=PermissionsForm&mediaid=$mediaid'><span>$msgShared</span></button>";
                }
                else
                {
                    $buttons = __("No available actions.");
                }

                $output .= <<<END
                <td>
                        <div class='buttons'>
                                $buttons
                        </div>
                </td>
END;

                $output .= "</tr>\n";
            }
        }
		
    	$output .= "</tbody></table>\n</div>\n";

    	$response->SetGridResponse($output);
        $response->Respond();
    }
	
	/**
	 * Display the forms
	 * @return 
	 */
	function displayForms() 
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
                $helpManager    = new HelpManager($db, $user);
		
		//displays all the content add forms - tabbed.
		$response = new ResponseManager();
		
		// Get a list of the enabled modules and then create buttons for them
		if (!$enabledModules = new ModuleManager($db, $user, 0)) trigger_error($enabledModules->message, E_USER_ERROR);
		
		$buttons = '';
		
		// Loop through the buttons we have and output store HTML for each one in $buttons.
		while ($modulesItem = $enabledModules->GetNextModule())
		{
			$mod 		= Kit::ValidateParam($modulesItem['Module'], _STRING);
			$caption 	= __('Add') . ' ' . Kit::ValidateParam($modulesItem['Name'], _STRING);
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
		$response->dialogTitle 	= __('Add Media to the Library');
		$response->dialogSize 	= true;
		$response->dialogWidth 	= '650px';
		$response->dialogHeight = '280px';
                $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Content', 'AddtoLibrary') . '")');
		$response->AddButton(__('Close'), 'XiboDialogClose()');

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
			trigger_error(__("You do not have permissions to access this page"), E_USER_ERROR);
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
                $helpManager            = new HelpManager($db, $user);

                $mediatype              = '';
                $name                   = '';
		
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
		
		// Messages
		$msgName	= __('Name');
		$msgType	= __('Type');
		
		$form = <<<HTML
		<form>
			<input type="hidden" name="p" value="content">
			<input type="hidden" name="q" value="LibraryAssignView">
			<input type="hidden" name="layoutid" value="$layoutid" />
			<input type="hidden" name="regionid" value="$regionid" />
			<table>
				<tr>
					<td>$msgName</td>
					<td><input type="text" name="name" id="name" value="$name"></td>
					<td>$msgType</td>
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
		$response->html         = $xiboGrid;
		$response->success	= true;
		$response->dialogSize	= true;
		$response->dialogWidth	= '500px';
		$response->dialogHeight = '380px';
		$response->dialogTitle	= __('Assign an item from the Library');

                $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Library', 'Assign') . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Assign'), '$("#LibraryAssignForm").submit()');
		
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
                $helpManager    = new HelpManager($db, $user);
		
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
		$SQL .= "        media.userID ";
		$SQL .= "FROM    media ";
		$SQL .= "WHERE   retired = 0 AND isEdited = 0 ";
		if($mediatype != "all") 
		{
			$SQL.= sprintf(" AND media.type = '%s'", $mediatype); //id of the remaining items
		}
		if ($name != "all") 
		{
			$SQL.= " AND media.name LIKE '%" . sprintf('%s', $name) . "%'";
		}
                $SQL .= " ORDER BY media.name ";
		
		if(!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__("Cant get content list"), E_USER_ERROR);			
		}
		
		// Messages
		$msgName	= __('Name');
		$msgType	= __('Type');
		$msgLen		= __('Duration');
		$msgOwner	= __('Owner');
		$msgSelect	= __('Select');
		
		//some table headings
		$form = <<<END
		<form id="LibraryAssignForm" class="XiboForm" method="post" action="index.php?p=layout&q=AddFromLibrary">
			<input type="hidden" name="layoutid" value="$layoutid" />
			<input type="hidden" name="regionid" value="$regionid" />
			<div class="dialog_table">
			<table style="width:100%">
				<thead>
			    <tr>
			        <th>$msgName</th>
		            <th>$msgType</th>
		            <th>$msgLen</th>
			        <th>$msgSelect</th>
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
			
			//get the username from the userID using the user module
			$username 		= $user->getNameFromID($ownerid);
			$group			= $user->getGroupFromID($ownerid);
	
			// Permissions
                        $auth = $this->user->MediaAuth($mediaid, true);

                        if ($auth->view) //is this user allowed to see this
                        {
                            $form .= "<tr>";
                            $form .= "<td>" . $media . "</td>\n";
                            $form .= "<td>" . $mediatype . "</td>\n";
                            $form .= "<td>" . $length . "</td>\n";
                            $form .= "<td><input type='checkbox' name='mediaids[]' value='$mediaid'></td>";
                            $form .= "</tr>";
			}
		}

		//table ending
		$form .= <<<END
				</tbody>
			</table>
                       <div style="display:none"><input type="submit" /></div>
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

        Debug::LogEntry($db, 'audit', 'Uploading a file', 'Library', 'FileUpload');

        Kit::ClassLoader('file');
        $fileObject = new File($db);

        
        // Check we got a valid file
        if (isset($_FILES['media_file']) && is_uploaded_file($_FILES['media_file']['tmp_name']) && $_FILES['media_file']['error'] == 0)
        {
            Debug::LogEntry($db, 'audit', 'Valid Upload', 'Library', 'FileUpload');

            // Directory location
            $libraryFolder  = Config::GetSetting($db, 'LIBRARY_LOCATION');
            $error          = 0;
            $fileName       = Kit::ValidateParam($_FILES['media_file']['name'], _FILENAME);
            $fileId         = $fileObject->GenerateFileId($this->user->userid);
            $fileLocation   = $libraryFolder . 'temp/' . $fileId;

            // Make sure the library exists
            $fileObject->EnsureLibraryExists();

            // Save the FILE
            Debug::LogEntry($db, 'audit', 'Saving the file to: ' . $fileLocation, 'FileUpload');

            move_uploaded_file($_FILES['media_file']['tmp_name'], $fileLocation);

            Debug::LogEntry($db, 'audit', 'Upload Success', 'FileUpload');
        }
        else
        {
            Debug::LogEntry($db, 'audit', 'Error uploading the file. Error Number: ' . $_FILES['media_file']['error'] , 'FileUpload');

            $error      = $_FILES['media_file']['error'];
            $fileName   = 'Error';
            $fileId     = 0;
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

        Debug::LogEntry($db, "audit", $complete_page, "FileUpload");
        Debug::LogEntry($db, "audit", "[OUT]", "FileUpload");
        exit;
    }

    /**
     * Get a list of group names for a layout
     * @param <type> $layoutId
     * @return <type>
     */
    private function GroupsForMedia($mediaId)
    {
        $db =& $this->db;

        $SQL = '';
        $SQL .= 'SELECT `group`.Group ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   INNER JOIN lkmediagroup ';
        $SQL .= '   ON `group`.GroupID = lkmediagroup.GroupID ';
        $SQL .= ' WHERE lkmediagroup.MediaID = %d ';

        $SQL = sprintf($SQL, $mediaId);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get group information for media'), E_USER_ERROR);
        }

        $groups = '';

        while ($row = $db->get_assoc_row($results))
        {
            $groups .= $row['Group'] . ', ';
        }

        $groups = trim($groups);
        $groups = trim($groups, ',');

        return $groups;
    }
}
?>