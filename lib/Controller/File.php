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
namespace Xibo\Controller;

use Exception;
use finfo;
use InvalidArgumentException;
use Xibo\Helper\Config;
use Xibo\Helper\Log;

class File extends Base
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
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('INSERT INTO file (CreatedDT, UserID) VALUES (:createddt, :userid)');
            $sth->execute(array(
                'createddt' => time(),
                'userid' => $userId
            ));

            $fileId = $dbh->lastInsertId();

            if (!$this->WriteToDisk($fileId, $payload))
                throw new Exception('Unable to WriteToDisk');

            return $fileId;
        } catch (Exception $e) {

            Log::error($e->getMessage());

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
            $dbh = \Xibo\Storage\PDOConnect::init();

            // Directory location
            $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');
            $libraryFolder = $libraryFolder . 'temp';

            // Append should only be called on existing files, if this file does not exist then we
            // need to error accordingly.
            if (!file_exists($libraryFolder . '/' . $fileId))
                $this->ThrowError(7);

            return $this->WriteToDisk($fileId, $payload);
        } catch (Exception $e) {

            Log::error($e->getMessage());

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
            $dbh = \Xibo\Storage\PDOConnect::init();

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
        } catch (Exception $e) {

            Log::error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            return false;
        }
    }

    /**
     * Get the Path to a file
     * @param int $fileId The File ID
     */
    public function GetPath($fileId)
    {

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
        $libraryFolder = Config::GetSetting("LIBRARY_LOCATION");
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
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('INSERT INTO file (CreatedDT, UserID) VALUES (:createddt, :userid)');
            $sth->execute(array(
                'createddt' => time(),
                'userid' => $userId
            ));

            $fileId = $dbh->lastInsertId();

            return $fileId;
        } catch (Exception $e) {

            Log::error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(3, __('Unknown Error'));

            return false;
        }
    }

    public static function EnsureLibraryExists()
    {
        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

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
            throw new Exception('Library not writable');

        return true;
    }

    public static function GetLibraryCacheUri()
    {
        return Config::GetSetting('LIBRARY_LOCATION') . '/cache';
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
        if (Config::GetSetting('PROXY_HOST') != '') {
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

    /**
     * Library Usage
     * @return int
     */
    public static function libraryUsage()
    {
        $results = \Xibo\Storage\PDOConnect::select('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', array());

        return \Xibo\Helper\Sanitize::int($results[0]['SumSize']);
    }

    /**
     * Return file based media items to the browser for Download/Preview
     * @param string $fileName
     * @param string $downloadFilename
     */
    public static function ReturnFile($fileName = '', $downloadFilename = '')
    {
        // Check we have a file name
        if ($fileName == '')
            throw new InvalidArgumentException(__('Filename not provided'));

        // What has been requested
        $proportional = \Kit::GetParam('proportional', _GET, _BOOL, true);
        $thumb = \Kit::GetParam('thumb', _GET, _BOOL, false);
        $dynamic = isset($_REQUEST['dynamic']);
        $width = \Kit::GetParam('width', _REQUEST, _INT, 80);
        $height = \Kit::GetParam('height', _REQUEST, _INT, 80);
        $download = \Kit::GetParam('download', _REQUEST, _BOOLEAN, false);
        $downloadFromLibrary = \Kit::GetParam('downloadFromLibrary', _REQUEST, _BOOLEAN, false);

        if ($downloadFromLibrary && $downloadFilename == '') {
            throw new InvalidArgumentException(__('Download Filename not provided'));
        }

        // Get the name with library
        $libraryLocation = Config::GetSetting('LIBRARY_LOCATION');
        $libraryPath = $libraryLocation . $fileName;

        // Are we requesting a thumbnail - if so then cache it for later use
        if ($thumb) {
            $thumbPath = $libraryLocation . sprintf('tn_%dx%d_%s', $width, $height, $fileName);

            // If the thumbnail doesn't exist then create one
            if (!file_exists($thumbPath)) {
                Log::notice('File doesn\'t exist, creating a thumbnail for ' . $fileName);

                if (!$info = getimagesize($libraryPath))
                    die($libraryPath . ' is not an image');

                // Save the thumbnail
                ResizeImage($libraryPath, $thumbPath, $width, $height, $proportional, 'file');
            }

            // From now onwards operate on the thumbnail
            $libraryPath = $thumbPath;
        }

        if ($dynamic || $thumb) {

            // Get the info for this new temporary file
            if (!$info = getimagesize($libraryPath)) {
                echo $libraryPath . ' is not an image';
                exit;
            }

            if ($dynamic && !$thumb && $info[2]) {
                $width = \Xibo\Helper\Sanitize::getInt('width');
                $height = \Xibo\Helper\Sanitize::getInt('height');

                // dynamically create an image of the correct size - used for previews
                ResizeImage($libraryPath, '', $width, $height, $proportional, 'browser');

                exit;
            }
        }

        $size = filesize($libraryPath);

        if ($download) {
            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary");
            header("Content-disposition: attachment; filename=\"" . (($downloadFromLibrary) ? $downloadFilename : basename($fileName)) . "\"");
        } else {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($libraryPath);
            header("Content-Type: {$mime}");
        }

        //Output a header
        header('Pragma: public');
        header('Cache-Control: max-age=86400');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
        header('Content-Length: ' . $size);

        // Send via Apache X-Sendfile header?
        if (Config::GetSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $libraryPath");
            exit();
        }

        // Send via Nginx X-Accel-Redirect?
        if (Config::GetSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/" . basename($fileName));
            exit();
        }

        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        @ob_end_clean();
        readfile($libraryPath);
        exit();
    }
}
