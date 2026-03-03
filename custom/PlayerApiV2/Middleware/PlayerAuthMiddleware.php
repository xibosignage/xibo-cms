<?php
/*
 * Copyright (C) 2026 Pau Aliagas <linuxnow@gmail.com>
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

namespace Xibo\Custom\PlayerApiV2\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Player API v2 authentication middleware.
 *
 * Validates JWT bearer tokens issued by the /auth endpoint.
 * Uses HMAC-SHA256 with the CMS SERVER_KEY as the signing secret —
 * simpler than RSA since both issuer and validator are the same server.
 *
 * Token claims:
 *   - sub: displayId (int)
 *   - hwk: hardwareKey (string)
 *   - iat: issued at
 *   - exp: expiry (1 hour default)
 *
 * On success, attaches 'displayId' and 'hardwareKey' to request attributes.
 */
class PlayerAuthMiddleware implements Middleware
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return $this->unauthorizedResponse('Missing or invalid Authorization header');
        }

        $serverKey = $this->container->get('configService')->getSetting('SERVER_KEY');

        $payload = $this->validateToken($token, $serverKey);
        if ($payload === null) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        // Attach authenticated display info to request for downstream use
        $request = $request->withAttribute('displayId', $payload['sub'])
            ->withAttribute('hardwareKey', $payload['hwk']);

        return $handler->handle($request);
    }

    /**
     * Extract bearer token from Authorization header.
     */
    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (empty($header)) {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Validate a JWT token using HMAC-SHA256.
     *
     * Token format: base64url(header).base64url(payload).base64url(signature)
     *
     * @return array|null Decoded payload on success, null on failure
     */
    private function validateToken(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify signature
        $expectedSig = $this->base64UrlEncode(
            hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true)
        );

        if (!hash_equals($expectedSig, $signatureB64)) {
            return null;
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if ($payload === null) {
            return null;
        }

        // Check expiry
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        // Check required claims
        if (!isset($payload['sub']) || !isset($payload['hwk'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Create a JSON error response with 401 status.
     */
    private function unauthorizedResponse(string $message): Response
    {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'error' => 'unauthorized',
            'message' => $message,
        ]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', 'Bearer');
    }

    /**
     * Generate a JWT token for a display.
     *
     * @param int    $displayId   Display ID
     * @param string $hardwareKey Display hardware key
     * @param string $secret      SERVER_KEY for HMAC signing
     * @param int    $ttl         Token TTL in seconds (default: 3600)
     * @return array ['token' => string, 'expiresIn' => int, 'expiresAt' => string]
     */
    public static function generateToken(
        int $displayId,
        string $hardwareKey,
        string $secret,
        int $ttl = 3600
    ): array {
        $now = time();

        $header = self::base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]));

        $payload = self::base64UrlEncode(json_encode([
            'sub' => $displayId,
            'hwk' => $hardwareKey,
            'iat' => $now,
            'exp' => $now + $ttl,
        ]));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        );

        return [
            'token' => "$header.$payload.$signature",
            'expiresIn' => $ttl,
            'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $now + $ttl),
        ];
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
