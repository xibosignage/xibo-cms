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
 
class contentDAO extends baseDAO {
	/**
	 * Displays the page logic
	 */
	function displayPage() 
	{
		$db =& $this->db;
		
		// Default options
        if (Kit::IsFilterPinned('content', 'Filter')) {
            $filter_pinned = 1;
            $filter_name = Session::Get('content', 'filter_name');
            $filter_type = Session::Get('content', 'filter_type');
            $filter_retired = Session::Get('content', 'filter_retired');
            $filter_owner = Session::Get('content', 'filter_owner');
            $filter_duration_in_seconds = Session::Get('content', 'filter_duration_in_seconds');
            $showTags = Session::Get('content', 'showTags');
            $filter_showThumbnail = Session::Get('content', 'filter_showThumbnail');
        }
        else {
            $filter_pinned = 0;
            $filter_name = NULL;
            $filter_type = NULL;
            $filter_retired = 0;
            $filter_owner = NULL;
            $filter_duration_in_seconds = 0;
            $filter_showThumbnail = 0;
            $showTags = 0;
        }

		$id = uniqid();
		Theme::Set('id', $id);
		Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
		Theme::Set('pager', ResponseManager::Pager($id));
		Theme::Set('form_meta', '<input type="hidden" name="p" value="content"><input type="hidden" name="q" value="LibraryGrid">');

        $formFields = array();
        $formFields[] = FormManager::AddText('filter_name', __('Name'), $filter_name, NULL, 'n');
        
        // Users we have permission to see
        $users = $this->user->userList();
        array_unshift($users, array('userid' => '', 'username' => 'All'));

        $formFields[] = FormManager::AddCombo(
            'filter_owner', 
            __('Owner'), 
            $filter_owner,
            $users,
            'userid',
            'username',
            NULL, 
            'o');

        $types = $db->GetArray("SELECT Module AS moduleid, Name AS module FROM `module` WHERE RegionSpecific = 0 AND Enabled = 1 ORDER BY 2");
        array_unshift($types, array('moduleid' => '', 'module' => 'All'));
        $formFields[] = FormManager::AddCombo(
            'filter_type', 
            __('Type'), 
            $filter_type,
            $types,
            'moduleid',
            'module',
            NULL, 
            'y');

        $formFields[] = FormManager::AddCombo(
            'filter_retired', 
            __('Retired'), 
            $filter_retired,
            array(array('retiredid' => 1, 'retired' => 'Yes'), array('retiredid' => 0, 'retired' => 'No')),
            'retiredid',
            'retired',
            NULL, 
            'r');

        $formFields[] = FormManager::AddCheckbox('filter_duration_in_seconds', __('Duration in Seconds'), 
            $filter_duration_in_seconds, NULL, 
            's');

        $formFields[] = FormManager::AddCheckbox('showTags', __('Show Tags'), 
            $showTags, NULL, 
            't');
        
        $formFields[] = FormManager::AddCheckbox('filter_showThumbnail', __('Show Thumbnails'), 
            $filter_showThumbnail, NULL, 
            't');

        $formFields[] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'), 
            $filter_pinned, NULL, 
            'k');

