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
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Routing\RouteContext;
use Stash\Invalidation;
use Stash\Item;
use Stash\Pool;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;

/**
 * This Middleware will Lock the Layout for the specific User and entry point
 * It is not added on the whole Application stack, instead it's added to selected groups of routes in routes.php
 *
 * For a User designing a Layout there will be no change in the way that User interacts with it
 * However if the same Layout will be accessed by different User or Entry Point then this middleware will throw
 * an Exception with suitable message.
 */
class LayoutLock implements Middleware
{
    /** @var Item */
    private $lock;

    private $layoutId;

    private $userId;

    private $entryPoint;

    private LoggerInterface $logger;

    /**
     * @param \Slim\App $app
     * @param int $ttl
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct(
        private readonly App $app,
        private readonly int $ttl = 300
    ) {
        $this->logger = $this->app->getContainer()->get('logService')->getLoggerInterface();
    }

    /**
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        // what route are we in?
        $resource = $route->getPattern();
        $routeName = $route->getName();

        // skip for test suite
        if ($request->getAttribute('name') === 'test' && $this->app->getContainer()->get('name') === 'test') {
            return $handler->handle($request);
        }

        $this->logger->debug('layoutLock: testing route ' . $routeName . ', pattern ' . $resource);

        if (str_contains($resource, 'layout') !== false) {
            // Layout route, we can get the Layout id from route argument.
            $this->layoutId = (int)$route->getArgument('id');
        } elseif (str_contains($resource, 'region') !== false) {
            // Region route, we need to get the Layout Id from layoutFactory by Region Id
            // if it's POST request or positionAll then id in route is already LayoutId we can use
            if (str_contains($resource, 'position') !== false || $route->getMethods()[0] === 'POST') {
                $this->layoutId = (int)$route->getArgument('id');
            } else {
                $regionId = (int)$route->getArgument('id');
                $this->layoutId = $this->app->getContainer()->get('layoutFactory')->getByRegionId($regionId)->layoutId;
            }
        } else if (str_contains($routeName, 'playlist') !== false || $routeName === 'module.widget.add') {
            // Playlist Route, we need to get to LayoutId, Widget add the same behaviour.
            $playlistId = (int)$route->getArgument('id');
            $regionId = $this->app->getContainer()->get('playlistFactory')->getById($playlistId)->regionId;

            // if we are assigning media or ordering Region Playlist, then we will have regionId
            // otherwise it's non Region specific Playlist, in which case we are not interested in locking anything.
            if ($regionId != null) {
                $this->layoutId = $this->app->getContainer()->get('layoutFactory')->getByRegionId($regionId)->layoutId;
            }
        } else if (str_contains($routeName, 'widget') !== false) {
            // Widget route, the id route argument will be Widget Id
            $widgetId = (int)$route->getArgument('id');

            // get the Playlist Id for this Widget
            $playlistId = $this->app->getContainer()->get('widgetFactory')->getById($widgetId)->playlistId;
            $regionId = $this->app->getContainer()->get('playlistFactory')->getById($playlistId)->regionId;

            // check if it's Region specific Playlist, otherwise we don't lock anything.
            if ($regionId != null) {
                $this->layoutId = $this->app->getContainer()->get('layoutFactory')->getByRegionId($regionId)->layoutId;
            }
        } else {
            // this should never happen
            throw new GeneralException(sprintf(
                __('Layout Lock Middleware called with incorrect route %s'),
                $route->getPattern(),
            ));
        }

        // run only if we have layout id, that will exclude non Region specific Playlist requests.
        if ($this->layoutId !== null) {
            $this->userId = $this->app->getContainer()->get('user')->userId;
            $this->entryPoint = $this->app->getContainer()->get('name');
            $key = $this->getKey();
            $this->lock = $this->getPool()->getItem('locks/layout/' . $key);

            $objectToCache = new \stdClass();
            $objectToCache->layoutId = $this->layoutId;
            $objectToCache->userId = $this->userId;
            $objectToCache->entryPoint = $this->entryPoint;

            $this->logger->debug('Layout Lock middleware for LayoutId ' . $this->layoutId
                . ' userId ' . $this->userId . ' emtrypoint ' . $this->entryPoint);

            $this->lock->setInvalidationMethod(Invalidation::OLD);

            // Get the lock
            // other requests will wait here until we're done, or we've timed out
            $locked = $this->lock->get();
            $this->logger->debug('$locked is ' . var_export($locked, true) . ', key = ' . $key);

            if ($this->lock->isMiss() || $locked === []) {
                $this->logger->debug('Lock miss or false. Locking for ' . $this->ttl . ' seconds. $locked is '
                    . var_export($locked, true) . ', key = ' . $key);

                // so lock now
                $this->lock->expiresAfter($this->ttl);
                $objectToCache->expires = $this->lock->getExpiration()->format(DateFormatHelper::getSystemFormat());
                $this->lock->set($objectToCache);
                $this->lock->save();
            } else {
                // We are a hit - we must be locked
                $this->logger->debug('LOCK hit for ' . $key . ' expires '
                    . $this->lock->getExpiration()->format(DateFormatHelper::getSystemFormat()) . ', created '
                    . $this->lock->getCreation()->format(DateFormatHelper::getSystemFormat()));

                if ($locked->userId == $this->userId && $locked->entryPoint == $this->entryPoint) {
                    // the same user in the same entry point is editing the same layoutId
                    $this->lock->expiresAfter($this->ttl);
                    $objectToCache->expires = $this->lock->getExpiration()->format(DateFormatHelper::getSystemFormat());
                    $this->lock->set($objectToCache);
                    $this->lock->save();

                    $this->logger->debug('Lock extended to '
                        . $this->lock->getExpiration()->format(DateFormatHelper::getSystemFormat()));
                } else {
                    // different user or entry point
                    $this->logger->debug('Sorry Layout is locked by another User!');
                    throw new AccessDeniedException(sprintf(
                        __('Layout ID %d is locked by another User! Lock expires on: %s'),
                        $locked->layoutId,
                        $locked->expires
                    ));
                }
            }
        }

        return $handler->handle($request);
    }

    /**
     * @return Pool
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function getPool()
    {
        return $this->app->getContainer()->get('pool');
    }

    /**
     * Get the lock key
     * @return mixed
     */
    private function getKey()
    {
        return $this->layoutId;
    }
}