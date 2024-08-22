<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Helper;

use Psr\Log\LoggerInterface;

/**
 * Heavily modified BlueImp Upload handler, stripped out image processing, downloads, etc.
 * jQuery File Upload Plugin PHP Class 6.4.2
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */
class BlueImpUploadHandler
{
    protected array $options;

    // PHP File Upload error message codes:
    // http://php.net/manual/en/features.file-upload.errors.php
    private array $errorMessages = [
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk',
        8 => 'A PHP extension stopped the file upload',
        'post_max_size' => 'The uploaded file exceeds the post_max_size directive in php.ini',
        'accept_file_types' => 'Filetype not allowed',
    ];

    /**
     * @param string $uploadDir
     * @param \Psr\Log\LoggerInterface $logger
     * @param array $options
     * @param bool $initialize
     */
    public function __construct(
        string $uploadDir,
        private readonly LoggerInterface $logger,
        array $options = [],
        bool $initialize = true,
    ) {
        $this->options = array_merge([
            'upload_dir' => $uploadDir,
            'access_control_allow_origin' => '*',
            'access_control_allow_methods' => array(
                'OPTIONS',
                'HEAD',
                'GET',
                'POST',
                'PUT',
                'PATCH',
                'DELETE'
            ),
            'access_control_allow_headers' => array(
                'Content-Type',
                'Content-Range',
                'Content-Disposition'
            ),
            // Defines which files can be displayed inline when downloaded:
            'inline_file_types' => '/\.(gif|jpe?g|png)$/i',
            // Defines which files (based on their names) are accepted for upload:
            'accept_file_types' => '/.+$/i',
            // Set the following option to false to enable resumable uploads:
            'discard_aborted_uploads' => true,
        ], $options);

        if ($initialize) {
            $this->initialize();
        }
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    private function initialize(): void
    {
        switch ($this->getServerVar('REQUEST_METHOD')) {
            case 'OPTIONS':
            case 'HEAD':
                $this->head();
                break;
            case 'PATCH':
            case 'PUT':
            case 'POST':
                $this->post();
                break;
            default:
                $this->header('HTTP/1.1 405 Method Not Allowed');
        }
    }

    /**
     * Get the upload directory
     * @return string
     */
    protected function getUploadDir(): string
    {
        return $this->options['upload_dir'];
    }

    /**
     * @param $fileName
     * @param $version
     * @return string
     */
    private function getUploadPath($fileName = null, $version = null): string
    {
        $this->getLogger()->debug('getUploadPath: ' . $fileName);

        $fileName = $fileName ?: '';
        $versionPath = empty($version) ? '' : $version . '/';
        return $this->options['upload_dir'] . $versionPath . $fileName;
    }

    /**
     * Fix for overflowing signed 32-bit integers,
     * works for sizes up to 2^32-1 bytes (4 GiB - 1):
     * @param $size
     * @return int
     */
    private function fixIntegerOverflow($size): int
    {
        if ($size < 0) {
            $size += 2.0 * (PHP_INT_MAX + 1);
        }
        return $size;
    }

    /**
     * @param string $filePath
     * @param bool $clearStatCache
     * @return int
     */
    private function getFileSize(string $filePath, bool $clearStatCache = false): int
    {
        if ($clearStatCache) {
            clearstatcache(true, $filePath);
        }
        return $this->fixIntegerOverflow(filesize($filePath));
    }

    /**
     * @param $error
     * @return string
     */
    private function getErrorMessage($error): string
    {
        return $this->errorMessages[$error] ?? $error;
    }

    /**
     * @param $val
     * @return float|int
     */
    private function getConfigBytes($val): float|int
    {
        return $this->fixIntegerOverflow(ByteFormatter::toBytes($val));
    }

    /**
     * @param $file
     * @param $error
     * @return bool
     */
    private function validate($file, $error): bool
    {
        if ($error) {
            $file->error = $this->getErrorMessage($error);
            return false;
        }

        // Make sure the content length isn't greater than the max size
        $contentLength = $this->fixIntegerOverflow(intval($this->getServerVar('CONTENT_LENGTH')));
        $postMaxSize = $this->getConfigBytes(ini_get('post_max_size'));
        if ($postMaxSize && ($contentLength > $postMaxSize)) {
            $file->error = $this->getErrorMessage('post_max_size');
            return false;
        }

        // Max sure the we are an accepted file type
        if (!preg_match($this->options['accept_file_types'], $file->name)) {
            $file->error = $this->getErrorMessage('accept_file_types');
            return false;
        }
        return true;
    }

    private function upcountName(string $name): string
    {
        $this->getLogger()->debug('upcountName: ' . $name);
        return preg_replace_callback(
            '/(?:(?: \(([\d]+)\))?(\.[^.]+))?$/',
            function ($matches): string {
                $this->getLogger()->debug('upcountName: callback, matches: ' . var_export($matches, true));
                $index = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
                $ext = $matches[2] ?? '';
                return ' (' . $index . ')' . $ext;
            },
            $name,
            1
        );
    }

    /**
     * @param $name
     * @param $contentRange
     * @return string
     */
    private function getUniqueFilename($name, $contentRange): string
    {
        $uploadPath = $this->getUploadPath($name);

        $this->getLogger()->debug('getUniqueFilename: ' . $name . ', uploadPath: ' . $uploadPath
            . ', contentRange: ' . $contentRange);

        $attempts = 0;
        while (is_dir($uploadPath) && $attempts < 100) {
            $name = $this->upcountName($name);
            $attempts++;
        }

        $this->getLogger()->debug('getUniqueFilename: resolved file path: ' . $name);

        $contentRange = $contentRange === null ? 0 : $contentRange[1];

        // Keep an existing filename if this is part of a chunked upload:
        $uploaded_bytes = $this->fixIntegerOverflow($contentRange);
        while (is_file($this->getUploadPath($name))) {
            if ($uploaded_bytes === $this->getFileSize($this->getUploadPath($name))) {
                break;
            }
            $name = $this->upcountName($name);
        }
        return $name;
    }

    /**
     * @param $name
     * @param $type
     * @return string
     */
    private function trimFileName($name, $type): string
    {
        // Remove path information and dots around the filename, to prevent uploading
        // into different directories or replacing hidden system files.
        // Also remove control characters and spaces (\x00..\x20) around the filename:
        $name = trim(basename(stripslashes($name)), ".\x00..\x20");
        // Use a timestamp for empty filenames:
        if (!$name) {
            $name = str_replace('.', '-', microtime(true));
        }
        // Add missing file extension for known image types:
        if (!str_contains($name, '.')
            && preg_match('/^image\/(gif|jpe?g|png)/', $type, $matches)
        ) {
            $name .= '.' . $matches[1];
        }
        return $name;
    }

    /**
     * @param string $name
     * @param string $type
     * @param int|null $contentRange
     * @return string
     */
    private function getFileName(string $name, string $type, ?int $contentRange): string
    {
        $this->getLogger()->debug('getFileName: ' . $name . ', type: ' . $type);

        return $this->getUniqueFilename(
            $this->trimFileName($name, $type),
            $contentRange
        );
    }

    /**
     * @param $uploadedFile
     * @param $name
     * @param $size
     * @param $type
     * @param $error
     * @param $index
     * @param $contentRange
     * @return \stdClass
     */
    private function handleFileUpload(
        $uploadedFile,
        $name,
        $size,
        $type,
        $error,
        $index = null,
        $contentRange = null
    ) {
        $this->getLogger()->debug('handleFileUpload: ' . $uploadedFile);

        // Build a file object to return.
        $file = new \stdClass();
        $file->name = $this->getFileName($name, $type, $contentRange);
        $file->size = $this->fixIntegerOverflow(intval($size));
        $file->type = $type;

        if ($this->validate($file, $error)) {
            $uploadPath = $this->getUploadPath();
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $filePath = $this->getUploadPath($file->name);

            // Are we appending?
            $appendFile = $contentRange && is_file($filePath) && $file->size > $this->getFileSize($filePath);

            if ($uploadedFile && is_uploaded_file($uploadedFile)) {
                // multipart/formdata uploads (POST method uploads)
                if ($appendFile) {
                    file_put_contents(
                        $filePath,
                        fopen($uploadedFile, 'r'),
                        FILE_APPEND
                    );
                } else {
                    move_uploaded_file($uploadedFile, $filePath);
                }
            } else {
                // Non-multipart uploads (PUT method support)
                file_put_contents(
                    $filePath,
                    fopen('php://input', 'r'),
                    $appendFile ? FILE_APPEND : 0
                );
            }
            $fileSize = $this->getFileSize($filePath, $appendFile);

            if ($fileSize === $file->size) {
                $this->handleFormData($file, $index);
            } else {
                $file->size = $fileSize;
                if (!$contentRange && $this->options['discard_aborted_uploads']) {
                    unlink($filePath);
                    $file->error = 'abort';
                }
            }
        }
        return $file;
    }

    /**
     * @param $file
     * @param $index
     * @return void
     */
    protected function handleFormData($file, $index)
    {
    }

    /**
     * @param string $str
     * @return void
     */
    private function header(string $str): void
    {
        header($str);
    }

    /**
     * @param $id
     * @return mixed|string
     */
    private function getServerVar($id): mixed
    {
        return $_SERVER[$id] ?? '';
    }

    private function sendContentTypeHeader(): void
    {
        $this->header('Vary: Accept');
        if (str_contains($this->getServerVar('HTTP_ACCEPT'), 'application/json')) {
            $this->header('Content-type: application/json');
        } else {
            $this->header('Content-type: text/plain');
        }
    }

    private function sendAccessControlHeaders(): void
    {
        $this->header('Access-Control-Allow-Origin: ' . $this->options['access_control_allow_origin']);
        $this->header('Access-Control-Allow-Methods: '
            . implode(', ', $this->options['access_control_allow_methods']));
        $this->header('Access-Control-Allow-Headers: '
            . implode(', ', $this->options['access_control_allow_headers']));
    }

    private function head(): void
    {
        $this->header('Pragma: no-cache');
        $this->header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->header('Content-Disposition: inline; filename="files.json"');
        // Prevent Internet Explorer from MIME-sniffing the content-type:
        $this->header('X-Content-Type-Options: nosniff');
        if ($this->options['access_control_allow_origin']) {
            $this->sendAccessControlHeaders();
        }
        $this->sendContentTypeHeader();
    }

    /**
     * @return void
     */
    public function post(): void
    {
        $upload = $_FILES['files'] ?? null;

        // Parse the Content-Disposition header, if available:
        $fileName = $this->getServerVar('HTTP_CONTENT_DISPOSITION') ?
            rawurldecode(preg_replace(
                '/(^[^"]+")|("$)/',
                '',
                $this->getServerVar('HTTP_CONTENT_DISPOSITION')
            )) : null;

        // Parse the Content-Range header, which has the following form:
        // Content-Range: bytes 0-524287/2000000
        $contentRange = $this->getServerVar('HTTP_CONTENT_RANGE')
            ? preg_split('/[^0-9]+/', $this->getServerVar('HTTP_CONTENT_RANGE'))
            : null;
        $size = $contentRange ? $contentRange[3] : null;

        $this->getLogger()->debug('post: contentRange: ' . var_export($contentRange, true));

        $files = [];
        if ($upload && is_array($upload['tmp_name'])) {
            // param_name is an array identifier like "files[]",
            // $_FILES is a multi-dimensional array:
            foreach ($upload['tmp_name'] as $index => $value) {
                $files[] = $this->handleFileUpload(
                    $upload['tmp_name'][$index],
                    $fileName ?: $upload['name'][$index],
                    $size ?: $upload['size'][$index],
                    $upload['type'][$index],
                    $upload['error'][$index],
                    $index,
                    $contentRange
                );
            }
        } else {
            // param_name is a single object identifier like "file",
            // $_FILES is a one-dimensional array:
            $files[] = $this->handleFileUpload(
                $upload['tmp_name'] ?? null,
                $fileName ?: ($upload['name'] ?? null),
                $size ?: ($upload['size'] ?? $this->getServerVar('CONTENT_LENGTH')),
                $upload['type'] ?? $this->getServerVar('CONTENT_TYPE'),
                $upload['error'] ?? null,
                null,
                $contentRange
            );
        }

        // Output response
        $json = json_encode(['files' => $files]);
        $this->head();
        if ($this->getServerVar('HTTP_CONTENT_RANGE')) {
            if ($files && is_array($files) && is_object($files[0]) && $files[0]->size) {
                $this->header('Range: 0-' . (
                        $this->fixIntegerOverflow(intval($files[0]->size)) - 1
                    ));
            }
        }
        echo $json;
    }
}
