<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Http\Factory\DecoratedResponseFactory;
use Slim\Routing\RouteContext;
use Xibo\Entity\User;
use Xibo\Helper\ApplicationState;

/**
 * Class WebAuthentication
 * @package Xibo\Middleware
 */
class WebAuthentication implements Middleware
{
    /* @var App $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Uses a Hook to check every call for authorization
     * Will redirect to the login route if the user is unauthorized
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\ConfigurationException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;
        $container = $app->getContainer();

        /** @var User $user */
        $user = $container->get('userFactory')->create();

        // Get the current route pattern
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $resource = $route->getPattern();
        $routeParser = $app->getRouteCollector()->getRouteParser();

        // Pass the page factory into the user object, so that it can check its page permissions
        $user->setChildAclDependencies($container->get('userGroupFactory'), $container->get('pageFactory'));

        // Check to see if this is a public resource (there are only a few, so we have them in an array)
        if (!in_array($resource, $request->getAttribute('publicRoutes', []))) {
            $request = $request->withAttribute('public', false);

            // Need to check
            if ($user->hasIdentity() && $container->get('session')->isExpired() == 0) {

                // Replace our user with a fully loaded one
                $user = $container->get('userFactory')->getById($user->userId);

                // Pass the page factory into the user object, so that it can check its page permissions
                $user->setChildAclDependencies($container->get('userGroupFactory'), $container->get('pageFactory'));

                // Load the user
                $user->load(false);

                // Configure the log service with the logged in user id
                $container->get('logService')->setUserId($user->userId);

                // Do they have permission?
                $user->routeAuthentication($resource);

                // We are authenticated, override with the populated user object
                $container->set('user', $user);

                $newRequest = $request->withAttribute('name', 'web');

                return $handler->handle($newRequest);
            } else {
                // TODO Store the current route so we can come back to it after login
                //  $app->flash('priorRoute', $app->request()->getRootUri() . $app->request()->getResourceUri());
                $app->getContainer()->get('flash')->addMessage('priorRoute', $request->getUri() . $resource);

                $request->withAttribute('name', 'web');

                $app->getContainer()->get('logger')->debug('not in public routes, expired, should redirect to login ');

                if ($user->hasIdentity()) {
                    $user->touch();
                }

                // Create a new response
                $nyholmFactory = new Psr17Factory();
                $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
                $response = $decoratedResponseFactory->createResponse();

                if ($this->isAjax($request)) {

                    $state = $app->getContainer()->get('state');
                    /* @var ApplicationState $state */
                    // Return a JSON response which tells the App to redirect to the login page

                    $state->Login();

                    return $response->withJson($state->asArray());
                } else {
                    // TODO: this probably doesn't ever call commit. How deep in the onion are we?
                    return $response->withStatus(302)->withHeader('Location', $routeParser->urlFor('login'));
                }
            }
        } else {
            $request = $request->withAttribute('public', true);

            // If we are expired and come from ping/clock, then we redirect
            if ($container->get('session')->isExpired() && ($resource == '/login/ping' || $resource == 'clock')) {
                $app->getContainer()->get('logger')->debug('should redirect to login , resource is ' . $resource);
                if ($user->hasIdentity()) {
                    $user->touch();
                }

                // We should redirect
                $nyholmFactory = new Psr17Factory();
                $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
                $response = $decoratedResponseFactory->createResponse();

                if ($this->isAjax($request)) {

                    $state = $app->getContainer()->get('state');
                    /* @var ApplicationState $state */
                    // Return a JSON response which tells the App to redirect to the login page
                    $state->Login();

                    return $response
                        ->withJson($state->asJson());
                } else {
                    return $response
                        ->withStatus(302)
                        ->withHeader('Location', $routeParser->urlFor('login'));
                }
            } else {
                return $handler->handle(
                    $request
                        ->withAttribute('currentUser', $user)
                        ->withAttribute('name', 'web')
                );
            }
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return bool
     */
    private function isAjax(Request $request)
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}