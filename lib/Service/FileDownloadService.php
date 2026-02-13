<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

namespace Xibo\Service;

use Psr\Container\ContainerInterface;
use Xibo\Entity\Bandwidth;
use Xibo\Helper\LinkSigner;
use Xibo\Support\Exception\InstanceSuspendedException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Shared file download logic for XMDS signed URLs.
 *
 * Used by both web/xmds.php (SOAP transport) and Pwa::downloadFile() (REST transport).
 * Handles HMAC signature validation, bandwidth tracking, content-type detection,
 * CSS url() rewriting for PWA displays, and sendfile mode resolution.
 */
class FileDownloadService
{
    /**
     * Process a signed file download request.
     *
     * @param ContainerInterface $container DI container
     * @param array $params Request parameters (file, displayId, type, itemId, X-Amz-*)
     * @param string $sendFileMode 'Apache' or 'Nginx'
     * @return array{contentType: string, mode: string, body: ?string, path: ?string}
     *   mode is one of: 'css' (body contains rewritten CSS), 'sendfile' (Apache X-Sendfile),
     *   'accel' (Nginx X-Accel-Redirect)
     * @throws NotFoundException
     * @throws InstanceSuspendedException
     */
    public static function serve(ContainerInterface $container, array $params, string $sendFileMode): array
    {
        $logger = $container->get('logService');

        // Validate required params
        $displayId = intval($params['displayId'] ?? 0);
        $type = $params['type'] ?? '';
        $itemId = $params['itemId'] ?? '';
        $file = $params['file'] ?? '';

        if (empty($displayId) || empty($type) || empty($itemId)) {
            $logger->error('FileDownload: Missing params');
            throw new NotFoundException(__('Missing params'));
        }

        // Check URL expiration
        $expires = intval($params['X-Amz-Expires'] ?? 0);
        if (time() > $expires) {
            $logger->error('FileDownload: Expired URL');
            throw new NotFoundException(__('Expired'));
        }

        // Validate HMAC signature
        $encryptionKey = $container->get('configService')->getApiKeyDetails()['encryptionKey'];
        $signature = $params['X-Amz-Signature'] ?? '';
        $calculatedSignature = LinkSigner::getSignature(
            parse_url(\Xibo\Xmds\Wsdl::getRoot(), PHP_URL_HOST),
            $file,
            $expires,
            $encryptionKey,
            $params['X-Amz-Date'] ?? '',
            true,
        );

        if ($signature !== $calculatedSignature) {
            $logger->error('FileDownload: Invalid signature');
            throw new NotFoundException(__('Invalid URL'));
        }

        // Resolve the required file
        /** @var \Xibo\Factory\RequiredFileFactory $requiredFileFactory */
        $requiredFileFactory = $container->get('requiredFileFactory');
        $requiredFile = $requiredFileFactory->resolveRequiredFileFromRequest($params);

        // Check monthly bandwidth limit
        /** @var \Xibo\Factory\BandwidthFactory $bandwidthFactory */
        $bandwidthFactory = $container->get('bandwidthFactory');
        if ($bandwidthFactory->isBandwidthExceeded(
            $container->get('configService')->getSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB')
        )) {
            $logger->error('FileDownload: Bandwidth Exceeded');
            throw new InstanceSuspendedException('Bandwidth Exceeded');
        }

        // Get the display and check authorization
        /** @var \Xibo\Entity\Display $display */
        $display = $container->get('displayFactory')->getById($displayId);
        if ($display->licensed == 0) {
            $logger->error('FileDownload: Display unauthorised');
            throw new NotFoundException(__('Display unauthorised'));
        }

        // Check per-display bandwidth limit
        $usage = 0;
        if ($bandwidthFactory->isBandwidthExceeded(
            $display->bandwidthLimit,
            $usage,
            $displayId
        )) {
            $logger->error('FileDownload: Bandwidth Exceeded for Display ' . $displayId);
            throw new InstanceSuspendedException('Bandwidth Exceeded');
        }

        // Track file-level bandwidth
        $requiredFile->bytesRequested = $requiredFile->bytesRequested + $requiredFile->size;
        $requiredFile->save(['useTransaction' => false]);

        $libraryLocation = $container->get('configService')->getSetting('LIBRARY_LOCATION');
        $cdnUrl = $container->get('configService')->getSetting('CDN_URL');

        // Determine content type
        $isCss = false;
        $contentType = 'application/octet-stream';

        if ($requiredFile->type === 'L') {
            $contentType = 'text/xml';
        } elseif ($requiredFile->fileType === 'bundle'
            || \Illuminate\Support\Str::endsWith($requiredFile->path, '.js')
        ) {
            $contentType = 'application/javascript';
        } elseif ($requiredFile->fileType === 'fontCss'
            || \Illuminate\Support\Str::endsWith($requiredFile->path, '.css')
        ) {
            $isCss = true;
            $contentType = 'text/css';
        } else {
            $detected = mime_content_type($libraryLocation . $requiredFile->path);
            if ($detected !== false) {
                $contentType = $detected;
            }
        }

        // CSS rewriting for PWA displays: replace url() paths with signed URLs
        if ($display->isPwa() && $isCss) {
            $logger->debug('FileDownload: Rewriting CSS for PWA: ' . $requiredFile->path);

            $cssFile = file_get_contents($libraryLocation . $requiredFile->path);
            $matches = [];
            preg_match_all('/url\(\'?(.*?)\'?\)/', $cssFile, $matches);

            foreach ($matches[1] as $match) {
                try {
                    $replacementFile = $requiredFileFactory->getByDisplayAndDependencyPath(
                        $displayId,
                        $match,
                    );
                    $url = LinkSigner::generateSignedLink(
                        $display,
                        $encryptionKey,
                        $cdnUrl,
                        'P',
                        $replacementFile->realId,
                        $replacementFile->path,
                        $requiredFile->fileType === 'fontCss' ? 'font' : 'asset',
                    );
                    $cssFile = str_replace($match, $url, $cssFile);
                } catch (\Exception $e) {
                    $logger->error('CSS dependency not in Required Files: ' . $match);
                }
            }

            return [
                'contentType' => $contentType,
                'mode' => 'css',
                'body' => $cssFile,
                'path' => null,
            ];
        }

        // Normal file serving via sendfile
        $logger->info('FileDownload: serving ' . $libraryLocation . $requiredFile->path);

        // Track overall bandwidth (non-CSS only, matching original xmds.php behavior)
        $bandwidthFactory->createAndSave(
            Bandwidth::$GETFILE,
            $requiredFile->displayId,
            $requiredFile->size,
        );

        if ($sendFileMode === 'Apache') {
            return [
                'contentType' => $contentType,
                'mode' => 'sendfile',
                'body' => null,
                'path' => $libraryLocation . $requiredFile->path,
            ];
        } elseif ($sendFileMode === 'Nginx') {
            return [
                'contentType' => $contentType,
                'mode' => 'accel',
                'body' => null,
                'path' => '/download/' . $requiredFile->path,
            ];
        }

        throw new NotFoundException(__('Send file mode not configured'));
    }
}
