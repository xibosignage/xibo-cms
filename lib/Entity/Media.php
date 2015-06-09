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
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\TagFactory;
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
    public $tags = [];
    private $permissions = [];

    public $fileSize;
    public $duration;
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
    public $newFile;
    public $force;

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
            $checkSQL .= ' AND mediaid <> :mediaid  AND IsEdited = 0 ';
            $params['mediaid'] = $this->mediaId;
        }

        $params['name'] = $this->name;
        $params['userId'] = $this->ownerId;

        $result = PDOConnect::select($checkSQL, $params);

        if (count($result) > 0)
            throw new \InvalidArgumentException(__('Media you own already has this name. Please choose another.'));
    }

    public function load()
    {
        $this->tags = TagFactory::loadByMediaId($this->mediaId);
        $this->permissions = PermissionFactory::getByObjectId('Media', $this->mediaId);
    }

    public function save($validate = true)
    {
        if ($validate && $this->mediaType != 'module')
            $this->validate();

        if ($this->mediaId == null || $this->mediaId == 0) {
            $this->add();

            if (Config::GetSetting('MEDIA_DEFAULT') == 'public') {
                $permission = PermissionFactory::createForEveryone('Mesdia', $this->mediaId, 1, 0, 0);
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
        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Work out the MD5 and File Size
        $this->md5 = md5_file($libraryFolder . 'temp/' . $this->fileName);
        $this->fileSize = filesize($libraryFolder . 'temp/' . $this->fileName);

        $this->mediaId = PDOConnect::insert('
            INSERT INTO media (name, type, duration, originalFilename, userID, retired, moduleSystemFile, FileSize, MD5, expires)
              VALUES (:name, :type, :duration, :originalFileName, :userId, :retired, :moduleSystemFile, :fileSize, :md5, :expires)
        ', [
            'name' => $this->name,
            'type' => $this->mediaType,
            'duration' => $this->duration,
            'originalFileName' => $this->fileName,
            'userId' => $this->ownerId,
            'retired' => $this->retired,
            'moduleSystemFile' => (($this->moduleSystemFile) ? 1 : 0),
            'fileSize' => $this->fileSize,
            'md5' => $this->md5,
            'expires' => $this->expires
        ]);

        $this->saveFile();
    }

    private function edit()
    {
        PDOConnect::update('
          UPDATE `media`
            SET `name` = :name, duration = :duration, retired = :retired, md5 = :md5, filesize = :fileSize, expires = :expires, moduleSystemFile = :moduleSystemFile
           WHERE mediaId = :mediaId
        ', [
            'name' => $this->name,
            'duration' => $this->duration,
            'retired' => $this->retired,
            'fileSize' => $this->fileSize,
            'md5' => $this->md5,
            'expires' => $this->expires,
            'moduleSystemFile' => $this->moduleSystemFile,
            'mediaId' => $this->mediaId
        ]);

        if ($this->newFile != '') {
            $this->saveFile();
        }
    }

    private function saveFile()
    {
        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Work out the extension
        $extension = strtolower(substr(strrchr($this->fileName, '.'), 1));

        Log::debug('saveFile with storedAs = %s. %s to %s', $this->storedAs, $this->fileName, $this->mediaId . '.' . $extension);

        // If the storesAs is empty, then set it to be the moved file name
        if (empty($this->storedAs)) {

            // Move the file into the library
            if (!@rename($libraryFolder . 'temp/' . $this->fileName, $libraryFolder . $this->mediaId . '.' . $extension))
                throw new ConfigurationException(__('Problem moving uploaded file into the Library Folder'));

            $this->storedAs = $this->mediaId . '.' . $extension;
            PDOConnect::update('UPDATE `media` SET storedAs = :storedAs WHERE mediaId = :mediaId', [
                'storedAs' => $this->storedAs,
                'mediaId' => $this->mediaId
            ]);
        }
        else {
            // We have pre-defined where we want this to be stored
            if (!@copy($this->fileName, $libraryFolder . $this->storedAs))
                throw new ConfigurationException(__('Problem moving provided file into the Library Folder'));
        }
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
}