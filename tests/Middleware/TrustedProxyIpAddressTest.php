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

namespace Xibo\Tests\Middleware;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Xibo\Middleware\TrustedProxyIpAddress;

/**
 * Tests for TrustedProxyIpAddress — resolves the `ip_address` request
 * attribute used for login/2FA/password-reset rate limiting and audit-log
 * attribution. See SECURITY.md's `$trustedProxyIps` section for the threat
 * model this middleware defends.
 */
class TrustedProxyIpAddressTest extends TestCase
{
    /**
     * @param string[] $trustedProxyIps
     * @param array<string, string> $headers
     */
    private function resolve(array $trustedProxyIps, string $remoteAddr, array $headers = []): ?string
    {
        $middleware = new TrustedProxyIpAddress($trustedProxyIps);
        $request = new ServerRequest('GET', '/', $headers, null, '1.1', ['REMOTE_ADDR' => $remoteAddr]);

        $captured = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (ServerRequestInterface $request) use (&$captured) {
                $captured = $request->getAttribute('ip_address');
                return true;
            }))
            ->willReturn(new Response());

        $middleware->process($request, $handler);

        return $captured;
    }

    public function testEmptyTrustedListIgnoresSpoofedHeaderAndUsesRemoteAddr(): void
    {
        $ip = $this->resolve([], '203.0.113.9', ['X-Forwarded-For' => '1.2.3.4']);

        $this->assertSame('203.0.113.9', $ip);
    }

    public function testTrustedProxyCorrectlyAppendingIsResolvedToRealClient(): void
    {
        $ip = $this->resolve(['10.0.0.5'], '10.0.0.5', ['X-Forwarded-For' => '9.9.9.9, 198.51.100.7']);

        $this->assertSame('198.51.100.7', $ip);
    }

    public function testAttackerBypassingTheProxyIsNotTrusted(): void
    {
        // Trust is configured for 10.0.0.5, but the request arrives directly from an
        // untrusted address — the spoofed header must be ignored.
        $ip = $this->resolve(['10.0.0.5'], '203.0.113.9', ['X-Forwarded-For' => '1.2.3.4']);

        $this->assertSame('203.0.113.9', $ip);
    }

    public function testCidrRangeTrustedProxy(): void
    {
        $ip = $this->resolve(['172.18.0.0/16'], '172.18.5.5', ['X-Forwarded-For' => '9.9.9.9, 198.51.100.7']);

        $this->assertSame('198.51.100.7', $ip);
    }

    public function testWildcardTrustedProxy(): void
    {
        $ip = $this->resolve(['10.0.*.*'], '10.0.5.5', ['X-Forwarded-For' => '9.9.9.9, 198.51.100.7']);

        $this->assertSame('198.51.100.7', $ip);
    }

    public function testMultiHopChainPeelsMultipleTrustedProxies(): void
    {
        $ip = $this->resolve(
            ['10.0.0.5', '10.0.0.6'],
            '10.0.0.6',
            ['X-Forwarded-For' => '198.51.100.7, 10.0.0.5']
        );

        $this->assertSame('198.51.100.7', $ip);
    }

    public function testMalformedHeaderEntryFallsBackToRemoteAddr(): void
    {
        $ip = $this->resolve(['10.0.0.5'], '10.0.0.5', ['X-Forwarded-For' => 'not-an-ip']);

        $this->assertSame('10.0.0.5', $ip);
    }

    /**
     * Regression test for a PR-review finding: a client whose traffic genuinely passes through
     * the real, correctly-configured trusted proxy (REMOTE_ADDR is trusted, and the proxy sets
     * X-Forwarded-For correctly) could previously still fully control ip_address by also sending
     * a raw `Forwarded: for=<anything>` header — most proxies never touch that header even when
     * they correctly manage X-Forwarded-For, so it reached the app unmodified and was picked
     * ahead of the correctly-set X-Forwarded-For (the old HEADERS priority list checked
     * 'Forwarded' first). The fix supports exactly one header (X-Forwarded-For), so a smuggled
     * Forwarded header must now have zero effect.
     */
    public function testSmuggledForwardedHeaderIsIgnoredWhenXForwardedForIsPresent(): void
    {
        $ip = $this->resolve(
            ['172.20.0.1'],
            '172.20.0.1',
            [
                'X-Forwarded-For' => '198.51.100.7',
                'Forwarded' => 'for=6.6.6.6',
            ]
        );

        $this->assertSame('198.51.100.7', $ip);
    }

    public function testForwardedHeaderAloneHasNoEffect(): void
    {
        $ip = $this->resolve(['172.20.0.1'], '172.20.0.1', ['Forwarded' => 'for=6.6.6.6']);

        $this->assertSame('172.20.0.1', $ip);
    }

    public function testOtherUnsupportedHeadersHaveNoEffect(): void
    {
        $ip = $this->resolve(
            ['172.20.0.1'],
            '172.20.0.1',
            ['X-Cluster-Client-Ip' => '6.6.6.6', 'Client-Ip' => '7.7.7.7']
        );

        $this->assertSame('172.20.0.1', $ip);
    }
}
