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

namespace Xibo\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Xibo\Helper\IpTrust;

/**
 * Resolves the client IP address into the `ip_address` request attribute.
 *
 * With an empty trusted-proxy list (the default), this is always REMOTE_ADDR — the immediate TCP
 * peer, which a client cannot spoof. Forwarded-for style headers are only consulted once REMOTE_ADDR,
 * and each successive hop peeled off a multi-hop chain, is confirmed to be on the trusted list (see
 * IpTrust::isTrusted() — exact IP, IPv4 CIDR, and wildcard entries all supported).
 *
 * Replaces the third-party RKA\Middleware\IpAddress package (removed so this and the other
 * trusted-proxy consumers — HttpsDetect::isShouldIssueSts(), Session::getIp(), Xmds\Soap::getIp() —
 * share one matcher instead of two, keeping CIDR/wildcard behaviour consistent everywhere).
 */
class TrustedProxyIpAddress implements Middleware
{
    private const HEADERS = ['Forwarded', 'X-Forwarded-For', 'X-Forwarded', 'X-Cluster-Client-Ip', 'Client-Ip'];

    /**
     * @param string[] $trustedProxyIps
     */
    public function __construct(private readonly array $trustedProxyIps)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        return $handler->handle($request->withAttribute('ip_address', $this->resolve($request)));
    }

    private function resolve(Request $request): ?string
    {
        $serverParams = $request->getServerParams();
        $remoteAddr = isset($serverParams['REMOTE_ADDR']) ? $this->extractIp($serverParams['REMOTE_ADDR']) : '';
        if (!filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (empty($this->trustedProxyIps)) {
            return $remoteAddr;
        }

        foreach (self::HEADERS as $header) {
            $value = $request->getHeaderLine($header);
            if ($value !== '') {
                return $this->resolveFromHeader($header, $value, $remoteAddr);
            }
        }

        return $remoteAddr;
    }

    private function resolveFromHeader(string $header, string $value, string $remoteAddr): string
    {
        if (strtolower($header) === 'forwarded') {
            // Simple `for=` extraction — not the full RFC 7239 grammar.
            preg_match_all('/for=([^,;]+)/i', $value, $matches);
            $ips = $matches[1];
        } else {
            $ips = explode(',', $value);
        }

        $ips[] = $remoteAddr;
        $ips = array_map(fn ($ip) => $this->extractIp(trim($ip)), $ips);

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                // Malformed entry anywhere in the chain — don't guess, fall back to REMOTE_ADDR.
                return $remoteAddr;
            }
        }

        // Walk right-to-left (nearest hop first), peeling off trusted proxies; the first
        // untrusted entry is the client. If every entry is trusted, fall back to REMOTE_ADDR.
        foreach (array_reverse($ips) as $ip) {
            if (!IpTrust::isTrusted($ip, $this->trustedProxyIps)) {
                return $ip;
            }
        }

        return $remoteAddr;
    }

    /**
     * Strip a trailing :port from an IPv4 address, and unwrap a bracketed IPv6 address
     * (`[::1]` or `[::1]:8080`) — IPv6 addresses without brackets are left untouched.
     */
    private function extractIp(string $ip): string
    {
        $parts = explode(':', $ip);
        if (count($parts) === 1) {
            return $ip;
        }

        if (count($parts) === 2 && filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $parts[0];
        }

        $ip = trim($ip, '"\'');
        if (str_starts_with($ip, '[') && (str_ends_with($ip, ']') || preg_match('/]:\d+$/', $ip))) {
            preg_match('/\[(.*?)]/', $ip, $matches);
            $ip = $matches[1] ?? $ip;
        }

        return $ip;
    }
}
