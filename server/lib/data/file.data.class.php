<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Daniel Garner
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

class File extends Data
{
    /**
     * Adds a new file and appends the first chunk.
     * @param <type> $payload
     * @param <type> $userId
     * @return <type>
     */
    public function NewFile($payload, $userId)
    {
        $db =& $this->db;

        // Create a new file record
        $SQL = sprintf("INSERT INTO file (CreatedDT, UserID) VALUES (%d, %d)", time(), $userId);

        if (!$fileId = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(3);

            return false;
        }

        if (!$this->WriteToDisk($fileId, $payload))
            return false;

        return $fileId;
    }

    /**
     * Appends the next chunk to the file
     * @param <type> $fileId
     * @param <type> $payload
     * @param <type> $userId
     */
    public function Append($fileId, $payload)
    {
        $db =& $this->db;

        // Directory location
	$libraryFolder 	= Config::GetSetting($db, 'LIBRARY_LOCATION');
        $libraryFolder  = $libraryFolder . 'temp';

        // Append should only be called on existing files, if this file does not exist then we
        // need to error accordingly.
        if (!file_exists($libraryFolder . '/' . $fileId))
        {
            $this->SetError(7);
            return false;
        }

        return $this->WriteToDisk($fileId, $payload);
    }

    /**
     * Writes the file to disk
     * @param <type> $fileId
     * @param <type> $payload
     */
    public function WriteToDisk($fileId, $payload)
    {
        $db =& $this->db;

        // Directory location
	$libraryFolder 	= Config::GetSetting($db, 'LIBRARY_LOCATION');
        $libraryFolder  = $libraryFolder . 'temp';

        if (!$this->EnsureLibraryExists($libraryFolder))
            return false;

        // Open a file pointer
        if (!$fp = fopen($libraryFolder . '/' . $fileId, 'a'))
        {
            $this->SetError(5);
            return false;
        }

        // Write the payload to the file handle.
        if (fwrite($fp, $payload) === false)
        {
            $this->SetError(6);
            return false;
        }

        // Close the file pointer
        fclose($fp);

        return true;
    }

    /**
     * The current size of a file
     * @param <type> $fileId
     * @return <int> filesize
     */
    public function Size($fileId)
    {
        // Directory location
	$libraryFolder 	= Config::GetSetting($this->db, "LIBRARY_LOCATION");
        $libraryFolder = $libraryFolder . 'temp';

        return filesize($libraryFolder . '/' . $fileId);
    }

    /**
     * Generates a fileid
     * @param <type> $userId
     */
    public function GenerateFileId($userId)
    {
        $db =& $this->db;

        // Create a new file record
        $SQL = sprintf("INSERT INTO file (CreatedDT, UserID) VALUES (%d, %d)", time(), $userId);

        if (!$fileId = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(3);

            return false;
        }

        return $fileId;
    }

    public function EnsureLibraryExists()
    {
        $db =& $this->db;
        
        $libraryFolder 	= Config::GetSetting($db, 'LIBRARY_LOCATION');

        // Check that this location exists - and if not create it..
        if (!file_exists($libraryFolder))
            mkdir($libraryFolder, 0777, true);

        if (!file_exists($libraryFolder . '/temp'))
            mkdir($libraryFolder . '/temp', 0777, true);

        // Check that we are now writable - if not then error
        if (!is_writable($libraryFolder))
        {
            $this->SetError(4);
            return false;
        }

        return true;
    }
}
?>