        // Call to render the template
        Theme::Set('header_text', __('Library'));
        Theme::Set('form_fields', $formFields);
        Theme::Render('grid_render');
	}

    function actionMenu() {

        return array(
                array('title' => __('Filter'),
                    'class' => '',
                    'selected' => false,
                    'link' => '#',
                    'help' => __('Open the filter form'),
                    'onclick' => 'ToggleFilterView(\'Filter\')'
                    ),
                array('title' => __('Add Media'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=content&q=fileUploadForm',
                    'help' => __('Add a new media item to the library'),
                    'onclick' => ''
                    )
            );                   
    }
	
	/**
	 * Prints out a Table of all media items
	 */
	function LibraryGrid() 
	{
		$user =& $this->user;
		$response = new ResponseManager();

		//Get the input params and store them
		$filter_type = Kit::GetParam('filter_type', _REQUEST, _WORD);
		$filter_name = Kit::GetParam('filter_name', _REQUEST, _STRING);
		$filter_userid = Kit::GetParam('filter_owner', _REQUEST, _INT);
        $filter_retired = Kit::GetParam('filter_retired', _REQUEST, _INT);
        $filter_duration_in_seconds = Kit::GetParam('filter_duration_in_seconds', _REQUEST, _CHECKBOX);
        $filter_showThumbnail = Kit::GetParam('filter_showThumbnail', _REQUEST, _CHECKBOX);
        $showTags = Kit::GetParam('showTags', _REQUEST, _CHECKBOX);
                
		setSession('content', 'filter_type', $filter_type);
		setSession('content', 'filter_name', $filter_name);
		setSession('content', 'filter_owner', $filter_userid);
        setSession('content', 'filter_retired', $filter_retired);
        setSession('content', 'filter_duration_in_seconds', $filter_duration_in_seconds);
        setSession('content', 'filter_showThumbnail', $filter_showThumbnail);
		setSession('content', 'showTags', $showTags);
        setSession('content', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
		
		// Construct the SQL
		$mediaList = $user->MediaList(NULL, array('type' => $filter_type, 'name' => $filter_name, 'ownerid' => $filter_userid, 'retired' => $filter_retired, 'showTags' => $showTags));

        $cols = array();
        $cols[] = array('name' => 'mediaid', 'title' => __('ID'));
        $cols[] = array('name' => 'tags', 'title' => __('Tag'), 'hidden' => ($showTags == 0), 'colClass' => 'group-word');
        $cols[] = array('name' => 'media', 'title' => __('Name'));
        $cols[] = array('name' => 'mediatype', 'title' => __('Type'));

        if ($filter_showThumbnail == 1)
            $cols[] = array('name' => 'thumbnail', 'title' => __('Thumbnail'));

        $cols[] = array('name' => 'duration_text', 'title' => __('Duration'));
        $cols[] = array('name' => 'size_text', 'title' => __('Size'), 'sorter' => 'filesize');
        $cols[] = array('name' => 'owner', 'title' => __('Owner'));
        $cols[] = array('name' => 'permissions', 'title' => __('Permissions'));
        $cols[] = array('name' => 'revised', 'title' => __('Revised?'), 'icons' => true);
        $cols[] = array('name' => 'filename', 'title' => __('File Name'));
            
        Theme::Set('table_cols', $cols);

		$rows = array();

		// Add some additional row content
		foreach ($mediaList as $media) {
            /* @var \Xibo\Entity\Media $media */
            $row = array();

            $row['mediaid'] = $media->mediaId;
            $row['media'] = $media->name;
            $row['filename'] = $media->fileName;
            $row['mediatype'] = $media->mediaType;
            $row['duration'] = $media->duration;
            $row['tags'] = $media->tags;

			$row['duration_text'] = ($filter_duration_in_seconds == 1) ? $media->duration : sec2hms($media->duration);
			$row['owner'] = $media->owner;
			$row['permissions'] = $media->groupsWithPermissions;
			$row['revised'] = ($media->parentId != 0) ? 1 : 0;

			// Display a friendly file size
			$row['size_text'] = Kit::FormatBytes($media->fileSize);

            // Thumbnail URL
            $row['thumbnail'] = '';

            if ($row['mediatype'] == 'image') {
                $row['thumbnail'] = '<a class="img-replace" data-toggle="lightbox" data-type="image" data-img-src="index.php?p=content&q=getFile&mediaid=' . $media->mediaId . '&width=100&height=100&dynamic=true&thumb=true" href="index.php?p=content&q=getFile&mediaid=' . $media->mediaId . '"><i class="fa fa-file-image-o"></i></a>';
            }

			$row['buttons'] = array();

			// Buttons
            if ($user->checkEditable($media)) {
                
                // Edit
                $row['buttons'][] = array(
                        'id' => 'content_button_edit',
                        'url' => 'index.php?p=content&q=editForm&mediaid=' . $media->mediaId,
                        'text' => __('Edit')
                    );
            }
            
            if ($user->checkDeleteable($media)) {
				// Delete
                $row['buttons'][] = array(
                        'id' => 'content_button_delete',
                        'url' => 'index.php?p=content&q=deleteForm&mediaid=' . $media->mediaId,
                        'text' => __('Delete')
                    );
            }

            if ($user->checkPermissionsModifyable($media)) {

        		// Permissions
                $row['buttons'][] = array(
                        'id' => 'content_button_permissions',
                        'url' => 'index.php?p=user&q=permissionsForm&entity=Media&objectId=' . $media->mediaId,
                        'text' => __('Permissions')
                    );
            }
            
            // Download
            $row['buttons'][] = array(
                    'id' => 'content_button_download',
                    'linkType' => '_self',
                    'url' => 'index.php?p=content&q=getFile&download=1&downloadFromLibrary=1&mediaid=' . $media->mediaId,
                    'text' => __('Download')
                );

            // Add to the collection
			$rows[] = $row;
		}
		
    	Theme::Set('table_rows', $rows);
        
        $output = Theme::RenderReturn('table_render');

    	$response->SetGridResponse($output);
        $response->initialSortColumn = 2;
        $response->Respond();
    }
	
	/**
	 * File Uploader
     * Presents a form which can be used to upload file based media
	 */
	function fileUploadForm()
	{
        $response = new ResponseManager();

        // Check we have room in the library
        $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

        if ($libraryLimit > 0)
        {
            $fileSize = File::libraryUsage();

            if (($fileSize / 1024) > $libraryLimit)
                trigger_error(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit), E_USER_ERROR);
        }

        // Set the Session / Security information
        $sessionId = session_id();
        $securityToken = Kit::Token('fileUploadToken', false);

        // Do we come from the Background Image?
        $backgroundImage = Kit::GetParam('backgroundImage', _GET, _BOOL, false);
        $layoutId = Kit::GetParam('layoutId', _GET, _INT);

        // Do we have a playlistId?
        $playlistId = Kit::GetParam('playlistId', _GET, _INT);
        $regionId = Kit::GetParam('regionId', _GET, _INT);

        // Save button is different depending on whether we came from the Layout Edit form or not.
        if ($backgroundImage) {
            $response->AddButton(__('Close'), 'XiboSwapDialog("index.php?p=layout&q=EditForm&modify=true&layoutid=' . $layoutId . '")');

            // Background override url is used on the theme to add a button next to each uploaded file (if in background override)
            Theme::Set('background_override_url', "index.php?p=layout&q=EditForm&modify=true&layoutid=$layoutId&backgroundOveride=");
        }
        else if ($playlistId != 0) {
            $response->AddButton(__('Finish'), 'XiboSwapDialog("index.php?p=timeline&q=Timeline&modify=true&layoutid=' . $layoutId . '&regionId=' . $regionId . '")');
        }
        else {
            $response->AddButton(__('Close'), 'XiboDialogClose(); XiboRefreshAllGrids();');
        }

        // Setup the theme
        Theme::Set('form_upload_id', 'fileupload');
        Theme::Set('form_action', 'index.php?p=content&q=JqueryFileUpload');
        Theme::Set('form_meta', '<input type="hidden" id="PHPSESSID" value="' . $sessionId . '" /><input type="hidden" id="SecurityToken" value="' . $securityToken . '" /><input type="hidden" name="playlistId" value="' . $playlistId . '" />');
        Theme::Set('form_valid_ext', '/(\.|\/)' . implode('|', \Xibo\Factory\ModuleFactory::getValidExtensions()) . '$/i');
        Theme::Set('form_max_size', Kit::ReturnBytes(Config::getMaxUploadSize()));
        Theme::Set('form_max_size_message', sprintf(__('This form accepts files up to a maximum size of %s'), Config::getMaxUploadSize()));

        $form = Theme::RenderReturn('library_form_media_add');

        $response->html = $form;
        $response->dialogTitle = __('Upload media');
        $response->callBack = 'MediaFormInitUpload';
        $response->dialogClass = 'modal-big';
        $response->Respond();
	}

    /**
     * Gets a file from the library
     */
    public function getFile()
    {
        // Get the MediaId
        $mediaId = Kit::GetParam('mediaId', _GET, _INT);

        // Can this user view?
        $entries = $this->user->MediaList(null, array('mediaId' => $mediaId));
        if (count($entries) <= 0) {
            $width = Kit::GetParam('width', _GET, _INT);
            $height = Kit::GetParam('height', _GET, _INT);

            // dynamically create an image of the correct size - used for previews
            ResizeImage(Theme::ImageUrl('forms/filenotfound.gif'), '', $width, $height, true, 'browser');
            exit();
        }

        File::ReturnFile($entries[0]['storedas'], $entries[0]['filename']);
    }

    /**
     * Edit Form
     */
    function editForm()
    {
        //TODO: Editform
        $formFields[] = FormManager::AddText('tags', __('Tags'), $this->widget->tags,
            __('Tag this media. Comma Separated.'), 'n');

        $formFields[] = FormManager::AddCheckbox('replaceBackgroundImages', __('Replace background images?'),
            0,
            __('If the current image is used as a background, should the new image replace it?'),
            '', 'replacement-controls');

        if ($this->assignable) {
            $formFields[] = FormManager::AddCheckbox('replaceInLayouts', __('Update this media in all layouts it is assigned to?'),
                ((Config::GetSetting('LIBRARY_MEDIA_UPDATEINALL_CHECKB') == 'Checked') ? 1 : 0),
                __('Note: It will only be replaced in layouts you have permission to edit.'),
                'r');
        }

        $formFields[] = FormManager::AddCheckbox('deleteOldVersion', __('Delete the old version?'),
            ((Config::GetSetting('LIBRARY_MEDIA_UPDATEINALL_CHECKB') == 'Checked') ? 1 : 0),
            __('Completely remove the old version of this media item if a new file is being uploaded.'),
            '');
    }

    /**
     * Media Delete Form
     * @throws Exception
     */
    public function deleteForm()
    {
        $response = new ResponseManager();

        // Get the MediaId
        $media = \Xibo\Factory\MediaFactory::getById(Kit::GetParam('mediaId', _GET, _INT));

        // Can this user delete?
        if (!$this->user->checkDeleteable($media))
            throw new Exception(__('You do not have permission to delete this media.'));

        Theme::Set('form_id', 'MediaDeleteForm');
        Theme::Set('form_action', 'index.php?p=content&q=delete');
        Theme::Set('form_meta', '<input type="hidden" name="mediaId" value="' . $media->mediaId . '">');
        $formFields = array(
            FormManager::AddMessage(__('Are you sure you want to remove this Media?')),
            FormManager::AddMessage(__('This action cannot be undone.')),
        );

        Theme::Set('form_fields', $formFields);
        $form = Theme::RenderReturn('form_render');

        $response->SetFormRequestResponse($form, __('Delete Media'), '300px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Media', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#MediaDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Delete Media
     */
    public function delete()
    {
        $response = new ResponseManager();

        // Get the MediaId
        $media = \Xibo\Factory\MediaFactory::getById(Kit::GetParam('mediaId', _GET, _INT));

        // Can this user delete?
        if (!$this->user->checkDeleteable($media))
            throw new Exception(__('You do not have permission to delete this media.'));

        // Delete
        $media->Delete();

        $response->SetFormSubmitResponse(__('The Media has been Deleted'));
        $response->Respond();
    }

    /**
     * Replace media in all layouts.
     * @param <type> $oldMediaId
     * @param <type> $newMediaId
     */
    private function ReplaceMediaInAllLayouts($replaceInLayouts, $replaceBackgroundImages, $oldMediaId, $newMediaId)
    {
        $count = 0;

        Debug::LogEntry('audit', sprintf('Replacing mediaid %s with mediaid %s in all layouts', $oldMediaId, $newMediaId), 'module', 'ReplaceMediaInAllLayouts');

        try {
            $dbh = PDOConnect::init();

            // Some update statements to use
            $sth = $dbh->prepare('SELECT lklayoutmediaid, regionid FROM lklayoutmedia WHERE mediaid = :media_id AND layoutid = :layout_id');
            $sth_update = $dbh->prepare('UPDATE lklayoutmedia SET mediaid = :media_id WHERE lklayoutmediaid = :lklayoutmediaid');

            // Loop through a list of layouts this user has access to
            foreach($this->user->LayoutList() as $layout) {
                $layoutId = $layout['layoutid'];

                // Does this layout use the old media id?
                $sth->execute(array(
                    'media_id' => $oldMediaId,
                    'layout_id' => $layoutId
                ));

                $results = $sth->fetchAll();

                if (count($results) <= 0)
                    continue;

                Debug::LogEntry('audit', sprintf('%d linked media items for layoutid %d', count($results), $layoutId), 'module', 'ReplaceMediaInAllLayouts');

                // Create a region object for later use (new one each time)
                $layout = new Layout();
                $region = new region($this->db);

                // Loop through each media link for this layout
                foreach ($results as $row)
                {
                    // Get the LKID of the link between this layout and this media.. could be more than one?
                    $lkId = $row['lklayoutmediaid'];
                    $regionId = $row['regionid'];

                    if ($regionId == 'background') {

                        Debug::Audit('Replacing background image');

                        if (!$replaceBackgroundImages)
                            continue;

                        // Straight swap this background image node.
                        if (!$layout->EditBackgroundImage($layoutId, $newMediaId))
                            return false;
                    }
                    else {

                        if (!$replaceInLayouts)
                            continue;

                        // Get the Type of this media
                        if (!$type = $region->GetMediaNodeType($layoutId, '', '', $lkId))
                            continue;

                        // Create a new media node use it to swap the nodes over
                        Debug::LogEntry('audit', 'Creating new module with MediaID: ' . $newMediaId . ' LayoutID: ' . $layoutId . ' and RegionID: ' . $regionId, 'region', 'ReplaceMediaInAllLayouts');
                        try {
                            $module = ModuleFactory::createForMedia($type, $newMediaId, $this->db, $this->user);
                        }
                        catch (Exception $e) {
                            Debug::Error($e->getMessage());
                            return false;
                        }

                        // Sets the URI field
                        if (!$module->SetRegionInformation($layoutId, $regionId))
                            return false;

                        // Get the media xml string to use in the swap.
                        $mediaXmlString = $module->AsXml();

                        // Swap the nodes
                        if (!$region->SwapMedia($layoutId, $regionId, $lkId, $oldMediaId, $newMediaId, $mediaXmlString))
                            return false;
                    }

                    // Update the LKID with the new media id
                    $sth_update->execute(array(
                        'media_id' => $newMediaId,
                        'lklayoutmediaid' => $row['lklayoutmediaid']
                    ));

                    $count++;
                }
            }
        }
        catch (Exception $e) {

            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            return false;
        }

        Debug::LogEntry('audit', sprintf('Replaced media in %d layouts', $count), 'module', 'ReplaceMediaInAllLayouts');
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
        Theme::Set('pager', ResponseManager::Pager($id, 'grid_pager'));
        
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
        $mediaList = $user->MediaList(NULL, array('type' => $mediatype, 'name' => $name));

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
            File::EnsureLibraryExists();

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
     * End point for jQuery file uploader
     */
    public function JqueryFileUpload() {

        require_once('3rdparty/jquery-file-upload/XiboUploadHandler.php');

        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');
        // Make sure the library exists
        $fileObject = new File();
        $fileObject->EnsureLibraryExists();

        // Get Valid Extensions
        $validExt = \Xibo\Factory\ModuleFactory::getValidExtensions();

        $options = array(
            'userId' => $this->user->userid,
            'playlistId' => Kit::GetParam('playlistId', _REQUEST, _INT),
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => Kit::GetXiboRoot() . '?p=content&q=JqueryFileUpload',
            'upload_url' => Kit::GetXiboRoot() . '?p=content&q=JqueryFileUpload',
            'image_versions' => array(),
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i'
        );

        // Hand off to the Upload Handler provided by jquery-file-upload
        try {
            $dbh = PDOConnect::init();
            new XiboUploadHandler($options);

            // Must commit if in a transaction
            if ($dbh->inTransaction())
                $dbh->commit();
        }
        catch (Exception $e) {
            // We must not issue an error, the file upload return should have the error object already
        }

        // Must prevent from continuing (framework will try to issue a response)
        exit;
    }
}
