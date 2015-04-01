<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2010-13 Daniel Garner
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
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('INSERT INTO file (CreatedDT, UserID) VALUES (:createddt, :userid)');
            $sth->execute(array(
                    'createddt' => time(),
                    'userid' => $userId
                ));

            $fileId = $dbh->lastInsertId();

            if (!$this->WriteToDisk($fileId, $payload))
                throw new Exception('Unable to WriteToDisk');
        
            return $fileId;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(3);
        
            return false;
        }
    }

    /**
     * Appends the next chunk to the file
     * @param <type> $fileId
     * @param <type> $payload
     * @param <type> $userId
     */
    public function Append($fileId, $payload)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Directory location
            $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');
            $libraryFolder = $libraryFolder . 'temp';
    
            // Append should only be called on existing files, if this file does not exist then we
            // need to error accordingly.
            if (!file_exists($libraryFolder . '/' . $fileId))
                $this->ThrowError(7);
        
            return $this->WriteToDisk($fileId, $payload);  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Writes the file to disk
     * @param <type> $fileId
     * @param <type> $payload
     */
    public function WriteToDisk($fileId, $payload)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Directory location
            $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');
            $libraryFolder = $libraryFolder . 'temp';
    
            if (!File::EnsureLibraryExists($libraryFolder))
                return false;
    
            // Open a file pointer
            if (!$fp = fopen($libraryFolder . '/' . $fileId, 'a'))
                $this->ThrowError(5);
    
            // Write the payload to the file handle.
            if (fwrite($fp, $payload) === false)
                $this->ThrowError(6);
    
            // Close the file pointer
            fclose($fp);
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Get the Path to a file
     * @param int $fileId The File ID
     */
    public function GetPath($fileId) {

        if ($fileId == '' || $fileId == 0)
            return $this->SetError(25001, __('Missing fileId'));

        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');
        $libraryFolder = $libraryFolder . 'temp';
        return $libraryFolder . DIRECTORY_SEPARATOR . $fileId;
    }

    /**
     * The current size of a file
     * @param <type> $fileId
     * @return <int> filesize
     */
    public function Size($fileId)
    {
        // Directory location
        $libraryFolder 	= Config::GetSetting("LIBRARY_LOCATION");
        $libraryFolder = $libraryFolder . 'temp';

        return filesize($libraryFolder . '/' . $fileId);
    }

    /**
     * Generates a fileid
     * @param <type> $userId
     */
    public function GenerateFileId($userId)
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('INSERT INTO file (CreatedDT, UserID) VALUES (:createddt, :userid)');
            $sth->execute(array(
                    'createddt' => time(),
                    'userid' => $userId
                ));

            $fileId = $dbh->lastInsertId();

            return $fileId;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(3, __('Unknown Error'));
        
            return false;
        }
    }

    public static function EnsureLibraryExists()
    {
        $libraryFolder 	= Config::GetSetting('LIBRARY_LOCATION');

        // Check that this location exists - and if not create it..
        if (!file_exists($libraryFolder))
            mkdir($libraryFolder, 0777, true);

        if (!file_exists($libraryFolder . '/temp'))
            mkdir($libraryFolder . '/temp', 0777, true);

        if (!file_exists($libraryFolder . '/cache'))
            mkdir($libraryFolder . '/cache', 0777, true);

        if (!file_exists($libraryFolder . '/screenshots'))
            mkdir($libraryFolder . '/screenshots', 0777, true);

        // Check that we are now writable - if not then error
        if (!is_writable($libraryFolder))
        {
            Debug::Error('Library not writable');
            return false;
        }

        return true;
    }

    public function GetLibraryCacheUri() {

        $libraryFolder  = Config::GetSetting('LIBRARY_LOCATION');

        return $libraryFolder . '/cache';
    }

    /**
     * Download a file
     * @param string $url
     * @param string $savePath
     */
    public static function downloadFile($url, $savePath)
    {
        // Use CURL to download a file
        // Open the file handle
        $fileHandle = fopen($savePath, 'w+');

        // Configure CURL with the file handle
        $httpOptions = array(
            CURLOPT_TIMEOUT => 50,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Xibo Digital Signage',
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fileHandle
        );

        // Proxy support
        if (Config::GetSetting('PROXY_HOST') != '' && !Config::isProxyException($url)) {
            $httpOptions[CURLOPT_PROXY] = Config::GetSetting('PROXY_HOST');
            $httpOptions[CURLOPT_PROXYPORT] = Config::GetSetting('PROXY_PORT');

            if (Config::GetSetting('PROXY_AUTH') != '')
                $httpOptions[CURLOPT_PROXYUSERPWD] = Config::GetSetting('PROXY_AUTH');
        }

        $curl = curl_init();

        // Set our options
        curl_setopt_array($curl, $httpOptions);

        // Exec saves the file
        curl_exec($curl);

        // Close the curl connection
        curl_close($curl);

        // Close the file handle
        fclose($fileHandle);
    }
}
?>
