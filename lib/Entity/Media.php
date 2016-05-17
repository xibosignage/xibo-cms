<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Media.php) is part of Xibo.
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


namespace Xibo\Entity;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Respect\Validation\Validator as v;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Media
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Media implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Media ID")
     * @var int
     */
    public $mediaId;

    /**
     * @SWG\Property(description="The ID of the User that owns this Media")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The Parent ID of this Media if it has been revised")
     * @var int
     */
    public $parentId;

    /**
     * @SWG\Property(description="The Name of this Media")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="The module type of this Media")
     * @var int
     */
    public $mediaType;

    /**
     * @SWG\Property(description="The file name of the media as stored in the library")
     * @var string
     */
    public $storedAs;

    /**
     * @SWG\Property(description="The original file name as it was uploaded")
     * @var string
     */
    public $fileName;

    // Thing that might be referred to
    /**
     * @SWG\Property(description="Tags associated with this Media")
     * @var Tag[]
     */
    public $tags = [];

    /**
     * @SWG\Property(description="The file size in bytes")
     * @var int
     */
    public $fileSize;

    /**
     * @SWG\Property(description="The duration to use when assigning this media to a Layout widget")
     * @var int
     */
    public $duration = 0;

    /**
     * @SWG\Property(description="Flag indicating whether this media is valid.")
     * @var int
     */
    public $valid = 1;

    /**
     * @SWG\Property(description="Flag indicating whether this media is a system file or not")
     * @var int
     */
    public $moduleSystemFile = 0;

    /**
     * @SWG\Property(description="Timestamp indicating when this media should expire")
     * @var int
     */
    public $expires = 0;

    /**
     * @SWG\Property(description="Flag indicating whether this media is retired")
     * @var int
     */
    public $retired = 0;

    /**
     * @SWG\Property(description="Flag indicating whether this media has been edited and replaced with a newer file")
     * @var int
     */
    public $isEdited = 0;

    /**
     * @SWG\Property(description="A MD5 checksum of the stored media file")
     * @var string
     */
    public $md5;

    /**
     * @SWG\Property(description="The username of the User that owns this media")
     * @var string
     */
    public $owner;

    /**
     * @SWG\Property(description="A comma separated list of groups/users with permissions to this Media")
     * @var string
     */
    public $groupsWithPermissions;

    /**
     * @SWG\Property(description="A flag indicating whether this media has been released")
     * @var int
     */
    public $released = 1;

    /**
     * @SWG\Property(description="An API reference")
     * @var string
     */
    public $apiRef;

    // Private
    private $unassignTags = [];

    // New file revision
    public $force;
    public $isRemote;
    public $cloned = false;
    public $newExpiry;
    public $alwaysCopy = false;

    private $widgets = [];
    private $displayGroups = [];
    private $layoutBackgroundImages = [];
    private $permissions = [];

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     * @param PermissionFactory $permissionFactory
     * @param TagFactory $tagFactory
     * @param PlaylistFactory $playlistFactory
     */
    public function __construct($store, $log, $config, $mediaFactory, $permissionFactory, $tagFactory, $playlistFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->config = $config;
        $this->mediaFactory = $mediaFactory;
        $this->permissionFactory = $permissionFactory;
        $this->tagFactory = $tagFactory;
        $this->playlistFactory = $playlistFactory;
    }

    /**
     * Set Child Object Dependencies
     * @param LayoutFactory $layoutFactory
     * @param WidgetFactory $widgetFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @return $this
     */
    public function setChildObjectDependencies($layoutFactory, $widgetFactory, $displayGroupFactory)
    {
        $this->layoutFactory = $layoutFactory;
        $this->widgetFactory = $widgetFactory;
        $this->displayGroupFactory  = $displayGroupFactory;
        return $this;
    }

    public function __clone()
    {
        // Clear the ID's and all widget/displayGroup assignments
        $this->mediaId = null;
        $this->widgets = [];
        $this->displayGroups = [];
        $this->layoutBackgroundImages = [];
        $this->permissions = [];

        // We need to do something with the name
        $this->name = sprintf(__('Copy of %s'), $this->name);

        // Set so that when we add, we copy the existing file in the library
        $this->fileName = $this->storedAs;
        $this->storedAs = null;
        $this->cloned = true;
    }

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->mediaId;
    }

    /**
     * Get Owner Id
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * @return int
     */
    private function countUsages()
    {
        $this->load(['fullInfo' => true]);

        return count($this->widgets) + count($this->displayGroups) + count($this->layoutBackgroundImages);
    }

    /**
     * Is this media used
     * @param int $usages threshold
     * @return bool
     */
    public function isUsed($usages = 0)
    {
        return $this->countUsages() > $usages;
    }

    /**
     * Assign Tag
     * @param Tag $tag
     * @return $this
     */
    public function assignTag($tag)
    {
        $this->load();

        if (!in_array($tag, $this->tags))
            $this->tags[] = $tag;

        return $this;
    }

    /**
     * Unassign tag
     * @param Tag $tag
     * @return $this
     */
    public function unassignTag($tag)
    {
        $this->tags = array_udiff($this->tags, [$tag], function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        return $this;
    }

    /**
     * @param array[Tag] $tags
     */
    public function replaceTags($tags = [])
    {
        if (!is_array($this->tags) || count($this->tags) <= 0)
            $this->tags = $this->tagFactory->loadByMediaId($this->mediaId);

        $this->unassignTags = array_udiff($this->tags, $tags, function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        $this->getLog()->debug('Tags to be removed: %s', json_encode($this->unassignTags));

        // Replace the arrays
        $this->tags = $tags;

        $this->getLog()->debug('Tags remaining: %s', json_encode($this->tags));
    }

    /**
     * Validate
     * @param array $options
     */
    public function validate($options)
    {
        if (!v::string()->notEmpty()->validate($this->mediaType))
            throw new \InvalidArgumentException(__('Unknown Module Type'));

        if (!v::string()->notEmpty()->length(1, 100)->validate($this->name))
            throw new \InvalidArgumentException(__('The name must be between 1 and 100 characters'));

        // Check the naming of this item to ensure it doesn't conflict
        $params = array();
        $checkSQL = 'SELECT `name` FROM `media` WHERE `name` = :name AND userid = :userId';

        if ($this->mediaId != 0) {
            $checkSQL .= ' AND mediaId <> :mediaId AND IsEdited = 0 ';
            $params['mediaId'] = $this->mediaId;
        }
        else if ($options['oldMedia'] != null && $this->name == $options['oldMedia']->name) {
            $checkSQL .= ' AND IsEdited = 0 ';
        }

        $params['name'] = $this->name;
        $params['userId'] = $this->ownerId;

        $result = $this->getStore()->select($checkSQL, $params);

        if (count($result) > 0)
            throw new \InvalidArgumentException(__('Media you own already has this name. Please choose another.'));
    }

    /**
     * Load
     * @param array $options
     */
    public function load($options = [])
    {
        $options = array_merge([
            'deleting' => false,
            'fullInfo' => false
        ], $options);

        $this->getLog()->debug('Loading Media. Options = %s', json_encode($options));

        // Tags
        $this->tags = $this->tagFactory->loadByMediaId($this->mediaId);

        // Are we loading for a delete? If so load the child models
        if ($options['deleting'] || $options['fullInfo']) {

            if ($this->widgetFactory === null)
                throw new ConfigurationException(__('Call setChildObjectDependencies before load'));

            // Permissions
            $this->permissions = $this->permissionFactory->getByObjectId(get_class($this), $this->mediaId);

            // Widgets
            $this->widgets = $this->widgetFactory->getByMediaId($this->mediaId);

            // Layout Background Images
            $this->layoutBackgroundImages = $this->layoutFactory->getByBackgroundImageId($this->mediaId);

            // Display Groups
            $this->displayGroups = $this->displayGroupFactory->getByMediaId($this->mediaId);
        }

        $this->loaded = true;
    }

    /**
     * Save this media
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'oldMedia' => null
        ], $options);

        if ($options['validate'] && $this->mediaType != 'module')
            $this->validate($options);

        // Add or edit
        if ($this->mediaId == null || $this->mediaId == 0) {
            $this->add();
        }
        else {
            // If the media file is invalid, then force an update (only applies to module files)
            if ($this->valid == 0)
                $this->force = true;

            $this->edit();
        }

        // Save the tags
        if (is_array($this->tags)) {
            foreach ($this->tags as $tag) {
                /* @var Tag $tag */
                $tag->assignMedia($this->mediaId);
                $tag->save();
            }
        }

        // Remove unwanted ones
        if (is_array($this->unassignTags)) {
            foreach ($this->unassignTags as $tag) {
                /* @var Tag $tag */
                $this->getLog()->debug('Unassigning tag: %s', $tag->tag);

                $tag->unassignMedia($this->mediaId);
                $tag->save();
            }
        }
    }

    /**
     * Delete
     * @throws \Xibo\Exception\NotFoundException
     */
    public function delete()
    {
        $this->load(['deleting' => true]);

        // If there is a parent, bring it back
        try {
            $parentMedia = $this->mediaFactory->getParentById($this->mediaId);
            $parentMedia->isEdited = 0;
            $parentMedia->parentId = null;
            $parentMedia->save(['validate' => false]);
        }
        catch (NotFoundException $e) {
            // This is fine, no parent
            $parentMedia = null;
        }

        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tag->unassignMedia($this->mediaId);
            $tag->save();
        }

        foreach ($this->widgets as $widget) {
            /* @var \Xibo\Entity\Widget $widget */
            $widget->unassignMedia($this->mediaId);

            if ($parentMedia != null) {
                // Assign the parent media to the widget instead
                $widget->assignMedia($parentMedia->mediaId);

                // Swap any audio nodes over to this new widget media assignment.
                $this->getStore()->update('
                  UPDATE `lkwidgetaudio` SET mediaId = :mediaId WHERE widgetId = :widgetId AND oldMediaId = :oldMediaId
                ' , [
                    'mediaId' => $parentMedia->mediaId,
                    'widgetId' => $widget->widgetId,
                    'oldMediaId' => $this->mediaId
                ]);
            } else {
                // Also delete the `lkwidgetaudio`
                $widget->unassignAudioById($this->mediaId);
            }

            // This action might result in us deleting a widget (unless we are a temporary file with an expiry date)
            if ($this->expires == 0 && count($widget->mediaIds) <= 0) {
                $widget->setChildObjectDepencencies($this->playlistFactory);
                $widget->delete();
            }
            else
                $widget->save(['saveWidgetOptions' => false]);
        }

        foreach ($this->displayGroups as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $displayGroup */
            $displayGroup->unassignMedia($this);

            if ($parentMedia != null)
                $displayGroup->assignMedia($parentMedia);

            $displayGroup->save(['validate' => false]);
        }

        foreach ($this->layoutBackgroundImages as $layout) {
            /* @var Layout $layout */
            $layout->backgroundImageId = null;
            $layout->save(Layout::$saveOptionsMinimum);
        }

        $this->getStore()->update('DELETE FROM media WHERE MediaID = :mediaId', ['mediaId' => $this->mediaId]);

        $this->deleteFile();

        // Update any background images
        if ($this->mediaType == 'image' && $parentMedia != null) {
            $this->getLog()->debug('Updating layouts with the old media %d as the background image.', $this->mediaId);
            // Get all Layouts with this as the background image
            foreach ($this->layoutFactory->query(null, ['backgroundImageId' => $this->mediaId]) as $layout) {
                /* @var Layout $layout */
                $this->getLog()->debug('Found layout that needs updating. ID = %d. Setting background image id to %d', $layout->layoutId, $parentMedia->mediaId);
                $layout->backgroundImageId = $parentMedia->mediaId;
                $layout->save();
            }
        }
    }

    /**
     * Add
     * @throws ConfigurationException
     */
    private function add()
    {
        $this->mediaId = $this->getStore()->insert('
            INSERT INTO media (name, type, duration, originalFilename, userID, retired, moduleSystemFile, expires, released, apiRef)
              VALUES (:name, :type, :duration, :originalFileName, :userId, :retired, :moduleSystemFile, :expires, :released, :apiRef)
        ', [
            'name' => $this->name,
            'type' => $this->mediaType,
            'duration' => $this->duration,
            'originalFileName' => basename($this->fileName),
            'userId' => $this->ownerId,
            'retired' => $this->retired,
            'moduleSystemFile' => (($this->moduleSystemFile) ? 1 : 0),
            'expires' => $this->expires,
            'released' => $this->released,
            'apiRef' => $this->apiRef
        ]);

        $this->saveFile();

        // Update the MD5 and storedAs to suit
        $this->getStore()->update('UPDATE `media` SET md5 = :md5, fileSize = :fileSize, storedAs = :storedAs, duration = :duration WHERE mediaId = :mediaId', [
            'fileSize' => $this->fileSize,
            'md5' => $this->md5,
            'storedAs' => $this->storedAs,
            'duration' => $this->duration,
            'mediaId' => $this->mediaId
        ]);
    }

    /**
     * Edit
     * @throws ConfigurationException
     */
    private function edit()
    {
        // Do we need to pull a new update?
        // Is the file either expired or is force set
        if ($this->force || ($this->expires > 0 && $this->expires < time())) {
            $this->getLog()->debug('Media %s has expired: %s. Force = %d', $this->name, date('Y-m-d H:i', $this->expires), $this->force);
            $this->saveFile();
        }

        $this->getStore()->update('
          UPDATE `media`
              SET `name` = :name,
                duration = :duration,
                retired = :retired,
                md5 = :md5,
                filesize = :fileSize,
                expires = :expires,
                moduleSystemFile = :moduleSystemFile,
                editedMediaId = :editedMediaId,
                isEdited = :isEdited,
                userId = :userId,
                released = :released,
                apiRef = :apiRef
           WHERE mediaId = :mediaId
        ', [
            'name' => $this->name,
            'duration' => $this->duration,
            'retired' => $this->retired,
            'fileSize' => $this->fileSize,
            'md5' => $this->md5,
            'expires' => $this->expires,
            'moduleSystemFile' => $this->moduleSystemFile,
            'editedMediaId' => $this->parentId,
            'isEdited' => $this->isEdited,
            'userId' => $this->ownerId,
            'released' => $this->released,
            'apiRef' => $this->apiRef,
            'mediaId' => $this->mediaId
        ]);
    }

    /**
     * Save File to Library
     *  this should download remote files, handle clones, handle local module files and also handle files uploaded
     *  over the web ui
     * @throws ConfigurationException
     */
    private function saveFile()
    {
        // If we are a remote media item, we want to download the newFile and save it to a temporary location
        if ($this->isRemote) {
            $this->download();
        }

        $libraryFolder = $this->config->GetSetting('LIBRARY_LOCATION');

        // Work out the extension
        $extension = strtolower(substr(strrchr($this->fileName, '.'), 1));

        $this->getLog()->debug('saveFile for "%s" with storedAs = "%s" (empty = %s), fileName = "%s" to "%s". Always Copy = "%s", Cloned = "%s"',
            $this->name,
            $this->storedAs,
            empty($this->storedAs),
            $this->fileName,
            $this->mediaId . '.' . $extension,
            $this->alwaysCopy,
            $this->cloned
        );

        // If the storesAs is empty, then set it to be the moved file name
        if (empty($this->storedAs) && !$this->alwaysCopy) {

            // We could be a fresh file entirely, or we could be a clone
            if ($this->cloned) {
                $this->getLog()->debug('Copying cloned file');
                // Copy the file into the library
                if (!@copy($libraryFolder . $this->fileName, $libraryFolder . $this->mediaId . '.' . $extension))
                    throw new ConfigurationException(__('Problem copying file in the Library Folder'));

            } else {
                $this->getLog()->debug('Moving temporary file');
                // Move the file into the library
                if (!@rename($libraryFolder . 'temp/' . $this->fileName, $libraryFolder . $this->mediaId . '.' . $extension))
                    throw new ConfigurationException(__('Problem moving uploaded file into the Library Folder'));
            }

            // Set the storedAs
            $this->storedAs = $this->mediaId . '.' . $extension;
        }
        else {
            $this->getLog()->debug('Copying specified file');
            // We have pre-defined where we want this to be stored
            if (empty($this->storedAs)) {
                // Assume we want to set this automatically (i.e. we are set to always copy)
                $this->storedAs = $this->mediaId . '.' . $extension;
            }

            if (!@copy($this->fileName, $libraryFolder . $this->storedAs)) {
                $this->getLog()->error('Cannot copy %s to %s', $this->fileName, $libraryFolder . $this->storedAs);
                throw new ConfigurationException(__('Problem moving provided file into the Library Folder'));
            }
        }

        // Work out the MD5
        $this->md5 = md5_file($libraryFolder . $this->storedAs);
        $this->fileSize = filesize($libraryFolder . $this->storedAs);
    }

    /**
     * Delete a Library File
     */
    private function deleteFile()
    {
        // Make sure storedAs isn't null
        if ($this->storedAs == null) {
            $this->getLog()->error('Deleting media [%s] with empty stored as. Skipping library file delete.', $this->name);
            return;
        }

        $this->unlink($this->storedAs);
    }

    /**
     * Unlink a file
     * @param string $fileName
     */
    public function unlink($fileName)
    {
        // Library location
        $libraryLocation = $this->config->GetSetting("LIBRARY_LOCATION");

        // 3 things to check for..
        // the actual file, the thumbnail, the background
        if (file_exists($libraryLocation . $fileName))
            unlink($libraryLocation . $fileName);

        if (file_exists($libraryLocation . 'tn_' . $fileName))
            unlink($libraryLocation . 'tn_' . $fileName);

        if (file_exists($libraryLocation . 'bg_' . $fileName))
            unlink($libraryLocation . 'bg_' . $fileName);
    }

    /**
     * Download remote file
     */
    private function download()
    {
        if (!$this->isRemote || $this->fileName == '')
            throw new \InvalidArgumentException(__('Not in a suitable state to download'));

        // Open the temporary file
        $storedAs = $this->config->GetSetting('LIBRARY_LOCATION') . 'temp' . DIRECTORY_SEPARATOR . $this->name;

        $this->getLog()->debug('Downloading %s to %s', $this->fileName, $storedAs);

        if (!$fileHandle = fopen($storedAs, 'w'))
            throw new ConfigurationException(__('Temporary location not writable'));

        try {
            $client = new Client();
            $client->get($this->fileName, $this->config->getGuzzleProxy(['save_to' => $fileHandle]));
        }
        catch (RequestException $e) {
            $this->getLog()->error('Unable to get %s, %s', $this->fileName, $e->getMessage());
        }

        // Change the filename to our temporary file
        $this->fileName = $storedAs;
    }
}