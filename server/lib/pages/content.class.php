<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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

	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
	}

	/**
	 * Displays the page logic
	 */
	function displayPage() 
	{
		$db =& $this->db;
		
		// Default options
        if (Kit::IsFilterPinned('content', 'Filter')) {
            Theme::Set('filter_pinned', 'checked');
            Theme::Set('filter_name', Session::Get('content', 'filter_name'));
            Theme::Set('filter_type', Session::Get('content', 'filter_type'));
            Theme::Set('filter_retired', Session::Get('content', 'filter_retired'));
            Theme::Set('filter_owner', Session::Get('content', 'filter_owner'));
        }
        else {
			Theme::Set('filter_retired', 0);
        }
		
    	Theme::Set('library_form_add_url', 'index.php?p=content&q=displayForms');

		$id = uniqid();
		Theme::Set('id', $id);
		Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
		Theme::Set('pager', ResponseManager::Pager($id));
		Theme::Set('form_meta', '<input type="hidden" name="p" value="content"><input type="hidden" name="q" value="LibraryGrid">');
		
		// Field list for a "retired" dropdown list
        Theme::Set('retired_field_list', array(array('retiredid' => 1, 'retired' => 'Yes'), array('retiredid' => 0, 'retired' => 'No')));
		
		// Field list for a "owner" dropdown list
		Theme::Set('owner_field_list', $db->GetArray("SELECT 0 AS UserID, 'All' AS UserName UNION SELECT DISTINCT user.UserID, user.UserName FROM `media` INNER JOIN `user` ON media.UserID = user.UserID "));

		// Module types filter
		$types = $db->GetArray("SELECT Module AS moduleid, Name AS module FROM `module` WHERE RegionSpecific = 0 AND Enabled = 1 ORDER BY 2");
        array_unshift($types, array('moduleid' => '', 'module' => 'All'));
        Theme::Set('module_field_list', $types);

		// Call to render the template
		Theme::Render('library_page');
	}
	
	/**
	 * Prints out a Table of all media items
	 */
	function LibraryGrid() 
	{
		$db =& $this->db;
		$user =& $this->user;
		$response = new ResponseManager();

		//Get the input params and store them
		$filter_type = Kit::GetParam('filter_type', _REQUEST, _WORD);
		$filter_name = Kit::GetParam('filter_name', _REQUEST, _STRING);
		$filter_userid = Kit::GetParam('filter_owner', _REQUEST, _INT);
		$filter_retired = Kit::GetParam('filter_retired', _REQUEST, _INT);
                
		setSession('content', 'filter_type', $filter_type);
		setSession('content', 'filter_name', $filter_name);
		setSession('content', 'filter_owner', $filter_userid);
		setSession('content', 'filter_retired', $filter_retired);
        setSession('content', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
		
		// Construct the SQL
		$mediaList = $user->MediaList($filter_type, $filter_name, $filter_userid, $filter_retired);

		$rows = array();

		// Add some additional row content
		foreach ($mediaList as $row) {

			$row['duration_text'] = sec2hms($row['duration']);
			$row['owner'] = $user->getNameFromID($row['ownerid']);
			$row['permissions'] = $group = $this->GroupsForMedia($row['mediaid']);
			$row['revised'] = ($row['parentid'] != 0) ? Theme::Image('act.gif') : '';

			// Display a friendly filesize
			$sz = 'BKMGTP';
            $factor = floor((strlen($row['filesize']) - 1) / 3);
            $fileSize = sprintf('%.2f', $row['filesize'] / pow(1024, $factor)) . @$sz[$factor];
			$row['size_text'] = $fileSize;

			$row['buttons'] = array();

			// Buttons
            if ($row['edit'] == 1) {
                
                // Edit
                $row['buttons'][] = array(
                        'id' => 'content_button_edit',
                        'url' => 'index.php?p=module&mod=' . $row['mediatype'] . '&q=Exec&method=EditForm&mediaid=' . $row['mediaid'],
                        'text' => __('Edit')
                    );
            }
            
            if ($row['del'] == 1) {
				
				// Delete
                $row['buttons'][] = array(
                        'id' => 'content_button_delete',
                        'url' => 'index.php?p=module&mod=' . $row['mediatype'] . '&q=Exec&method=DeleteForm&mediaid=' . $row['mediaid'],
                        'text' => __('Delete')
                    );
            }

            if ($row['modifyPermissions'] == 1) {

        		// Permissions
                $row['buttons'][] = array(
                        'id' => 'content_button_permissions',
                        'url' => 'index.php?p=module&mod=' . $row['mediatype'] . '&q=Exec&method=PermissionsForm&mediaid=' . $row['mediaid'],
                        'text' => __('Permissions')
                    );
            }

            // Add to the collection
			$rows[] = $row;
		}
		
    	Theme::Set('table_rows', $rows);
        
        $output = Theme::RenderReturn('library_page_grid');

    	$response->SetGridResponse($output);
        $response->Respond();
    }
	
	/**
	 * Display the forms
	 */
	function displayForms() 
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		
		//displays all the content add forms - tabbed.
		$response = new ResponseManager();
		
		// Get a list of the enabled modules and then create buttons for them
		if (!$enabledModules = new ModuleManager($db, $user, 0)) 
            trigger_error($enabledModules->message, E_USER_ERROR);
		
		$buttons = array();
		
		// Loop through the buttons we have and output store HTML for each one in $buttons.
		while ($modulesItem = $enabledModules->GetNextModule())
		{
			$button['mod'] = Kit::ValidateParam($modulesItem['Module'], _STRING);
			$button['caption'] = __('Add') . ' ' . Kit::ValidateParam($modulesItem['Name'], _STRING);
			$button['mod'] = strtolower($button['mod']);
			$button['title'] = Kit::ValidateParam($modulesItem['Description'], _STRING);
			$button['img'] = Theme::Image(Kit::ValidateParam($modulesItem['ImageUri'], _STRING));
			$button['uri'] = 'index.php?p=module&q=Exec&mod=' . $button['mod'] . '&method=AddForm';
			
			$buttons[] = $button;
		}

		Theme::Set('buttons', $buttons);
		
		$response->html = Theme::RenderReturn('library_form_add');
		$response->dialogTitle 	= __('Add Media to the Library');
		$response->dialogSize 	= true;
		$response->dialogWidth 	= '650px';
		$response->dialogHeight = '280px';
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Content', 'AddtoLibrary') . '")');
		$response->AddButton(__('Close'), 'XiboDialogClose()');
		$response->Respond();
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

        //Media Type drop down list
        $sql = "SELECT 'all', 'all' ";
        $sql .= "UNION ";
        $sql .= "SELECT type, type ";
        $sql .= "FROM media WHERE 1=1 ";
        $sql .= "  GROUP BY type ";

        $type_list 	= $formMgr->DropDown($sql, 'type', $mediatype);

        //Input vars
        $layoutId = Kit::GetParam('layoutid', _REQUEST, _INT);
        $regionId = Kit::GetParam('regionid', _REQUEST, _STRING);

        // Messages
        $msgName	= __('Name');
        $msgType	= __('Type');

        $form = <<<HTML
        <form>
            <input type="hidden" name="p" value="content">
            <input type="hidden" name="q" value="LibraryAssignView">
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

        $msgAssignBox = __('Media to Assign');
        $msgInfoMessage = __('Drag or double click to move items between lists');

        $xiboGrid = <<<HTML
        <div class="XiboGrid LibraryAssign" id="$id">
            <div class="XiboFilter">
                $form
            </div>
            <center>$msgInfoMessage</center>
            <div class="XiboData LibraryAssignLeftSortableList connectedlist">

            </div>
            <div class="LibraryAssignRightSortableList connectedlist">
                <h3>$msgAssignBox</h3>
                <ul id="LibraryAssignSortable" class="connectedSortable">

                </ul>
            </div>
        </div>
HTML;
		
        // Construct the Response
        $response->html         = $xiboGrid;
        $response->success	= true;
        $response->dialogSize	= true;
        $response->dialogWidth	= '780px';
        $response->dialogHeight = '580px';
        $response->dialogTitle	= __('Assign an item from the Library');

        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Library', 'Assign') . '")');
        $response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=layout&layoutid=' . $layoutId . '&regionid=' . $regionId . '&q=RegionOptions")');
        $response->AddButton(__('Assign'), 'LibraryAssignSubmit("' . $layoutId . '","' . $regionId . '")');

        $response->Respond();
    }
	
    /**
     * Show the library
     * @return 
     */
    function LibraryAssignView() 
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        //Input vars
        $mediatype = Kit::GetParam('type', _POST, _STRING, 'all');
        $name = Kit::GetParam('name', _POST, _STRING, 'all');

        setSession('content', 'mediatype', $mediatype);
        setSession('content', 'name', $name);

        // query to get all media that is in the database ready to display
        $SQL  = "";
        $SQL .= "SELECT media.mediaID, ";
        $SQL .= "       media.name, ";
        $SQL .= "       media.type, ";
        $SQL .= "       media.duration ";
        $SQL .= "  FROM media ";
        $SQL .= " WHERE retired = 0 AND isEdited = 0 ";

        // Filter on media type
        if($mediatype != 'all') 
            $SQL.= sprintf(" AND media.type = '%s'", $mediatype);
            
        // Filter on name
        if ($name != 'all') 
        {
            // convert into a space delimited array
            $names = explode(' ', $name);

            foreach($names as $searchName)
            {
                // Not like, or like?
                if (substr($searchName, 0, 1) == '-')
                    $SQL.= " AND  (media.name NOT LIKE '%" . sprintf('%s', ltrim($db->escape_string($searchName), '-')) . "%') ";
                else
                    $SQL.= " AND  (media.name LIKE '%" . sprintf('%s', $db->escape_string($searchName)) . "%') ";
            }
        }

        $SQL .= " ORDER BY media.name ";

        if(!$results = $db->query($SQL)) 
        {
            trigger_error($db->error());
            trigger_error(__('Cannot get list of media in the library'), E_USER_ERROR);			
        }

        $response->html  = '<h3>' . __('Library') . '</h3>';
        $response->html .= '<ul id="LibraryAvailableSortable" class="connectedSortable">';

        // while loop
        while ($row = $db->get_row($results)) 
        {			
            $mediaId = Kit::ValidateParam($row[0], _INT);
            $media = Kit::ValidateParam($row[1], _STRING);
            $mediatype = Kit::ValidateParam($row[2], _WORD);
            $length = sec2hms(Kit::ValidateParam($row[3], _DOUBLE));
            
            // Permissions
            $auth = $this->user->MediaAuth($mediaId, true);
            
            // Is this user allowed to see this
            if ($auth->view) 
                $response->html .= '<li class="li-sortable" id="MediaID_' . $mediaId . '">' . $media . ' (' . $mediatype . ') - Duration (sec): ' . $length . '</li>';
        }

        //table ending
        $response->html .= '</ul>';

        // Construct the Response
        $response->success = true;
        $response->callBack = 'LibraryAssignCallback';
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
