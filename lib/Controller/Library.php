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
namespace Xibo\Controller;

use Xibo\Exception\LibraryFullException;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Config;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;


class Library extends Base
{
    /**
     * Displays the page logic
     */
    function displayPage()
    {
        // Default options
        if (Session::Get(get_class(), 'Filter') == 1) {
            $filter_pinned = 1;
            $filter_name = Session::Get('content', 'filter_name');
            $filter_type = Session::Get('content', 'filter_type');
            $filter_retired = Session::Get('content', 'filter_retired');
            $filter_owner = Session::Get('content', 'filter_owner');
            $filter_duration_in_seconds = Session::Get('content', 'filter_duration_in_seconds');
            $showTags = Session::Get('content', 'showTags');
            $filter_showThumbnail = Session::Get('content', 'filter_showThumbnail');
        } else {
            $filter_pinned = 0;
            $filter_name = NULL;
            $filter_type = NULL;
            $filter_retired = 0;
            $filter_owner = NULL;
            $filter_duration_in_seconds = 0;
            $filter_showThumbnail = 0;
            $showTags = 0;
        }

        $data = [
            'defaults' => [
                'name' => $filter_name,
                'type' => $filter_type,
                'retired' => $filter_retired,
                'owner' => $filter_owner,
                'durationInSeconds' => $filter_duration_in_seconds,
                'showTags' => $showTags,
                'showThumbnail' => $filter_showThumbnail,
                'filterPinned' => $filter_pinned
            ]
        ];

        // Users we have permission to see
        $users = $this->getUser()->userList();
        array_unshift($users, array('userid' => '', 'username' => 'All'));
        $data['users'] = $users;

        $types = ModuleFactory::query(['module'], ['regionSpecific' => 0, 'enabled' => 1]);
        array_unshift($types, array('moduleid' => '', 'module' => 'All'));
        $data['modules'] = $types;

        $this->getState()->template = 'library-page';
        $this->getState()->setData($data);
    }

