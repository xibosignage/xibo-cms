<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Service;

use GuzzleHttp\Psr7\Stream;
use Psr\Log\LoggerInterface;
use Xibo\Helper\HttpCacheProvider;

class DownloadService
{
    /** @var string File path inside the library folder */
    private $filePath;

    /** @var string Send file mode */
    private $sendFileMode;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param string $filePath
     * @param string $sendFileMode
     */
    public function __construct(
        string $filePath,
        string $sendFileMode
    ) {
        $this->filePath = $filePath;
        $this->sendFileMode = $sendFileMode;
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @return $this
     */
    public function useLogger(LoggerInterface $logger): DownloadService
    {
        $this->logger = $logger;
        return $this;
    }

    public function returnFile($response, $attachmentName, $nginxRedirect)
    {
        // Issue some headers
        $response = HttpCacheProvider::withEtag($response, $this->filePath);
        $response = HttpCacheProvider::withExpires($response, '+1 week');
        // Set some headers
        $headers = [];
        $headers['Content-Length'] = filesize($this->filePath);
        $headers['Content-Type'] = 'application/octet-stream';
        $headers['Content-Transfer-Encoding'] = 'Binary';
        $headers['Content-disposition'] = 'attachment; filename="' . $attachmentName . '"';

        // Output the file
        if ($this->sendFileMode === 'Apache') {
            // Send via Apache X-Sendfile header?
            $headers['X-Sendfile'] = $this->filePath;
        } else if ($this->sendFileMode === 'Nginx') {
            // Send via Nginx X-Accel-Redirect?
            $headers['X-Accel-Redirect'] = $nginxRedirect;
        }

        // Add the headers we've collected to our response
        foreach ($headers as $header => $value) {
            $response = $response->withHeader($header, $value);
        }

        // Should we output the file via the application stack, or directly by reading the file.
        if ($this->sendFileMode == 'Off') {
            // Return the file with PHP
            $response = $response->withBody(new Stream(fopen($this->filePath, 'r')));

            $this->logger->debug('Returning Stream with response body, sendfile off.');
        } else {
            $this->logger->debug('Using sendfile to return the file, only output headers.');
        }

        return $response;
    }
}
