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
    private $moduleInfoLoaded;
    private $regionSpecific;
    private $validExtensions;

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
    
            $extension = strtolower(substr(strrchr($fileName, '.'), 1));
    
            // Check that is a valid media type
            if (!$this->IsValidType($type))
                throw new Exception("Error Processing Request", 1);
                
            // Check the extension is valid for that media type
            if (!$this->IsValidFile($extension))
                $this->ThrowError(18, __('Invalid file extension'));
    
            // Validation
            if (strlen($name) > 100)
                $this->ThrowError(10, __('The name cannot be longer than 100 characters'));
    
            // Test the duration (except for video and localvideo which can have a 0)
            if ($duration == 0 && $type != 'video' && $type != 'localvideo' && $type != 'genericfile')
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
            $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');
    
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
    public function Edit($mediaId, $name, $duration, $userId)
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
    
            if ($duration == 0 && $type != 'video' && $type != 'localvideo' && $type != 'genericfile')
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
     * @param <type> $mediaId
     * @param <type> $fileId
     * @param <type> $fileName
     */
    public function FileRevise($mediaId, $fileId, $fileName)
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

    public function Delete($mediaId)
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'Delete');
        
        Kit::ClassLoader('lkmediadisplaygroup');

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
            Kit::ClassLoader('mediagroupsecurity');
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

                $sth = $dbh->prepare('UPDATE media SET IsEdited = 0, EditedMediaID = NULL WHERE mediaid = :mediaid');
                $sth->execute(array(
                        'mediaid' => $editedMediaId
                    ));
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

    private function IsValidFile($extension)
    {
        Debug::LogEntry('audit', 'IN', 'Media', 'IsValidFile');
        
        if (!$this->moduleInfoLoaded)
        {
            if (!$this->LoadModuleInfo())
                return false;
        }

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
}
?>
