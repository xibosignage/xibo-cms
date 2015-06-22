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

use Parsedown;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ResolutionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;

class Layout extends Base
{
    /**
     * Displays the Layout Page
     */
    function displayPage()
    {
        // Default options
        if (Session::Get('layout', 'Filter') == 1) {

            $layout = Session::Get('layout', 'filter_layout');
            $tags = Session::Get('layout', 'filter_tags');
            $retired = Session::Get('layout', 'filter_retired');
            $owner = Session::Get('layout', 'filter_userid');
            $filterLayoutStatusId = Session::Get('layout', 'filterLayoutStatusId');
            $showDescriptionId = Session::Get('layout', 'showDescriptionId');
            $showThumbnail = Session::Get('layout', 'showThumbnail');
            $showTags = Session::Get('layout', 'showTags');
            $pinned = 1;

        } else {

            $layout = NULL;
            $tags = NULL;
            $retired = 0;
            $owner = NULL;
            $filterLayoutStatusId = 1;
            $showDescriptionId = 2;
            $pinned = 0;
            $showThumbnail = 1;
            $showTags = 0;
        }

        // Users we have permission to see
        $users = $this->getUser()->userList();
        $users = array_map(function($element) { return array('userid' => $element->userId, 'username' => $element->userName); }, $users);
        array_unshift($users, array('userid' => '', 'username' => 'All'));

        $data = [
            'users' => $users,
            'defaults' => [
                'layout' => $layout,
                'tags' => $tags,
                'owner' => $owner,
                'retired' => $retired,
                'filterLayoutStatusId' => $filterLayoutStatusId,
                'showDescriptionId' => $showDescriptionId,
                'showTags' => $showTags,
                'showThumbnail' => $showThumbnail,
                'filterPinned' => $pinned
            ]
        ];

        // Call to render the template
        $this->getState()->template = 'layout-page';
        $this->getState()->setData($data);
    }

    /**
     * Display the Layout Designer
     * @param int $layoutId
     */
    public function displayDesigner($layoutId)
    {
        $layout = LayoutFactory::loadById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Set up any JavaScript translations
        $data = [
            'layout' => $layout,
            'layouts' => LayoutFactory::query(),
            'zoom' => Sanitize::getDouble('zoom', 1)
        ];

        // Call the render the template
        $this->getState()->template = 'layout-designer-page';
        $this->getState()->setData($data);
    }

