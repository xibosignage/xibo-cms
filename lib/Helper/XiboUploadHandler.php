<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Entity\Layout;
use Xibo\Entity\Permission;
use Xibo\Entity\Widget;
use Xibo\Event\LibraryReplaceEvent;
use Xibo\Event\LibraryReplaceWidgetEvent;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\LibraryFullException;
use Xibo\Exception\NotFoundException;

/**
 * Class XiboUploadHandler
 * @package Xibo\Helper
 */
class XiboUploadHandler extends BlueImpUploadHandler
{
    /**
     * Handle form data from BlueImp
     * @param $file
     * @param $index
     */
    protected function handle_form_data($file, $index)
    {
        $controller = $this->options['controller'];
        /* @var \Xibo\Controller\Library $controller */

        // Handle form data, e.g. $_REQUEST['description'][$index]
        // Link the file to the module
        $fileName = $file->name;
        $filePath = $controller->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $fileName;

        $controller->getLog()->debug('Upload complete for name: ' . $fileName . '. Index is %s.', $index);

        // Upload and Save
        try {

            // Check Library
            if ($this->options['libraryQuotaFull'])
                throw new LibraryFullException(sprintf(__('Your library is full. Library Limit: %s K'), $this->options['libraryLimit']));

            // Check for a user quota
            // this method has the ability to reconnect to MySQL in the event that the upload has taken a long time.
            // OSX-381
            $controller->getUser()->isQuotaFullByUser(true);

            // Get some parameters
            if ($index === null) {
                if (isset($_REQUEST['name'])) {
                    $name = $_REQUEST['name'];
                } else {
                    $name = $fileName;
                }

                if (isset($_REQUEST['tags'])) {
                    $tags = $_REQUEST['tags'];
                } else {
                    $tags = '';
                }
            } else {
                if (isset($_REQUEST['name'][$index])) {
                    $name = $_REQUEST['name'][$index];
                } else {
                    $name = $fileName;
                }

                if (isset($_REQUEST['tags'][$index])) {
                    $tags = $_REQUEST['tags'][$index];
                } else {
                    $tags = '';
                }
            }
            // Guess the type
            $module = $controller->getModuleFactory()->getByExtension(strtolower(substr(strrchr($fileName, '.'), 1)));
            $module = $controller->getModuleFactory()->create($module->type);

            $controller->getLog()->debug('Module Type = %s, Name = %s', $module->getModuleType(), $module->getModuleName());

            // Do we need to run any pre-processing on the file?
            $module->preProcessFile($filePath);

            // Old Media Id or not?
            if ($this->options['oldMediaId'] != 0) {

                $updateInLayouts = ($this->options['updateInLayouts'] == 1);
                $deleteOldRevisions = ($this->options['deleteOldRevisions'] == 1);

                $controller->getLog()->debug('Replacing old with new - updateInLayouts = %d, deleteOldRevisions = %d', $updateInLayouts, $deleteOldRevisions);

                // Load old media
                $oldMedia = $controller->getMediaFactory()->getById($this->options['oldMediaId']);

                // Check permissions
                if (!$controller->getUser()->checkEditable($oldMedia))
                    throw new AccessDeniedException(__('Access denied replacing old media'));

                // Check to see if we are changing the media type
                if ($oldMedia->mediaType != $module->getModuleType() && $this->options['allowMediaTypeChange'] == 0)
                    throw new \InvalidArgumentException(__('You cannot replace this media with an item of a different type'));

                // Set the old record to edited
                $oldMedia->isEdited = 1;

                $oldMedia->save(['validate' => false]);

                // The media name might be empty here, because the user isn't forced to select it
                $name = ($name == '') ? $oldMedia->name : $name;
                $tags = ($tags == '') ? '' : $tags;


                // Add the Media
                //  the userId is either the existing user (if we are changing media type) or the currently logged in user otherwise.
                $media = $controller->getMediaFactory()->create(
                    $name,
                    $fileName,
                    $module->getModuleType(),
                    $oldMedia->getOwnerId()
                );

                if ($tags != '') {
                    $concatTags = (string)$oldMedia->tags . ',' . $tags;
                    $media->replaceTags($controller->getTagFactory()->tagsFromString($concatTags));
                }

                // Set the duration
                if ($oldMedia->mediaType != 'video' && $media->mediaType != 'video')
                    $media->duration = $oldMedia->duration;
                else
                    $media->duration = $module->determineDuration($filePath);

                // Pre-process
                $module->preProcess($media, $filePath);

                // Raise an event for this media item
                $controller->getDispatcher()->dispatch(LibraryReplaceEvent::$NAME, new LibraryReplaceEvent($module, $media, $oldMedia));

                $media->enableStat = $oldMedia->enableStat;
                $media->expires = $this->options['expires'];

                // Save
                $media->save(['oldMedia' => $oldMedia]);

                // Post process
                $module->postProcess($media);

                $controller->getLog()->debug('Copying permissions to new media');

                foreach ($controller->getPermissionFactory()->getAllByObjectId($controller->getUser(), get_class($oldMedia), $oldMedia->mediaId) as $permission) {
                    /* @var Permission $permission */
                    $permission = clone $permission;
                    $permission->objectId = $media->mediaId;
                    $permission->save();
                }

                // Do we want to replace this in all layouts?
                if ($updateInLayouts) {
                    $controller->getLog()->debug('Replace in all Layouts selected. Getting associated widgets');

                    foreach ($controller->getWidgetFactory()->getByMediaId($oldMedia->mediaId) as $widget) {
                        /* @var Widget $widget */
                        if (!$controller->getUser()->checkEditable($widget)) {
                            // Widget that we cannot update, this means we can't delete the original mediaId when it comes time to do so.
                            $deleteOldRevisions = false;

                            $controller->getLog()->info('Media used on Widget that we cannot edit. Delete Old Revisions has been disabled.');
                        }

                        // If we are replacing an audio media item, we should check to see if the widget we've found has any
                        // audio items assigned.
                        if ($module->getModuleType() == 'audio' && in_array($oldMedia->mediaId, $widget->getAudioIds())) {

                            $controller->getLog()->debug('Found audio on widget that needs updating. widgetId = ' . $widget->getId() . '. Linking ' . $media->mediaId);
                            $widget->unassignAudioById($oldMedia->mediaId);
                            $widget->assignAudioById($media->mediaId);
                            $widget->save();

                        } else if (count($widget->getPrimaryMedia()) > 0 && $widget->getPrimaryMediaId() == $oldMedia->mediaId) {
                            // We're only interested in primary media at this point (no audio)
                            // Check whether this widget is of the same type as our incoming media item
                            if ($widget->type != $module->getModuleType()) {
                                // Are we supposed to switch, or should we prevent?
                                if ($this->options['allowMediaTypeChange'] == 1) {
                                    $widget->type = $module->getModuleType();
                                } else {
                                    throw new \InvalidArgumentException(__('You cannot replace this media with an item of a different type'));
                                }
                            }

                            $controller->getLog()->debug('Found widget that needs updating. ID = %d. Linking %d', $widget->getId(), $media->mediaId);
                            $widget->unassignMedia($oldMedia->mediaId);
                            $widget->assignMedia($media->mediaId);

                            // calculate duration
                            $module->setWidget($widget);
                            $widget->calculateDuration($module);

                            // Raise an event for this media item
                            $controller->getDispatcher()->dispatch(LibraryReplaceWidgetEvent::$NAME, new LibraryReplaceWidgetEvent($module, $widget, $media, $oldMedia));

                            // Save
                            $widget->save(['alwaysUpdate' => true]);
                        }
                    }

                    // Update any background images
                    if ($media->mediaType == 'image') {
                        $controller->getLog()->debug('Updating layouts with the old media %d as the background image.', $oldMedia->mediaId);
                        // Get all Layouts with this as the background image
                        foreach ($controller->getLayoutFactory()->query(null, ['disableUserCheck' => 1, 'backgroundImageId' => $oldMedia->mediaId]) as $layout) {
                            /* @var Layout $layout */

                            if (!$controller->getUser()->checkEditable($layout)) {
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
                    $widget = $controller->getWidgetFactory()->getById($this->options['widgetId']);

                    if (!$controller->getUser()->checkEditable($widget))
                        throw new AccessDeniedException();

                    $widget->unassignMedia($oldMedia->mediaId);
                    $widget->assignMedia($media->mediaId);
                    $widget->save();
                }

                // We either want to Link the old record to this one, or delete it
                if ($updateInLayouts && $deleteOldRevisions) {

                    $controller->getLog()->debug('Delete old revisions of ' . $oldMedia->mediaId);

                    // Check we have permission to delete this media
                    if (!$controller->getUser()->checkDeleteable($oldMedia))
                        throw new AccessDeniedException();

                    try {
                        // Join the prior revision up with the new media.
                        $priorMedia = $controller->getMediaFactory()->getParentById($oldMedia->mediaId);

                        $controller->getLog()->debug('Prior media found, joining ' . $priorMedia->mediaId . ' with ' . $media->mediaId);

                        $priorMedia->parentId = $media->mediaId;
                        $priorMedia->save(['validate' => false]);
                    }
                    catch (NotFoundException $e) {
                        // Nothing to do then
                        $controller->getLog()->debug('No prior media found');
                    }

                    $oldMedia->setChildObjectDependencies($controller->getLayoutFactory(), $controller->getWidgetFactory(), $controller->getDisplayGroupFactory(), $controller->getDisplayFactory(), $controller->getScheduleFactory(), $controller->getPlayerVersionFactory());
                    $oldMedia->delete();

                } else {
                    $oldMedia->parentId = $media->mediaId;
                    $oldMedia->save(['validate' => false]);
                }

            } else {

                // The media name might be empty here, because the user isn't forced to select it
                $name = ($name == '') ? $fileName : $name;
                $tags = ($tags == '') ? '' : $tags;

                // Add the Media
                $media = $controller->getMediaFactory()->create($name, $fileName, $module->getModuleType(), $this->options['userId']);
                if ($tags != '') {
                    $media->replaceTags($controller->getTagFactory()->tagsFromString($tags));
                }
                // Set the duration
                $media->duration = $module->determineDuration($filePath);

                // Pre-process
                $module->preProcess($media, $filePath);

                if ($media->enableStat == null) {
                    $media->enableStat = $controller->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT');
                }

                $media->expires = $this->options['expires'];

                // Save
                $media->save();

                // Post process
                $module->postProcess($media);

                // Permissions
                foreach ($controller->getPermissionFactory()->createForNewEntity($controller->getUser(), get_class($media), $media->getId(), $controller->getConfig()->getSetting('MEDIA_DEFAULT'), $controller->getUserGroupFactory()) as $permission) {
                    /* @var Permission $permission */
                    $permission->save();
                }
            }

            // Configure the return values according to the media item we've added
            $file->name = $name;
            $file->mediaId = $media->mediaId;
            $file->storedas = $media->storedAs;
            $file->duration = $media->duration;
            $file->retired = $media->retired;
            $file->fileSize = $media->fileSize;
            $file->md5 = $media->md5;
            $file->enableStat = $media->enableStat;
            $file->mediaType = $module->getModuleType();

            // Test to ensure the final file size is the same as the file size we're expecting
            if ($file->fileSize != $file->size)
                throw new InvalidArgumentException(__('Sorry this is a corrupted upload, the file size doesn\'t match what we\'re expecting.'), 'size');

            // Fonts, then install
            if ($module->getModuleType() == 'font') {
                $controller->installFonts();
            }

            // Are we assigning to a Playlist?
            if ($this->options['playlistId'] != 0 && $this->options['widgetId'] == 0) {

                $controller->getLog()->debug('Assigning uploaded media to playlistId ' . $this->options['playlistId']);

                // Get the Playlist
                $playlist = $controller->getPlaylistFactory()->getById($this->options['playlistId']);

                // Create a Widget and add it to our region
                $widget = $controller->getWidgetFactory()->create($this->options['userId'], $playlist->playlistId, $module->getModuleType(), $media->duration);

                // Assign the widget to the module
                $module->setWidget($widget);

                // Set default options (this sets options on the widget)
                $module->setDefaultWidgetOptions();

                // Assign media
                $widget->assignMedia($media->mediaId);

                // Calculate the widget duration for new uploaded media widgets
                $widget->calculateDuration($module);

                // Assign the new widget to the playlist
                $playlist->assignWidget($widget, $this->options['displayOrder'] ?? null);

                // Save the playlist
                $playlist->save();

                // Configure widgetId is reponse
                $file->widgetId = $widget->widgetId;

                // Handle permissions
                // https://github.com/xibosignage/xibo/issues/1274
                if ($controller->getConfig()->getSetting('INHERIT_PARENT_PERMISSIONS') == 1) {
                    // Apply permissions from the Parent
                    foreach ($playlist->permissions as $permission) {
                        /* @var Permission $permission */
                        $permission = $controller->getPermissionFactory()->create($permission->groupId, get_class($widget), $widget->getId(), $permission->view, $permission->edit, $permission->delete);
                        $permission->save();
                    }
                } else {
                    foreach ($controller->getPermissionFactory()->createForNewEntity($controller->getUser(), get_class($widget), $widget->getId(), $controller->getConfig()->getSetting('LAYOUT_DEFAULT'), $controller->getUserGroupFactory()) as $permission) {
                        /* @var Permission $permission */
                        $permission->save();
                    }
                }
            }
        } catch (Exception $e) {
            $controller->getLog()->error('Error uploading media: %s', $e->getMessage());
            $controller->getLog()->debug($e->getTraceAsString());

            // Unlink the temporary file
            @unlink($filePath);

            $file->error = $e->getMessage();

            $controller->getApp()->commit = false;
        }
    }
}
