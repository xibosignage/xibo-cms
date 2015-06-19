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


use Respect\Validation\Validator as v;
use Xibo\Exception\ConfigurationException;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

class Media
{
    use EntityTrait;
    public $mediaId;
    public $ownerId;
    public $parentId;

    public $name;
    public $mediaType;
    public $storedAs;
    public $fileName;

    // Thing we might be referred to
    public $tags = [];
    private $widgets = [];
    private $displayGroups = [];
    private $permissions = [];

    public $fileSize;
    public $duration = 0;
    public $valid;
    public $moduleSystemFile = false;
    public $expires = 0;
    public $retired = 0;
    public $isEdited = 0;
    public $md5;

    // Read only properties
    public $owner;
    public $groupsWithPermissions;

    // New file revision
    public $force;
    public $isRemote;
    public $cloned = false;

    public function __clone()
    {
        // Clear the ID's and all widget/displayGroup assignments
        $this->mediaId = null;
        $this->widgets = [];
        $this->displayGroups = [];

        // We need to do something with the name
        $this->name = sprintf(__('Copy of %s'), $this->name);

        // Set so that when we add, we copy the existing file in the library
        $this->fileName = $this->storedAs;
        $this->storedAs = null;
        $this->cloned = true;
    }

    public function getId()
    {
        return $this->mediaId;
    }

