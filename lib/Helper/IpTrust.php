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
 * getTrustedProxyIpList()) containing a mix of exact IPs, CIDR ranges (IPv4 or IPv6, e.g.
 * 172.18.0.0/16 or 2001:db8::/32), and wildcard patterns (e.g. 10.0.*.*) — the single
 * authoritative matcher used by both TrustedProxyIpAddress (resolving X-Forwarded-For chains)
 * and the simpler single-address checks in HttpsDetect::isShouldIssueSts(), Session::getIp(),
 * and Xmds\Soap::getIp(), so a CIDR/wildcard entry behaves identically everywhere it's consulted.
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
     * IPv4 and IPv6, via a bitmask over each address's packed binary form — inet_pton() gives a
     * uniform representation for both families and sidesteps textual quirks (leading zeros,
     * "::" compression), matching the same approach already used by
     * Xibo\Helper\Guzzle\SsrfProtectionMiddleware::isSafeIp() for its own range checks.
     */
    private static function matchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        if (!is_numeric($bits)) {
            return false;
        }

        $packedIp = @inet_pton($ip);
        $packedSubnet = @inet_pton($subnet);
        if ($packedIp === false || $packedSubnet === false || strlen($packedIp) !== strlen($packedSubnet)) {
            // Invalid address, or the CIDR is a different address family (e.g. an IPv6 range
            // can never match an IPv4 address) — either way, not a match.
            return false;
        }

        $bits = (int) $bits;
        $maxBits = strlen($packedIp) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        return self::maskBytes($packedIp, $bits) === self::maskBytes($packedSubnet, $bits);
    }

    /**
     * Zeroes every bit beyond the first $bits bits of a packed IP address, so two addresses in
     * the same CIDR range become byte-for-byte equal after masking.
     */
    private static function maskBytes(string $packed, int $bits): string
    {
        $fullBytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        $masked = substr($packed, 0, $fullBytes);
        if ($remainderBits > 0) {
            $maskByte = (0xFF << (8 - $remainderBits)) & 0xFF;
            $masked .= chr(ord($packed[$fullBytes]) & $maskByte);
        }

        return str_pad($masked, strlen($packed), "\0");
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
