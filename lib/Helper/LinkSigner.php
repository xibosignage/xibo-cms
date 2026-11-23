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

namespace Xibo\Helper;

use Xibo\Entity\Display;

/**
 * S3 style links
 *  inspired by https://gist.github.com/kelvinmo/d78be66c4f36415a6b80
 */
class LinkSigner
{
    /**
     * @param \Xibo\Entity\Display $display
     * @param string $encryptionKey
     * @param string|null $cdnUrl
     * @param $type
     * @param $itemId
     * @param string $storedAs
     * @param string|null $fileType
     * @param bool $isRequestFromPwa
     * @param string|null $baseUrl the CMS address to link to, instead of the address of this request
     * @return string
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public static function generateSignedLink(
        Display $display,
        string $encryptionKey,
        ?string $cdnUrl,
        $type,
        $itemId,
        string $storedAs,
        ?string $fileType = null,
        bool $isRequestFromPwa = false,
        ?string $baseUrl = null,
    ): string {
        if ($baseUrl !== null) {
            $baseUrl = rtrim($baseUrl, '/');
        } else {
            // Start with the base url, which should correctly account for running with a CMS_ALIAS.
            // getBaseUrl() uses $_SERVER['SCRIPT_NAME'] to find the CMS root, but when called during
            // a PWA request it includes /pwa in the path. Strip it here since we have no request object
            // to pass to the entry-point stripping logic inside getBaseUrl().
            $baseUrl = (new HttpsDetect())->getBaseUrl();
            if ($isRequestFromPwa && str_ends_with($baseUrl, '/pwa')) {
                $baseUrl = substr($baseUrl, 0, -4);
            }
        }
        $xmdsRoot = $baseUrl . '/xmds.php';

        // Build the rest of the URL
        $saveAsPath = $xmdsRoot
            . '?file=' . $storedAs
            . '&displayId=' . $display->displayId
            . '&type=' . $type
            . '&itemId=' . $itemId;

        if ($fileType !== null) {
            $saveAsPath .= '&fileType=' . $fileType;
        }

        $saveAsPath .= '&' . LinkSigner::getSignature(
            parse_url($xmdsRoot, PHP_URL_HOST),
            self::getXmdsResource($storedAs, $display->displayId, $type, $itemId, $fileType),
            time() + self::XMDS_LINK_TTL,
            $encryptionKey,
        );

        // CDN?
        if (!empty($cdnUrl)) {
            // Serve a link to the CDN
            // CDN_URL has a `?dl=` parameter on the end already, so we just encode our string and concatenate it
            return 'http' . (
                (
                    (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ||
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                        && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
                ) ? 's' : '')
                . '://' . $cdnUrl . urlencode($saveAsPath);
        } else {
            // Serve a HTTP link to XMDS
            return $saveAsPath;
        }
    }

    /** How long an xmds.php link lasts, as it always has. */
    public const XMDS_LINK_TTL = 600;

    /**
     * What an xmds.php link signs: the file and every parameter xmds.php uses to pick the file it serves, so none of
     * them can be changed to download another display's file.
     */
    public static function getXmdsResource($storedAs, $displayId, $type, $itemId, $fileType): string
    {
        return json_encode(
            array_map(fn ($value) => (string)$value, [$storedAs, $displayId, $type, $itemId, $fileType ?? '']),
            JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * When a direct storage link handed to $display expires: two collect intervals from now, kept between 10 minutes
     * and 6 days.
     */
    public static function getExpiry(Display $display): int
    {
        return time() + min(max(600, 2 * (int)$display->getSetting('collectInterval', 300)), 6 * 86400);
    }

    /**
     * Get a S3 compatible signature
     */
    public static function getSignature(
        string $host,
        string $uri,
        int $expires,
        string $secretKey,
        ?string $timeText = null,
        ?bool $isReturnSignature = false
    ): string {
        $encodedUri = str_replace('%2F', '/', rawurlencode($uri));
        $headerString = 'host:' . $host . "\n";
        $signedHeadersString = 'host';

        if ($timeText === null) {
            $timestamp = time();
            $dateText = gmdate('Ymd', $timestamp);
            $timeText = $dateText . 'T000000Z';
        } else {
            $dateText = explode('T', $timeText)[0];
        }

        $algorithm = 'AWS4-HMAC-SHA256';
        $scope = $dateText . '/all/s3/aws4_request';

        $amzParams = [
            'X-Amz-Algorithm' => $algorithm,
            'X-Amz-Date' => $timeText,
            'X-Amz-SignedHeaders' => $signedHeadersString
        ];
        if ($expires > 0) {
            $amzParams['X-Amz-Expires'] = $expires;
        }
        ksort($amzParams);

        $queryStringItems = [];
        foreach ($amzParams as $key => $value) {
            $queryStringItems[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $queryString = implode('&', $queryStringItems);

        $request = 'GET' . "\n" . $encodedUri . "\n" . $queryString . "\n" . $headerString . "\n"
            . $signedHeadersString . "\nUNSIGNED-PAYLOAD";
        $stringToSign = $algorithm . "\n" . $timeText . "\n" . $scope . "\n" . hash('sha256', $request);
        $signingKey = hash_hmac(
            'sha256',
            'aws4_request',
            hash_hmac(
                'sha256',
                's3',
                hash_hmac(
                    'sha256',
                    'all',
                    hash_hmac(
                        'sha256',
                        $dateText,
                        'AWS4' . $secretKey,
                        true
                    ),
                    true
                ),
                true
            ),
            true
        );
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return ($isReturnSignature) ? $signature : $queryString . '&X-Amz-Signature=' . $signature;
    }
}
