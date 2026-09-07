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
        $ip = self::normalize($ip);

        foreach ($trustedList as $entry) {
            if (str_contains($entry, '/')) {
                if (self::matchesCidr($ip, $entry)) {
                    return true;
                }
            } elseif (str_contains($entry, '*')) {
                if (self::matchesWildcard($ip, $entry)) {
                    return true;
                }
            } elseif (self::matchesExact($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduce an IPv4-mapped IPv6 address (::ffff:a.b.c.d, RFC 4291 §2.5.5.2) to its plain IPv4
     * form, so it matches an IPv4 trusted-list entry the way an operator would expect. Some
     * dual-stack network stacks (certain proxies, some container networking setups) can present a
     * genuinely-IPv4 connection this way; without this, inet_pton()'s differing byte lengths
     * (16 bytes vs 4) mean it could never match a plain IPv4 entry at all. This can only turn a
     * false non-match into a correct match — never the reverse — so it's a correctness fix, not a
     * security-relevant one: the previous behaviour already failed closed here, just incorrectly.
     * Only the address being tested is normalised, not trusted-list entries — an operator
     * authoring a trust list in mapped-IPv6 form for what they consider an IPv4 proxy is not a
     * realistic case worth the extra complexity.
     */
    private static function normalize(string $ip): string
    {
        $packed = @inet_pton($ip);
        $ipv4MappedPrefix = str_repeat("\0", 10) . "\xff\xff";
        if ($packed !== false && strlen($packed) === 16 && str_starts_with($packed, $ipv4MappedPrefix)) {
            return inet_ntop(substr($packed, 12));
        }

        return $ip;
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
     * Exact-address match via packed binary form (inet_pton()) rather than string equality, so
     * equivalent textual forms of the same IPv6 address (e.g. "::1" and "0:0:0:0:0:0:0:1") match
     * — a plain string comparison would treat those as different addresses.
     */
    private static function matchesExact(string $ip, string $entry): bool
    {
        $packedIp = @inet_pton($ip);

        return $packedIp !== false && $packedIp === @inet_pton($entry);
    }

    /**
     * Segment-by-segment match — 4 dot-separated parts for IPv4, or 8 colon-separated hex groups
     * for IPv6 — with '*' as a wildcard for any one segment. Both the address and the pattern's
     * "::" shorthand are expanded to the full 8 groups first (via inet_pton() for the address,
     * which is always a real, syntactically-valid IP; via expandIpv6WithWildcards() for the
     * pattern, which may contain '*'), so e.g. "2001:db8::*" matches the same addresses as the
     * fully-expanded "2001:0db8:0000:0000:0000:0000:0000:*".
     */
    private static function matchesWildcard(string $ip, string $pattern): bool
    {
        if (str_contains($pattern, ':')) {
            $ipGroups = self::expandIpv6($ip);
            $patternGroups = self::expandIpv6WithWildcards($pattern);
        } else {
            $ipGroups = explode('.', $ip);
            $patternGroups = explode('.', $pattern);
        }

        if ($ipGroups === null || $patternGroups === null || count($ipGroups) !== count($patternGroups)) {
            return false;
        }

        foreach ($patternGroups as $i => $part) {
            if ($part !== '*' && $part !== $ipGroups[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fully expand a real IPv6 address into 8 zero-padded, lowercase hex groups. Delegates
     * "::" compression / IPv4-mapped-form handling to inet_pton() (PHP's own well-tested parser)
     * rather than re-implementing IPv6 address syntax. Returns null if not a valid IPv6 address.
     * @return string[]|null
     */
    private static function expandIpv6(string $ip): ?array
    {
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        return str_split(bin2hex($packed), 4);
    }

    /**
     * Expand an admin-authored IPv6 wildcard pattern's "::" shorthand (at most one occurrence,
     * per RFC 4291) into 8 groups, zero-padding each hex group so it lines up with expandIpv6()'s
     * output. '*' groups pass through unchanged. Returns null if the pattern isn't well-formed.
     * @return string[]|null
     */
    private static function expandIpv6WithWildcards(string $pattern): ?array
    {
        $sides = explode('::', $pattern, 2);
        if (count($sides) === 2) {
            if (str_contains($sides[1], '::')) {
                return null; // "::" may only appear once.
            }
            $left = $sides[0] === '' ? [] : explode(':', $sides[0]);
            $right = $sides[1] === '' ? [] : explode(':', $sides[1]);
            $fillCount = 8 - count($left) - count($right);
            if ($fillCount < 0) {
                return null;
            }
            $groups = array_merge($left, array_fill(0, $fillCount, '0'), $right);
        } else {
            $groups = explode(':', $pattern);
        }

        if (count($groups) !== 8) {
            return null;
        }

        foreach ($groups as &$group) {
            if ($group === '*') {
                continue;
            }
            if (!preg_match('/^[0-9a-fA-F]{1,4}$/', $group)) {
                return null;
            }
            $group = str_pad(strtolower($group), 4, '0', STR_PAD_LEFT);
        }

        return $groups;
    }
}
