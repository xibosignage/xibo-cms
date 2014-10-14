<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012 Daniel Garner
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

class Maintenance extends Data
{
    /**
     * Backup the Database
     * @param <string> $saveAs file|string
     */
    public function BackupDatabase($saveAs = "string")
    {
        // Always truncate the log first
        $this->db->query("TRUNCATE TABLE `log` ");
        $this->db->query("TRUNCATE TABLE `oauth_log` ");

        global $dbhost;
        global $dbuser;
        global $dbpass;
        global $dbname;

        // Run mysqldump to a temporary file

        // get temporary file
        $tempFile = tempnam(Config::GetSetting('LIBRARY_LOCATION'), 'dmp');

        exec('mysqldump --opt --host=' . $dbhost . ' --user=' . $dbuser . ' --password=' . $dbpass . ' ' . $dbname . ' > ' . escapeshellarg($tempFile) . ' ');

        $sqlDump = file_get_contents($tempFile);

        unlink($tempFile);

        return $sqlDump;
    }

    /**
     * Restore Database
     * @param <string> $fileName
     */
    public function RestoreDatabase($fileName)
    {
        global $dbhost;
        global $dbuser;
        global $dbpass;
        global $dbname;
        
        // Push the file into msqldump
        exec('mysql --user=' . $dbuser . ' --password=' . $dbpass . ' ' . $dbname . ' < ' . escapeshellarg($fileName) . ' ');

        Debug::LogEntry('audit', 'mysql --user=' . $dbuser . ' --password=' . $dbpass . ' ' . $dbname . ' < ' . escapeshellarg($fileName) . ' ' );

        return true;
    }

    public function TidyLibrary($tidyOldRevisions) {
        // Also run a script to tidy up orphaned media in the library
        $library = Config::GetSetting('LIBRARY_LOCATION');
        $library = rtrim($library, '/') . '/';
        $mediaObject = new Media();

        Debug::Audit('Library Location: ' . $library);

        // Dump the files in the temp folder
        foreach (scandir($library . 'temp') as $item) {
            if ($item == '.' || $item == '..')
                continue;

            Debug::Audit('Deleting temp file: ' . $item);

            unlink($library . 'temp' . DIRECTORY_SEPARATOR . $item);
        }

        $media = array();
        $unusedMedia = array();

        // Run a query to get an array containing all of the media in the library
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                SELECT media.mediaid, media.storedAs, media.type, media.isedited, COUNT(lklayoutmedia.lklayoutmediaid) AS UsedInLayoutCount 
                  FROM `media` 
                    LEFT OUTER JOIN `lklayoutmedia`
                    ON lklayoutmedia.mediaid = media.mediaid
                GROUP BY media.mediaid, media.storedAs ');

            $sth->execute(array());

            foreach ($sth->fetchAll() as $row) {
                $media[$row['storedAs']] = $row;
                
                // If its not used in a layout and its not a generic module, add to the unused array.
                if ($tidyOldRevisions && $row['UsedInLayoutCount'] <= 0 && $row['isedited'] > 0 && $row['type'] != 'module' && $row['type'] != 'font')
                    $unusedMedia[$row['storedAs']] = $row;
            }
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }

        //Debug::Audit(var_export($media, true));
        //Debug::Audit(var_export($unusedMedia, true));

        // Get a list of all media files
        foreach(scandir($library) as $file) {

            if ($file == '.' || $file == '..')
                continue;

            if (is_dir($library . $file))
                continue;

            // Ignore thumbnails
            if (strstr($file, 'tn_') || strstr($file, 'bg_'))
                continue;
            
            // Is this file in the system anywhere?
            if (!array_key_exists($file, $media)) {
                // Totally missing
                Debug::Audit('Deleting file: ' . $file);
                
                // If not, delete it
                $mediaObject->DeleteMediaFile($file);
            }
            else if (array_key_exists($file, $unusedMedia)) {
                // It exists but isn't being used any more
                Debug::Audit('Deleting media: ' . $media[$file]['mediaid']);
                $mediaObject->Delete($media[$file]['mediaid']);
            }
            else {
                // Don't do anything, this file still exists
                //Debug::Audit('Still exists: ' . $file);
            }
        }

        return true;
    }
}
?>
