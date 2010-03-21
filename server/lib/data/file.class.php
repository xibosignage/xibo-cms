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
        $SQL = sprintf("INSERT INTO file ('CreatedDT, UserID') VALUES (%d, %d)", time(), $userId);

        if (!$fileId = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(3);

            return false;
        }

        if (!$this->Append($fileId, $payload, $userId))
            return false;

        return $fileId;
    }

    /**
     * Appends the next chunk to the file
     * @param <type> $fileId
     * @param <type> $payload
     * @param <type> $userId
     */
    public function Append($fileId, $payload, $userId)
    {
        $db =& $this->db;

        // Directory location
	$libraryFolder 	= Config::GetSetting($db, "LIBRARY_LOCATION");
        $libraryFolder = $libraryFolder . 'temp';

        // Check that this location exists - and if not create it..
        if (!file_exists($libraryFolder))
        {
            // Make the directory with broad permissions recursively (so will add the whole path)
            mkdir($libraryFolder, 0777, true);
        }

        // Check that we are now writable - if not then error
        if (!is_writable($libraryFolder))
        {
            $this->SetError(4);
            return false;
        }

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
}
?>
