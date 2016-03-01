<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Entity\Layout;
use Xibo\Entity\Permission;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetFactory;

class XiboUploadHandler extends BlueImpUploadHandler
{
    protected function handle_form_data($file, $index)
    {
        $controller = $this->options['controller'];
        /* @var \Xibo\Controller\Library $controller */

        // Handle form data, e.g. $_REQUEST['description'][$index]
        // Link the file to the module
        $fileName = $file->name;
        $filePath = $controller->getConfig()->GetSetting('LIBRARY_LOCATION') . 'temp/' . $fileName;

        $controller->getLog()->debug('Upload complete for name: ' . $fileName . '. Index is %s.', $index);

        // Upload and Save
        try {
            // Get some parameters
            if ($index === null) {
                if (!isset($_REQUEST['name']))
                    throw new \InvalidArgumentException(__('Missing Name Parameter'));

                $name = $_REQUEST['name'];
            }
            else {
                if (!isset($_REQUEST['name'][$index]))
                    throw new \InvalidArgumentException(__('Missing Name Parameter'));

                $name = $_REQUEST['name'][$index];
            }

            // Guess the type
            $module = (new ModuleFactory($controller->getContainer()))->getByExtension(strtolower(substr(strrchr($fileName, '.'), 1)));
            $module = (new ModuleFactory($controller->getContainer()))->create($module->type);

            $controller->getLog()->debug('Module Type = %s', $module->getModuleType());

            // Do we need to run any pre-processing on the file?
            $module->preProcess($filePath);

            // Old Media Id or not?
            if ($this->options['oldMediaId'] != 0) {

                $updateInLayouts = ($this->options['updateInLayouts'] == 1);
                $deleteOldRevisions = ($this->options['deleteOldRevisions'] == 1);

                $controller->getLog()->debug('Replacing old with new - updateInLayouts = %d, deleteOldRevisions = %d', $updateInLayouts, $deleteOldRevisions);

                // Load old media
                $oldMedia = (new MediaFactory($controller->getContainer()))->getById($this->options['oldMediaId']);

                // Check permissions
                if (!$controller->getUser()->checkEditable($oldMedia))
                    throw new AccessDeniedException(__('Access denied replacing old media'));

                // Set the old record to edited
                $oldMedia->isEdited = 1;
                $oldMedia->save(['validate' => false]);

                // The media name might be empty here, because the user isn't forced to select it
                $name = ($name == '') ? $oldMedia->name : $name;

                // Add the Media
                $media = (new MediaFactory($controller->getContainer()))->create($name, $fileName, $module->getModuleType(), $this->options['userId']);

                // Set the duration
                $media->duration = $module->determineDuration($filePath);

                // Save
                $media->save(['oldMedia' => $oldMedia]);

                $controller->getLog()->debug('Copying permissions to new media');

                foreach ((new PermissionFactory($controller->getContainer()))->getAllByObjectId(get_class($oldMedia), $oldMedia->mediaId) as $permission) {
                    /* @var Permission $permission */
                    $permission = clone $permission;
                    $permission->objectId = $media->mediaId;
                    $permission->save();
                }

                // Do we want to replace this in all layouts?
                if ($updateInLayouts) {
                    $controller->getLog()->debug('Replace in all Layouts selected. Getting associated widgets');

                    foreach ((new WidgetFactory($controller->getContainer()))->getByMediaId($oldMedia->mediaId) as $widget) {
                        /* @var Widget $widget */
                        if ($controller->getUser()->checkEditable($widget)) {
                            // Widget that we cannot update, this means we can't delete the original mediaId when it comes time to do so.
                            $deleteOldRevisions = false;

                            $controller->getLog()->info('Media used on Widget that we cannot edit. Delete Old Revisions has been disabled.');
                        }

                        $controller->getLog()->debug('Found widget that needs updating. ID = %d. Linking %d', $widget->getId(), $media->mediaId);
                        $widget->unassignMedia($oldMedia->mediaId);
                        $widget->assignMedia($media->mediaId);
                        $widget->save();
                    }

                    // Update any background images
                    if ($media->mediaType == 'image') {
                        $controller->getLog()->debug('Updating layouts with the old media %d as the background image.', $oldMedia->mediaId);
                        // Get all Layouts with this as the background image
                        foreach ((new LayoutFactory($controller->getContainer()))->query(null, ['disableUserCheck' => 1, 'backgroundImageId' => $oldMedia->mediaId]) as $layout) {
                            /* @var Layout $layout */

                            if ($controller->getUser()->checkEditable($layout)) {
                                // Widget that we cannot update, this means we can't delete the original mediaId when it comes time to do so.
                                $deleteOldRevisions = false;

                                $controller->getLog()->info('Media used on Widget that we cannot edit. Delete Old Revisions has been disabled.');
                            }

                            $controller->getLog()->debug('Found layout that needs updating. ID = %d. Setting background image id to %d', $layout->layoutId, $media->mediaId);
                            $layout->backgroundImageId = $media->mediaId;
                            $layout->save();
                        }
                    }

                } else if ($this->options['widgetId'] != 0) {
                    $controller->getLog()->debug('Swapping a specific widget only.');
                    // swap this one
                    $widget = (new WidgetFactory($controller->getContainer()))->getById($this->options['widgetId']);

                    if (!$controller->getUser()->checkEditable($widget))
                        throw new AccessDeniedException();

                    $widget->unassignMedia($oldMedia->mediaId);
                    $widget->assignMedia($media->mediaId);
                    $widget->save();
                }

                // We either want to Link the old record to this one, or delete it
                if ($updateInLayouts && $deleteOldRevisions) {

                    // Check we have permission to delete this media
                    if (!$controller->getUser()->checkDeleteable($oldMedia))
                        throw new AccessDeniedException();

                    try {
                        // Join the prior revision up with the new media.
                        $priorMedia = (new MediaFactory($controller->getContainer()))->getParentById($oldMedia->mediaId);
                        $priorMedia->parentId = $media->mediaId;
                        $priorMedia->save(['validate' => false]);
                    }
                    catch (NotFoundException $e) {
                        // Nothing to do then
                    }

                    $oldMedia->delete();

                } else {
                    $oldMedia->parentId = $media->mediaId;
                    $oldMedia->save(['validate' => false]);
                }

            } else {

                // The media name might be empty here, because the user isn't forced to select it
                $name = ($name == '') ? $fileName : $name;

                // Add the Media
                $media = (new MediaFactory($controller->getContainer()))->create($name, $fileName, $module->getModuleType(), $this->options['userId']);

                // Set the duration
                $media->duration = $module->determineDuration($filePath);

                // Save
                $media->save();

                // Permissions
                foreach ((new PermissionFactory($controller->getContainer()))->createForNewEntity($controller->getUser(), get_class($media), $media->getId(), $controller->getConfig()->GetSetting('MEDIA_DEFAULT')) as $permission) {
                    /* @var Permission $permission */
                    $permission->save();
                }
            }

            // Set the name to the one we have selected
            $file->name = $name;

            // Get the storedAs valid for return
            $file->storedas = $media->storedAs;

            // Fonts, then install
            if ($module->getModuleType() == 'font') {
                $controller->installFonts();
            }

            // Are we assigning to a Playlist?
            if ($this->options['playlistId'] != 0 && $this->options['widgetId'] == 0) {

                $controller->getLog()->debug('Assigning uploaded media to playlistId ' . $this->options['playlistId']);

                // Get the Playlist
                $playlist = (new PlaylistFactory($controller->getContainer()))->getById($this->options['playlistId']);

                // Create a Widget and add it to our region
                $widget = (new WidgetFactory($controller->getContainer()))->create($this->options['userId'], $playlist->playlistId, $module->getModuleType(), $media->duration);

                // Assign the widget to the module
                $module->setWidget($widget);

                // Set default options (this sets options on the widget)
                $module->setDefaultWidgetOptions();

                // Assign media
                $widget->assignMedia($media->mediaId);

                // Assign the new widget to the playlist
                $playlist->assignWidget($widget);

                // Save the playlist
                $playlist->save();
            }
        } catch (Exception $e) {
            $controller->getLog()->error('Error uploading media: %s', $e->getMessage());
            $controller->getLog()->debug($e->getTraceAsString());

            $file->error = $e->getMessage();

            $this->options['controller']->getApp()->commit = false;
        }
    }
}