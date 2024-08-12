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

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Http\Factory\DecoratedResponseFactory;
use Slim\Routing\RouteContext;
use Xibo\Entity\User;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\UserLogProcessor;

/**
 * Trait AuthenticationTrait
 * @package Xibo\Middleware
 */
trait AuthenticationTrait
{
    /* @var App $app */
    protected $app;

    /**
     * Set dependencies
     * @param App $app
     * @return $this
     */
    public function setDependencies(App $app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * @return \Xibo\Service\ConfigServiceInterface
     */
    protected function getConfig()
    {
        return $this->app->getContainer()->get('configService');
    }

    /**
     * @return \Xibo\Helper\Session
     */
    protected function getSession()
    {
        return $this->app->getContainer()->get('session');
    }

    /**
     * @return \Xibo\Service\LogServiceInterface
     */
    protected function getLog()
    {
        return $this->app->getContainer()->get('logService');
    }

    /**
     * @param $array
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     */
    protected function getSanitizer($array)
    {
        return $this->app->getContainer()->get('sanitizerService')->getSanitizer($array);
    }

    /**
     * @return \Xibo\Factory\UserFactory
     */
    protected function getUserFactory()
    {
        return $this->app->getContainer()->get('userFactory');
    }

    /**
     * @return \Xibo\Factory\UserGroupFactory
     */
    protected function getUserGroupFactory()
    {
        return $this->app->getContainer()->get('userGroupFactory');
    }

    /**
     * @return \Xibo\Entity\User
     */
    protected function getEmptyUser()
    {
        $container = $this->app->getContainer();

        /** @var User $user */
        $user = $container->get('userFactory')->create();
        $user->setChildAclDependencies($container->get('userGroupFactory'));

        return $user;
    }

    /**
     * @param int $userId
     * @param string $ip
     * @param int $sessionHistoryId
     * @return \Xibo\Entity\User
     */
    protected function getUser($userId, $ip, $sessionHistoryId): User
    {
        $container = $this->app->getContainer();
        $user = $container->get('userFactory')->getById($userId);

        // Pass the page factory into the user object, so that it can check its page permissions
        $user->setChildAclDependencies($container->get('userGroupFactory'));

        // Load the user
        $user->load(false);

        // Configure the log service with the logged in user id
        $container->get('logService')->setUserId($user->userId);
        $container->get('logService')->setIpAddress($ip);
        $container->get('logService')->setSessionHistoryId($sessionHistoryId);

        return $user;
    }

    /**
     * @param \Xibo\Entity\User $user
     */
    protected function setUserForRequest($user)
    {
        $container = $this->app->getContainer();
        $container->set('user', $user);

        // Add this users information to the logger
        $this->getLog()->getLoggerInterface()->pushProcessor(new UserLogProcessor(
            $user->userId,
            $this->getLog()->getSessionHistoryId(),
            null,
        ));
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function getRoutePattern($request)
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route->getPattern();
    }

    /**
     * @return \Slim\Interfaces\RouteParserInterface
     */
    protected function getRouteParser()
    {
        return $this->app->getRouteCollector()->getRouteParser();
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return bool
     */
    protected function isAjax(Request $request)
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function createResponse(Request $request)
    {
        // Create a new response
        $nyholmFactory = new Psr17Factory();
        $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
        return HttpsDetect::decorateWithStsIfNecessary(
            $this->getConfig(),
            $request,
            $decoratedResponseFactory->createResponse()
        );
    }

    /**
     * @param string $route
     */
    protected function rememberRoute($route)
    {
        $this->app->getContainer()->get('flash')->addMessage('priorRoute', $route);
    }
}