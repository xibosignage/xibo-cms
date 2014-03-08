<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
            Theme::Set('filter_duration_in_seconds', Session::Get('content', 'filter_duration_in_seconds'));
            Theme::Set('filter_duration_in_seconds_checked', ((Theme::Get('filter_duration_in_seconds') == 1) ? 'checked' : ''));
        }
        else {
            Theme::Set('filter_retired', 0);
			Theme::Set('filter_duration_in_seconds', 0);
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
		$filter_duration_in_seconds = Kit::GetParam('filter_duration_in_seconds', _REQUEST, _CHECKBOX);
                
		setSession('content', 'filter_type', $filter_type);
		setSession('content', 'filter_name', $filter_name);
		setSession('content', 'filter_owner', $filter_userid);
        setSession('content', 'filter_retired', $filter_retired);
		setSession('content', 'filter_duration_in_seconds', $filter_duration_in_seconds);
        setSession('content', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
		
		// Construct the SQL
		$mediaList = $user->MediaList($filter_type, $filter_name, $filter_userid, $filter_retired);

		$rows = array();

		// Add some additional row content
		foreach ($mediaList as $row) {

			$row['duration_text'] = ($filter_duration_in_seconds == 1) ? $row['duration'] : sec2hms($row['duration']);
			$row['owner'] = $user->getNameFromID($row['ownerid']);
			$row['permissions'] = $group = $this->GroupsForMedia($row['mediaid']);
			$row['revised'] = ($row['parentid'] != 0) ? Theme::Image('act.gif') : '';

			// Display a friendly file size
			$row['size_text'] = Kit::FormatBytes($row['filesize']);

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
            
            // Download
            $row['buttons'][] = array(
                    'id' => 'content_button_download',
                    'url' => 'index.php?p=module&mod=' . $row['mediatype'] . '&q=Exec&method=GetResource&download=1&mediaid=' . $row['mediaid'],
                    'text' => __('Download')
                );

            // Add to the collection
			$rows[] = $row;
		}
		
    	Theme::Set('table_rows', $rows);
        
        $output = Theme::RenderReturn('library_page_grid');

    	$response->SetGridResponse($output);
        $response->initialSortColumn = 2;
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
		if (!$enabledModules = new ModuleManager($db, $user, 0, '', -1)) 
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
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="content"><input type="hidden" name="q" value="LibraryAssignView">');
        Theme::Set('pager', ResponseManager::Pager($id));
        
        // Module types filter
        $modules = $this->user->ModuleAuth(0, '', 1);
        $types = array();

        foreach ($modules as $module) {
            $type['moduleid'] = $module['Module'];
            $type['module'] = $module['Name'];

            $types[] = $type;
        }

        array_unshift($types, array('moduleid' => '', 'module' => 'All'));
        Theme::Set('module_field_list', $types);

        // Call to render the template
        $output = Theme::RenderReturn('library_form_assign');

        // Input vars
        $layoutId = Kit::GetParam('layoutid', _REQUEST, _INT);
        $regionId = Kit::GetParam('regionid', _REQUEST, _STRING);

        // Construct the Response
        $response->html = $output;
        $response->success = true;
        $response->dialogSize = true;
        $response->dialogClass = 'modal-big';
        $response->dialogWidth = '780px';
        $response->dialogHeight = '580px';
        $response->dialogTitle = __('Assign an item from the Library');

        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Library', 'Assign') . '")');
        $response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutId . '&regionid=' . $regionId . '&q=RegionOptions")');
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
        $mediatype = Kit::GetParam('filter_type', _POST, _STRING);
        $name = Kit::GetParam('filter_name', _POST, _STRING);

        // Get a list of media
        $mediaList = $user->MediaList($mediatype, $name);

        $rows = array();

        // Add some extra information
        foreach ($mediaList as $row) {

            $row['duration_text'] = sec2hms($row['duration']);
            $row['list_id'] = 'MediaID_' . $row['mediaid'];

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        // Render the Theme
        $response->SetGridResponse(Theme::RenderReturn('library_form_assign_list'));
        $response->callBack = 'LibraryAssignCallback';
        $response->pageSize = 5;
        $response->Respond();
    }
	
    /**
     * Gets called by the SWFUpload Object for uploading files
     * @return
     */
    function FileUpload()
    {
        $db =& $this->db;

        Debug::LogEntry('audit', 'Uploading a file', 'Library', 'FileUpload');

        Kit::ClassLoader('file');
        $fileObject = new File($db);

        
        // Check we got a valid file
        if (isset($_FILES['media_file']) && is_uploaded_file($_FILES['media_file']['tmp_name']) && $_FILES['media_file']['error'] == 0)
        {
            Debug::LogEntry('audit', 'Valid Upload', 'Library', 'FileUpload');

            // Directory location
            $libraryFolder  = Config::GetSetting('LIBRARY_LOCATION');
            $error          = 0;
            $fileName       = Kit::ValidateParam($_FILES['media_file']['name'], _FILENAME);
            $fileId         = $fileObject->GenerateFileId($this->user->userid);
            $fileLocation   = $libraryFolder . 'temp/' . $fileId;

            // Make sure the library exists
            $fileObject->EnsureLibraryExists();

            // Save the FILE
            Debug::LogEntry('audit', 'Saving the file to: ' . $fileLocation, 'FileUpload');

            move_uploaded_file($_FILES['media_file']['tmp_name'], $fileLocation);

            Debug::LogEntry('audit', 'Upload Success', 'FileUpload');
        }
        else
        {
            $error      = (isset($_FILES['media_file'])) ? $_FILES['media_file']['error'] : -1;
            $fileName   = 'Error';
            $fileId     = 0;
            
            Debug::LogEntry('audit', 'Error uploading the file. Error Number: ' . $error , 'FileUpload');
        }

        $complete_page = <<<HTML
        <html>
            <head>
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

        Debug::LogEntry("audit", $complete_page, "FileUpload");
        Debug::LogEntry("audit", "[OUT]", "FileUpload");
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

    /**
     * End point for jQuery file uploader
     */
    public function JqueryFileUpload() {
        $db =& $this->db;

        require_once("3rdparty/jquery-file-upload/UploadHandler.php");
        $type = Kit::GetParam('type', _REQUEST, _WORD);

        Kit::ClassLoader('file');
        $fileObject = new File($db);
        
        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        $fileObject->EnsureLibraryExists();

        // Get Valid Extensions
        Kit::ClassLoader('media');
        $media = new Media($db);
        $validExt = $media->ValidExtensions($type);

        $options = array(
                'upload_dir' => $libraryFolder . 'temp/', 
                'download_via_php' => true,
                'script_url' => Kit::GetXiboRoot() . '?p=content&q=JqueryFileUpload',
                'upload_url' => Kit::GetXiboRoot() . '?p=content&q=JqueryFileUpload',
                'image_versions' => array(),
                'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i'
            );

        // Hand off to the Upload Handler provided by jquery-file-upload
        $handler = new UploadHandler($db, $this->user, $options);

        // Must commit if in a transaction
        try {
            $dbh = PDOConnect::init();
            $dbh->commit();
        }
        catch (Exception $e) {
            Debug::LogEntry('audit', 'Unable to commit/rollBack');
        }

        // Must prevent from continuing (framework will try to issue a response)
        exit;
    }
}
?>