    /**
     * Prints out a Table of all media items
     */
    function grid()
    {
        $user = $this->getUser();

        //Get the input params and store them
        $filter_type = Sanitize::getString('filter_type');
        $filter_name = Sanitize::getString('filter_name');
        $filter_userid = Sanitize::getInt('filter_owner');
        $filter_retired = Sanitize::getInt('filter_retired');
        $filter_duration_in_seconds = Sanitize::getCheckbox('filter_showThumbnail');
        $filter_showThumbnail = Sanitize::getCheckbox('filter_showThumbnail');
        $showTags = Sanitize::getCheckbox('showTags');

        Session::Set('content', 'filter_type', $filter_type);
        Session::Set('content', 'filter_name', $filter_name);
        Session::Set('content', 'filter_owner', $filter_userid);
        Session::Set('content', 'filter_retired', $filter_retired);
        Session::Set('content', 'filter_duration_in_seconds', $filter_duration_in_seconds);
        Session::Set('content', 'filter_showThumbnail', $filter_showThumbnail);
        Session::Set('content', 'showTags', $showTags);
        Session::Set('content', 'Filter', Sanitize::getCheckbox('XiboFilterPinned'));

        // Construct the SQL
        $mediaList = $user->MediaList(NULL, array(
            'type' => $filter_type,
            'name' => $filter_name,
            'ownerid' => $filter_userid,
            'retired' => $filter_retired,
            'showTags' => $showTags)
        );

        // Add some additional row content
        foreach ($mediaList as $media) {
            /* @var \Xibo\Entity\Media $media */
            $media->revised = ($media->parentId != 0) ? 1 : 0;

            // Thumbnail URL
            $media->thumbnail = '';

            if ($media->mediaType == 'image') {
                $media->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" data-img-src="index.php?p=content&q=getFile&mediaid=' . $media->mediaId . '&width=100&height=100&dynamic=true&thumb=true" href="index.php?p=content&q=getFile&mediaid=' . $media->mediaId . '"><i class="fa fa-file-image-o"></i></a>';
            }

            $media->buttons = array();

            // Buttons
            if ($user->checkEditable($media)) {

                // Edit
                $media->buttons[] = array(
                    'id' => 'content_button_edit',
                    'url' => 'index.php?p=content&q=editForm&mediaid=' . $media->mediaId,
                    'text' => __('Edit')
                );
            }

            if ($user->checkDeleteable($media)) {
                // Delete
                $media->buttons[] = array(
                    'id' => 'content_button_delete',
                    'url' => 'index.php?p=content&q=deleteForm&mediaid=' . $media->mediaId,
                    'text' => __('Delete')
                );
            }

            if ($user->checkPermissionsModifyable($media)) {
                // Permissions
                $media->buttons[] = array(
                    'id' => 'content_button_permissions',
                    'url' => 'index.php?p=user&q=permissionsForm&entity=Media&objectId=' . $media->mediaId,
                    'text' => __('Permissions')
                );
            }

            // Download
            $media->buttons[] = array(
                'id' => 'content_button_download',
                'linkType' => '_self', 'external' => true,
                'url' => 'index.php?p=content&q=getFile&download=1&downloadFromLibrary=1&mediaid=' . $media->mediaId,
                'text' => __('Download')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($mediaList);
    }

    /**
     * File Uploader
     * Presents a form which can be used to upload file based media
     */
    function fileUploadForm()
    {
        $response = $this->getState();

        // Check we have room in the library
        $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

        if ($libraryLimit > 0) {
            $fileSize = File::libraryUsage();

            if (($fileSize / 1024) > $libraryLimit)
                trigger_error(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit), E_USER_ERROR);
        }

        // Check this user doesn't have a quota
        if (!\UserGroup::isQuotaFullByUser($this->getUser()->userId))
            throw new LibraryFullException(__('You have exceeded your library quota'));

        // Set the Session / Security information
        $sessionId = session_id();
        $securityToken = \Kit::Token('fileUploadToken', false);

        // Do we come from the Background Image?
        $backgroundImage = \Kit::GetParam('backgroundImage', _GET, _BOOL, false);
        $layoutId = Sanitize::getInt('layoutId');

        // Do we have a playlistId?
        $playlistId = Sanitize::getInt('playlistId');
        $regionId = Sanitize::getInt('regionId');

        // Save button is different depending on whether we came from the Layout Edit form or not.
        if ($backgroundImage) {
            $response->AddButton(__('Close'), 'XiboSwapDialog("index.php?p=layout&q=EditForm&modify=true&layoutid=' . $layoutId . '")');

            // Background override url is used on the theme to add a button next to each uploaded file (if in background override)
            Theme::Set('background_override_url', "index.php?p=layout&q=EditForm&modify=true&layoutid=$layoutId&backgroundOveride=");
        } else if ($playlistId != 0) {
            $response->AddButton(__('Finish'), 'XiboSwapDialog("index.php?p=timeline&q=Timeline&modify=true&layoutid=' . $layoutId . '&regionId=' . $regionId . '")');
        } else {
            $response->AddButton(__('Close'), 'XiboDialogClose(); XiboRefreshAllGrids();');
        }

        // Setup the theme
        Theme::Set('form_upload_id', 'fileupload');
        Theme::Set('form_action', 'index.php?p=content&q=JqueryFileUpload');
        Theme::Set('form_meta', '<input type="hidden" id="PHPSESSID" value="' . $sessionId . '" /><input type="hidden" id="SecurityToken" value="' . $securityToken . '" /><input type="hidden" name="playlistId" value="' . $playlistId . '" />');
        Theme::Set('form_valid_ext', '/(\.|\/)' . implode('|', \Xibo\Factory\ModuleFactory::getValidExtensions()) . '$/i');
        Theme::Set('form_max_size', \Kit::ReturnBytes(Config::getMaxUploadSize()));
        Theme::Set('form_max_size_message', sprintf(__('This form accepts files up to a maximum size of %s'), Config::getMaxUploadSize()));

        $form = Theme::RenderReturn('library_form_media_add');

        $response->html = $form;
        $response->dialogTitle = __('Upload media');
        $response->callBack = 'MediaFormInitUpload';
        $response->dialogClass = 'modal-big';

    }

    /**
     * Gets a file from the library
     */
    public function getFile()
    {
        // Get the MediaId
        $mediaId = Sanitize::getInt('mediaId');

        // Can this user view?
        $entries = $this->getUser()->MediaList(null, array('mediaId' => $mediaId));

        $media = $entries[0];
        /* @var \Xibo\Entity\Media $media */

        if (count($entries) <= 0) {
            $width = Sanitize::getInt('width');
            $height = Sanitize::getInt('height');

            // dynamically create an image of the correct size - used for previews
            ResizeImage(Theme::ImageUrl('forms/filenotfound.gif'), '', $width, $height, true, 'browser');
            exit();
        }

        File::ReturnFile($media->storedAs, $media->fileName);
    }

    /**
     * Edit Form
     */
    function editForm()
    {
        //TODO: Editform
        $formFields[] = Form::AddText('tags', __('Tags'), $this->widget->tags,
            __('Tag this media. Comma Separated.'), 'n');

        $formFields[] = Form::AddCheckbox('replaceBackgroundImages', __('Replace background images?'),
            0,
            __('If the current image is used as a background, should the new image replace it?'),
            '', 'replacement-controls');

        if ($this->assignable) {
            $formFields[] = Form::AddCheckbox('replaceInLayouts', __('Update this media in all layouts it is assigned to?'),
                ((Config::GetSetting('LIBRARY_MEDIA_UPDATEINALL_CHECKB') == 'Checked') ? 1 : 0),
                __('Note: It will only be replaced in layouts you have permission to edit.'),
                'r');
        }

        $formFields[] = Form::AddCheckbox('deleteOldVersion', __('Delete the old version?'),
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
        $response = $this->getState();

        // Get the MediaId
        $media = \Xibo\Factory\MediaFactory::getById(Kit::GetParam('mediaId', _GET, _INT));

        // Can this user delete?
        if (!$this->getUser()->checkDeleteable($media))
            throw new Exception(__('You do not have permission to delete this media.'));

        Theme::Set('form_id', 'MediaDeleteForm');
        Theme::Set('form_action', 'index.php?p=content&q=delete');
        Theme::Set('form_meta', '<input type="hidden" name="mediaId" value="' . $media->mediaId . '">');
        $formFields = array(
            Form::AddMessage(__('Are you sure you want to remove this Media?')),
            Form::AddMessage(__('This action cannot be undone.')),
        );

        Theme::Set('form_fields', $formFields);
        $form = Theme::RenderReturn('form_render');

        $response->SetFormRequestResponse($form, __('Delete Media'), '300px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Media', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#MediaDeleteForm").submit()');

    }

    /**
     * Delete Media
     */
    public function delete()
    {
        $response = $this->getState();

        // Get the MediaId
        $media = \Xibo\Factory\MediaFactory::getById(Kit::GetParam('mediaId', _GET, _INT));

        // Can this user delete?
        if (!$this->getUser()->checkDeleteable($media))
            throw new Exception(__('You do not have permission to delete this media.'));

        // Delete
        $media->Delete();

        $response->SetFormSubmitResponse(__('The Media has been Deleted'));

    }

    /**
     * Replace media in all layouts.
     * @param <type> $oldMediaId
     * @param <type> $newMediaId
     */
    private function ReplaceMediaInAllLayouts($replaceInLayouts, $replaceBackgroundImages, $oldMediaId, $newMediaId)
    {
        $count = 0;

        Log::notice(sprintf('Replacing mediaid %s with mediaid %s in all layouts', $oldMediaId, $newMediaId), 'module', 'ReplaceMediaInAllLayouts');

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            // Some update statements to use
            $sth = $dbh->prepare('SELECT lklayoutmediaid, regionid FROM lklayoutmedia WHERE mediaid = :media_id AND layoutid = :layout_id');
            $sth_update = $dbh->prepare('UPDATE lklayoutmedia SET mediaid = :media_id WHERE lklayoutmediaid = :lklayoutmediaid');

            // Loop through a list of layouts this user has access to
            foreach ($this->getUser()->LayoutList() as $layout) {
                $layoutId = $layout['layoutid'];

                // Does this layout use the old media id?
                $sth->execute(array(
                    'media_id' => $oldMediaId,
                    'layout_id' => $layoutId
                ));

                $results = $sth->fetchAll();

                if (count($results) <= 0)
                    continue;

                Log::notice(sprintf('%d linked media items for layoutid %d', count($results), $layoutId), 'module', 'ReplaceMediaInAllLayouts');

                // Create a region object for later use (new one each time)
                $layout = new Layout();
                $region = new region($this->db);

                // Loop through each media link for this layout
                foreach ($results as $row) {
                    // Get the LKID of the link between this layout and this media.. could be more than one?
                    $lkId = $row['lklayoutmediaid'];
                    $regionId = $row['regionid'];

                    if ($regionId == 'background') {

                        Log::debug('Replacing background image');

                        if (!$replaceBackgroundImages)
                            continue;

                        // Straight swap this background image node.
                        if (!$layout->EditBackgroundImage($layoutId, $newMediaId))
                            return false;
                    } else {

                        if (!$replaceInLayouts)
                            continue;

                        // Get the Type of this media
                        if (!$type = $region->GetMediaNodeType($layoutId, '', '', $lkId))
                            continue;

                        // Create a new media node use it to swap the nodes over
                        Log::notice('Creating new module with MediaID: ' . $newMediaId . ' LayoutID: ' . $layoutId . ' and RegionID: ' . $regionId, 'region', 'ReplaceMediaInAllLayouts');
                        try {
                            $module = ModuleFactory::createForMedia($type, $newMediaId, $this->db, $this->user);
                        } catch (Exception $e) {
                            Log::error($e->getMessage());
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
        } catch (Exception $e) {

            Log::error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            return false;
        }

        Log::notice(sprintf('Replaced media in %d layouts', $count), 'module', 'ReplaceMediaInAllLayouts');
    }

    /**
     * Displays the Library Assign form
     * @return
     */
    function LibraryAssignForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="content"><input type="hidden" name="q" value="LibraryAssignView">');
        Theme::Set('pager', ApplicationState::Pager($id, 'grid_pager'));

        // Module types filter
        $modules = $this->getUser()->ModuleAuth(0, '', 1);
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
        $layoutId = Sanitize::getInt('layoutid');
        $regionId = Sanitize::getString('regionid');

        // Construct the Response
        $response->html = $output;
        $response->success = true;
        $response->dialogSize = true;
        $response->dialogClass = 'modal-big';
        $response->dialogWidth = '780px';
        $response->dialogHeight = '580px';
        $response->dialogTitle = __('Assign an item from the Library');

        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Library', 'Assign') . '")');
        $response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutId . '&regionid=' . $regionId . '&q=RegionOptions")');
        $response->AddButton(__('Assign'), 'LibraryAssignSubmit("' . $layoutId . '","' . $regionId . '")');


    }

    /**
     * Show the library
     * @return
     */
    function LibraryAssignView()
    {

        $user = $this->getUser();
        $response = $this->getState();

        //Input vars
        $mediatype = Sanitize::getString('filter_type');
        $name = Sanitize::getString('filter_name');

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

    }

    /**
     * Gets called by the SWFUpload Object for uploading files
     * @return
     */
    function FileUpload()
    {


        Log::notice('Uploading a file', 'Library', 'FileUpload');


        $fileObject = new File($db);


        // Check we got a valid file
        if (isset($_FILES['media_file']) && is_uploaded_file($_FILES['media_file']['tmp_name']) && $_FILES['media_file']['error'] == 0) {
            Log::notice('Valid Upload', 'Library', 'FileUpload');

            // Directory location
            $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');
            $error = 0;
            $fileName = \Kit::ValidateParam($_FILES['media_file']['name'], _FILENAME);
            $fileId = $fileObject->GenerateFileId($this->getUser()->userId);
            $fileLocation = $libraryFolder . 'temp/' . $fileId;

            // Make sure the library exists
            File::EnsureLibraryExists();

            // Save the FILE
            Log::notice('Saving the file to: ' . $fileLocation, 'FileUpload');

            move_uploaded_file($_FILES['media_file']['tmp_name'], $fileLocation);

            Log::notice('Upload Success', 'FileUpload');
        } else {
            $error = (isset($_FILES['media_file'])) ? $_FILES['media_file']['error'] : -1;
            $fileName = 'Error';
            $fileId = 0;

            Log::notice('Error uploading the file. Error Number: ' . $error, 'FileUpload');
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

        Log::notice("audit", $complete_page, "FileUpload");
        Log::notice("audit", "[OUT]", "FileUpload");
        exit;
    }

    /**
     * End point for jQuery file uploader
     */
    public function JqueryFileUpload()
    {

        require_once('3rdparty/jquery-file-upload/XiboUploadHandler.php');

        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');
        // Make sure the library exists
        $fileObject = new File();
        $fileObject->EnsureLibraryExists();

        // Get Valid Extensions
        $validExt = \Xibo\Factory\ModuleFactory::getValidExtensions();

        $options = array(
            'userId' => $this->getUser()->userId,
            'playlistId' => \Kit::GetParam('playlistId', _REQUEST, _INT),
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => \Kit::GetXiboRoot() . '?p=content&q=JqueryFileUpload',
            'upload_url' => \Kit::GetXiboRoot() . '?p=content&q=JqueryFileUpload',
            'image_versions' => array(),
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i'
        );

        // Hand off to the Upload Handler provided by jquery-file-upload
        try {
            $dbh = \Xibo\Storage\PDOConnect::init();
            new XiboUploadHandler($options);

            // Must commit if in a transaction
            if ($dbh->inTransaction())
                $dbh->commit();
        } catch (Exception $e) {
            // We must not issue an error, the file upload return should have the error object already
        }

        // Must prevent from continuing (framework will try to issue a response)
        exit;
    }

    public function tidyLibraryForm()
    {
        $response = $this->getState();

        Theme::Set('form_id', 'TidyLibraryForm');
        Theme::Set('form_action', 'index.php?p=content&q=tidyLibrary');

        $formFields = array();
        $formFields[] = Form::AddMessage(__('Tidying your Library will delete any media that is not currently in use.'));

        // Work out how many files there are
        $media = Media::entriesUnusedForUser($this->getUser()->userId);

        $formFields[] = Form::AddMessage(sprintf(__('There is %s of data stored in %d files . Are you sure you want to proceed?', ByteFormatter::format(array_sum(array_map(function ($element) {
            return $element['fileSize'];
        }, $media))), count($media))));

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Tidy Library'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Content', 'TidyLibrary') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#TidyLibraryForm").submit()');

    }

    /**
     * Tidies up the library
     */
    public function tidyLibrary()
    {
        $response = $this->getState();

        if (Config::GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            trigger_error(__('Sorry this function is disabled.'), E_USER_ERROR);

        $media = new Media();
        if (!$media->deleteUnusedForUser($this->getUser()->userId))
            trigger_error($media->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Library Tidy Complete'));

    }
}