    /**
     * Add a Layout
     */
    function add()
    {
        $name = Sanitize::getString('name');
        $description = Sanitize::getString('description');
        $templateId = Sanitize::getInt('layoutId');
        $resolutionId = Sanitize::getInt('resolutionId');

        if ($templateId != 0)
            $layout = LayoutFactory::createFromTemplate($templateId, $this->getUser()->userId, $name, $description, Sanitize::getString('tags'));
        else
            $layout = LayoutFactory::createFromResolution($resolutionId, $this->getUser()->userId, $name, $description, Sanitize::getString('tags'));

        // Validate
        $layout->validate();

        // Save
        $layout->save();

        Log::debug('Layout Added');
        // TODO: Set the default permissions on the regions

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => [$layout]
        ]);
    }

    /**
     * Edit Layout
     * @param int $layoutId
     */
    function edit($layoutId)
    {
        $layout = LayoutFactory::loadById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $layout->layout = Sanitize::getString('name');
        $layout->description = Sanitize::getString('description');
        $layout->tags = TagFactory::tagsFromString(Sanitize::getString('tags'));
        $layout->retired = Sanitize::getCheckbox('retired');
        $layout->backgroundColor = Sanitize::getString('backgroundColor');
        $layout->backgroundImageId = Sanitize::getInt('backgroundImageId');
        $layout->backgroundzIndex = Sanitize::getInt('backgroundzIndex');

        // Resolution
        $resolution = ResolutionFactory::getById(Sanitize::getInt('resolutionId'));
        $layout->width = $resolution->width;
        $layout->height = $resolution->height;

        // Validate
        $layout->validate();

        // Save
        $layout->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => [$layout]
        ]);
    }

    /**
     * Delete Layout Form
     * @param int $layoutId
     */
    function deleteForm($layoutId)
    {
        $layout = LayoutFactory::getById($layoutId);

        if (!$this->getUser()->checkDeleteable($layout))
            throw new AccessDeniedException(__('You do not have permissions to delete this layout'));

        $data = [
            'layout' => $layout,
            'help' => [
                'delete' => Help::Link('Layout', 'Delete')
            ]
        ];

        $this->getState()->template = 'layout-form-delete';
        $this->getState()->setData($data);
    }

    /**
     * Retire Layout Form
     * @param int $layoutId
     */
    public function retireForm($layoutId)
    {
        $layout = LayoutFactory::getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $data = [
            'layout' => $layout,
            'help' => [
                'delete' => Help::Link('Layout', 'Retire')
            ]
        ];

        $this->getState()->template = 'layout-form-retire';
        $this->getState()->setData($data);
    }

    /**
     * Deletes a layout
     * @param int $layoutId
     */
    function delete($layoutId)
    {
        $layout = LayoutFactory::loadById($layoutId);

        if (!$this->getUser()->checkDeleteable($layout))
            throw new AccessDeniedException(__('You do not have permissions to delete this layout'));

        $layout->delete();
    }

    /**
     * Retires a layout
     * @param int $layoutId
     */
    function retire($layoutId)
    {
        $layout = LayoutFactory::loadById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $layout->retired = 1;
        $layout->save();
    }

    /**
     * Shows the Layout Grid
     */
    function grid()
    {
        $this->getState()->template = 'grid';

        // Filter by Name
        $name = Sanitize::getString('filter_layout');
        Session::Set('layout', 'filter_layout', $name);

        // User ID
        $filter_userid = Sanitize::getInt('filter_userid');
        Session::Set('layout', 'filter_userid', $filter_userid);

        // Show retired
        $filter_retired = Sanitize::getInt('filter_retired');
        Session::Set('layout', 'filter_retired', $filter_retired);

        // Show filterLayoutStatusId
        $filterLayoutStatusId = Sanitize::getInt('filterLayoutStatusId');
        Session::Set('layout', 'filterLayoutStatusId', $filterLayoutStatusId);

        // Show showDescriptionId
        $showDescriptionId = Sanitize::getInt('showDescriptionId');
        Session::Set('layout', 'showDescriptionId', $showDescriptionId);

        // Show filter_showThumbnail
        $showTags = Sanitize::getCheckbox('showTags');
        Session::Set('layout', 'showTags', $showTags);

        // Show filter_showThumbnail
        $showThumbnail = Sanitize::getCheckbox('showThumbnail');
        Session::Set('layout', 'showThumbnail', $showThumbnail);

        // Tags list
        $filter_tags = Sanitize::getString('filter_tags');
        Session::Set('layout', 'filter_tags', $filter_tags);

        // Pinned option?
        Session::Set('layout', 'LayoutFilter', Sanitize::getCheckbox('XiboFilterPinned'));

        // Get all layouts
        $layouts = LayoutFactory::query($this->gridRenderSort(), $this->gridRenderFilter([
            'layout' => $name,
            'userId' => $filter_userid,
            'retired' => $filter_retired,
            'tags' => $filter_tags,
            'filterLayoutStatusId' => $filterLayoutStatusId,
            'showTags' => $showTags
        ]));

        foreach ($layouts as $layout) {
            /* @var \Xibo\Entity\Layout $layout */

            if ($this->isApi())
                break;

            $layout->includeProperty('buttons');

            $layout->thumbnail = '';

            if ($layout->backgroundImageId != 0) {
                $download = $this->urlFor('library.download', ['id' => $layout->backgroundImageId]) . '?preview=1';
                $layout->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56" /></i></a>';
            }

            // Fix up the description
            $layout->descriptionFormatted = $layout->description;

            if ($layout->description != '') {
                if ($showDescriptionId == 1) {
                    // Parse down for description
                    $layout->descriptionFormatted = Parsedown::instance()->text($layout->description);
                } else if ($showDescriptionId == 2) {
                    $layout->descriptionFormatted = strtok($layout->description, "\n");
                }
            }

            switch ($layout->status) {

                case 1:
                    $layout->status = 1;
                    $layout->statusDescription = __('This Layout is ready to play');
                    break;

                case 2:
                    $layout->status = 2;
                    $layout->statusDescription = __('There are items on this Layout that can only be assessed by the Display');
                    break;

                case 3:
                    $layout->status = 0;
                    $layout->statusDescription = __('This Layout is invalid and should not be scheduled');
                    break;

                default:
                    $layout->status = 0;
                    $layout->statusDescription = __('The Status of this Layout is not known');
            }

            // Add some buttons for this row
            if ($this->getUser()->checkEditable($layout)) {
                // Design Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_design',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor('layout.designer', array('id' => $layout->layoutId)),
                    'text' => __('Design')
                );
            }

            // Preview
            $layout->buttons[] = array(
                'id' => 'layout_button_preview',
                'linkType' => '_blank',
                'external' => true,
                'url' => $this->urlFor('layout.preview', ['id' => $layout->layoutId]),
                'text' => __('Preview Layout')
            );

            // Schedule Now
            $layout->buttons[] = array(
                'id' => 'layout_button_schedulenow',
                'url' => $this->urlFor('schedule.now.form', ['id' => $layout->campaignId, 'from' => 'Campaign']),
                'text' => __('Schedule Now')
            );

            $layout->buttons[] = ['divider' => true];

            // Only proceed if we have edit permissions
            if ($this->getUser()->checkEditable($layout)) {

                // Edit Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_edit',
                    'url' => $this->urlFor('layout.edit.form', ['id' => $layout->layoutId]),
                    'text' => __('Edit')
                );

                // Copy Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_copy',
                    'url' => $this->urlFor('layout.copy.form', ['id' => $layout->layoutId]) . '?oldlayout=' . urlencode($layout->layout),
                    'text' => __('Copy')
                );

                // Retire Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_retire',
                    'url' => $this->urlFor('layout.retire.form', ['id' => $layout->layoutId]),
                    'text' => __('Retire'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'multiselectlink', 'value' => 'index.php?p=layout&q=Retire'),
                        array('name' => 'id', 'value' => 'layout_button_retire'),
                        array('name' => 'text', 'value' => __('Retire')),
                        array('name' => 'rowtitle', 'value' => $layout->layout),
                        array('name' => 'layoutid', 'value' => $layout->layoutId)
                    )
                );

                // Extra buttons if have delete permissions
                if ($this->getUser()->checkDeleteable($layout)) {
                    // Delete Button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_delete',
                        'url' => $this->urlFor('layout.delete.form', ['id' => $layout->layoutId]),
                        'text' => __('Delete'),
                        'multi-select' => true,
                        'dataAttributes' => array(
                            array('name' => 'multiselectlink', 'value' => 'index.php?p=layout&q=delete'),
                            array('name' => 'id', 'value' => 'layout_button_delete'),
                            array('name' => 'text', 'value' => __('Delete')),
                            array('name' => 'rowtitle', 'value' => $layout->layout),
                            array('name' => 'layoutid', 'value' => $layout->layoutId)
                        )
                    );
                }

                $layout->buttons[] = ['divider' => true];

                // Export Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_export',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor('layout.export', ['id' => $layout->layoutId]),
                    'text' => __('Export')
                );

                // Extra buttons if we have modify permissions
                if ($this->getUser()->checkPermissionsModifyable($layout)) {
                    // Permissions button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_permissions',
                        'url' => $this->urlFor('user.permissions.form', ['entity' => 'Campaign', 'id' => $layout->campaignId]),
                        'text' => __('Permissions')
                    );
                }
            }
        }

        // Store the table rows
        $this->getState()->setData($layouts);
    }

    /**
     * Displays an Add/Edit form
     */
    function addForm()
    {
        $this->getState()->template = 'layout-form-add';
        $this->getState()->setData([
            'layouts' => LayoutFactory::query(['layout'], ['excludeTemplates' => 0, 'tags' => 'template']),
            'resolutions' => ResolutionFactory::query(['resolution']),
            'help' => Help::Link('Layout', 'Add')
        ]);
    }

    /**
     * Edit form
     * @param int $layoutId
     */
    function editForm($layoutId)
    {
        // Get the layout
        $layout = LayoutFactory::getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $resolution = ResolutionFactory::getByDimensions($layout->width, $layout->height);

        $this->getState()->template = 'layout-form-edit';
        $this->getState()->setData([
            'layout' => $layout,
            'resolution' => $resolution,
            'resolutions' => ResolutionFactory::query(['resolution'], ['withCurrent' => $resolution->resolutionId]),
            'backgroundId' => Sanitize::getInt('backgroundOveride', $layout->backgroundImageId),
            'backgrounds' => MediaFactory::query(null, ['type' => 'image']),
            'help' => Help::Link('Layout', 'Edit')
        ]);
    }

    /**
     * Copy layout form
     * @param int $layoutId
     */
    public function copyForm($layoutId)
    {
        // Get the layout
        $layout = LayoutFactory::getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        $this->getState()->template = 'layout-form-copy';
        $this->getState()->setData([
            'layout' => $layout,
            'help' => Help::Link('Layout', 'Copy')
        ]);
    }

    /**
     * Copies a layout
     * @param int $layoutId
     */
    public function copy($layoutId)
    {
        // Get the layout
        $layout = LayoutFactory::getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        // Load the layout for Copy
        $layout->load();
        $layout = clone $layout;

        $layout->layout = Sanitize::getString('name');
        $layout->description = Sanitize::getString('description');

        // Validate the new layout
        $layout->validate();

        // TODO: Copy the media on the layout and change the assignments.
        if (Sanitize::getCheckbox('copyMediaFiles') == 1) {

        }

        // Save the new layout
        $layout->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Copied as %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => [$layout]
        ]);
    }

    /**
     * Layout Status
     * @param int $layoutId
     */
    public function status($layoutId)
    {
        // Get the layout
        $layout = LayoutFactory::getById($layoutId);

        switch ($layout->status) {

            case 1:
                $status = '<span title="' . __('This Layout is ready to play') . '" class="glyphicon glyphicon-ok-circle"></span>';
                break;

            case 2:
                $status = '<span title="' . __('There are items on this Layout that can only be assessed by the client') . '" class="glyphicon glyphicon-question-sign"></span>';
                break;

            case 3:
                $status = '<span title="' . __('This Layout is invalid and should not be scheduled') . '" class="glyphicon glyphicon-remove-sign"></span>';
                break;

            default:
                $status = '<span title="' . __('The Status of this Layout is not known') . '" class="glyphicon glyphicon-warning-sign"></span>';
        }

        // Keep things tidy
        // Maintenance should also do this.
        Library::removeExpiredFiles();

        $this->getState()->html = $status;
        $this->getState()->success = true;
    }

    /**
     * @param int $layoutId
     */
    public function export($layoutId)
    {
        $this->setNoOutput(true);

        // Get the layout
        $layout = LayoutFactory::getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        $fileName = Config::GetSetting('LIBRARY_LOCATION') . 'temp/export_' . $layout->layout . '.zip';
        $layout->toZip($fileName);

        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($fileName) . "\"");
        header('Content-Length: ' . filesize($fileName));

        // Send via Apache X-Sendfile header?
        if (Config::GetSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $fileName");
            exit();
        }
        // Send via Nginx X-Accel-Redirect?
        if (Config::GetSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/temp/" . basename($fileName));
            exit();
        }

        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        readfile($fileName);
    }

    public function ImportForm()
    {
        global $session;

         

        // Set the Session / Security information
        $sessionId = session_id();
        $securityToken = CreateFormToken();

        $session->setSecurityToken($securityToken);

        // Find the max file size
        $maxFileSizeBytes = convertBytes(ini_get('upload_max_filesize'));

        // Set some information about the form
        Theme::Set('form_id', 'LayoutImportForm');
        Theme::Set('form_action', 'index.php?p=layout&q=Import');
        Theme::Set('form_meta', '<input type="hidden" id="txtFileName" name="txtFileName" readonly="true" /><input type="hidden" name="hidFileID" id="hidFileID" value="" /><input type="hidden" name="template" value="' . \Kit::GetParam('template', _GET, _STRING, 'false') . '" />');

        Theme::Set('form_upload_id', 'file_upload');
        Theme::Set('form_upload_action', 'index.php?p=content&q=FileUpload');
        Theme::Set('form_upload_meta', '<input type="hidden" id="PHPSESSID" value="' . $sessionId . '" /><input type="hidden" id="SecurityToken" value="' . $securityToken . '" /><input type="hidden" name="MAX_FILE_SIZE" value="' . $maxFileSizeBytes . '" />');

        Theme::Set('prepend', Theme::RenderReturn('form_file_upload_single'));

        $formFields = array();
        $formFields[] = Form::AddText('layout', __('Name'), NULL, __('The Name of the Layout - (1 - 50 characters). Leave blank to use the name from the import.'), 'n');
        $formFields[] = Form::AddCheckbox('replaceExisting', __('Replace Existing Media?'),
            NULL,
            __('If the import finds existing media with the same name, should it be replaced in the Layout or should the Layout use that media.'),
            'r');

        if (\Kit::GetParam('template', _GET, _STRING, 'false') != 'true')
            $formFields[] = Form::AddCheckbox('importTags', __('Import Tags?'),
                NULL,
                __('Would you like to import any tags contained on the layout.'),
                't');

        Theme::Set('form_fields', $formFields);

         $this->getState()->SetFormRequestResponse(NULL, __('Import Layout'), '350px', '200px');
         $this->getState()->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DataSet', 'ImportCsv') . '")');
         $this->getState()->AddButton(__('Cancel'), 'XiboDialogClose()');
         $this->getState()->AddButton(__('Import'), '$("#LayoutImportForm").submit()');

    }

    public function Import()
    {
        // What are we importing?
        $template = \Kit::GetParam('template', _POST, _STRING, 'false');
        $template = ($template == 'true');

        $layout = Sanitize::getString('layout');
        $replaceExisting = Sanitize::getCheckbox('replaceExisting');
        $importTags = \Kit::GetParam('importTags', _POST, _CHECKBOX, (!$template));

        // File data
        $tmpName = Sanitize::getString('hidFileID');

        if ($tmpName == '')
            trigger_error(__('Please ensure you have picked a file and it has finished uploading'), E_USER_ERROR);

        // File name and extension (original name)
        $fileName = Sanitize::getString('txtFileName');
        $fileName = basename($fileName);
        $ext = strtolower(substr(strrchr($fileName, "."), 1));

        // File upload directory.. get this from the settings object
        $fileLocation = Config::GetSetting('LIBRARY_LOCATION') . 'temp/' . $tmpName;


        $layoutObject = new Layout($this->db);

        if (!$layoutObject->Import($fileLocation, $layout, $this->getUser()->userId, $template, $replaceExisting, $importTags)) {
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);
        }

         $this->getState()->SetFormSubmitResponse(__('Layout Imported'));
    }
}
