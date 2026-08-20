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

namespace Xibo\Helper;

/**
 * Matches an IP address against an operator-configured trust list (ConfigServiceInterface::
 * getTrustedProxyIpList()) containing a mix of exact IPs, IPv4 CIDR ranges (e.g. 172.18.0.0/16),
 * and wildcard patterns (e.g. 10.0.*.*) — the single authoritative matcher used by both
 * TrustedProxyIpAddress (resolving X-Forwarded-For chains) and the simpler single-address checks
 * in HttpsDetect::isShouldIssueSts(), Session::getIp(), and Xmds\Soap::getIp(), so a CIDR/wildcard
 * entry behaves identically everywhere it's consulted.
 */
class IpTrust
{
    /**
     * @param string[] $trustedList
     */
    public static function isTrusted(string $ip, array $trustedList): bool
    {
        foreach ($trustedList as $entry) {
            if (str_contains($entry, '/')) {
                if (self::matchesCidr($ip, $entry)) {
                    return true;
                }
            } elseif (str_contains($entry, '*')) {
                if (self::matchesWildcard($ip, $entry)) {
                    return true;
                }
            } elseif ($ip === $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * IPv4 only — matching the same limitation the previous RKA\Middleware\IpAddress-based
     * implementation had ("Only IPv4 is supported for CIDR matching").
     */
    private static function matchesCidr(string $ip, string $cidr): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false || !str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $subnetLong = ip2long($subnet);
        if ($subnetLong === false || !is_numeric($bits) || (int) $bits < 0 || (int) $bits > 32) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);
        $min = $subnetLong & $mask;
        $max = $subnetLong | ~$mask;

        return $ipLong >= $min && $ipLong <= $max;
    }

    /**
     * Segment-by-segment match — 4 dot-separated parts for IPv4, 8 colon-separated parts for
     * IPv6 — with '*' as a wildcard for any one segment.
     */
    private static function matchesWildcard(string $ip, string $pattern): bool
    {
        $delimiter = str_contains($ip, ':') ? ':' : '.';
        $ipParts = explode($delimiter, $ip);
        $patternParts = explode($delimiter, $pattern);

        if (count($ipParts) !== count($patternParts)) {
            return false;
        }

        foreach ($patternParts as $i => $part) {
            if ($part !== '*' && $part !== $ipParts[$i]) {
                return false;
            }
        }

        return true;
    }
}
