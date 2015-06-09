<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2010-2013 Daniel Garner
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
use Xibo\Controller\File;
use Xibo\Helper\Config;
use Xibo\Helper\Log;


class Media extends Data
{
    private $_moduleFiles;

    private $moduleInfoLoaded;
    private $regionSpecific;
    private $validExtensions;

    public $mediaId;
    public $storedAs;

    /**
     * Revises the file for this media id
     * @param int $mediaId
     * @param int $fileId
     * @param string $fileName
     * @param int $userId
     * @return bool|int
     */
    public function FileRevise($mediaId, $fileId, $fileName, $userId)
    {
        Log::notice('IN', 'Media', 'FileRevise');

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            // Check we have room in the library
            $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');
    
            if ($libraryLimit > 0) {

                $sth = $dbh->prepare('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media');
                $sth->execute();

                if (!$row = $sth->fetch())
                    throw new Exception("Error Processing Request", 1);
                    
                $fileSize = \Xibo\Helper\Sanitize::int($row['SumSize']);
    
                if (($fileSize / 1024) > $libraryLimit) {
                    $this->ThrowError(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit));
                }
            }

            // Check this user doesn't have a quota
            if (!UserGroup::isQuotaFullByUser($userId))
                $this->ThrowError(__('You have exceeded your library quota.'));
    
            // Call add with this file Id and then update the existing mediaId with the returned mediaId
            // from the add call.
            // Will need to get some information about the existing media record first.
            $sth = $dbh->prepare('SELECT name, duration, UserID, type FROM media WHERE MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId
                ));
    
            if (!$row = $sth->fetch())
                $this->ThrowError(31, 'Unable to get information about existing media record.');
    
            // Pass in the old media id ($mediaid) so that we don't validate against it during the name check
            if (!$newMediaId = $this->Add($fileId, $row['type'], $row['name'], $row['duration'], $fileName, $row['UserID'], $mediaId))
                throw new Exception("Error Processing Request", 1);
                
            // We need to assign all permissions for the old media id to the new media id

    
            $security = new MediaGroupSecurity($this->db);
            $security->Copy($mediaId, $newMediaId);
    
            // Update the existing record with the new record's id
            $sth = $dbh->prepare('UPDATE media SET isEdited = 1, editedMediaID = :newmediaid WHERE IFNULL(editedMediaID, 0) <> :newmediaid AND MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId,
                    'newmediaid' => $newMediaId
                ));
    
            return $newMediaId;  
        }
        catch (Exception $e) {
            
            Log::error($e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(32, 'Unable to update existing media record');
        
            return false;
        }
    }

    /**
     * Make a copy of this media record
     * @param <type> $oldMediaId
     */
    public function Copy($oldMediaId, $prefix = '')
    {
        Log::notice('IN', 'Media', 'Copy');
        
        try {
            $dbh = \Xibo\Storage\PDOConnect::init();
        
            // Get the extension from the old media record
            $sth = $dbh->prepare('SELECT StoredAs, Name FROM media WHERE MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $oldMediaId
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(26, __('Error getting media extension before copy.'));

            // Get the file name
            $fileName = \Xibo\Helper\Sanitize::string($row['StoredAs']);
            $extension = strtolower(substr(strrchr($fileName, '.'), 1));
    
            $newMediaName = \Xibo\Helper\Sanitize::string($row['Name']) . ' 2';
    
            if ($prefix != '')
                $newMediaName = $prefix . ' ' . $newMediaName;
    
            // All OK to insert this record
            $SQL  = "INSERT INTO media (name, type, duration, originalFilename, userID, retired )
             SELECT :name, type, duration, originalFilename, userID, retired
               FROM media
              WHERE MediaID = :mediaid ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'mediaid' => $oldMediaId,
                    'name' => $newMediaName
                ));

            $newMediaId = $dbh->lastInsertId();
    
            // Make a copy of the file
            $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');
    
            if (!copy($libraryFolder . $oldMediaId . '.' . $extension, $libraryFolder . $newMediaId . '.' . $extension))
                $this->ThrowError(15, 'Error storing file.');
    
            // Calculate the MD5 and the file size
            $storedAs   = $libraryFolder . $newMediaId . '.' . $extension;
            $md5        = md5_file($storedAs);
            $fileSize   = filesize($storedAs);
    
            // Update the media record to include this information
            $sth = $dbh->prepare('UPDATE media SET storedAs = :storedas, `MD5` = :md5, FileSize = :filesize WHERE mediaid = :mediaid');
            $sth->execute(array(
                    'mediaid' => $newMediaId,
                    'storedas' => $newMediaId . '.' . $extension,
                    'md5' => $md5,
                    'filesize' => $fileSize
                ));
    
            return $newMediaId;  
        }
        catch (Exception $e) {
            
            Log::error($e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(26, __('Error copying media.'));
        
            return false;
        }
    }




}
