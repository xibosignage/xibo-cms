<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

/**
 * Class AuthenticationBase
 * @package Xibo\Middleware
 */
abstract class AuthenticationBase implements Middleware, AuthenticationInterface
{
    use AuthenticationTrait;

    /**
     * Uses a Hook to check every call for authorization
     * Will redirect to the login route if the user is unauthorized
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws \Xibo\Support\Exception\AccessDeniedException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        // This Middleware protects the Web Route, so we update the request with that name
        $request = $request->withAttribute('name', 'web');

        // Add any authentication specific request modifications
        $request = $this->addToRequest($request);

        // Start with an empty user.
        $user = $this->getEmptyUser();

        // Get the current route pattern
        $resource = $this->getRoutePattern($request);

        // Check to see if this is a public resource (there are only a few, so we have them in an array)
        if (!in_array($resource, $this->getPublicRoutes($request))) {
            $request = $request->withAttribute('public', false);

            // Need to check
            if ($user->hasIdentity() && !$this->getSession()->isExpired()) {
                // Replace our user with a fully loaded one
                $user = $this->getUser(
                    $user->userId,
                    $request->getAttribute('ip_address'),
                    $this->getSession()->get('sessionHistoryId')
                );

                // We are authenticated, override with the populated user object
                $this->setUserForRequest($user);

                // Handle the rest of the Middleware stack and return
                return $handler->handle($request);
            } else {
                // Session has expired or the user is already logged out.
                // in either case, capture the route
                $this->rememberRoute($request->getUri()->getPath());

                $this->getLog()->debug('not in public routes, expired, should redirect to login');

                // We update the last accessed date on the user here, if there was one logged in at this point
                if ($user->hasIdentity()) {
                    $user->touch();
                }

                // Issue appropriate logout depending on the type of web request
                return $this->redirectToLogin($request);
            }
        } else {
            // This is a public route.
            $request = $request->withAttribute('public', true);

            // If we are expired and come from ping/clock, then we redirect
            if ($this->shouldRedirectPublicRoute($resource)) {
                $this->getLog()->debug('should redirect to login , resource is ' . $resource);

                if ($user->hasIdentity()) {
                    $user->touch();
                }

                // Issue appropriate logout depending on the type of web request
                return $this->redirectToLogin($request);
            } else {
                // We handle the rest of the request, unauthenticated.
                return $handler->handle($request);
            }
        }
    }
}