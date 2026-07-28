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
use Slim\App as App;
use Slim\Routing\RouteContext;
use Xibo\Helper\Environment;
use Xibo\Helper\HttpsDetect;
use Xibo\Support\Exception\ExpiredException;

class CsrfGuard implements Middleware
{
    /**
     * CSRF token key name.
     *
     * @var string
     */
    protected $key;

    /* @var App $app */
    private $app;

    /**
     * Constructor.
     *
     * @param App $app
     * @param string $key The CSRF token key name.
     */
    public function __construct($app, $key = 'csrfToken')
    {
        if (! is_string($key) || empty($key) || preg_match('/[^a-zA-Z0-9\-\_]/', $key)) {
            throw new \OutOfBoundsException('Invalid CSRF token key "' . $key . '"');
        }

        $this->key = $key;
        $this->app = $app;
    }

    /**
     * Call middleware.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws ExpiredException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $container = $this->app->getContainer();

        $token = self::issueToken($this->key);

        // Validate the CSRF token.
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'])) {
            // Validate the token unless we are on an excluded route
            // Get the current route pattern
            $routeContext = RouteContext::fromRequest($request);
            $route = $routeContext->getRoute();
            $resource = $route->getPattern();

            $excludedRoutes = $request->getAttribute('excludedCsrfRoutes');

            if ((is_array($excludedRoutes) && in_array($resource, $excludedRoutes))
                || (Environment::isDevMode() && $resource === '/login')
            ) {
                $container->get('logger')->info('Route excluded from CSRF: ' . $resource);
            } else {
                // Checking CSRF
                $userToken = $request->getHeaderLine('X-XSRF-TOKEN');

                if ($userToken == '') {
                    // Not in the header, check in params instead
                    $parsedBody = $request->getParsedBody();
                    foreach ($parsedBody as $param => $value) {
                        if ($param == $this->key) {
                            $userToken = $value;
                        }
                    }
                }

                // hash_equals avoids any short-circuit / length-prefix timing side channel
                // on token comparison. The token and userToken are both nullable strings here
                // so coerce to string before comparing — hash_equals throws on null.
                if (!hash_equals((string)$token, (string)$userToken)) {
                    throw new ExpiredException(__('Sorry the form has expired. Please refresh.'));
                }
            }
        }

        // Assign the CSRF token to the view.
        // This is used when the backend outputs HTML (such as the login form)
        $container->get('view')->offsetSet('csrfToken', $token);

        // Call next middleware.
        return $handler->handle($request);
    }

    /**
     * Get (generating if necessary) the current session's CSRF token, and (re-)issue the
     * XSRF-TOKEN cookie for it.
     *
     * Re-issuing on every call (not just when the token is first generated) means a browser
     * whose cookie is missing or stale relative to the session's token self-heals on its very
     * next request, rather than being stuck until the session itself expires. This matters for
     * long-lived, DB-backed sessions that can carry a csrfToken value from before this cookie
     * mechanism existed (e.g. a session created pre-upgrade), which would otherwise never
     * receive the cookie for the rest of its life since regenerateSessionId()/logout preserve
     * $_SESSION contents.
     *
     * Also called directly by render paths that run outside the middleware stack (see
     * Handlers::webErrorHandler()'s UpgradePendingException branch) so those pages don't bake a
     * stale/blank token into the CSRF meta tag. The caller must ensure the session is already
     * started before calling this (true for every normal request, since State::setState() does
     * that ahead of routing/CsrfGuard).
     *
     * @param string $key Session key the token is stored under.
     * @return string
     */
    public static function issueToken(string $key = 'csrfToken'): string
    {
        $token = $_SESSION[$key] ?? null;

        if ($token === null) {
            $token = bin2hex(random_bytes(20));
            $_SESSION[$key] = $token;
        }

        // This cookie is NOT HttpOnly so the SPA can read it.
        setcookie(
            'XSRF-TOKEN',
            $token,
            [
                'expires' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => HttpsDetect::isHttps(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );

        return $token;
    }
}
