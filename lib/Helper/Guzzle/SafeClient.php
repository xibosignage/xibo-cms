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
        // Must not allow the stack to be set
        if (isset($config['stack'])) {
            unset($config['stack']);
        }

        // Manually build Guzzle's stack, starting with the defaults.
        $stack = HandlerStack::create();

        // Add Ssrf protection
        $stack->unshift(SsrfProtectionMiddleware::create([
            'allow_local_network' => $config['xibo']['allow_local_network'] ?? false
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
