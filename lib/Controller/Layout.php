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
use Xibo\Exception\LibraryFullException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ResolutionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Help;
use Xibo\Helper\LayoutUploadHandler;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;

class Layout extends Base
{
    /**
     * Displays the Layout Page
     */
    function displayPage()
    {
        // Default options
        if ($this->getSession()->get('layout', 'Filter') == 1) {

            $layout = $this->getSession()->get('layout', 'filter_layout');
            $tags = $this->getSession()->get('layout', 'filter_tags');
            $retired = $this->getSession()->get('layout', 'filter_retired');
            $owner = $this->getSession()->get('layout', 'filter_userid');
            $filterLayoutStatusId = $this->getSession()->get('layout', 'filterLayoutStatusId');
            $showDescriptionId = $this->getSession()->get('layout', 'showDescriptionId');
            $showThumbnail = $this->getSession()->get('layout', 'showThumbnail');
            $showTags = $this->getSession()->get('layout', 'showTags');
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

        $data = [
            // Users we have permission to see
            'users' => UserFactory::query(),
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
        $layout = LayoutFactory::getById($layoutId);

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
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => false,
            'setBuildRequired' => false
        ]);

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

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $layout->layout)
        ]);
    }

    /**
     * Retires a layout
     * @param int $layoutId
     */
    function retire($layoutId)
    {
        $layout = LayoutFactory::getById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $layout->retired = 1;
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => false,
            'setBuildRequired' => false
        ]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Retired %s'), $layout->layout)
        ]);
    }

    /**
     * Shows the Layout Grid
     */
    function grid()
    {
        $this->getState()->template = 'grid';

        // Filter by Name
        $name = Sanitize::getString('filter_layout');
        $this->getSession()->set('layout', 'filter_layout', $name);

        // User ID
        $filter_userid = Sanitize::getInt('filter_userid');
        $this->getSession()->set('layout', 'filter_userid', $filter_userid);

        // Show retired
        $filter_retired = Sanitize::getInt('filter_retired');
        $this->getSession()->set('layout', 'filter_retired', $filter_retired);

        // Show filterLayoutStatusId
        $filterLayoutStatusId = Sanitize::getInt('filterLayoutStatusId');
        $this->getSession()->set('layout', 'filterLayoutStatusId', $filterLayoutStatusId);

        // Show showDescriptionId
        $showDescriptionId = Sanitize::getInt('showDescriptionId');
        $this->getSession()->set('layout', 'showDescriptionId', $showDescriptionId);

        // Show filter_showThumbnail
        $showTags = Sanitize::getCheckbox('showTags');
        $this->getSession()->set('layout', 'showTags', $showTags);

        // Show filter_showThumbnail
        $showThumbnail = Sanitize::getCheckbox('showThumbnail');
        $this->getSession()->set('layout', 'showThumbnail', $showThumbnail);

        // Tags list
        $filter_tags = Sanitize::getString('filter_tags');
        $this->getSession()->set('layout', 'filter_tags', $filter_tags);

        // Pinned option?
        $this->getSession()->set('layout', 'LayoutFilter', Sanitize::getCheckbox('XiboFilterPinned'));

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
                    $layout->statusDescription = __('This Layout is ready to play');
                    break;

                case 2:
                    $layout->statusDescription = __('There are items on this Layout that can only be assessed by the Display');
                    break;

                case 3:
                    $layout->statusDescription = __('This Layout has not been built yet');
                    break;

                default:
                    $layout->statusDescription = __('This Layout is invalid and should not be scheduled');
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
        $this->getState()->recordsTotal = LayoutFactory::countLast();
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

        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($fileName) . "\"");
        header('Content-Length: ' . filesize($fileName));

        // Send via Apache X-Sendfile header?
        if (Config::GetSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $fileName");
            $this->getApp()->halt(200);
        }
        // Send via Nginx X-Accel-Redirect?
        if (Config::GetSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/temp/" . basename($fileName));
            $this->getApp()->halt(200);
        }

        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        readfile($fileName);
    }

    public function import()
    {
        Log::debug('Import Layout');

        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        Library::ensureLibraryExists();

        $options = array(
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor('layout.import'),
            'upload_url' => $this->urlFor('layout.import'),
            'image_versions' => array(),
            'accept_file_types' => '/\.zip$/i'
        );

        // Make sure there is room in the library
        $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

        if ($libraryLimit > 0 && Library::libraryUsage() > $libraryLimit)
            throw new LibraryFullException(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit));

        // Check for a user quota
        $this->getUser()->isQuotaFullByUser();

        try {
            // Hand off to the Upload Handler provided by jquery-file-upload
            new LayoutUploadHandler($options);

        } catch (\Exception $e) {
            // We must not issue an error, the file upload return should have the error object already
            //TODO: for some reason this commits... it shouldn't
            $this->app->commit = false;
        }

        $this->setNoOutput(true);
    }
}
