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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class Media extends Data
{
    private $_moduleFiles;

    private $moduleInfoLoaded;
    private $regionSpecific;
    private $validExtensions;

    public $mediaId;
    public $ownerId;
    public $parentId;

    public $name;
    public $mediaType;
    public $storedAs;
    public $fileName;
    public $tags;
    
    public $fileSize;
    public $duration;
    public $valid;
    public $moduleSystemFile;
    public $expires;

    public static function Entries($sort_order = array('name'), $filter_by = array())
    {
        $entries = array();
        
        try {
            $dbh = PDOConnect::init();

            $params = array();
            $SQL  = '';
            $SQL .= "SELECT  media.mediaID, ";
            $SQL .= "   media.name, ";
            $SQL .= "   media.type, ";
            $SQL .= "   media.duration, ";
            $SQL .= "   media.userID, ";
            $SQL .= "   media.FileSize, ";
            $SQL .= "   media.storedAs, ";
            $SQL .= "   media.valid, ";
            $SQL .= "   media.moduleSystemFile, ";
            $SQL .= "   media.expires, ";
            $SQL .= "   IFNULL((SELECT parentmedia.mediaid FROM media parentmedia WHERE parentmedia.editedmediaid = media.mediaid),0) AS ParentID, ";
            
            if (Kit::GetParam('showTags', $filter_by, _INT) == 1)
                $SQL .= " tag.tag AS tags, ";
            else
                $SQL .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktagmedia ON lktagmedia.tagId = tag.tagId WHERE lktagmedia.mediaId = media.mediaID GROUP BY lktagmedia.mediaId) AS tags, ";
            
            $SQL .= "   media.originalFileName ";
            $SQL .= " FROM media ";
            $SQL .= "   LEFT OUTER JOIN media parentmedia ";
            $SQL .= "   ON parentmedia.MediaID = media.MediaID ";

            if (Kit::GetParam('showTags', $filter_by, _INT) == 1) {
                $SQL .= " LEFT OUTER JOIN lktagmedia ON lktagmedia.mediaId = media.mediaId ";
                $SQL .= " LEFT OUTER JOIN tag ON tag.tagId = lktagmedia.tagId";
            }

            $SQL .= " WHERE media.isEdited = 0 ";

            if (Kit::GetParam('allModules', $filter_by, _INT) == 0) {
                $SQL .= "AND media.type <> 'module'";
            }
            
            if (Kit::GetParam('name', $filter_by, _STRING) != '') {
                // convert into a space delimited array
                $names = explode(' ', Kit::GetParam('name', $filter_by, _STRING));
                $i = 0;
                foreach($names as $searchName) {
                    $i++;
                    // Not like, or like?
                    if (substr($searchName, 0, 1) == '-') {
                        $SQL .= " AND media.name NOT LIKE :notLike ";
                        $params['notLike'] = '%' . ltrim($searchName, '-') . '%';
                    }
                    else {
                        $SQL .= " AND media.name LIKE :like ";
                        $params['like'] = '%' . $searchName . '%';
                    }
                }
            }

            if (Kit::GetParam('mediaId', $filter_by, _INT, -1) != -1) {
                $SQL .= " AND media.mediaId = :mediaId ";
                $params['mediaId'] = Kit::GetParam('mediaId', $filter_by, _INT);
            }

            if (Kit::GetParam('type', $filter_by, _STRING) != '') {
                $SQL .= 'AND media.type = :type';
                $params['type'] = Kit::GetParam('type', $filter_by, _STRING);
            }

            if (Kit::GetParam('storedAs', $filter_by, _STRING) != '') {
                $SQL .= 'AND media.storedAs = :storedAs';
                $params['storedAs'] = Kit::GetParam('storedAs', $filter_by, _STRING);
            }

            if (Kit::GetParam('ownerid', $filter_by, _INT) != 0) {
                $SQL .= " AND media.userid = :ownerId ";
                $params['ownerId'] = Kit::GetParam('ownerid', $filter_by, _INT);
            }
            
            if (Kit::GetParam('retired', $filter_by, _INT, -1) == 1)
                $SQL .= " AND media.retired = 1 ";
            
            if (Kit::GetParam('retired', $filter_by, _INT, -1) == 0)
                $SQL .= " AND media.retired = 0 ";

            // Expired files?
            if (Kit::GetParam('expires', $filter_by, _INT) != 0) {
                $SQL .= ' AND media.expires < :expires AND IFNULL(media.expires, 0) <> 0 ';
                $params['expires'] = Kit::GetParam('expires', $filter_by, _INT);
            }
            
            // Sorting?
            if (is_array($sort_order))
                $SQL .= 'ORDER BY ' . implode(',', $sort_order);

            //Debug::Audit(sprintf('Retrieving list of media with SQL: %s. Params: %s', $SQL, var_export($params, true)));
        
            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            foreach ($sth->fetchAll() as $row) {
                $media = new Media();
                $media->mediaId = Kit::ValidateParam($row['mediaID'], _INT);
                $media->name = Kit::ValidateParam($row['name'], _STRING);
                $media->mediaType = Kit::ValidateParam($row['type'], _WORD);
                $media->duration = Kit::ValidateParam($row['duration'], _DOUBLE);
                $media->ownerId = Kit::ValidateParam($row['userID'], _INT);
                $media->fileSize = Kit::ValidateParam($row['FileSize'], _INT);
                $media->parentId = Kit::ValidateParam($row['ParentID'], _INT);
                $media->fileName = Kit::ValidateParam($row['originalFileName'], _STRING);
                $media->tags = Kit::ValidateParam($row['tags'], _STRING);
                $media->storedAs = Kit::ValidateParam($row['storedAs'], _STRING);
                $media->valid = Kit::ValidateParam($row['valid'], _INT);
                $media->moduleSystemFile = Kit::ValidateParam($row['moduleSystemFile'], _INT);
                $media->expires = Kit::ValidateParam($row['expires'], _INT);

                $entries[] = $media;
            }
        
            return $entries;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            return false;
        }
    }

    /**
     * Adds a new media record
     * @param <type> $fileId
     * @param <type> $type
     * @param <type> $name
     * @param <type> $duration
     * @param <type> $fileName
     * @param <type> $userId
     * @param <int> [$oldMediaId] [The old media id during a file revision]
     * @return <type>
     */
    public function Add($fileId, $type, $name, $duration, $fileName, $userId, $oldMediaId = 0)
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'Add');

        try {
            $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

            // Check that the file exists
            if (!file_exists($libraryFolder . 'temp/' . $fileId)) {
                $this->ThrowError(__('File cannot be found. Please check library permissions.'));
            }

            $dbh = PDOConnect::init();
        
            // Check we have room in the library
            $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');
    
            if ($libraryLimit > 0) {

                $sth = $dbh->prepare('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media');
                $sth->execute();

                if (!$row = $sth->fetch())
                    throw new Exception("Error Processing Request", 1);
                    
                $fileSize = Kit::ValidateParam($row['SumSize'], _INT);
    
                if (($fileSize / 1024) > $libraryLimit) {
                    $this->ThrowError(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit));
                }
            }

            // Check this user doesn't have a quota
            if (!UserGroup::isQuotaFullByUser($userId))
                $this->ThrowError(__('You have exceeded your library quota.'));
    
            $extension = strtolower(substr(strrchr($fileName, '.'), 1));
    
            // Check that is a valid media type
            if (!$this->IsValidType($type))
                throw new Exception("Error Processing Request", 1);
                
            // Check the extension is valid for that media type
            if (!$this->IsValidFile($type, $extension)) {
                Debug::Error('Invalid extension: ' . $extension);
                $this->ThrowError(18, __('Invalid file extension'));
            }
    
            // Validation
            if (strlen($name) > 100)
                $this->ThrowError(10, __('The name cannot be longer than 100 characters'));
    
            // Test the duration (except for video and localvideo which can have a 0)
            if ($duration == 0 && $type != 'video' && $type != 'localvideo' && $type != 'genericfile' && $type != 'font')
                $this->ThrowError(11, __('You must enter a duration.'));
    
            // Check the naming of this item to ensure it doesnt conflict
            $params = array();
            $checkSQL = 'SELECT name FROM media WHERE name = :name AND userid = :userid';
            
            if ($oldMediaId != 0) {
                $checkSQL .= ' AND mediaid <> :mediaid  AND IsEdited = 0 ';
                $params['mediaid'] = $oldMediaId;
            }

            $sth = $dbh->prepare($checkSQL);
            $params['name'] = $name;
            $params['userid'] = $userId;

            $sth->execute($params);

            if ($row = $sth->fetch())
                $this->ThrowError(12, __('Media you own already has this name. Please choose another.'));
            // End Validation
    
            // All OK to insert this record
            $SQL  = "INSERT INTO media (name, type, duration, originalFilename, userID, retired) ";
            $SQL .= "VALUES (:name, :type, :duration, :originalfilename, :userid, :retired) ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'name' => $name,
                    'type' => $type,
                    'duration' => $duration,
                    'originalfilename' => $fileName,
                    'userid' => $userId,
                    'retired' => 0
                ));

            $mediaId = $dbh->lastInsertId();
    
            // Now move the file
            if (!@rename($libraryFolder . 'temp/' . $fileId, $libraryFolder . $mediaId . '.' . $extension))
                $this->ThrowError(15, 'Error storing file.');
    
            // Calculate the MD5 and the file size
            $storedAs   = $libraryFolder . $mediaId . '.' . $extension;
            $md5        = md5_file($storedAs);
            $fileSize   = filesize($storedAs);
    
            // Update the media record to include this information
            $sth = $dbh->prepare('UPDATE media SET storedAs = :storedas, `MD5` = :md5, FileSize = :filesize WHERE mediaid = :mediaid');
            $sth->execute(array(
                    'storedas' => $mediaId . '.' . $extension,
                    'md5' => $md5,
                    'filesize' => $fileSize,
                    'mediaid' => $mediaId
                ));
    
            // What permissions should we assign this with?
            if (Config::GetSetting('MEDIA_DEFAULT') == 'public')
            {
                Kit::ClassLoader('mediagroupsecurity');
    
                $security = new MediaGroupSecurity($this->db);
                $security->LinkEveryone($mediaId, 1, 0, 0);
            }

            // Set some properties
            $this->storedAs = $mediaId . '.' . $extension;
            $this->mediaId = $mediaId;
    
            return $mediaId;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(13, __('Error adding media.'));
        
            return false;
        }
    }

    /**
     * Edit Media Record
     * @param <type> $mediaId
     * @param <type> $name
     * @param <type> $duration
     * @return <bool>
     */
    public function Edit($mediaId, $name, $duration, $userId, $tags = '')
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'Edit');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT type FROM `media` WHERE MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(12, __('Unable to find media type'));

            // Look up the type
            $type = Kit::ValidateParam($row['type'], _WORD);                
    
            // Validation
            if (strlen($name) > 100)
                $this->ThrowError(10, __('The name cannot be longer than 100 characters'));
    
            if ($duration == 0 && $type != 'video' && $type != 'localvideo' && $type != 'genericfile' && $type != 'font')
                $this->ThrowError(11, __('You must enter a duration.'));
    
            // Any media (not this one) already has this name?
            $sth = $dbh->prepare('SELECT name FROM media WHERE name = :name AND userid = :userid AND mediaid <> :mediaid AND IsEdited = 0');
            $sth->execute(array(
                    'mediaid' => $mediaId,
                    'name' => $name,
                    'userid' => $userId
                ));

            if ($row = $sth->fetch())
                $this->ThrowError(12, __('Media you own already has this name. Please choose another.'));
           
            // Update the media record
            $sth = $dbh->prepare('UPDATE media SET name = :name, duration = :duration WHERE MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId,
                    'name' => $name,
                    'duration' => $duration
                ));

            // Update the tags.
            if ($tags != '') {
                // Convert to an array.
                $tags = explode(',', $tags);

                // Untag all existing tags.
                $this->unTagAll($mediaId);

                // Loop through the new ones and tag accordingly.
                foreach ($tags as $tag) {
                    $this->tag($tag, $mediaId);
                }
            }
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(30, 'Database failure updating media');
        
            return false;
        }
    }

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
        Debug::LogEntry('audit', 'IN', 'Media', 'FileRevise');

        try {
            $dbh = PDOConnect::init();

            // Check we have room in the library
            $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');
    
            if ($libraryLimit > 0) {

                $sth = $dbh->prepare('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media');
                $sth->execute();

                if (!$row = $sth->fetch())
                    throw new Exception("Error Processing Request", 1);
                    
                $fileSize = Kit::ValidateParam($row['SumSize'], _INT);
    
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
            Kit::ClassLoader('mediagroupsecurity');
    
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
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(32, 'Unable to update existing media record');
        
            return false;
        }
    }

    public function Retire($mediaId)
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'Retire');

        try {
            $dbh = PDOConnect::init();
        
            // Retire the media
            $sth = $dbh->prepare('UPDATE media SET retired = 1 WHERE MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId
                ));
            
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(19, __('Error retiring media.'));
        
            return false;
        }
    }

    public function Delete($mediaId, $newRevisionMediaId = NULL)
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'Delete');
        
        try {
            $dbh = PDOConnect::init();
        
            // Check for links
            $sth = $dbh->prepare('SELECT * FROM lklayoutmedia WHERE MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId
                ));

            if ($sth->fetch())
                $this->ThrowError(21, __('This media is in use, please retire it instead.'));

            // Get the file name
            $sth = $dbh->prepare('SELECT StoredAs FROM media WHERE mediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(22, __('Cannot locate the files for this media. Unable to delete.'));
    
            // This will be used to delete the actual file (stored on disk)
            $fileName = Kit::ValidateParam($row['StoredAs'], _STRING);
    
            // Remove permission assignments
            $security = new MediaGroupSecurity($this->db);
    
            if (!$security->UnlinkAll($mediaId))
                throw new Exception("Error Processing Request", 1);

            // Delete any assignments
            $link = new LkMediaDisplayGroup($this->db);
            if (!$link->UnlinkAllFromDisplayGroup($mediaId))
                $this->ThrowError(__('Unable to drop file assignments during display delete.'));
                
            // Delete the media
            $sth = $dbh->prepare('DELETE FROM media WHERE MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId
                ));
    
            // Delete the file itself (and any thumbs, etc)
            if (!$this->DeleteMediaFile($fileName))
                throw new Exception("Error Processing Request", 1);
                
            // Bring back the previous revision of this media (if there is one)
            $sth = $dbh->prepare('SELECT IFNULL(MediaID, 0) AS MediaID FROM media WHERE EditedMediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId
                ));

            if ($editedMediaRow = $sth->fetch()) {
                // Unretire this edited record
                $editedMediaId = Kit::ValidateParam($editedMediaRow['MediaID'], _INT);

                if ($newRevisionMediaId == null) {
                    // Bring back the old one
                    $sth = $dbh->prepare('UPDATE media SET IsEdited = 0, EditedMediaID = NULL WHERE mediaid = :mediaid');
                    $sth->execute(array(
                        'mediaid' => $editedMediaId
                    ));

                } else {
                    // Link up the old one
                    $sth = $dbh->prepare('UPDATE media SET EditedMediaID = :newRevisionMediaId WHERE mediaid = :mediaid');
                    $sth->execute(array(
                        'mediaid' => $editedMediaId,
                        'newRevisionMediaId' => $newRevisionMediaId
                    ));
                }
            }
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(23, __('Error deleting media.'));
        
            return false;
        }
    }

    public function GetStoredAs($mediaId) {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT storedas FROM `media` WHERE mediaid = :id');
            $sth->execute(array('id' => $mediaId));

            return $sth->fetchColumn();          
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function DeleteMediaFile($fileName)
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'DeleteMediaFile');
        
        // Library location
        $databaseDir = Config::GetSetting("LIBRARY_LOCATION");

        //3 things to check for..
        //the actual file, the thumbnail, the background
        if (file_exists($databaseDir . $fileName))
            unlink($databaseDir . $fileName);

        if (file_exists($databaseDir . 'tn_' . $fileName))
            unlink($databaseDir . 'tn_' . $fileName);

        if (file_exists($databaseDir . 'bg_' . $fileName))
            unlink($databaseDir . 'bg_' . $fileName);

        return true;
    }

    private function IsValidType($type)
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'IsValidType');
        
        if (!$this->moduleInfoLoaded)
        {
            if (!$this->LoadModuleInfo($type))
                return false;
        }

        return true;
    }

    private function IsValidFile($type, $extension)
    {
        // Load some information about this module
        if (!$this->moduleInfoLoaded)
        {
            if (!$this->LoadModuleInfo($type))
                return false;
        }

        Debug::Audit('Valid Extensions: ' . var_export($this->validExtensions, true));

        // TODO: Is this search case sensitive?
        return in_array($extension, $this->validExtensions);
    }

    /**
     * Loads some information about this type of module
     * @return <bool>
     */
    private function LoadModuleInfo($type)
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'LoadModuleInfo');
        
        try {
            $dbh = PDOConnect::init();
        
            if ($type == '')
                $this->ThrowError(18, __('No module type given'));

            $sth = $dbh->prepare('SELECT * FROM module WHERE Module = :module');
            $sth->execute(array(
                    'module' => $type
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(20, __('No Module of this type found'));
    
            $this->moduleInfoLoaded = true;
            $this->regionSpecific = Kit::ValidateParam($row['RegionSpecific'], _INT);
            $this->validExtensions = explode(',', Kit::ValidateParam($row['ValidExtensions'], _STRING));
            
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(19, __('Database error checking module'));
        
            return false;
        }
    }

    /**
     * Valid Extensions
     * @param [string] $type [The Type of Media Item]
     * @return [array] Array containing the valid extensions
     */
    public function ValidExtensions($type) {
        Debug::LogEntry('audit', 'IN', 'Media', 'ValidExtensions');
        
        if (!$this->moduleInfoLoaded)
        {
            if (!$this->LoadModuleInfo($type))
                return false;
        }

        return $this->validExtensions;
    }

    /**
     * List of available modules
     * @return <array>
     */
    public function ModuleList()
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'ModuleList');
        
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT * FROM module WHERE Enabled = 1');
            $sth->execute();

            $modules = array();
    
            while($row = $sth->fetch()) {
                $module = array();
    
                $module['module'] = $row['Module'];
                $module['layoutOnly'] = $row['RegionSpecific'];
                $module['description'] = $row['Description'];
                $module['extensions'] = $row['ValidExtensions'];
                
                $modules[] = $module;
            }
    
            return $modules;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Make a copy of this media record
     * @param <type> $oldMediaId
     */
    public function Copy($oldMediaId, $prefix = '')
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'Copy');
        
        try {
            $dbh = PDOConnect::init();
        
            // Get the extension from the old media record
            $sth = $dbh->prepare('SELECT StoredAs, Name FROM media WHERE MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $oldMediaId
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(26, __('Error getting media extension before copy.'));

            // Get the file name
            $fileName = Kit::ValidateParam($row['StoredAs'], _STRING);    
            $extension = strtolower(substr(strrchr($fileName, '.'), 1));
    
            $newMediaName = Kit::ValidateParam($row['Name'], _STRING) . ' 2';
    
            if ($prefix != '')
                $newMediaName = $prefix . ' ' . $newMediaName;
    
            // All OK to insert this record
            $SQL  = "INSERT INTO media (name, type, duration, originalFilename, userID, retired ) ";
            $SQL .= " SELECT :name, type, duration, originalFilename, userID, retired ";
            $SQL .= "  FROM media ";
            $SQL .= " WHERE MediaID = :mediaid ";

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
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(26, __('Error copying media.'));
        
            return false;
        }
    }

    /**
     * Adds module files from a folder.
     * The entire folder will be added as module files
     * @param string  $folder The path to the folder to add.
     * @param boolean $force  Whether or not each individual module should be force updated if it exists already
     */
    public function addModuleFileFromFolder($folder, $force = false) 
    {
        if (!is_dir($folder))
            return $this->SetError(__('Not a folder'));

        foreach (array_diff(scandir($folder), array('..', '.')) as $file) {

            //Debug::Audit('Found file: ' . $file);

            $this->addModuleFile($folder . DIRECTORY_SEPARATOR . $file, 0, true, $force);
        }
    }

    /**
     * Adds a module file from a URL
     */
    public function addModuleFileFromUrl($url, $name, $expires, $moduleSystemFile = false, $force = false)
    {
        // See if we already have it
        // It doesn't matter that we might have already done this, its cached.
        $media = $this->moduleFileExists($name);

        //Debug::Audit('Module File: ' . var_export($media, true));

        if ($media === false || $force) {
            Debug::Audit('Adding: ' . $url . ' with Name: ' . $name . '. Expiry: ' . date('Y-m-d h:i:s', $expires));
            
            $fileName = Config::GetSetting('LIBRARY_LOCATION') . 'temp' . DIRECTORY_SEPARATOR . $name;
            
            // Put in a temporary folder
            File::downloadFile($url, $fileName);

            // Add the media file to the library
            $media = $this->addModuleFile($fileName, $expires, $moduleSystemFile, true);

            // Tidy temp
            unlink($fileName);
        }

        return $media;
    }

    /**
     * Adds a module file. 
     * Module files are hidden from the UI and supplementary files that will be used
     * by the module that added them.
     * @param string  $file  The path to the file that needs adding
     * @param int[Optional] $expires Expiry time in seconds - default 0
     * @param boolean[Optional] $moduleSystemFile Is this a system file - default true
     * @param boolean[Optional] $force Whether to force an update to the file or not
     * @return array Media File Added
     */
    public function addModuleFile($file, $expires = 0, $moduleSystemFile = true, $force = false)
    {
        try {
            $name = basename($file);

            $media = $this->moduleFileExists($name);

            //Debug::Audit('Module File: ' . var_export($media, true));

            $dbh = PDOConnect::init();
            
            // Do we need to update this module file (meaning, is it out of date)
            // Why might it be out of date?
            //  - an upgrade might of invalidated it
            // How can we tell?
            // - valid flag on the media
            if ($media !== false && $media['valid'] == 0) {
                Debug::Audit('Media not valid, forcing update.');
                $force = true;
            }

            // Force will be set by now. 
            if (!$force && $media !== false) {
                // Nibble on the update date
                $sth = $dbh->prepare('UPDATE `media` SET expires = :expires WHERE mediaId = :mediaId');
                $sth->execute(array(
                        'mediaId' => $media['mediaId'],
                        'expires' => $expires
                    ));

                // Need to return the media object
                return $media;
            }

            $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

            // Get the name
            $storedAs = $libraryFolder . $name;

            Debug::Audit('Updating: ' . $name);
             
            // Now copy the file
            if (!@copy($file, $storedAs))
                $this->ThrowError(15, 'Error storing file.');

            // Calculate the MD5 and the file size
            $md5        = md5_file($storedAs);
            $fileSize   = filesize($storedAs);
        
            if ($media !== false) {
                
                $SQL = "UPDATE `media` SET md5 = :md5, filesize = :filesize, expires = :expires, moduleSystemFile = :moduleSystemFile WHERE mediaId = :mediaId ";

                $sth = $dbh->prepare($SQL);
                $sth->execute(array(
                        'mediaId' => $media['mediaId'],
                        'filesize' => $fileSize,
                        'md5' => $md5,
                        'expires' => $expires,
                        'moduleSystemFile' => $moduleSystemFile
                    ));

                // Update the media array for returning
                $media['expires'] = $expires;
            }
            else {
                // All OK to insert this record
                $SQL  = "INSERT INTO media (name, type, duration, originalFilename, userID, retired, moduleSystemFile, storedAs, FileSize, MD5, expires) ";
                $SQL .= "VALUES (:name, :type, :duration, :originalfilename, 1, :retired, :moduleSystemFile, :storedas, :filesize, :md5, :expires) ";

                $sth = $dbh->prepare($SQL);
                $sth->execute(array(
                        'name' => $name,
                        'type' => 'module',
                        'duration' => 10,
                        'originalfilename' => $name,
                        'retired' => 0,
                        'storedas' => $name,
                        'filesize' => $fileSize,
                        'md5' => $md5,
                        'moduleSystemFile' => (($moduleSystemFile) ? 1 : 0),
                        'expires' => $expires
                    ));

                $media = array('mediaId' => $dbh->lastInsertId(), 'storedAs' => $name, 'expires' => $expires);
            }

            // Add to the cache
            $this->_moduleFiles[$name] = $media;

            return $media;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Remove a module file
     * @param  int $mediaId  The MediaID of the module to remove
     * @param  string $storedAs The Location of the File as it is stored
     * @return boolean True or False
     */
    public function removeModuleFile($mediaId, $storedAs)
    {
        try {
            $dbh = PDOConnect::init();

            Debug::Audit('Removing: ' . $storedAs . ' ID:' . $mediaId);
        
            // Delete the links
            $sth = $dbh->prepare('DELETE FROM lklayoutmedia WHERE mediaId = :mediaId AND regionId = :regionId');
            $sth->execute(array(
                    'mediaId' => $mediaId,
                    'regionId' => 'module'
                ));
    
            // Delete the media
            $sth = $dbh->prepare('DELETE FROM media WHERE mediaId = :mediaId');
            $sth->execute(array(
                    'mediaId' => $mediaId
                ));
    
            // Delete the file itself (and any thumbs, etc)
            return $this->DeleteMediaFile($storedAs);
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Does the module file exist?
     * Checks to see if the module file specified exists or not
     * @param  string $file The path
     * @return int The MediaId or false
     */
    public function moduleFileExists($file)
    {
        try {
            if ($this->_moduleFiles == NULL || count($this->_moduleFiles) < 1) {
                $dbh = PDOConnect::init();
            
                $sth = $dbh->prepare('SELECT storedAs, mediaId, valid, expires FROM `media` WHERE type = :type');
                $sth->execute(array(
                        'type' => 'module'
                    ));
                
                $this->_moduleFiles = array();

                foreach ($sth->fetchAll() as $moduleFile)
                    $this->_moduleFiles[$moduleFile['storedAs']] = array('mediaId' => $moduleFile['mediaId'], 'valid' => $moduleFile['valid'], 'expires' => $moduleFile['expires'], 'storedAs' => $moduleFile['storedAs']);
            }

            //Debug::Audit(var_export($this->_moduleFiles, true));

            // Return the value (the ID) or false
            return (array_key_exists($file, $this->_moduleFiles) ? $this->_moduleFiles[$file] : false);
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Installs all files related to the enabled modules
     */
    public static function installAllModuleFiles()
    {
        $media = new Media();

        // Do this for all enabled modules
        foreach ($media->ModuleList() as $module) {

            // Install Files for this module
            $moduleObject = ModuleFactory::create($module['module']);
            $moduleObject->InstallFiles();
        }
    }

    /**
     * Removes all expired media files
     */
    public static function removeExpiredFiles()
    {
        $media = new Media();

        // Get a list of all expired files and delete them
        foreach (Media::Entries(NULL, array('expires' => time(), 'allModules' => 1)) as $entry) {
            // If the media type is a module, then pretend its a generic file
            if ($entry->mediaType == 'module') {
                // Find and remove any links to layouts.
                $media->removeModuleFile($entry->mediaId, $entry->storedAs);
            }
            else {
                // Create a module for it and issue a delete
                include_once('modules/' . $entry->type . '.module.php');
                $moduleObject = new $entry->type(new database(), new User());

                // Remove it from all assigned layout
                $moduleObject->UnassignFromAll($entry->mediaId);
                
                // Delete it
                $media->Delete($entry->mediaId);
            }
        }
    }

    /**
     * Links a layout and tag
     * @param string $tag The Tag
     * @param int $mediaId The Layout
     */
    public function tag($tag, $mediaId)
    {
        $tagObject = new Tag();
        if (!$tagId = $tagObject->add($tag))
            return $this->SetError($tagObject->GetErrorMessage());

        try {
            $dbh = PDOConnect::init();

            // See if this tag exists
            $sth = $dbh->prepare('SELECT * FROM `lktagmedia` WHERE mediaId = :mediaId AND tagId = :tagId');
            $sth->execute(array(
                    'tagId' => $tagId,
                    'mediaId' => $mediaId
                ));

            if (!$row = $sth->fetch()) {
        
                $sth = $dbh->prepare('INSERT INTO `lktagmedia` (tagId, mediaId) VALUES (:tagId, :mediaId)');
                $sth->execute(array(
                        'tagId' => $tagId,
                        'mediaId' => $mediaId
                    ));
          
                return $dbh->lastInsertId();
            }
            else {
                return Kit::ValidateParam($row['lkTagMediaId'], _INT);
            }
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Untag a layout
     * @param  string $tag The Tag
     * @param  int $mediaId The Layout Id
     */
    public function unTag($tag, $mediaId) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM `lktagmedia` WHERE tagId IN (SELECT tagId FROM tag WHERE tag = :tag) AND mediaId = :mediaId)');
            $sth->execute(array(
                    'tag' => $tag,
                    'mediaId' => $mediaId
                ));
          
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Untag all tags on a layout
     * @param  [int] $mediaId The Layout Id
     */
    public function unTagAll($mediaId) {
        Debug::Audit('IN');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM `lktagmedia` WHERE mediaId = :mediaId');
            $sth->execute(array(
                    'mediaId' => $mediaId
                ));
          
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Delete all Media for a User
     * @param int $userId
     * @return bool
     */
    public function deleteAllForUser($userId)
    {
        $media = Media::Entries(null, array('ownerid' => $userId));

        foreach ($media as $item) {
            /* @var Media $item */
            if (!$item->Delete($item->mediaId))
                return $this->SetError($item->GetErrorMessage());
        }

        return true;
    }

    /**
     * Get unused media entries
     * @param int $userId
     * @return array
     * @throws Exception
     */
    public static function entriesUnusedForUser($userId)
    {
        $media = array();

        try {
            $dbh = PDOConnect::init();
            $sth = $dbh->prepare('SELECT media.mediaId, media.storedAs, media.type, media.isedited, media.fileSize,
                    SUM(CASE WHEN IFNULL(lklayoutmedia.lklayoutmediaid, 0) = 0 THEN 0 ELSE 1 END) AS UsedInLayoutCount,
                    SUM(CASE WHEN IFNULL(lkmediadisplaygroup.id, 0) = 0 THEN 0 ELSE 1 END) AS UsedInDisplayCount
                  FROM `media`
                    LEFT OUTER JOIN `lklayoutmedia`
                    ON lklayoutmedia.mediaid = media.mediaid
                    LEFT OUTER JOIN `lkmediadisplaygroup`
                    ON lkmediadisplaygroup.mediaid = media.mediaid
                 WHERE media.userId = :userId
                  AND media.type <> \'module\' AND media.type <> \'font\'
                GROUP BY media.mediaid, media.storedAs, media.type, media.isedited');

            $sth->execute(array('userId' => $userId));

            foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $row) {
                // Check to make sure it is not used
                if ($row['UsedInLayoutCount'] > 0 || $row['UsedInDisplayCount'] > 0)
                    continue;

                $media[] = $row;
            }
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage());
            throw new Exception(__('Cannot get entries'));
        }

        return $media;
    }

    /**
     * Delete unused media for user
     * @param int $userId
     * @return bool
     */
    public function deleteUnusedForUser($userId)
    {
        foreach (Media::entriesUnusedForUser($userId) as $item) {
            Debug::Audit('Deleting unused media: ' . $item['mediaId']);
            if (!$this->Delete($item['mediaId']))
                return false;
        }

        return true;
    }
}
