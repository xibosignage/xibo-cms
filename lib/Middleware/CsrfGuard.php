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

        // Do we have a token already?
        $token = $_SESSION[$this->key] ?? null;

        if ($token === null) {
            $_SESSION[$this->key] = bin2hex(random_bytes(20));

            // Set the XSRF-TOKEN cookie
            // This cookie is NOT HttpOnly so the SPA can read it.
            // cookie is only set when generated
            setcookie(
                'XSRF-TOKEN',
                $_SESSION[$this->key],
                [
                    'expires' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => HttpsDetect::isHttps(),
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]
            );
        }

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

                if ($token !== $userToken) {
                    throw new ExpiredException(__('Sorry the form has expired. Please refresh.'));
                }
            }
        }

        // Assign CSRF token key and value to view.
        // This is used when the backend outputs HTML (such as the login form)
        $container->get('view')->offsetSet('csrfKey', $this->key);
        $container->get('view')->offsetSet('csrfToken', $token);

        // Call next middleware.
        return $handler->handle($request);
    }
}
