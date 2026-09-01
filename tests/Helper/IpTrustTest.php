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

namespace Xibo\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xibo\Helper\IpTrust;

/**
 * Tests for IpTrust::isTrusted() — the single matcher shared by
 * TrustedProxyIpAddress, HttpsDetect::isShouldIssueSts(), Session::getIp(),
 * and Xmds\Soap::getIp() for exact/CIDR/wildcard trusted-proxy matching.
 */
class IpTrustTest extends TestCase
{
    public static function exactMatchProvider(): array
    {
        return [
            'IPv4 exact match' => ['10.0.0.5', ['10.0.0.5'], true],
            'IPv4 exact non-match' => ['10.0.0.6', ['10.0.0.5'], false],
            'IPv6 exact match' => ['::1', ['::1'], true],
            'IPv6 compressed vs fully-expanded equivalence' => ['::1', ['0:0:0:0:0:0:0:1'], true],
            'IPv6 fully-expanded vs compressed equivalence' => ['0:0:0:0:0:0:0:1', ['::1'], true],
            'IPv6 leading-zero normalisation' => [
                '2001:db8::1',
                ['2001:0db8:0000:0000:0000:0000:0000:0001'],
                true,
            ],
            'IPv6 different addresses still distinct' => ['::1', ['::2'], false],
            'IPv4 does not match an IPv6 entry' => ['10.0.0.5', ['::a00:5'], false],
            // IPv4-mapped IPv6 (RFC 4291 §2.5.5.2) — some dual-stack proxies/container network
            // stacks present a genuinely-IPv4 connection this way; it must still match a plain
            // IPv4 trusted entry.
            'IPv4-mapped IPv6 REMOTE_ADDR matches a plain IPv4 entry' => [
                '::ffff:10.0.0.5',
                ['10.0.0.5'],
                true,
            ],
            'IPv4-mapped IPv6 REMOTE_ADDR non-match still correctly rejected' => [
                '::ffff:10.0.0.6',
                ['10.0.0.5'],
                false,
            ],
            'genuine IPv6 address is not confused with an IPv4-mapped one' => [
                '2001:db8::a00:5',
                ['10.0.0.5'],
                false,
            ],
            'garbage entry does not match' => ['::1', ['not-an-ip'], false],
            'empty trust list' => ['10.0.0.5', [], false],
        ];
    }

    #[DataProvider('exactMatchProvider')]
    public function testExactMatch(string $ip, array $trustedList, bool $expected): void
    {
        $this->assertSame($expected, IpTrust::isTrusted($ip, $trustedList));
    }

    public static function cidrProvider(): array
    {
        return [
            'IPv4 /16 match' => ['172.18.5.5', ['172.18.0.0/16'], true],
            'IPv4 /16 non-match' => ['172.19.5.5', ['172.18.0.0/16'], false],
            'IPv4 /16 boundary low' => ['172.18.0.0', ['172.18.0.0/16'], true],
            'IPv4 /16 boundary high' => ['172.18.255.255', ['172.18.0.0/16'], true],
            'IPv4-mapped IPv6 REMOTE_ADDR matches a plain IPv4 CIDR' => [
                '::ffff:172.18.5.5',
                ['172.18.0.0/16'],
                true,
            ],
            'IPv4 /16 just over boundary' => ['172.19.0.0', ['172.18.0.0/16'], false],
            'IPv4 /24' => ['10.0.0.200', ['10.0.0.0/24'], true],
            'IPv4 /24 non-match' => ['10.0.1.1', ['10.0.0.0/24'], false],
            'IPv4 /32 single host match' => ['10.0.0.5', ['10.0.0.5/32'], true],
            'IPv4 /32 single host non-match' => ['10.0.0.6', ['10.0.0.5/32'], false],
            'IPv4 /0 matches everything' => ['8.8.8.8', ['0.0.0.0/0'], true],
            'IPv6 /32 match' => ['2001:db8::1', ['2001:db8::/32'], true],
            'IPv6 /32 non-match' => ['2001:db9::1', ['2001:db8::/32'], false],
            'IPv6 /128 single host match' => ['::1', ['::1/128'], true],
            'IPv6 /128 single host non-match' => ['::2', ['::1/128'], false],
            // Non-byte-aligned prefixes prove the bitmask logic, not just byte truncation.
            'IPv6 non-byte-aligned /48 match' => ['2001:db8:abcd::1', ['2001:db8:abcd::/48'], true],
            'IPv6 non-byte-aligned /48 non-match' => ['2001:db8:abce::1', ['2001:db8:abcd::/48'], false],
            'IPv6 non-byte-aligned /46 boundary match' => ['2001:db8:abcf::1', ['2001:db8:abcc::/46'], true],
            'IPv6 non-byte-aligned /46 boundary non-match' => ['2001:db8:abd0::1', ['2001:db8:abcc::/46'], false],
            // Cross-family: a CIDR range must never match a genuinely different address family
            // (::ffff:172.18.5.5 is IPv4-mapped IPv6, not a different family — see the
            // IPv4-mapped-IPv6 cases above/below, which correctly do match).
            'IPv4 CIDR never matches a genuine IPv6 address' => ['2001:db8::1', ['172.18.0.0/16'], false],
            'IPv6 CIDR never matches an IPv4 address' => ['172.18.5.5', ['2001:db8::/32'], false],
            // Malformed entries fail closed, not fatally.
            'missing bits fails closed' => ['10.0.0.5', ['10.0.0.0/'], false],
            'non-numeric bits fails closed' => ['10.0.0.5', ['10.0.0.0/abc'], false],
            'bits out of range fails closed' => ['10.0.0.5', ['10.0.0.0/99'], false],
            'garbage subnet fails closed' => ['10.0.0.5', ['not-an-ip/16'], false],
        ];
    }

    #[DataProvider('cidrProvider')]
    public function testCidrMatch(string $ip, array $trustedList, bool $expected): void
    {
        $this->assertSame($expected, IpTrust::isTrusted($ip, $trustedList));
    }

    public static function wildcardProvider(): array
    {
        return [
            'IPv4 wildcard match' => ['10.0.5.99', ['10.0.*.*'], true],
            'IPv4 wildcard non-match' => ['10.1.5.99', ['10.0.*.*'], false],
            'IPv6 compressed pattern matches expanded address' => [
                '2001:db8:0:0:0:0:0:5',
                ['2001:db8::*'],
                true,
            ],
            'IPv6 compressed pattern matches compressed address' => ['2001:db8::5', ['2001:db8::*'], true],
            'IPv6 compressed pattern non-match on different prefix' => [
                '2001:db9:0:0:0:0:0:5',
                ['2001:db8::*'],
                false,
            ],
            'IPv6 wildcard mid-pattern' => ['2001:db8:abcd::1', ['2001:db8:*::1'], true],
            'IPv6 fully-expanded pattern (no shorthand) still works' => [
                '2001:0db8:0000:0000:0000:0000:0000:0005',
                ['2001:0db8:0000:0000:0000:0000:0000:*'],
                true,
            ],
            'malformed pattern: double "::" fails closed' => ['2001:db8::5', ['2001::db8::*'], false],
            'malformed pattern: too many groups fails closed' => ['2001:db8::5', ['1:2:3:4:5:6:7:8:*'], false],
        ];
    }

    #[DataProvider('wildcardProvider')]
    public function testWildcardMatch(string $ip, array $trustedList, bool $expected): void
    {
        $this->assertSame($expected, IpTrust::isTrusted($ip, $trustedList));
    }
}
