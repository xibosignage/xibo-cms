<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

use GuzzleHttp\Psr7\Stream;
use Slim\Http\Response;

/**
 * Class SendFile
 * @package Xibo\Helper
 */
class SendFile
{
    /**
     * @param \Slim\Http\Response $response
     * @param string $sendFile
     * @param string $filePath
     * @param string|null $name
     * @param bool $zlibOff
     * @return \Slim\Http\Response
     */
    public static function decorateResponse($response, $sendFile, $filePath, $name = null, $zlibOff = true):? Response
    {
        if ($zlibOff && ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        $baseName = basename($filePath);
        $response = $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename=' . ($name === null ? $baseName : $name))
            ->withHeader('Content-Transfer-Encoding', 'Binary')
            ->withHeader('Content-Length', filesize($filePath));

        // Send via Apache X-Sendfile header?
        if ($sendFile == 'Apache') {
            $response = $response->withHeader('X-Sendfile', $filePath);
        } else if ($sendFile == 'Nginx') {
            // Send via Nginx X-Accel-Redirect?
            $response = $response->withHeader('X-Accel-Redirect', '/download/temp/' . $baseName);
        } else {
            $response = $response->withBody(new Stream(fopen($filePath, 'r')));
        }

        return $response;
    }
}