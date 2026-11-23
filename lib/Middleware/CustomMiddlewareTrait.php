<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */
namespace Xibo\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

/**
 * Trait CustomMiddlewareTrait
 * Add this trait to all custom middleware
 * @package Xibo\Middleware
 */
trait CustomMiddlewareTrait
{
    /** @var \Slim\App */
    private $app;

    /**
     * @param \Slim\App $app
     * @return $this
     */
    public function setApp(App $app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * @return \Slim\App
     */
    protected function getApp()
    {
        return $this->app;
    }

    /**
     * @return \DI\Container|\Psr\Container\ContainerInterface
     */
    protected function getContainer()
    {
        return $this->app->getContainer();
    }

    /**
     * @param $key
     * @return mixed
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function getFromContainer($key): mixed
    {
        return $this->getContainer()->get($key);
    }

    /**
     * Append public routes
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param array $routes
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function appendPublicRoutes(ServerRequestInterface $request, array $routes): ServerRequestInterface
    {
        // Set some public routes
        return $request->withAttribute(
            'publicRoutes',
            array_merge($request->getAttribute('publicRoutes', []), $routes)
        );
    }
}
