<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2020 Xibo Signage Ltd
 * Author: Emmanuel Blindauer
 * Based on SAMLAuthentication.php
 *
 * This file (CASAuthentication.php) is part of Xibo.
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
use Slim\Http\Response as SlimResponse;
use Slim\Http\ServerRequest as SlimRequest;
use Slim\Routing\RouteContext;
use Xibo\Entity\User;
use Xibo\Helper\ApplicationState;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class CASAuthentication
 * @package Xibo\Middleware
 *
 * Provide CAS authentication to Xibo configured via settings.php.
 */
class CASAuthentication implements Middleware
{
    /* @var App $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public static function casRoutes()
    {
        return array(
            '/cas/login',
            '/cas/logout',
        );
    }


    /**
     * Uses a Hook to check every call for authorization
     * Will redirect to the login route if the user is unauthorized
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws NotFoundException
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

        // Register CAS routes.
        $request = $request->withAttribute('excludedCsrfRoutes', CASAuthentication::casRoutes());
        $app->logoutRoute = 'cas.logout';

        $app->map(['GET', 'POST'],'/cas/login', function (SlimRequest $request, SlimResponse $response) use ($app) {

            // Initiate CAS SSO
            $sso_host = $app->getContainer()->get('configService')->casSettings['config']['server'];
            $sso_port = $app->getContainer()->get('configService')->casSettings['config']['port'];
            $sso_uri = $app->getContainer()->get('configService')->casSettings['config']['uri'];
            \phpCAS::client(CAS_VERSION_2_0, $sso_host, intval($sso_port), $sso_uri, true);
            \phpCAS::setNoCasServerValidation();

            //Login happens here
            \phpCAS::forceAuthentication();

            $username = \phpCAS::getUser();

            try {
                /** @var User $user */
                $user = $app->getContainer()->get('userFactory')->getByName($username);
            } catch (NotFoundException $e) {
                throw new AccessDeniedException("Unknown user");
            }

            if (isset($user) && $user->userId > 0) {
                // Load User
                $user->setChildAclDependencies($app->getContainer()->get('userGroupFactory'), $app->getContainer()->get('pageFactory'));
                $user->load();

                // Overwrite our stored user with this new object.
                $app->getContainer()->set('user', $user);

                // Set the user factory ACL dependencies (used for working out intra-user permissions)
                $app->getContainer()->get('userFactory')->setAclDependencies($user, $app->getContainer()->get('userFactory'));

                // Switch Session ID's
                $app->getContainer()->get('session')->setIsExpired(0);
                $app->getContainer()->get('session')->regenerateSessionId();
                $app->getContainer()->get('session')->setUser($user->userId);

                // Audit Log
                // Set the userId on the log object
                $app->getContainer()->get('logService')->setUserId($user->userId);
                $app->getContainer()->get('logService')->audit('User', $user->userId, 'Login Granted via SAML', [
                    'IPAddress' => $request->getAttribute('ip_address'),
                    'UserAgent' => $request->getHeader('User-Agent')
                ]);
            }

            $routeParser = $app->getRouteCollector()->getRouteParser();
            return $response->withRedirect($routeParser->urlFor('home'));

        })->setName('cas.login');;

        // Service for the logout of the user.
        // End the CAS session and the application session
        $app->get('/cas/logout', function (SlimRequest $request, SlimResponse $response) use ($app) {
            // The order is first: local session to destroy, second the cas session
            // because phpCAS::logout() redirects to CAS server
            $loginController = $app->getContainer()->get('\Xibo\Controller\Login');
            $loginController->logout($request, $response);
            $sso_host = $app->getContainer()->get('configService')->casSettings['config']['server'];
            $sso_port = $app->getContainer()->get('configService')->casSettings['config']['port'];
            $sso_uri = $app->getContainer()->get('configService')->casSettings['config']['uri'];

            \phpCAS::client(CAS_VERSION_2_0, $sso_host, intval($sso_port), $sso_uri, true);
            \phpCAS::logout();

        })->setName('cas.logout');

        // Create a function which we will call should the request be for a protected page
        // and the user not yet be logged in.
        $redirectToLogin = function (Request $request, SlimResponse $response) use ($app) {

            if ($this->isAjax($request)) {
                /* @var ApplicationState $state */
                $state = $app->getContainer()->get('state');
                /* @var ApplicationState $state */
                // Return a JSON response which tells the App to redirect to the login page
                $response = $response->withHeader('Content-Type', 'application/json');
                $state->Login();
                return $response->withJson($state->asArray());
            }
            else {
                $routeParser = $app->getRouteCollector()->getRouteParser();
                return $response->withRedirect($routeParser->urlFor('login'));
            }
        };

        if (!in_array($resource, $request->getAttribute('publicRoutes', [])) && !in_array($resource, $request->getAttribute('excludedCsrfRoutes', [])) ) {

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

                $request = $request->withAttribute('name', 'web');

                return $handler->handle($request);
            } else {
                $app->getContainer()->get('flash')->addMessage('priorRoute', $resource);
                $request->withAttribute('name', 'web');

                $nyholmFactory = new Psr17Factory();
                $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
                $response = $decoratedResponseFactory->createResponse();

                return $redirectToLogin($request, $response);
            }
        } else {
            $request = $request->withAttribute('public', true);

            // If we are expired and come from ping/clock, then we redirect
            if ($container->get('session')->isExpired() && ($resource == '/login/ping' || $resource == 'clock')) {
                $app->getContainer()->get('logger')->debug('should redirect to login , resource is ' . $resource);

                $nyholmFactory = new Psr17Factory();
                $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
                $response = $decoratedResponseFactory->createResponse();

                return $redirectToLogin($request, $response);
            }
        }

        return $handler->handle($request);
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
