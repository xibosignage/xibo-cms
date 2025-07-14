<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Helper;

use Exception;
use Xibo\Entity\Layout;
use Xibo\Entity\Permission;
use Xibo\Event\LibraryReplaceEvent;
use Xibo\Event\LibraryReplaceWidgetEvent;
use Xibo\Event\LibraryUploadCompleteEvent;
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
    protected function handleFormData($file, $index)
    {
        $controller = $this->options['controller'];
        /* @var \Xibo\Controller\Library $controller */

        // Handle form data, e.g. $_REQUEST['description'][$index]
        // Link the file to the module
        $fileName = $file->name;
        $filePath = $controller->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $fileName;

        $this->getLogger()->debug('Upload complete for name: ' . $fileName . '. Index is ' . $index);

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
            $name = htmlspecialchars($this->getParam($index, 'name', $fileName));
            $tags = $controller->getUser()->featureEnabled('tag.tagging')
                ? htmlspecialchars($this->getParam($index, 'tags', ''))
                : '';

            // Guess the type
            $module = $controller->getModuleFactory()
                ->getByExtension(strtolower(substr(strrchr($fileName, '.'), 1)));

            $this->getLogger()->debug(sprintf(
                'Module Type = %s, Name = %s',
                $module->type,
                $module->name
            ));

            // If we have an oldMediaId then we are replacing that media with new one
            if ($this->options['oldMediaId'] != 0) {
                $updateInLayouts = ($this->options['updateInLayouts'] == 1);
                $deleteOldRevisions = ($this->options['deleteOldRevisions'] == 1);

                $this->getLogger()->debug(sprintf(
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
                if ($oldMedia->mediaType != $module->type && $this->options['allowMediaTypeChange'] == 0) {
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
                // (if we are changing media type) or the currently logged-in user otherwise.
                $media = $controller->getMediaFactory()->create(
                    $name,
                    $fileName,
                    $module->type,
                    $oldMedia->getOwnerId()
                );

                if ($tags != '') {
                    $concatTags = $oldMedia->getTagString() . ',' . $tags;
                    $media->updateTagLinks($controller->getTagFactory()->tagsFromString($concatTags));
                }

                // Apply the duration from the old media, unless we're a video
                if ($module->type === 'video') {
                    $media->duration = $module->fetchDurationOrDefaultFromFile($filePath);
                } else {
                    $media->duration = $oldMedia->duration;
                }

                // Raise an event for this media item
                $controller->getDispatcher()->dispatch(
                    new LibraryReplaceEvent($module, $media, $oldMedia),
                    LibraryReplaceEvent::$NAME
                );

                $media->enableStat = $oldMedia->enableStat;
                $media->expires = $this->options['expires'];
                $media->folderId = $this->options['oldFolderId'];
                $media->permissionsFolderId = $oldMedia->permissionsFolderId;

                // Save
                $media->save(['oldMedia' => $oldMedia]);

                // Upload finished
                $controller->getDispatcher()->dispatch(
                    new LibraryUploadCompleteEvent($media),
                    LibraryUploadCompleteEvent::$NAME
                );

                $this->getLogger()->debug('Copying permissions to new media');

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
                    $this->getLogger()->debug('Replace in all Layouts selected. Getting associated widgets');

                    foreach ($controller->getWidgetFactory()->getByMediaId($oldMedia->mediaId, 0) as $widget) {
                        $this->getLogger()->debug('Found widgetId ' . $widget->widgetId
                                . ' to assess, type is ' . $widget->type);

                        if (!$controller->getUser()->checkEditable($widget)) {
                            // Widget that we cannot update,
                            // this means we can't delete the original mediaId when it comes time to do so.
                            $deleteOldRevisions = false;

                            $controller
                                ->getLog()->info('Media used on Widget that we cannot edit. Delete Old Revisions has been disabled.'); //phpcs:ignore
                        }

                        // Load the module for this widget.
                        $moduleToReplace = $controller->getModuleFactory()->getByType($widget->type);

                        // If we are replacing an audio media item,
                        // we should check to see if the widget we've found has any
                        // audio items assigned.
                        if ($module->type == 'audio'
                            && in_array($oldMedia->mediaId, $widget->getAudioIds())
                        ) {
                            $this->getLogger()->debug('Found audio on widget that needs updating. widgetId = ' .
                                $widget->getId() . '. Linking ' . $media->mediaId);

                            $widget->unassignAudioById($oldMedia->mediaId);
                            $widget->assignAudioById($media->mediaId);
                            $widget->save();
                        } else if ($widget->type !== 'global'
                            && count($widget->getPrimaryMedia()) > 0
                            && $widget->getPrimaryMediaId() == $oldMedia->mediaId
                        ) {
                            // We're only interested in primary media at this point (no audio)
                            // Check whether this widget is of the same type as our incoming media item
                            // This needs to be applicable only to non region specific Widgets,
                            // otherwise we would not be able to replace Media references in region specific Widgets.

                            // If these types are different, and the module we're replacing isn't region specific
                            // then we need to see if we're allowed to change it.
                            if ($widget->type != $module->type && $moduleToReplace->regionSpecific == 0) {
                                // Are we supposed to switch, or should we prevent?
                                if ($this->options['allowMediaTypeChange'] == 1) {
                                    $widget->type = $module->type;
                                } else {
                                    throw new InvalidArgumentException(__(
                                        'You cannot replace this media with an item of a different type'
                                    ));
                                }
                            }

                            $this->getLogger()->debug(sprintf(
                                'Found widget that needs updating. ID = %d. Linking %d',
                                $widget->getId(),
                                $media->mediaId
                            ));
                            $widget->unassignMedia($oldMedia->mediaId);
                            $widget->assignMedia($media->mediaId);

                            // calculate duration
                            $widget->calculateDuration($module);

                            // replace mediaId references in applicable widgets
                            $controller->getLayoutFactory()->handleWidgetMediaIdReferences(
                                $widget,
                                $media->mediaId,
                                $oldMedia->mediaId
                            );

                            // Raise an event for this media item
                            $controller->getDispatcher()->dispatch(
                                new LibraryReplaceWidgetEvent($module, $widget, $media, $oldMedia),
                                LibraryReplaceWidgetEvent::$NAME
                            );

                            // Save
                            $widget->save(['alwaysUpdate' => true]);
                        }

                        // Does this widget have any elements?
                        if ($moduleToReplace->regionSpecific == 1) {
                            // This is a global widget and will have elements which refer to this media id.
                            $this->getLogger()
                                ->debug('handleFormData: This is a region specific widget, checking for elements.');

                            // We need to load options as that is where we store elements
                            $widget->load(false);

                            // Parse existing elements.
                            $mediaFoundInElement = false;
                            $elements = json_decode($widget->getOptionValue('elements', '[]'), true);
                            foreach ($elements as $index => $widgetElement) {
                                foreach ($widgetElement['elements'] ?? [] as $elementIndex => $element) {
                                    // mediaId on the element, used for things like image element
                                    if (!empty($element['mediaId']) && $element['mediaId'] == $oldMedia->mediaId) {
                                        // We have found an element which uses the mediaId we are replacing
                                        $elements[$index]['elements'][$elementIndex]['mediaId'] = $media->mediaId;

                                        // Swap the ID on the link record
                                        $widget->unassignMedia($oldMedia->mediaId);
                                        $widget->assignMedia($media->mediaId);

                                        $mediaFoundInElement = true;
                                    }

                                    // mediaId on the property, used for mediaSelector properties.
                                    foreach ($element['properties'] ?? [] as $propertyIndex => $property) {
                                        if (!empty($property['mediaId'])) {
                                            // TODO: should we really load in all templates here and replace?
                                            // Set the mediaId and value of this property
                                            // this only works because mediaSelector is the only property which
                                            // uses mediaId and it always has the value set.
                                            $elements[$index]['elements'][$elementIndex]['properties']
                                                [$propertyIndex]['mediaId'] = $media->mediaId;
                                            $elements[$index]['elements'][$elementIndex]['properties']
                                                [$propertyIndex]['value'] = $media->mediaId;

                                            $widget->unassignMedia($oldMedia->mediaId);
                                            $widget->assignMedia($media->mediaId);

                                            $mediaFoundInElement = true;
                                        }
                                    }
                                }
                            }

                            if ($mediaFoundInElement) {
                                $this->getLogger()
                                    ->debug('handleFormData: mediaId found in elements, replacing');

                                // Save the new elements
                                $widget->setOptionValue('elements', 'raw', json_encode($elements));

                                // Raise an event for this media item
                                $controller->getDispatcher()->dispatch(
                                    new LibraryReplaceWidgetEvent($module, $widget, $media, $oldMedia),
                                    LibraryReplaceWidgetEvent::$NAME
                                );

                                // Save
                                $widget->save(['alwaysUpdate' => true]);
                            }
                        }
                    }

                    // Update any background images
                    if ($media->mediaType == 'image') {
                        $this->getLogger()->debug(sprintf(
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

                                $this->getLogger()->info(
                                    'Media used on Widget that we cannot edit. Delete Old Revisions has been disabled.'
                                );
                            }

                            $this->getLogger()->debug(sprintf(
                                'Found layout that needs updating. ID = %d. Setting background image id to %d',
                                $layout->layoutId,
                                $media->mediaId
                            ));
                            $layout->backgroundImageId = $media->mediaId;
                            $layout->save();
                        }
                    }
                } elseif ($this->options['widgetId'] != 0) {
                    $this->getLogger()->debug('Swapping a specific widget only.');
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
                    $this->getLogger()->debug('Delete old revisions of ' . $oldMedia->mediaId);

                    // Check we have permission to delete this media
                    if (!$controller->getUser()->checkDeleteable($oldMedia)) {
                        throw new AccessDeniedException(
                            __('You do not have permission to delete the old version.')
                        );
                    }

                    try {
                        // Join the prior revision up with the new media.
                        $priorMedia = $controller->getMediaFactory()->getParentById($oldMedia->mediaId);

                        $this->getLogger()->debug(
                            'Prior media found, joining ' .
                            $priorMedia->mediaId . ' with ' . $media->mediaId
                        );

                        $priorMedia->parentId = $media->mediaId;
                        $priorMedia->save(['validate' => false]);
                    } catch (NotFoundException $e) {
                        // Nothing to do then
                        $this->getLogger()->debug('No prior media found');
                    }

                    $controller->getDispatcher()->dispatch(
                        new MediaDeleteEvent($oldMedia),
                        MediaDeleteEvent::$NAME
                    );
                    $oldMedia->delete();
                } else {
                    $oldMedia->parentId = $media->mediaId;
                    $oldMedia->save(['validate' => false]);
                }
            } else {
                // Not a replacement
                // Fresh upload
                // The media name might be empty here, because the user isn't forced to select it
                $name = ($name == '') ? $fileName : $name;
                $tags = ($tags == '') ? '' : $tags;

                // Add the Media
                $media = $controller->getMediaFactory()->create(
                    $name,
                    $fileName,
                    $module->type,
                    $this->options['userId']
                );

                if ($tags != '') {
                    $media->updateTagLinks($controller->getTagFactory()->tagsFromString($tags));
                }

                // Set the duration
                $media->duration = $module->fetchDurationOrDefaultFromFile($filePath);

                if ($media->enableStat == null) {
                    $media->enableStat = $controller->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT');
                }

                // Media library expiry.
                $media->expires = $this->options['expires'];
                $media->folderId = $this->options['oldFolderId'];

                // Permissions
                $folder = $controller->getFolderFactory()->getById($this->options['oldFolderId'], 0);
                $media->permissionsFolderId = $folder->getPermissionFolderIdOrThis();

                // Save
                $media->save();

                // Upload finished
                $controller->getDispatcher()->dispatch(
                    new LibraryUploadCompleteEvent($media),
                    LibraryUploadCompleteEvent::$NAME
                );
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
            $file->width = $media->width;
            $file->height = $media->height;
            $file->mediaType = $module->type;
            $file->fileName = $fileName;

            // Test to ensure the final file size is the same as the file size we're expecting
            if ($file->fileSize != $file->size) {
                throw new InvalidArgumentException(
                    __('Sorry this is a corrupted upload, the file size doesn\'t match what we\'re expecting.'),
                    'size'
                );
            }

            // Are we assigning to a Playlist?
            if ($this->options['playlistId'] != 0 && $this->options['widgetId'] == 0) {
                $this->getLogger()->debug('Assigning uploaded media to playlistId '
                    . $this->options['playlistId']);

                // Get the Playlist
                $playlist = $controller->getPlaylistFactory()->getById($this->options['playlistId']);

                if (!$playlist->isEditable()) {
                    throw new InvalidArgumentException(
                        __('This Layout is not a Draft, please checkout.'),
                        'layoutId'
                    );
                }

                // Create a Widget and add it to our region
                $widget = $controller->getWidgetFactory()->create(
                    $this->options['userId'],
                    $playlist->playlistId,
                    $module->type,
                    $media->duration,
                    $module->schemaVersion
                );

                // Default options
                $widget->setOptionValue(
                    'enableStat',
                    'attrib',
                    $controller->getConfig()->getSetting('WIDGET_STATS_ENABLED_DEFAULT')
                );

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

                // Configure widgetId is response
                $file->widgetId = $widget->widgetId;
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Error uploading media: ' . $e->getMessage());
            $this->getLogger()->debug($e->getTraceAsString());

            // Unlink the temporary file
            @unlink($filePath);

            $file->error = $e->getMessage();

            // Don't commit
            $controller->getState()->setCommitState(false);
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
