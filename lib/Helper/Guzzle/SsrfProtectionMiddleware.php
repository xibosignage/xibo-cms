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

namespace Xibo\Helper\Guzzle;

use Psr\Http\Message\RequestInterface;

/**
 * SsrfProtection
 * Middleware for Guzzle
 */
class SsrfProtectionMiddleware
{
    /**
     * Creates a Guzzle middleware closure.
     */
    public static function create(): callable
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler) {
                $uri = $request->getUri();

                // 1. Strict Scheme Validation
                $scheme = strtolower($uri->getScheme());
                if (!in_array($scheme, ['http', 'https'])) {
                    throw new \InvalidArgumentException('Invalid scheme: Only HTTP and HTTPS are allowed.');
                }

                $host = $uri->getHost();
                $port = $uri->getPort() ?: ($scheme === 'https' ? 443 : 80);

                // Resolve Hostname to IP Address
                $ip = gethostbyname($host);

                if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
                    throw new \RuntimeException('DNS resolution failed for hostname: ' . $host);
                }

                // Validate the IP Address against a blocklist
                if (!self::isSafeIp($ip)) {
                    throw new \RuntimeException('SSRF Protection triggered. Blocked request to restricted IP: ' . $ip);
                }

                // Pin the validated IP to prevent DNS Rebinding on this specific hop
                // This tells cURL to bypass its own DNS lookup and use our validated IP
                // CURLOPT_RESOLVE = 10203
                $options['curl'][10203] = [$host . ':' . $port . ':' . $ip];

                // 5. Pass the modified request and options down to the cURL handler
                return $handler($request, $options);
            };
        };
    }

    /**
     * Validates that an IP is publicly routable and not restricted.
     */
    private static function isSafeIp(string $ip): bool
    {
        // Block Loopback (127.x.x.x) and RFC 1918 Private Networks (10.x, 172.16.x, 192.168.x)
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        // Block 169.254.0.0/16 (AWS/GCP/Azure Cloud Metadata)
        if (($ipLong & ip2long('255.255.0.0')) === ip2long('169.254.0.0')) {
            return false;
        }

        // Block 0.0.0.0/8 (Current network / localhost bypass)
        if (($ipLong & ip2long('255.0.0.0')) === ip2long('0.0.0.0')) {
            return false;
        }

        return true;
    }
}
