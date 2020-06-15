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

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Interface AuthenticationInterface
 * @package Xibo\Middleware
 */
interface AuthenticationInterface
{
    /**
     * @param \Slim\App $app
     * @return mixed
     */
    public function setDependencies(App $app);

    /**
     * @return $this
     */
    public function addRoutes();

    /**
     * @param Request $request
     * @return \Psr\Http\Message\ResponseInterface|\Slim\Http\Response
     */
    public function redirectToLogin(Request $request);

    /**
     * @param Request $request
     * @return array
     */
    public function getPublicRoutes(Request $request);

    /**
     * Should this public route be redirected to login when the session is expired?
     * @param string $route
     * @return bool
     */
    public function shouldRedirectPublicRoute($route);

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return Request
     */
    public function addToRequest(Request $request);
}