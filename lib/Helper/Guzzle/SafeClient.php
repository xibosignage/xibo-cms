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

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

/**
 * Get a Safe Guzzle client
 */
class SafeClient
{
    public static function getSafeClient(array $config = []): Client
    {
        return self::build($config, $config['xibo']['allow_local_network'] ?? false);
    }

    /**
     * Get a Safe Guzzle client that permits RFC 1918 / IPv6 ULA destinations.
     *
     * Use this for connections to known-internal services that are by design
     * on the same network as the CMS — XMR (the message router) is the canonical
     * case: in Docker Compose deployments XMR resolves to a 172.x address, and
     * even in bare-metal installs it's typically reached via localhost or an
     * internal LAN IP. Using getSafeClient() for those calls would have
     * SsrfProtectionMiddleware throw on every request once $allowLocalNetworkRequests
     * is at the default (false).
     *
     * The other SSRF defences still apply: scheme allow-list (http/https only),
     * the always-block list (loopback 127/8, AWS metadata 169.254/16, current
     * network 0.0.0.0/8, IPv6 loopback ::1, AWS IPv6 metadata fd00:ec2::254, and
     * IPv6 transition prefixes that can smuggle an arbitrary embedded IPv4 address —
     * deprecated IPv4-compatible ::/96, NAT64 64:ff9b::/96 and 64:ff9b:1::/48,
     * 6to4 2002::/16, Teredo 2001::/32), DNS-rebind pinning via CURLOPT_RESOLVE,
     * redirect cap. Even with allow_local_network=true an attacker can't redirect
     * a request to the cloud-metadata endpoint or through one of these prefixes.
     *
     * Don't use this for arbitrary user/admin-settable URLs — only for
     * fixed-purpose internal connections.
     */
    public static function getSafeClientForInternal(array $config = []): Client
    {
        return self::build($config, true);
    }

    private static function build(array $config, bool $allowLocalNetwork): Client
    {
        // Must not allow the stack to be set
        if (isset($config['stack'])) {
            unset($config['stack']);
        }

        // Manually build Guzzle's stack, starting with the defaults.
        $stack = HandlerStack::create();

        // Add Ssrf protection
        $stack->unshift(SsrfProtectionMiddleware::create([
            'allow_local_network' => $allowLocalNetwork
        ]), 'ssrf_protection');

        // Sensible default config
        //  - lower timeouts
        //  - we must support redirects, but limit the number
        //  - http/https only
        $config = array_merge([
            'handler' => $stack,
            'timeout' => 10,
            'connect_timeout' => 5,
            'allow_redirects' => [
                'max' => 3,
                'strict' => false,
                'referer' => true,
                'protocols' => ['http', 'https'],
                'track_redirects' => false,
            ]
        ], $config);

        return new Client($config);
    }
}
