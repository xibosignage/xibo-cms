<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

/**
 * S3 style links
 *  inspired by https://gist.github.com/kelvinmo/d78be66c4f36415a6b80
 */
class LinkSigner
{
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