    public function getOwnerId()
    {
        return $this->ownerId;
    }

    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->mediaType))
            throw new \InvalidArgumentException(__('Unknown Module Type'));

        if (!v::string()->notEmpty()->length(1, 100)->validate($this->name))
            throw new \InvalidArgumentException(__('The name cannot be longer than 100 characters'));

        // Check the naming of this item to ensure it doesn't conflict
        $params = array();
        $checkSQL = 'SELECT `name` FROM `media` WHERE `name` = :name AND userid = :userId';

        if ($this->mediaId != 0) {
            $checkSQL .= ' AND mediaId <> :mediaId AND IsEdited = 0 ';
            $params['mediaId'] = $this->mediaId;
        }

        $params['name'] = $this->name;
        $params['userId'] = $this->ownerId;

        $result = PDOConnect::select($checkSQL, $params);

        if (count($result) > 0)
            throw new \InvalidArgumentException(__('Media you own already has this name. Please choose another.'));
    }

    public function load()
    {
        // Tags
        $this->tags = TagFactory::loadByMediaId($this->mediaId);

        // Are we loading for a delete? If so load the child models
        if ($this->deleting) {
            // Permissions
            $this->permissions = PermissionFactory::getByObjectId('Media', $this->mediaId);

            // Widgets
            $this->widgets = WidgetFactory::getByMediaId($this->mediaId);

            // Display Groups
            $this->displayGroups = DisplayGroupFactory::getByMediaId($this->mediaId);
        }
    }

    /**
     * Save this media
     * @param bool $validate
     */
    public function save($validate = true)
    {
        if ($validate && $this->mediaType != 'module')
            $this->validate();

        // If we are a remote media item, we want to download the newFile and save it to a temporary location
        if ($this->isRemote) {
            $this->download();
        }

        // Add or edit
        if ($this->mediaId == null || $this->mediaId == 0) {
            $this->add();

            if ($this->mediaType != 'module' && Config::GetSetting('MEDIA_DEFAULT') == 'public') {
                $permission = PermissionFactory::createForEveryone('Media', $this->mediaId, 1, 0, 0);
                $permission->save();
            }
        }
        else {
            // If the media file is invalid, then force an update (only applies to module files)
            if ($this->valid == 0)
                $this->force = true;

            $this->edit();
        }

        // Save the tags
        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tag->assignMedia($this->mediaId);
            $tag->save();
        }
    }

    public function delete()
    {
        $this->deleting = true;
        $this->load();

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
            $widget->save();
        }

        foreach ($this->displayGroups as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $displayGroup */
            $displayGroup->unassignMedia($this->mediaId);
            $displayGroup->save(false);
        }

        PDOConnect::update('DELETE FROM media WHERE MediaID = :mediaId', ['mediaId' => $this->mediaId]);

        $this->deleteFile();

        // If there is a parent, bring it back
        if ($this->parentId != 0) {
            $media = MediaFactory::getById($this->parentId);
            $media->isEdited = 0;
            $media->parentId = null;
            $media->save(false);
        }
    }

    private function add()
    {
        $this->mediaId = PDOConnect::insert('
            INSERT INTO media (name, type, duration, originalFilename, userID, retired, moduleSystemFile, expires)
              VALUES (:name, :type, :duration, :originalFileName, :userId, :retired, :moduleSystemFile, :expires)
        ', [
            'name' => $this->name,
            'type' => $this->mediaType,
            'duration' => $this->duration,
            'originalFileName' => $this->fileName,
            'userId' => $this->ownerId,
            'retired' => $this->retired,
            'moduleSystemFile' => (($this->moduleSystemFile) ? 1 : 0),
            'expires' => $this->expires
        ]);

        $this->saveFile();

        // Update the MD5 and storedAs to suit
        PDOConnect::update('UPDATE `media` SET md5 = :md5, fileSize = :fileSize, storedAs = :storedAs WHERE mediaId = :mediaId', [
            'fileSize' => $this->fileSize,
            'md5' => $this->md5,
            'storedAs' => $this->storedAs,
            'mediaId' => $this->mediaId
        ]);
    }

    private function edit()
    {
        // Do we need to pull a new update?
        // Is the file either expired or is force set
        if ($this->force || ($this->expires > 0 && $this->expires < time())) {
            $this->saveFile();
        }

        PDOConnect::update('
          UPDATE `media`
              SET `name` = :name,
                duration = :duration,
                retired = :retired,
                md5 = :md5,
                filesize = :fileSize,
                expires = :expires,
                moduleSystemFile = :moduleSystemFile,
                editedMediaId = :editedMediaId,
                isEdited = :isEdited
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
            'mediaId' => $this->mediaId
        ]);
    }

    private function saveFile()
    {
        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Work out the extension
        $extension = strtolower(substr(strrchr($this->fileName, '.'), 1));

        Log::debug('saveFile with storedAs = %s. %s to %s', $this->storedAs, $this->fileName, $this->mediaId . '.' . $extension);

        // If the storesAs is empty, then set it to be the moved file name
        if (empty($this->storedAs)) {

            // We could be a fresh file entirely, or we could be a clone
            if ($this->cloned) {
                // Copy the file into the library
                if (!@copy($libraryFolder . $this->fileName, $libraryFolder . $this->mediaId . '.' . $extension))
                    throw new ConfigurationException(__('Problem copying file in the Library Folder'));

            } else {
                // Move the file into the library
                if (!@rename($libraryFolder . 'temp/' . $this->fileName, $libraryFolder . $this->mediaId . '.' . $extension))
                    throw new ConfigurationException(__('Problem moving uploaded file into the Library Folder'));
            }

            // Set the storedAs
            $this->storedAs = $this->mediaId . '.' . $extension;
        }
        else {
            // We have pre-defined where we want this to be stored
            if (!@copy($this->fileName, $libraryFolder . $this->storedAs)) {
                Log::error('Cannot move %s to %s', $this->fileName, $libraryFolder . $this->storedAs);
                throw new ConfigurationException(__('Problem moving provided file into the Library Folder'));
            }
        }

        // Work out the MD5
        $this->md5 = md5_file($libraryFolder . $this->storedAs);
        $this->fileSize = filesize($libraryFolder . $this->storedAs);
    }

    private function deleteFile()
    {
        // Library location
        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");

        // 3 things to check for..
        // the actual file, the thumbnail, the background
        if (file_exists($libraryLocation . $this->storedAs))
            unlink($libraryLocation . $this->storedAs);

        if (file_exists($libraryLocation . 'tn_' . $this->storedAs))
            unlink($libraryLocation . 'tn_' . $this->storedAs);

        if (file_exists($libraryLocation . 'bg_' . $this->storedAs))
            unlink($libraryLocation . 'bg_' . $this->storedAs);
    }

    private function download()
    {
        if (!$this->isRemote || $this->fileName == '')
            throw new \InvalidArgumentException(__('Not in a suitable state to download'));

        // Proxy
        $options = [];
        if (Config::GetSetting('PROXY_HOST') != '' && !Config::isProxyException($this->fileName)) {
            $options[] = Config::GetSetting('PROXY_HOST') . ':' . Config::GetSetting('PROXY_PORT');

            if (Config::GetSetting('PROXY_AUTH') != '') {
                $auth = explode(':', Config::GetSetting('PROXY_AUTH'));
                $options[] = $auth[0];
                $options[] = $auth[1];
            }
        }

        // Download the file and save it. Fill in the "storedAs" with the temporary file name and then continue
        $response = \Requests::get($this->fileName, [], $options);

        $this->storedAs = Config::GetSetting('LIBRARY_LOCATION') . 'temp' . DIRECTORY_SEPARATOR . $this->name;
        file_put_contents($this->storedAs, $response->body);
    }
}