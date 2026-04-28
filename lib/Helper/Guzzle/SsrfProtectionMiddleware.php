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
     * @param array $config Middleware configuration options.
     * Example: ['allow_local_network' => true]
     */
    public static function create(array $config = []): callable
    {
        $allowLocalNetwork = $config['allow_local_network'] ?? false;

        return function (callable $handler) use ($allowLocalNetwork): callable {
            return function (RequestInterface $request, array $options) use ($handler, $allowLocalNetwork) {
                $uri = $request->getUri();

                // 1. Strict Scheme Validation
                $scheme = strtolower($uri->getScheme());
                if (!in_array($scheme, ['http', 'https'])) {
                    throw new \InvalidArgumentException('Invalid scheme: Only HTTP and HTTPS are allowed.');
                }

                $host = $uri->getHost();
                $port = $uri->getPort() ?: ($scheme === 'https' ? 443 : 80);

                // Strip brackets if the user provided a static IPv6 literal (e.g., [2001:db8::1])
                $cleanHost = trim($host, '[]');

                // Check to see if the host provided is an IP aaddress
                if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
                    // It's a static IP (IPv4 or IPv6), no DNS lookup needed
                    $ip = $cleanHost;
                } else {
                    // Not an IP, therefore a hostname
                    // Try IPv4 (A record) first
                    $ip = gethostbyname($cleanHost);

                    if ($ip === $cleanHost) {
                        // Fallback to IPv6 (AAAA record) using dns_get_record
                        $records = dns_get_record($cleanHost, DNS_AAAA);
                        if (!empty($records) && isset($records[0]['ipv6'])) {
                            $ip = $records[0]['ipv6'];
                        } else {
                            throw new \RuntimeException('DNS resolution failed for hostname: ' . $cleanHost);
                        }
                    }
                }

                // Validate the IP Address against a blocklist
                if (!self::isSafeIp($ip, $allowLocalNetwork)) {
                    throw new \RuntimeException('SSRF Protection triggered. Blocked request to restricted IP: ' . $ip);
                }

                // Pin the validated IP to prevent DNS Rebinding on this specific hop
                // This tells cURL to bypass its own DNS lookup and use our validated IP
                // CURLOPT_RESOLVE = 10203
                $options['curl'][10203] = [$cleanHost . ':' . $port . ':' . $ip];

                // 5. Pass the modified request and options down to the cURL handler
                return $handler($request, $options);
            };
        };
    }

    /**
     * Validates that an IP is publicly routable and not restricted.
     */
    private static function isSafeIp(string $ip, bool $allowLocalNetwork): bool
    {
        // PHP's built-in filter securely handles Loopback, IPv4 RFC 1918,
        // and IPv6 ULA (which natively blocks the AWS IPv6 metadata endpoint fd00:ec2::254)
        // Base flags: Always block Reserved ranges (e.g., 240.0.0.0/4)
        $flags = FILTER_FLAG_NO_RES_RANGE;

        // If local networks are NOT allowed, add the private range block flag
        if (!$allowLocalNetwork) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
            return false;
        }

        // Defense in Depth: Explicit Blocks
        $ipLong = ip2long($ip);

        if ($ipLong !== false) {
            // It's an IPv4 address
            // ALWAYS Block Loopback (127.0.0.0/8) - Never trust local application access
            if (($ipLong & ip2long('255.0.0.0')) === ip2long('127.0.0.0')) {
                return false;
            }

            // ALWAYS Block Cloud Metadata (169.254.0.0/16)
            if (($ipLong & ip2long('255.255.0.0')) === ip2long('169.254.0.0')) {
                return false;
            }

            // ALWAYS Block Current Network (0.0.0.0/8)
            if (($ipLong & ip2long('255.0.0.0')) === ip2long('0.0.0.0')) {
                return false;
            }
        } else {
            // It's an IPv6 address
            $packedIp = inet_pton($ip);
            if ($packedIp !== false) {
                // ALWAYS Block IPv6 Loopback (::1)
                if ($packedIp === inet_pton('::1')) {
                    return false;
                }

                // ALWAYS Block AWS IPv6 Metadata Endpoint (fd00:ec2::254)
                // When allow_local_network is true, PHP allows the ULA space (fc00::/7).
                // AWS uses a ULA address for metadata, so we must explicitly block it.
                if ($packedIp === inet_pton('fd00:ec2::254')) {
                    return false;
                }
            }
        }

        return true;
    }
}
