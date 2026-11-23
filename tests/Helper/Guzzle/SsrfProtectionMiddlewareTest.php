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

namespace Xibo\Tests\Helper\Guzzle;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xibo\Helper\Guzzle\SsrfProtectionMiddleware;

class SsrfProtectionMiddlewareTest extends TestCase
{
    public static function addresses(): array
    {
        // ip, safe by default, safe with allow_local_network
        return [
            'public IPv4' => ['8.8.8.8', true, true],
            'public IPv4 next to CGNAT' => ['100.128.0.1', true, true],
            'public IPv6' => ['2001:4860:4860::8888', true, true],
            'CGNAT start' => ['100.64.0.1', false, true],
            'CGNAT end' => ['100.127.255.254', false, true],
            'Alibaba metadata' => ['100.100.100.200', false, false],
            'benchmarking range' => ['198.18.0.1', false, true],
            'documentation range' => ['192.0.2.1', false, true],
            'RFC 1918' => ['10.0.0.1', false, true],
            'loopback' => ['127.0.0.1', false, false],
            'AWS metadata' => ['169.254.169.254', false, false],
            'current network' => ['0.0.0.1', false, false],
            'IPv6 loopback' => ['::1', false, false],
            'IPv6 ULA' => ['fc00::1', false, true],
            'AWS IPv6 metadata' => ['fd00:ec2::254', false, false],
            'IPv4-mapped loopback' => ['::ffff:127.0.0.1', false, false],
            'IPv4-mapped AWS metadata' => ['::ffff:169.254.169.254', false, false],
            'IPv4-mapped CGNAT' => ['::ffff:100.100.100.200', false, false],
            'NAT64' => ['64:ff9b::a9fe:a9fe', false, false],
        ];
    }

    #[DataProvider('addresses')]
    public function testIsSafeIp(string $ip, bool $safe, bool $safeWithLocalNetwork)
    {
        $isSafeIp = new \ReflectionMethod(SsrfProtectionMiddleware::class, 'isSafeIp');

        $this->assertSame($safe, $isSafeIp->invoke(null, $ip, false), 'default');
        $this->assertSame($safeWithLocalNetwork, $isSafeIp->invoke(null, $ip, true), 'allow_local_network');
    }

    public function testRequestToCgnatNeverReachesTheHandler()
    {
        $called = false;
        $handler = SsrfProtectionMiddleware::create()(function () use (&$called) {
            $called = true;
        });

        try {
            $handler(new Request('GET', 'http://100.100.100.200/latest/meta-data/'), []);
            $this->fail('Expected the request to be blocked');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('SSRF Protection triggered', $exception->getMessage());
        }

        $this->assertFalse($called);
    }
}
