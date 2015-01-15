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
}
?>
