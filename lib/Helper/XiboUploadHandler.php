<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Entity\Layout;
use Xibo\Entity\Permission;
use Xibo\Entity\Widget;
use Xibo\Event\LibraryReplaceEvent;
use Xibo\Event\LibraryReplaceWidgetEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\LibraryFullException;
use Xibo\Support\Exception\NotFoundException;

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

        $controller->getLog()->debug('Upload complete for name: ' . $fileName . '. Index is ' . $index);

        // Upload and Save
        try {
            // Check Library
            if ($this->options['libraryQuotaFull']) {
                throw new LibraryFullException(
                    sprintf(
                        __('Your library is full. Library Limit: %s K'),
                        $this->options['libraryLimit']
                    )
                );
            }
            // Check for a user quota
            // this method has the ability to reconnect to MySQL in the event that the upload has taken a long time.
            // OSX-381
            $controller->getUser()->isQuotaFullByUser(true);

            // Get some parameters
            $name = $this->getParam($index, 'name', $fileName);
            $tags = $controller->getUser()->featureEnabled('tag.tagging')
                ? $this->getParam($index, 'tags', '')
                : '';

            // Guess the type
            $module = $controller->getModuleFactory()->getByExtension(strtolower(substr(strrchr($fileName, '.'), 1)));
            $module = $controller->getModuleFactory()->create($module->type);
            $module->setUser($controller->getUser());

            $controller->getLog()->debug(sprintf(
                'Module Type = %s, Name = %s',
                $module->getModuleType(),
                $module->getModuleName()
            ));

            // Do we need to run any pre-processing on the file?
            $module->preProcessFile($filePath);

            // Old Media Id or not?
            if ($this->options['oldMediaId'] != 0) {
                $updateInLayouts = ($this->options['updateInLayouts'] == 1);
                $deleteOldRevisions = ($this->options['deleteOldRevisions'] == 1);

                $controller->getLog()->debug(sprintf(
                    'Replacing old with new - updateInLayouts = %d, deleteOldRevisions = %d',
                    $updateInLayouts,
                    $deleteOldRevisions
                ));

                // Load old media
                $oldMedia = $controller->getMediaFactory()->getById($this->options['oldMediaId']);

                // Check permissions
                if (!$controller->getUser()->checkEditable($oldMedia)) {
                    throw new AccessDeniedException(__('Access denied replacing old media'));
                }

                // Check to see if we are changing the media type
                if ($oldMedia->mediaType != $module->getModuleType() && $this->options['allowMediaTypeChange'] == 0) {
                    throw new InvalidArgumentException(
                        __('You cannot replace this media with an item of a different type')
                    );
                }

                // Set the old record to edited
                $oldMedia->isEdited = 1;

                $oldMedia->save(['validate' => false]);

                // The media name might be empty here, because the user isn't forced to select it
                $name = ($name == '') ? $oldMedia->name : $name;
                $tags = ($tags == '') ? '' : $tags;


                // Add the Media
                // the userId is either the existing user
                // (if we are changing media type) or the currently logged in user otherwise.
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
                if ($oldMedia->mediaType != 'video' && $media->mediaType != 'video') {
                    $media->duration = $oldMedia->duration;
                } else {
                    $media->duration = $module->determineDuration($filePath);
                }

                // Pre-process
                $module->preProcess($media, $filePath);

                // Raise an event for this media item
                $controller->getDispatcher()->dispatch(
                    LibraryReplaceEvent::$NAME,
                    new LibraryReplaceEvent($module, $media, $oldMedia)
                );

                $media->enableStat = $oldMedia->enableStat;
                $media->expires = $this->options['expires'];
                $media->folderId = $this->options['oldFolderId'];
                $media->permissionsFolderId = $oldMedia->permissionsFolderId;

                // Save
                $media->save(['oldMedia' => $oldMedia]);

                // Post process
                $playerVersionFactory = null;
                if ($media->mediaType === 'playersoftware') {
                    $playerVersionFactory = $controller->getPlayerVersionFactory();
                }
                $module->postProcess($media, $playerVersionFactory);

                $controller->getLog()->debug('Copying permissions to new media');

                foreach ($controller->getPermissionFactory()->getAllByObjectId(
                    $controller->getUser(),
                    get_class($oldMedia),
                    $oldMedia->mediaId
                ) as $permission) {
                    /* @var Permission $permission */
                    $permission = clone $permission;
                    $permission->objectId = $media->mediaId;
                    $permission->save();
                }

                // Do we want to replace this in all layouts?
                if ($updateInLayouts) {
                    $controller->getLog()->debug('Replace in all Layouts selected. Getting associated widgets');

                    foreach ($controller->getWidgetFactory()->getByMediaId($oldMedia->mediaId, 0) as $widget) {
                        /* @var Widget $widget */
                        if (!$controller->getUser()->checkEditable($widget)) {
                            // Widget that we cannot update,
                            // this means we can't delete the original mediaId when it comes time to do so.
                            $deleteOldRevisions = false;

                            $controller->getLog()->info(
                                'Media used on Widget that we cannot edit.
                             Delete Old Revisions has been disabled.'
                            );
                        }

                        // If we are replacing an audio media item,
                        // we should check to see if the widget we've found has any
                        // audio items assigned.
                        if ($module->getModuleType() == 'audio' &&
                            in_array($oldMedia->mediaId, $widget->getAudioIds())) {
                            $controller->getLog()->debug('Found audio on widget that needs updating. widgetId = ' .
                                $widget->getId() . '. Linking ' . $media->mediaId);

                            $widget->unassignAudioById($oldMedia->mediaId);
                            $widget->assignAudioById($media->mediaId);
                            $widget->save();
                        } elseif (count($widget->getPrimaryMedia()) > 0 &&
                            $widget->getPrimaryMediaId() == $oldMedia->mediaId) {
                            // We're only interested in primary media at this point (no audio)
                            // Check whether this widget is of the same type as our incoming media item
                            // This needs to be applicable only to non region specific Widgets,
                            // otherwise we would not be able to replace Media references in region specific Widgets.
                            $moduleWidget = $controller->getModuleFactory()->createWithWidget($widget);

                            if ($widget->type != $module->getModuleType() &&
                                $moduleWidget->getModule()->regionSpecific == 0) {
                                // Are we supposed to switch, or should we prevent?
                                if ($this->options['allowMediaTypeChange'] == 1) {
                                    $widget->type = $module->getModuleType();
                                } else {
                                    throw new InvalidArgumentException(__(
                                        'You cannot replace this media with an item of a different type'
                                    ));
                                }
                            }

                            $controller->getLog()->debug(sprintf(
                                'Found widget that needs updating. ID = %d. Linking %d',
                                $widget->getId(),
                                $media->mediaId
                            ));
                            $widget->unassignMedia($oldMedia->mediaId);
                            $widget->assignMedia($media->mediaId);

                            // calculate duration
                            $module->setWidget($widget);
                            $widget->calculateDuration($module);

                            // replace mediaId references in applicable widgets
                            $controller->getLayoutFactory()->handleWidgetMediaIdReferences(
                                $widget,
                                $media->mediaId,
                                $oldMedia->mediaId
                            );

                            // Raise an event for this media item
                            $controller->getDispatcher()->dispatch(
                                LibraryReplaceWidgetEvent::$NAME,
                                new LibraryReplaceWidgetEvent($module, $widget, $media, $oldMedia)
                            );

                            // Save
                            $widget->save(['alwaysUpdate' => true]);
                        }
                    }

                    // Update any background images
                    if ($media->mediaType == 'image') {
                        $controller->getLog()->debug(sprintf(
                            'Updating layouts with the old media %d as the background image.',
                            $oldMedia->mediaId
                        ));
                        // Get all Layouts with this as the background image
                        foreach ($controller->getLayoutFactory()->query(
                            null,
                            ['disableUserCheck' => 1, 'backgroundImageId' => $oldMedia->mediaId]
                        ) as $layout) {
                            /* @var Layout $layout */

                            if (!$controller->getUser()->checkEditable($layout)) {
                                // Widget that we cannot update,
                                // this means we can't delete the original mediaId when it comes time to do so.
                                $deleteOldRevisions = false;

                                $controller->getLog()->info(
                                    'Media used on Widget that we cannot edit. Delete Old Revisions has been disabled.'
                                );
                            }

                            $controller->getLog()->debug(sprintf(
                                'Found layout that needs updating. ID = %d. Setting background image id to %d',
                                $layout->layoutId,
                                $media->mediaId
                            ));
                            $layout->backgroundImageId = $media->mediaId;
                            $layout->save();
                        }
                    }
                } elseif ($this->options['widgetId'] != 0) {
                    $controller->getLog()->debug('Swapping a specific widget only.');
                    // swap this one
                    $widget = $controller->getWidgetFactory()->getById($this->options['widgetId']);

                    if (!$controller->getUser()->checkEditable($widget)) {
                        throw new AccessDeniedException();
                    }

                    $widget->unassignMedia($oldMedia->mediaId);
                    $widget->assignMedia($media->mediaId);
                    $widget->save();
                }

                // We either want to Link the old record to this one, or delete it
                if ($updateInLayouts && $deleteOldRevisions) {
                    $controller->getLog()->debug('Delete old revisions of ' . $oldMedia->mediaId);

                    // Check we have permission to delete this media
                    if (!$controller->getUser()->checkDeleteable($oldMedia)) {
                        throw new AccessDeniedException();
                    }

                    try {
                        // Join the prior revision up with the new media.
                        $priorMedia = $controller->getMediaFactory()->getParentById($oldMedia->mediaId);

                        $controller->getLog()->debug(
                            'Prior media found, joining ' .
                            $priorMedia->mediaId . ' with ' . $media->mediaId
                        );

                        $priorMedia->parentId = $media->mediaId;
                        $priorMedia->save(['validate' => false]);
                    } catch (NotFoundException $e) {
                        // Nothing to do then
                        $controller->getLog()->debug('No prior media found');
                    }

                    $controller->getDispatcher()->dispatch(MediaDeleteEvent::$NAME, new MediaDeleteEvent($oldMedia));
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
                $media = $controller->getMediaFactory()->create(
                    $name,
                    $fileName,
                    $module->getModuleType(),
                    $this->options['userId']
                );

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

                // Media library expiry.
                $media->expires = $this->options['expires'];
                $media->folderId = $this->options['oldFolderId'];

                // Permissions
                try {
                    $folder = $controller->getFolderFactory()->getById($this->options['oldFolderId']);
                    $media->permissionsFolderId =
                        ($folder->permissionsFolderId == null) ? $folder->id : $folder->permissionsFolderId;
                } catch (NotFoundException $exception) {
                    $media->permissionsFolderId = 1;
                }

                // Save
                $media->save();

                // Post process
                $playerVersionFactory = null;
                if ($media->mediaType === 'playersoftware') {
                    $playerVersionFactory = $controller->getPlayerVersionFactory();
                }
                $module->postProcess($media, $playerVersionFactory);
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
            $file->fileName = $fileName;

            // Test to ensure the final file size is the same as the file size we're expecting
            if ($file->fileSize != $file->size) {
                throw new InvalidArgumentException(
                    __('Sorry this is a corrupted upload, the file size doesn\'t match what we\'re expecting.'),
                    'size'
                );
            }
            // Fonts, then install
            if ($module->getModuleType() == 'font') {
                $controller->getMediaService()->installFonts($this->options['routeParser']);
            }

            // Are we assigning to a Playlist?
            if ($this->options['playlistId'] != 0 && $this->options['widgetId'] == 0) {
                $controller->getLog()->debug('Assigning uploaded media to playlistId ' . $this->options['playlistId']);

                // Get the Playlist
                $playlist = $controller->getPlaylistFactory()->getById($this->options['playlistId']);

                // Create a Widget and add it to our region
                $widget = $controller->getWidgetFactory()->create(
                    $this->options['userId'],
                    $playlist->playlistId,
                    $module->getModuleType(),
                    $media->duration
                );

                // Assign the widget to the module
                $module->setWidget($widget);

                // Set default options (this sets options on the widget)
                $module->setDefaultWidgetOptions();

                // From/To dates?
                $widget->fromDt = $this->options['widgetFromDt'];
                $widget->toDt = $this->options['widgetToDt'];
                $widget->setOptionValue('deleteOnExpiry', 'attrib', $this->options['deleteOnExpiry']);

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
            }
        } catch (Exception $e) {
            $controller->getLog()->error('Error uploading media: ' . $e->getMessage());
            $controller->getLog()->debug($e->getTraceAsString());

            // Unlink the temporary file
            @unlink($filePath);

            $file->error = $e->getMessage();
        }
    }

    /**
     * Get Param from File Input, taking into account multi-upload index if applicable
     * @param int $index
     * @param string $param
     * @param mixed $default
     * @return mixed
     */
    private function getParam($index, $param, $default)
    {
        if ($index === null) {
            if (isset($_REQUEST[$param])) {
                return $_REQUEST[$param];
            } else {
                return $default;
            }
        } else {
            if (isset($_REQUEST[$param][$index])) {
                return $_REQUEST[$param][$index];
            } else {
                return $default;
            }
        }
    }
}
